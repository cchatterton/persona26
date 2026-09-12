<?php
if (!defined('ABSPATH')) exit;

const P26_MENU_SLUG = 'persona26';

/* ---------------------------------------------------------
 * Admin Menu
 * --------------------------------------------------------- */
add_action('admin_menu', 'p26_register_admin_menu');

function p26_register_admin_menu(): void {
    add_menu_page(
        'Persona26',
        'Persona26',
        'manage_options',
        P26_MENU_SLUG,
        'p26_render_page',
        'dashicons-groups',
        80
    );
}

/* ---------------------------------------------------------
 * Save handler
 * --------------------------------------------------------- */
add_action('admin_post_p26_save_settings', 'p26_save_settings');

function p26_save_settings(): void {
    if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
    check_admin_referer('p26_save', 'p26_nonce');

    $tracked = [];
    $posted_tracked = isset($_POST['p26_tracked']) ? (array) wp_unslash($_POST['p26_tracked']) : [];
    $available_post_types = array_keys(p26_all_post_types());

    foreach ($posted_tracked as $row) {
        if (!is_array($row)) {
            continue;
        }
        $post_type = is_string($row['post_type'] ?? null) ? $row['post_type'] : '';
        $tracked[] = [
            'post_type' => in_array($post_type, $available_post_types, true) ? $post_type : '',
            'context'   => sanitize_text_field($row['context'] ?? ''),
        ];
    }

    // Enforce 2 rows minimum
    if (count($tracked) < 2) {
        $tracked = array_pad($tracked, 2, ['post_type' => '', 'context' => '']);
    }

    if (empty($tracked[0]['context'])) $tracked[0]['context'] = 'Audience';
    if (empty($tracked[1]['context'])) $tracked[1]['context'] = 'Interests';

    // Only store checked post types (slugs)
    $posted_content_post_types = isset($_POST['p26_content_post_types']) ? (array) wp_unslash($_POST['p26_content_post_types']) : [];
    $content_pts = array_values(
        array_intersect(
            array_keys($posted_content_post_types),
            $available_post_types
        )
    );

    update_option(P26_SETTINGS_OPTION, [
        'tracked'            => $tracked,
        'content_post_types' => $content_pts,
    ], true);
    p26_queue_alignment_mirror_migration();

    p26_rebuild_personalize_css();
    set_transient('p26_settings_saved_' . get_current_user_id(), true, MINUTE_IN_SECONDS);
    wp_safe_redirect(admin_url('admin.php?page=' . P26_MENU_SLUG));
    exit;
}

/* ---------------------------------------------------------
 * Render page
 * --------------------------------------------------------- */
function p26_render_page(): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to manage these settings.', 'persona26'));
    }
    $settings = p26_get_settings();
    $pts      = p26_all_post_types();

    $primary_pt   = $settings['tracked'][0]['post_type'] ?? '';
    $secondary_pt = $settings['tracked'][1]['post_type'] ?? '';

    $cols = p26_get_posts($primary_pt);
    $rows = p26_get_posts($secondary_pt);

    $actual = p26_build_actual_heatmap($settings, $rows, $cols);
    $users  = p26_build_users_heatmap($settings, $rows, $cols);
    ?>
    <div class="wrap p26-wrap">
        <h1 class="screen-reader-text">Persona26</h1>
        <?php if (get_transient('p26_settings_saved_' . get_current_user_id())): ?>
            <?php delete_transient('p26_settings_saved_' . get_current_user_id()); ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Persona settings saved.', 'persona26'); ?></p></div>
        <?php endif; ?>
        <header class="p26-hero">
            <span class="p26-version" aria-label="<?php echo esc_attr(sprintf(__('Version %s', 'persona26'), P26_VERSION)); ?>">v<?php echo esc_html(P26_VERSION); ?></span>
            <div class="p26-hero-copy">
                <p class="p26-eyebrow">Visitor intelligence</p>
                <h2>Persona26</h2>
                <p class="p26-hero-lead">Understand your audience. Make every visit more relevant.</p>
            </div>
            <div class="p26-capabilities">
                <span>Content personalisation</span>
                <?php if (p26_analytics_available()): ?><span>Independent Analytics connected</span><?php endif; ?>
                <?php if (class_exists('GFForms')): ?><span>Gravity Forms connected</span><?php endif; ?>
            </div>
        </header>

        <div class="nav-tab-wrapper p26-main-tabs" role="tablist" aria-label="Persona settings">
            <button type="button" id="p26-tab-dimensions" class="nav-tab nav-tab-active" role="tab" aria-selected="true" aria-controls="p26-panel-dimensions" data-tab="dimensions">Dimensions</button>
            <button type="button" id="p26-tab-matrix" class="nav-tab" role="tab" aria-selected="false" tabindex="-1" aria-controls="p26-panel-matrix" data-tab="matrix">Engagement matrix</button>
        </div>

        <div id="p26-panel-dimensions" class="p26-main-panel active" role="tabpanel" aria-labelledby="p26-tab-dimensions" data-tab="dimensions">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="p26-form-panel">
                <input type="hidden" name="action" value="p26_save_settings">
                <?php wp_nonce_field('p26_save', 'p26_nonce'); ?>

                <div class="p26-card">
                    <p class="p26-eyebrow">Audience model</p><h2>Persona dimensions</h2>
                    <p class="description">Choose the content types that describe your visitors. The first two dimensions form the matrix axes.</p>
                    <p class="description">Dimension positions identify saved targets and visitor profiles. Clear a dimension to retire it without shifting later dimensions.</p>

                    <div class="p26-table-scroll" role="region" aria-label="Persona dimensions" tabindex="0"><table class="widefat striped">
                        <thead>
                            <tr>
                                <th class="p26-post-type-column">Post Type</th>
                                <th class="p26-context-column">Context</th>
                                <th class="p26-action-column"><span class="screen-reader-text">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody id="p26-repeater">
                        <?php foreach ($settings['tracked'] as $i => $row): ?>
                            <tr class="p26-row <?php echo ($i < 2) ? 'is-locked' : ''; ?>">
                                <td>
                                    <select name="p26_tracked[<?php echo (int) $i; ?>][post_type]" class="p26-post-type" aria-label="<?php echo esc_attr(sprintf(__('Dimension %d post type', 'persona26'), $i + 1)); ?>">
                                        <option value="">—</option>
                                        <?php foreach ($pts as $k => $label): ?>
                                            <option value="<?php echo esc_attr($k); ?>" <?php selected($row['post_type'], $k); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        name="p26_tracked[<?php echo (int) $i; ?>][context]"
                                        value="<?php echo esc_attr($row['context']); ?>"
                                        class="p26-context"
                                        aria-label="<?php echo esc_attr(sprintf(__('Dimension %d context', 'persona26'), $i + 1)); ?>"
                                    />
                                </td>
                                <td>
                                    <?php if ($i >= 2): ?>
                                        <button type="button" class="button p26-remove">Clear</button>
                                    <?php else: ?>
                                        <span class="p26-locked">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>

                    <p>
                        <button type="button" id="p26-add" class="button">Add dimension</button>
                    </p>
                </div>

                <div class="p26-card">
                    <p class="p26-eyebrow">Content coverage</p><h2>Content profiling scope</h2>
                    <p class="description">Enable Persona Targets on these content types to build visitor profiles from their activity.</p>

                    <div class="p26-checkbox-grid">
                        <?php foreach ($pts as $slug => $label): ?>
                            <label class="p26-checkbox">
                                <input
                                    type="checkbox"
                                    name="p26_content_post_types[<?php echo esc_attr($slug); ?>]"
                                    <?php checked(in_array($slug, ($settings['content_post_types'] ?? []), true)); ?>
                                >
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <p>
                    <button type="submit" class="button button-primary">Save settings</button>
                    <span class="p26-save-hint">Changes apply after saving.</span>
                </p>
            </form>
        </div>

        <div id="p26-panel-matrix" class="p26-main-panel" role="tabpanel" aria-labelledby="p26-tab-matrix" data-tab="matrix" hidden>
            <div class="p26-card">
                <p class="p26-eyebrow">Audience insights</p><h2>Engagement matrix</h2><p class="description">Compare targeted content and unique visitors across your first two dimensions.</p>

                <div class="nav-tab-wrapper p26-sub-tabs" role="tablist" aria-label="Matrix view">
                    <button type="button" id="p26-tab-actual" class="nav-tab nav-tab-active" role="tab" aria-selected="true" aria-controls="p26-panel-actual" data-subtab="actual">Content</button>
                    <button type="button" id="p26-tab-users" class="nav-tab" role="tab" aria-selected="false" tabindex="-1" aria-controls="p26-panel-users" data-subtab="users">Visitors</button>
                </div>

                <div id="p26-panel-actual" class="p26-sub-panel active" role="tabpanel" aria-labelledby="p26-tab-actual" data-subtab="actual">
                    <?php p26_render_actual_matrix($rows, $cols, $actual); ?>
                </div>

                <div id="p26-panel-users" class="p26-sub-panel" role="tabpanel" aria-labelledby="p26-tab-users" data-subtab="users" hidden>
                    <?php if (p26_analytics_available()) {
                        p26_render_users_matrix($rows, $cols, $users);
                    } else {
                        echo '<p>Connect Independent Analytics with its database tables available to view visitor activity. Your persona dimensions and content targets remain available.</p>';
                    } ?>
                </div>
            </div>
        </div>
        <details class="p26-reference">
            <summary>Developer reference</summary>
            <p>Read configured dimensions with <code>p26_dimensions()</code>. Read a post’s targets with <code>get_post_meta($post_id, 'p26_alignment', true)</code>.</p>
            <p>On the front end, <code>window.p26.profile</code> exposes the current browser profile. Use <code>?persona=show</code> to inspect it.</p>
        </details>
    </div>

<?php
}

/* ---------------------------------------------------------
 * Matrix renderers
 * --------------------------------------------------------- */
function p26_render_matrix(array $rows, array $cols): void {
    if (!$rows || !$cols) {
        echo '<p><em>Select two Engagement Dimensions and save.</em></p>';
        return;
    }

    echo '<div class="p26-table-scroll" role="region" aria-label="Engagement matrix" tabindex="0"><table class="widefat striped p26-matrix"><thead><tr><th class="p26-corner">—</th>';
    foreach ($cols as $c) {
        echo '<th scope="col">' . esc_html(p26_post_label($c)) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
        echo '<tr>';
        echo '<th scope="row">' . esc_html(p26_post_label($r)) . '</th>';

        foreach ($cols as $c) {
            $label = p26_post_label($r) . ' × ' . p26_post_label($c);
            echo '<td><div class="p26-cell-label">' . esc_html($label) . '</div></td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function p26_render_actual_matrix(array $rows, array $cols, array $actual): void {
    if (!$rows || !$cols) {
        echo '<p><em>Select two Engagement Dimensions and save.</em></p>';
        return;
    }

    $counts = $actual['counts'] ?? [];
    $max    = max(1, (int)($actual['max'] ?? 0));

    echo '<div class="p26-table-scroll" role="region" aria-label="Engagement matrix" tabindex="0"><table class="widefat striped p26-matrix p26-heat"><thead><tr><th>—</th>';
    foreach ($cols as $c) echo '<th scope="col">' . esc_html(p26_post_label($c)) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
        $rid = (int)$r->ID;
        echo '<tr><th scope="row">' . esc_html(p26_post_label($r)) . '</th>';

        foreach ($cols as $c) {
            $cid = (int)$c->ID;
            $val = (int)($counts[$rid][$cid] ?? 0);

            $style = p26_heatmap_cell_style($val, $max);

            echo '<td style="' . esc_attr($style) . '">' . $val . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function p26_render_users_matrix(array $rows, array $cols, array $users): void {
    if (!$rows || !$cols) {
        echo '<p><em>Select two Engagement Dimensions and save.</em></p>';
        return;
    }

    $counts = $users['counts'] ?? [];
    $max    = max(1, (int)($users['max'] ?? 0));
    $totalV = (int)($users['visitors_total'] ?? 0);

    echo '<p class="p26-users-summary"><small><strong>Unique visitors</strong> per cell based on viewed content. Total mapped visitors: <strong>' . esc_html((string)$totalV) . '</strong>.</small></p>';

    echo '<div class="p26-table-scroll" role="region" aria-label="Engagement matrix" tabindex="0"><table class="widefat striped p26-matrix p26-heat"><thead><tr><th>—</th>';
    foreach ($cols as $c) echo '<th scope="col">' . esc_html(p26_post_label($c)) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
        $rid = (int)$r->ID;
        echo '<tr><th scope="row">' . esc_html(p26_post_label($r)) . '</th>';

        foreach ($cols as $c) {
            $cid = (int)$c->ID;
            $val = (int)($counts[$rid][$cid] ?? 0);

            $style = p26_heatmap_cell_style($val, $max);

            echo '<td style="' . esc_attr($style) . '">' . $val . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

/** Opaque navy tints keep numeric contrast predictable on striped table rows. */
function p26_heatmap_cell_style(int $value, int $max): string {
    $opacity = $value === 0 ? 0.04 : 0.08 + 0.92 * min(1, $value / max(1, $max));
    $rgb = array_map(static fn($channel) => (int) round(255 * (1 - $opacity) + $channel * $opacity), array(0, 40, 64));
    return 'background:rgb(' . implode(',', $rgb) . ');color:' . ($opacity >= 0.62 ? '#fff' : '#000') . ';font-weight:600;';
}
