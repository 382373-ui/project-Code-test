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
    FROM users ORDER BY created_at DESC LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$recentJobs = $pdo->query("
    SELECT j.id, j.title, j.category, j.is_active, j.date_posted, u.username AS poster
    FROM jobs j JOIN users u ON u.id = j.poster_user_id
    ORDER BY j.date_posted DESC LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$pendingFlags = $pdo->query("
    SELECT f.id, f.item_type, f.item_id, f.reason, f.created_at, u.username AS reporter
    FROM flags f JOIN users u ON u.id = f.reported_by
    WHERE f.status = 'pending' ORDER BY f.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Monetization
$monetizationExists = false;
try {
    $pdo->query("SELECT 1 FROM monetization_log LIMIT 1");
    $monetizationExists = true;
} catch (PDOException $e) {}

$totalRevenue      = 0;
$revenueByType     = [];
$recentTransactions = [];
if ($monetizationExists) {
    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM monetization_log")->fetchColumn();
    $revenueByType = $pdo->query("
        SELECT type, COALESCE(SUM(amount), 0) AS total, COUNT(*) AS cnt
        FROM monetization_log GROUP BY type ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $recentTransactions = $pdo->query("
        SELECT ml.id, ml.type, ml.amount, ml.description, ml.created_at, u.username
        FROM monetization_log ml JOIN users u ON u.id = ml.user_id
        ORDER BY ml.created_at DESC LIMIT 25
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Boost revenue
$boostRevenue = 0;
try {
    $boostRevenue = $pdo->query("SELECT COALESCE(SUM(boost_amount), 0) FROM jobs WHERE boost_amount > 0")->fetchColumn();
} catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([(int)$_POST['delete_user']]);
        header("Location: admin.php"); exit;
    }
    if (isset($_POST['toggle_job'])) {
        $pdo->prepare("UPDATE jobs SET is_active = NOT is_active WHERE id = ?")->execute([(int)$_POST['toggle_job']]);
        header("Location: admin.php"); exit;
    }
    if (isset($_POST['delete_job'])) {
        $pdo->prepare("DELETE FROM jobs WHERE id = ?")->execute([(int)$_POST['delete_job']]);
        header("Location: admin.php"); exit;
    }
    if (isset($_POST['resolve_flag'])) {
        $pdo->prepare("UPDATE flags SET status = 'resolved' WHERE id = ?")->execute([(int)$_POST['resolve_flag']]);
        header("Location: admin.php#flags"); exit;
    }
    if (isset($_POST['warn_flag'])) {
        // Mark the flag as reviewed (warning sent — no actual email in demo, just status change)
        $pdo->prepare("UPDATE flags SET status = 'reviewed' WHERE id = ?")->execute([(int)$_POST['warn_flag']]);
        header("Location: admin.php#flags"); exit;
    }
    if (isset($_POST['delete_flagged_item'])) {
        $flagId = (int)$_POST['delete_flagged_item'];
        // Fetch the flag so we know what to soft-delete
        $flag = $pdo->prepare("SELECT item_type, item_id FROM flags WHERE id = ?");
        $flag->execute([$flagId]);
        $flagRow = $flag->fetch(PDO::FETCH_ASSOC);
        if ($flagRow) {
            if ($flagRow['item_type'] === 'job') {
                $pdo->prepare("UPDATE jobs SET is_deleted = 1, is_active = 0 WHERE id = ?")->execute([(int)$flagRow['item_id']]);
            } elseif ($flagRow['item_type'] === 'user') {
                // Soft-disable: set a note in flags only — full user deletion is a separate admin action
                $pdo->prepare("UPDATE users SET is_verified = 0 WHERE id = ?")->execute([(int)$flagRow['item_id']]);
            } elseif ($flagRow['item_type'] === 'message') {
                $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([(int)$flagRow['item_id']]);
            }
            $pdo->prepare("UPDATE flags SET status = 'resolved' WHERE id = ?")->execute([$flagId]);
        }
        header("Location: admin.php#flags"); exit;
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
    <link href="public/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .sidebar { min-height: 100vh; background: #0a2540; color: #fff; width: 220px; position: fixed; top: 0; left: 0; padding-top: 20px; z-index: 100; }
        .sidebar a { color: #adb5bd; text-decoration: none; display: block; padding: 10px 20px; transition: background 0.2s; }
        .sidebar a:hover, .sidebar a.active { background: #1251a3; color: #fff; text-decoration: none; }
        .sidebar .brand { font-size: 1.15rem; font-weight: bold; padding: 10px 20px 20px; color: #fff; border-bottom: 1px solid #1251a3; margin-bottom: 10px; }
        .main { margin-left: 220px; padding: 30px; }
        .section { display: none; }
        .section.active { display: block; }
    </style>
</head>
<body style="background:#f0f4f8;">

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
    <a href="#" onclick="show('monetization', this)"><i class="bi bi-currency-dollar me-2"></i>Revenue</a>
    <hr style="border-color:#1251a3;">
    <a href="index.php"><i class="bi bi-house me-2"></i>Back to Site</a>
</div>

<div class="main">

    <!-- OVERVIEW -->
    <div id="sec-overview" class="section active">
        <h3 class="mb-4">Dashboard Overview</h3>
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-primary">
                    <div class="fs-2 fw-bold"><?= $totalUsers ?></div>
                    <div><i class="bi bi-people me-1"></i>Total Users</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-success">
                    <div class="fs-2 fw-bold"><?= $totalJobs ?></div>
                    <div><i class="bi bi-briefcase me-1"></i>Total Jobs</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-warning text-dark">
                    <div class="fs-2 fw-bold"><?= $totalMessages ?></div>
                    <div><i class="bi bi-chat me-1"></i>Messages</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-info">
                    <div class="fs-2 fw-bold"><?= $totalApplications ?></div>
                    <div><i class="bi bi-file-text me-1"></i>Applications</div>
                </div>
            </div>
        </div>

        <!-- Revenue summary on overview -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="fs-3 fw-bold text-success">$<?= number_format($totalRevenue, 2) ?></div>
                        <div class="text-muted">Total Revenue (logged)</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="fs-3 fw-bold text-primary">$<?= number_format($boostRevenue, 2) ?></div>
                        <div class="text-muted">Boost Revenue</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="fs-3 fw-bold text-warning"><?= count($pendingFlags) ?></div>
                        <div class="text-muted">Pending Flags</div>
                    </div>
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
                        <tr><th>#</th><th>Username</th><th>Email</th><th>Role</th><th>Verified</th><th>Joined</th><th>Actions</th></tr>
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
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
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
                        <tr><th>#</th><th>Title</th><th>Category</th><th>Posted By</th><th>Date</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentJobs as $j): ?>
                        <tr>
                            <td><?= $j['id'] ?></td>
                            <td><?= htmlspecialchars($j['title']) ?></td>
                            <td><span class="badge bg-secondary"><?= $j['category'] ?></span></td>
                            <td><?= htmlspecialchars($j['poster']) ?></td>
                            <td><?= date('M j, Y', strtotime($j['date_posted'])) ?></td>
                            <td><?= $j['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                            <td class="d-flex gap-1">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="toggle_job" value="<?= $j['id'] ?>">
                                    <button class="btn btn-sm btn-outline-warning"><i class="bi bi-toggle-on"></i></button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
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
        <div class="alert alert-secondary small mb-3 py-2">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Warn</strong> — marks the flag as reviewed and records a warning against the item.
            <strong class="ms-2">Resolve</strong> — closes the flag with no further action.
            <strong class="ms-2">Delete</strong> — soft-deletes the reported item (jobs hidden, users suspended, messages removed) and closes the flag.
        </div>
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr><th>#</th><th>Type</th><th>Item ID</th><th>Reported By</th><th>Reason</th><th>Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingFlags as $f): ?>
                        <tr>
                            <td><?= $f['id'] ?></td>
                            <td><span class="badge bg-warning text-dark"><?= $f['item_type'] ?></span></td>
                            <td><?= $f['item_id'] ?></td>
                            <td><?= htmlspecialchars($f['reporter']) ?></td>
                            <td class="small"><?= htmlspecialchars($f['reason'] ?? '—') ?></td>
                            <td class="small text-nowrap"><?= date('M j, Y', strtotime($f['created_at'])) ?></td>
                            <td>
                                <div class="d-flex gap-1 flex-nowrap">
                                    <!-- WARN -->
                                    <form method="POST">
                                        <input type="hidden" name="warn_flag" value="<?= $f['id'] ?>">
                                        <button class="btn btn-sm btn-outline-warning"
                                                title="Issue a warning — marks flag as reviewed"
                                                onclick="return confirm('Issue a warning for this flag?')">
                                            <i class="bi bi-exclamation-triangle me-1"></i>Warn
                                        </button>
                                    </form>
                                    <!-- RESOLVE -->
                                    <form method="POST">
                                        <input type="hidden" name="resolve_flag" value="<?= $f['id'] ?>">
                                        <button class="btn btn-sm btn-outline-success"
                                                title="Resolve with no further action">
                                            <i class="bi bi-check-circle me-1"></i>Resolve
                                        </button>
                                    </form>
                                    <!-- DELETE (soft) -->
                                    <form method="POST">
                                        <input type="hidden" name="delete_flagged_item" value="<?= $f['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger"
                                                title="Soft-delete the reported item and close this flag"
                                                onclick="return confirm('This will remove the reported <?= $f['item_type'] ?> (soft delete) and close the flag. Continue?')">
                                            <i class="bi bi-trash me-1"></i>Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- MONETIZATION -->
    <div id="sec-monetization" class="section">
        <h3 class="mb-4">Revenue Dashboard</h3>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="fs-2 fw-bold text-success">$<?= number_format($totalRevenue, 2) ?></div>
                        <div class="text-muted">Total Revenue</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="fs-2 fw-bold text-primary">$<?= number_format($boostRevenue, 2) ?></div>
                        <div class="text-muted">Boost Revenue (jobs)</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="fs-2 fw-bold text-info"><?= count($recentTransactions) ?></div>
                        <div class="text-muted">Recent Transactions</div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($revenueByType)): ?>
        <!-- Revenue by Type + Chart -->
        <div class="row g-4 mb-4">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header bg-primary text-white"><strong>Revenue by Type</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Type</th><th>Transactions</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($revenueByType as $r): ?>
                                <tr>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($r['type']) ?></span></td>
                                    <td><?= $r['cnt'] ?></td>
                                    <td class="fw-bold text-success">$<?= number_format($r['total'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-primary text-white"><strong>Revenue Breakdown</strong></div>
                    <div class="card-body">
                        <canvas id="revenueChart" height="160"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header bg-primary text-white"><strong>Recent Transactions</strong></div>
            <div class="card-body p-0">
                <?php if (empty($recentTransactions)): ?>
                    <div class="p-4 text-muted text-center">No transactions logged yet.</div>
                <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr><th>#</th><th>User</th><th>Type</th><th>Amount</th><th>Description</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $t): ?>
                        <tr>
                            <td><?= $t['id'] ?></td>
                            <td><?= htmlspecialchars($t['username']) ?></td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($t['type']) ?></span></td>
                            <td class="fw-bold text-success">$<?= number_format($t['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($t['description'] ?? '—') ?></td>
                            <td><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /main -->

<script>
function show(section, el) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById('sec-' + section).classList.add('active');
    document.querySelectorAll('.sidebar a').forEach(a => a.classList.remove('active'));
    if (el) el.classList.add('active');
}

<?php if (!empty($revenueByType)): ?>
// Revenue chart
const ctx = document.getElementById('revenueChart');
if (ctx) {
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($revenueByType, 'type')) ?>,
            datasets: [{
                data: <?= json_encode(array_map(fn($r) => round($r['total'], 2), $revenueByType)) ?>,
                backgroundColor: ['#1976d2','#2196f3','#64b5f6','#bbdefb','#e3f2fd'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}
<?php endif; ?>
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
