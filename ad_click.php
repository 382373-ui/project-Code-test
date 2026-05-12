<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

$adId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($adId > 0) {
    $db = getDBConnection();

    // Fetch the ad
    $stmt = $db->prepare("SELECT id, click_url, title FROM ads WHERE id = ? AND is_active = 1");
    $stmt->execute([$adId]);
    $ad = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ad) {
        // Increment click counter on the ad row
        $db->prepare("UPDATE ads SET clicks = clicks + 1 WHERE id = ?")
           ->execute([$adId]);

        // Log $0.01 to monetization_log (user_id = 1 = admin/platform account)
        $db->prepare(
            "INSERT INTO monetization_log (user_id, type, amount, reference_id, description, created_at)
             VALUES (1, 'ad_impression', 0.01, ?, ?, NOW())"
        )->execute([$adId, 'Ad click: ' . $ad['title']]);

        // Redirect to the advertiser's URL
        $url = $ad['click_url'] ?: 'index.php';
        header("Location: " . $url);
        exit;
    }
}

// Fallback
header("Location: index.php");
exit;
