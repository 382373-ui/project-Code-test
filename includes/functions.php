<?php
// Sanitize user input
function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Format date and time
function formatDateTime($datetime) {
    return date('M d, Y g:i A', strtotime($datetime));
}

// Redirect helper
function redirect($url) {
    header("Location: $url");
    exit;
}

// Flash messages
function setFlashMessage($message, $type = 'success') {
    startSecureSession();
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function getFlashMessage() {
    startSecureSession();
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

// CSRF token helpers
function generateCSRFToken() {
    startSecureSession();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    startSecureSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// File upload helper
function uploadFile($file, $targetDir, $allowedTypes = [], $maxSize = 5242880) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        // Handle specific PHP upload errors
        switch ($file['error'] ?? 0) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['error' => 'File is too large. Maximum size is 5MB.'];
            case UPLOAD_ERR_PARTIAL:
                return ['error' => 'File was only partially uploaded.'];
            case UPLOAD_ERR_NO_FILE:
                return ['error' => 'No file uploaded.'];
            default:
                return ['error' => 'Upload failed with error code ' . ($file['error'] ?? 'unknown')];
        }
    }

    // Validate file type
    if (!empty($allowedTypes) && !in_array($file['type'], $allowedTypes)) {
        return ['error' => 'Invalid file type.'];
    }

    // Validate file size
    if ($file['size'] > $maxSize) {
        return ['error' => 'File is too large. Maximum size is 5MB.'];
    }

    // Ensure folder exists
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            return ['error' => 'Failed to create upload directory.'];
        }
    }

    // Build unique file name
    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = time() . "_" . bin2hex(random_bytes(5)) . "." . $fileExt;
    $uploadPath = rtrim($targetDir, '/') . '/' . $newName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['error' => 'Failed to save uploaded file. Check folder permissions.'];
    }

    return ['path' => $uploadPath];
}
?>
