<div class="container mt-4">
<h3>Your Messages</h3>

<div id="inboxArea">
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

   /* ================= GET CONVERSATIONS ================= */

   $stmt = $pdo->prepare("
   SELECT 
       u.id AS user_id,
       u.username,
       j.title AS job_title,

       m.content AS last_message,
       m.created_at,

       -- unread messages from this user to me
       (
         SELECT COUNT(*) FROM messages
         WHERE sender_id = u.id
         AND receiver_id = ?
         AND is_read = 0
       ) AS unread

   FROM messages m

   JOIN users u 
     ON (u.id = CASE 
           WHEN m.sender_id = ? THEN m.receiver_id 
           ELSE m.sender_id END)

   LEFT JOIN jobs j 
     ON j.id = m.job_id

   WHERE ? IN (m.sender_id, m.receiver_id)

   AND m.id = (
       SELECT id FROM messages
       WHERE (sender_id = m.sender_id AND receiver_id = m.receiver_id)
          OR (sender_id = m.receiver_id AND receiver_id = m.sender_id)
       ORDER BY created_at DESC LIMIT 1
   )

   GROUP BY u.id
   ORDER BY m.created_at DESC
   ");

   $stmt->execute([$myId, $myId, $myId]);
   $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
   ?>

   <!DOCTYPE html>
   <html>
   <head>
   <title>Messages</title>

   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

   <style>
   .conv {
       padding:12px;
       border-bottom:1px solid #ddd;
       cursor:pointer;
   }
   .conv:hover {
       background:#f5f5f5;
   }
   .time {
       font-size:12px;
       color:#666;
   }
   .badge {
       float:right;
   }
   .text-job {
       font-size:12px;
       color:#0d6efd;
   }
   </style>
   </head>

   <body>
   <?php include 'includes/header.php'; ?>

   <div class="container mt-4">

   <h3>Your Messages</h3>

   <!-- ===== SEARCH BOX ===== -->
   <input id="searchBox"
          class="form-control mb-2"
          placeholder="Search conversations..."
          onkeyup="filterChats()">

   <div id="inboxArea">

   <?php foreach($conversations as $c): ?>

   <div class="conv"
        data-name="<?= strtolower($c['username']) ?>"
        onclick="location='chat.php?user_id=<?= $c['user_id'] ?>'">

       <div class="d-flex justify-content-between">

           <strong>
               <?= htmlspecialchars($c['username']) ?>
           </strong>

           <?php if($c['unread'] > 0): ?>
               <span class="badge bg-danger">
                   <?= $c['unread'] ?>
               </span>
           <?php endif; ?>

       </div>

       <?php if(!empty($c['job_title'])): ?>
           <div class="text-job">
               <?= htmlspecialchars($c['job_title']) ?>
           </div>
       <?php endif; ?>

       <span>
           <?= htmlspecialchars(substr($c['last_message'],0,40)) ?>
       </span>

       <div class="time">
           <?= $c['created_at'] ?>
       </div>

   </div>

   <?php endforeach; ?>

   <?php if(empty($conversations)): ?>
   <p>No conversations yet.</p>
   <?php endif; ?>

   </div><!-- inboxArea -->

   </div><!-- container -->

   <!-- ============ SCRIPTS ============ -->

   <script>
   // ---- SEARCH FILTER ----
   function filterChats(){
    let q = document.getElementById("searchBox").value.toLowerCase();

    document.querySelectorAll('.conv').forEach(div=>{
       let name = div.dataset.name;
       div.style.display =
           name.includes(q) ? '' : 'none';
    });
   }

   // ---- AUTO REFRESH INBOX ----
   function refreshInbox(){
   fetch('messages.php?partial=1')
   .then(r => r.text())
   .then(html => {
       let area = document.getElementById('inboxArea');
       if(area){
           area.innerHTML = html;
       }
   });
   }

   // refresh every 4 seconds
   setInterval(refreshInbox, 4000);
   </script>

   </body>
   </html>
<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

$pdo = getDBConnection();
$myId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
SELECT 
    u.id AS user_id,
    u.username,
    m.job_id,
    m.content AS last_message,
    m.created_at
FROM messages m
JOIN users u
 ON u.id = CASE 
     WHEN m.sender_id = ? THEN m.receiver_id
     ELSE m.sender_id END

WHERE ? IN (m.sender_id, m.receiver_id)

AND m.id = (
   SELECT id FROM messages
   WHERE job_id = m.job_id
   AND (
      (sender_id=m.sender_id AND receiver_id=m.receiver_id)
      OR
      (sender_id=m.receiver_id AND receiver_id=m.sender_id)
   )
   ORDER BY created_at DESC LIMIT 1
)

ORDER BY m.created_at DESC
");

$stmt->execute([$myId, $myId]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
