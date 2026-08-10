<?php
/**
 * Plugin Name: Persona26
 * Plugin URI: https://github.com/cchatterton/persona26/releases/latest
 * Description: Extends IA with cookie and tracks related sessions
 * Version: 0.3
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Update URI: https://github.com/cchatterton/persona26
 * Author: Techn
 * Author URI: https://techn.com.au
 * Text Domain: persona26
 */

if (!defined('ABSPATH')) {
    exit;
}

define('P26_VERSION', '0.3');
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
    'profile.php', 
    'personalize.php',
    'github-updater.php',
 );
    
foreach ($functions as $function ) {
    require_once $dir . 'functions/' . $function;
}

register_activation_hook(P26_PLUGIN_FILE, 'p26_activate');
register_deactivation_hook(P26_PLUGIN_FILE, 'p26_deactivate');
