<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$db = getDBConnection();
$userId = getCurrentUserId();
$message = '';
$error = '';

// --- 1. HANDLE JOB POST SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_job') {
    
    // SECURITY CHECK: Ensure user is NOT a student
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
        $error = "Students are not authorized to post jobs.";
    } else {
        // Sanitize inputs
        $title = trim($_POST['title']);
        $category = $_POST['category'];
        $description = trim($_POST['description']);
        $pay = !empty($_POST['pay']) ? $_POST['pay'] : null;
        $zip_code = trim($_POST['zip_code']);
        $location_details = trim($_POST['location_details']);
        $date_needed = !empty($_POST['date_needed']) ? $_POST['date_needed'] : null;

        // Basic Validation
        if (empty($title) || empty($description) || empty($category)) {
            $error = "Title, Category, and Description are required.";
        } else {
            try {
                // *** FIX: Changed 'created_at' to 'date_posted' to match your DB screenshot ***
                $sql = "INSERT INTO jobs (poster_user_id, title, description, category, pay, zip_code, location_details, date_needed, date_posted, is_active, verified_flag) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1, 0)";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $userId,
                    $title,
                    $description,
                    $category,
                    $pay,
                    $zip_code,
                    $location_details,
                    $date_needed
                ]);

                $message = "Job posted successfully!";
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// --- FETCH USER PROFILE DATA ---
$stmt = $db->prepare("
    SELECT u.username, u.email, u.role, u.created_at,
           p.first_name, p.last_name, p.profile_img, p.grade_year, 
           p.skills, p.availability, p.location_radius, p.bio
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Determine correct image path
$profileImg = !empty($user['profile_img']) 
              ? htmlspecialchars($user['profile_img']) 
              : 'public/images/default-avatar.png';
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
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="<?= $profileImg ?>" alt="Profile" class="profile-img mb-3" style="max-width:150px; border-radius:50%;">

                        <h4><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h4>
                        <p class="text-muted">@<?= htmlspecialchars($user['username']) ?></p>
                        <p><span class="badge bg-primary"><?= ucfirst($user['role']) ?></span></p>
                        
                        <div class="d-grid gap-2">
                            <a href="edit-profile.php" class="btn btn-outline-primary btn-sm">Edit Profile</a>
                            
                            <?php if ($user['role'] !== 'student'): ?>
                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#postJobModal">
                                    + Post a New Job
                                </button>
                            <?php endif; ?>
                            
                            <a href="change-password.php" class="btn btn-outline-secondary btn-sm">Change Password</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Email:</strong><br>
                                <?= htmlspecialchars($user['email']) ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Member Since:</strong><br>
                                <?= htmlspecialchars(date("F j, Y", strtotime($user['created_at']))) ?>
                            </div>
                        </div>

                        <?php if ($user['role'] === 'student'): ?>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Grade/Year:</strong><br>
                                    <?= htmlspecialchars($user['grade_year'] ?? 'Not specified') ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Availability:</strong><br>
                                    <?= htmlspecialchars($user['availability'] ?? 'Not specified') ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <strong>Skills:</strong><br>
                                <?= nl2br(htmlspecialchars($user['skills'] ?? 'Not specified')) ?>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <strong>Bio:</strong><br>
                            <?= nl2br(htmlspecialchars($user['bio'] ?? 'No bio added yet.')) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($user['role'] !== 'student'): ?>
    <div class="modal fade" id="postJobModal" tabindex="-1" aria-labelledby="postJobModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="postJobModalLabel">Post a New Job</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="post_job">
                        
                        <div class="mb-3">
                            <label class="form-label">Job Title *</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Lawn Mowing, Math Tutor">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category *</label>
                                <select name="category" class="form-select" required>
                                    <option value="" disabled selected>Select Category</option>
                                    <option value="company">Company</option>
                                    <option value="odd">Odd Job</option>
                                    <option value="volunteer">Volunteer</option>
                                    <option value="internship">Internship</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pay Amount ($)</label>
                                <input type="number" step="0.01" name="pay" class="form-control" placeholder="0.00">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea name="description" class="form-control" rows="4" required placeholder="Describe the job duties..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Zip Code</label>
                                <input type="text" name="zip_code" class="form-control" maxlength="10">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date Needed</label>
                                <input type="date" name="date_needed" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Location Details</label>
                                <input type="text" name="location_details" class="form-control" placeholder="e.g. Main Street">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Post Job</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/main.js"></script>
</body>
</html>