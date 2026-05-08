<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];
$formData = [
    'username'   => '',
    'email'      => '',
    'first_name' => '',
    'last_name'  => '',
    'role'       => 'student',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['username']   = sanitizeInput($_POST['username']   ?? '');
    $formData['email']      = sanitizeInput($_POST['email']      ?? '');
    $formData['first_name'] = sanitizeInput($_POST['first_name'] ?? '');
    $formData['last_name']  = sanitizeInput($_POST['last_name']  ?? '');
    $formData['role']       = sanitizeInput($_POST['role']       ?? 'student');
    $password               = $_POST['password']         ?? '';
    $confirmPassword        = $_POST['confirm_password'] ?? '';

    if (empty($formData['username']) || empty($formData['email']) || empty($password) ||
        empty($formData['first_name']) || empty($formData['last_name'])) {
        $errors[] = 'All fields are required.';
    }

    if (!validateEmail($formData['email'])) {
        $errors[] = 'Invalid email address.';
    }

    $passwordValidation = validatePassword($password);
    if ($passwordValidation !== true) {
        $errors[] = $passwordValidation;
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $db   = getDBConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$formData['username'], $formData['email']]);

        if ($stmt->fetch()) {
            $errors[] = 'Username or email already exists.';
        } else {
            $hashedPassword = hashPassword($password);
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())");

            if ($stmt->execute([$formData['username'], $formData['email'], $hashedPassword, $formData['role']])) {
                $userId = $db->lastInsertId();
                $stmt   = $db->prepare("INSERT INTO profiles (user_id, first_name, last_name) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $formData['first_name'], $formData['last_name']]);
                redirect('login.php?registered=1');
                exit;
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Register for JobBridge</h3>
                    </div>
                    <div class="card-body">

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">

                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control <?= !empty($errors) && empty($formData['username']) ? 'is-invalid' : '' ?>"
                                       id="username" name="username"
                                       value="<?= htmlspecialchars($formData['username']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control"
                                       id="email" name="email"
                                       value="<?= htmlspecialchars($formData['email']) ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control"
                                           id="first_name" name="first_name"
                                           value="<?= htmlspecialchars($formData['first_name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control"
                                           id="last_name" name="last_name"
                                           value="<?= htmlspecialchars($formData['last_name']) ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="role" class="form-label">I am a:</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="student"  <?= $formData['role'] === 'student'   ? 'selected' : '' ?>>Student</option>
                                    <option value="employer" <?= $formData['role'] === 'employer'  ? 'selected' : '' ?>>Employer / Civilian</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <p class="text-muted small mb-1">Must be at least 8 characters with an uppercase letter, lowercase letter, number, and special character.</p>
                                <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Register</button>
                        </form>

                        <div class="mt-3 text-center">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
