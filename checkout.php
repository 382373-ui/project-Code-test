<?php
// Get variables from the URL, with fallbacks to prevent "Undefined index" warnings
$jobId  = $_GET['id'] ?? $_GET['job_id'] ?? 0;
$amount = $_GET['amount'] ?? 0;
$desc   = $_GET['desc'] ?? "Job Boost Service"; // Default description if missing

// Security: Ensure amount is a number
$amount = number_format((float)$amount, 2, '.', '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow mx-auto" style="max-width: 400px;">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Complete Your Payment</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Item: <?= htmlspecialchars($desc) ?></p>
                <h2 class="mb-4">$<?= $amount ?></h2>

                <form action="process_payment.php" method="POST">
                    <input type="hidden" name="job_id" value="<?= htmlspecialchars($jobId) ?>">
                    <input type="hidden" name="amount" value="<?= $amount ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Cardholder Name</label>
                        <input type="text" class="form-control" placeholder="John Doe" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Card Details</label>
                        <input type="text" class="form-control" placeholder="0000 0000 0000 0000" required>
                    </div>

                    <button type="submit" class="btn btn-success w-100 btn-lg">Confirm Payment</button>
                </form>
            </div>
            <div class="card-footer text-center">
                <a href="boost.php?id=<?= $jobId ?>" class="text-muted small text-decoration-none">← Go Back</a>
            </div>
        </div>
    </div>
</body>
</html>