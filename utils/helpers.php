<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include the logger for error logging within helper functions
require_once __DIR__ . '/logger.php';

// Helper Function to Check if a File Exists in Spaces
function wp_spaces_check_file_exists_in_spaces($s3, $bucket, $key) {
    try {
        $s3->headObject([
            'Bucket' => $bucket,
            'Key'    => $key,
        ]);
        return true;
    } catch (AwsException $e) {
        if ($e->getStatusCode() !== 404) {
            wp_spaces_log_error('Error checking file in Spaces: ' . $e->getMessage());
        }
        return false;
    }
}

// Helper Function to Get the Relative Path for a File
function wp_spaces_get_relative_path($file_path) {
    $options = get_option('wp_spaces_settings');
    $wp_upload_dir = wp_upload_dir();
    
    // Get the path relative to the uploads directory
    $relative_to_uploads = str_replace(trailingslashit($wp_upload_dir['basedir']), '', $file_path);
    
    if (!empty($options['use_subfolder']) && !empty($options['subfolder_name'])) {
        // Use subfolder mode
        return trailingslashit($options['subfolder_name']) . $relative_to_uploads;
    } else {
        // Use traditional wp-content/uploads structure
        return 'wp-content/uploads/' . $relative_to_uploads;
    }
}

// Helper Function to Validate Subfolder Access
function wp_spaces_validate_subfolder_access($s3, $bucket, $subfolder) {
    try {
        // Try to list objects in the subfolder to verify access
        $result = $s3->listObjects([
            'Bucket' => $bucket,
            'Prefix' => $subfolder . '/',
            'MaxKeys' => 1
        ]);
        return true;
    } catch (AwsException $e) {
        wp_spaces_log_error('Subfolder access validation failed: ' . $e->getMessage());
        return false;
    }
}

// Helper Function to Get the Storage Path for a File
function wp_spaces_get_storage_path($file_path) {
    $options = get_option('wp_spaces_settings');
    $wp_upload_dir = wp_upload_dir();
    
    // Get the path relative to the uploads directory
    $uploads_relative_path = str_replace(trailingslashit($wp_upload_dir['basedir']), '', $file_path);
    
    if (!empty($options['use_subfolder']) && !empty($options['subfolder_name'])) {
        // Prepend the client subfolder to the wp-content/uploads path
        return trailingslashit($options['subfolder_name']) . 'wp-content/uploads/' . $uploads_relative_path;
    } else {
        // Traditional wp-content/uploads structure
        return 'wp-content/uploads/' . $uploads_relative_path;
    }
}
