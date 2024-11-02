<?php
/**
 * Admin Actions Handler
 * 
 * This file handles all administrative actions related to synchronization between
 * WordPress and DigitalOcean Spaces, including enabling/disabling sync, canceling operations,
 * and monitoring progress.
 * 
 * @package WordPress_Spaces
 * @subpackage Admin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles all synchronization-related actions in the admin area.
 * 
 * Processes form submissions for:
 * - Enabling synchronization
 * - Disabling synchronization
 * - Canceling ongoing sync operations
 * 
 * @since 0.6
 * @return void
 */
add_action('admin_init', 'wp_spaces_handle_sync_actions');
function wp_spaces_handle_sync_actions() {
    
    // Enable Sync Action
    if (isset($_POST['wp_spaces_enable_sync'])) {
        // Verify nonce for security
        if (!check_admin_referer('wp_spaces_enable_sync', 'wp_spaces_enable_sync_nonce')) {
            wp_die(__('Nonce verification failed', 'wp-spaces'));
        }

        // Enable and start synchronization
        update_option('wp_spaces_sync_enabled', true);
        wp_spaces_start_sync();

        // Display success message
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . 
                __('Synchronization has started. You can leave this page, and the process will continue in the background.', 'wp-spaces') . 
                '</p></div>';
        });
    }

    // Disable Sync Action
    if (isset($_POST['wp_spaces_disable_sync'])) {
        // Verify nonce for security
        if (!check_admin_referer('wp_spaces_disable_sync', 'wp_spaces_disable_sync_nonce')) {
            wp_die(__('Nonce verification failed', 'wp-spaces'));
        }

        // Start reverse synchronization process
        update_option('wp_spaces_sync_enabled', false);
        wp_spaces_start_reverse_sync();

        // Display success message
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . 
                __('Reverse synchronization has started. You can leave this page, and the process will continue in the background.', 'wp-spaces') . 
                '</p></div>';
        });
    }

    // Cancel Sync Action
    if (isset($_POST['action']) && $_POST['action'] === 'cancel_sync') {
        // Verify nonce and user capabilities
        if (!isset($_POST['wp_spaces_cancel_sync_nonce']) || 
            !wp_verify_nonce($_POST['wp_spaces_cancel_sync_nonce'], 'wp_spaces_cancel_sync')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        // Clean up all sync-related data
        delete_transient('wp_spaces_sync_in_progress');
        delete_transient('wp_spaces_reverse_sync_in_progress');
        delete_option('wp_spaces_sync_total');
        delete_option('wp_spaces_sync_progress');
        delete_option('wp_spaces_reverse_sync_total');
        delete_option('wp_spaces_reverse_sync_progress');
        delete_transient('wp_spaces_sync_offset');
        wp_clear_scheduled_hook('wp_spaces_sync_event');
        wp_clear_scheduled_hook('wp_spaces_reverse_sync_event');
        update_option('wp_spaces_sync_enabled', false);

        // Display cancellation message
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . 
                __('Synchronization has been cancelled.', 'wp-spaces') . 
                '</p></div>';
        });
    }
}

/**
 * AJAX handler for retrieving sync progress information.
 * 
 * Returns JSON response containing:
 * - total: Total number of items to process
 * - progress: Number of items processed
 * - type: Type of sync (Sync/Reverse Sync)
 * - complete: Boolean indicating if sync is complete
 * 
 * @since 0.6
 * @return void Sends JSON response
 */
add_action('wp_ajax_wp_spaces_get_progress', 'wp_spaces_ajax_get_progress');
function wp_spaces_ajax_get_progress() {
    // Verify nonce and user capabilities
    check_ajax_referer('wp_spaces_progress_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    // Check sync status and gather progress data
    $sync_in_progress = get_transient('wp_spaces_sync_in_progress');
    $reverse_sync_in_progress = get_transient('wp_spaces_reverse_sync_in_progress');

    if ($sync_in_progress) {
        $total = get_option('wp_spaces_sync_total', 0);
        $progress = get_option('wp_spaces_sync_progress', 0);
        $type = 'Sync';
    } elseif ($reverse_sync_in_progress) {
        $total = get_option('wp_spaces_reverse_sync_total', 0);
        $progress = get_option('wp_spaces_reverse_sync_progress', 0);
        $type = 'Reverse Sync';
    } else {
        // Return complete status if no sync is in progress
        wp_send_json_success(['complete' => true]);
        return;
    }

    // Send progress data
    wp_send_json_success([
        'total' => $total,
        'progress' => $progress,
        'type' => $type,
        'complete' => false
    ]);
}

/**
 * Secondary admin action handler for sync operations.
 * 
 * Processes additional sync-related actions through a different form submission path.
 * Includes debug logging for better troubleshooting.
 * 
 * @since 0.6
 * @return void
 */
add_action('admin_init', 'wp_spaces_handle_admin_actions');
function wp_spaces_handle_admin_actions() {
    // Early return if no action or nonce is present
    if (!isset($_POST['action']) || !isset($_POST['wp_spaces_sync_nonce'])) {
        return;
    }

    // Log incoming action for debugging
    wp_spaces_log_error('Action received: ' . $_POST['action']);

    // Security checks
    if (!wp_verify_nonce($_POST['wp_spaces_sync_nonce'], 'wp_spaces_sync_action')) {
        wp_die('Security check failed');
    }

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    // Process different action types
    switch ($_POST['action']) {
        case 'enable_sync':
            // Log sync enablement
            wp_spaces_log_error('Enabling sync');
            
            // Enable and initiate sync
            update_option('wp_spaces_sync_enabled', true);
            wp_spaces_start_sync();
            
            // Display success message
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . 
                    __('Synchronization has been enabled and started.', 'wp-spaces') . 
                    '</p></div>';
            });
            break;

        case 'disable_sync':
            // Log sync disablement attempt
            wp_spaces_log_error('Disabling sync');
            
            // Check if sync can be safely disabled
            if (wp_spaces_can_disable_sync()) {
                // Disable sync if all files are local
                update_option('wp_spaces_sync_enabled', false);
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>' . 
                        __('Synchronization has been disabled.', 'wp-spaces') . 
                        '</p></div>';
                });
            } else {
                // Initiate reverse sync if files need to be downloaded
                wp_spaces_start_reverse_sync();
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-info"><p>' . 
                        __('Reverse synchronization has started. Sync will be disabled once all files are local.', 'wp-spaces') . 
                        '</p></div>';
                });
            }
            break;

        // Additional cases can be added here
    }
}

/**
 * Checks if synchronization can be safely disabled.
 * 
 * Verifies that:
 * 1. No reverse sync is currently in progress
 * 2. All media files have local copies available
 * 
 * @since 0.6
 * @global wpdb $wpdb WordPress database abstraction object
 * @return boolean True if sync can be disabled, false otherwise
 */
function wp_spaces_can_disable_sync() {
    global $wpdb;
    
    // Check for ongoing reverse sync
    if (get_transient('wp_spaces_reverse_sync_in_progress')) {
        return false;
    }
    
    // Get all attachment IDs
    $attachments = $wpdb->get_col(
        "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment'"
    );
    
    // Verify local copies exist for all attachments
    foreach ($attachments as $attachment_id) {
        $file_meta = get_post_meta($attachment_id, '_wp_attached_file', true);
        if (!$file_meta) continue;
        
        // Build local file path
        $wp_upload_dir = wp_upload_dir();
        $local_path = trailingslashit($wp_upload_dir['basedir']) . $file_meta;
        
        // If any file is missing locally, sync cannot be disabled
        if (!file_exists($local_path)) {
            return false;
        }
    }
    
    // All checks passed, safe to disable sync
    return true;
}
