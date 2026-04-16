<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$db = getDBConnection();
$userId = getCurrentUserId();
$jobId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$receipt = false;

// Verify ownership
$stmt = $db->prepare("SELECT * FROM jobs WHERE id = ? AND poster_user_id = ?");
$stmt->execute([$jobId, $userId]);
$job = $stmt->fetch();

if (!$job) { die("Job not found."); }

// Check if they have already paid a base fee
$hasInitialBoost = ($job['boost_amount'] >= 2.00);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentAmount = 0;
    $durationLabel = "";

    if (!$hasInitialBoost && isset($_POST['duration'])) {
        $duration = $_POST['duration'];
        switch ($duration) {
            case '1day':  $paymentAmount = 2.00;  $durationLabel = "1 Day"; break;
            case '1week': $paymentAmount = 5.00;  $durationLabel = "1 Week"; break;
            case '1month':$paymentAmount = 15.00; $durationLabel = "1 Month"; break;
        }
    } elseif ($hasInitialBoost && isset($_POST['extra_amount'])) {
        $paymentAmount = floatval($_POST['extra_amount']);
        $durationLabel = "Extra Rank Bonus";
    }

    if ($paymentAmount > 0) {
        $newTotal = $job['boost_amount'] + $paymentAmount;
        
        // IMPORTANT: We set is_boosted = 1 here so it shows up in the priority list
        $update = $db->prepare("UPDATE jobs SET boost_amount = ?, is_boosted = 1 WHERE id = ?");
        $update->execute([$newTotal, $jobId]);
        
        $receipt = [
            'type' => $durationLabel,
            'paid' => $paymentAmount,
            'total' => $newTotal,
            'title' => $job['title']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Boost Job - JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'includes/header.php'; ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                
                <?php if ($receipt): ?>
                    <div class="card border-success shadow text-center">
                        <div class="card-body">
                            <h2 class="text-success">Success! 🎉</h2>
                            <p class="lead">Payment for <strong><?= $receipt['type'] ?></strong> received.</p>
                            <div class="p-4 bg-light rounded mb-3">
                                <p>Paid: $<?= number_format($receipt['paid'], 2) ?></p>
                                <h4 class="text-primary">Total Rank Power: $<?= number_format($receipt['total'], 2) ?></h4>
                            </div>
                            <a href="profile.php" class="btn btn-primary">Return to Profile</a>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white text-center">
                            <h4 class="mb-0">Boost: <?= htmlspecialchars($job['title']) ?></h4>
                        </div>
                        <div class="card-body">
                            
                            <?php if (!$hasInitialBoost): ?>
                                <h5 class="mb-3 text-center">Select your Boost Duration</h5>
                                <form method="POST">
                                    <div class="list-group mb-3">
                                        <label class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <input class="form-check-input me-1" type="radio" name="duration" value="1day" required>
                                                1 Day Boost
                                            </div>
                                            <span class="badge bg-secondary rounded-pill">$2.00</span>
                                        </label>
                                        <label class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <input class="form-check-input me-1" type="radio" name="duration" value="1week" checked>
                                                1 Week Boost
                                            </div>
                                            <span class="badge bg-primary rounded-pill">$5.00</span>
                                        </label>
                                        <label class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <input class="form-check-input me-1" type="radio" name="duration" value="1month">
                                                1 Month Boost
                                            </div>
                                            <span class="badge bg-success rounded-pill">$15.00</span>
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 btn-lg">Pay & Activate Boost</button>
                                </form>

                            <?php else: ?>
                                <div class="text-center">
                                    <span class="badge bg-success mb-2">Boost Active</span>
                                    <h5>Current Rank Power: $<?= number_format($job['boost_amount'], 2) ?></h5>
                                    <p class="small text-muted">Others can outbid you. Add an <strong>Extra Bonus</strong> to stay at the top.</p>
                                    <hr>
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">Add Extra Bonus Amount ($)</label>
                                            <input type="number" name="extra_amount" step="0.01" min="0.01" class="form-control form-control-lg text-center" placeholder="1.00" required>
                                        </div>
                                        <button type="submit" class="btn btn-success w-100">Pay Extra Bonus</button>
                                    </form>
                                </div>
                            <?php endif; ?>

                        </div>
                        <div class="card-footer text-center">
                            <a href="profile.php" class="text-muted text-decoration-none small">Cancel and go back</a>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>