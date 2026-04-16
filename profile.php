<?php
// 1. Start buffering to catch the "auth loaded" message
ob_start(); 

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// 2. Clear the buffer to remove the "auth loaded" text
ob_clean(); 

requireLogin();

$db = getDBConnection();
$userId = getCurrentUserId();
$message = '';
$error = '';

// --- HANDLE JOB ACTIONS (EDIT / DELETE / TOGGLE ACTIVE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_action'])) {
    $jobId = (int)$_POST['job_id'];

    // Make sure the job belongs to the logged-in user
    $check = $db->prepare("SELECT poster_user_id FROM jobs WHERE id = ?");
    $check->execute([$jobId]);
    $job = $check->fetch();

    if (!$job || $job['poster_user_id'] != $userId) {
        $error = "Unauthorized action.";
    } else {
        try {
            switch ($_POST['job_action']) {
                case 'delete':
                    $stmt = $db->prepare("UPDATE jobs SET is_deleted = 1, is_active = 0 WHERE id = ?");
                    $stmt->execute([$jobId]);
                    $message = "Job removed successfully.";
                    break;

                case 'toggle':
                    $stmt = $db->prepare("UPDATE jobs SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$jobId]);
                    $message = "Job status updated.";
                    break;

                case 'edit':
                    $stmt = $db->prepare("
                        UPDATE jobs
                        SET title = ?, 
                            description = ?, 
                            pay = ?, 
                            pay_type = ?, 
                            category = ?, 
                            date_needed = ?,
                            zip_code = ?,
                            location_details = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        trim($_POST['title'] ?? ''),
                        trim($_POST['description'] ?? ''),
                        (isset($_POST['pay']) && $_POST['pay'] !== '') ? $_POST['pay'] : null,
                        $_POST['pay_type'] ?? 'full',
                        $_POST['category'] ?? 'odd',
                        !empty($_POST['date_needed']) ? $_POST['date_needed'] : null,
                        trim($_POST['zip_code'] ?? ''),
                        trim($_POST['location_details'] ?? ''),
                        $jobId
                    ]);
                    $message = "Job updated successfully.";
                    break;
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// --- HANDLE JOB POST SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_job') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
        $error = "Students are not authorized to post jobs.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $pay = !empty($_POST['pay']) ? $_POST['pay'] : null;
        $pay_type = $_POST['pay_type'] ?? 'full'; 
        $zip_code = trim($_POST['zip_code'] ?? '');
        $location_details = trim($_POST['location_details'] ?? '');
        $date_needed = !empty($_POST['date_needed']) ? $_POST['date_needed'] : null;

        if (empty($title) || empty($description) || empty($category)) {
            $error = "Title, Category, and Description are required.";
        } elseif (!empty($zip_code) && !preg_match('/^[0-9]{5}$/', $zip_code)) {
            $error = "Please enter a valid 5-digit zip code.";
        } else {
            try {
                $sql = "INSERT INTO jobs (poster_user_id, title, description, category, pay, pay_type, zip_code, location_details, date_needed, date_posted, is_active, verified_flag, is_deleted) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1, 0, 0)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$userId, $title, $description, $category, $pay, $pay_type, $zip_code, $location_details, $date_needed]);
                $message = "Job posted successfully!";
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// --- FETCH USER PROFILE DATA ---
$stmt = $db->prepare("SELECT u.username, u.email, u.role, u.created_at, p.first_name, p.last_name, p.profile_img, p.grade_year, p.skills, p.availability, p.location_radius, p.bio FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$profileImg = !empty($user['profile_img']) ? htmlspecialchars($user['profile_img']) : 'public/images/default-avatar.png';

// --- FETCH JOBS (Updated to include all fields) ---
$jobsStmt = $db->prepare("
    SELECT id, title, description, category, pay, pay_type, zip_code, location_details, date_posted, date_needed, is_active, boost_amount
    FROM jobs
    WHERE poster_user_id = ? AND is_deleted = 0
    ORDER BY boost_amount DESC, date_posted DESC
");
$jobsStmt->execute([$userId]);
$userJobs = $jobsStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= $message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="<?= $profileImg ?>" alt="Profile" class="profile-img mb-3" style="max-width:150px; border-radius:50%;">
                        <h4><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></h4>
                        <p class="text-muted">@<?= htmlspecialchars($user['username'] ?? 'User') ?></p>
                        <p><span class="badge bg-primary"><?= ucfirst($user['role'] ?? 'Member') ?></span></p>
                        <div class="d-grid gap-2">
                            <a href="edit-profile.php" class="btn btn-outline-primary btn-sm">Edit Profile</a>
                            <?php if (($user['role'] ?? '') !== 'student'): ?>
                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#postJobModal">+ Post a New Job</button>
                            <?php endif; ?>
                            <a href="change-password.php" class="btn btn-outline-secondary btn-sm">Change Password</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><h5>Profile Information</h5></div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6"><strong>Email:</strong><br><?= htmlspecialchars($user['email'] ?? '') ?></div>
                            <div class="col-md-6"><strong>Member Since:</strong><br><?= isset($user['created_at']) ? htmlspecialchars(date("F j, Y", strtotime($user['created_at']))) : 'N/A' ?></div>
                        </div>
                        <?php if (($user['role'] ?? '') === 'student'): ?>
                            <div class="row mb-3">
                                <div class="col-md-6"><strong>Grade/Year:</strong><br><?= htmlspecialchars($user['grade_year'] ?? 'Not specified') ?></div>
                                <div class="col-md-6"><strong>Availability:</strong><br><?= htmlspecialchars($user['availability'] ?? 'Not specified') ?></div>
                            </div>
                            <div class="mb-3"><strong>Skills:</strong><br><?= nl2br(htmlspecialchars($user['skills'] ?? 'Not specified')) ?></div>
                        <?php endif; ?>
                        <div class="mb-3"><strong>Bio:</strong><br><?= nl2br(htmlspecialchars($user['bio'] ?? 'No bio added yet.')) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (($user['role'] ?? '') !== 'student'): ?>
        <div class="card mt-4">
            <div class="card-header"><h5>Your Posted Jobs</h5></div>
            <div class="card-body">
                <?php if (empty($userJobs)): ?>
                    <p class="text-muted">You have not posted any jobs yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Pay</th>
                                    <th>Date Needed</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userJobs as $job): ?>
                                <tr>
                                    <td><?= htmlspecialchars($job['title']) ?></td>
                                    <td>
                                        <?php if ($job['pay'] !== null): ?>
                                            $<?= number_format($job['pay'], 2) ?><?= $job['pay_type'] === 'hourly' ? '/hr' : '' ?>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td><?= $job['date_needed'] ? htmlspecialchars(date("M j, Y", strtotime($job['date_needed']))) : 'Flexible' ?></td>
                                    <td>
                                        <span class="badge <?= $job['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $job['is_active'] ? 'Active' : 'Closed' ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                            <input type="hidden" name="job_action" value="toggle">
                                            <button class="btn btn-sm btn-warning"><?= $job['is_active'] ? 'Close' : 'Reopen' ?></button>
                                        </form>

                                        <a href="boost.php?id=<?= $job['id'] ?>" class="btn btn-sm <?= $job['boost_amount'] > 0 ? 'btn-success' : 'btn-outline-success' ?>">
                                            ⚡ <?= $job['boost_amount'] > 0 ? 'Boosted ($' . number_format($job['boost_amount'], 2) . ')' : 'Boost' ?>
                                        </a>

                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editJobModal<?= $job['id'] ?>">Edit</button>

                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this job?');">
                                            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                            <input type="hidden" name="job_action" value="delete">
                                            <button class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (($user['role'] ?? '') !== 'student') { 
        foreach ($userJobs as $job) { ?>
            <div class="modal fade" id="editJobModal<?= $job['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <form method="POST" class="modal-content">
                        <input type="hidden" name="job_action" value="edit">
                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Job</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3"><label class="form-label">Title</label><input name="title" class="form-control" value="<?= htmlspecialchars($job['title']) ?>" required></div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="company" <?= $job['category'] == 'company' ? 'selected' : '' ?>>Company</option>
                                    <option value="odd" <?= $job['category'] == 'odd' ? 'selected' : '' ?>>Odd Job</option>
                                    <option value="volunteer" <?= $job['category'] == 'volunteer' ? 'selected' : '' ?>>Volunteer</option>
                                    <option value="internship" <?= $job['category'] == 'internship' ? 'selected' : '' ?>>Internship</option>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6"><label class="form-label">Pay</label><input type="number" step="0.01" name="pay" class="form-control" value="<?= htmlspecialchars((string)($job['pay'] ?? '')) ?>"></div>
                                <div class="col-md-6">
                                    <label class="form-label">Pay Type</label>
                                    <select name="pay_type" class="form-select">
                                        <option value="full" <?= $job['pay_type'] == 'full' ? 'selected' : '' ?>>Full</option>
                                        <option value="hourly" <?= $job['pay_type'] == 'hourly' ? 'selected' : '' ?>>Hourly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3 mt-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($job['description']) ?></textarea></div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Zip Code</label>
                                    <input type="text" name="zip_code" class="form-control" pattern="\d{5}" value="<?= htmlspecialchars($job['zip_code'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date Needed</label>
                                    <input type="date" name="date_needed" class="form-control" value="<?= htmlspecialchars((string)($job['date_needed'] ?? '')) ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Location Details (Optional)</label>
                                <input type="text" name="location_details" class="form-control" value="<?= htmlspecialchars($job['location_details'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
    <?php } } ?>

    <div class="modal fade" id="postJobModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Post a New Job</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="post_job">
                    <div class="mb-3"><label class="form-label">Job Title *</label><input type="text" name="title" class="form-control" required></div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Category *</label>
                            <select name="category" class="form-select" required>
                                <option value="" disabled selected>Select Category</option>
                                <option value="company">Company</option>
                                <option value="odd">Odd Job</option>
                                <option value="volunteer">Volunteer</option>
                                <option value="internship">Internship</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Payment Type *</label>
                            <select name="pay_type" id="payTypeSelect" class="form-select" required>
                                <option value="full">In Full</option>
                                <option value="hourly">Hourly</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3"><label class="form-label">Pay Amount ($)</label><input type="number" step="0.01" name="pay" id="payInput" class="form-control"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Description *</label><textarea name="description" class="form-control" rows="4" required></textarea></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Zip Code</label><input type="text" name="zip_code" class="form-control" pattern="\d{5}"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Date Needed</label><input type="date" name="date_needed" class="form-control"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location Details (Optional)</label>
                        <input type="text" name="location_details" class="form-control" placeholder="e.g. Near the library">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Post Job</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>