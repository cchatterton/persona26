<?php
if (!defined('ABSPATH')) exit;

add_action('admin_post_p26_legacy_migration', 'p26_legacy_admin_action');
function p26_legacy_admin_action(): void {
    if (!current_user_can('manage_options')) wp_die('Insufficient permissions.', '', ['response' => 403]);
    check_admin_referer('p26_legacy_migration');
    $operation = sanitize_key(wp_unslash($_POST['operation'] ?? ''));
    $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
    try {
        switch ($operation) {
            case 'simulate':
                p26_legacy_simulate();
                $message = 'Simulation complete. Review the counts, record changes and any blockers below. Site content has not changed.';
                break;
            case 'commit':
                if (empty($_POST['acknowledge'])) throw new RuntimeException('Confirm that you reviewed the simulation and have a site backup.');
                p26_legacy_commit($token);
                $message = 'Migration committed. Check the mapped posts, content targets and affected pages, then deactivate the original Personas plugin. Recovery remains available in Dimensions.';
                break;
            case 'check_rollback':
                p26_legacy_rollback($token, true);
                $message = 'Recovery check passed. All migrated records still match the snapshot; rollback is currently available.';
                break;
            case 'rollback':
                if (empty($_POST['acknowledge'])) throw new RuntimeException('Confirm that you want to restore the records from the snapshot.');
                p26_legacy_rollback($token);
                $message = 'Migration rolled back. Original post types, metadata and stored references were restored. Reactivate Personas if it is currently off.';
                break;
            default: throw new RuntimeException('Unknown migration action.');
        }
        $notice = ['message' => $message, 'error' => false];
    } catch (Throwable $error) {
        $notice = ['message' => $error->getMessage(), 'error' => true];
    }
    set_transient('p26_legacy_notice_' . get_current_user_id(), $notice, 120);
    wp_safe_redirect(admin_url('admin.php?page=' . P26_MENU_SLUG . '&p26_migration=1'));
    exit;
}

function p26_legacy_form_start(string $operation, string $token = ''): void {
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="p26-migration-action">';
    wp_nonce_field('p26_legacy_migration');
    echo '<input type="hidden" name="action" value="p26_legacy_migration"><input type="hidden" name="operation" value="' . esc_attr($operation) . '"><input type="hidden" name="token" value="' . esc_attr($token) . '">';
}

function p26_legacy_notice(): void {
    $notice = get_transient('p26_legacy_notice_' . get_current_user_id());
    if (!$notice) return;
    delete_transient('p26_legacy_notice_' . get_current_user_id());
    echo '<div class="notice ' . ($notice['error'] ? 'notice-error' : 'notice-success') . '" role="status"><p>' . esc_html($notice['message']) . '</p></div>';
}

/** Mapping is deliberately configured outside the wizard, alongside dimensions. */
function p26_legacy_mapping_controls(): void {
    if (!p26_legacy_plugin()) return;
    $map = (array) get_option(P26_LEGACY_MAP, []);
    echo '<div class="p26-card"><p class="p26-eyebrow">Move from Personas</p><h2>Legacy destination mapping</h2><p class="description">Choose and save your destination dimensions here before opening Migrate Wizard. Register destination post types outside the wizard. Existing post IDs will be retained.</p><div class="p26-checkbox-grid">';
    foreach (p26_legacy_sources() as $source => $keys) {
        echo '<label>' . esc_html('__persona' === $source ? 'Original personas (__persona)' : 'Original interests (__interest)') . '<br><select name="p26_legacy_mapping[' . esc_attr($source) . ']">';
        echo '<option value="">Choose a saved dimension</option>';
        foreach (p26_dimensions() as $dimension) {
            if (isset(p26_legacy_sources()[$dimension['post_type']])) continue;
            echo '<option value="' . esc_attr($dimension['key']) . '" ' . selected($map[$source] ?? '', $dimension['key'], false) . '>' . esc_html($dimension['label']) . '</option>';
        }
        echo '</select></label>';
    }
    echo '</div></div>';
}

function p26_legacy_render_wizard(bool $recovery = false): void {
    $journal = p26_legacy_read_journal();
    $status = $journal['status'] ?? '';
    $plugin = p26_legacy_plugin();
    echo '<div class="p26-card p26-migration"><p class="p26-eyebrow">' . ($recovery ? 'Migration recovery' : 'Move from Personas') . '</p><h2>' . ($recovery ? 'Restore the original tagging' : 'Migrate Wizard') . '</h2>';
    if ($plugin) echo '<p class="description">Original Personas ' . esc_html($plugin['version']) . ' is active on this site.</p>';
    if ('committed' === $status) {
        echo '<p>Migration committed on ' . esc_html($journal['committed']) . '. Post IDs, slugs, media and tag selections were retained. Translated relationship fields continue to store IDs; Persona26 also creates its title-based query fields.</p><ol><li>Check the destination posts and Persona Targets on tagged content.</li><li>Check affected blocks, templates and personalised pages on the front end.</li><li>Deactivate the original Personas plugin after those checks. Keep Persona26 active for the translated relationship fields.</li></ol><p>Rollback restores only the migration’s recorded changes. If those records have since been edited, recovery stops and identifies the conflict.</p>';
        p26_legacy_form_start('check_rollback', $journal['token']);
        echo '<button class="button" type="submit">Check rollback</button></form>';
        p26_legacy_form_start('rollback', $journal['token']);
        echo '<p><label><input type="checkbox" name="acknowledge" value="1" required> Restore the original post types, metadata and references from this migration snapshot.</label></p><button class="button" type="submit">Roll back migration</button></form>';
    } elseif (!$recovery) {
        echo '<ol><li><strong>Map:</strong> save destination dimensions and content profiling scope in Dimensions.</li><li><strong>Simulate:</strong> review changes without changing site content.</li><li><strong>Commit:</strong> apply the reviewed plan and retain a recovery snapshot.</li></ol>';
        if ('rolled_back' === $status) echo '<p>The previous migration was rolled back. You can run a new simulation.</p>';
        p26_legacy_form_start('simulate');
        echo '<button class="button button-primary" type="submit">' . ('preview' === $status ? 'Run simulation again' : 'Run simulation') . '</button></form>';
    }
    if (isset($journal['plan'])) {
        $plan = $journal['plan'];
        echo '<h3>' . ('preview' === $status ? 'Simulation results' : 'Migration record') . '</h3><ul>';
        foreach ($plan['mapping'] as $source => $dimension) echo '<li><code>' . esc_html($source) . '</code> → ' . esc_html($dimension['label']) . '; relationship field <code>' . esc_html($dimension['alias']) . '</code></li>';
        echo '</ul><p>' . esc_html(sprintf('%d personas · %d interests · %d tagged content records · %d stored reference records', $plan['counts']['personas'], $plan['counts']['interests'], $plan['counts']['tagged_content'], $plan['counts']['reference_records'])) . '</p>';
        if ($plan['blockers']) {
            echo '<div class="notice notice-error inline"><p><strong>Resolve these before committing</strong></p><ul>';
            foreach ($plan['blockers'] as $blocker) echo '<li>' . esc_html($blocker) . '</li>';
            echo '</ul></div>';
        }
        echo '<details><summary>Review affected records</summary><div class="p26-table-scroll"><table class="widefat striped"><thead><tr><th>Record</th><th>Planned change</th></tr></thead><tbody>';
        foreach ($plan['posts'] as $id => $change) {
            $columns = array_keys(array_diff_assoc($change['after'], $change['before']));
            echo '<tr><td>Post #' . (int) $id . ': ' . esc_html($change['before']['post_title']) . '</td><td>' . esc_html(implode(', ', $columns)) . '</td></tr>';
        }
        foreach ($plan['meta'] as $id => $change) echo '<tr><td>Post #' . (int) $id . '</td><td>Merge targets, create query fields or translate stored metadata references</td></tr>';
        foreach ($plan['other'] as $change) echo '<tr><td>' . esc_html($change['table'] . ' #' . $change['before'][$change['id']]) . '</td><td>Translate structured references</td></tr>';
        echo '</tbody></table></div></details>';
        if ('preview' === $status && !$plan['blockers'] && ($plan['posts'] || $plan['meta'] || $plan['other'])) {
            p26_legacy_form_start('commit', $journal['token']);
            echo '<p><label><input type="checkbox" name="acknowledge" value="1" required> I have reviewed the simulation, have a full site backup, and am ready to apply these changes.</label></p><button class="button button-primary" type="submit">Commit migration</button></form>';
        }
    }
    echo '<p class="description">Coverage: this site’s posts, block attributes, templates, reusable blocks, post metadata, structured options, term metadata and comment metadata. Unrecognised references block commit. Original relationship fields and visitor history are retained. Theme/plugin PHP, external systems, old visitor-history files, URL redirects and caches outside WordPress are not converted; test those integrations on staging before switching off Personas.</p></div>';
}
