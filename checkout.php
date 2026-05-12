<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$jobId  = isset($_GET['id'])     ? (int)$_GET['id']         : 0;
$amount = isset($_GET['amount']) ? (float)$_GET['amount']   : 0;
$desc   = isset($_GET['desc'])   ? urldecode($_GET['desc'])  : 'Job Boost Service';

// Sanitise amount to 2dp
$amount = number_format($amount, 2, '.', '');

if ($amount <= 0 || $jobId <= 0) {
    header('Location: profile.php');
    exit;
}

$planLabel = '';
switch ((string)$amount) {
    case '2.00':  $planLabel = '1 Day Boost';   break;
    case '5.00':  $planLabel = '1 Week Boost';  break;
    case '15.00': $planLabel = '1 Month Boost'; break;
    default:      $planLabel = 'Boost Top-Up';  break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout – JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
    <style>
        .checkout-wrap { max-width: 480px; margin: 60px auto; }
        .card-icon { font-size: 1.4rem; color: #888; cursor: pointer; }
        .card-icon:hover { color: #1976d2; }
    </style>
</head>
<body class="bg-light">
<?php include 'includes/header.php'; ?>

<div class="checkout-wrap">

    <!-- Order summary -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold"><?= htmlspecialchars($desc) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($planLabel) ?></div>
                </div>
                <div class="fs-4 fw-bold text-primary">$<?= $amount ?></div>
            </div>
        </div>
    </div>

    <!-- Demo notice -->
    <div class="alert alert-warning small mb-3 py-2">
        <i class="bi bi-shield-exclamation me-1"></i>
        <strong>Demo mode:</strong> This is a simulated checkout. No real payment is processed.
    </div>

    <!-- Payment form -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
            <i class="bi bi-lock-fill"></i>
            <strong>Secure Payment</strong>
        </div>
        <div class="card-body">
            <form action="process_payment.php" method="POST" id="payForm" novalidate>
                <input type="hidden" name="job_id" value="<?= $jobId ?>">
                <input type="hidden" name="amount" value="<?= $amount ?>">

                <div class="mb-3">
                    <label class="form-label">Cardholder Name</label>
                    <input type="text" class="form-control" name="card_name"
                           placeholder="Jane Smith" required>
                </div>

                <div class="mb-3">
                    <label class="form-label d-flex justify-content-between">
                        Card Number
                        <span>
                            <i class="bi bi-credit-card-2-front card-icon" title="Visa / Mastercard"></i>
                        </span>
                    </label>
                    <input type="text" class="form-control" id="cardNum"
                           placeholder="0000 0000 0000 0000"
                           maxlength="19" autocomplete="cc-number" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Expiry</label>
                        <input type="text" class="form-control" id="cardExp"
                               placeholder="MM / YY" maxlength="7" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">CVV</label>
                        <input type="password" class="form-control" id="cardCvv"
                               placeholder="•••" maxlength="4" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-success w-100 btn-lg">
                    <i class="bi bi-check-circle me-1"></i>Confirm Payment — $<?= $amount ?>
                </button>
            </form>
        </div>
        <div class="card-footer text-center">
            <a href="boost.php?id=<?= $jobId ?>" class="text-muted small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Go back
            </a>
        </div>
    </div>

    <p class="text-center text-muted small mt-3">
        <i class="bi bi-lock me-1"></i>All data is for demo purposes only. Nothing is stored or charged.
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-format card number with spaces
document.getElementById('cardNum').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').substring(0, 16);
    this.value = v.replace(/(.{4})/g, '$1 ').trim();
});

// Auto-format expiry MM / YY
document.getElementById('cardExp').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').substring(0, 4);
    if (v.length >= 3) v = v.substring(0, 2) + ' / ' + v.substring(2);
    this.value = v;
});

// Basic validation before submit
document.getElementById('payForm').addEventListener('submit', function (e) {
    const name = this.card_name.value.trim();
    const num  = document.getElementById('cardNum').value.replace(/\s/g, '');
    const exp  = document.getElementById('cardExp').value;
    const cvv  = document.getElementById('cardCvv').value;

    if (!name || num.length < 16 || exp.length < 4 || cvv.length < 3) {
        e.preventDefault();
        alert('Please fill in all card details correctly before continuing.');
    }
});
</script>
</body>
</html>
