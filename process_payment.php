<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$db     = getDBConnection();
$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$jobId      = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
$amountPaid = isset($_POST['amount']) ? round((float)$_POST['amount'], 2) : 0;

// Verify ownership
$stmt = $db->prepare("SELECT id, title, boost_amount FROM jobs WHERE id = ? AND poster_user_id = ?");
$stmt->execute([$jobId, $userId]);
$job = $stmt->fetch();

if (!$job) {
    die("Error: Job not found or you are not authorised to boost it.");
}

if ($amountPaid <= 0) {
    die("Invalid payment amount.");
}

// Determine boost duration label
$duration = 'custom';
$days     = 1;
if ($amountPaid == 2.00)  { $duration = '1day';   $days = 1;  }
if ($amountPaid == 5.00)  { $duration = '1week';  $days = 7;  }
if ($amountPaid == 15.00) { $duration = '1month'; $days = 30; }

$newBoostTotal = $job['boost_amount'] + $amountPaid;
$expiry        = date('Y-m-d H:i:s', strtotime("+{$days} days"));

try {
    // 1. Update job boost fields
    $db->prepare("UPDATE jobs SET boost_amount = ?, is_boosted = 1 WHERE id = ?")
       ->execute([$newBoostTotal, $jobId]);

    // 2. Record in job_boosts table
    $db->prepare("INSERT INTO job_boosts (job_id, user_id, amount, duration, expiry_date, created_at)
                  VALUES (?, ?, ?, ?, ?, NOW())")
       ->execute([$jobId, $userId, $amountPaid, $duration, $expiry]);

    // 3. Log to monetization_log
    $db->prepare("INSERT INTO monetization_log (user_id, type, amount, reference_id, description, created_at)
                  VALUES (?, 'boost', ?, ?, ?, NOW())")
       ->execute([$userId, $amountPaid, $jobId, 'Boost: ' . $job['title'] . ' (' . $duration . ')']);

    header("Location: boost.php?id={$jobId}&success=1");
    exit;

} catch (PDOException $e) {
    die("Database error while processing payment: " . $e->getMessage());
}
