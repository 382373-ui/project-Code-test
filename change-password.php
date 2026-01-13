<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$db = getDBConnection();
$userId = getCurrentUserId();

$errors = [];

/* Handle form submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($current) || empty($new) || empty($confirm)) {
        $errors[] = "All fields are required.";
    } elseif ($new !== $confirm) {
        $errors[] = "New password and confirm password do not match.";
    } else {
        // Fetch current hashed password
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['password_hash'])) {
            $errors[] = "Current password is incorrect.";
        } else {
            // Update password
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hashed, $userId]);

            // Redirect immediately to profile page
            header("Location: profile.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; background-color: #f4f4f4; }
        .header-bar { background-color: #007bff; color: white; padding: 20px 50px; text-align: center; }
        .header-bar h1 { margin: 0; font-size: 2em; }
        .container { max-width: 600px; margin: 40px auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .btn-primary { background-color: #007bff; border: none; }
    </style>
</head>
<body>

<div class="header-bar">
    <h1>Change Password</h1>
</div>

<div class="container">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e) echo htmlspecialchars($e) . "<br>"; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Change Password</button>
        <a href="profile.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

</body>
</html>
