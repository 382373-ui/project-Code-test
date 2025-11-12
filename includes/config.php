<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'jobbridge');
define('DB_USER', 'root');
define('DB_PASS', '');

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
