<?php
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/auth.php';
}
startSecureSession();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="index.php">JobBridge</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="jobs.php">Jobs</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="volunteer.php">Volunteer</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="internship.php">Internships</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="resource-hub.php">Resources</a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="saved-jobs.php">Saved Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">Messages</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <?php if (getCurrentUserRole() === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">Admin</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
