<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files
require_once __DIR__ . '/../upload/upload-handler.php';
require_once __DIR__ . '/../url/url-rewrite.php';
require_once __DIR__ . '/../sync/sync.php';
require_once __DIR__ . '/../sync/reverse-sync.php';

// Handle File Uploads (New Media)
add_filter('wp_handle_upload', 'wp_spaces_handle_upload');

// Handle Attachment Metadata (New Media)
add_filter('wp_generate_attachment_metadata', 'wp_spaces_handle_attachment_metadata', 10, 2);

// Filter Attachment URLs
add_filter('wp_get_attachment_url', 'wp_spaces_get_attachment_url', 10, 2);

// Hook the sync function to the scheduled event
add_action('wp_spaces_sync_event', 'wp_spaces_process_sync_batch');

// Hook the reverse sync function to the scheduled event
add_action('wp_spaces_reverse_sync_event', 'wp_spaces_process_reverse_sync_batch');
