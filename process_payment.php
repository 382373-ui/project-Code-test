<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Ensure user is logged in
requireLogin();

$db = getDBConnection();
$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Collect data from the checkout form
    $jobId = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
    $amountPaid = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

    // 2. Security Check: Verify the user actually owns this job
    $stmt = $db->prepare("SELECT id, boost_amount FROM jobs WHERE id = ? AND poster_user_id = ?");
    $stmt->execute([$jobId, $userId]);
    $job = $stmt->fetch();

    if (!$job) {
        die("Error: Job not found or unauthorized.");
    }

    if ($amountPaid > 0) {
        // --- MOCK PAYMENT PROCESSING ---
        // In a real app, you would verify the credit card here.
        // If successful, we proceed to update the database.

        $newBoostTotal = $job['boost_amount'] + $amountPaid;

        try {
            // 3. Update the Job table
            // We set is_boosted = 1 and increase the boost_amount
            $update = $db->prepare("UPDATE jobs SET boost_amount = ?, is_boosted = 1 WHERE id = ?");
            $update->execute([$newBoostTotal, $jobId]);

            // 4. (Optional) Log the transaction in a payments table
            // If you have a 'payments' table, insert a record here.

            // 5. Success! Redirect back to boost.php with a success flag
            header("Location: boost.php?id=$jobId&success=1");
            exit;

        } catch (PDOException $e) {
            // Handle database errors
            die("Database Error: " . $e->getMessage());
        }
    } else {
        die("Invalid payment amount.");
    }
} else {
    // If someone tries to access this page directly without POSTing
    header("Location: profile.php");
    exit;
}