<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include core files
require_once __DIR__ . '/aws-client.php';
require_once __DIR__ . '/hooks.php';
require_once __DIR__ . '/functions.php';

// Include synchronization cron functions
require_once __DIR__ . '/../sync/cron.php';
