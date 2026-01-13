<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();

$db = getDBConnection();

// Fetch some recent jobs (limit 6)
$stmt = $db->prepare("SELECT j.id, j.title, j.category, j.pay, j.zip_code, j.date_posted, u.username AS poster 
                      FROM jobs j
                      JOIN users u ON j.poster_user_id = u.id
                      WHERE j.is_active = 1
                      ORDER BY j.date_posted DESC
                      LIMIT 6");
$stmt->execute();
$recentJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JobBridge - Find Jobs for Students</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <div class="bg-primary text-white text-center py-5 mb-5">
        <h1>Welcome to JobBridge</h1>
        <p>Your student-friendly platform to find jobs, internships, odd jobs, and volunteer work!</p>
        <?php if (!isLoggedIn()): ?>
            <a href="register.php" class="btn btn-light btn-lg me-2">Register</a>
            <a href="login.php" class="btn btn-outline-light btn-lg">Login</a>
        <?php endif; ?>
    </div>

    <div class="container">
        <!-- Search Jobs -->
        <div class="mb-5">
            <h3 class="mb-3">Search Jobs</h3>
            <form action="jobs.php" method="GET" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="keyword" class="form-control" placeholder="Job title or keyword">
                </div>
                <div class="col-md-2">
                    <input type="text" name="zip" class="form-control" placeholder="ZIP code">
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <option value="company">Company Jobs</option>
                        <option value="odd">Odd Jobs</option>
                        <option value="volunteer">Volunteer</option>
                        <option value="internship">Internship</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" name="pay_min" class="form-control" placeholder="Min Pay">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>

        <!-- Featured Categories -->
        <div class="mb-5">
            <h3 class="mb-3">Featured Categories</h3>
            <div class="row g-3">
                <div class="col-md-3">
                    <a href="jobs.php?category=company" class="text-decoration-none">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Company Jobs</h5>
                                <p class="card-text">Find local company and store jobs.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="jobs.php?category=odd" class="text-decoration-none">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Odd Jobs</h5>
