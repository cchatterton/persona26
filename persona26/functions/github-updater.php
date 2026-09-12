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
    private const BACKOFF_TRANSIENT = 'p26_github_backoff';
    private static bool $forced_check_started = false;
    private const MANUAL_CHECK_ACTION = 'p26_check_updates';

    public static function init(): void {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject_update'));
        add_filter('site_transient_update_plugins', array(__CLASS__, 'inject_update'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_information'), 10, 3);
        add_filter('plugin_row_meta', array(__CLASS__, 'plugin_row_meta'), 10, 2);
        add_action('admin_init', array(__CLASS__, 'handle_manual_update_check'));
        add_action('admin_notices', array(__CLASS__, 'manual_check_notice'));
        add_action('network_admin_notices', array(__CLASS__, 'manual_check_notice'));
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
                'description' => 'Extends Independent Analytics with visitor persona tracking, Gravity Forms integration, and personalisation.',
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
        if (!current_user_can('update_plugins')) {
            return $links;
        }
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
        self::$forced_check_started = true;
        delete_site_transient('update_plugins');

        if (!function_exists('wp_update_plugins')) {
            require_once ABSPATH . 'wp-includes/update.php';
        }

        wp_update_plugins();
        $transient = self::inject_update(get_site_transient('update_plugins'));
        set_site_transient('update_plugins', $transient);
        $result = get_site_transient(self::ERROR_TRANSIENT) ? 'failed' :
            (isset($transient->response[plugin_basename(P26_PLUGIN_FILE)]) ? 'available' : 'current');
        set_transient('p26_update_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('p26_update_result', $result, self::plugins_page_url()));
        exit;
    }

    public static function clear_cache_after_upgrade($upgrader, $hook_extra): void {
        if (!is_array($hook_extra) || 'plugin' !== ($hook_extra['type'] ?? '') || 'update' !== ($hook_extra['action'] ?? '')
            || (isset($upgrader->result) && is_wp_error($upgrader->result))) {
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

    public static function manual_check_notice(): void {
        $screen = get_current_screen();
        if (!current_user_can('update_plugins') || !$screen || !in_array($screen->id, array('plugins', 'plugins-network'), true)) {
            return;
        }
        $key = 'p26_update_result_' . get_current_user_id();
        $result = get_transient($key);
        $messages = array(
            'available' => __('A Persona26 update is available. Use the update now link below.', 'persona26'),
            'current' => __('Persona26 is up to date.', 'persona26'),
            'failed' => __('Persona26 could not check for updates. Please try again later.', 'persona26'),
        );
        if (!is_string($result) || !isset($messages[$result])) {
            return;
        }
        delete_transient($key);
        echo '<div class="notice notice-' . ('failed' === $result ? 'warning' : 'success') . ' is-dismissible"><p>' . esc_html($messages[$result]) . '</p></div>';
    }

    private static function get_latest_release(): ?array {
        // WordPress may invoke both transient hooks repeatedly during one check.
        if (!self::$forced_check_started && self::is_forced_update_check()) {
            self::clear_release_cache();
            self::$forced_check_started = true;
        }
        $cached = get_site_transient(self::RELEASE_TRANSIENT);
        if (is_array($cached) && self::release_version($cached) && self::release_asset_url($cached)) {
            return $cached;
        }
        if (get_site_transient(self::BACKOFF_TRANSIENT)) {
            return null;
        }

        $response = self::request('https://raw.githubusercontent.com/' . self::OWNER . '/' . self::REPO . '/main/update.json');
        if (self::rate_limited($response)) {
            return null;
        }
        if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
            $manifest = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($manifest) && is_string($manifest['version'] ?? null) && self::valid_version($manifest['version'])) {
                $release = self::release_from_tag('v' . $manifest['version'], is_string($manifest['body'] ?? null) ? $manifest['body'] : '');
                self::cache_release($release);
                return $release;
            }
        }

        $response = self::request(self::repository_url() . '/releases/latest', 0);
        if (self::rate_limited($response)) {
            return null;
        }
        if (!is_wp_error($response) && in_array(wp_remote_retrieve_response_code($response), array(301, 302, 303, 307, 308), true)) {
            $location = (string) wp_remote_retrieve_header($response, 'location');
            $prefix = self::repository_url() . '/releases/tag/';
            if (str_starts_with($location, $prefix)) {
                $tag = substr($location, strlen($prefix));
                if (self::valid_version(preg_replace('/^[vV]/', '', $tag))) {
                    $release = self::release_from_tag($tag, __('See the release on GitHub for full release notes.', 'persona26'));
                    self::cache_release($release);
                    return $release;
                }
            }
        }

        $response = self::request('https://api.github.com/repos/' . self::OWNER . '/' . self::REPO . '/releases/latest');
        if (self::rate_limited($response)) {
            return null;
        }
        if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
            $release = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($release) && empty($release['draft']) && empty($release['prerelease']) && self::release_version($release) && self::release_asset_url($release)) {
                self::cache_release($release);
                return $release;
            }
        }
        self::store_lookup_error(array(
            'type' => is_wp_error($response) ? 'transport' : 'invalid_release',
            'code' => is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response),
            'checked_at' => time(),
        ));
        return null;
    }

    private static function request(string $url, int $redirects = 3) {
        return wp_remote_get($url, array(
            'timeout' => 10,
            'redirection' => $redirects,
            'limit_response_size' => 262144,
            'headers' => array('Accept' => 'application/json', 'User-Agent' => 'TN-Persona26/' . P26_VERSION),
        ));
    }

    private static function rate_limited($response): bool {
        if (is_wp_error($response)) {
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        if (429 !== $code && !(403 === $code && '0' === (string) wp_remote_retrieve_header($response, 'x-ratelimit-remaining'))) {
            return false;
        }
        self::store_lookup_error(array('type' => 'rate_limit', 'code' => $code, 'checked_at' => time()));
        return true;
    }

    private static function release_from_tag(string $tag, string $body): array {
        return array(
            'tag_name' => $tag,
            'html_url' => self::repository_url() . '/releases/tag/' . rawurlencode($tag),
            'body' => $body,
            'assets' => array(array(
                'name' => self::ASSET_NAME,
                'browser_download_url' => self::repository_url() . '/releases/download/' . rawurlencode($tag) . '/' . self::ASSET_NAME,
            )),
        );
    }

    private static function valid_version(string $version): bool {
        // Older Persona26 releases used two components; preserve that compatibility.
        return (bool) preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:\.(0|[1-9][0-9]*))?$/D', $version);
    }

    private static function cache_release(array $release): void {
        $version = self::release_version($release);
        if (!$version) {
            return;
        }

        $expiration = version_compare($version, P26_VERSION, '>') ? 6 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS;
        set_site_transient(self::RELEASE_TRANSIENT, $release, $expiration);
        delete_site_transient(self::ERROR_TRANSIENT);
        delete_site_transient(self::BACKOFF_TRANSIENT);
    }

    private static function store_lookup_error(array $error): void {
        delete_site_transient(self::RELEASE_TRANSIENT);
        set_site_transient(self::ERROR_TRANSIENT, $error, 10 * MINUTE_IN_SECONDS);
        set_site_transient(self::BACKOFF_TRANSIENT, true, 10 * MINUTE_IN_SECONDS);
    }

    private static function clear_release_cache(): void {
        delete_site_transient(self::RELEASE_TRANSIENT);
        delete_site_transient(self::ERROR_TRANSIENT);
        delete_site_transient(self::BACKOFF_TRANSIENT);
    }

    private static function is_forced_update_check(): bool {
        if (!current_user_can('update_plugins')) {
            return false;
        }

        $force_check = isset($_GET['force-check']) || isset($_POST['force-check']);
        $action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
        $manual = !empty($_GET[self::MANUAL_CHECK_ACTION]) && isset($_GET['_wpnonce']) && is_string($_GET['_wpnonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), self::MANUAL_CHECK_ACTION);

        return $force_check || $manual || in_array($action, array('update-selected', 'upgrade-plugin', 'do-plugin-upgrade'), true);
    }

    private static function release_version(array $release): string {
        $tag = $release['tag_name'] ?? '';
        if (!is_string($tag)) {
            return '';
        }
        $version = preg_replace('/^[vV]/', '', $tag);
        return self::valid_version($version) ? $version : '';
    }

    private static function release_asset_url(array $release): string {
        if (!self::release_version($release)) {
            return '';
        }
        $expected = self::repository_url() . '/releases/download/' . rawurlencode($release['tag_name']) . '/' . self::ASSET_NAME;
        foreach ((array) ($release['assets'] ?? array()) as $asset) {
            if (is_array($asset) && self::ASSET_NAME === ($asset['name'] ?? '') && $expected === ($asset['browser_download_url'] ?? '')) {
                return $expected;
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
