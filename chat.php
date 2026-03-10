<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pdo = getDBConnection();

$myId     = $_SESSION['user_id'];
$otherId  = (int)($_GET['user_id'] ?? 0);
$jobId    = (int)($_GET['job_id'] ?? 0);

if ($otherId <= 0) {
    die("Invalid user.");
}

/* ========== GET OTHER USER INFO ========== */
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$otherId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

/* ========== MARK MESSAGES AS READ ========== */
$stmt = $pdo->prepare("
UPDATE messages
SET is_read = 1
WHERE sender_id = ?
AND receiver_id = ?
");
$stmt->execute([$otherId, $myId]);

/* ========== LOAD CHAT MESSAGES ========== */
$stmt = $pdo->prepare("
SELECT *
FROM messages
WHERE 
   (sender_id = ? AND receiver_id = ?)
OR (sender_id = ? AND receiver_id = ?)

ORDER BY created_at ASC
");

$stmt->execute([$myId, $otherId, $otherId, $myId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Chat</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.chat-box{
    max-width:700px;
    margin:30px auto;
    background:#f9f9f9;
    padding:15px;
    border-radius:8px;
}

.bubble{
    padding:10px 15px;
    border-radius:18px;
    margin:6px;
    max-width:75%;
}

.me{
    background:#007bff;
    color:white;
    margin-left:auto;
    text-align:right;
}

.them{
    background:#e5e5ea;
    color:black;
}

.input-area{
    display:flex;
    gap:6px;
    margin-top:10px;
}

.time{
    font-size:11px;
    opacity:0.8;
}
</style>
</head>

<body>

<?php include 'includes/header.php'; ?>

<div class="chat-box">

<h4>
Chat with <?= htmlspecialchars($user['username']) ?>
</h4>

<div id="chatArea">

<?php foreach($messages as $m): ?>

<div class="bubble <?= $m['sender_id']==$myId ? 'me' : 'them' ?>">

<?= nl2br(htmlspecialchars($m['content'])) ?>

<div class="time">
<?= $m['created_at'] ?>
</div>

</div>

<?php endforeach; ?>

</div>

<!-- SEND BOX -->
<div class="input-area">
<input id="msg" class="form-control" placeholder="Type message...">

<button class="btn btn-primary" onclick="sendMessage()">
Send
</button>
</div>

</div>

<script>
function sendMessage(){

let txt = document.getElementById("msg").value.trim();
if(!txt) return;

fetch('send_message.php', {
method:'POST',
headers:{'Content-Type':'application/x-www-form-urlencoded'},

body:
"receiver_id=<?= $otherId ?>" +
"&job_id=<?= $jobId ?>" +
"&content=" + encodeURIComponent(txt)

})
.then(r=>r.json())
.then(d=>{

if(d.success){

let chat = document.getElementById("chatArea");

chat.innerHTML += `
<div class="bubble me">
${txt}
<div class="time">just now</div>
</div>`;

document.getElementById("msg").value = "";

}
else{
alert("Error: " + d.error);
}

});
}
</script>

</body>
</html>
