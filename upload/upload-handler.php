<?php
/**
 * Upload Handler
 * 
 * Manages the upload process of media files to DigitalOcean Spaces,
 * handling both initial file uploads and their associated metadata.
 * 
 * @package WordPress_Spaces
 * @subpackage Upload
 * @since 0.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files
require_once __DIR__ . '/../core/aws-client.php';
require_once __DIR__ . '/../core/functions.php';

/**
 * Handles the upload of new media files to DigitalOcean Spaces.
 * 
 * Intercepts WordPress media uploads and processes them for storage in
 * DigitalOcean Spaces. If successful, removes the local copy and updates
 * the file URL to point to the Spaces location.
 * 
 * @since 0.6
 * @param array $upload Upload file information
 * @return array Modified upload information
 */
function wp_spaces_handle_upload($upload) {
    if (isset($upload['error']) && !empty($upload['error'])) {
        return $upload;
    }

    $s3 = wp_spaces_get_s3_client();

    if (!$s3) {
        return $upload;
    }

    // Check if sync is enabled
    $sync_enabled = get_option('wp_spaces_sync_enabled', false);

    if (!$sync_enabled) {
        return $upload;
    }

    $options = get_option('wp_spaces_settings');
    $bucket = $options['space_name'];
    $file_path = $upload['file'];

    // Generate the correct storage path
    $storage_path = wp_spaces_get_storage_path($file_path);

    try {
        $result = $s3->putObject([
            'Bucket'      => $bucket,
            'Key'         => $storage_path,
            'SourceFile'  => $file_path,
            'ACL'         => 'public-read',
            'ContentType' => $upload['type'],
        ]);

        // Delete local file after successful upload
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Update the upload array with the new URL
        if (!empty($options['cdn_url'])) {
            $upload['url'] = rtrim($options['cdn_url'], '/') . '/' . $storage_path;
        } else {
            $upload['url'] = $result['ObjectURL'];
        }
        
        $upload['file'] = ''; // No local file

    } catch (AwsException $e) {
        wp_spaces_log_error('DigitalOcean Spaces Upload Error: ' . $e->getMessage());
        $upload['error'] = __('Error uploading file to DigitalOcean Spaces: ', 'wp-spaces') . $e->getMessage();
    }

    return $upload;
}

/**
 * Processes attachment metadata for uploaded media files.
 * 
 * Handles the upload of generated image sizes and their metadata to
 * DigitalOcean Spaces. This includes the original image and all
 * intermediate sizes created by WordPress.
 * 
 * @since 0.6
 * @param array $metadata Attachment metadata
 * @param int $attachment_id Attachment post ID
 * @return array Original metadata
 */
function wp_spaces_handle_attachment_metadata($metadata, $attachment_id) {
    $s3 = wp_spaces_get_s3_client();

    if (!$s3) {
        return $metadata;
    }

    // Check if sync is enabled
    $sync_enabled = get_option('wp_spaces_sync_enabled', false);

    if (!$sync_enabled) {
        return $metadata;
    }

    $options = get_option('wp_spaces_settings');
    $bucket = $options['space_name'];
    $wp_upload_dir = wp_upload_dir();

    // Handle the original image
    if (isset($metadata['file'])) {
        $file_path = trailingslashit($wp_upload_dir['basedir']) . $metadata['file'];
        // Adjust relative path
        $relative_path = 'wp-content/uploads/' . $metadata['file'];

        wp_spaces_upload_file($s3, $bucket, $file_path, $relative_path);
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // Handle intermediate sizes
    if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
        foreach ($metadata['sizes'] as $size) {
            if (isset($size['file'])) {
                $file_path = trailingslashit(dirname(trailingslashit($wp_upload_dir['basedir']) . $metadata['file'])) . $size['file'];
                // Adjust relative path
                $relative_path = 'wp-content/uploads/' . trailingslashit(dirname($metadata['file'])) . $size['file'];

                wp_spaces_upload_file($s3, $bucket, $file_path, $relative_path);
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
    }

    return $metadata;
}
