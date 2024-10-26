<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Admin Notice for Missing Dependencies
function wp_spaces_missing_dependencies_notice() {
    echo '<div class="notice notice-error"><p>' . __('Error: AWS SDK not found. Please run <code>composer install</code> in the plugin directory.', 'wp-spaces') . '</p></div>';
}
