<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/db.php';

if (
    (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') &&
    empty($_SESSION['admin_unlocked'])
) {
    die("Access denied.");
}

$pdo = getDBConnection();

$totalUsers        = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalJobs         = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$totalMessages     = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$totalApplications = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();

$users = $pdo->query("
    SELECT id, username, email, role, is_verified, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$recentJobs = $pdo->query("
    SELECT j.id, j.title, j.category, j.is_active, j.date_posted, u.username AS poster
    FROM jobs j
    JOIN users u ON u.id = j.poster_user_id
    ORDER BY j.date_posted DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$pendingFlags = $pdo->query("
    SELECT f.id, f.item_type, f.item_id, f.reason, f.created_at, u.username AS reporter
    FROM flags f
    JOIN users u ON u.id = f.reported_by
    WHERE f.status = 'pending'
    ORDER BY f.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_user']]);
        header("Location: admin.php");
        exit;
    }
    if (isset($_POST['toggle_job'])) {
        $stmt = $pdo->prepare("UPDATE jobs SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([(int)$_POST['toggle_job']]);
        header("Location: admin.php");
        exit;
    }
    if (isset($_POST['delete_job'])) {
        $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_job']]);
        header("Location: admin.php");
        exit;
    }
    if (isset($_POST['resolve_flag'])) {
        $stmt = $pdo->prepare("UPDATE flags SET status = 'resolved' WHERE id = ?");
        $stmt->execute([(int)$_POST['resolve_flag']]);
        header("Location: admin.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .sidebar { min-height: 100vh; background: #1a1a2e; color: #fff; width: 220px; position: fixed; top: 0; left: 0; padding-top: 20px; }
        .sidebar a { color: #adb5bd; text-decoration: none; display: block; padding: 10px 20px; }
        .sidebar a:hover, .sidebar a.active { background: #16213e; color: #fff; }
        .sidebar .brand { font-size: 1.2rem; font-weight: bold; padding: 10px 20px 20px; color: #fff; border-bottom: 1px solid #333; margin-bottom: 10px; }
        .main { margin-left: 220px; padding: 30px; }
        .stat-card { border-radius: 12px; color: #fff; padding: 20px; }
        .section { display: none; }
        .section.active { display: block; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="bi bi-shield-lock-fill me-2"></i>Admin Panel</div>
    <a href="#" class="active" onclick="show('overview', this)"><i class="bi bi-grid me-2"></i>Overview</a>
    <a href="#" onclick="show('users', this)"><i class="bi bi-people me-2"></i>Users</a>
    <a href="#" onclick="show('jobs', this)"><i class="bi bi-briefcase me-2"></i>Jobs</a>
    <a href="#" onclick="show('flags', this)">
        <i class="bi bi-flag me-2"></i>Flags
        <?php if (count($pendingFlags) > 0): ?>
            <span class="badge bg-danger ms-1"><?= count($pendingFlags) ?></span>
        <?php endif; ?>
    </a>
    <hr style="border-color:#333;">
    <a href="index.php"><i class="bi bi-house me-2"></i>Back to Site</a>
</div>

<div class="main">

    <!-- OVERVIEW -->
    <div id="sec-overview" class="section active">
        <h3 class="mb-4">Dashboard Overview</h3>
        <div class="row g-3 mb-5">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-primary">
                    <div class="fs-2 fw-bold"><?= $totalUsers ?></div>
                    <div>Total Users</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-success">
                    <div class="fs-2 fw-bold"><?= $totalJobs ?></div>
                    <div>Total Jobs</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-warning text-dark">
                    <div class="fs-2 fw-bold"><?= $totalMessages ?></div>
                    <div>Total Messages</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-info">
                    <div class="fs-2 fw-bold"><?= $totalApplications ?></div>
                    <div>Applications</div>
                </div>
            </div>
        </div>

        <?php if (count($pendingFlags) > 0): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong><?= count($pendingFlags) ?></strong> pending flag(s) need review.
            <a href="#" onclick="show('flags', null)" class="alert-link ms-2">View flags &rarr;</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- USERS -->
    <div id="sec-users" class="section">
        <h3 class="mb-4">Users <span class="badge bg-secondary"><?= $totalUsers ?></span></h3>
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Verified</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'employer' ? 'primary' : 'secondary') ?>"><?= $u['role'] ?></span></td>
                            <td><?= $u['is_verified'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                            <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user and all their data?')">
                                    <input type="hidden" name="delete_user" value="<?= $u['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- JOBS -->
    <div id="sec-jobs" class="section">
        <h3 class="mb-4">Recent Jobs <span class="badge bg-secondary"><?= $totalJobs ?></span></h3>
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Posted By</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentJobs as $j): ?>
                        <tr>
                            <td><?= $j['id'] ?></td>
                            <td><?= htmlspecialchars($j['title']) ?></td>
                            <td><span class="badge bg-secondary"><?= $j['category'] ?></span></td>
                            <td><?= htmlspecialchars($j['poster']) ?></td>
                            <td><?= date('M j, Y', strtotime($j['date_posted'])) ?></td>
                            <td>
                                <?php if ($j['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="d-flex gap-1">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="toggle_job" value="<?= $j['id'] ?>">
                                    <button class="btn btn-sm btn-outline-warning" title="Toggle active"><i class="bi bi-toggle-on"></i></button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this job?')">
                                    <input type="hidden" name="delete_job" value="<?= $j['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FLAGS -->
    <div id="sec-flags" class="section">
        <h3 class="mb-4">Pending Flags <span class="badge bg-danger"><?= count($pendingFlags) ?></span></h3>
        <?php if (empty($pendingFlags)): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>No pending flags.</div>
        <?php else: ?>
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Item ID</th>
                            <th>Reported By</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingFlags as $f): ?>
                        <tr>
                            <td><?= $f['id'] ?></td>
                            <td><span class="badge bg-warning text-dark"><?= $f['item_type'] ?></span></td>
                            <td><?= $f['item_id'] ?></td>
                            <td><?= htmlspecialchars($f['reporter']) ?></td>
                            <td><?= htmlspecialchars($f['reason'] ?? '—') ?></td>
                            <td><?= date('M j, Y', strtotime($f['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="resolve_flag" value="<?= $f['id'] ?>">
                                    <button class="btn btn-sm btn-outline-success">Resolve</button>
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

</div>

<script>
function show(section, el) {
    document.querySelectorAll('.section').forEach(function(s) { s.classList.remove('active'); });
    document.getElementById('sec-' + section).classList.add('active');
    document.querySelectorAll('.sidebar a').forEach(function(a) { a.classList.remove('active'); });
    if (el) el.classList.add('active');
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
