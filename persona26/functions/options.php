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

    foreach ($posted_tracked as $row) {
        $tracked[] = [
            'post_type' => sanitize_key($row['post_type'] ?? ''),
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
    $content_pts = array_map('sanitize_key', array_keys($posted_content_post_types));

    update_option(P26_SETTINGS_OPTION, [
        'tracked'            => $tracked,
        'content_post_types' => $content_pts,
    ], true);

    wp_safe_redirect(admin_url('admin.php?page=' . P26_MENU_SLUG));
    exit;
}

/* ---------------------------------------------------------
 * Render page
 * --------------------------------------------------------- */
function p26_render_page(): void {
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
        <h1>Persona26</h1>

        <p class="nav-tab-wrapper p26-main-tabs">
            <a href="#" class="nav-tab nav-tab-active" data-tab="dimensions">Dimensions</a>
            <a href="#" class="nav-tab" data-tab="matrix">Engagement Matrix</a>
        </p>

        <div class="p26-main-panel active" data-tab="dimensions">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="p26-form-panel">
                <input type="hidden" name="action" value="p26_save_settings">
                <?php wp_nonce_field('p26_save', 'p26_nonce'); ?>

                <div class="p26-card">
                    <h2>Persona Dimensions</h2>

                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th class="p26-post-type-column">Post Type</th>
                                <th class="p26-context-column">Context</th>
                                <th class="p26-action-column"></th>
                            </tr>
                        </thead>
                        <tbody id="p26-repeater">
                        <?php foreach ($settings['tracked'] as $i => $row): ?>
                            <tr class="p26-row <?php echo ($i < 2) ? 'is-locked' : ''; ?>">
                                <td>
                                    <select name="p26_tracked[<?php echo (int) $i; ?>][post_type]" class="p26-post-type">
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
                                    />
                                </td>
                                <td>
                                    <?php if ($i >= 2): ?>
                                        <button type="button" class="button link-button p26-remove" title="Remove">✕</button>
                                    <?php else: ?>
                                        <span class="p26-locked">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p>
                        <button type="button" id="p26-add" class="button">Add</button>
                    </p>
                </div>

                <div class="p26-card">
                    <h2>Content Profiling Scope</h2>

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
                    <button class="button button-primary">Save</button>
                </p>
            </form>
        </div>

        <div class="p26-main-panel" data-tab="matrix">
            <div class="p26-card">
                <h2>Engagement Matrix</h2>

                <p class="nav-tab-wrapper p26-sub-tabs">
                    <!--<a href="#" class="nav-tab nav-tab-active" data-subtab="plan">Plan</a>-->
                    <a href="#" class="nav-tab nav-tab-active" data-subtab="actual">Actual</a>
                    <a href="#" class="nav-tab" data-subtab="users">Users</a>
                </p>

                <!--<div class="p26-sub-panel active" data-subtab="plan">-->
                <!--    <?php //p26_render_matrix($rows, $cols); ?>-->
                <!--</div>-->

                <div class="p26-sub-panel active" data-subtab="actual">
                    <?php p26_render_actual_matrix($rows, $cols, $actual); ?>
                </div>

                <div class="p26-sub-panel" data-subtab="users">
                    <?php p26_render_users_matrix($rows, $cols, $users); ?>
                </div>
            </div>
        </div>
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

    echo '<table class="widefat striped p26-matrix"><thead><tr><th class="p26-corner">—</th>';
    foreach ($cols as $c) {
        echo '<th>' . esc_html(p26_post_label($c)) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
        echo '<tr>';
        echo '<th>' . esc_html(p26_post_label($r)) . '</th>';

        foreach ($cols as $c) {
            $label = p26_post_label($r) . ' × ' . p26_post_label($c);
            echo '<td><div class="p26-cell-label">' . esc_html($label) . '</div></td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table>';
}

function p26_render_actual_matrix(array $rows, array $cols, array $actual): void {
    if (!$rows || !$cols) {
        echo '<p><em>Select two Engagement Dimensions and save.</em></p>';
        return;
    }

    $counts = $actual['counts'] ?? [];
    $max    = max(1, (int)($actual['max'] ?? 0));

    echo '<table class="widefat striped p26-matrix p26-heat"><thead><tr><th>—</th>';
    foreach ($cols as $c) echo '<th>' . esc_html(p26_post_label($c)) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
        $rid = (int)$r->ID;
        echo '<tr><th>' . esc_html(p26_post_label($r)) . '</th>';

        foreach ($cols as $c) {
            $cid = (int)$c->ID;
            $val = (int)($counts[$rid][$cid] ?? 0);

            $opacity = ($val === 0) ? 0.04 : (0.08 + 0.92 * ($val / $max));
            $style = ($val === 0)
                ? 'background:rgba(0,0,0,0.04);'
                : 'background:rgba(0,0,0,' . $opacity . ');color:#fff;font-weight:600;';

            echo '<td style="' . esc_attr($style) . '">' . $val . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table>';
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

    echo '<table class="widefat striped p26-matrix p26-heat"><thead><tr><th>—</th>';
    foreach ($cols as $c) echo '<th>' . esc_html(p26_post_label($c)) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
        $rid = (int)$r->ID;
        echo '<tr><th>' . esc_html(p26_post_label($r)) . '</th>';

        foreach ($cols as $c) {
            $cid = (int)$c->ID;
            $val = (int)($counts[$rid][$cid] ?? 0);

            $opacity = ($val === 0) ? 0.04 : (0.08 + 0.92 * ($val / $max));
            $style = ($val === 0)
                ? 'background:rgba(0,0,0,0.04);'
                : 'background:rgba(0,0,0,' . $opacity . ');color:#fff;font-weight:600;';

            echo '<td style="' . esc_attr($style) . '">' . $val . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table>';
}
