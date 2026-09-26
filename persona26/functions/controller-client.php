<?php
/** Copy this file into a Techn plugin, require it, then call tnuc_client_register(__FILE__, 'repository-name') from the plugin main file. */
if (!defined('ABSPATH')) { exit; }
if (!function_exists('tnuc_client_register')) {
    function tnuc_client_register($main_file, $repository) {
        $GLOBALS['tnuc_clients'][plugin_basename($main_file)] = sanitize_text_field($repository);
    }
    function tnuc_client_links($links, $file) {
        if (!isset($GLOBALS['tnuc_clients'][$file])) { return $links; }
        if (function_exists('tnuc_available') && tnuc_available() && defined('TNUC_API_VERSION') && TNUC_API_VERSION >= 1) { return $links; }
        $links[] = '<a href="' . esc_url('https://github.com/cchatterton/' . $GLOBALS['tnuc_clients'][$file]) . '">GitHub</a>';
        $controller = 'tn-update-controller/tn-update-controller.php';
        if (!file_exists(WP_PLUGIN_DIR . '/' . $controller)) {
            if (current_user_can('install_plugins')) {
                $url = wp_nonce_url(admin_url('admin-post.php?action=tnuc_bootstrap_install'), 'tnuc_bootstrap_install');
                $links[] = '<a href="' . esc_url($url) . '">Install Techn Update Controller</a>';
            }
        } elseif (!defined('TNUC_API_VERSION') || (is_multisite() && !is_plugin_active_for_network($controller))) {
            if (current_user_can('activate_plugin', $controller)) {
                $url = wp_nonce_url(add_query_arg(['action'=>'activate','plugin'=>$controller,'networkwide'=>is_multisite() ? 1 : 0], network_admin_url('plugins.php')), 'activate-plugin_' . $controller);
                $links[] = '<a href="' . esc_url($url) . '">Activate Techn Update Controller</a>';
            }
        } elseif (current_user_can('update_plugins')) {
            $links[] = '<a href="' . esc_url(network_admin_url('plugins.php')) . '">Update Techn Update Controller</a>';
        }
        return $links;
    }
    function tnuc_bootstrap_install() {
        if (!current_user_can('install_plugins') || (is_multisite() && !current_user_can('manage_network_plugins'))) { wp_die('You cannot install this controller.'); }
        check_admin_referer('tnuc_bootstrap_install');
        global $wp_version;
        if (version_compare(PHP_VERSION, '7.4', '<') || version_compare($wp_version, '6.5', '<')) {
            wp_die('Techn Update Controller requires WordPress 6.5 and PHP 7.4 or later. This plugin can continue to run without it.');
        }
        if (!wp_is_file_mod_allowed('tnuc_bootstrap')) { wp_die('File modifications are disabled.'); }
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        $title = 'Install Techn Update Controller';
        require_once ABSPATH . 'wp-admin/admin-header.php';
        $skin = new Plugin_Installer_Skin(['type'=>'web','title'=>$title,'url'=>admin_url('admin-post.php?action=tnuc_bootstrap_install'),'nonce'=>'tnuc_bootstrap_install']);
        $upgrader = new Plugin_Upgrader($skin);
        $upgrader->install('https://github.com/cchatterton/tn-update-controller/releases/latest/download/tn-update-controller.zip');
        echo '<p><a href="' . esc_url(network_admin_url('plugins.php')) . '">Return to Plugins to activate the controller</a></p>';
        require_once ABSPATH . 'wp-admin/admin-footer.php';
    }
    add_filter('plugin_row_meta', 'tnuc_client_links', 20, 2);
    add_action('admin_post_tnuc_bootstrap_install', 'tnuc_bootstrap_install');
}
