<?php
if (!defined('ABSPATH')) exit;

/**
 * Persona26 Personalization CSS
 *
 * Builds CSS rules for all published posts in configured dimension post types.
 * Outputs them inline on the front end.
 *
 * Rule pattern:
 * body:not(.resource-1) .resource-1 { display:none !important; }
 */

const P26_PERSONALIZE_CSS_OPTION = 'p26_personalize_css';

function p26_personalize_dimension_post_types(): array {
    if (!function_exists('p26_dimensions')) return [];

    $dims = p26_dimensions();
    if (!is_array($dims)) return [];

    $post_types = [];

    foreach ($dims as $dim) {
        if (!is_array($dim)) continue;

        $pt = (string) ($dim['post_type'] ?? '');
        if ($pt && post_type_exists($pt)) {
            $post_types[] = $pt;
        }
    }

    return array_values(array_unique($post_types));
}

function p26_personalize_dimension_slugs(): array {
    $post_types = p26_personalize_dimension_post_types();
    if (empty($post_types)) return [];

    $posts = get_posts([
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ]);

    $slugs = [];

    foreach ($posts as $post) {
        $slug = sanitize_title($post->post_name ?: $post->post_title);
        if ($slug) {
            $slugs[$slug] = true;
        }
    }

    return array_keys($slugs);
}

function p26_rebuild_personalize_css(): void {
    $slugs = p26_personalize_dimension_slugs();

    if (empty($slugs)) {
        update_option(P26_PERSONALIZE_CSS_OPTION, '', true);
        return;
    }

    $css = [];
    $css[] = '/* Persona26 Personalization */';

    foreach ($slugs as $slug) {
        $css[] = 'body:not(.' . $slug . ') .' . $slug . ' { display:none !important; }';
    }

    update_option(P26_PERSONALIZE_CSS_OPTION, implode("\n", $css), true);
}

function p26_output_personalize_css(): void {
    if (is_admin()) return;

    $css = get_option(P26_PERSONALIZE_CSS_OPTION, '');
    if (!$css || !is_string($css)) return;

    wp_register_style('p26-personalize', false, array(), P26_VERSION);
    wp_enqueue_style('p26-personalize');
    wp_add_inline_style('p26-personalize', wp_strip_all_tags($css));
}
add_action('wp_enqueue_scripts', 'p26_output_personalize_css', 20);

function p26_personalize_should_rebuild_for_post(int $post_id): bool {
    $post_type = get_post_type($post_id);
    if (!$post_type) return false;

    return in_array($post_type, p26_personalize_dimension_post_types(), true);
}

function p26_maybe_rebuild_personalize_css_for_saved_post($post_id): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!p26_personalize_should_rebuild_for_post((int) $post_id)) return;

    p26_rebuild_personalize_css();
}
add_action('save_post', 'p26_maybe_rebuild_personalize_css_for_saved_post', 20);

function p26_maybe_rebuild_personalize_css_for_deleted_post($post_id, $post): void {
    if (!$post instanceof WP_Post || !in_array($post->post_type, p26_personalize_dimension_post_types(), true)) return;
    p26_rebuild_personalize_css();
}
add_action('after_delete_post', 'p26_maybe_rebuild_personalize_css_for_deleted_post', 20, 2);

function p26_maybe_rebuild_personalize_css_for_trashed_post($post_id): void {
    if (!p26_personalize_should_rebuild_for_post((int) $post_id)) return;
    p26_rebuild_personalize_css();
}
add_action('trashed_post', 'p26_maybe_rebuild_personalize_css_for_trashed_post', 20);

function p26_maybe_rebuild_personalize_css_for_untrashed_post($post_id): void {
    if (!p26_personalize_should_rebuild_for_post((int) $post_id)) return;
    p26_rebuild_personalize_css();
}
add_action('untrashed_post', 'p26_maybe_rebuild_personalize_css_for_untrashed_post', 20);
