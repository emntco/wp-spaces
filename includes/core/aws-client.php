<?php
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Initialize S3 Client
function wp_spaces_get_s3_client() {
    $options = get_option('wp_spaces_settings');

    $access_key = defined('WP_SPACES_ACCESS_KEY') ? WP_SPACES_ACCESS_KEY : (isset($options['access_key']) ? $options['access_key'] : '');
    $secret_key = defined('WP_SPACES_SECRET_KEY') ? WP_SPACES_SECRET_KEY : (isset($options['secret_key']) ? $options['secret_key'] : '');

    if (empty($access_key) || empty($secret_key) || empty($options['region']) || empty($options['space_name'])) {
        return null; // Credentials are missing
    }

    $s3 = new S3Client([
        'version'     => 'latest',
        'region'      => $options['region'],
        'endpoint'    => 'https://' . $options['region'] . '.digitaloceanspaces.com',
        'credentials' => [
            'key'    => $access_key,
            'secret' => $secret_key,
        ],
        'bucket_endpoint' => false,
        'use_path_style_endpoint' => false,
    ]);

    return $s3;
}
