<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Filter Attachment URLs
function wp_spaces_get_attachment_url($url, $post_id) {
    $options = get_option('wp_spaces_settings');
    $sync_enabled = get_option('wp_spaces_sync_enabled', false);
    $reverse_sync_in_progress = get_transient('wp_spaces_reverse_sync_in_progress');

    // Get the original WordPress URL
    $wp_upload_dir = wp_upload_dir();
    $file_meta = get_post_meta($post_id, '_wp_attached_file', true);
    $local_file_path = $file_meta ? trailingslashit($wp_upload_dir['basedir']) . $file_meta : '';
    
    if ($sync_enabled || ($reverse_sync_in_progress && !file_exists($local_file_path))) {
        if ($file_meta) {
            // Build the storage path
            if (!empty($options['use_subfolder']) && !empty($options['subfolder_name'])) {
                $storage_path = trailingslashit($options['subfolder_name']) . 'wp-content/uploads/' . $file_meta;
            } else {
                $storage_path = 'wp-content/uploads/' . $file_meta;
            }

            // Determine the base URL
            if (!empty($options['cdn_url'])) {
                $base_url = rtrim($options['cdn_url'], '/') . '/';
            } else {
                $base_url = 'https://' . $options['space_name'] . '.' . $options['region'] . '.digitaloceanspaces.com/';
            }

            return $base_url . $storage_path;
        }
    }

    return $url;
}
