<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pdo = getDBConnection();
$myId = $_SESSION['user_id'];

// Get unique conversations based on User + Job combination
$stmt = $pdo->prepare("
    SELECT 
        u.id AS user_id,
        u.username,
        j.id AS job_id,
        j.title AS job_title,
        m.content AS last_message,
        m.created_at,
        (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0 AND job_id = m.job_id) AS unread
    FROM messages m
    JOIN users u ON (u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END)
    LEFT JOIN jobs j ON j.id = m.job_id
    WHERE (m.sender_id = ? OR m.receiver_id = ?)
    AND m.id IN (
        SELECT MAX(id) FROM messages 
        WHERE (sender_id = ? OR receiver_id = ?)
        GROUP BY CASE WHEN sender_id < receiver_id THEN sender_id ELSE receiver_id END, job_id
    )
    ORDER BY m.created_at DESC
");
$stmt->execute([$myId, $myId, $myId, $myId, $myId, $myId]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Messages | JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .inbox-container { max-width: 800px; margin: 20px auto; }
        .conv-card { 
            transition: 0.2s; 
            border-left: 4px solid transparent; 
            cursor: pointer;
        }
        .conv-card:hover { background: #f8f9fa; border-left: 4px solid #0d6efd; }
        .unread { background: #edf4ff !important; font-weight: bold; }
        .job-tag { font-size: 0.85rem; color: #6c757d; }
        .last-msg { font-size: 0.9rem; color: #555; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body class="bg-light">
<?php include 'includes/header.php'; ?>

<div class="container inbox-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Inbox</h3>
        <input id="searchBox" class="form-control w-50" placeholder="Filter by name..." onkeyup="filterChats()">
    </div>

    <div class="list-group shadow-sm" id="inboxArea">
        <?php foreach($conversations as $c): ?>
        <a href="chat.php?user_id=<?= $c['user_id'] ?>&job_id=<?= $c['job_id'] ?>" 
           class="list-group-item list-group-item-action conv-card <?= $c['unread'] > 0 ? 'unread' : '' ?>"
           data-name="<?= strtolower($c['username']) ?>">
            
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="h6 mb-0"><?= htmlspecialchars($c['username']) ?></span>
                    <?php if($c['job_title']): ?>
                        <span class="job-tag"> | <?= htmlspecialchars($c['job_title']) ?></span>
                    <?php endif; ?>
                </div>
                <small class="text-muted"><?= date('M j, g:i a', strtotime($c['created_at'])) ?></small>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mt-1">
                <div class="last-msg"><?= htmlspecialchars($c['last_message']) ?></div>
                <?php if($c['unread'] > 0): ?>
                    <span class="badge rounded-pill bg-primary"><?= $c['unread'] ?></span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
        
        <?php if(empty($conversations)): ?>
            <div class="p-5 text-center bg-white rounded">No messages yet.</div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterChats(){
    let q = document.getElementById("searchBox").value.toLowerCase();
    document.querySelectorAll('.conv-card').forEach(el => {
        el.style.display = el.dataset.name.includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>