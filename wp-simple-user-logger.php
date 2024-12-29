<?php
/*
Plugin Name: WP Simple User Logger
Plugin URI: 
Description: Logs all user-related activities to a file with detailed information
Version: 1.0.0
Author: Luciano Hanna
Author URI: 
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: wp-simple-user-logger
Domain Path: /languages
*/

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('WSUL_VERSION', '1.0.0');
define('WSUL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WSUL_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the main class file
require_once WSUL_PLUGIN_DIR . 'includes/class-simple-user-logger.php';

// Initialize the plugin
function run_wp_simple_user_logger() {
    new SimpleUserLogger();
}
add_action('plugins_loaded', 'run_wp_simple_user_logger');
