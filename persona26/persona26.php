<?php
/**
 * Plugin Name: Persona26
 * Description: Visitor profiles, engagement dimensions and personalisation with Independent Analytics and Gravity Forms.
 * Version: 0.7.6
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/cchatterton/persona26
 * Author: Techn
 * Author URI: https://techn.com.au
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Techn Controller API: 1
 * Text Domain: persona26
 */

if (!defined('ABSPATH')) {
    exit;
}

define('P26_VERSION', '0.7.6');
define('P26_PLUGIN_FILE', __FILE__);
define('P26_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('P26_PLUGIN_URL', plugin_dir_url(__FILE__));

$dir = P26_PLUGIN_DIR;
$functions = array(
    'init.php', 
    'cookies.php',
    'functions.php',
    'assets.php',
    'track.php', 
    'options.php', 
    'meta.php',
    'migration-references.php',
    'migration.php',
    'migration-admin.php',
    'profile.php', 
    'personalize.php',
    'gravity-forms.php',
 );
    
foreach ($functions as $function ) {
    require_once $dir . 'functions/' . $function;
}

register_activation_hook(P26_PLUGIN_FILE, 'p26_activate');
register_deactivation_hook(P26_PLUGIN_FILE, 'p26_deactivate');

require_once __DIR__ . '/functions/controller-client.php';
tnuc_client_register(__FILE__, 'persona26');
