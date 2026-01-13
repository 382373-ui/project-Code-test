<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

requireLogin(); // optional if only logged-in users can see
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Resource Hub - JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; margin: 0; background-color: #f4f4f4; }
        .header-bar { background-color: #007bff; color: white; padding: 20px 50px; text-align: center; }
        .header-bar h1 { margin-top: 0; font-size: 2.5em; }
        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .resource-section { margin-bottom: 40px; }
        .resource-card { background-color: white; border: 1px solid #ddd; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .resource-card h3 { color: #007bff; margin-top: 0; }
        .resource-card p { margin-bottom: 10px; }
        .btn-resource { background-color: #007bff; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; }
        .btn-resource:hover { background-color: #0056b3; color: white; }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <div class="header-bar">
        <h1>Student Resource Hub</h1>
        <p>Access resume templates, interview tips, verification letters, and more!</p>
    </div>

    <div class="container">

        <!-- Resume Templates Section -->
        <div class="resource-section">
            <h2>Resume Templates</h2>
            <div class="resource-card">
                <h3>Basic Resume Template</h3>
                <p>A simple and clean template to help you create a professional resume quickly.</p>
                <a href="public/resources/resume-basic (1).pdf" class="btn-resource" download>Download</a>
            </div>
            <div class="resource-card">
                <h3>Creative Resume Template</h3>
                <p>A visually appealing template for students applying to creative positions.</p>
                <a href="public/resources/resume-creative.pdf" class="btn-resource" download>Download</a>
            </div>
        </div>

        <!-- Interview Tips Section -->
        <div class="resource-section">
            <h2>Interview Tips</h2>
            <div class="resource-card">
                <h3>Top 10 Interview Tips</h3>
                <p>Learn how to make a strong impression, answer common questions, and succeed in interviews.</p>
                <a href="public/resources/interview_tips (1).pdf" class="btn-resource" target="_blank">View PDF</a>
            </div>

        <!-- Verification Letters Section -->
        <div class="resource-section">
            <h2>Verification Letters</h2>
            <div class="resource-card">
                <h3>Employment Verification Letter Template</h3>
                <p>Template to help students request verification letters from previous employers or schools.</p>
                <a href="public/resources/verification-letter-community.pdf" class="btn-resource" download>Download</a>
            </div>
            <div class="resource-card">
                <h3>Volunteer Verification Letter Template</h3>
                <p>Template for confirming volunteer work for applications or resumes.</p>
                <a href="public/resources/verification-letter-work.pdf" class="btn-resource" download>Download</a>
            </div>
        </div>

    </div>

</body>
</html>
