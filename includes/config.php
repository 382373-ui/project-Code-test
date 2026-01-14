<?php
// includes/config.php

/**
 * DATABASE CONFIGURATION
 * 
 * Replit provides a built-in PostgreSQL database.
 * If you are using an external MySQL database, ensure:
 * 1. The external database allows connections from Replit's IP addresses.
 * 2. You have configured the correct hostname, username, and password.
 */

define('DB_HOST', getenv('DB_HOST') ?: '89.117.139.204');
define('DB_NAME', getenv('DB_NAME') ?: 'u455644781_jobbridge');
define('DB_USER', getenv('DB_USER') ?: 'u455644781_jobbridge');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('SITE_NAME', 'JobBridge');
define('SITE_URL', 'http://localhost');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png']);

define('MIN_PASSWORD_LENGTH', 8);
define('SESSION_TIMEOUT', 3600);

ini_set('display_errors', 1);
error_reporting(E_ALL);
?>
