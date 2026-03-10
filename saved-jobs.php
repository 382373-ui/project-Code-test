<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();
$pdo = getDBConnection();

/* ==========================
   AJAX: Remove saved job
   ========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['job_id'])) {
        $stmt = $pdo->prepare(
            "DELETE FROM saved_jobs
             WHERE user_id = ? AND job_id = ?"
        );
        $stmt->execute([$_SESSION['user_id'], $data['job_id']]);

        echo json_encode(['success' => true]);
        exit;
    }
}

/* ==========================
   Fetch saved jobs
   ========================== */
$stmt = $pdo->prepare(
    "SELECT j.id, j.title, j.description, j.category, j.pay,
            j.location_details, j.zip_code, j.date_posted
     FROM saved_jobs sj
     JOIN jobs j ON sj.job_id = j.id
     WHERE sj.user_id = ?
       AND j.is_active = 1
     ORDER BY sj.created_at DESC"
);
$stmt->execute([$_SESSION['user_id']]);
$savedJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Saved Jobs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { background-color: #f4f4f4; }
        .job-listing {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }
        .remove-btn {
            cursor: pointer;
            font-size: 1.4rem;
            color: #dc3545;
        }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <h2 class="mb-4">Saved Jobs</h2>

    <?php if (empty($savedJobs)): ?>
        <div class="alert alert-info">You have no saved jobs.</div>
    <?php endif; ?>

    <?php foreach ($savedJobs as $job): ?>
        <div class="job-listing">
            <div class="d-flex justify-content-between align-items-start">
                <h4><?= htmlspecialchars($job['title']) ?></h4>

                <i class="bi bi-bookmark-fill remove-btn"
                   data-job-id="<?= $job['id'] ?>"
                   title="Remove from saved"></i>
            </div>

            <p><?= htmlspecialchars($job['description']) ?></p>
            <small class="text-muted">
                <?= htmlspecialchars($job['location_details'] ?? 'Location not specified') ?>
 |
                <?php if (!empty($job['pay'])): ?>
                    $<?= htmlspecialchars(number_format((float)$job['pay'], 2)) ?>
                <?php else: ?>
                    <span class="text-muted"> Pay: Unpaid / Volunteer</span>
                <?php endif; ?>


            </small>
        </div>
    <?php endforeach; ?>
</div>

<script>
document.querySelectorAll('.remove-btn').forEach(icon => {
    icon.addEventListener('click', () => {
        fetch('saved-jobs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ job_id: icon.dataset.jobId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                icon.closest('.job-listing').remove();
            }
        });
    });
});
</script>

</body>
</html>
