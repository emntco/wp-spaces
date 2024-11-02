<?php
/**
 * Helper Functions
 * 
 * Provides utility functions for file path manipulation, storage validation,
 * and DigitalOcean Spaces operations used throughout the plugin.
 * 
 * @package WordPress_Spaces
 * @subpackage Utils
 * @since 0.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include the logger for error logging within helper functions
require_once __DIR__ . '/logger.php';

/**
 * Checks if a file exists in DigitalOcean Spaces.
 * 
 * Performs a HEAD request to verify the existence of a file in the
 * specified Spaces bucket without downloading the file contents.
 * 
 * @since 0.6
 * @param S3Client $s3 The initialized S3 client
 * @param string $bucket The name of the Spaces bucket
 * @param string $key The file key/path in the bucket
 * @return bool True if file exists, false otherwise
 */
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

/**
 * Gets the relative path for a file based on plugin settings.
 * 
 * Calculates the correct relative path for a file, taking into account
 * the subfolder settings and WordPress upload directory structure.
 * 
 * @since 0.6
 * @param string $file_path The full file system path
 * @return string The calculated relative path
 */
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

/**
 * Validates access to a specified subfolder in Spaces.
 * 
 * Attempts to list objects in the specified subfolder to verify
 * that the bucket and credentials have proper access permissions.
 * 
 * @since 0.6
 * @param S3Client $s3 The initialized S3 client
 * @param string $bucket The name of the Spaces bucket
 * @param string $subfolder The subfolder path to validate
 * @return bool True if access is valid, false otherwise
 */
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

/**
 * Generates the complete storage path for a file.
 * 
 * Creates the full storage path for a file in DigitalOcean Spaces,
 * incorporating subfolder settings and maintaining WordPress directory structure.
 * 
 * @since 0.6
 * @param string $file_path The full file system path
 * @return string The complete storage path for Spaces
 */
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
