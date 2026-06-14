<?php
if (!defined('ABSPATH')) exit;

/* =========================================================
 * Constants
 * ======================================================= */

if (!defined('P26_SETTINGS_OPTION')) {
    define('P26_SETTINGS_OPTION', 'p26_settings');
}

if (!defined('P26_ALIGNMENT_META')) {
    define('P26_ALIGNMENT_META', 'p26_alignment');
}

/* =========================================================
 * Table name helpers
 * ======================================================= */

/**
 * Your custom join table name (you said: wp_independent_analytics_p26)
 * Uses the site prefix so it works in multisite / custom prefixes.
 */
function p26_map_table_name(): string {
    global $wpdb;
    return $wpdb->prefix . 'independent_analytics_p26';
}

/**
 * Independent Analytics core tables (per your screenshots).
 */
function p26_ia_table(string $suffix): string {
    global $wpdb;
    return $wpdb->prefix . 'independent_analytics_' . $suffix;
}

/* =========================================================
 * Post Types
 * ======================================================= */

function p26_all_post_types(): array {
    $pts = get_post_types(['public'=>true,'show_ui'=>true],'objects');
    $out = [];

    foreach ($pts as $pt) {
        if (in_array($pt->name, ['attachment','revision','nav_menu_item'], true)) continue;
        $label = $pt->labels->singular_name ?: ($pt->label ?: $pt->name);
        $out[$pt->name] = $label . " ({$pt->name})";
    }

    asort($out);
    return $out;
}

function p26_get_posts(string $post_type, int $limit = 500): array {
    if (!$post_type) return [];
    return get_posts([
        'post_type'      => $post_type,
        'posts_per_page' => $limit,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ]);
}

/* =========================================================
 * Settings
 * ======================================================= */

function p26_get_settings(): array {
    $settings = get_option(P26_SETTINGS_OPTION);
    if (!is_array($settings)) $settings = [];

    $settings['tracked'] = array_values($settings['tracked'] ?? []);

    if (count($settings['tracked']) < 2) {
        $settings['tracked'] = array_pad(
            $settings['tracked'],
            2,
            ['post_type'=>'', 'context'=>'']
        );
    }

    if (empty($settings['tracked'][0]['context'])) $settings['tracked'][0]['context'] = 'Audience';
    if (empty($settings['tracked'][1]['context'])) $settings['tracked'][1]['context'] = 'Interests';

    foreach ($settings['tracked'] as &$row) {
        $row['post_type'] = sanitize_key($row['post_type'] ?? '');
        $row['context']   = sanitize_text_field($row['context'] ?? '');
    }
    unset($row);

    $settings['content_post_types'] = array_values(
        array_map('sanitize_key', $settings['content_post_types'] ?? [])
    );

    return $settings;
}

function p26_scope_post_types(array $settings): array {
    return $settings['content_post_types'] ?? [];
}

/* =========================================================
 * Alignment helpers (NEW SYSTEM ONLY)
 * ======================================================= */

function p26_extract_post_tags(array $alignment): array {
    if (!isset($alignment['dims']) || !is_array($alignment['dims'])) {
        return [];
    }

    $tags = [];
    foreach ($alignment['dims'] as $values) {
        if (!is_array($values)) continue;
        foreach ($values as $id) {
            $tags[(int)$id] = true;
        }
    }

    return $tags; // associative set: [post_id => true]
}

/* =========================================================
 * Actual Heatmap Builder
 * ======================================================= */

function p26_build_actual_heatmap(array $settings, array $rows, array $cols): array {
    global $wpdb;

    $scope = p26_scope_post_types($settings);
    if (!$scope || !$rows || !$cols) {
        return ['counts'=>[], 'max'=>0, 'total'=>0];
    }

    $row_ids = array_map(fn($p) => (int)$p->ID, $rows);
    $col_ids = array_map(fn($p) => (int)$p->ID, $cols);

    $row_set = array_flip($row_ids);
    $col_set = array_flip($col_ids);

    $counts = [];
    foreach ($row_ids as $rid) {
        foreach ($col_ids as $cid) {
            $counts[$rid][$cid] = 0;
        }
    }

    $placeholders = implode(',', array_fill(0, count($scope), '%s'));

    $sql = "
        SELECT pm.meta_value
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = %s
          AND p.post_status = 'publish'
          AND p.post_type IN ($placeholders)
    ";

    $args = array_merge([P26_ALIGNMENT_META], $scope);
    $results = $wpdb->get_results($wpdb->prepare($sql, $args));

    $max = 0;
    $total = 0;

    foreach ($results as $r) {
        $alignment = maybe_unserialize($r->meta_value);
        if (!is_array($alignment)) continue;

        $tags = p26_extract_post_tags($alignment);

        $selected_rows = array_intersect_key($tags, $row_set);
        $selected_cols = array_intersect_key($tags, $col_set);

        if (!$selected_rows && !$selected_cols) continue;

        foreach ($selected_rows ?: [0 => true] as $rid => $_a) {
            foreach ($selected_cols ?: [0 => true] as $cid => $_b) {
        
                if (!$rid || !$cid) continue;
        
                $counts[$rid][$cid]++;
                $total++;
                if ($counts[$rid][$cid] > $max) $max = $counts[$rid][$cid];
            }
        }
    }

    return [
        'counts' => $counts,
        'max'    => $max,
        'total'  => $total,
    ];
}

/* =========================================================
 * Users Heatmap Builder (Unique visitors based on viewed content)
 * ======================================================= */

/**
 * Build a heatmap for the "Users" tab:
 * - Take visitor_ids from wp_independent_analytics_p26
 * - Resolve viewed WP singular IDs via IA sessions -> views -> resources
 * - Union dimension tags across each visitor's consumed posts
 * - Count UNIQUE visitors per cell where visitor has BOTH row+col tags
 *
 * Returns:
 *  [
 *    'counts' => [ rowId => [ colId => int ] ],
 *    'max'    => int,
 *    'visitors_total' => int
 *  ]
 */
function p26_build_users_heatmap(array $settings, array $rows, array $cols): array {
    global $wpdb;

    $scope = p26_scope_post_types($settings);
    if (!$scope || !$rows || !$cols) {
        return ['counts'=>[], 'max'=>0, 'visitors_total'=>0];
    }

    $row_ids = array_map(fn($p) => (int)$p->ID, $rows);
    $col_ids = array_map(fn($p) => (int)$p->ID, $cols);

    $row_set = array_flip($row_ids);
    $col_set = array_flip($col_ids);

    // init matrix
    $counts = [];
    foreach ($row_ids as $rid) {
        foreach ($col_ids as $cid) $counts[$rid][$cid] = 0;
    }

    $map_table = p26_map_table_name();
    $sessions  = p26_ia_table('sessions');
    $views     = p26_ia_table('views');
    $resources = p26_ia_table('resources');

    // 1) get distinct IA visitor IDs from our mapping table
    $visitor_ids = $wpdb->get_col("
        SELECT DISTINCT ia_visitor_id
        FROM {$map_table}
        WHERE ia_visitor_id IS NOT NULL AND ia_visitor_id <> 0
    ");

    $visitor_ids = array_values(array_filter(array_map('intval', (array)$visitor_ids)));
    if (!$visitor_ids) {
        return ['counts'=>$counts, 'max'=>0, 'visitors_total'=>0];
    }

    $visitors_total = count($visitor_ids);

    // We'll batch visitors to avoid giant IN() queries.
    $batch_size = 400;

    $max = 0;

    for ($offset = 0; $offset < $visitors_total; $offset += $batch_size) {
        $batch = array_slice($visitor_ids, $offset, $batch_size);
        if (!$batch) continue;

        $in = implode(',', array_fill(0, count($batch), '%d'));

        // 2) Fetch (visitor_id, singular_id) pairs for all sessions/views in batch
        // Note: We only rely on columns shown in your screenshots:
        // sessions.session_id, sessions.visitor_id
        // views.session_id, views.resource_id
        // resources.resource_id, resources.singular_id
        $sql = "
            SELECT s.visitor_id AS visitor_id, r.singular_id AS post_id
            FROM {$sessions} s
            INNER JOIN {$views} v ON v.session_id = s.session_id
            INNER JOIN {$resources} r ON r.id = v.resource_id
            WHERE s.visitor_id IN ($in)
              AND r.singular_id IS NOT NULL
              AND r.singular_id <> 0
        ";
        

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders are safe, we still prepare below
        $pairs = $wpdb->get_results($wpdb->prepare($sql, ...$batch));
        
        // var_dump($wpdb->prepare($sql, ...$batch));
        // die();


        if (!$pairs) continue;

        // 3) Build per-visitor list of consumed post IDs
        $posts_by_visitor = []; // [visitor_id => [post_id => true]]
        $all_post_ids = [];     // [post_id => true]

        foreach ($pairs as $p) {
            $vid = (int)$p->visitor_id;
            $pid = (int)$p->post_id;
            if ($vid <= 0 || $pid <= 0) continue;

            $posts_by_visitor[$vid][$pid] = true;
            $all_post_ids[$pid] = true;
        }

        if (!$all_post_ids) continue;

        $all_post_ids = array_keys($all_post_ids);

        // 4) Prefetch alignment meta for all consumed posts (only those that have it)
        $meta_in = implode(',', array_fill(0, count($all_post_ids), '%d'));
        $meta_sql = "
            SELECT post_id, meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = %s
              AND post_id IN ($meta_in)
        ";

        $meta_rows = $wpdb->get_results($wpdb->prepare($meta_sql, array_merge([P26_ALIGNMENT_META], $all_post_ids)));

        // Map: post_id => tag set [tag_id => true]
        $tags_by_post = [];

        foreach ($meta_rows as $mr) {
            $pid = (int)$mr->post_id;
            $alignment = maybe_unserialize($mr->meta_value);
            if (!is_array($alignment)) continue;

            $tags = p26_extract_post_tags($alignment);
            if ($tags) $tags_by_post[$pid] = $tags;
        }

        if (!$tags_by_post) continue;

        // 5) For each visitor, union tags across their consumed posts, then count into cells
        foreach ($posts_by_visitor as $vid => $post_set) {
            $visitor_tags = []; // [tag_id => true]

            foreach ($post_set as $pid => $_t) {
                if (!isset($tags_by_post[$pid])) continue;
                foreach ($tags_by_post[$pid] as $tag_id => $_x) {
                    $visitor_tags[(int)$tag_id] = true;
                }
            }

            if (!$visitor_tags) continue;

            // visitor matches rows/cols only if they have those tag IDs somewhere in their consumed content
            $v_rows = array_intersect_key($visitor_tags, $row_set);
            $v_cols = array_intersect_key($visitor_tags, $col_set);

            if (!$v_rows || !$v_cols) continue;

            // Each visitor contributes at most +1 per cell (unique visitor)
            foreach ($v_rows as $rid => $_a) {
                foreach ($v_cols as $cid => $_b) {
                    $counts[$rid][$cid] += 1;
                    if ($counts[$rid][$cid] > $max) $max = $counts[$rid][$cid];
                }
            }
        }
    }

    return [
        'counts'         => $counts,
        'max'            => $max,
        'visitors_total' => $visitors_total,
    ];
}

/* =========================================================
 * Labels
 * ======================================================= */

function p26_post_label(WP_Post $p): string {
    $t = trim((string)$p->post_title);
    return ($t !== '') ? $t : '(untitled #' . (int)$p->ID . ')';
}
