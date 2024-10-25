<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Error Logging Function
function wp_spaces_log_error($message) {
    $upload_dir = wp_upload_dir();
    $log_file = trailingslashit($upload_dir['basedir']) . 'wp-spaces-error.log';

    $date = date('Y-m-d H:i:s');
    $log_message = "[$date] $message" . PHP_EOL;

    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}
