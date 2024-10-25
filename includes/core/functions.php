<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary utilities
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/helpers.php';

// Activation hook function
function wp_spaces_activate() {
    // Initial setup tasks (if any)
}

// Deactivation hook function
function wp_spaces_deactivate() {
    // Clear scheduled events on deactivation
    wp_clear_scheduled_hook('wp_spaces_sync_event');
    wp_clear_scheduled_hook('wp_spaces_reverse_sync_event');
}

// Upload a file to DigitalOcean Spaces
function wp_spaces_upload_file($s3, $bucket, $file_path, $relative_path) {
    try {
        $s3->putObject([
            'Bucket'      => $bucket,
            'Key'         => $relative_path,
            'SourceFile'  => $file_path,
            'ACL'         => 'public-read',
            'ContentType' => wp_check_filetype($file_path)['type'],
        ]);
    } catch (AwsException $e) {
        wp_spaces_log_error('DigitalOcean Spaces Upload Error: ' . $e->getMessage());
    }
}
