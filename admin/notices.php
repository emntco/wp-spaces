<?php
/**
 * Admin Notices Handler
 * 
 * Manages administrative notices and error messages for the WordPress Spaces plugin,
 * particularly focusing on dependency requirements and installation issues.
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
 * Displays an admin notice when required dependencies are missing.
 * 
 * Shows an error message in the WordPress admin panel when the AWS SDK
 * is not properly installed via Composer. This typically occurs when
 * the plugin is newly installed or when dependencies are not properly
 * set up.
 * 
 * @since 0.6
 * @return void
 */
function wp_spaces_missing_dependencies_notice() {
    echo '<div class="notice notice-error"><p>' . 
        __('Error: AWS SDK not found. Please run <code>composer install</code> in the plugin directory.', 'wp-spaces') . 
        '</p></div>';
}
