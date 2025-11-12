<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$db = getDBConnection();
$userId = getCurrentUserId();

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
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="<?= $user['profile_img'] ?? 'public/images/default-avatar.png' ?>" 
                             alt="Profile" class="profile-img mb-3">
                        <h4><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h4>
                        <p class="text-muted">@<?= htmlspecialchars($user['username']) ?></p>
                        <p><span class="badge bg-primary"><?= ucfirst($user['role']) ?></span></p>
                        <a href="edit-profile.php" class="btn btn-outline-primary btn-sm">Edit Profile</a>
                        <a href="change-password.php" class="btn btn-outline-secondary btn-sm">Change Password</a>
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
                                <?= formatDate($user['created_at']) ?>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/main.js"></script>
</body>
</html>
