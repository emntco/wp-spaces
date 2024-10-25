<?php
/*
Plugin Name: WordPress Spaces
Description: Offloads media uploads to DigitalOcean Spaces.
Version: 0.6
Author: emnt.co
Text Domain: wp-spaces
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Include admin notices for missing dependencies
    require_once __DIR__ . '/includes/admin/notices.php';
    add_action('admin_notices', 'wp_spaces_missing_dependencies_notice');
    return;
}

// Register activation and deactivation hooks
require_once __DIR__ . '/includes/core/functions.php';
register_activation_hook(__FILE__, 'wp_spaces_activate');
register_deactivation_hook(__FILE__, 'wp_spaces_deactivate');

// Include core initialization file
require_once __DIR__ . '/includes/core/init.php';

// Include admin menu and settings
require_once __DIR__ . '/includes/admin/menu.php';
require_once __DIR__ . '/includes/admin/settings.php';
require_once __DIR__ . '/includes/admin/actions.php';
