<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin(); // optional if only logged-in users can see

// Search variables
$search_title = $_GET['title'] ?? '';
$search_zip = $_GET['zip'] ?? '';
$search_min_pay = $_GET['min_pay'] ?? '';

$volunteers = [];
$error_message = '';

try {
    $pdo = getDBConnection();

    // Base query for volunteers
    $sql = "SELECT id, title, category, pay, zip_code, location_details, date_posted
            FROM jobs
            WHERE is_active = 1 AND category = 'volunteer'";
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
    $volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Error fetching volunteer opportunities: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Opportunities - JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
      body { font-family: sans-serif; margin: 0; background-color: #f4f4f4; }
      .header-bar { background-color: #007bff; color: white; padding: 20px 50px; text-align: center; } /* Blue header like jobs.php */
      .header-bar h1 { margin-top: 0; font-size: 2.5em; }
      .header-bar p { margin-bottom: 0; }
      .container { max-width: 1100px; margin: 0 auto; padding: 20px; }
      .search-section { background-color: white; padding: 20px 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px; }
      .search-form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
      .search-form input { padding: 10px; border: 1px solid #ccc; border-radius: 4px; flex-grow: 1; }
      .search-form button { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; } /* Blue button */
      .volunteer-listing { background-color: white; border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 4px; } /* Green listing for testing */
      .volunteer-listing h3 { margin-top: 0; color: #28a745; }
  </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <div class="header-bar">
        <h1>Volunteer Opportunities</h1>
        <p>Find ways to give back to your community and gain valuable experience!</p>
    </div>

    <div class="container">

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="search-section">
            <h2>Search Volunteer Opportunities</h2>
            <form action="volunteer.php" method="GET" class="search-form">
                <input type="text" name="title" placeholder="Title or keyword" value="<?= htmlspecialchars($search_title) ?>">
                <input type="text" name="zip" placeholder="ZIP code" value="<?= htmlspecialchars($search_zip) ?>" style="width: 150px;">
                <input type="text" name="min_pay" placeholder="Min Pay" value="<?= htmlspecialchars($search_min_pay) ?>" style="width: 100px;">
                <button type="submit" name="search">Search</button>
            </form>
        </div>

        <?php if (!empty($volunteers)): ?>
            <?php foreach ($volunteers as $volunteer): ?>
                <div class="volunteer-listing">
                    <h3><?= htmlspecialchars($volunteer['title']) ?></h3>
                    <p><strong>Location:</strong> <?= htmlspecialchars($volunteer['location_details']) ?>, ZIP: <?= htmlspecialchars($volunteer['zip_code']) ?></p>
                    <p><strong>Date Posted:</strong> <?= htmlspecialchars($volunteer['date_posted']) ?></p>
                    <p><strong>Pay:</strong> <?= $volunteer['pay'] ? '$' . htmlspecialchars(number_format($volunteer['pay'], 2)) : 'Unpaid' ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="padding: 15px; background-color: #e0ffe0; border: 1px solid #28a745; border-radius: 4px;">
                <p>No volunteer opportunities found matching your search.</p>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
