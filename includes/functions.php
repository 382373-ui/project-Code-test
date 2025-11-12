<?php
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
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
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

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
?>
