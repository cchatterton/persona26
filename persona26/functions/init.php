<?php
if (!defined('ABSPATH')) exit;

/**
 * Bump this if you ever change schema.
 */
define('P26_DB_VERSION', '2'); // was 1 (session map). now visitor map.

function p26_table_name(): string {
    global $wpdb;
    // visitor mapping table (p26_id <-> IA visitor_id)
    return $wpdb->prefix . 'independent_analytics_p26';
}

/** Initialise each site's schema lazily, including newly created network sites. */
function p26_activate(): void {
    p26_maybe_upgrade_database();
}

function p26_maybe_upgrade_database(): void {
    if (P26_DB_VERSION === get_option('p26_db_version')) {
        return;
    }
    p26_create_table();
    global $wpdb;
    $table = p26_table_name();
    if ($table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)))) {
        update_option('p26_db_version', P26_DB_VERSION, false);
    }
}
add_action('init', 'p26_maybe_upgrade_database', 1);

/** Deactivation must preserve historical mappings, settings and targets. */
function p26_deactivate(): void {
    wp_clear_scheduled_hook('p26_alignment_mirror_migration_batch');
}

/**
 * Create the visitor mapping table.
 */
function p26_create_table(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = p26_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    // Minimal join table: p26_id <-> ia_visitor_id
    $sql = "CREATE TABLE {$table} (
        p26_id VARCHAR(64) NOT NULL,
        ia_visitor_id BIGINT UNSIGNED NOT NULL,
        first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (p26_id, ia_visitor_id),
        KEY ia_visitor_id (ia_visitor_id)
    ) {$charset_collate};";

    dbDelta($sql);
}
