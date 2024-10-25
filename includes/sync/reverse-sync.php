<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary utilities
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../core/aws-client.php';

// Start Reverse Synchronization
function wp_spaces_start_reverse_sync() {
    $s3 = wp_spaces_get_s3_client();
    $options = get_option('wp_spaces_settings');
    $bucket = $options['space_name'];
    
    try {
        // Count total files
        $total_items = 0;
        $marker = null;
        
        do {
            $args = [
                'Bucket' => $bucket,
                'Prefix' => 'wp-content/uploads/'
            ];
            
            if ($marker) {
                $args['Marker'] = $marker;
            }
            
            $objects = $s3->listObjects($args);
            
            // Don't count the directory itself
            foreach ($objects['Contents'] as $object) {
                if ($object['Key'] !== 'wp-content/uploads/') {
                    $total_items++;
                }
            }
            
            // Set marker for next batch if there are more files
            $marker = $objects['IsTruncated'] ? end($objects['Contents'])['Key'] : null;
            
        } while ($objects['IsTruncated']);
        
        update_option('wp_spaces_reverse_sync_total', $total_items);
        update_option('wp_spaces_reverse_sync_progress', 0);
        delete_transient('wp_spaces_reverse_sync_marker');
        
        // Set transient to indicate reverse sync is in progress
        set_transient('wp_spaces_reverse_sync_in_progress', true, HOUR_IN_SECONDS);

        // Schedule the reverse sync event
        if (!wp_next_scheduled('wp_spaces_reverse_sync_event')) {
            wp_schedule_event(time(), 'wp_spaces_migration_interval', 'wp_spaces_reverse_sync_event');
        }
        
    } catch (Exception $e) {
        wp_spaces_log_error('Error starting reverse sync: ' . $e->getMessage());
        return;
    }
}

// Process Reverse Synchronization Batch
function wp_spaces_process_reverse_sync_batch() {
    $s3 = wp_spaces_get_s3_client();
    if (!$s3) {
        wp_spaces_log_error('Failed to initialize S3 client for reverse sync');
        return;
    }

    $options = get_option('wp_spaces_settings');
    $bucket = $options['space_name'];
    $wp_upload_dir = wp_upload_dir();
    $processed_count = 0;

    // Get the marker from transient
    $marker = get_transient('wp_spaces_reverse_sync_marker');
    
    try {
        $prefix = !empty($options['use_subfolder']) && !empty($options['subfolder_name']) 
            ? trailingslashit($options['subfolder_name']) . 'wp-content/uploads/'
            : 'wp-content/uploads/';

        $args = [
            'Bucket' => $bucket,
            'Prefix' => $prefix,
            'MaxKeys' => 10
        ];

        if ($marker) {
            $args['Marker'] = $marker;
        }

        $objects = $s3->listObjects($args);
        
        if (empty($objects['Contents'])) {
            // No more files to process
            delete_transient('wp_spaces_reverse_sync_marker');
            delete_transient('wp_spaces_reverse_sync_in_progress');
            delete_option('wp_spaces_reverse_sync_total');
            delete_option('wp_spaces_reverse_sync_progress');
            wp_clear_scheduled_hook('wp_spaces_reverse_sync_event');
            
            // Only now disable sync completely
            update_option('wp_spaces_sync_enabled', false);
            
            wp_spaces_log_error('Reverse sync completed successfully');
            return;
        }

        foreach ($objects['Contents'] as $object) {
            $key = $object['Key'];
            
            try {
                // Remove the prefix to get the relative path
                $relative_path = str_replace($prefix, '', $key);
                $local_path = trailingslashit($wp_upload_dir['basedir']) . $relative_path;
                
                // Create directory if it doesn't exist
                wp_mkdir_p(dirname($local_path));

                // Download the file
                $s3->getObject([
                    'Bucket' => $bucket,
                    'Key'    => $key,
                    'SaveAs' => $local_path
                ]);

                // Verify local file exists and has content
                if (file_exists($local_path) && filesize($local_path) > 0) {
                    // Delete from Spaces only after successful download
                    $s3->deleteObject([
                        'Bucket' => $bucket,
                        'Key'    => $key
                    ]);
                    
                    wp_spaces_log_error("Successfully reversed synced and deleted: " . $key);
                    $processed_count++;

                    // Update post meta to indicate file is local
                    wp_spaces_update_attachment_location($relative_path, 'local');
                } else {
                    wp_spaces_log_error("Failed to verify local file: " . $local_path);
                }
            } catch (Exception $e) {
                wp_spaces_log_error("Error processing file {$key}: " . $e->getMessage());
                continue;
            }
        }

        // Update marker for next batch
        if (!empty($objects['Contents'])) {
            $last_key = end($objects['Contents'])['Key'];
            set_transient('wp_spaces_reverse_sync_marker', $last_key, HOUR_IN_SECONDS);
        }

        // Update progress
        $current_progress = get_option('wp_spaces_reverse_sync_progress', 0);
        update_option('wp_spaces_reverse_sync_progress', $current_progress + $processed_count);

    } catch (Exception $e) {
        wp_spaces_log_error('Error in reverse sync batch: ' . $e->getMessage());
    }
}

// Helper function to track attachment locations
function wp_spaces_update_attachment_location($relative_path, $location) {
    global $wpdb;
    
    // Find the attachment ID based on the relative path
    $meta_value = str_replace('wp-content/uploads/', '', $relative_path);
    $attachment_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
        $meta_value
    ));
    
    if ($attachment_id) {
        update_post_meta($attachment_id, '_wp_spaces_location', $location);
    }
}
