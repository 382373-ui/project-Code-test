<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// 🔒 Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Please use the admin shortcut on the home page.");
}

$pdo = getDBConnection();

try {
    // 📊 DASHBOARD STATS
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalJobs = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
    $totalMessages = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
    $totalApplications = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();

    // 👤 USERS LIST
    $stmtUsers = $pdo->query("
        SELECT id, username, email, created_at 
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // 📝 RECENT JOBS
    $stmtJobs = $pdo->query("
        SELECT id, title, category, location_details, date_posted 
        FROM jobs 
        ORDER BY date_posted DESC 
        LIMIT 10
    ");
    $recentJobs = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // This will help us catch if a column name is slightly different
    die("Database error: " . $e->getMessage());
}
?>