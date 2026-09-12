<?php
if (!defined('ABSPATH')) exit;

/**
 * Persona26 Profile
 *
 * Uses:
 * - p26_profiled_post_types()
 * - p26_dimensions()
 * - P26_ALIGNMENT_META / p26_alignment
 *
 * Stores:
 * - localStorage: p26_profile
 * - cookie mirror: p26_profile (best effort)
 *
 * Query strings:
 * - ?persona=show  => update profile, then show debug
 * - ?persona=get   => show debug only, do not update
 * - ?persona=clear => clear profile and reload clean
 */

class P26_Profile {

    const FOREVER = 630720000; // 20 years

    public static function boot(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('body_class', [__CLASS__, 'add_persona_body_classes'], 20, 1);
    }

    protected static function storage_key(string $key): string {
        return 'p26_' . $key;
    }

    protected static function alignment_meta_key(): string {
        return defined('P26_ALIGNMENT_META') ? P26_ALIGNMENT_META : 'p26_alignment';
    }

    protected static function current_post(): ?WP_Post {
        if (is_admin() || !is_singular()) return null;
        $post = get_queried_object();
        return ($post instanceof WP_Post) ? $post : null;
    }

    protected static function profiled_post_types(): array {
        if (!function_exists('p26_profiled_post_types')) return [];
        $pts = p26_profiled_post_types();
        return is_array($pts) ? $pts : [];
    }

    protected static function dimensions(): array {
        if (!function_exists('p26_dimensions')) return [];
        $dims = p26_dimensions();
        return is_array($dims) ? $dims : [];
    }

    protected static function is_enabled_post_type(?WP_Post $post): bool {
        if (!$post) return false;
        return in_array($post->post_type, self::profiled_post_types(), true);
    }

    protected static function saved_alignment(int $post_id): array {
        $saved = get_post_meta($post_id, self::alignment_meta_key(), true);
        $saved = is_array($saved) ? $saved : [];

        if (!isset($saved['dims']) || !is_array($saved['dims'])) {
            $saved['dims'] = [];
        }

        return $saved;
    }

    /**
     * Return post_name slugs for selected dimension items.
     */
    protected static function slugs_from_ids(array $ids, string $post_type): array {
        $out = [];

        foreach ($ids as $id) {
            $id = (int) $id;
            if (!$id) continue;

            $p = get_post($id);
            if (!$p instanceof WP_Post) continue;
            if ($p->post_type !== $post_type) continue;
            if ($p->post_status !== 'publish') continue;

            $slug = trim((string) $p->post_name);
            if ($slug !== '') {
                $out[] = $slug;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Output shape:
     * [
     *   'order' => ['d0','d1'],
     *   'labels' => ['d0' => 'Audience', 'd1' => 'Interests'],
     *   'dimensions' => [
     *      'd0' => ['parent', 'carer'],
     *      'd1' => ['giving']
     *   ]
     * ]
     */
    protected static function page_data(): array {
        $empty = [
            'order'      => [],
            'labels'     => [],
            'dimensions' => [],
        ];

        $post = self::current_post();
        if (!$post) return $empty;
        if (!self::is_enabled_post_type($post)) return $empty;

        $dims = self::dimensions();
        if (empty($dims)) return $empty;

        $saved = self::saved_alignment((int) $post->ID);
        $map   = $saved['dims'] ?? [];
        if (empty($map) || !is_array($map)) return $empty;

        foreach ($dims as $dim) {
            if (!is_array($dim)) continue;

            $dim_key  = sanitize_key($dim['key'] ?? '');
            $dim_pt   = (string) ($dim['post_type'] ?? '');
            $dim_name = sanitize_text_field($dim['context'] ?? '');

            if (!$dim_key || !$dim_pt || !post_type_exists($dim_pt)) continue;

            $ids = $map[$dim_key] ?? [];
            if (!is_array($ids) || empty($ids)) continue;

            $ids    = array_values(array_unique(array_filter(array_map('intval', $ids))));
            $values = self::slugs_from_ids($ids, $dim_pt);

            if (empty($values)) continue;

            $empty['order'][] = $dim_key;
            $empty['labels'][$dim_key] = $dim_name ?: $dim_key;
            $empty['dimensions'][$dim_key] = $values;
        }

        return $empty;
    }

    public static function enqueue_assets(): void {
        if (is_admin()) return;
        $action = isset($_GET['persona']) && is_string($_GET['persona']) ? sanitize_key(wp_unslash($_GET['persona'])) : '';
        $data = self::page_data();
        $data['order'] = array_column(self::dimensions(), 'key');
        wp_enqueue_script('p26-profile', P26_PLUGIN_URL . 'scripts/persona26-profile.js', array('p26-identity'), P26_VERSION, false);
        wp_add_inline_script('p26-profile', 'window.p26PageProfileData=' . wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';window.p26ProfileAction=' . wp_json_encode($action) . ';', 'before');
        if (in_array($action, array('show', 'get', 'clear'), true)) {
            wp_enqueue_style('p26-profile-debug', P26_PLUGIN_URL . 'styles/persona26-front.css', array(), P26_VERSION);
        }
    }

    public static function add_persona_body_classes(array $classes): array {
        if (is_admin()) return $classes;

        $cookie_key = self::storage_key('profile');
        $raw = isset($_COOKIE[$cookie_key]) && is_string($_COOKIE[$cookie_key]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookie_key])) : '';
        if (!$raw || !is_string($raw)) return $classes;

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['persona']) || !is_string($decoded['persona'])) {
            return $classes;
        }

        $parts = preg_split('/\s*,\s*|\s*\|\s*/', $decoded['persona']);
        if (!is_array($parts) || empty($parts)) return $classes;

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') continue;

            $class = sanitize_html_class($part);
            if ($class !== '') {
                $classes[] = $class;
            }
        }

        return array_values(array_unique($classes));
    }

}

P26_Profile::boot();
