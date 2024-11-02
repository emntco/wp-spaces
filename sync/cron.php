<?php
/**
 * Cron Job Handler
 * 
 * Manages WordPress cron schedules and events for synchronization tasks.
 * Provides custom intervals and cleanup functions for scheduled events.
 * 
 * @package WordPress_Spaces
 * @subpackage Sync
 * @since 0.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers a custom cron interval for migration tasks.
 * 
 * Adds a 5-second interval to WordPress cron schedules specifically
 * for handling media synchronization in small batches.
 * 
 * @since 0.6
 * @param array $schedules Existing WordPress cron schedules
 * @return array Modified cron schedules
 */
function wp_spaces_custom_cron_interval($schedules) {
    $schedules['wp_spaces_migration_interval'] = array(
        'interval' => 5, // Every 5 seconds
        'display'  => __('Every 5 seconds', 'wp-spaces'),
    );
    return $schedules;
}

// Register the custom cron interval
add_filter('cron_schedules', 'wp_spaces_custom_cron_interval');

/**
 * Cleans up scheduled synchronization events.
 * 
 * Removes all scheduled sync and reverse-sync events from WordPress cron
 * when the plugin is deactivated or cleanup is needed.
 * 
 * @since 0.6
 * @return void
 */
function wp_spaces_clear_scheduled_events() {
    wp_clear_scheduled_hook('wp_spaces_sync_event');
    wp_clear_scheduled_hook('wp_spaces_reverse_sync_event');
}

// This function is called in the deactivation hook in functions.php
