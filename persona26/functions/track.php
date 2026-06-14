<?php
if (!defined('ABSPATH')) exit;

/**
 * Track: map our p26_id (cookie) to IA visitor_id
 * Table: wp_independent_analytics_p26 (via p26_table_name())
 */

function p26_cookie_id(): string {
    // From your cookie/localStorage sync: key is p26_id
    return isset($_COOKIE['p26_id']) ? sanitize_text_field(wp_unslash($_COOKIE['p26_id'])) : '';
}

function p26_get_ia_visitor_id(): int {
    // Use IA's own code if available (best + future-proof)
    if (class_exists('\\IAWP\\Models\\Visitor')) {
        try {
            $visitor = \IAWP\Models\Visitor::fetch_current_visitor();
            return (int) $visitor->id();
        } catch (\Throwable $e) {
            return 0;
        }
    }
    return 0;
}

function p26_insert_map(string $p26_id, int $ia_visitor_id): void {
    if (!$p26_id || $ia_visitor_id <= 0) return;

    global $wpdb;
    $table = p26_table_name(); // init.php

    // Insert once, never update
    $wpdb->query(
        $wpdb->prepare(
            "INSERT IGNORE INTO {$table} (p26_id, ia_visitor_id) VALUES (%s, %d)",
            $p26_id,
            $ia_visitor_id
        )
    );
}

/**
 * Run on front-end page loads (simple MVP).
 * Logged-in users are excluded from the engagement matrix mapping table,
 * but still keep Persona JSON / cookies / personalisation elsewhere.
 */
function p26_track_current_visitor(): void {
    if (is_admin()) return;

    // Exclude logged-in users from mapping table / engagement matrix
    if (is_user_logged_in()) return;

    $p26_id = p26_cookie_id();
    if (!$p26_id) return;

    $ia_visitor_id = p26_get_ia_visitor_id();
    if ($ia_visitor_id <= 0) return;

    p26_insert_map($p26_id, $ia_visitor_id);
}
add_action('wp_loaded', 'p26_track_current_visitor', 20);
