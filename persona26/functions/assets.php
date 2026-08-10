<?php
/**
 * Admin asset loading for Persona26.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_enqueue_scripts', 'p26_enqueue_admin_assets');

function p26_enqueue_admin_assets($hook_suffix): void {
    $screen = get_current_screen();
    $is_persona_page = 'toplevel_page_persona26' === $hook_suffix;
    $is_profiled_editor = $screen && in_array((string) $screen->post_type, p26_profiled_post_types(), true);

    if (!$is_persona_page && !$is_profiled_editor) {
        return;
    }

    wp_enqueue_style(
        'persona26-admin',
        P26_PLUGIN_URL . 'styles/persona26.css',
        array(),
        P26_VERSION
    );

    wp_enqueue_script(
        'persona26-admin',
        P26_PLUGIN_URL . 'scripts/persona26.js',
        array(),
        P26_VERSION,
        true
    );
}
