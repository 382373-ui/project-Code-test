<?php
require_once 'includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

$password = $_POST['password'] ?? '';

if ($password === 'AdminZSRB') {
    $_SESSION['admin_unlocked'] = true;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
