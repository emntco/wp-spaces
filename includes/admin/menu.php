<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wp_spaces_action_links');
function wp_spaces_action_links($links) {
    $settings_link = '<a href="options-general.php?page=wp-spaces">' . __('Settings', 'wp-spaces') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Add admin menu
add_action('admin_menu', 'wp_spaces_add_admin_menu');
function wp_spaces_add_admin_menu() {
    add_options_page(
        __('WordPress Spaces Settings', 'wp-spaces'),
        __('WordPress Spaces', 'wp-spaces'),
        'manage_options',
        'wp-spaces',
        'wp_spaces_options_page'
    );
}
