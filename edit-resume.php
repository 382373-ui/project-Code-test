<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$db  = getDBConnection();
$uid = getCurrentUserId();

$roleStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->execute([$uid]);
$userRole = $roleStmt->fetchColumn();

if ($userRole !== 'student') {
    header('Location: profile.php');
    exit;
}

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_experience') {
        $company   = trim($_POST['company']     ?? '');
        $position  = trim($_POST['position']    ?? '');
        $start     = !empty($_POST['start_date'])  ? $_POST['start_date']  : null;
        $end       = !empty($_POST['end_date'])    ? $_POST['end_date']    : null;
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;
        $desc      = trim($_POST['description'] ?? '');

        // If not currently working, end date must not be in the future
        if (!$isCurrent && $end && $end > date('Y-m-d')) {
            $error = 'End date cannot be in the future when you are no longer working there.';
        } elseif ($company && $position) {
            // Clear end date if currently working
            if ($isCurrent) {
                $end = null;
            }
            $stmt = $db->prepare("INSERT INTO experience (user_id, company, position, start_date, end_date, is_current, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$uid, $company, $position, $start, $end, $isCurrent, $desc]);
            $message = 'Experience entry added.';
        } else {
            $error = 'Company and position are required.';
        }
    }

    if ($action === 'delete_experience') {
        $id = (int)$_POST['experience_id'];
        $stmt = $db->prepare("DELETE FROM experience WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $uid]);
        $message = 'Experience entry removed.';
    }

    if ($action === 'add_project') {
        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $url   = trim($_POST['url'] ?? '');

        if ($title) {
            $stmt = $db->prepare("INSERT INTO projects (user_id, title, description, url) VALUES (?, ?, ?, ?)");
            $stmt->execute([$uid, $title, $desc ?: null, $url ?: null]);
            $message = 'Project added.';
        } else {
            $error = 'Project title is required.';
        }
    }

    if ($action === 'delete_project') {
        $id = (int)$_POST['project_id'];
        $stmt = $db->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $uid]);
        $message = 'Project removed.';
    }
}

$expStmt = $db->prepare("SELECT * FROM experience WHERE user_id = ? ORDER BY start_date DESC");
$expStmt->execute([$uid]);
$experiences = $expStmt->fetchAll(PDO::FETCH_ASSOC);

$projStmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC");
$projStmt->execute([$uid]);
$projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Resume – JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="container mt-4" style="max-width:860px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Edit Resume</h3>
        <div>
            <a href="resume.php" class="btn btn-outline-primary btn-sm me-2"><i class="bi bi-eye me-1"></i>Preview</a>
            <a href="profile.php" class="btn btn-outline-secondary btn-sm">Back to Profile</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- EXPERIENCE -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-briefcase me-2"></i>Experience</strong>
            <button class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#addExpForm">
                <i class="bi bi-plus"></i> Add
            </button>
        </div>
        <div id="addExpForm" class="collapse <?= $error ? 'show' : '' ?>">
            <div class="card-body border-bottom bg-light">
                <form method="POST" class="row g-2" id="expForm">
                    <input type="hidden" name="action" value="add_experience">
                    <div class="col-md-6">
                        <label class="form-label">Company *</label>
                        <input name="company" class="form-control" required placeholder="Company name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Position *</label>
                        <input name="position" class="form-control" required placeholder="Your role">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="expStart" class="form-control" max="<?= $today ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" id="expEnd" class="form-control" max="<?= $today ?>">
                        <div class="invalid-feedback">End date cannot be in the future.</div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_current" id="isCurrent">
                            <label class="form-check-label" for="isCurrent">Currently working here</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief description of your role..."></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">Save Experience</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($experiences)): ?>
                <p class="text-muted mb-0">No experience entries yet. Add your first one above.</p>
            <?php else: ?>
                <?php foreach ($experiences as $exp): ?>
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                    <div>
                        <strong><?= htmlspecialchars($exp['position']) ?></strong> at <?= htmlspecialchars($exp['company']) ?>
                        <?php if ($exp['is_current']): ?>
                            <span class="badge bg-success ms-2">Current</span>
                        <?php endif; ?>
                        <div class="text-muted small">
                            <?= $exp['start_date'] ? date('M Y', strtotime($exp['start_date'])) : '' ?>
                            <?= $exp['end_date'] && !$exp['is_current'] ? ' – ' . date('M Y', strtotime($exp['end_date'])) : ($exp['is_current'] ? ' – Present' : '') ?>
                        </div>
                        <?php if ($exp['description']): ?>
                            <p class="mb-0 mt-1 small"><?= nl2br(htmlspecialchars($exp['description'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <form method="POST" class="ms-3 flex-shrink-0">
                        <input type="hidden" name="action" value="delete_experience">
                        <input type="hidden" name="experience_id" value="<?= $exp['id'] ?>">
                        <button class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove this entry?')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- PROJECTS -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-code-slash me-2"></i>Projects</strong>
            <button class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#addProjForm">
                <i class="bi bi-plus"></i> Add
            </button>
        </div>
        <div id="addProjForm" class="collapse">
            <div class="card-body border-bottom bg-light">
                <form method="POST" class="row g-2">
                    <input type="hidden" name="action" value="add_project">
                    <div class="col-md-6">
                        <label class="form-label">Project Title *</label>
                        <input name="title" class="form-control" required placeholder="e.g. Portfolio Website">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">URL <small class="text-muted">(optional)</small></label>
                        <input name="url" type="url" class="form-control" placeholder="https://...">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="What did you build and what did you learn?"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">Save Project</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($projects)): ?>
                <p class="text-muted mb-0">No projects added yet. Add one above.</p>
            <?php else: ?>
                <?php foreach ($projects as $proj): ?>
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                    <div>
                        <strong><?= htmlspecialchars($proj['title']) ?></strong>
                        <?php if ($proj['url']): ?>
                            <a href="<?= htmlspecialchars($proj['url']) ?>" target="_blank" class="ms-2 small">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($proj['description']): ?>
                            <p class="mb-0 mt-1 small"><?= nl2br(htmlspecialchars($proj['description'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <form method="POST" class="ms-3 flex-shrink-0">
                        <input type="hidden" name="action" value="delete_project">
                        <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                        <button class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove this project?')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <a href="resume.php" class="btn btn-primary">View My Resume</a>
    <a href="edit-profile.php" class="btn btn-outline-secondary ms-2">Edit Profile Info</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const today      = '<?= $today ?>';
    const isCurrentCb = document.getElementById('isCurrent');
    const endInput    = document.getElementById('expEnd');
    const startInput  = document.getElementById('expStart');

    function applyEndDateRules() {
        if (isCurrentCb.checked) {
            // Currently working — disable & clear end date
            endInput.value    = '';
            endInput.disabled = true;
            endInput.removeAttribute('max');
        } else {
            // Not currently working — cap end date at today
            endInput.disabled = false;
            endInput.max      = today;
        }
    }

    isCurrentCb.addEventListener('change', applyEndDateRules);
    applyEndDateRules(); // run on page load

    // Also keep start date from being in the future
    startInput.max = today;

    // Client-side validation before submit
    document.getElementById('expForm').addEventListener('submit', function (e) {
        if (!isCurrentCb.checked && endInput.value && endInput.value > today) {
            endInput.classList.add('is-invalid');
            e.preventDefault();
        } else {
            endInput.classList.remove('is-invalid');
        }
    });
})();
</script>
</body>
</html>
