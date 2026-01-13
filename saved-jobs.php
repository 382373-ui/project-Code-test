<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$pdo = getDBConnection();
// Check if job is active
$stmt = $pdo->prepare(
    "SELECT is_active FROM jobs WHERE id = ?"
);
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job || $job['is_active'] != 1) {
    echo json_encode(['saved' => false, 'error' => 'inactive']);
    exit;
}

/*
 Fetch ONLY active saved jobs
 Inactive jobs are automatically excluded
*/
$stmt = $pdo->prepare(
    "SELECT j.id, j.title, j.description, j.category, j.pay,
            j.location_details, j.zip_code, j.date_posted
     FROM saved_jobs sj
     JOIN jobs j ON sj.job_id = j.id
     WHERE sj.user_id = ?
       AND j.is_active = 1
     ORDER BY sj.created_at DESC"
);
$stmt->execute([$_SESSION['user_id']]);
$savedJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Saved Jobs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background-color: #f4f4f4;
        }
        .job-listing {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }
        .job-listing h3 {
            color: #007bff;
        }
        .remove-btn {
            cursor: pointer;
        }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">

    <h2 class="mb-4">Saved Jobs</h2>

    <?php if (empty($savedJobs)): ?>
        <div class="alert alert-info">
            You have no saved jobs.
        </div>
    <?php endif; ?>

    <?php foreach ($savedJobs as $job): ?>
        <div class="job-listing">
            <div class="d-flex justify-content-between align-items-start">
                <h3><?= htmlspecialchars($job['title']) ?></h3>

                <i
                    class="b
