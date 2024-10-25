<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary utilities
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../core/aws-client.php';
require_once __DIR__ . '/../core/functions.php';

// Start Synchronization
function wp_spaces_start_sync() {
    wp_spaces_log_error('Starting sync process');
    
    // Count total attachments
    $args = array(
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'post_status'    => 'any',
    );
    $attachments = get_posts($args);
    $total_items = count($attachments);
    
    // Store total and progress
    update_option('wp_spaces_sync_total', $total_items);
    update_option('wp_spaces_sync_progress', 0);
    
    // Set transient to indicate sync is in progress
    set_transient('wp_spaces_sync_in_progress', true, HOUR_IN_SECONDS);

    // Schedule the sync event
    if (!wp_next_scheduled('wp_spaces_sync_event')) {
        wp_schedule_event(time(), 'wp_spaces_migration_interval', 'wp_spaces_sync_event');
    }
}

// Process Synchronization Batch
function wp_spaces_process_sync_batch() {
    // Add debug logging
    wp_spaces_log_error('Starting sync batch process');
    
    $s3 = wp_spaces_get_s3_client();

    if (!$s3) {
        wp_spaces_log_error('DigitalOcean Spaces client not initialized.');
        return;
    }

    $options = get_option('wp_spaces_settings');
    $bucket = $options['space_name'];
    $wp_upload_dir = wp_upload_dir();

    // Log configuration
    wp_spaces_log_error('Sync configuration: ' . json_encode([
        'bucket' => $bucket,
        'upload_dir' => $wp_upload_dir['basedir']
    ]));

    // Get offset from transient
    $offset = get_transient('wp_spaces_sync_offset');
    if ($offset === false) {
        $offset = 0;
    }

    // Get all attachment posts
    $args = array(
        'post_type'      => 'attachment',
        'posts_per_page' => 10,
        'offset'         => $offset,
        'post_status'    => 'any',
    );

    $attachments = get_posts($args);
    
    // Log attachment count
    wp_spaces_log_error('Found ' . count($attachments) . ' attachments to process');

    if (empty($attachments)) {
        // No more attachments to process
        delete_transient('wp_spaces_sync_offset');
        delete_transient('wp_spaces_sync_in_progress');
        delete_option('wp_spaces_sync_total');
        delete_option('wp_spaces_sync_progress');
        wp_clear_scheduled_hook('wp_spaces_sync_event');
        // Don't delete wp_spaces_sync_enabled - we want to keep sync enabled
        wp_spaces_log_error('Sync completed successfully');
        return;
    }

    foreach ($attachments as $attachment) {
        $file_meta = get_post_meta($attachment->ID, '_wp_attached_file', true);
        if (!$file_meta) {
            wp_spaces_log_error('No file meta found for attachment ID: ' . $attachment->ID);
            continue;
        }

        // Log each file being processed
        wp_spaces_log_error('Processing file: ' . $file_meta);
        
        // Get the full file path
        $file_path = trailingslashit($wp_upload_dir['basedir']) . $file_meta;
        
        // Generate the correct storage path
        $storage_path = wp_spaces_get_storage_path($file_path);

        // Upload original file if it exists locally
        if (file_exists($file_path)) {
            wp_spaces_upload_file($s3, $bucket, $file_path, $storage_path);
            
            // Verify file exists in Spaces before deleting
            if (wp_spaces_check_file_exists_in_spaces($s3, $bucket, $storage_path)) {
                unlink($file_path);
                wp_spaces_log_error("Deleted original file after sync: " . $file_path);
            }
        }

        // Handle intermediate sizes
        $metadata = wp_get_attachment_metadata($attachment->ID);
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size) {
                if (isset($size['file'])) {
                    $size_file_path = trailingslashit(dirname($file_path)) . $size['file'];
                    $size_storage_path = wp_spaces_get_storage_path($size_file_path);

                    if (file_exists($size_file_path)) {
                        wp_spaces_upload_file($s3, $bucket, $size_file_path, $size_storage_path);
                        
                        // Verify file exists in Spaces before deleting
                        if (wp_spaces_check_file_exists_in_spaces($s3, $bucket, $size_storage_path)) {
                            unlink($size_file_path);
                            wp_spaces_log_error("Deleted resized file after sync: " . $size_file_path);
                        }
                    }
                }
            }
        }
    }

    // Update progress
    $current_progress = get_option('wp_spaces_sync_progress', 0);
    update_option('wp_spaces_sync_progress', $current_progress + count($attachments));

    // Update offset for next batch
    $new_offset = $offset + count($attachments);
    set_transient('wp_spaces_sync_offset', $new_offset, HOUR_IN_SECONDS);
}
