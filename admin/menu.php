<?php
/**
 * Admin Menu Handler
 * 
 * Handles the creation and configuration of admin menu items for WordPress Spaces,
 * including the settings page link and menu registration.
 * 
 * @package WordPress_Spaces
 * @subpackage Admin
 * @since 0.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds a settings link to the plugins page.
 * 
 * Filters the plugin action links to add a 'Settings' link that directs users
 * to the WordPress Spaces configuration page.
 * 
 * @since 0.6
 * @param array $links Array of plugin action links
 * @return array Modified array of plugin action links
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wp_spaces_action_links');
function wp_spaces_action_links($links) {
    // Create the settings link with proper URL
    $settings_link = '<a href="options-general.php?page=wp-spaces">' . 
        __('Settings', 'wp-spaces') . 
        '</a>';
    
    // Add settings link to beginning of the array
    array_unshift($links, $settings_link);
    
    return $links;
}

/**
 * Registers the WordPress Spaces settings page in the admin menu.
 * 
 * Creates a new submenu page under the 'Settings' menu that contains
 * all configuration options for the WordPress Spaces plugin.
 * 
 * @since 0.6
 * @return void
 */
add_action('admin_menu', 'wp_spaces_add_admin_menu');
function wp_spaces_add_admin_menu() {
    add_options_page(
        __('WordPress Spaces Settings', 'wp-spaces'),  // Page title
        __('WordPress Spaces', 'wp-spaces'),          // Menu title
        'manage_options',                             // Capability required
        'wp-spaces',                                  // Menu slug
        'wp_spaces_options_page'                      // Callback function
    );
}
