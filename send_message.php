<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'login_required']);
    exit;
}

$pdo = getDBConnection();

$myId      = $_SESSION['user_id'];
$receiver  = (int)($_POST['receiver_id'] ?? 0);
$jobId     = (int)($_POST['job_id'] ?? 0);
$content   = trim($_POST['content'] ?? '');

if ($receiver <= 0 || $content === '') {
    echo json_encode(['error' => 'invalid_data']);
    exit;
}
// Inside send_message.php [cite: 154]
$content = trim($_POST['content'] ?? '');

if ($receiver <= 0 || $content === '') {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit;
}
// Insert EXACTLY to your columns
$stmt = $pdo->prepare("
INSERT INTO messages
(sender_id, receiver_id, job_id, content)
VALUES (?, ?, ?, ?)
");

$stmt->execute([
    $myId,
    $receiver,
    $jobId,
    $content
]);

echo json_encode([
    'success' => true,
    'message' => [
        'sender_id'  => $myId,
        'content'    => htmlspecialchars($content),
        'created_at' => date('Y-m-d H:i:s')
    ]
]);
