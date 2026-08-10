<?php
/**
 * GitHub release updater for Persona26.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class P26_GitHub_Updater {
    private const OWNER = 'cchatterton';
    private const REPO = 'persona26';
    private const SLUG = 'persona26';
    private const ASSET_NAME = 'persona26.zip';
    private const RELEASE_TRANSIENT = 'p26_github_latest_release';
    private const ERROR_TRANSIENT = 'p26_github_latest_release_error';
    private const MANUAL_CHECK_ACTION = 'p26_check_updates';

    public static function init(): void {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject_update'));
        add_filter('site_transient_update_plugins', array(__CLASS__, 'inject_update'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_information'), 10, 3);
        add_filter('plugin_row_meta', array(__CLASS__, 'plugin_row_meta'), 10, 2);
        add_action('admin_init', array(__CLASS__, 'handle_manual_update_check'));
        add_action('upgrader_process_complete', array(__CLASS__, 'clear_cache_after_upgrade'), 10, 2);
    }

    public static function inject_update($transient) {
        if (!is_object($transient)) {
            $transient = new stdClass();
        }

        $transient->response = isset($transient->response) && is_array($transient->response) ? $transient->response : array();
        $transient->no_update = isset($transient->no_update) && is_array($transient->no_update) ? $transient->no_update : array();

        $plugin_file = plugin_basename(P26_PLUGIN_FILE);
        $release = self::get_latest_release();
        if (!$release) {
            unset($transient->response[$plugin_file], $transient->no_update[$plugin_file]);
            return $transient;
        }

        $version = self::release_version($release);
        $download_url = self::release_asset_url($release);

        if (!$version || !$download_url || !version_compare($version, P26_VERSION, '>')) {
            unset($transient->response[$plugin_file], $transient->no_update[$plugin_file]);
            return $transient;
        }

        $transient->response[$plugin_file] = (object) array(
            'id'           => self::repository_url(),
            'slug'         => self::SLUG,
            'plugin'       => $plugin_file,
            'new_version'  => $version,
            'url'          => self::repository_url(),
            'package'      => $download_url,
            'requires'     => '6.0',
            'requires_php' => '8.1',
        );
        unset($transient->no_update[$plugin_file]);

        return $transient;
    }

    public static function plugin_information($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || self::SLUG !== $args->slug) {
            return $result;
        }

        $release = self::get_latest_release();
        if (!$release) {
            return $result;
        }

        $version = self::release_version($release);
        $download_url = self::release_asset_url($release);
        if (!$version || !$download_url) {
            return $result;
        }

        return (object) array(
            'name'           => 'Persona26',
            'slug'           => self::SLUG,
            'version'        => $version,
            'author'         => 'Techn',
            'author_profile' => 'https://techn.com.au',
            'homepage'       => self::repository_url(),
            'download_link'  => $download_url,
            'requires'       => '6.0',
            'requires_php'   => '8.1',
            'sections'       => array(
                'description' => 'Extends Independent Analytics with visitor persona tracking and personalisation.',
                'changelog'   => wp_kses_post((string) ($release['body'] ?? '')),
            ),
        );
    }

    public static function plugin_row_meta($links, $file) {
        if (plugin_basename(P26_PLUGIN_FILE) !== $file) {
            return $links;
        }

        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url(self::repository_url()),
            esc_html__('GitHub', 'persona26')
        );
        $links[] = sprintf(
            '<a href="%s">%s</a>',
            esc_url(self::manual_check_url()),
            esc_html__('Check for updates', 'persona26')
        );

        return $links;
    }

    public static function handle_manual_update_check(): void {
        if (empty($_GET[self::MANUAL_CHECK_ACTION])) {
            return;
        }

        if (!current_user_can('update_plugins')) {
            wp_die(esc_html__('You do not have permission to check for plugin updates.', 'persona26'));
        }

        check_admin_referer(self::MANUAL_CHECK_ACTION);
        self::clear_release_cache();
        delete_site_transient('update_plugins');

        if (!function_exists('wp_update_plugins')) {
            require_once ABSPATH . 'wp-includes/update.php';
        }

        wp_update_plugins();
        wp_safe_redirect(self::plugins_page_url());
        exit;
    }

    public static function clear_cache_after_upgrade($upgrader, $hook_extra): void {
        if (!is_array($hook_extra) || 'plugin' !== ($hook_extra['type'] ?? '')) {
            return;
        }

        $updated_plugins = isset($hook_extra['plugins']) ? (array) $hook_extra['plugins'] : array();
        if (!empty($hook_extra['plugin'])) {
            $updated_plugins[] = (string) $hook_extra['plugin'];
        }

        if (in_array(plugin_basename(P26_PLUGIN_FILE), $updated_plugins, true)) {
            self::clear_release_cache();
        }
    }

    private static function get_latest_release(): ?array {
        if (self::is_forced_update_check()) {
            self::clear_release_cache();
        }

        $cached = get_site_transient(self::RELEASE_TRANSIENT);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::OWNER . '/' . self::REPO . '/releases/latest',
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'Persona26/' . P26_VERSION,
                ),
            )
        );

        if (is_wp_error($response)) {
            return self::release_lookup_fallback(
                array(
                    'type'       => 'wp_error',
                    'message'    => $response->get_error_message(),
                    'checked_at' => time(),
                )
            );
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $response_code) {
            return self::release_lookup_fallback(
                array(
                    'type'       => 'http_error',
                    'code'       => $response_code,
                    'message'    => wp_remote_retrieve_response_message($response),
                    'body'       => substr(wp_remote_retrieve_body($response), 0, 500),
                    'checked_at' => time(),
                )
            );
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($release) || !self::release_version($release)) {
            return self::release_lookup_fallback(
                array(
                    'type'       => 'json_error',
                    'checked_at' => time(),
                )
            );
        }

        self::cache_release($release);
        return $release;
    }

    private static function release_lookup_fallback(array $api_error): ?array {
        $response = wp_remote_get(
            self::repository_url() . '/releases/latest',
            array(
                'timeout'     => 10,
                'redirection' => 0,
                'headers'     => array('User-Agent' => 'Persona26/' . P26_VERSION),
            )
        );

        if (is_wp_error($response)) {
            $api_error['fallback_message'] = $response->get_error_message();
            self::store_lookup_error($api_error);
            return null;
        }

        $location = (string) wp_remote_retrieve_header($response, 'location');
        if (!preg_match('~/releases/tag/([^/?#]+)~', $location, $matches)) {
            $api_error['fallback_code'] = (int) wp_remote_retrieve_response_code($response);
            self::store_lookup_error($api_error);
            return null;
        }

        $tag = rawurldecode($matches[1]);
        $version = ltrim($tag, 'vV');
        if ('' === $version) {
            self::store_lookup_error($api_error);
            return null;
        }

        $asset_url = self::repository_url() . '/releases/download/' . rawurlencode($tag) . '/' . rawurlencode(self::ASSET_NAME);
        $asset_response = wp_remote_head(
            $asset_url,
            array(
                'timeout'     => 10,
                'redirection' => 0,
                'headers'     => array('User-Agent' => 'Persona26/' . P26_VERSION),
            )
        );
        $asset_code = is_wp_error($asset_response) ? 0 : (int) wp_remote_retrieve_response_code($asset_response);
        if ($asset_code < 200 || $asset_code >= 400) {
            $api_error['fallback_asset_code'] = $asset_code;
            self::store_lookup_error($api_error);
            return null;
        }

        $release = array(
            'tag_name' => $tag,
            'html_url' => self::repository_url() . '/releases/tag/' . rawurlencode($tag),
            'body'     => '',
            'assets'   => array(
                array(
                    'name'                 => self::ASSET_NAME,
                    'browser_download_url' => $asset_url,
                ),
            ),
        );

        self::cache_release($release);
        return $release;
    }

    private static function cache_release(array $release): void {
        $version = self::release_version($release);
        if (!$version) {
            return;
        }

        $expiration = version_compare($version, P26_VERSION, '>') ? 6 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS;
        set_site_transient(self::RELEASE_TRANSIENT, $release, $expiration);
        delete_site_transient(self::ERROR_TRANSIENT);
    }

    private static function store_lookup_error(array $error): void {
        delete_site_transient(self::RELEASE_TRANSIENT);
        set_site_transient(self::ERROR_TRANSIENT, $error, 10 * MINUTE_IN_SECONDS);
    }

    private static function clear_release_cache(): void {
        delete_site_transient(self::RELEASE_TRANSIENT);
        delete_site_transient(self::ERROR_TRANSIENT);
    }

    private static function is_forced_update_check(): bool {
        if (!current_user_can('update_plugins')) {
            return false;
        }

        $force_check = isset($_GET['force-check']) || isset($_POST['force-check']);
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash((string) $_REQUEST['action'])) : '';

        return $force_check || in_array($action, array('update-selected', 'upgrade-plugin', 'do-plugin-upgrade'), true);
    }

    private static function release_version(array $release): string {
        return ltrim((string) ($release['tag_name'] ?? ''), 'vV');
    }

    private static function release_asset_url(array $release): string {
        foreach ((array) ($release['assets'] ?? array()) as $asset) {
            if (self::ASSET_NAME === ($asset['name'] ?? '') && !empty($asset['browser_download_url'])) {
                return esc_url_raw((string) $asset['browser_download_url']);
            }
        }

        return '';
    }

    private static function manual_check_url(): string {
        return wp_nonce_url(
            add_query_arg(self::MANUAL_CHECK_ACTION, '1', self::plugins_page_url()),
            self::MANUAL_CHECK_ACTION
        );
    }

    private static function plugins_page_url(): string {
        return is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php');
    }

    private static function repository_url(): string {
        return 'https://github.com/' . self::OWNER . '/' . self::REPO;
    }
}

P26_GitHub_Updater::init();
