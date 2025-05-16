<?php
/**
 * Plugin Name: BuddyBoss Theme Dark Mode Toggle
 * Plugin URI: https://bluespringsweb.com
 * Description: Adds light/dark mode toggle for BuddyBoss users with integration for popular plugins
 * Version: 1.1.1
 * Author: Jason Wood
 * Text Domain: bb-theme-darkmode-toggle
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('BB_THEME_DARKMODE_TOGGLE_VERSION', '1.1.1');
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
    // Default settings for the plugin
    $default_options = array(
        'enable_dark_mode' => 'yes',
        'plugin_integrations' => array(
            'buddyboss' => 'yes',
            'better_messages' => 'no',
            'tutor_lms' => 'no',
            'events_calendar' => 'no',
            'dokan' => 'no'
        )
    );
    
    // Add default options if they don't exist
    if (!get_option('bb_theme_darkmode_toggle_settings')) {
        add_option('bb_theme_darkmode_toggle_settings', $default_options);
    }
}
register_activation_hook(__FILE__, 'bb_theme_darkmode_toggle_activate');

// Deactivation hook
function bb_theme_darkmode_toggle_deactivate() {
    // Cleanup when plugin is deactivated
    // We're keeping the settings in case the plugin is reactivated
}
register_deactivation_hook(__FILE__, 'bb_theme_darkmode_toggle_deactivate');