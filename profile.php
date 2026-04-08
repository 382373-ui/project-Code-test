<?php
ob_start(); 
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
ob_clean(); 

requireLogin();

$db = getDBConnection();
$userId = getCurrentUserId();
$message = '';
$error = '';

// --- HANDLE JOB ACTIONS (EDIT / SOFT DELETE / TOGGLE ACTIVE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_action'])) {
    $jobId = (int)$_POST['job_id'];

    $check = $db->prepare("SELECT poster_user_id FROM jobs WHERE id = ?");
    $check->execute([$jobId]);
    $job = $check->fetch();

    if (!$job || $job['poster_user_id'] != $userId) {
        $error = "Unauthorized action.";
    } else {
        try {
            switch ($_POST['job_action']) {

                case 'delete':
                    // SOFT DELETE: Mark as deleted and inactive instead of removing the row
                    $stmt = $db->prepare("UPDATE jobs SET deleted_at = NOW(), is_active = 0 WHERE id = ?");
                    $stmt->execute([$jobId]);
                    $message = "Job listing removed successfully.";
                    break;

                case 'toggle':
                    $stmt = $db->prepare("UPDATE jobs SET is_active = NOT is_active WHERE id = ? AND deleted_at IS NULL");
                    $stmt->execute([$jobId]);
                    $message = "Job status updated.";
                    break;

                case 'edit':
                    $stmt = $db->prepare("
                        UPDATE jobs 
                        SET title = ?, description = ?, pay = ?, pay_type = ?, category = ?, date_needed = ? 
                        WHERE id = ? AND deleted_at IS NULL
                    ");
                    $stmt->execute([
                        trim($_POST['title']),
                        trim($_POST['description']),
                        $_POST['pay'] !== '' ? $_POST['pay'] : null,
                        $_POST['pay_type'],
                        $_POST['category'],
                        $_POST['date_needed'] ?: null,
                        $jobId
                    ]);
                    $message = "Job updated successfully.";
                    break;
            }
        } catch (PDOException $e) {
            $error = "Database error.";
        }
    }
}

// --- HANDLE NEW JOB POSTING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_job') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
        $error = "Students are not authorized to post jobs.";
    } else {
        // ... (Keep your existing validation logic here)
        $title = trim($_POST['title']);
        $category = $_POST['category'];
        $description = trim($_POST['description']);
        $pay = !empty($_POST['pay']) ? $_POST['pay'] : null;
        $pay_type = $_POST['pay_type'] ?? 'full';
        $zip_code = trim($_POST['zip_code']);
        $location_details = trim($_POST['location_details']);
        $date_needed = !empty($_POST['date_needed']) ? $_POST['date_needed'] : null;

        if (!empty($title) && !empty($description)) {
            $sql = "INSERT INTO jobs (poster_user_id, title, description, category, pay, pay_type, zip_code, location_details, date_needed, date_posted, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)";
            $db->prepare($sql)->execute([$userId, $title, $description, $category, $pay, $pay_type, $zip_code, $location_details, $date_needed]);
            $message = "Job posted successfully!";
        }
    }
}

// --- FETCH DATA ---
$stmt = $db->prepare("SELECT u.*, p.* FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Only fetch jobs that haven't been soft-deleted
$jobsStmt = $db->prepare("SELECT * FROM jobs WHERE poster_user_id = ? AND deleted_at IS NULL ORDER BY date_posted DESC");
$jobsStmt->execute([$userId]);
$userJobs = $jobsStmt->fetchAll();

$profileImg = !empty($user['profile_img']) ? htmlspecialchars($user['profile_img']) : 'public/images/default-avatar.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Profile | JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-header { background: white; padding: 30px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .job-table-card { border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= $message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="profile-header row align-items-center">
            <div class="col-md-2 text-center">
                <img src="<?= $profileImg ?>" class="img-fluid rounded-circle mb-2" style="width: 120px; height: 120px; object-fit: cover;">
            </div>
            <div class="col-md-7">
                <h3><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                <p class="text-muted mb-1">@<?= htmlspecialchars($user['username']) ?> • <?= ucfirst($user['role']) ?></p>
                <p class="small mb-0 text-secondary"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
            </div>
            <div class="col-md-3 text-md-end mt-3 mt-md-0">
                <a href="edit-profile.php" class="btn btn-sm btn-outline-primary">Edit Profile</a>
                <?php if ($user['role'] !== 'student'): ?>
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#postJobModal">+ Post Job</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($user['role'] !== 'student'): ?>
        <div class="card job-table-card border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Your Active Listings</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Pay</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userJobs as $job): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($job['title']) ?></strong><br>
                                <small class="text-muted"><?= ucfirst($job['category']) ?></small>
                            </td>
                            <td>
                                <?php if ($job['pay']): ?>
                                    $<?= number_format($job['pay'], 2) ?><?= $job['pay_type'] == 'hourly' ? '/hr' : '' ?>
                                <?php else: ?> Flexible <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= $job['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $job['is_active'] ? 'Active' : 'Closed' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                    <input type="hidden" name="job_action" value="toggle">
                                    <button class="btn btn-sm btn-light border"><?= $job['is_active'] ? 'Close' : 'Open' ?></button>
                                </form>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editJobModal<?= $job['id'] ?>">Edit</button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Remove this listing?');">
                                    <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                    <input type="hidden" name="job_action" value="delete">
                                    <button class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>