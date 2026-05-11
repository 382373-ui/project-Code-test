<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/ad_helper.php';

$pdo       = getDBConnection();
$isLoggedIn = isset($_SESSION['user_id']);
$myId       = $isLoggedIn ? (int)$_SESSION['user_id'] : 0;
$jobId      = (int)($_GET['id'] ?? 0);

if (!$jobId) { header('Location: jobs.php'); exit; }

// ── Job + poster info ─────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT j.*,
           u.id AS poster_id, u.username AS poster_username, u.role AS poster_role,
           p.first_name AS poster_fn, p.last_name AS poster_ln,
           p.profile_img AS poster_img, p.bio AS poster_bio,
           p.skills AS poster_skills, p.is_public AS poster_public
    FROM jobs j
    JOIN users u ON u.id = j.poster_user_id
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE j.id = ? AND (j.is_deleted IS NULL OR j.is_deleted = 0)
");
$stmt->execute([$jobId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) { http_response_code(404); die("Job not found."); }

$isPoster = ($isLoggedIn && $myId === (int)$job['poster_id']);

// ── All confirmations for this job ───────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT jc.*,
           wu.id AS w_id, wu.username AS worker_username,
           wp.first_name AS worker_fn, wp.last_name AS worker_ln,
           wp.profile_img AS worker_img, wp.skills AS worker_skills,
           wp.bio AS worker_bio, wp.is_public AS worker_public,
           wp.grade_year AS worker_grade, wp.availability AS worker_avail,
           wp.tags AS worker_tags
    FROM job_confirmations jc
    JOIN users wu ON wu.id = jc.worker_user_id
    LEFT JOIN profiles wp ON wp.user_id = jc.worker_user_id
    WHERE jc.job_id = ?
    ORDER BY jc.id DESC
");
$stmt->execute([$jobId]);
$confirmations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// My own confirmation record (as worker)
$myConfirmation = null;
foreach ($confirmations as $c) {
    if ((int)$c['w_id'] === $myId) { $myConfirmation = $c; break; }
}

// ── Saved? ───────────────────────────────────────────────────────────────────
$isSaved = false;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT 1 FROM saved_jobs WHERE user_id = ? AND job_id = ?");
    $stmt->execute([$myId, $jobId]);
    $isSaved = (bool)$stmt->fetchColumn();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function statusInfo($status) {
    return match($status) {
        'submitted' => ['primary',   'clock',               'Proof Submitted',    'Waiting for poster to review the submitted proof.'],
        'confirmed' => ['success',   'check-circle-fill',   'Confirmed Complete', 'The poster has confirmed this job is complete.'],
        'disputed'  => ['danger',    'exclamation-triangle', 'Disputed',          'A dispute has been filed. Admin will review.'],
        default     => ['secondary', 'hourglass-split',     'Pending',            'No proof submitted yet.'],
    };
}

$posterFullName = trim(($job['poster_fn'] ?? '') . ' ' . ($job['poster_ln'] ?? ''))
               ?: $job['poster_username'];
$posterImg = !empty($job['poster_img'])
    ? htmlspecialchars($job['poster_img'])
    : 'public/images/default-avatar.png';

// Overall job confirmation status
$overallStatus = 'none';
foreach ($confirmations as $c) {
    if ($c['status'] === 'confirmed')  { $overallStatus = 'confirmed'; break; }
    if ($c['status'] === 'submitted')  { $overallStatus = 'submitted'; }
    if ($c['status'] === 'disputed' && $overallStatus !== 'submitted') { $overallStatus = 'disputed'; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($job['title']) ?> – JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
    <style>
        .status-step { text-align:center; min-width:80px; }
        .status-step .circle {
            width:42px; height:42px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            margin:0 auto 6px; font-size:1rem; font-weight:700;
        }
        .status-step small { font-size:.75rem; color:#6c757d; }
        .worker-card { border-left: 4px solid #1565c0; }
        .tag-pill { display:inline-block; background:#e8f0fe; color:#1565c0;
                    border-radius:20px; padding:2px 10px; font-size:.75rem; margin:2px; }
        .section-header { background: #0a2540; color:#fff; border-radius:8px 8px 0 0; padding:10px 16px; font-weight:600; }
    </style>
</head>
<body class="bg-light">
<?php include 'includes/header.php'; ?>

<div class="container mt-4 mb-5" style="max-width:960px;">

    <!-- Back + actions -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <a href="jobs.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Jobs
        </a>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($isLoggedIn && !$isPoster): ?>
                <a href="chat.php?user_id=<?= $job['poster_id'] ?>&job_id=<?= $jobId ?>"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-chat me-1"></i>Message Poster
                </a>
                <a href="confirm_job.php?job_id=<?= $jobId ?>"
                   class="btn btn-sm btn-<?= $myConfirmation ? 'success' : 'outline-success' ?>">
                    <i class="bi bi-check2-circle me-1"></i>
                    <?= $myConfirmation ? 'View My Confirmation' : 'Submit Confirmation' ?>
                </a>
            <?php elseif ($isPoster): ?>
                <a href="confirm_job.php?job_id=<?= $jobId ?>"
                   class="btn btn-sm btn-primary">
                    <i class="bi bi-check2-circle me-1"></i>Review Confirmations
                </a>
                <a href="boost.php?id=<?= $jobId ?>"
                   class="btn btn-sm btn-<?= ($job['boost_amount'] ?? 0) > 0 ? 'success' : 'outline-success' ?>">
                    <i class="bi bi-lightning-fill me-1"></i>
                    <?= ($job['boost_amount'] ?? 0) > 0 ? 'Boosted' : 'Boost Job' ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">

        <!-- ════════════════ LEFT: JOB INFO ════════════════ -->
        <div class="col-lg-7">

            <!-- Job header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                        <div>
                            <?php if ($job['is_boosted'] || ($job['boost_amount'] ?? 0) > 0): ?>
                                <span class="badge bg-warning text-dark me-1">
                                    <i class="bi bi-lightning-fill me-1"></i>FEATURED
                                </span>
                            <?php endif; ?>
                            <span class="badge bg-primary"><?= ucfirst($job['category']) ?></span>
                        </div>
                        <?php if (!$job['is_active']): ?>
                            <span class="badge bg-secondary">Closed</span>
                        <?php endif; ?>
                    </div>

                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($job['title']) ?></h3>

                    <div class="text-muted small mb-3">
                        <i class="bi bi-geo-alt me-1"></i>
                        <?= htmlspecialchars($job['location_details'] ?: 'Location not specified') ?>
                        <?php if ($job['zip_code']): ?> (<?= htmlspecialchars($job['zip_code']) ?>)<?php endif; ?>
                        &nbsp;·&nbsp;
                        <i class="bi bi-calendar me-1"></i>Posted <?= date('M j, Y', strtotime($job['date_posted'])) ?>
                        <?php if ($job['date_needed']): ?>
                            &nbsp;·&nbsp;<i class="bi bi-alarm me-1"></i>Needed by <?= date('M j, Y', strtotime($job['date_needed'])) ?>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <span class="fs-5 fw-bold <?= ($job['pay'] ?? 0) > 0 ? 'text-success' : 'text-muted' ?>">
                            <?= ($job['pay'] ?? 0) > 0
                                ? '$' . number_format($job['pay'], 2) . ($job['pay_type'] === 'hourly' ? '/hr' : ' total')
                                : 'Volunteer / Unpaid' ?>
                        </span>
                    </div>

                    <p class="mb-0"><?= nl2br(htmlspecialchars($job['description'])) ?></p>
                </div>
            </div>

            <!-- ════ CONFIRMATION STATUS SECTION ════ -->
            <div class="card mb-4">
                <div class="section-header">
                    <i class="bi bi-patch-check me-2"></i>Confirmation Status
                </div>
                <div class="card-body">

                    <!-- Progress bar visual -->
                    <?php
                    $step1done = ($overallStatus !== 'none');
                    $step2done = in_array($overallStatus, ['confirmed']);
                    $step3done = ($overallStatus === 'confirmed');
                    ?>
                    <div class="d-flex align-items-center mb-4">
                        <div class="status-step">
                            <div class="circle bg-<?= $step1done ? 'primary' : 'light border' ?> text-<?= $step1done ? 'white' : 'muted' ?>">
                                <?= $step1done ? '<i class="bi bi-check-lg"></i>' : '1' ?>
                            </div>
                            <small>Proof<br>Submitted</small>
                        </div>
                        <div class="flex-grow-1 border-top border-2 mx-2"></div>
                        <div class="status-step">
                            <div class="circle bg-<?= ($overallStatus === 'submitted' || $step2done) ? 'primary' : 'light border' ?> text-<?= ($overallStatus === 'submitted' || $step2done) ? 'white' : 'muted' ?>">
                                <?= $step2done ? '<i class="bi bi-check-lg"></i>' : '2' ?>
                            </div>
                            <small>Poster<br>Reviews</small>
                        </div>
                        <div class="flex-grow-1 border-top border-2 mx-2"></div>
                        <div class="status-step">
                            <div class="circle bg-<?= $step3done ? 'success' : 'light border' ?> text-<?= $step3done ? 'white' : 'muted' ?>">
                                <?= $step3done ? '<i class="bi bi-check-lg"></i>' : '3' ?>
                            </div>
                            <small>Complete</small>
                        </div>
                    </div>

                    <?php if ($overallStatus === 'none'): ?>
                    <div class="alert alert-secondary d-flex align-items-center gap-2 mb-0">
                        <i class="bi bi-hourglass-split fs-5"></i>
                        <div>
                            <strong>Not Started</strong> — No worker has submitted proof yet.
                            <?php if ($isLoggedIn && !$isPoster): ?>
                                <a href="confirm_job.php?job_id=<?= $jobId ?>" class="ms-2">
                                    Submit your completion proof &rarr;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php foreach ($confirmations as $c):
                        [$color, $icon, $label, $desc] = statusInfo($c['status']);
                        $wName = trim(($c['worker_fn'] ?? '') . ' ' . ($c['worker_ln'] ?? '')) ?: $c['worker_username'];
                        $wImg  = !empty($c['worker_img']) ? htmlspecialchars($c['worker_img']) : 'public/images/default-avatar.png';
                        $wTags = !empty($c['worker_tags']) ? array_filter(array_map('trim', explode(',', $c['worker_tags']))) : [];
                        $wPublic = (bool)($c['worker_public'] ?? true);
                    ?>
                    <div class="border rounded p-3 mb-3 <?= $c['status'] === 'confirmed' ? 'border-success' : '' ?>">
                        <!-- Worker identity -->
                        <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
                            <img src="<?= $wImg ?>" class="rounded-circle"
                                 style="width:42px;height:42px;object-fit:cover;">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($wName) ?></div>
                                <div class="text-muted small">@<?= htmlspecialchars($c['worker_username']) ?>
                                    <?php if (!empty($c['worker_grade'])): ?>
                                        · <?= htmlspecialchars($c['worker_grade']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="badge bg-<?= $color ?> ms-auto">
                                <i class="bi bi-<?= $icon ?> me-1"></i><?= $label ?>
                            </span>
                        </div>

                        <!-- Worker tags -->
                        <?php if (!empty($wTags)): ?>
                        <div class="mb-2">
                            <?php foreach ($wTags as $tag): ?>
                                <span class="tag-pill"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Worker skills / bio (if public) -->
                        <?php if ($wPublic): ?>
                            <?php if (!empty($c['worker_skills'])): ?>
                            <div class="mb-2 small text-muted">
                                <i class="bi bi-lightning me-1"></i><?= htmlspecialchars(substr($c['worker_skills'], 0, 120)) ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($c['worker_avail'])): ?>
                            <div class="mb-2 small text-muted">
                                <i class="bi bi-clock me-1"></i><?= htmlspecialchars($c['worker_avail']) ?>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="small text-muted mb-2"><i class="bi bi-lock me-1"></i>Profile is private.</div>
                        <?php endif; ?>

                        <!-- Proof text -->
                        <?php if (!empty($c['proof_text'])): ?>
                        <div class="bg-light rounded p-2 small mb-2">
                            <strong>Proof submitted:</strong><br>
                            <?= nl2br(htmlspecialchars($c['proof_text'])) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($c['proof_file_ref'])): ?>
                        <a href="<?= htmlspecialchars($c['proof_file_ref']) ?>" target="_blank"
                           class="btn btn-sm btn-outline-secondary mb-2">
                            <i class="bi bi-paperclip me-1"></i>View Attachment
                        </a>
                        <?php endif; ?>

                        <?php if ($c['confirmed_at']): ?>
                        <div class="small text-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Confirmed on <?= date('M j, Y \a\t g:i a', strtotime($c['confirmed_at'])) ?>
                        </div>
                        <?php endif; ?>

                        <!-- Links -->
                        <div class="d-flex gap-2 mt-2 flex-wrap">
                            <?php if ($wPublic): ?>
                                <a href="resume.php?user=<?= $c['w_id'] ?>" target="_blank"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-file-person me-1"></i>View Resume
                                </a>
                            <?php endif; ?>
                            <?php if ($isLoggedIn && $c['w_id'] != $myId): ?>
                                <a href="chat.php?user_id=<?= $c['w_id'] ?>&job_id=<?= $jobId ?>"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-chat me-1"></i>Message
                                </a>
                            <?php endif; ?>
                            <?php if ($isPoster && $c['status'] === 'submitted'): ?>
                                <a href="confirm_job.php?job_id=<?= $jobId ?>"
                                   class="btn btn-sm btn-success">
                                    <i class="bi bi-check-circle me-1"></i>Confirm Complete
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($confirmations)): ?>
                        <p class="text-muted small mb-0">Workers who complete this job will appear here with their proof and profile info.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ════ BOOST INFO ════ -->
            <?php if (($job['boost_amount'] ?? 0) > 0): ?>
            <div class="card mb-4 border-warning">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-lightning-fill text-warning fs-3"></i>
                    <div>
                        <div class="fw-bold">Featured Listing</div>
                        <div class="text-muted small">
                            This job has been boosted with a $<?= number_format($job['boost_amount'], 2) ?> promotion,
                            giving it priority placement at the top of search results.
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- ════════════════ RIGHT: POSTER INFO ════════════════ -->
        <div class="col-lg-5">
            <div style="position:sticky;top:80px;">

                <!-- Poster profile card -->
                <div class="card mb-3">
                    <div class="card-header bg-light py-2">
                        <strong class="small"><i class="bi bi-person-circle me-1 text-primary"></i>Posted by</strong>
                    </div>
                    <div class="card-body text-center py-3">
                        <img src="<?= $posterImg ?>" class="rounded-circle mb-2"
                             style="width:64px;height:64px;object-fit:cover;border:3px solid #dee2e6;">
                        <div class="fw-bold"><?= htmlspecialchars($posterFullName) ?></div>
                        <div class="text-muted small mb-1">@<?= htmlspecialchars($job['poster_username']) ?></div>
                        <span class="badge bg-primary mb-2"><?= ucfirst($job['poster_role']) ?></span>

                        <?php if (!empty($job['poster_bio'])): ?>
                        <p class="text-muted small mb-2">
                            <?= htmlspecialchars(substr($job['poster_bio'], 0, 120)) ?><?= strlen($job['poster_bio']) > 120 ? '…' : '' ?>
                        </p>
                        <?php endif; ?>

                        <?php if ($isLoggedIn && !$isPoster): ?>
                        <a href="chat.php?user_id=<?= $job['poster_id'] ?>&job_id=<?= $jobId ?>"
                           class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-chat me-1"></i>Message Poster
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ad sidebar -->
                <div class="mb-3">
                    <?php renderAd('sidebar', $pdo); ?>
                </div>

                <!-- Quick stats card -->
                <div class="card">
                    <div class="card-header bg-light py-2">
                        <strong class="small"><i class="bi bi-bar-chart me-1 text-primary"></i>Job Summary</strong>
                    </div>
                    <div class="card-body py-2">
                        <table class="table table-sm table-borderless mb-0 small">
                            <tr>
                                <td class="text-muted">Category</td>
                                <td class="fw-semibold"><?= ucfirst($job['category']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Pay</td>
                                <td class="fw-semibold text-success">
                                    <?= ($job['pay'] ?? 0) > 0
                                        ? '$' . number_format($job['pay'], 2) . ($job['pay_type'] === 'hourly' ? '/hr' : '')
                                        : 'Unpaid' ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status</td>
                                <td>
                                    <span class="badge bg-<?= $job['is_active'] ? 'success' : 'secondary' ?>">
                                        <?= $job['is_active'] ? 'Active' : 'Closed' ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Boost</td>
                                <td>
                                    <?php if (($job['boost_amount'] ?? 0) > 0): ?>
                                        <span class="text-warning fw-semibold">
                                            <i class="bi bi-lightning-fill"></i>
                                            $<?= number_format($job['boost_amount'], 2) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">None</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Confirmation</td>
                                <td>
                                    <?php [$c, $ic, $lb] = statusInfo($overallStatus === 'none' ? 'pending' : $overallStatus); ?>
                                    <span class="badge bg-<?= $c ?>">
                                        <i class="bi bi-<?= $ic ?> me-1"></i><?= $lb ?>
                                    </span>
                                </td>
                            </tr>
                            <?php if (!empty($confirmations)): ?>
                            <tr>
                                <td class="text-muted">Applicants</td>
                                <td class="fw-semibold"><?= count($confirmations) ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
