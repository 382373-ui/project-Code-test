<?php

function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M d, Y g:i A', strtotime($datetime));
}

function redirect($url) {
    header("Location: $url");
    exit;
}

/* ── Flash messages (uses session already started by auth.php) ── */
function setFlashMessage($message, $type = 'success') {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type']    = $type;
}

function getFlashMessage() {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type    = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/* ── CSRF ── */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/* ── File upload (4-arg signature used by confirm_job, edit-profile) ── */
function uploadFile($file, $targetDir, $allowedTypes = [], $maxSize = 5242880) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error'] ?? 0) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:  return ['error' => 'File is too large (max 5 MB).'];
            case UPLOAD_ERR_PARTIAL:    return ['error' => 'File was only partially uploaded.'];
            case UPLOAD_ERR_NO_FILE:    return ['error' => 'No file was uploaded.'];
            default:                    return ['error' => 'Upload error code ' . ($file['error'] ?? '?')];
        }
    }

    if (!empty($allowedTypes) && !in_array($file['type'], $allowedTypes)) {
        return ['error' => 'Invalid file type.'];
    }

    if ($file['size'] > $maxSize) {
        return ['error' => 'File is too large (max 5 MB).'];
    }

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            return ['error' => 'Could not create upload directory.'];
        }
    }

    $fileExt  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName  = time() . '_' . bin2hex(random_bytes(5)) . '.' . $fileExt;
    $destPath = rtrim($targetDir, '/') . '/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['error' => 'Failed to save file. Check folder permissions.'];
    }

    return ['path' => $destPath];
}
