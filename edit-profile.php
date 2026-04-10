<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$db = getDBConnection();
$userId = getCurrentUserId();

/* Fetch current profile data */
$stmt = $db->prepare("
    SELECT u.username, u.email,
           p.first_name, p.last_name, p.profile_img, p.grade_year, 
           p.skills, p.availability, p.location_radius, p.bio, p.age
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* Normalize null values (IMPORTANT for PHP 8.1+) */
$user = $user ?: [];
$user['first_name'] = $user['first_name'] ?? '';
$user['last_name'] = $user['last_name'] ?? '';
$user['grade_year'] = $user['grade_year'] ?? '';
$user['skills'] = $user['skills'] ?? '';
$user['availability'] = $user['availability'] ?? '';
$user['location_radius'] = $user['location_radius'] ?? '';
$user['bio'] = $user['bio'] ?? '';
$user['age'] = $user['age'] ?? '';
$user['profile_img'] = $user['profile_img'] ?? 'public/images/default-avatar.png';

$errors = [];

/* Handle form submission */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $first = trim($_POST["first_name"] ?? '');
    $last = trim($_POST["last_name"] ?? '');
    $grade = trim($_POST["grade_year"] ?? '');
    $skills = trim($_POST["skills"] ?? '');
    $availability = trim($_POST["availability"] ?? '');
    $radius = trim($_POST["location_radius"] ?? '');
    $bio = trim($_POST["bio"] ?? '');
    $age = trim($_POST["age"] ?? '');
    $profileImg = $user['profile_img'];

    /* Handle uploaded image */
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
        /* Update or insert profile */
        $stmt = $db->prepare("
            INSERT INTO profiles 
            (user_id, first_name, last_name, grade_year, skills, availability, 
             location_radius, bio, age, profile_img, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON CONFLICT (user_id) DO UPDATE SET
                first_name = EXCLUDED.first_name,
                last_name = EXCLUDED.last_name,
                grade_year = EXCLUDED.grade_year,
                skills = EXCLUDED.skills,
                availability = EXCLUDED.availability,
                location_radius = EXCLUDED.location_radius,
                bio = EXCLUDED.bio,
                age = EXCLUDED.age,
                profile_img = EXCLUDED.profile_img,
                updated_at = NOW()
        ");

        $stmt->execute([
            $userId, $first, $last, $grade, $skills, $availability,
            $radius, $bio, $age, $profileImg
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
    <title>Edit Profile - JobBridge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="row">

        <!-- Profile Preview -->
        <div class="col-md-4">
            <div class="card text-center mb-3">
                <div class="card-body">
                    <img src="<?= htmlspecialchars($profileImg ?? $user['profile_img']) ?>"
                         class="img-thumbnail mb-2" style="width:150px;" alt="Profile Image">
                    <h5>
                        <?= htmlspecialchars(($first ?? $user['first_name']) . ' ' . ($last ?? $user['last_name'])) ?>
                    </h5>
                    <p class="text-muted">Preview</p>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h5>Edit Profile</h5></div>
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
                                <input name="first_name" class="form-control"
                                       value="<?= htmlspecialchars($first ?? $user['first_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input name="last_name" class="form-control"
                                       value="<?= htmlspecialchars($last ?? $user['last_name']) ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Grade / Year</label>
                                <input name="grade_year" class="form-control"
                                       value="<?= htmlspecialchars($grade ?? $user['grade_year']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Availability</label>
                                <input name="availability" class="form-control"
                                       value="<?= htmlspecialchars($availability ?? $user['availability']) ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Location Radius</label>
                                <input name="location_radius" class="form-control"
                                       value="<?= htmlspecialchars($radius ?? $user['location_radius']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Age</label>
                                <input name="age" type="number" class="form-control"
                                       value="<?= htmlspecialchars($age ?? $user['age']) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Skills</label>
                            <textarea name="skills" class="form-control"><?= htmlspecialchars($skills ?? $user['skills']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bio</label>
                            <textarea name="bio" class="form-control" rows="4"><?= htmlspecialchars($bio ?? $user['bio']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Profile Image</label>
                            <input type="file" name="profile_img" class="form-control">
                            <small class="text-muted">Current: <?= htmlspecialchars($user['profile_img']) ?></small>
                        </div>

                        <button class="btn btn-primary">Save Changes</button>
                        <a href="profile.php" class="btn btn-secondary">Cancel</a>

                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
