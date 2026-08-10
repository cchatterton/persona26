<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Persona26 Meta Boxes (scalable)
 *
 * - Adds a native WP metabox to post types selected in "Content Profiling Scope"
 * - Renders ONE tag-picker per Engagement Dimension (unlimited rows)
 * - Stores selections in ONE meta key: p26_alignment
 * - Mirrors selections into queryable scalar meta rows keyed by exact CPT name
 *
 * Meta shape:
 *   [
 *     'dims' => [
 *        'd0' => [12,45],   // dimension 0 selected post IDs
 *        'd1' => [7],
 *        'd2' => [3,9],
 *     ]
 *   ]
 */

if (!defined('P26_SETTINGS_OPTION')) {
    define('P26_SETTINGS_OPTION', 'p26_settings');
}

if (!defined('P26_ALIGNMENT_META')) {
    define('P26_ALIGNMENT_META', 'p26_alignment');
}

if (!defined('P26_META_NONCE')) {
    define('P26_META_NONCE', 'p26_alignment_nonce');
}

if (!defined('P26_ALIGNMENT_MIRRORS_META')) {
    define('P26_ALIGNMENT_MIRRORS_META', '_p26_alignment_mirrors');
}

if (!defined('P26_ALIGNMENT_MIGRATION_VERSION_OPTION')) {
    define('P26_ALIGNMENT_MIGRATION_VERSION_OPTION', 'p26_alignment_mirror_version');
}

if (!defined('P26_ALIGNMENT_MIGRATION_CURSOR_OPTION')) {
    define('P26_ALIGNMENT_MIGRATION_CURSOR_OPTION', 'p26_alignment_mirror_cursor');
}

if (!defined('P26_ALIGNMENT_MIGRATION_VERSION')) {
    define('P26_ALIGNMENT_MIGRATION_VERSION', '1');
}

/* ---------------------------------------------------------
 * Settings helpers
 * --------------------------------------------------------- */
function p26_settings(): array {
    $settings = get_option(P26_SETTINGS_OPTION);
    return is_array($settings) ? $settings : [];
}

function p26_profiled_post_types(): array {
    $settings = p26_settings();
    $pts = $settings['content_post_types'] ?? [];
    if (!is_array($pts)) {
        return [];
    }

    return array_values(
        array_filter(
            array_map('strval', $pts),
            'post_type_exists'
        )
    );
}

/**
 * Returns dimensions from options repeater.
 * Each entry:
 *   [
 *     'key'      => 'd0',
 *     'post_type'=> 'page',
 *     'context'  => 'Audience',
 *     'label'    => 'Audience (page)',
 *   ]
 */
function p26_dimensions(): array {
    $settings = p26_settings();
    $tracked = $settings['tracked'] ?? [];
    if (!is_array($tracked)) {
        $tracked = [];
    }

    $dims = [];
    $i = 0;

    foreach ($tracked as $row) {
        if (!is_array($row)) {
            continue;
        }

        $pt = (string) ($row['post_type'] ?? '');
        $cx = sanitize_text_field($row['context'] ?? '');

        // allow empty rows, but don't render if no post_type
        if (!$pt || !post_type_exists($pt)) {
            $i++;
            continue;
        }

        $dims[] = [
            'key'       => 'd' . $i,
            'post_type' => $pt,
            'context'   => $cx ?: ('Dimension ' . ($i + 1)),
            'label'     => ($cx ?: ('Dimension ' . ($i + 1))) . ' (' . $pt . ')',
        ];

        $i++;
    }

    return $dims;
}

function p26_fetch_dimension_items(string $post_type, int $limit = 200): array {
    if (!$post_type) {
        return [];
    }

    $posts = get_posts([
        'post_type'      => $post_type,
        'posts_per_page' => $limit,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ]);

    $items = [];
    foreach ($posts as $p) {
        $items[] = [
            'id'    => (int) $p->ID,
            'title' => (string) $p->post_title,
        ];
    }
    return $items;
}

/**
 * Normalise the current and legacy alignment shapes.
 */
function p26_normalize_alignment(array $alignment): array {
    if (isset($alignment['dims']) && is_array($alignment['dims'])) {
        return array('dims' => $alignment['dims']);
    }

    $dims = array();

    if (isset($alignment['primary']) && is_array($alignment['primary'])) {
        $dims['d0'] = array_values(array_unique(array_map('intval', $alignment['primary'])));
    }

    if (isset($alignment['secondary']) && is_array($alignment['secondary'])) {
        $dims['d1'] = array_values(array_unique(array_map('intval', $alignment['secondary'])));
    }

    return array('dims' => $dims);
}

/**
 * Return canonical, published dimension selections grouped by dimension key.
 */
function p26_validate_alignment_dimensions(array $submitted_dims): array {
    $dimensions = array_column(p26_dimensions(), null, 'key');
    $clean = array();

    foreach ($submitted_dims as $dim_key => $ids) {
        $dim_key = sanitize_key((string) $dim_key);
        if (!isset($dimensions[$dim_key]) || !is_array($ids)) {
            continue;
        }

        $valid_ids = array();
        foreach (array_unique(array_map('intval', $ids)) as $selected_id) {
            $selected_post = get_post($selected_id);
            if (
                !$selected_post
                || 'publish' !== $selected_post->post_status
                || $dimensions[$dim_key]['post_type'] !== $selected_post->post_type
            ) {
                continue;
            }

            $valid_ids[] = $selected_id;
        }

        if ($valid_ids) {
            $clean[$dim_key] = array_values($valid_ids);
        }
    }

    return $clean;
}

/**
 * Build exact, human-readable mirror values keyed by registered post type.
 */
function p26_build_alignment_mirrors(array $alignment): array {
    $alignment = p26_normalize_alignment($alignment);
    $dimensions = array_column(p26_dimensions(), null, 'key');
    $mirrors = array();

    foreach ($alignment['dims'] as $dim_key => $ids) {
        if (!isset($dimensions[$dim_key]) || !is_array($ids)) {
            continue;
        }

        $meta_key = $dimensions[$dim_key]['post_type'];
        foreach (array_unique(array_map('intval', $ids)) as $selected_id) {
            $selected_post = get_post($selected_id);
            if (
                !$selected_post
                || 'publish' !== $selected_post->post_status
                || $meta_key !== $selected_post->post_type
            ) {
                continue;
            }

            $value = (string) $selected_post->post_title;
            if ('' !== $value) {
                $mirrors[$meta_key][$value] = true;
            }
        }
    }

    return array_map('array_keys', $mirrors);
}

/**
 * Replace only Persona26-owned mirror values, preserving unrelated post meta.
 */
function p26_sync_alignment_mirrors(int $post_id, array $alignment): void {
    $previous_mirrors = get_post_meta($post_id, P26_ALIGNMENT_MIRRORS_META, true);
    $previous_mirrors = is_array($previous_mirrors) ? $previous_mirrors : array();

    foreach ($previous_mirrors as $meta_key => $values) {
        if (!is_string($meta_key) || !is_array($values)) {
            continue;
        }

        foreach ($values as $value) {
            delete_post_meta($post_id, $meta_key, $value);
        }
    }

    $profiled_post_type = get_post_type($post_id);
    $mirrors = in_array($profiled_post_type, p26_profiled_post_types(), true)
        ? p26_build_alignment_mirrors($alignment)
        : array();
    foreach ($mirrors as $meta_key => $values) {
        foreach ($values as $value) {
            add_post_meta($post_id, $meta_key, $value, false);
        }
    }

    if ($mirrors) {
        update_post_meta($post_id, P26_ALIGNMENT_MIRRORS_META, $mirrors);
    } else {
        delete_post_meta($post_id, P26_ALIGNMENT_MIRRORS_META);
    }
}

/**
 * Register mirror keys for block-editor and REST consumers without replacing
 * registrations owned by another plugin.
 */
function p26_register_alignment_mirror_meta(): void {
    $mirror_keys = array_unique(array_column(p26_dimensions(), 'post_type'));

    foreach (p26_profiled_post_types() as $profiled_post_type) {
        foreach ($mirror_keys as $meta_key) {
            if (registered_meta_key_exists('post', $meta_key, $profiled_post_type)) {
                continue;
            }

            register_post_meta(
                $profiled_post_type,
                $meta_key,
                array(
                    'type'              => 'string',
                    'single'            => false,
                    'show_in_rest'      => true,
                    'sanitize_callback' => 'sanitize_text_field',
                )
            );
        }
    }
}
add_action('init', 'p26_register_alignment_mirror_meta', 20);

/* ---------------------------------------------------------
 * Metabox registration
 * --------------------------------------------------------- */
function p26_register_alignment_metaboxes(): void {
    $post_types = p26_profiled_post_types();
    if (empty($post_types)) {
        return;
    }

    foreach ($post_types as $pt) {
        add_meta_box(
            'p26-intended-engagement-alignment',
            'Persona Targets',
            'p26_render_alignment_metabox',
            $pt,
            'side',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'p26_register_alignment_metaboxes');

/* ---------------------------------------------------------
 * Render metabox
 * --------------------------------------------------------- */
function p26_render_alignment_metabox(\WP_Post $post): void {
    $dims = p26_dimensions();

    if (empty($dims)) {
        echo '<p><em>Set your Engagement Dimensions in Persona26 first.</em></p>';
        return;
    }

    $saved = get_post_meta($post->ID, P26_ALIGNMENT_META, true);
    $saved = is_array($saved) ? $saved : [];
    $saved = p26_normalize_alignment($saved);

    wp_nonce_field('p26_save_alignment', P26_META_NONCE);

    $uid = 'p26_' . $post->ID . '_' . wp_rand(1000, 9999);

    foreach ($dims as $dim) {
        $dim_key = $dim['key'];
        $items   = p26_fetch_dimension_items($dim['post_type']);
        $selected = $saved['dims'][$dim_key] ?? [];
        $selected = is_array($selected) ? array_values(array_unique(array_map('intval', $selected))) : [];

        echo '<div class="p26-section" id="' . esc_attr($uid . '_' . $dim_key) . '">';
        echo '<h4 class="p26-label">' . esc_html($dim['label']) . '</h4>';
        p26_render_tagpicker_ui(
            $uid . '_' . $dim_key,
            'p26_alignment_dims[' . $dim_key . ']',
            $items,
            $selected
        );
        echo '</div>';
    }

    echo '<p class="p26-help">These selections power the <strong>Actual</strong> matrix.</p>';
}

/**
 * Render a tag-picker UI.
 *
 * @param string $id Unique DOM id base
 * @param string $field Base field name (without [])
 * @param array  $items [{id,title}]
 * @param array  $selected_ids [int...]
 */
function p26_render_tagpicker_ui(string $id, string $field, array $items, array $selected_ids): void {
    $selected_ids = array_map('intval', $selected_ids);
    $selected_map = array_flip($selected_ids);
    $listbox_id = $id . '_options';

    echo '<div class="p26-tagbox" id="' . esc_attr($id) . '_picker" data-field="' . esc_attr($field) . '">';
    echo '<div class="p26-tags" aria-live="polite">';

    foreach ($items as $it) {
        $pid = (int)$it['id'];
        if (!isset($selected_map[$pid])) {
            continue;
        }

        echo '<span class="p26-tag" data-id="' . esc_attr((string) $pid) . '">';
        echo '<span class="p26-tag-label">' . esc_html($it['title']) . '</span>';
        echo '<button type="button" class="p26-tag-remove" aria-label="' . esc_attr(sprintf('Remove %s', $it['title'])) . '">×</button>';
        echo '</span>';
    }

    echo '</div>';
    echo '<button type="button" class="button p26-picker-toggle" aria-expanded="false" aria-controls="' . esc_attr($listbox_id) . '">Click to add…</button>';
    echo '<div class="p26-dropdown" id="' . esc_attr($listbox_id) . '" hidden>';
    foreach ($items as $it) {
        $pid = (int) $it['id'];
        $hidden = isset($selected_map[$pid]) ? ' hidden' : '';
        echo '<button type="button" class="p26-option" data-id="' . esc_attr((string) $pid) . '" data-label="' . esc_attr($it['title']) . '"' . $hidden . '>' . esc_html($it['title']) . '</button>';
    }
    echo '</div>';

    echo '<div class="p26-hidden" hidden>';
    foreach ($selected_ids as $pid) {
        echo '<input type="hidden" name="' . esc_attr($field) . '[]" value="' . esc_attr((string) $pid) . '" />';
    }
    echo '</div>';

    echo '</div>';
}

/* ---------------------------------------------------------
 * Save handler
 * --------------------------------------------------------- */
function p26_save_alignment_meta($post_id): void {
    $post_id = (int) $post_id;
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
    }

    $nonce = isset($_POST[P26_META_NONCE]) ? sanitize_text_field(wp_unslash($_POST[P26_META_NONCE])) : '';
    if (!wp_verify_nonce($nonce, 'p26_save_alignment')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $post_type = get_post_type($post_id);
    if (!$post_type) {
        return;
    }

    $scoped = p26_profiled_post_types();
    if (!in_array($post_type, $scoped, true)) {
        return;
    }

    $posted_dims = isset($_POST['p26_alignment_dims']) ? (array) wp_unslash($_POST['p26_alignment_dims']) : [];
    $clean = p26_validate_alignment_dimensions($posted_dims);

    if (empty($clean)) {
        delete_post_meta($post_id, P26_ALIGNMENT_META);
        p26_sync_alignment_mirrors($post_id, array());
        return;
    }

    $alignment = array('dims' => $clean);
    update_post_meta($post_id, P26_ALIGNMENT_META, $alignment);
    p26_sync_alignment_mirrors($post_id, $alignment);
}
add_action('save_post', 'p26_save_alignment_meta', 10, 1);

/**
 * Queue a complete mirror refresh after dimension settings change.
 */
function p26_queue_alignment_mirror_migration(): void {
    delete_option(P26_ALIGNMENT_MIGRATION_VERSION_OPTION);
    delete_option(P26_ALIGNMENT_MIGRATION_CURSOR_OPTION);

    if (!wp_next_scheduled('p26_alignment_mirror_migration_batch')) {
        wp_schedule_single_event(time() + 1, 'p26_alignment_mirror_migration_batch');
    }
}

/**
 * Backfill existing serialized alignments in small, resumable admin batches.
 */
function p26_migrate_alignment_mirrors(): void {
    if (!current_user_can('manage_options')) {
        return;
    }

    p26_process_alignment_mirror_migration_batch();
}
add_action('admin_init', 'p26_migrate_alignment_mirrors');

/**
 * Process one migration batch and schedule the next batch when needed.
 */
function p26_process_alignment_mirror_migration_batch(): void {
    global $wpdb;

    if (P26_ALIGNMENT_MIGRATION_VERSION === get_option(P26_ALIGNMENT_MIGRATION_VERSION_OPTION)) {
        return;
    }

    $cursor = (int) get_option(P26_ALIGNMENT_MIGRATION_CURSOR_OPTION, 0);
    $post_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = %s
              AND post_id > %d
            ORDER BY post_id ASC
            LIMIT %d",
            P26_ALIGNMENT_META,
            $cursor,
            50
        )
    );

    if (!$post_ids) {
        update_option(P26_ALIGNMENT_MIGRATION_VERSION_OPTION, P26_ALIGNMENT_MIGRATION_VERSION, false);
        delete_option(P26_ALIGNMENT_MIGRATION_CURSOR_OPTION);
        return;
    }

    foreach ($post_ids as $post_id) {
        $post_id = (int) $post_id;
        $alignment = get_post_meta($post_id, P26_ALIGNMENT_META, true);
        if (is_array($alignment)) {
            p26_sync_alignment_mirrors($post_id, $alignment);
        }
        $cursor = max($cursor, $post_id);
    }

    update_option(P26_ALIGNMENT_MIGRATION_CURSOR_OPTION, $cursor, false);

    if (!wp_next_scheduled('p26_alignment_mirror_migration_batch')) {
        wp_schedule_single_event(time() + 10, 'p26_alignment_mirror_migration_batch');
    }
}
add_action('p26_alignment_mirror_migration_batch', 'p26_process_alignment_mirror_migration_batch');

/**
 * Refresh content mirrors when a selected dimension title or status changes.
 */
function p26_refresh_mirrors_for_dimension_item($post_id, $post, $update): void {
    global $wpdb;

    if (!$update || !$post instanceof WP_Post || wp_is_post_revision($post_id)) {
        return;
    }

    $dimension_post_types = array_unique(array_column(p26_dimensions(), 'post_type'));
    if (!in_array($post->post_type, $dimension_post_types, true)) {
        return;
    }

    $serialized_id = 'i:' . (int) $post_id . ';';
    $reference_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = %s
              AND meta_value LIKE %s",
            P26_ALIGNMENT_META,
            '%' . $wpdb->esc_like($serialized_id) . '%'
        )
    );

    foreach (array_unique(array_map('intval', $reference_ids)) as $reference_id) {
        $alignment = get_post_meta($reference_id, P26_ALIGNMENT_META, true);
        if (!is_array($alignment)) {
            continue;
        }

        $normalized = p26_normalize_alignment($alignment);
        $contains_id = false;
        foreach ($normalized['dims'] as $ids) {
            if (is_array($ids) && in_array((int) $post_id, array_map('intval', $ids), true)) {
                $contains_id = true;
                break;
            }
        }

        if ($contains_id) {
            p26_sync_alignment_mirrors($reference_id, $alignment);
        }
    }
}
add_action('save_post', 'p26_refresh_mirrors_for_dimension_item', 20, 3);
