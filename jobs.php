<?php
// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$isLoggedIn = isset($_SESSION['user_id']);
$pdo = getDBConnection();

/* =========================
   RATING SYSTEM (AJAX)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_job_id'])) {
    if (!$isLoggedIn) {
        http_response_code(403);
        echo json_encode(['error' => 'login_required']);
        exit;
    }

    $jobId = (int) $_POST['rate_job_id'];
    $rating = (int) $_POST['rating'];

    if ($rating < 1 || $rating > 5) {
        echo json_encode(['error' => 'invalid_rating']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO job_ratings (job_id, user_id, rating)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating)
    ");
    $stmt->execute([$jobId, $_SESSION['user_id'], $rating]);

    echo json_encode(['success' => true]);
    exit;
}

/* =========================
   BOOKMARK SYSTEM
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id'])) {
    if (!$isLoggedIn) {
        http_response_code(403);
        echo json_encode(['error' => 'login_required']);
        exit;
    }

    $jobId = (int) $_POST['job_id'];

    $stmt = $pdo->prepare("SELECT is_active FROM jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job || (int)$job['is_active'] !== 1) {
        echo json_encode(['error' => 'inactive', 'disabled' => true]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT 1 FROM saved_jobs WHERE user_id = ? AND job_id = ?");
    $stmt->execute([$_SESSION['user_id'], $jobId]);
    $isSaved = $stmt->fetchColumn();

    if ($isSaved) {
        $stmt = $pdo->prepare("DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?");
        $stmt->execute([$_SESSION['user_id'], $jobId]);
        echo json_encode(['saved' => false]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO saved_jobs (user_id, job_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $jobId]);
        echo json_encode(['saved' => true]);
    }
    exit;
}

/* =========================
   SEARCH LOGIC + RATING FIX
========================= */
$search_title = $_GET['title'] ?? '';
$search_zip = $_GET['zip'] ?? '';
$search_category = $_GET['category'] ?? 'All';
$search_min_pay = $_GET['min_pay'] ?? '';

$jobs = [];
$categories = ['company','odd','volunteer','internship'];
$error_message = '';

try {

    $sql = "SELECT 
                j.id,
                j.title,
                j.description,
                j.category,
                j.pay,
                j.zip_code,
                j.is_active,
                j.location_details,
                j.date_posted,
                j.date_needed,
                j.poster_user_id,
                j.is_boosted,
                j.boost_amount,

                COALESCE(AVG(r.rating), 0) AS avg_rating,
                COUNT(r.rating) AS rating_count

            FROM jobs j
            LEFT JOIN job_ratings r ON j.id = r.job_id
            WHERE j.is_active = 1";

    $params = [];

    if (!empty($search_title)) {
        $sql .= " AND j.title LIKE ?";
        $params[] = '%' . $search_title . '%';
    }

    if (!empty($search_zip)) {
        $sql .= " AND j.zip_code LIKE ?";
        $params[] = $search_zip . '%';
    }

    if ($search_category !== 'All' && in_array($search_category, $categories)) {
        $sql .= " AND j.category = ?";
        $params[] = $search_category;
    }

    if (!empty($search_min_pay) && is_numeric($search_min_pay)) {
        $sql .= " AND j.pay >= ?";
        $params[] = $search_min_pay;
    }

    $sql .= " GROUP BY j.id ";
    $sql .= " ORDER BY j.is_boosted DESC, j.boost_amount DESC, j.pay DESC, j.date_posted DESC LIMIT 50";

    $stmt_jobs = $pdo->prepare($sql);
    $stmt_jobs->execute($params);
    $jobs = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);

    $savedJobIds = [];
    if ($isLoggedIn) {
        $stmt = $pdo->prepare("SELECT job_id FROM saved_jobs WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $savedJobIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

} catch (PDOException $e) {
    $error_message = "An error occurred: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JobBridge - Find Jobs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { background-color: #f4f4f4; }
        .header-bar { background-color: #007bff; color: white; padding: 40px 20px; text-align: center; }
        .container { max-width: 1100px; margin: 20px auto; }
        .search-section { background: white; padding: 25px; border-radius: 8px; }
        .job-listing { background: white; border: 1px solid #ddd; padding: 20px; margin-bottom: 15px; border-radius: 8px; position: relative; }
        .job-listing.boosted { border: 2px solid #007bff; background-color: #f0f7ff; }
        .boosted-badge { position: absolute; top: 15px; right: 15px; background: #007bff; color: white; font-size: 0.75rem; padding: 3px 10px; border-radius: 20px; }

        .bookmark { cursor: pointer; font-size: 1.4rem; color: #ccc; }
        .bookmark.saved { color: #007bff; }

        .rating-stars .star {
            cursor: pointer;
            font-size: 1.2rem;
            color: #ccc;
        }

        .rating-stars .star.active {
            color: #ffc107;
        }
    </style>
</head>

<body>

<?php include 'includes/header.php'; ?>

<div class="header-bar">
    <h1>Welcome to JobBridge</h1>
    <p>Find jobs, internships, and volunteer work!</p>
</div>

<div class="container">

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<div class="search-section">
<form method="GET" class="row g-2">
    <div class="col-md-4">
        <input type="text" name="title" class="form-control" placeholder="Job title..." value="<?= htmlspecialchars($search_title) ?>">
    </div>
    <div class="col-md-2">
        <input type="text" name="zip" class="form-control" placeholder="ZIP" value="<?= htmlspecialchars($search_zip) ?>">
    </div>
    <div class="col-md-3">
        <select name="category" class="form-select">
            <option value="All">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat ?>" <?= ($search_category === $cat) ? 'selected' : '' ?>>
                    <?= ucfirst($cat) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <input type="text" name="min_pay" class="form-control" placeholder="Min Pay $" value="<?= htmlspecialchars($search_min_pay) ?>">
    </div>
    <div class="col-md-1">
        <button class="btn btn-primary w-100">Search</button>
    </div>
</form>
</div>

<?php foreach ($jobs as $i => $job): ?>
    <div class="job-listing <?= $job['is_boosted'] ? 'boosted' : '' ?>">

        <?php if ($job['is_boosted']): ?>
            <span class="boosted-badge">FEATURED</span>
        <?php endif; ?>

        <div class="d-flex justify-content-between">
            <h3><?= htmlspecialchars($job['title']) ?></h3>

            <div>
                <?php if ($isLoggedIn): ?>
                    
                    <!-- ✅ ADDED MESSAGE SYSTEM (ONLY ADDITION) -->
                    <?php if ($job['poster_user_id'] != $_SESSION['user_id']): ?>
                        <a href="chat.php?user_id=<?= $job['poster_user_id'] ?>&job_id=<?= $job['id'] ?>" class="btn btn-sm btn-outline-primary me-2">
                            Message
                        </a>
                    <?php endif; ?>

                    <i class="bookmark bi <?= in_array($job['id'], $savedJobIds) ? 'bi-bookmark-fill saved' : 'bi-bookmark' ?>"
                       data-job-id="<?= $job['id'] ?>"></i>

                <?php endif; ?>
            </div>
        </div>

        <p class="text-muted small">
            Posted: <?= date('M j, Y', strtotime($job['date_posted'])) ?>
        </p>

        <p>
            <strong>Pay:</strong>
            <?= $job['pay'] > 0 ? '$' . number_format($job['pay'], 2) : 'Volunteer' ?>
        </p>

        <p><?= nl2br(htmlspecialchars($job['description'])) ?></p>

        <div class="mt-2">
            <strong>Rating:</strong>
            <span class="text-warning">
                <?= str_repeat("★", round($job['avg_rating'] ?? 0)) ?>
                <?= str_repeat("☆", 5 - round($job['avg_rating'] ?? 0)) ?>
            </span>

            <small class="text-muted">
                (<?= number_format($job['avg_rating'] ?? 0, 1) ?>/5 · <?= $job['rating_count'] ?? 0 ?> reviews)
            </small>
        </div>

        <?php if ($isLoggedIn): ?>
        <div class="rating-stars" data-job-id="<?= $job['id'] ?>">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="bi bi-star star" data-value="<?= $i ?>"></i>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

    </div>

    <?php if ($i % 5 === 4): ?>
        <div class="ad-slot text-center">Advertisement</div>
    <?php endif; ?>

<?php endforeach; ?>

</div>

<script>
document.querySelectorAll('.bookmark').forEach(icon => {
    icon.addEventListener('click', function () {
        fetch('jobs.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'job_id=' + this.dataset.jobId
        })
        .then(res => res.json())
        .then(data => {
            if (data.saved) {
                this.classList.add('saved');
                this.classList.replace('bi-bookmark', 'bi-bookmark-fill');
            } else {
                this.classList.remove('saved');
                this.classList.replace('bi-bookmark-fill', 'bi-bookmark');
            }
        });
    });
});
    document.querySelectorAll('.rating-stars').forEach(container => {
        const stars = container.querySelectorAll('.star');
        const jobId = container.dataset.jobId;

        stars.forEach(star => {
            star.addEventListener('click', function () {
                const rating = this.dataset.value;

                fetch('jobs.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `rate_job_id=${jobId}&rating=${rating}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        stars.forEach(s => {
                            s.classList.toggle('active', s.dataset.value <= rating);
                        });
                    }
                });
            });
        });
    });
</script>

</body>
</html>