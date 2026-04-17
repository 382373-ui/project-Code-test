<?php
session_start();
// This matches the key in your index.php JavaScript
$admin_key = 'AdminZSRB';

if (isset($_GET['key']) && $_GET['key'] === $admin_key) {
    $_SESSION['role'] = 'admin';
    header('Location: admin.php');
    exit;
} else {
    die("Invalid Admin Key.");
}