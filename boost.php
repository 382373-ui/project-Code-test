<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$db     = getDBConnection();
$userId = getCurrentUserId();
$jobId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("SELECT * FROM jobs WHERE id = ? AND poster_user_id = ?");
$stmt->execute([$jobId, $userId]);
$job = $stmt->fetch();

if (!$job) {
    die("Job not found or you don't own this job.");
}

$success       = isset($_GET['success']) && $_GET['success'] == 1;
$hasActiveBoost = ($job['boost_amount'] >= 2.00);

// Fetch boost history for this job
$boostHistory = [];
try {
    $bStmt = $db->prepare("SELECT amount, duration, expiry_date, created_at FROM job_boosts WHERE job_id = ? ORDER BY created_at DESC LIMIT 5");
    $bStmt->execute([$jobId]);
    $boostHistory = $bStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentAmount = 0;

    if (!$hasActiveBoost && isset($_POST['duration'])) {
        $duration = $_POST['duration'];
        switch ($duration) {
            case '1day':   $paymentAmount = 2.00;  break;
            case '1week':  $paymentAmount = 5.00;  break;
            case '1month': $paymentAmount = 15.00; break;
        }
    } elseif ($hasActiveBoost && isset($_POST['extra_amount'])) {
        $paymentAmount = floatval($_POST['extra_amount']);
    }

    if ($paymentAmount > 0) {
        $desc = urlencode('Boost: ' . $job['title']);
        header("Location: checkout.php?id=$jobId&amount=$paymentAmount&desc=$desc");
        exit;
    }
}

$plans = [
    ['value' => '1day',   'label' => '1 Day',   'price' => 2.00,  'badge' => 'secondary', 'desc' => 'Quick visibility push for 24 hours'],
    ['value' => '1week',  'label' => '1 Week',  'price' => 5.00,  'badge' => 'primary',   'desc' => 'Top placement for a full week — most popular'],
    ['value' => '1month', 'label' => '1 Month', 'price' => 15.00, 'badge' => 'success',   'desc' => 'Maximum exposure for 30 days'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boost Job – JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
    <style>
        .boost-card { border: 2px solid transparent; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
        .boost-card:hover { border-color: #1976d2; box-shadow: 0 0 0 3px rgba(25,118,210,.15); }
        .boost-card.selected { border-color: #1976d2; box-shadow: 0 0 0 3px rgba(25,118,210,.2); }
        .boost-card input[type=radio] { display: none; }
    </style>
</head>
<body class="bg-light">
<?php include 'includes/header.php'; ?>

<div class="container mt-5" style="max-width:640px;">

    <?php if ($success): ?>
    <!-- SUCCESS STATE -->
    <div class="card border-success shadow text-center">
        <div class="card-body py-5">
            <div class="mb-3"><i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i></div>
            <h3 class="text-success">Boost Activated!</h3>
            <p class="text-muted">Your job "<strong><?= htmlspecialchars($job['title']) ?></strong>" is now featured at the top of search results.</p>
            <hr>
            <div class="d-flex gap-2 justify-content-center">
                <a href="profile.php" class="btn btn-primary"><i class="bi bi-person me-1"></i>My Profile</a>
                <a href="jobs.php" class="btn btn-outline-primary"><i class="bi bi-briefcase me-1"></i>Browse Jobs</a>
            </div>
        </div>
    </div>

    <?php elseif ($hasActiveBoost): ?>
    <!-- ALREADY BOOSTED — add more power -->
    <div class="card shadow">
        <div class="card-header bg-success text-white text-center py-3">
            <i class="bi bi-rocket-takeoff-fill me-2"></i><strong>Boost Active</strong>
        </div>
        <div class="card-body">
            <h5 class="text-center mb-1"><?= htmlspecialchars($job['title']) ?></h5>
            <div class="text-center mb-3">
                <span class="badge bg-success fs-6">Featured</span>
                <div class="text-muted small mt-1">Current rank power: <strong>$<?= number_format($job['boost_amount'], 2) ?></strong></div>
            </div>

            <div class="alert alert-info small mb-3">
                <i class="bi bi-info-circle me-1"></i>
                Boosted jobs appear at the top of all job listings. The higher your total boost amount, the higher your job ranks among other boosted listings.
            </div>

            <form method="POST">
                <label class="form-label fw-semibold">Add More Boost Power ($)</label>
                <div class="input-group mb-1">
                    <span class="input-group-text">$</span>
                    <input type="number" name="extra_amount" step="0.01" min="0.01"
                           class="form-control form-control-lg"
                           placeholder="e.g. 5.00" required>
                </div>
                <small class="text-muted d-block mb-3">Any amount — added directly to your rank score.</small>
                <button type="submit" class="btn btn-success w-100 btn-lg">
                    <i class="bi bi-lightning-charge-fill me-1"></i>Go to Payment
                </button>
            </form>

            <?php if (!empty($boostHistory)): ?>
            <hr>
            <h6 class="text-muted mb-2">Boost History</h6>
            <ul class="list-group list-group-flush">
                <?php foreach ($boostHistory as $bh): ?>
                <li class="list-group-item d-flex justify-content-between px-0 small">
                    <span><?= htmlspecialchars($bh['duration']) ?> — $<?= number_format($bh['amount'], 2) ?></span>
                    <span class="text-muted"><?= date('M j, Y', strtotime($bh['created_at'])) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <div class="card-footer text-center">
            <a href="profile.php" class="text-muted text-decoration-none small">← Back to Profile</a>
        </div>
    </div>

    <?php else: ?>
    <!-- NEW BOOST — pick a plan -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white text-center py-3">
            <i class="bi bi-rocket-takeoff me-2"></i><strong>Boost Your Job</strong>
        </div>
        <div class="card-body">
            <h5 class="text-center mb-1"><?= htmlspecialchars($job['title']) ?></h5>
            <p class="text-center text-muted small mb-4">Boosted jobs appear <strong>at the top</strong> of all job listings and are marked <span class="badge bg-warning text-dark">Featured</span></p>

            <div class="alert alert-light border small mb-4">
                <i class="bi bi-info-circle me-1 text-primary"></i>
                This is a <strong>simulated payment</strong> for demonstration purposes. No real charge will be made.
            </div>

            <form method="POST" id="boostForm">
                <div class="row g-3 mb-4" id="planCards">
                    <?php foreach ($plans as $plan): ?>
                    <div class="col-12">
                        <label class="boost-card card p-3 mb-0 w-100" id="card-<?= $plan['value'] ?>">
                            <input type="radio" name="duration" value="<?= $plan['value'] ?>"
                                   <?= $plan['value'] === '1week' ? 'checked' : '' ?>>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= $plan['label'] ?> Boost</strong>
                                    <div class="text-muted small"><?= $plan['desc'] ?></div>
                                </div>
                                <span class="badge bg-<?= $plan['badge'] ?> fs-6 ms-3">$<?= number_format($plan['price'], 2) ?></span>
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    <i class="bi bi-credit-card me-1"></i>Continue to Payment
                </button>
            </form>
        </div>
        <div class="card-footer text-center">
            <a href="profile.php" class="text-muted text-decoration-none small">← Cancel and go back</a>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Highlight selected plan card
document.querySelectorAll('#planCards .boost-card').forEach(label => {
    const radio = label.querySelector('input[type=radio]');
    if (radio && radio.checked) label.classList.add('selected');

    label.addEventListener('click', () => {
        document.querySelectorAll('#planCards .boost-card').forEach(l => l.classList.remove('selected'));
        label.classList.add('selected');
    });
});
</script>
</body>
</html>
