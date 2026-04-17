<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$db = getDBConnection();

$currentUserId = getCurrentUserId();

/*
    If user=ID is passed → view that profile
    Otherwise → show your own resume
*/
$requestedUserId = isset($_GET['user']) ? (int)$_GET['user'] : $currentUserId;

/*
    SECURITY RULE:
    Only allow viewing others, but ONLY allow editing your own
*/
$isOwnProfile = ($requestedUserId === $currentUserId);

// Fetch user + profile
$stmt = $db->prepare("
    SELECT u.id, u.username, u.email, u.created_at,
           p.first_name, p.last_name, p.profile_img,
           p.skills, p.bio, p.availability, p.grade_year
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$requestedUserId]);
$user = $stmt->fetch();

if (!$user) {
    die("Resume not found.");
}

$profileImg = !empty($user['profile_img'])
    ? htmlspecialchars($user['profile_img'])
    : 'public/images/default-avatar.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resume Portfolio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container mt-5">

    <!-- PROFILE HEADER -->
    <div class="text-center mb-5">
        <img src="<?= $profileImg ?>" class="rounded-circle mb-3" style="width:140px;height:140px;object-fit:cover;">

        <h2>
            <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
        </h2>

        <p class="text-muted">@<?= htmlspecialchars($user['username']) ?></p>

        <p><?= nl2br(htmlspecialchars($user['bio'] ?? 'No bio available')) ?></p>

        <!-- EDIT BUTTON (ONLY OWN PROFILE) -->
        <?php if ($isOwnProfile): ?>
            <a href="edit-profile.php" class="btn btn-primary btn-sm mt-2">Edit Resume</a>
        <?php endif; ?>
    </div>

    <!-- INFO -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header"><strong>Contact</strong></div>
                <div class="card-body">
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                    <p><strong>Member Since:</strong>
                        <?= date("F Y", strtotime($user['created_at'])) ?>
                    </p>
                    <p><strong>Availability:</strong>
                        <?= htmlspecialchars($user['availability'] ?? 'Not set') ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header"><strong>Education</strong></div>
                <div class="card-body">
                    <p><strong>Year:</strong>
                        <?= htmlspecialchars($user['grade_year'] ?? 'Not set') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- SKILLS -->
    <div class="card mb-4">
        <div class="card-header"><strong>Skills</strong></div>
        <div class="card-body">
            <?php if (!empty($user['skills'])): ?>
                <?= nl2br(htmlspecialchars($user['skills'])) ?>
            <?php else: ?>
                <span class="text-muted">No skills added yet</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- FUTURE SECTIONS -->
    <div class="card mb-4">
        <div class="card-header"><strong>Experience</strong></div>
        <div class="card-body text-muted">
            Coming soon...
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Projects</strong></div>
        <div class="card-body text-muted">
            Coming soon...
        </div>
    </div>

</div>

</body>
</html>