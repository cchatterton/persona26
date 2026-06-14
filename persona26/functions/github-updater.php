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
    private const FAILED_TRANSIENT = 'p26_github_latest_release_failed';

    public static function init(): void {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject_update'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_information'), 10, 3);
        add_filter('plugin_row_meta', array(__CLASS__, 'plugin_row_meta'), 10, 2);
    }

    public static function inject_update($transient) {
        if (!is_object($transient)) {
            return $transient;
        }

        $release = self::get_latest_release();
        if (empty($release)) {
            return $transient;
        }

        $version = self::release_version($release);
        $download_url = self::release_asset_url($release);
        $plugin_file = plugin_basename(P26_PLUGIN_FILE);

        if (empty($version) || empty($download_url) || !version_compare($version, P26_VERSION, '>')) {
            return self::clear_stale_update($transient, $plugin_file);
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

        return $transient;
    }

    public static function plugin_information($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || self::SLUG !== $args->slug) {
            return $result;
        }

        $release = self::get_latest_release();
        if (empty($release)) {
            return $result;
        }

        $version = self::release_version($release);
        $download_url = self::release_asset_url($release);

        if (empty($version) || empty($download_url)) {
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

        return $links;
    }

    private static function get_latest_release() {
        if (self::is_forced_update_check()) {
            delete_site_transient(self::RELEASE_TRANSIENT);
            delete_site_transient(self::FAILED_TRANSIENT);
        }

        $release = get_site_transient(self::RELEASE_TRANSIENT);
        if (is_array($release)) {
            return $release;
        }

        if (get_site_transient(self::FAILED_TRANSIENT)) {
            return array();
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

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            set_site_transient(self::FAILED_TRANSIENT, 1, 30 * MINUTE_IN_SECONDS);
            return array();
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($release)) {
            set_site_transient(self::FAILED_TRANSIENT, 1, 30 * MINUTE_IN_SECONDS);
            return array();
        }

        set_site_transient(self::RELEASE_TRANSIENT, $release, 6 * HOUR_IN_SECONDS);
        delete_site_transient(self::FAILED_TRANSIENT);

        return $release;
    }

    private static function clear_stale_update($transient, $plugin_file) {
        if (isset($transient->response[$plugin_file])) {
            unset($transient->response[$plugin_file]);
        }

        $transient->no_update[$plugin_file] = (object) array(
            'id'           => self::repository_url(),
            'slug'         => self::SLUG,
            'plugin'       => $plugin_file,
            'new_version'  => P26_VERSION,
            'url'          => self::repository_url(),
            'package'      => '',
            'requires'     => '6.0',
            'requires_php' => '8.1',
        );

        return $transient;
    }

    private static function is_forced_update_check(): bool {
        $force_check = isset($_GET['force-check']) ? sanitize_text_field(wp_unslash($_GET['force-check'])) : '';

        return '1' === $force_check;
    }

    private static function release_version($release): string {
        return ltrim((string) ($release['tag_name'] ?? ''), 'vV');
    }

    private static function release_asset_url($release): string {
        if (empty($release['assets']) || !is_array($release['assets'])) {
            return '';
        }

        foreach ($release['assets'] as $asset) {
            if (self::ASSET_NAME === ($asset['name'] ?? '') && !empty($asset['browser_download_url'])) {
                return esc_url_raw((string) $asset['browser_download_url']);
            }
        }

        return '';
    }

    private static function repository_url(): string {
        return 'https://github.com/' . self::OWNER . '/' . self::REPO;
    }
}

P26_GitHub_Updater::init();
