<?php
// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php'; 

requireLogin();

// Initialize search variables
$search_title = $_GET['title'] ?? '';  
$search_zip = $_GET['zip'] ?? '';
$search_category = $_GET['category'] ?? 'All';
$search_min_pay = $_GET['min_pay'] ?? '';

// Array to hold job listings
$jobs = [];
$categories = ['company','odd','volunteer','internship']; // ENUM values
$error_message = '';

try {
    $pdo = getDBConnection();

    // --- Handle Job Search ---
    $sql = "SELECT id, title, description, category, pay, zip_code, location_details, date_posted, date_needed
            FROM jobs 
            WHERE is_active = 1"; 
    $params = [];

    if (!empty($search_title)) {
        $sql .= " AND title LIKE ?";
        $params[] = '%' . $search_title . '%';
    }

    if (!empty($search_zip)) {
        $sql .= " AND zip_code LIKE ?";
        $params[] = $search_zip . '%';
    }

    if ($search_category !== 'All' && in_array($search_category, $categories)) {
        $sql .= " AND category = ?";
        $params[] = $search_category;
    }

    if (!empty($search_min_pay) && is_numeric($search_min_pay)) {
        $sql .= " AND pay >= ?";
        $params[] = $search_min_pay;
    }

    $sql .= " ORDER BY date_posted DESC LIMIT 50";

    $stmt_jobs = $pdo->prepare($sql);
    $stmt_jobs->execute($params);
    $jobs = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);

    // --- Get saved jobs for this user ---
    $stmt = $pdo->prepare(
        "SELECT job_id FROM saved_jobs WHERE user_id = ?"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $savedJobIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $error_message = "An error occurred while fetching data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; margin: 0; background-color: #f4f4f4; }
        .header-bar { background-color: #007bff; color: white; padding: 20px 50px; text-align: center; }
        .header-bar h1 { margin-top: 0; font-size: 2.5em; }
        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .search-section { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .search-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .search-form input, .search-form select { padding: 10px; border: 1px solid #ccc; border-radius: 4px; flex-grow: 1; }
        .search-form button { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .job-listing { background-color: white; border: 1px solid #ddd; padding: 20px; margin-bottom: 15px; border-radius: 8px; transition: box-shadow 0.3s; }
        .job-listing:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .job-listing h3 { margin-top: 0; color: #007bff; }
        .badge-category { background-color: #6c757d; color: white; padding: 5px 10px; border-radius: 12px; font-size: 0.8em; }
        .bookmark {
            cursor: pointer;
            font-size: 22px;
            color: #ccc;
        }
        .bookmark.saved {
            color: #555;
        }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>
    
<div class="header-bar">
    <h1>Welcome to JobBridge</h1>
    <p>Your student-friendly platform to find jobs, internships, odd jobs, and volunteer work!</p>
</div>

<div class="container">
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="search-section">
        <h2>Search Jobs</h2>
        <form action="jobs.php" method="GET" class="search-form">
            <input type="text" name="title" placeholder="Job title or keyword" value="<?= htmlspecialchars($search_title) ?>">
            <input type="text" name="zip" placeholder="ZIP code" value="<?= htmlspecialchars($search_zip) ?>" style="width: 150px;">
            
            <select name="category" style="width: 200px;">
                <option value="All">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category) ?>" 
                        <?= ($search_category === $category) ? 'selected' : '' ?>>
                        <?= ucfirst($category) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="min_pay" placeholder="Min Pay" value="<?= htmlspecialchars($search_min_pay) ?>" style="width: 100px;">
            <button type="submit" name="search">Search</button>
        </form>
    </div>

    <?php if (!empty($jobs)): ?>
    <div style="margin-top: 30px;">
        <h2>Latest Jobs</h2>
        <?php foreach ($jobs as $job): ?>
            <div class="job-listing">
                <div class="d-flex justify-content-between align-items-start">
                    <h3><?= htmlspecialchars($job['title']) ?></h3>

                    <i
                        class="bi <?= in_array($job['id'], $savedJobIds) ? 'bi-bookmark-fill saved' : 'bi-bookmark' ?> bookmark"
                        data-job-id="<?= $job['id'] ?>"
                        title="<?= in_array($job['id'], $savedJobIds) ? 'Remove from saved' : 'Save job' ?>"
                    ></i>
                </div>

                <p class="mb-2 text-muted">
                    <small>Posted: <?= htmlspecialchars($job['date_posted']) ?></small>
                    <?php if(!empty($job['date_needed'])): ?>
                         | <small><strong>Needed by:</strong> <?= htmlspecialchars($job['date_needed']) ?></small>
                    <?php endif; ?>
                </p>

                <p><strong>Pay:</strong> 
                    <?php if ($job['pay'] > 0): ?>
                        $<?= htmlspecialchars(number_format($job['pay'], 2)) ?>
                    <?php else: ?>
                        <span class="text-muted">Unpaid / Volunteer</span>
                    <?php endif; ?>
                </p>
                
                <p><strong>Location:</strong> <?= htmlspecialchars($job['location_details']) ?> (ZIP: <?= htmlspecialchars($job['zip_code']) ?>)</p>
                
                <hr>
                <p><?= nl2br(htmlspecialchars($job['description'])) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <?php elseif (!empty($_GET) && empty($jobs) && empty($error_message)): ?>
    <div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border-radius: 4px; text-align:center;">
        <p>No jobs found matching your criteria.</p>
    </div>
    <?php endif; ?>

</div>

<script>
document.querySelectorAll('.bookmark').forEach(icon => {
    icon.addEventListener('click', function () {
        const jobId = this.dataset.jobId;

        fetch('saved_job.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'job_id=' + jobId
        })
        .then(res => res.json())
        .then(data => {
            if (data.error === 'inactive') {
                alert('This job is no longer active and cannot be saved.');
                return;
            }

            if (data.saved) {
                this.classList.remove('bi-bookmark');
                this.classList.add('bi-bookmark-fill', 'saved');
                this.title = 'Remove from saved';
            } else {
                this.classList.remove('bi-bookmark-fill', 'saved');
                this.classList.add('bi-bookmark');
                this.title = 'Save job';
            }
        });
    });
});
</script>

</body>
</html>
