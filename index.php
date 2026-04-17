<?php
// Make sure THERE IS NOTHING BEFORE THIS LINE (no blank line, no space, no BOM)
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$pdo = getDBConnection();
$isLoggedIn = isset($_SESSION['user_id']);

// Fetch the 3 most recent active jobs
$recentJobs = [];
$error = null;

try {
    $stmt = $pdo->prepare("
        SELECT id, title, category, pay, zip_code, location_details, date_posted, description
        FROM jobs
        WHERE is_active = 1
        ORDER BY date_posted DESC
        LIMIT 3
    ");
    $stmt->execute();
    $recentJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Could not load recent jobs: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: system-ui, -apple-system, sans-serif; }
        .hero { background-color: #0d6efd; color: white; padding: 5rem 1rem; text-align: center; }
        .job-card {
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            border: none;
            border-radius: 10px;
            overflow: hidden;
        }
        .job-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.12);
        }
        .contact-box {
            background: white;
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        .ad-slot {
            background: #f0f0f0;
            padding: 10px;
            border: 1px dashed #ccc;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<!-- Hero -->
<div class="hero mb-5">
    <div class="container">
        <h1 class="display-4 fw-bold">About JobBridge</h1>
        <p class="lead mt-3">
            JobBridge is a student-focused platform connecting young people with meaningful opportunities —
            from part-time jobs and internships to odd jobs and volunteer positions in your local area.
        </p>
    </div>
</div>

<!-- Ad: 728x90 Leaderboard -->
<div class="ad-slot text-center my-3">
    <small>Advertisement</small><br>
    <div style="height: 90px; background: #eee; display: flex; align-items: center; justify-content: center;">
        <span style="color: #999;">728x90 Ad Space</span>
    </div>
</div>

<div class="container mb-5">
    <!-- About content -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-8">
            <h2 class="mb-4 text-center">What We Do</h2>
            <p class="lead">
                We created JobBridge because students often struggle to find flexible, local work that fits around classes,
                extracurriculars, and life. Whether you're looking for:
            </p>
            <ul class="list-group list-group-flush mb-4 fs-5">
                <li class="list-group-item"><strong>Company Jobs</strong> – retail, cafes, warehouses, offices</li>
                <li class="list-group-item"><strong>Odd Jobs</strong> – lawn care, moving help, pet sitting, small tasks</li>
                <li class="list-group-item"><strong>Internships</strong> – gain real experience in your field of interest</li>
                <li class="list-group-item"><strong>Volunteer Work</strong> – give back to your community</li>
            </ul>
            <p class="lead">…we aim to make it easy to discover opportunities posted by people and businesses near you.</p>
        </div>
    </div>

    <!-- Recent jobs -->
    <h2 class="mb-4 text-center">Latest Opportunities</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php elseif (empty($recentJobs)): ?>
        <div class="alert alert-info text-center py-4">
            No recent jobs available at the moment. Check back soon!
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($recentJobs as $job): ?>
                <div class="col-md-4">
                    <div class="card job-card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-primary fw-semibold"><?= htmlspecialchars($job['title']) ?></h5>
                            <p class="text-muted small mb-3">
                                <i class="bi bi-calendar-event me-1"></i>
                                <?= date('M j, Y', strtotime($job['date_posted'])) ?>
                            </p>
                            <div class="mb-2">
                                <strong>Category:</strong> <?= ucfirst(htmlspecialchars($job['category'])) ?>
                            </div>
                            <div class="mb-2">
                                <strong>Location:</strong>
                                <?= htmlspecialchars($job['location_details'] ?: 'Not specified') ?>
                                <span class="text-muted">(<?= htmlspecialchars($job['zip_code']) ?>)</span>
                            </div>
                            <div class="mb-3">
                                <strong>Pay:</strong>
                                <?php if ($job['pay'] > 0): ?>
                                    $<?= number_format($job['pay'], 2) ?>
                                <?php else: ?>
                                    Volunteer / Unpaid
                                <?php endif; ?>
                            </div>
                            <p class="card-text text-secondary">
                                <?= nl2br(htmlspecialchars(substr(trim($job['description']), 0, 110))) ?>…
                            </p>
                        </div>
                        <div class="card-footer bg-white border-0 pt-0">
                            <a href="jobs.php" class="btn btn-outline-primary btn-sm">View all jobs →</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Contact -->
    <div class="row justify-content-center mt-5">
        <div class="col-lg-6 col-md-8">
            <div class="contact-box text-center">
                <h3 class="mb-4">Get in Touch</h3>
                <p class="lead mb-4">
                    Questions, suggestions, or want to report something?<br>
                    We'd love to hear from you.
                </p>
                <a href="mailto:contact@jobbridge.example.com" class="btn btn-primary btn-lg px-4">
                    <i class="bi bi-envelope-fill me-2"></i> contact@jobbridge.example.com
                </a>
                <p class="mt-3 text-muted small">
                    (change to your real email address)
                </p>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Admin Access Modal -->
<div class="modal fade" id="adminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-muted">Admin Access</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form onsubmit="return false;">
                    <input type="password" id="adminPassInput" class="form-control" placeholder="Password" autocomplete="off">
                </form>
                <div id="adminPassError" class="text-danger small mt-2" style="display:none;">Incorrect password.</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-primary w-100" id="adminPassSubmit">Continue</button>
            </div>
        </div>
    </div>
</div>

<script>
var adminModal = new bootstrap.Modal(document.getElementById('adminModal'));

document.addEventListener('keydown', function(e) {
    if (e.metaKey && e.key === 'l') {
        e.preventDefault();
        document.getElementById('adminPassInput').value = '';
        document.getElementById('adminPassError').style.display = 'none';
        adminModal.show();
        setTimeout(function() {
            document.getElementById('adminPassInput').focus();
        }, 300);
    }
});

function submitAdminPass() {
    var pass = document.getElementById('adminPassInput').value;
    var formData = new FormData();
    formData.append('password', pass);
    fetch('admin-auth.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                adminModal.hide();
                window.location.href = 'admin.php';
            } else {
                document.getElementById('adminPassError').style.display = 'block';
                document.getElementById('adminPassInput').value = '';
                document.getElementById('adminPassInput').focus();
            }
        });
}

document.getElementById('adminPassSubmit').addEventListener('click', submitAdminPass);

document.getElementById('adminPassInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') submitAdminPass();
});
</script>
</body>
</html>
