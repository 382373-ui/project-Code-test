<?php
require_once 'config.php';

function uploadFile($file, $subfolder = '') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error.'];
    }

    $fileSize = $file['size'];
    if ($fileSize > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File size exceeds maximum allowed size.'];
    }

    $fileName = $file['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, ALLOWED_FILE_TYPES)) {
        return ['success' => false, 'error' => 'File type not allowed.'];
    }

    $newFileName = uniqid('', true) . '.' . $fileExt;
    $uploadPath = UPLOAD_DIR . $subfolder;
    
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }

    $destination = $uploadPath . '/' . $newFileName;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $newFileName, 'path' => $subfolder . '/' . $newFileName];
    }
    
    return ['success' => false, 'error' => 'Failed to move uploaded file.'];
}

function deleteFile($filepath) {
    $fullPath = UPLOAD_DIR . $filepath;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}
?>
