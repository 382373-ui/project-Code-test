<?php
if (!function_exists('renderAd')) {

    function renderAd(string $placement, $db): void {
        $today = date('Y-m-d');

        $stmt = $db->prepare("
            SELECT id, title, image_url, click_url, placement
            FROM ads
            WHERE is_active = 1
              AND placement = ?
              AND (start_date IS NULL OR start_date <= ?)
              AND (end_date   IS NULL OR end_date   >= ?)
            ORDER BY RAND()
            LIMIT 1
        ");
        $stmt->execute([$placement, $today, $today]);
        $ad = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ad || empty($ad['image_url'])) return;

        // Increment impression counter
        $db->prepare("UPDATE ads SET impressions = impressions + 1 WHERE id = ?")
           ->execute([$ad['id']]);

        // Log impression revenue ($0.002) to monetization_log
        $db->prepare(
            "INSERT INTO monetization_log (user_id, type, amount, reference_id, description, created_at)
             VALUES (1, 'ad_impression', 0.002, ?, ?, NOW())"
        )->execute([$ad['id'], 'Ad impression: ' . $ad['title']]);

        // Render the ad
        $clickUrl  = htmlspecialchars('ad_click.php?id=' . $ad['id']);
        $imgSrc    = htmlspecialchars($ad['image_url']);
        $altText   = htmlspecialchars($ad['title']);

        if ($placement === 'banner') {
            echo <<<HTML
<div class="text-center py-2 bg-light border-bottom" style="background:#f8f9fc!important;">
    <a href="{$clickUrl}" target="_blank" rel="noopener" title="Advertisement">
        <img src="{$imgSrc}" alt="{$altText}"
             style="max-width:728px;width:100%;height:auto;border-radius:4px;"
             loading="lazy">
    </a>
    <div style="font-size:10px;color:#aaa;letter-spacing:.5px;">ADVERTISEMENT</div>
</div>
HTML;
        } elseif ($placement === 'sidebar') {
            echo <<<HTML
<div class="card mb-3 text-center border-0 shadow-sm">
    <div class="card-body p-2">
        <a href="{$clickUrl}" target="_blank" rel="noopener" title="Advertisement">
            <img src="{$imgSrc}" alt="{$altText}"
                 style="max-width:300px;width:100%;height:auto;border-radius:4px;"
                 loading="lazy">
        </a>
        <div style="font-size:10px;color:#aaa;letter-spacing:.5px;margin-top:2px;">ADVERTISEMENT</div>
    </div>
</div>
HTML;
        } elseif ($placement === 'in-feed') {
            echo <<<HTML
<div class="my-3 text-center">
    <a href="{$clickUrl}" target="_blank" rel="noopener" title="Advertisement">
        <img src="{$imgSrc}" alt="{$altText}"
             style="max-width:600px;width:100%;height:auto;border-radius:6px;border:1px solid #e0e0e0;"
             loading="lazy">
    </a>
    <div style="font-size:10px;color:#aaa;letter-spacing:.5px;margin-top:2px;">SPONSORED</div>
</div>
HTML;
        }
    }

}
