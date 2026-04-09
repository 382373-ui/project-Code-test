<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin(); // optional if only logged-in users can see

// Search variables
$search_title = $_GET['title'] ?? '';
$search_zip = $_GET['zip'] ?? '';
$search_min_pay = $_GET['min_pay'] ?? '';

$internships = [];
$error_message = '';

try {
    $pdo = getDBConnection();

    // Base query for internships
    $sql = "SELECT id, title, category, pay, zip_code, location_details, date_posted
            FROM jobs
            WHERE is_active = 1 AND category = 'internship'";
    $params = [];

    // Filters
    if (!empty($search_title)) {
        $sql .= " AND title ILIKE ?";
        $params[] = '%' . $search_title . '%';
    }

    if (!empty($search_zip)) {
        $sql .= " AND zip_code LIKE ?";
        $params[] = $search_zip . '%';
    }

    if (!empty($search_min_pay) && is_numeric($search_min_pay)) {
        $sql .= " AND pay >= ?";
        $params[] = $search_min_pay;
    }

    $sql .= " ORDER BY date_posted DESC LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $internships = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Error fetching internship opportunities: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Opportunities - JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; margin: 0; background-color: #f4f4f4; }
        .header-bar { background-color: #007bff; color: white; padding: 20px 50px; text-align: center; }
        .header-bar h1 { margin-top: 0; font-size: 2.5em; }
        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .search-section { background-color: white; padding: 20px 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .search-form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .search-form input { padding: 10px; border: 1px solid #ccc; border-radius: 4px; flex-grow: 1; }
        .search-form button { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .internship-listing { background-color: white; border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 4px; }
        .internship-listing h3 { margin-top: 0; color: #007bff; }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <div class="header-bar">
        <h1>Internship Opportunities</h1>
        <p>Explore internships to gain experience and boost your resume!</p>
    </div>

    <div class="container">

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="search-section">
            <h2>Search Internships</h2>
            <form action="internship.php" method="GET" class="search-form">
                <input type="text" name="title" placeholder="Title or keyword" value="<?= htmlspecialchars($search_title) ?>">
                <input type="text" name="zip" placeholder="ZIP code" value="<?= htmlspecialchars($search_zip) ?>" style="width: 150px;">
                <input type="text" name="min_pay" placeholder="Min Pay" value="<?= htmlspecialchars($search_min_pay) ?>" style="width: 100px;">
                <button type="submit" name="search">Search</button>
            </form>
        </div>

        <?php if (!empty($internships)): ?>
            <?php foreach ($internships as $internship): ?>
                <div class="internship-listing">
                    <h3><?= htmlspecialchars($internship['title']) ?></h3>
                    <p><strong>Location:</strong> <?= htmlspecialchars($internship['location_details']) ?>, ZIP: <?= htmlspecialchars($internship['zip_code']) ?></p>
                    <p><strong>Date Posted:</strong> <?= htmlspecialchars($internship['date_posted']) ?></p>
                    <p><strong>Pay:</strong> <?= $internship['pay'] ? '$' . htmlspecialchars(number_format($internship['pay'], 2)) : 'Unpaid' ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="padding: 15px; background-color: #e0e7ff; border: 1px solid #007bff; border-radius: 4px;">
                <p>No internships found matching your search criteria.</p>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
