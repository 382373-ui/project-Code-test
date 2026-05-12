<?php

/* ── Database ── */
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'jobbridge');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

/* ── File Uploads ── */
define('UPLOAD_DIR',         __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE',      5 * 1024 * 1024);           // 5 MB in bytes
define('ALLOWED_FILE_TYPES', ['jpg','jpeg','png','gif','pdf','doc','docx']);
