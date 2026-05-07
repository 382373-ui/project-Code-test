<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$db  = getDBConnection();
$uid = getCurrentUserId();

$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
if (!$jobId) { header('Location: jobs.php'); exit; }

// Fetch job info
$stmt = $db->prepare("SELECT j.*, u.username AS poster_name FROM jobs j JOIN users u ON u.id = j.poster_user_id WHERE j.id = ?");
$stmt->execute([$jobId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) { die("Job not found."); }

$isPoster = ($uid === (int)$job['poster_user_id']);
$isWorker = !$isPoster;

// Fetch or create confirmation record
$stmt = $db->prepare("SELECT * FROM job_confirmations WHERE job_id = ? AND worker_user_id = ?");
$stmt->execute([$jobId, $isPoster ? $uid : $uid]);
$confirmation = $stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
$error   = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // WORKER: Submit proof
    if ($action === 'submit_proof' && $isWorker) {
        $proofText = trim($_POST['proof_text'] ?? '');
        $proofFile = null;

        if (!empty($_FILES['proof_file']['name'])) {
            $upload = uploadFile($_FILES['proof_file'], 'uploads/proofs/',
                ['image/jpeg','image/png','image/gif','application/pdf'], 5 * 1024 * 1024);
            if (isset($upload['error'])) {
                $error = $upload['error'];
            } else {
                $proofFile = $upload['path'];
            }
        }

        if (!$error) {
            if ($confirmation) {
                $stmt = $db->prepare("UPDATE job_confirmations SET proof_text = ?, proof_file_ref = ?, status = 'submitted' WHERE id = ?");
                $stmt->execute([$proofText, $proofFile ?: $confirmation['proof_file_ref'], $confirmation['id']]);
            } else {
                $stmt = $db->prepare("INSERT INTO job_confirmations (job_id, worker_user_id, proof_text, proof_file_ref, status) VALUES (?, ?, ?, ?, 'submitted')");
                $stmt->execute([$jobId, $uid, $proofText, $proofFile]);
            }
            // Notify poster via email
            $posterStmt = $db->prepare("SELECT email FROM users WHERE id = ?");
            $posterStmt->execute([$job['poster_user_id']]);
            $posterEmail = $posterStmt->fetchColumn();
            @mail($posterEmail, "JobBridge: Proof submitted for \"{$job['title']}\"",
                "A worker has submitted proof of completion for your job \"{$job['title']}\".\n\nLog in to review and confirm:\n" . (getenv('APP_URL') ?: 'http://localhost') . "/confirm_job.php?job_id=$jobId",
                "From: noreply@jobbridge.local");
            $message = 'Proof submitted. Waiting for poster confirmation.';
            $confirmation = $db->prepare("SELECT * FROM job_confirmations WHERE job_id = ? AND worker_user_id = ?")->execute([$jobId, $uid]);
            $stmt2 = $db->prepare("SELECT * FROM job_confirmations WHERE job_id = ? AND worker_user_id = ?");
            $stmt2->execute([$jobId, $uid]);
            $confirmation = $stmt2->fetch(PDO::FETCH_ASSOC);
        }
    }

    // POSTER: Confirm completion
    if ($action === 'poster_confirm' && $isPoster) {
        $workerId = (int)$_POST['worker_id'];
        $stmt = $db->prepare("UPDATE job_confirmations SET poster_confirmed = 1, status = 'confirmed', confirmed_at = NOW() WHERE job_id = ? AND worker_user_id = ?");
        $stmt->execute([$jobId, $workerId]);
        // Mark job as completed
        $db->prepare("UPDATE jobs SET is_active = 0 WHERE id = ?")->execute([$jobId]);
        // Notify worker
        $workerStmt = $db->prepare("SELECT email, username FROM users WHERE id = ?");
        $workerStmt->execute([$workerId]);
        $worker = $workerStmt->fetch(PDO::FETCH_ASSOC);
        if ($worker) {
            @mail($worker['email'], "JobBridge: Job \"{$job['title']}\" confirmed!",
                "Great news! The poster has confirmed your work on \"{$job['title']}\".\nThe job is now marked as complete.",
                "From: noreply@jobbridge.local");
        }
        $message = 'Job confirmed as complete!';
    }

    // EITHER: Dispute
    if ($action === 'dispute') {
        $reason = trim($_POST['reason'] ?? 'No reason provided.');
        $stmt = $db->prepare("UPDATE job_confirmations SET status = 'disputed' WHERE job_id = ?");
        $stmt->execute([$jobId]);
        $stmt = $db->prepare("INSERT INTO flags (item_type, item_id, reported_by, reason, status) VALUES ('job', ?, ?, ?, 'pending')");
        $stmt->execute([$jobId, $uid, $reason]);
        $message = 'Dispute filed. An admin will review shortly.';
    }
}

// Refresh confirmation
$stmt = $db->prepare("
    SELECT jc.*, u.username AS worker_name
    FROM job_confirmations jc
    JOIN users u ON u.id = jc.worker_user_id
    WHERE jc.job_id = ?
");
$stmt->execute([$jobId]);
$confirmations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$myConfirmation = null;
foreach ($confirmations as $c) {
    if ((int)$c['worker_user_id'] === $uid) { $myConfirmation = $c; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Confirmation – JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="container mt-4" style="max-width:760px;">
    <h3 class="mb-1">Job Confirmation</h3>
    <p class="text-muted mb-4"><?= htmlspecialchars($job['title']) ?></p>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- FLOW DIAGRAM -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="text-center">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-1" style="width:40px;height:40px;">1</div>
                    <small>Worker submits proof</small>
                </div>
                <div class="flex-grow-1 border-top border-2"></div>
                <div class="text-center">
                    <div class="rounded-circle bg-<?= ($myConfirmation && in_array($myConfirmation['status'],['confirmed','submitted'])) ? 'success' : 'secondary' ?> text-white d-flex align-items-center justify-content-center mx-auto mb-1" style="width:40px;height:40px;">2</div>
                    <small>Poster reviews</small>
                </div>
                <div class="flex-grow-1 border-top border-2"></div>
                <div class="text-center">
                    <div class="rounded-circle bg-<?= ($myConfirmation && $myConfirmation['status'] === 'confirmed') ? 'success' : 'secondary' ?> text-white d-flex align-items-center justify-content-center mx-auto mb-1" style="width:40px;height:40px;">3</div>
                    <small>Complete</small>
                </div>
            </div>
        </div>
    </div>

    <!-- WORKER VIEW: Submit Proof -->
    <?php if ($isWorker): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white"><strong>Submit Your Proof of Completion</strong></div>
        <div class="card-body">
            <?php if ($myConfirmation && $myConfirmation['status'] === 'confirmed'): ?>
                <div class="alert alert-success mb-0"><i class="bi bi-check-circle-fill me-2"></i>This job has been confirmed as complete!</div>
            <?php elseif ($myConfirmation && $myConfirmation['status'] === 'disputed'): ?>
                <div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-2"></i>A dispute is in progress. An admin will review.</div>
            <?php elseif ($myConfirmation && $myConfirmation['status'] === 'submitted'): ?>
                <div class="alert alert-info mb-0"><i class="bi bi-clock me-2"></i>Proof submitted. Waiting for poster to confirm.</div>
            <?php else: ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_proof">
                <div class="mb-3">
                    <label class="form-label">Description of Work Done</label>
                    <textarea name="proof_text" class="form-control" rows="4" placeholder="Describe what you completed..." required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Upload Photo/File Proof <small class="text-muted">(optional)</small></label>
                    <input type="file" name="proof_file" class="form-control" accept="image/*,application/pdf">
                </div>
                <button type="submit" class="btn btn-primary">Submit Proof</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- POSTER VIEW: Review Submissions -->
    <?php if ($isPoster): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white"><strong>Review Worker Submissions</strong></div>
        <div class="card-body">
            <?php if (empty($confirmations)): ?>
                <p class="text-muted">No workers have submitted proof yet.</p>
            <?php else: ?>
                <?php foreach ($confirmations as $c): ?>
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong><?= htmlspecialchars($c['worker_name']) ?></strong>
                        <span class="badge bg-<?= $c['status'] === 'confirmed' ? 'success' : ($c['status'] === 'disputed' ? 'danger' : ($c['status'] === 'submitted' ? 'primary' : 'secondary')) ?>">
                            <?= ucfirst($c['status']) ?>
                        </span>
                    </div>
                    <?php if ($c['proof_text']): ?>
                        <p><?= nl2br(htmlspecialchars($c['proof_text'])) ?></p>
                    <?php endif; ?>
                    <?php if ($c['proof_file_ref']): ?>
                        <a href="<?= htmlspecialchars($c['proof_file_ref']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary mb-2">
                            <i class="bi bi-paperclip"></i> View Attachment
                        </a>
                    <?php endif; ?>
                    <?php if ($c['status'] === 'submitted'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="poster_confirm">
                        <input type="hidden" name="worker_id" value="<?= $c['worker_user_id'] ?>">
                        <button class="btn btn-success btn-sm"><i class="bi bi-check-circle me-1"></i>Confirm Complete</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- DISPUTE SECTION -->
    <?php if ($myConfirmation && !in_array($myConfirmation['status'], ['confirmed','disputed'])): ?>
    <div class="card border-danger">
        <div class="card-header text-danger"><strong><i class="bi bi-exclamation-triangle me-1"></i>Report an Issue</strong></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="dispute">
                <div class="mb-3">
                    <label class="form-label">Describe the issue</label>
                    <textarea name="reason" class="form-control" rows="3" placeholder="Explain the problem..." required></textarea>
                </div>
                <button type="submit" class="btn btn-outline-danger btn-sm">File Dispute</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <a href="jobs.php" class="btn btn-link mt-3">&larr; Back to Jobs</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
