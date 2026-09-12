<?php
/** Browser identity uses localStorage with a first-party cookie mirror. */
if (!defined('ABSPATH')) { exit; }

class p26_Cookie {
    const FOREVER = 630720000;
    public static function name(string $key): string { return 'p26_' . $key; }
    public static function outputSyncScript(string $key = 'id'): void {
        wp_enqueue_script('p26-identity', P26_PLUGIN_URL . 'scripts/persona26-identity.js', array(), P26_VERSION, false);
    }
}
function p26_output_identity_sync_script(): void {
    p26_Cookie::outputSyncScript();
}
add_action('wp_enqueue_scripts', 'p26_output_identity_sync_script', 1);
