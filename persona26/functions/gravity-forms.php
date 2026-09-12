<?php
/**
 * Gravity Forms integration for Persona26.
 */

if (!defined('ABSPATH')) {
    exit;
}

const P26_GF_PENDING_COOKIE = 'p26_pending_profile';

add_action('gform_loaded', array('P26_Gravity_Forms_Bootstrap', 'load'), 5);
add_action('wp_enqueue_scripts', 'p26_output_gravity_forms_pending_profile_script', 20);
add_filter('gform_pre_render', 'p26_populate_gravity_forms_dimension_fields');
add_filter('gform_pre_validation', 'p26_populate_gravity_forms_dimension_fields');
add_filter('gform_pre_submission_filter', 'p26_populate_gravity_forms_dimension_fields');
add_filter('gform_admin_pre_render', 'p26_populate_gravity_forms_dimension_fields');

final class P26_Gravity_Forms_Bootstrap {
    public static function load(): void {
        if (!class_exists('GFForms') || !method_exists('GFForms', 'include_feed_addon_framework')) {
            return;
        }

        GFForms::include_feed_addon_framework();

        if (!class_exists('GFFeedAddOn') || class_exists('P26_Gravity_Forms_Addon')) {
            return;
        }

        require_once P26_PLUGIN_DIR . 'functions/gravity-forms-addon.php';

        GFAddOn::register('P26_Gravity_Forms_Addon');
    }
}

if (did_action('gform_loaded')) {
    P26_Gravity_Forms_Bootstrap::load();
}

function p26_gravity_forms_dimensions(): array {
    if (!function_exists('p26_dimensions')) {
        return array();
    }

    $dimensions = p26_dimensions();
    if (!is_array($dimensions)) {
        return array();
    }

    $out = array();

    foreach ($dimensions as $dimension) {
        if (!is_array($dimension)) {
            continue;
        }

        $key = sanitize_key((string) ($dimension['key'] ?? ''));
        $post_type = (string) ($dimension['post_type'] ?? '');

        if ('' === $key || '' === $post_type || !post_type_exists($post_type)) {
            continue;
        }

        $out[] = array(
            'key' => $key,
            'post_type' => $post_type,
            'context' => sanitize_text_field((string) ($dimension['context'] ?? '')),
            'label' => sanitize_text_field((string) ($dimension['label'] ?? $key)),
        );
    }

    return $out;
}

function p26_gravity_forms_dimension(string $dim_key): array {
    foreach (p26_gravity_forms_dimensions() as $dimension) {
        if ($dim_key === $dimension['key']) {
            return $dimension;
        }
    }

    return array();
}

function p26_gravity_forms_dimension_by_token(string $token): array {
    $token = sanitize_title($token);
    if ('' === $token) {
        return array();
    }

    foreach (p26_gravity_forms_dimensions() as $dimension) {
        $key = sanitize_title((string) $dimension['key']);
        $post_type = sanitize_title((string) $dimension['post_type']);
        $context = sanitize_title((string) $dimension['context']);
        $label = sanitize_title((string) $dimension['label']);

        if ($token === $key || $token === $post_type || $token === $context || $token === $label) {
            return $dimension;
        }
    }

    return array();
}

function p26_gravity_forms_dimension_order(): array {
    return array_values(array_map(
        static fn($dimension) => (string) $dimension['key'],
        p26_gravity_forms_dimensions()
    ));
}

function p26_gravity_forms_resolve_value(string $raw_value, array $dimension, string $value_mode): string {
    if ('submitted_slug' === $value_mode) {
        return sanitize_title($raw_value);
    }

    $post_type = (string) ($dimension['post_type'] ?? '');
    if ('' === $post_type || !post_type_exists($post_type)) {
        return '';
    }

    $needle_slug = sanitize_title($raw_value);
    $needle_title = strtolower(trim($raw_value));

    $posts = get_posts(array(
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => 500,
        'orderby' => 'title',
        'order' => 'ASC',
        'no_found_rows' => true,
    ));

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $post_slug = sanitize_title((string) $post->post_name);
        $post_title = strtolower(trim((string) $post->post_title));

        if ($needle_slug === $post_slug || $needle_title === $post_title || $needle_slug === sanitize_title((string) $post->post_title)) {
            return $post_slug;
        }
    }

    return '';
}

function p26_gravity_forms_dimension_choices(array $dimension): array {
    $post_type = (string) ($dimension['post_type'] ?? '');
    if ('' === $post_type || !post_type_exists($post_type)) {
        return array();
    }

    $posts = get_posts(array(
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => 500,
        'orderby' => 'title',
        'order' => 'ASC',
        'no_found_rows' => true,
    ));

    $choices = array();

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $label = trim((string) $post->post_title);
        $value = sanitize_title((string) $post->post_name);

        if ('' === $label || '' === $value) {
            continue;
        }

        $choices[] = array(
            'text' => $label,
            'value' => $value,
        );
    }

    return $choices;
}

function p26_gravity_forms_cookie_profile(): array {
    $raw = isset($_COOKIE['p26_profile']) && is_string($_COOKIE['p26_profile']) ? sanitize_text_field(wp_unslash($_COOKIE['p26_profile'])) : '';
    if ('' === $raw) {
        return array();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return array();
    }

    return $decoded;
}

function p26_gravity_forms_profile_dimension_values(string $dim_key): array {
    $dim_key = sanitize_key($dim_key);
    if ('' === $dim_key) {
        return array();
    }

    $profile = p26_gravity_forms_cookie_profile();
    $counters = isset($profile['counters']) && is_array($profile['counters']) ? $profile['counters'] : array();
    $dimension_counts = isset($counters[$dim_key]) && is_array($counters[$dim_key]) ? $counters[$dim_key] : array();

    if (!empty($dimension_counts)) {
        $max = 0;
        $values = array();

        foreach ($dimension_counts as $value => $count) {
            $value = sanitize_title((string) $value);
            $count = (int) $count;

            if ('' === $value || $count <= 0) {
                continue;
            }

            if ($count > $max) {
                $max = $count;
                $values = array($value);
            } elseif ($count === $max) {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    $persona = isset($profile['persona']) && is_string($profile['persona']) ? $profile['persona'] : '';
    if ('' === $persona) {
        return array();
    }

    $parts = preg_split('/\s*,\s*|\s*\|\s*/', $persona);
    if (!is_array($parts)) {
        return array();
    }

    return array_values(array_unique(array_filter(array_map('sanitize_title', $parts))));
}

function p26_gravity_forms_apply_default_choices(array $choices, array $default_values): array {
    $default_map = array_flip(array_map('sanitize_title', $default_values));

    foreach ($choices as &$choice) {
        $value = sanitize_title((string) ($choice['value'] ?? ''));
        $choice['isSelected'] = isset($default_map[$value]);
    }
    unset($choice);

    return $choices;
}

function p26_populate_gravity_forms_dimension_fields($form) {
    if (empty($form['fields']) || !is_array($form['fields'])) {
        return $form;
    }

    foreach ($form['fields'] as &$field) {
        if (!p26_gravity_forms_is_dynamic_choice_field($field)) {
            continue;
        }

        $dimension = p26_gravity_forms_field_dimension($field);
        if (empty($dimension)) {
            continue;
        }

        $choices = p26_gravity_forms_dimension_choices($dimension);
        if (empty($choices)) {
            continue;
        }

        $default_values = is_admin()
            ? array()
            : p26_gravity_forms_profile_dimension_values((string) $dimension['key']);
        if (!empty($default_values)) {
            $choices = p26_gravity_forms_apply_default_choices($choices, $default_values);
        }

        $field->choices = $choices;

        if ('checkbox' === $field->type) {
            $field->inputs = p26_gravity_forms_checkbox_inputs((int) $field->id, $choices);
        } elseif (!empty($default_values)) {
            $field->defaultValue = (string) $default_values[0];
        }
    }
    unset($field);

    return $form;
}

function p26_gravity_forms_is_dynamic_choice_field($field): bool {
    if (!is_object($field) || empty($field->type)) {
        return false;
    }

    return in_array((string) $field->type, array('checkbox', 'radio'), true);
}

function p26_gravity_forms_field_dimension($field): array {
    $css_class = is_object($field) && isset($field->cssClass) ? (string) $field->cssClass : '';
    if ('' === trim($css_class)) {
        return array();
    }

    $classes = preg_split('/\s+/', trim($css_class));
    if (!is_array($classes)) {
        return array();
    }

    foreach ($classes as $class) {
        $class = sanitize_html_class((string) $class);

        if (str_starts_with($class, 'p26-dimension-')) {
            $token = substr($class, strlen('p26-dimension-'));
            return p26_gravity_forms_dimension_by_token($token);
        }
    }

    return array();
}

function p26_gravity_forms_checkbox_inputs(int $field_id, array $choices): array {
    $inputs = array();
    $input_index = 1;

    foreach ($choices as $choice) {
        if (0 === $input_index % 10) {
            $input_index++;
        }

        $inputs[] = array(
            'id' => $field_id . '.' . $input_index,
            'label' => (string) ($choice['text'] ?? ''),
            'name' => '',
        );

        $input_index++;
    }

    return $inputs;
}

function p26_set_gravity_forms_pending_profile_cookie(array $updates): void {
    if (headers_sent()) {
        return;
    }

    $updates = array_values(array_filter($updates, 'is_array'));
    if (empty($updates)) {
        return;
    }

    $encoded = base64_encode((string) wp_json_encode($updates));

    setcookie(
        P26_GF_PENDING_COOKIE,
        $encoded,
        array(
            'expires' => time() + 10 * MINUTE_IN_SECONDS,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        )
    );

    $_COOKIE[P26_GF_PENDING_COOKIE] = $encoded;
}

function p26_gravity_forms_profile_update_script(array $updates, bool $clear_pending_cookie): string {
    $updates = array_values(array_filter($updates, 'is_array'));
    if (empty($updates)) {
        return '';
    }

    $json = wp_json_encode($updates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        return '';
    }
    return '<script>window.p26ApplyProfileUpdates(' . $json . ', ' . ($clear_pending_cookie ? 'true' : 'false') . ');</script>';
}

function p26_output_gravity_forms_pending_profile_script(): void {
    if (is_admin() || empty($_COOKIE[P26_GF_PENDING_COOKIE]) || !is_string($_COOKIE[P26_GF_PENDING_COOKIE])) {
        return;
    }

    $raw = sanitize_text_field(wp_unslash($_COOKIE[P26_GF_PENDING_COOKIE]));
    $decoded = base64_decode($raw, true);
    if (!is_string($decoded) || '' === $decoded) {
        return;
    }

    $updates = json_decode($decoded, true);
    if (!is_array($updates)) {
        return;
    }

    wp_add_inline_script('p26-profile', 'window.p26ApplyProfileUpdates(' . wp_json_encode(array_values(array_filter($updates, 'is_array')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ', true);', 'after');
}
