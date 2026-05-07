<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$db           = getDBConnection();
$isLoggedIn   = isset($_SESSION['user_id']);
$currentUserId = $isLoggedIn ? (int)$_SESSION['user_id'] : 0;

// Support ?username=X (from .htaccess) OR ?user=ID (legacy)
if (!empty($_GET['username'])) {
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([trim($_GET['username'])]);
    $requestedUserId = (int)$stmt->fetchColumn();
    if (!$requestedUserId) { http_response_code(404); die("Resume not found."); }
} elseif (!empty($_GET['user'])) {
    $requestedUserId = (int)$_GET['user'];
} else {
    if (!$isLoggedIn) { header('Location: login.php'); exit; }
    $requestedUserId = $currentUserId;
}

$isOwnProfile = ($isLoggedIn && $requestedUserId === $currentUserId);

// Fetch user + profile (including is_public, tags)
$stmt = $db->prepare("
    SELECT u.id, u.username, u.email, u.role, u.created_at,
           p.first_name, p.last_name, p.profile_img,
           p.skills, p.bio, p.availability, p.grade_year,
           p.is_public, p.tags
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$requestedUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) { http_response_code(404); die("Resume not found."); }

// Restrict to students only for viewing
if ($user['role'] !== 'student' && !$isOwnProfile) {
    http_response_code(403);
    die("This resume is only available for student accounts.");
}

// Privacy check: if not public and not own profile, require login and ownership check
$isPublic = (bool)($user['is_public'] ?? true);
if (!$isPublic && !$isOwnProfile) {
    if (!$isLoggedIn) { header('Location: login.php'); exit; }
    http_response_code(403);
    die("This resume is private.");
}

$profileImg = !empty($user['profile_img'])
    ? htmlspecialchars($user['profile_img'])
    : 'public/images/default-avatar.png';

// Fetch experience
$expStmt = $db->prepare("SELECT * FROM experience WHERE user_id = ? ORDER BY is_current DESC, start_date DESC");
$expStmt->execute([$requestedUserId]);
$experiences = $expStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch projects
$projStmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC");
$projStmt->execute([$requestedUserId]);
$projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

// Tags array
$tags = !empty($user['tags'])
    ? array_filter(array_map('trim', explode(',', $user['tags'])))
    : [];

$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($fullName) ?> – Resume | JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
</head>
<body>
<?php if ($isLoggedIn): ?>
<?php include 'includes/header.php'; ?>
<?php endif; ?>

<div class="container mt-5 mb-5" style="max-width:860px;">

    <!-- Action Bar (no-print) -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <?php if ($isLoggedIn): ?>
            <a href="profile.php" class="btn btn-outline-secondary btn-sm">&larr; Profile</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-outline-secondary btn-sm">Login</a>
        <?php endif; ?>
        <div class="d-flex gap-2">
            <?php if ($isOwnProfile): ?>
                <a href="edit-resume.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i>Edit Resume
                </a>
                <a href="edit-profile.php" class="btn btn-outline-secondary btn-sm">Edit Profile Info</a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="bi bi-printer me-1"></i>Download / Print PDF
            </button>
        </div>
    </div>

    <?php if (!$isPublic && $isOwnProfile): ?>
    <div class="alert alert-warning no-print">
        <i class="bi bi-lock me-2"></i>Your resume is currently <strong>private</strong>. Only you can see it.
        <a href="edit-profile.php" class="alert-link ms-2">Make it public</a>
    </div>
    <?php endif; ?>

    <!-- HEADER -->
    <div class="card mb-4">
        <div class="card-body text-center py-4" style="background:linear-gradient(135deg,#0a2540,#1565c0);border-radius:10px;">
            <img src="<?= $profileImg ?>" class="rounded-circle mb-3"
                 style="width:120px;height:120px;object-fit:cover;border:4px solid rgba(255,255,255,0.4);" alt="Profile">
            <h2 class="text-white fw-bold mb-1"><?= htmlspecialchars($fullName) ?></h2>
            <p class="text-white-50 mb-2">@<?= htmlspecialchars($user['username']) ?></p>
            <?php if (!empty($tags)): ?>
                <div>
                    <?php foreach ($tags as $tag): ?>
                        <span class="badge bg-light text-primary me-1 mb-1"><?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PUBLIC LINK (own profile) -->
    <?php if ($isOwnProfile && $isPublic): ?>
    <div class="alert alert-info no-print d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-link-45deg fs-5"></i>
        <span>Public link: <strong><?= htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/resume/' . $user['username']) ?></strong></span>
        <button class="btn btn-sm btn-outline-primary ms-auto"
            onclick="navigator.clipboard.writeText('<?= htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/resume/' . $user['username']) ?>'); this.textContent='Copied!'">
            Copy
        </button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- LEFT -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><strong>Contact & Info</strong></div>
                <div class="card-body">
                    <?php if ($isOwnProfile): ?>
                        <p><i class="bi bi-envelope me-2 text-primary"></i><?= htmlspecialchars($user['email']) ?></p>
                    <?php endif; ?>
                    <p><i class="bi bi-calendar me-2 text-primary"></i>Member since <?= date("F Y", strtotime($user['created_at'])) ?></p>
                    <?php if (!empty($user['availability'])): ?>
                        <p><i class="bi bi-clock me-2 text-primary"></i><?= htmlspecialchars($user['availability']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($user['grade_year'])): ?>
                        <p><i class="bi bi-mortarboard me-2 text-primary"></i><?= htmlspecialchars($user['grade_year']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Skills -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><strong>Skills</strong></div>
                <div class="card-body">
                    <?php if (!empty($user['skills'])): ?>
                        <p><?= nl2br(htmlspecialchars($user['skills'])) ?></p>
                    <?php else: ?>
                        <span class="text-muted">No skills listed yet.</span>
                    <?php endif; ?>
                    <?php if (!empty($tags)): ?>
                        <div class="mt-2">
                            <?php foreach ($tags as $tag): ?>
                                <span class="skill-tag"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="col-md-8">
            <!-- Bio -->
            <?php if (!empty($user['bio'])): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><strong>About</strong></div>
                <div class="card-body">
                    <p class="mb-0"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Experience -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-briefcase me-2"></i>Experience</strong>
                    <?php if ($isOwnProfile): ?>
                        <a href="edit-resume.php" class="btn btn-sm btn-light no-print">+ Add</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($experiences)): ?>
                        <span class="text-muted">No experience added yet.</span>
                        <?php if ($isOwnProfile): ?>
                            <a href="edit-resume.php" class="ms-2 no-print">Add experience</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php foreach ($experiences as $i => $exp): ?>
                        <div class="<?= $i > 0 ? 'border-top pt-3 mt-3' : '' ?>">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?= htmlspecialchars($exp['position']) ?></strong>
                                    <span class="text-muted"> · <?= htmlspecialchars($exp['company']) ?></span>
                                </div>
                                <?php if ($exp['is_current']): ?>
                                    <span class="badge bg-success">Current</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                <?= $exp['start_date'] ? date('M Y', strtotime($exp['start_date'])) : '' ?>
                                <?= (!$exp['is_current'] && $exp['end_date']) ? ' – ' . date('M Y', strtotime($exp['end_date'])) : ($exp['is_current'] ? ' – Present' : '') ?>
                            </small>
                            <?php if (!empty($exp['description'])): ?>
                                <p class="mt-1 mb-0 small"><?= nl2br(htmlspecialchars($exp['description'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Projects -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-code-slash me-2"></i>Projects</strong>
                    <?php if ($isOwnProfile): ?>
                        <a href="edit-resume.php" class="btn btn-sm btn-light no-print">+ Add</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($projects)): ?>
                        <span class="text-muted">No projects added yet.</span>
                        <?php if ($isOwnProfile): ?>
                            <a href="edit-resume.php" class="ms-2 no-print">Add a project</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php foreach ($projects as $i => $proj): ?>
                        <div class="<?= $i > 0 ? 'border-top pt-3 mt-3' : '' ?>">
                            <strong><?= htmlspecialchars($proj['title']) ?></strong>
                            <?php if ($proj['url']): ?>
                                <a href="<?= htmlspecialchars($proj['url']) ?>" target="_blank" class="ms-2 small">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($proj['description'])): ?>
                                <p class="mt-1 mb-0 small"><?= nl2br(htmlspecialchars($proj['description'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
