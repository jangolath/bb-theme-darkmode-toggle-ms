<?php
/**
 * Plugin Name: BuddyBoss Theme Dark Mode Toggle
 * Plugin URI: https://bluespringsweb.com
 * Description: Adds light/dark mode toggle and profile frame options for BuddyBoss users
 * Version: 1.0.2
 * Author: Jason Wood
 * Author URI: https://bluespringsweb.com
 * Text Domain: bb-theme-darkmode-toggle
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('BB_THEME_DARKMODE_TOGGLE_VERSION', '1.0.2');
define('BB_THEME_DARKMODE_TOGGLE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BB_THEME_DARKMODE_TOGGLE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BB_THEME_DARKMODE_TOGGLE_NETWORK_WIDE', true); // Use network-wide preferences

// Include required files
require_once BB_THEME_DARKMODE_TOGGLE_PLUGIN_DIR . 'includes/class-bb-theme-darkmode-toggle.php';

// Initialize the plugin
function bb_theme_darkmode_toggle_init() {
    // Check if BuddyBoss is active
    if (class_exists('BuddyPress') || class_exists('BP_Component')) {
        $plugin = new BB_Theme_Darkmode_Toggle();
        $plugin->initialize();
    } else {
        // Add admin notice if BuddyBoss is not active
        add_action('admin_notices', 'bb_theme_darkmode_toggle_admin_notice');
    }
}
add_action('plugins_loaded', 'bb_theme_darkmode_toggle_init');

// Admin notice for BuddyBoss dependency
function bb_theme_darkmode_toggle_admin_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('BuddyBoss Theme Darkmode Toggle requires BuddyBoss Platform to be installed and activated.', 'bb-theme-darkmode-toggle'); ?></p>
    </div>
    <?php
}

// Activation hook
function bb_theme_darkmode_toggle_activate() {
    // Create database tables if needed
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // We'll use user meta instead of custom tables
    // But you can add custom tables here if needed
}
register_activation_hook(__FILE__, 'bb_theme_darkmode_toggle_activate');

// Deactivation hook
function bb_theme_darkmode_toggle_deactivate() {
    // Cleanup when plugin is deactivated
}
register_deactivation_hook(__FILE__, 'bb_theme_darkmode_toggle_deactivate');