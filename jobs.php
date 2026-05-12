<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/ad_helper.php';

$isLoggedIn = isset($_SESSION['user_id']);
$pdo        = getDBConnection();

/* =========================
   RATING SYSTEM (AJAX)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_job_id'])) {
    if (!$isLoggedIn) { http_response_code(403); echo json_encode(['error' => 'login_required']); exit; }

    $jobId   = (int)$_POST['rate_job_id'];
    $rating  = (int)$_POST['rating'];
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) { echo json_encode(['error' => 'invalid_rating']); exit; }

    $stmt = $pdo->prepare("
        INSERT INTO job_ratings (job_id, user_id, rating, comment)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)
    ");
    $stmt->execute([$jobId, $_SESSION['user_id'], $rating, $comment ?: null]);
    echo json_encode(['success' => true]);
    exit;
}

/* =========================
   DISPUTE / FLAG (AJAX)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flag_job_id'])) {
    if (!$isLoggedIn) { http_response_code(403); echo json_encode(['error' => 'login_required']); exit; }

    $jobId  = (int)$_POST['flag_job_id'];
    $reason = trim($_POST['flag_reason'] ?? 'No reason provided.');

    $stmt = $pdo->prepare("INSERT INTO flags (item_type, item_id, reported_by, reason, status) VALUES ('job', ?, ?, ?, 'pending')");
    $stmt->execute([$jobId, $_SESSION['user_id'], $reason]);
    echo json_encode(['success' => true]);
    exit;
}

/* =========================
   BOOKMARK SYSTEM
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id'])) {
    if (!$isLoggedIn) { http_response_code(403); echo json_encode(['error' => 'login_required']); exit; }

    $jobId = (int)$_POST['job_id'];
    $stmt  = $pdo->prepare("SELECT is_active FROM jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job || (int)$job['is_active'] !== 1) { echo json_encode(['error' => 'inactive', 'disabled' => true]); exit; }

    $stmt = $pdo->prepare("SELECT 1 FROM saved_jobs WHERE user_id = ? AND job_id = ?");
    $stmt->execute([$_SESSION['user_id'], $jobId]);
    $isSaved = $stmt->fetchColumn();

    if ($isSaved) {
        $pdo->prepare("DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?")->execute([$_SESSION['user_id'], $jobId]);
        echo json_encode(['saved' => false]);
    } else {
        $pdo->prepare("INSERT INTO saved_jobs (user_id, job_id, created_at) VALUES (?, ?, NOW())")->execute([$_SESSION['user_id'], $jobId]);
        echo json_encode(['saved' => true]);
    }
    exit;
}

/* =========================
   SEARCH + JOB FETCH
========================= */
$search_title    = $_GET['title']    ?? '';
$search_zip      = $_GET['zip']      ?? '';
$search_category = $_GET['category'] ?? 'All';
$search_min_pay  = $_GET['min_pay']  ?? '';

$jobs       = [];
$categories = ['company','odd','volunteer','internship'];
$error_message = '';

try {
    $sql = "
        SELECT j.id, j.title, j.description, j.category, j.pay, j.zip_code,
               j.is_active, j.location_details, j.date_posted, j.date_needed,
               j.poster_user_id, j.is_boosted, j.boost_amount,
               COALESCE(AVG(r.rating), 0) AS avg_rating,
               COUNT(r.rating) AS rating_count
        FROM jobs j
        LEFT JOIN job_ratings r ON j.id = r.job_id
        WHERE j.is_active = 1 AND (j.is_deleted IS NULL OR j.is_deleted = 0)
    ";
    $params = [];

    if (!empty($search_title)) {
        $sql .= " AND j.title LIKE ?";
        $params[] = '%' . $search_title . '%';
    }
    if (!empty($search_zip)) {
        $sql .= " AND j.zip_code LIKE ?";
        $params[] = $search_zip . '%';
    }
    if ($search_category !== 'All' && in_array($search_category, $categories)) {
        $sql .= " AND j.category = ?";
        $params[] = $search_category;
    }
    if (!empty($search_min_pay) && is_numeric($search_min_pay)) {
        $sql .= " AND j.pay >= ?";
        $params[] = $search_min_pay;
    }

    $sql .= " GROUP BY j.id ORDER BY j.is_boosted DESC, j.boost_amount DESC, j.pay DESC, j.date_posted DESC LIMIT 50";

    $stmt_jobs = $pdo->prepare($sql);
    $stmt_jobs->execute($params);
    $jobs = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);

    $savedJobIds = [];
    if ($isLoggedIn) {
        $stmt = $pdo->prepare("SELECT job_id FROM saved_jobs WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $savedJobIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Confirmation status per job (for the current user)
    $myConfirmations = [];
    if ($isLoggedIn && !empty($jobs)) {
        $jobIds = array_column($jobs, 'id');
        $in     = implode(',', array_fill(0, count($jobIds), '?'));
        $stmt   = $pdo->prepare("
            SELECT job_id, status FROM job_confirmations
            WHERE job_id IN ($in)
            ORDER BY id DESC
        ");
        $stmt->execute($jobIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            // keep highest-priority status per job
            $existing = $myConfirmations[$row['job_id']] ?? null;
            if (!$existing || $row['status'] === 'confirmed') {
                $myConfirmations[$row['job_id']] = $row['status'];
            }
        }
    }
} catch (PDOException $e) {
    $error_message = "An error occurred: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JobBridge – Find Jobs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="header-bar">
    <h1>Welcome to JobBridge</h1>
    <p class="lead mb-0">Find jobs, internships, and volunteer work near you.</p>
</div>

<div class="container mt-3">
<?php renderAd('in-feed', $pdo); ?>
</div>

<div class="container">

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<!-- Search -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="title" class="form-control" placeholder="Job title..." value="<?= htmlspecialchars($search_title) ?>">
            </div>
            <div class="col-md-2">
                <input type="text" name="zip" class="form-control" placeholder="ZIP" value="<?= htmlspecialchars($search_zip) ?>">
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select">
                    <option value="All">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= ($search_category === $cat) ? 'selected' : '' ?>>
                            <?= ucfirst($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="min_pay" class="form-control" placeholder="Min Pay $" value="<?= htmlspecialchars($search_min_pay) ?>">
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100">Search</button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($jobs)): ?>
    <div class="alert alert-info text-center py-4">No jobs match your search. Try different keywords or filters.</div>
<?php endif; ?>

<?php foreach ($jobs as $i => $job): ?>

    <div class="job-listing <?= $job['is_boosted'] ? 'boosted' : '' ?>">

        <?php if ($job['is_boosted']): ?>
            <span class="boosted-badge">FEATURED</span>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4 class="mb-1"><?= htmlspecialchars($job['title']) ?></h4>
                <span class="badge bg-primary me-1"><?= ucfirst(htmlspecialchars($job['category'])) ?></span>
                <span class="text-muted small">
                    <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($job['location_details'] ?: 'Location not specified') ?>
                    <?php if ($job['zip_code']): ?>(<?= htmlspecialchars($job['zip_code']) ?>)<?php endif; ?>
                </span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php
                    $confStatus = $myConfirmations[$job['id']] ?? null;
                    $confBadge  = '';
                    if ($confStatus === 'confirmed') {
                        $confBadge = '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Confirmed</span>';
                    } elseif ($confStatus === 'submitted') {
                        $confBadge = '<span class="badge bg-primary"><i class="bi bi-clock me-1"></i>Proof Submitted</span>';
                    } elseif ($confStatus === 'disputed') {
                        $confBadge = '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Disputed</span>';
                    }
                ?>
                <?php if ($isLoggedIn && $job['poster_user_id'] != $_SESSION['user_id']): ?>
                    <a href="chat.php?user_id=<?= $job['poster_user_id'] ?>&job_id=<?= $job['id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-chat me-1"></i>Message
                    </a>
                    <a href="confirm_job.php?job_id=<?= $job['id'] ?>" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-check-circle me-1"></i>Confirm Job
                    </a>
                <?php endif; ?>
                <?php if ($isLoggedIn): ?>
                    <i class="bookmark bi <?= in_array($job['id'], $savedJobIds) ? 'bi-bookmark-fill saved' : 'bi-bookmark' ?>"
                       data-job-id="<?= $job['id'] ?>" title="Save job"></i>
                <?php endif; ?>
            </div>
        </div>

        <p class="text-muted small mt-2 mb-1">
            <i class="bi bi-calendar-event me-1"></i>Posted <?= date('M j, Y', strtotime($job['date_posted'])) ?>
            <?php if ($job['date_needed']): ?>
                &nbsp;·&nbsp;<i class="bi bi-alarm me-1"></i>Needed by <?= date('M j, Y', strtotime($job['date_needed'])) ?>
            <?php endif; ?>
        </p>

        <p class="mb-2">
            <strong>Pay:</strong>
            <?= $job['pay'] > 0 ? '<span class="text-success fw-semibold">$' . number_format($job['pay'], 2) . '</span>' : '<span class="text-muted">Volunteer / Unpaid</span>' ?>
        </p>

        <p class="mb-2"><?= nl2br(htmlspecialchars(substr($job['description'], 0, 200))) ?><?= strlen($job['description']) > 200 ? '…' : '' ?></p>

        <!-- Rating Display -->
        <div class="mb-2">
            <span class="rating-display">
                <?= str_repeat("★", round($job['avg_rating'] ?? 0)) ?><?= str_repeat("☆", 5 - round($job['avg_rating'] ?? 0)) ?>
            </span>
            <small class="text-muted">(<?= number_format($job['avg_rating'] ?? 0, 1) ?>/5 · <?= $job['rating_count'] ?? 0 ?> reviews)</small>
        </div>

        <?php if ($isLoggedIn && $job['poster_user_id'] != $_SESSION['user_id']): ?>
        <!-- Rate & Comment -->
        <div class="rating-stars mb-1" data-job-id="<?= $job['id'] ?>">
            <?php for ($s = 1; $s <= 5; $s++): ?>
                <i class="bi bi-star star" data-value="<?= $s ?>"></i>
            <?php endfor; ?>
        </div>
        <div class="rating-comment-row" data-job-id="<?= $job['id'] ?>" style="display:none;">
            <div class="input-group input-group-sm mt-1" style="max-width:400px;">
                <input type="text" class="form-control comment-input" placeholder="Add a comment (optional)">
                <button class="btn btn-primary submit-rating">Submit</button>
            </div>
        </div>

        <!-- Report Issue -->
        <button class="btn btn-sm btn-outline-danger mt-2 flag-btn" data-job-id="<?= $job['id'] ?>">
            <i class="bi bi-flag me-1"></i>Report Issue
        </button>
        <?php endif; ?>

        <!-- Confirmation status + View Details -->
        <div class="d-flex align-items-center gap-2 mt-2 flex-wrap border-top pt-2">
            <?php if ($confBadge): ?>
                <?= $confBadge ?>
            <?php else: ?>
                <span class="badge bg-light text-muted border">
                    <i class="bi bi-hourglass-split me-1"></i>Not Started
                </span>
            <?php endif; ?>
            <a href="job-detail.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-outline-secondary ms-auto">
                <i class="bi bi-eye me-1"></i>View Details
            </a>
        </div>
    </div>

    <?php if ($i > 0 && $i % 5 === 4): renderAd('in-feed', $pdo); endif; ?>

<?php endforeach; ?>

</div>

<!-- Flag / Dispute Modal -->
<div class="modal fade" id="flagModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-danger"><i class="bi bi-flag me-2"></i>Report an Issue</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="flagJobId">
                <label class="form-label">Describe the issue</label>
                <textarea id="flagReason" class="form-control" rows="3" placeholder="e.g. Suspicious posting, did not pay, misleading description..."></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger btn-sm" id="flagSubmitBtn">Submit Report</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Bookmark
document.querySelectorAll('.bookmark').forEach(icon => {
    icon.addEventListener('click', function () {
        fetch('jobs.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'job_id=' + this.dataset.jobId
        })
        .then(r => r.json())
        .then(data => {
            if (data.saved) {
                this.classList.add('saved');
                this.classList.replace('bi-bookmark', 'bi-bookmark-fill');
            } else {
                this.classList.remove('saved');
                this.classList.replace('bi-bookmark-fill', 'bi-bookmark');
            }
        });
    });
});

// Star rating with comment
document.querySelectorAll('.rating-stars').forEach(container => {
    const stars   = container.querySelectorAll('.star');
    const jobId   = container.dataset.jobId;
    const commentRow = document.querySelector(`.rating-comment-row[data-job-id="${jobId}"]`);
    let selectedRating = 0;

    stars.forEach(star => {
        star.addEventListener('mouseover', function () {
            stars.forEach(s => s.classList.toggle('active', s.dataset.value <= this.dataset.value));
        });
        star.addEventListener('mouseleave', function () {
            stars.forEach(s => s.classList.toggle('active', s.dataset.value <= selectedRating));
        });
        star.addEventListener('click', function () {
            selectedRating = this.dataset.value;
            stars.forEach(s => s.classList.toggle('active', s.dataset.value <= selectedRating));
            if (commentRow) commentRow.style.display = 'block';
        });
    });

    const submitBtn = commentRow ? commentRow.querySelector('.submit-rating') : null;
    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            const comment = commentRow.querySelector('.comment-input').value;
            fetch('jobs.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `rate_job_id=${jobId}&rating=${selectedRating}&comment=${encodeURIComponent(comment)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    commentRow.innerHTML = '<small class="text-success"><i class="bi bi-check-circle me-1"></i>Rating submitted. Thanks!</small>';
                }
            });
        });
    }
});

// Flag / Report
const flagModal = new bootstrap.Modal(document.getElementById('flagModal'));
document.querySelectorAll('.flag-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('flagJobId').value = this.dataset.jobId;
        document.getElementById('flagReason').value = '';
        flagModal.show();
    });
});
document.getElementById('flagSubmitBtn').addEventListener('click', function () {
    const jobId  = document.getElementById('flagJobId').value;
    const reason = document.getElementById('flagReason').value.trim();
    if (!reason) { alert('Please describe the issue.'); return; }
    fetch('jobs.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `flag_job_id=${jobId}&flag_reason=${encodeURIComponent(reason)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            flagModal.hide();
            alert('Report submitted. An admin will review it.');
        }
    });
});
</script>
</body>
</html>
