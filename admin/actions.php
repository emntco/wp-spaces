<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Handle Synchronization Actions
add_action('admin_init', 'wp_spaces_handle_sync_actions');
function wp_spaces_handle_sync_actions() {
    
    // Enable Sync
    if (isset($_POST['wp_spaces_enable_sync'])) {
        if (!check_admin_referer('wp_spaces_enable_sync', 'wp_spaces_enable_sync_nonce')) {
            wp_die(__('Nonce verification failed', 'wp-spaces'));
        }

        // Start the synchronization process
        update_option('wp_spaces_sync_enabled', true);
        wp_spaces_start_sync();

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Synchronization has started. You can leave this page, and the process will continue in the background.', 'wp-spaces') . '</p></div>';
        });
    }

    // Disable Sync
    if (isset($_POST['wp_spaces_disable_sync'])) {
        if (!check_admin_referer('wp_spaces_disable_sync', 'wp_spaces_disable_sync_nonce')) {
            wp_die(__('Nonce verification failed', 'wp-spaces'));
        }

        // Start the reverse synchronization process
        update_option('wp_spaces_sync_enabled', false);
        wp_spaces_start_reverse_sync();

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Reverse synchronization has started. You can leave this page, and the process will continue in the background.', 'wp-spaces') . '</p></div>';
        });
    }

    // Cancel Sync
    if (isset($_POST['action']) && $_POST['action'] === 'cancel_sync') {
        if (!isset($_POST['wp_spaces_cancel_sync_nonce']) || 
            !wp_verify_nonce($_POST['wp_spaces_cancel_sync_nonce'], 'wp_spaces_cancel_sync')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        // Clear all sync-related data
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

        // Add success notice
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Synchronization has been cancelled.', 'wp-spaces') . '</p></div>';
        });
    }
}

// Handle the AJAX request
add_action('wp_ajax_wp_spaces_get_progress', 'wp_spaces_ajax_get_progress');
function wp_spaces_ajax_get_progress() {
    check_ajax_referer('wp_spaces_progress_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

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
        // Sync is complete
        wp_send_json_success([
            'complete' => true
        ]);
        return;
    }

    wp_send_json_success([
        'total' => $total,
        'progress' => $progress,
        'type' => $type,
        'complete' => false
    ]);
}

// Add this if it's not already present
add_action('admin_init', 'wp_spaces_handle_admin_actions');

function wp_spaces_handle_admin_actions() {
    if (!isset($_POST['action']) || !isset($_POST['wp_spaces_sync_nonce'])) {
        return;
    }

    // Debug log for the action
    wp_spaces_log_error('Action received: ' . $_POST['action']);

    // Verify nonce
    if (!wp_verify_nonce($_POST['wp_spaces_sync_nonce'], 'wp_spaces_sync_action')) {
        wp_die('Security check failed');
    }

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    switch ($_POST['action']) {
        case 'enable_sync':
            // Debug log
            wp_spaces_log_error('Enabling sync');
            
            update_option('wp_spaces_sync_enabled', true);
            wp_spaces_start_sync();
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Synchronization has been enabled and started.', 'wp-spaces') . '</p></div>';
            });
            break;

        case 'disable_sync':
            wp_spaces_log_error('Disabling sync');
            
            if (wp_spaces_can_disable_sync()) {
                update_option('wp_spaces_sync_enabled', false);
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>' . 
                        __('Synchronization has been disabled.', 'wp-spaces') . 
                        '</p></div>';
                });
            } else {
                wp_spaces_start_reverse_sync();
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-info"><p>' . 
                        __('Reverse synchronization has started. Sync will be disabled once all files are local.', 'wp-spaces') . 
                        '</p></div>';
                });
            }
            break;

        // ... other cases ...
    }
}

// Helper function to check if sync can be disabled
function wp_spaces_can_disable_sync() {
    global $wpdb;
    
    // Check if reverse sync is in progress
    if (get_transient('wp_spaces_reverse_sync_in_progress')) {
        return false;
    }
    
    // Check if all files are local
    $attachments = $wpdb->get_col(
        "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment'"
    );
    
    foreach ($attachments as $attachment_id) {
        $file_meta = get_post_meta($attachment_id, '_wp_attached_file', true);
        if (!$file_meta) continue;
        
        $wp_upload_dir = wp_upload_dir();
        $local_path = trailingslashit($wp_upload_dir['basedir']) . $file_meta;
        
        if (!file_exists($local_path)) {
            return false;
        }
    }
    
    return true;
}
