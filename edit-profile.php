<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$db     = getDBConnection();
$userId = getCurrentUserId();

$roleStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->execute([$userId]);
$userRole = $roleStmt->fetchColumn();

$stmt = $db->prepare("
    SELECT u.username, u.email,
           p.first_name, p.last_name, p.profile_img, p.grade_year,
           p.skills, p.availability, p.location_radius, p.bio, p.age,
           p.is_public, p.tags
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$user = $user ?: [];
$user['first_name']      = $user['first_name']      ?? '';
$user['last_name']       = $user['last_name']        ?? '';
$user['grade_year']      = $user['grade_year']       ?? '';
$user['skills']          = $user['skills']           ?? '';
$user['availability']    = $user['availability']     ?? '';
$user['location_radius'] = $user['location_radius']  ?? '';
$user['bio']             = $user['bio']              ?? '';
$user['age']             = $user['age']              ?? '';
$user['profile_img']     = $user['profile_img']      ?? 'public/images/default-avatar.png';
$user['is_public']       = $user['is_public']        ?? 1;
$user['tags']            = $user['tags']             ?? '';

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first        = trim($_POST["first_name"]      ?? '');
    $last         = trim($_POST["last_name"]       ?? '');
    $grade        = trim($_POST["grade_year"]      ?? '');
    $skills       = trim($_POST["skills"]          ?? '');
    $availability = trim($_POST["availability"]    ?? '');
    $radius       = trim($_POST["location_radius"] ?? '');
    $bio          = trim($_POST["bio"]             ?? '');
    $age          = trim($_POST["age"]             ?? '');
    $is_public    = isset($_POST["is_public"])     ? 1 : 0;
    $tags         = trim($_POST["tags"]            ?? '');
    $profileImg   = $user['profile_img'];

    if (!empty($_FILES['profile_img']['name'])) {
        $uploadResult = uploadFile(
            $_FILES['profile_img'],
            "uploads/profiles/",
            ['image/jpeg', 'image/png', 'image/gif'],
            5 * 1024 * 1024
        );
        if (isset($uploadResult['error'])) {
            $errors[] = $uploadResult['error'];
        } else {
            $profileImg = $uploadResult['path'];
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare("
            INSERT INTO profiles
            (user_id, first_name, last_name, grade_year, skills, availability,
             location_radius, bio, age, profile_img, is_public, tags, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                first_name      = VALUES(first_name),
                last_name       = VALUES(last_name),
                grade_year      = VALUES(grade_year),
                skills          = VALUES(skills),
                availability    = VALUES(availability),
                location_radius = VALUES(location_radius),
                bio             = VALUES(bio),
                age             = VALUES(age),
                profile_img     = VALUES(profile_img),
                is_public       = VALUES(is_public),
                tags            = VALUES(tags),
                updated_at      = NOW()
        ");
        $stmt->execute([
            $userId, $first, $last, $grade, $skills, $availability,
            $radius, $bio, $age, $profileImg, $is_public, $tags
        ]);
        header("Location: profile.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile – JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="row">

        <!-- Preview -->
        <div class="col-md-4">
            <div class="card text-center mb-3">
                <div class="card-body">
                    <img src="<?= htmlspecialchars($user['profile_img']) ?>"
                         class="img-thumbnail mb-2 rounded-circle" style="width:130px;height:130px;object-fit:cover;" alt="Profile">
                    <h5><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                    <p class="text-muted small">Preview</p>
                    <a href="profile.php" class="btn btn-outline-secondary btn-sm">Back to Profile</a>
                </div>
            </div>

            <?php if ($userRole === 'student'): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">Resume</div>
                <div class="card-body text-center">
                    <a href="edit-resume.php" class="btn btn-primary btn-sm w-100 mb-2">
                        <i class="bi bi-pencil me-1"></i>Edit Resume
                    </a>
                    <a href="resume.php" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-eye me-1"></i>Preview Resume
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Form -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white"><h5 class="mb-0">Edit Profile</h5></div>
                <div class="card-body">

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $e) echo htmlspecialchars($e) . "<br>"; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Grade / Year</label>
                                <input name="grade_year" class="form-control" value="<?= htmlspecialchars($user['grade_year']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Availability</label>
                                <input name="availability" class="form-control" value="<?= htmlspecialchars($user['availability']) ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Location Radius (miles)</label>
                                <input name="location_radius" class="form-control" value="<?= htmlspecialchars($user['location_radius']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Age</label>
                                <input name="age" type="number" class="form-control" value="<?= htmlspecialchars($user['age']) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Skills</label>
                            <textarea name="skills" class="form-control" rows="3" placeholder="e.g. Customer service, Microsoft Excel..."><?= htmlspecialchars($user['skills']) ?></textarea>
                        </div>

                        <?php if ($userRole === 'student'): ?>
                        <div class="mb-3">
                            <label class="form-label">Skill Tags <small class="text-muted">(comma-separated, searchable)</small></label>
                            <input name="tags" class="form-control" value="<?= htmlspecialchars($user['tags']) ?>" placeholder="e.g. Python, Customer Service, Photoshop">
                            <small class="text-muted">These appear as badges on your public resume.</small>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Bio</label>
                            <textarea name="bio" class="form-control" rows="4" placeholder="Tell employers a bit about yourself..."><?= htmlspecialchars($user['bio']) ?></textarea>
                        </div>

                        <?php if ($userRole === 'student'): ?>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="isPublic" name="is_public"
                                       <?= $user['is_public'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isPublic">
                                    <strong>Make resume publicly visible</strong>
                                    <small class="text-muted d-block">When on, anyone with your resume link can view it without logging in.</small>
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Profile Image</label>
                            <input type="file" name="profile_img" class="form-control" accept="image/*">
                            <small class="text-muted">Current: <?= htmlspecialchars($user['profile_img']) ?></small>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary">Save Changes</button>
                            <a href="profile.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>

                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
