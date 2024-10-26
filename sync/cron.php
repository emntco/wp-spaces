<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Custom Cron Interval
function wp_spaces_custom_cron_interval($schedules) {
    $schedules['wp_spaces_migration_interval'] = array(
        'interval' => 5, // Every 5 seconds
        'display'  => __('Every 5 seconds', 'wp-spaces'),
    );
    return $schedules;
}

// Register the custom cron interval
add_filter('cron_schedules', 'wp_spaces_custom_cron_interval');

// Clear scheduled events on plugin deactivation
function wp_spaces_clear_scheduled_events() {
    wp_clear_scheduled_hook('wp_spaces_sync_event');
    wp_clear_scheduled_hook('wp_spaces_reverse_sync_event');
}

// This function is called in the deactivation hook in functions.php
