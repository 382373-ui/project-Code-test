<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$pdo = getDBConnection();
$myId = $_SESSION['user_id'];
$otherId = (int)($_GET['user_id'] ?? 0);
$jobId = (int)($_GET['job_id'] ?? 0);

// Get Other User Info
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$otherId]);
$otherUser = $stmt->fetch();

// Get Job Info
$jobTitle = "";
if($jobId > 0) {
    $stmt = $pdo->prepare("SELECT title FROM jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();
    $jobTitle = $job['title'] ?? "";
}

// Mark as read
$stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND job_id = ?");
$stmt->execute([$otherId, $myId, $jobId]);

// Fetch history
$stmt = $pdo->prepare("SELECT * FROM messages WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) AND job_id = ? ORDER BY created_at ASC");
$stmt->execute([$myId, $otherId, $otherId, $myId, $jobId]);
$messages = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat with <?= htmlspecialchars($otherUser['username']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #chatArea { height: 450px; overflow-y: auto; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px 8px 0 0; }
        .bubble { max-width: 75%; padding: 10px 15px; border-radius: 20px; margin-bottom: 10px; position: relative; clear: both; }
        .me { background: #007bff; color: white; float: right; border-bottom-right-radius: 2px; }
        .them { background: #f1f0f0; color: #333; float: left; border-bottom-left-radius: 2px; }
        .time { font-size: 0.7rem; opacity: 0.7; margin-top: 5px; display: block; }
        .chat-footer { background: #fff; padding: 15px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px; }
    </style>
</head>
<body class="bg-light">
<?php include 'includes/header.php'; ?>

<div class="container mt-4" style="max-width: 700px;">
    <div class="d-flex align-items-center mb-3">
        <a href="messages.php" class="btn btn-sm btn-outline-secondary me-3">&larr; Back</a>
        <div>
            <h5 class="mb-0"><?= htmlspecialchars($otherUser['username']) ?></h5>
            <small class="text-primary"><?= htmlspecialchars($jobTitle) ?></small>
        </div>
    </div>

    <div id="chatArea">
        <?php foreach($messages as $m): ?>
            <div class="bubble <?= $m['sender_id'] == $myId ? 'me' : 'them' ?>">
                <?= nl2br(htmlspecialchars($m['content'])) ?>
                <span class="time"><?= date('g:i a', strtotime($m['created_at'])) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="chat-footer">
        <div class="input-group">
            <input type="text" id="msgInput" class="form-control" placeholder="Write a message..." onkeypress="checkEnter(event)">
            <button class="btn btn-primary" onclick="sendMessage()">Send</button>
        </div>
    </div>
</div>

<script>
    const chatArea = document.getElementById('chatArea');
    chatArea.scrollTop = chatArea.scrollHeight;

    function checkEnter(e) { if(e.key === 'Enter') sendMessage(); }

    function sendMessage() {
        const input = document.getElementById('msgInput');
        const text = input.value.trim(); // .trim() removes whitespace/newlines 

        // CRITICAL: Stop if the message is empty
        if (text === "") {
            return; 
        }

        const formData = new FormData();
        formData.append('receiver_id', '<?= $otherId ?>');
        formData.append('job_id', '<?= $jobId ?>');
        formData.append('content', text);

        fetch('send_message.php', { 
            method: 'POST', 
            body: formData 
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                // Append the new bubble to the chat area
                const chatArea = document.getElementById('chatArea');
                chatArea.innerHTML += `
                    <div class="bubble me">
                        ${text.replace(/\n/g, '<br>')}
                        <span class="time">Just now</span>
                    </div>`;

                // CLEAR the input so it can't be sent again accidentally
                input.value = ''; 

                // Scroll to the bottom [cite: 136]
                chatArea.scrollTop = chatArea.scrollHeight;
            } else {
                console.error("Message failed to send:", data.error);
            }
        })
        .catch(err => console.error("Network error:", err));
    }
</script>
</body>
</html>