<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$pdo        = getDBConnection();
$isLoggedIn = isset($_SESSION['user_id']);

$recentJobs = [];
$error      = null;

try {
    $stmt = $pdo->prepare("
        SELECT id, title, category, pay, zip_code, location_details, date_posted, description
        FROM jobs
        WHERE is_active = 1 AND (is_deleted IS NULL OR is_deleted = 0)
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
    <title>JobBridge – About</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/header.php'; ?>

<!-- Hero -->
<div class="hero mb-0">
    <div class="container">
        <h1 class="display-4 fw-bold">About JobBridge</h1>
        <p class="lead mt-3">
            A student-focused platform connecting young people with meaningful opportunities —<br>
            from part-time jobs and internships to odd jobs and volunteer positions near you.
        </p>
        <a href="jobs.php" class="btn btn-light btn-lg mt-2 fw-semibold">Browse Jobs</a>
    </div>
</div>

<!-- Top Leaderboard Ad -->
<div class="container mt-3">
    <div class="ad-slot text-center">
        <span class="ad-label">Advertisement</span>
        <!-- Google AdSense: replace the div below with your AdSense <script> block -->
        <div style="height:90px;display:flex;align-items:center;justify-content:center;color:#adb5bd;">
            728×90 Leaderboard Ad
        </div>
    </div>
</div>

<div class="container mb-5">
    <div class="row g-4">

        <!-- Main Content -->
        <div class="col-lg-8">

            <!-- About -->
            <div class="row justify-content-center mb-4">
                <div class="col-12">
                    <h2 class="mb-3">What We Do</h2>
                    <p class="lead">
                        We created JobBridge because students often struggle to find flexible, local work that
                        fits around classes, extracurriculars, and life. Whether you're looking for:
                    </p>
                    <ul class="list-group list-group-flush mb-4 fs-6">
                        <li class="list-group-item"><i class="bi bi-building me-2 text-primary"></i><strong>Company Jobs</strong> – retail, cafes, warehouses, offices</li>
                        <li class="list-group-item"><i class="bi bi-tools me-2 text-primary"></i><strong>Odd Jobs</strong> – lawn care, moving help, pet sitting, small tasks</li>
                        <li class="list-group-item"><i class="bi bi-mortarboard me-2 text-primary"></i><strong>Internships</strong> – gain real experience in your field of interest</li>
                        <li class="list-group-item"><i class="bi bi-heart me-2 text-primary"></i><strong>Volunteer Work</strong> – give back to your community</li>
                    </ul>
                    <p class="lead">…we make it easy to discover opportunities posted by people and businesses near you.</p>
                </div>
            </div>

            <!-- Recent Jobs -->
            <h2 class="mb-4">Latest Opportunities</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php elseif (empty($recentJobs)): ?>
                <div class="alert alert-info text-center py-4">No recent jobs available at the moment. Check back soon!</div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($recentJobs as $job): ?>
                        <div class="col-md-4">
                            <div class="card job-card h-100 shadow-sm">
                                <div class="card-body">
                                    <span class="badge bg-primary mb-2"><?= ucfirst(htmlspecialchars($job['category'])) ?></span>
                                    <h5 class="card-title text-primary fw-semibold"><?= htmlspecialchars($job['title']) ?></h5>
                                    <p class="text-muted small mb-2">
                                        <i class="bi bi-calendar-event me-1"></i><?= date('M j, Y', strtotime($job['date_posted'])) ?>
                                    </p>
                                    <p class="small mb-2">
                                        <i class="bi bi-geo-alt me-1 text-primary"></i>
                                        <?= htmlspecialchars($job['location_details'] ?: 'Not specified') ?>
                                        <?php if ($job['zip_code']): ?>
                                            <span class="text-muted">(<?= htmlspecialchars($job['zip_code']) ?>)</span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="fw-semibold mb-2">
                                        <?php if ($job['pay'] > 0): ?>
                                            <span class="text-success">$<?= number_format($job['pay'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Volunteer / Unpaid</span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="card-text text-secondary small">
                                        <?= nl2br(htmlspecialchars(substr(trim($job['description']), 0, 100))) ?>…
                                    </p>
                                </div>
                                <div class="card-footer bg-white border-0 pt-0">
                                    <a href="jobs.php" class="btn btn-outline-primary btn-sm">View all jobs &rarr;</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Contact -->
            <div class="contact-box text-center mt-5">
                <h3 class="mb-3">Get in Touch</h3>
                <p class="lead mb-4">Questions, suggestions, or want to report something?</p>
                <a href="mailto:contact@jobbridge.example.com" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-envelope-fill me-2"></i>contact@jobbridge.example.com
                </a>
                <p class="mt-3 text-muted small">(Replace with your real email address)</p>
            </div>

        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">

            <!-- Sidebar Ad (top) -->
            <div class="ad-slot text-center mb-4">
                <span class="ad-label">Advertisement</span>
                <!-- Google AdSense: replace below with your sidebar AdSense block -->
                <div style="min-height:250px;display:flex;align-items:center;justify-content:center;color:#adb5bd;">
                    300×250 Sidebar Ad
                </div>
            </div>

            <!-- Quick Links -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><strong>Quick Links</strong></div>
                <div class="list-group list-group-flush">
                    <a href="jobs.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-briefcase me-2 text-primary"></i>Browse All Jobs
                    </a>
                    <a href="jobs.php?category=volunteer" class="list-group-item list-group-item-action">
                        <i class="bi bi-heart me-2 text-primary"></i>Volunteer Work
                    </a>
                    <a href="jobs.php?category=internship" class="list-group-item list-group-item-action">
                        <i class="bi bi-mortarboard me-2 text-primary"></i>Internships
                    </a>
                    <a href="resource-hub.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-book me-2 text-primary"></i>Resource Hub
                    </a>
                </div>
            </div>

            <!-- Sidebar Ad (bottom) -->
            <div class="ad-slot text-center">
                <span class="ad-label">Advertisement</span>
                <div style="min-height:200px;display:flex;align-items:center;justify-content:center;color:#adb5bd;">
                    300×200 Sidebar Ad
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Admin Access Modal -->
<div class="modal fade" id="adminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-muted">Admin Access</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="password" id="adminPassInput" class="form-control" placeholder="Password" autocomplete="off">
                <div id="adminPassError" class="text-danger small mt-2" style="display:none;">Incorrect password.</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-primary w-100" id="adminPassSubmit">Continue</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
var adminModal = new bootstrap.Modal(document.getElementById('adminModal'));
document.addEventListener('keydown', function(e) {
    if (e.metaKey && e.key === 'l') {
        e.preventDefault();
        document.getElementById('adminPassInput').value = '';
        document.getElementById('adminPassError').style.display = 'none';
        adminModal.show();
        setTimeout(function() { document.getElementById('adminPassInput').focus(); }, 300);
    }
});
function submitAdminPass() {
    var pass = document.getElementById('adminPassInput').value;
    var fd = new FormData(); fd.append('password', pass);
    fetch('admin-auth.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) { adminModal.hide(); window.location.href = 'admin.php'; }
            else {
                document.getElementById('adminPassError').style.display = 'block';
                document.getElementById('adminPassInput').value = '';
                document.getElementById('adminPassInput').focus();
            }
        });
}
document.getElementById('adminPassSubmit').addEventListener('click', submitAdminPass);
document.getElementById('adminPassInput').addEventListener('keydown', e => { if (e.key === 'Enter') submitAdminPass(); });
</script>
</body>
</html>
