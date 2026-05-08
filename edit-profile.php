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
    $imgUrl       = trim($_POST["profile_img_url"] ?? '');
    $profileImg   = $user['profile_img'];

    // Priority 1: file upload
    if (!empty($_FILES['profile_img']['name'])) {
        $uploadResult = uploadFile(
            $_FILES['profile_img'],
            "uploads/profiles/",
            ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            5 * 1024 * 1024
        );
        if (isset($uploadResult['error'])) {
            $errors[] = $uploadResult['error'];
        } else {
            $profileImg = $uploadResult['path'];
        }
    } elseif (!empty($imgUrl)) {
        // Priority 2: URL
        // Basic sanity check — must start with http/https and look like an image
        if (filter_var($imgUrl, FILTER_VALIDATE_URL) &&
            preg_match('/\.(jpg|jpeg|png|gif|webp|svg)(\?.*)?$/i', $imgUrl)) {
            $profileImg = $imgUrl;
        } else {
            $errors[] = 'Image URL must be a valid URL ending in .jpg, .png, .gif, .webp, or .svg';
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

        <!-- Preview sidebar -->
        <div class="col-md-4">
            <div class="card text-center mb-3">
                <div class="card-body">
                    <img id="imgPreview"
                         src="<?= htmlspecialchars($user['profile_img']) ?>"
                         class="img-thumbnail mb-2 rounded-circle"
                         style="width:130px;height:130px;object-fit:cover;" alt="Profile">
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

        <!-- Main form -->
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

                        <!-- Profile Image section -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Profile Image</label>

                            <!-- Current image info -->
                            <div class="mb-2 d-flex align-items-center gap-2">
                                <small class="text-muted">Current:</small>
                                <a href="<?= htmlspecialchars($user['profile_img']) ?>"
                                   target="_blank"
                                   class="small text-truncate"
                                   style="max-width:280px;"
                                   title="<?= htmlspecialchars($user['profile_img']) ?>">
                                    <?= htmlspecialchars($user['profile_img']) ?>
                                </a>
                            </div>

                            <!-- Tab toggle -->
                            <ul class="nav nav-tabs mb-2" id="imgTab">
                                <li class="nav-item">
                                    <button type="button" class="nav-link active" id="tabUpload" onclick="switchImgTab('upload')">
                                        <i class="bi bi-upload me-1"></i>Upload File
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button type="button" class="nav-link" id="tabUrl" onclick="switchImgTab('url')">
                                        <i class="bi bi-link-45deg me-1"></i>Image URL
                                    </button>
                                </li>
                            </ul>

                            <div id="paneUpload">
                                <input type="file" name="profile_img" id="fileInput" class="form-control" accept="image/*">
                                <small class="text-muted">Max 5 MB. JPG, PNG, GIF, WebP accepted.</small>
                            </div>

                            <div id="paneUrl" style="display:none;">
                                <input type="url" name="profile_img_url" id="urlInput"
                                       class="form-control"
                                       placeholder="https://example.com/photo.jpg"
                                       value="">
                                <small class="text-muted">Must end in .jpg, .png, .gif, .webp, or .svg</small>
                            </div>
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
<script>
function switchImgTab(tab) {
    const uploadPane = document.getElementById('paneUpload');
    const urlPane    = document.getElementById('paneUrl');
    const tabUpload  = document.getElementById('tabUpload');
    const tabUrl     = document.getElementById('tabUrl');

    if (tab === 'upload') {
        uploadPane.style.display = '';
        urlPane.style.display    = 'none';
        tabUpload.classList.add('active');
        tabUrl.classList.remove('active');
        document.getElementById('urlInput').value = '';
    } else {
        uploadPane.style.display = 'none';
        urlPane.style.display    = '';
        tabUpload.classList.remove('active');
        tabUrl.classList.add('active');
        document.getElementById('fileInput').value = '';
    }
}

// Live preview when a file is selected
document.getElementById('fileInput').addEventListener('change', function () {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('imgPreview').src = e.target.result;
        reader.readAsDataURL(file);
    }
});

// Live preview when a URL is typed
document.getElementById('urlInput').addEventListener('input', function () {
    const url = this.value.trim();
    if (url) document.getElementById('imgPreview').src = url;
});
</script>
</body>
</html>
