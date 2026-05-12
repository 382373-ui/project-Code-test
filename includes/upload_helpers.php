<?php
require_once 'config.php';

/**
 * Legacy upload helper — wraps the richer uploadFile() in functions.php.
 * Guard prevents "Cannot redeclare" when both files are loaded.
 */
if (!function_exists('uploadFile')) {
    function uploadFile($file, $subfolder = '') {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'No file uploaded or upload error.'];
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'error' => 'File size exceeds 5 MB limit.'];
        }

        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, ALLOWED_FILE_TYPES)) {
            return ['success' => false, 'error' => 'File type not allowed.'];
        }

        $uploadPath = rtrim(UPLOAD_DIR . $subfolder, '/');
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $newFileName = uniqid('', true) . '.' . $fileExt;
        $destination = $uploadPath . '/' . $newFileName;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => true, 'path' => ltrim($subfolder, '/') . '/' . $newFileName];
        }

        return ['success' => false, 'error' => 'Failed to move uploaded file.'];
    }
}

function deleteFile($filepath) {
    $fullPath = UPLOAD_DIR . ltrim($filepath, '/');
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}
