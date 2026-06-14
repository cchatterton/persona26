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

/**
 * Plugin activation: create table + store schema version.
 */
function p26_activate(): void {
    p26_create_table();
    update_option('p26_db_version', P26_DB_VERSION, true);
}

/**
 * Plugin deactivation: drop table (derived data; safe to rebuild).
 */
function p26_deactivate(): void {
    global $wpdb;
    $table = p26_table_name();
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
    // optional: cleanup version option too
    delete_option('p26_db_version');
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
