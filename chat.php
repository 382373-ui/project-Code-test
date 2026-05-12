<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$pdo   = getDBConnection();
$myId  = (int)$_SESSION['user_id'];
$otherId = (int)($_GET['user_id'] ?? 0);
$jobId   = (int)($_GET['job_id']  ?? 0);

if (!$otherId) { header("Location: messages.php"); exit; }

// ── Other user + profile ─────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.role,
           p.first_name, p.last_name, p.profile_img, p.bio,
           p.skills, p.availability, p.grade_year, p.is_public, p.tags
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$otherId]);
$otherUser = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$otherUser) { die("User not found."); }

$otherIsPublic = (bool)($otherUser['is_public'] ?? true);
$otherFullName = trim(($otherUser['first_name'] ?? '') . ' ' . ($otherUser['last_name'] ?? ''))
               ?: $otherUser['username'];
$otherImg = !empty($otherUser['profile_img'])
    ? htmlspecialchars($otherUser['profile_img'])
    : 'public/images/default-avatar.png';
$otherTags = !empty($otherUser['tags'])
    ? array_filter(array_map('trim', explode(',', $otherUser['tags'])))
    : [];

// ── Job info ─────────────────────────────────────────────────────────────────
$job = null;
if ($jobId > 0) {
    $stmt = $pdo->prepare("
        SELECT j.*, u.username AS poster_name
        FROM jobs j JOIN users u ON u.id = j.poster_user_id
        WHERE j.id = ?
    ");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── Confirmation record for this job ─────────────────────────────────────────
$confirmation = null;
if ($jobId > 0) {
    // get any confirmation involving the two users
    $stmt = $pdo->prepare("
        SELECT jc.*, u.username AS worker_name
        FROM job_confirmations jc
        JOIN users u ON u.id = jc.worker_user_id
        WHERE jc.job_id = ?
          AND (jc.worker_user_id = ? OR jc.worker_user_id = ?)
        ORDER BY jc.id DESC LIMIT 1
    ");
    $stmt->execute([$jobId, $myId, $otherId]);
    $confirmation = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── Other user's resume (experience + projects) – only if public ─────────────
$otherExperience = [];
$otherProjects   = [];
if ($otherIsPublic && $otherUser['role'] === 'student') {
    $stmt = $pdo->prepare("SELECT * FROM experience WHERE user_id = ? ORDER BY is_current DESC, start_date DESC LIMIT 5");
    $stmt->execute([$otherId]);
    $otherExperience = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
    $stmt->execute([$otherId]);
    $otherProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Mark messages as read ─────────────────────────────────────────────────────
$pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND job_id = ?")
    ->execute([$otherId, $myId, $jobId]);

// ── Fetch message history ─────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT * FROM messages
    WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))
      AND job_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$myId, $otherId, $otherId, $myId, $jobId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Confirmation status badge helper ─────────────────────────────────────────
function confirmBadge($status) {
    $map = [
        'submitted' => ['primary',   'clock',              'Proof Submitted'],
        'confirmed' => ['success',   'check-circle-fill',  'Confirmed Complete'],
        'disputed'  => ['danger',    'exclamation-triangle','Disputed'],
        'pending'   => ['secondary', 'hourglass-split',    'Pending'],
    ];
    $s = $map[$status] ?? ['secondary','question-circle', ucfirst($status)];
    return "<span class=\"badge bg-{$s[0]}\"><i class=\"bi bi-{$s[1]} me-1\"></i>{$s[2]}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?= htmlspecialchars($otherUser['username']) ?> – JobBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
    <style>
        #chatArea {
            height: 420px; overflow-y: auto;
            background: #fff; padding: 16px;
            border: 1px solid #dee2e6; border-radius: 8px 8px 0 0;
        }
        .bubble { max-width: 78%; padding: 10px 14px; border-radius: 18px; margin-bottom: 10px; clear: both; }
        .me   { background: #1565c0; color: #fff; float: right; border-bottom-right-radius: 4px; }
        .them { background: #f1f3f5; color: #333; float: left;  border-bottom-left-radius: 4px; }
        .time { font-size: 0.68rem; opacity: .65; margin-top: 4px; display: block; }
        .chat-footer {
            background: #fff; padding: 12px;
            border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 8px 8px;
        }
        .profile-panel { position: sticky; top: 80px; }
        .resume-section { font-size: .88rem; }
        .resume-section h6 { font-size: .8rem; font-weight: 700; text-transform: uppercase;
                             letter-spacing: .05em; color: #6c757d; margin-bottom: .5rem; }
        .tag-pill { display:inline-block; background:#e8f0fe; color:#1565c0;
                    border-radius:20px; padding:2px 10px; font-size:.75rem; margin:2px; }
        .conf-strip { font-size:.82rem; border-radius:6px; padding:8px 12px; }
    </style>
</head>
<body class="bg-light">
<?php include 'includes/header.php'; ?>

<div class="container mt-4 mb-5">
    <div class="row g-4">

        <!-- ═══════════════════════════════ CHAT COLUMN ═══════════════════════════════ -->
        <div class="col-lg-7">

            <!-- Header bar -->
            <div class="d-flex align-items-center mb-3 gap-3">
                <a href="messages.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <img src="<?= $otherImg ?>" class="rounded-circle"
                     style="width:40px;height:40px;object-fit:cover;border:2px solid #dee2e6;">
                <div>
                    <div class="fw-semibold"><?= htmlspecialchars($otherFullName) ?></div>
                    <?php if ($job): ?>
                        <small class="text-primary">
                            <i class="bi bi-briefcase me-1"></i><?= htmlspecialchars($job['title']) ?>
                        </small>
                    <?php endif; ?>
                </div>
                <?php if ($job): ?>
                <a href="job-detail.php?id=<?= $jobId ?>" class="btn btn-sm btn-outline-primary ms-auto">
                    <i class="bi bi-eye me-1"></i>View Job
                </a>
                <?php endif; ?>
            </div>

            <!-- Confirmation strip (if job exists) -->
            <?php if ($job && $confirmation): ?>
            <div class="conf-strip bg-white border mb-3 d-flex align-items-center gap-3 flex-wrap">
                <span class="text-muted small"><i class="bi bi-patch-check me-1"></i>Confirmation:</span>
                <?= confirmBadge($confirmation['status']) ?>
                <?php if ($confirmation['confirmed_at']): ?>
                    <span class="text-muted small">
                        Confirmed <?= date('M j, Y', strtotime($confirmation['confirmed_at'])) ?>
                    </span>
                <?php endif; ?>
                <a href="confirm_job.php?job_id=<?= $jobId ?>"
                   class="btn btn-sm btn-outline-success ms-auto">
                    <i class="bi bi-check2-circle me-1"></i>Go to Confirmation
                </a>
            </div>
            <?php elseif ($job && !$confirmation): ?>
            <div class="conf-strip bg-white border mb-3 d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted small"><i class="bi bi-patch-check me-1"></i>Confirmation:</span>
                <span class="badge bg-secondary"><i class="bi bi-hourglass-split me-1"></i>Not Started</span>
                <a href="confirm_job.php?job_id=<?= $jobId ?>"
                   class="btn btn-sm btn-outline-success ms-auto">
                    <i class="bi bi-check2-circle me-1"></i>Start Confirmation
                </a>
            </div>
            <?php endif; ?>

            <!-- Chat area -->
            <div id="chatArea">
                <?php if (empty($messages)): ?>
                    <div class="text-center text-muted py-5 small">
                        <i class="bi bi-chat-dots fs-2 d-block mb-2"></i>
                        No messages yet. Start the conversation!
                    </div>
                <?php endif; ?>
                <?php foreach ($messages as $m): ?>
                <div class="bubble <?= $m['sender_id'] == $myId ? 'me' : 'them' ?>">
                    <?= nl2br(htmlspecialchars($m['content'])) ?>
                    <span class="time"><?= date('g:i a · M j', strtotime($m['created_at'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="chat-footer">
                <div class="input-group">
                    <input type="text" id="msgInput" class="form-control"
                           placeholder="Write a message…" onkeypress="checkEnter(event)">
                    <button class="btn btn-primary" onclick="sendMessage()">
                        <i class="bi bi-send me-1"></i>Send
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════ PROFILE PANEL ═══════════════════════════════ -->
        <div class="col-lg-5">
            <div class="profile-panel">

                <!-- Profile card -->
                <div class="card mb-3">
                    <div class="card-body text-center pt-4">
                        <img src="<?= $otherImg ?>" class="rounded-circle mb-2"
                             style="width:80px;height:80px;object-fit:cover;border:3px solid #dee2e6;">
                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($otherFullName) ?></h6>
                        <div class="text-muted small mb-2">@<?= htmlspecialchars($otherUser['username']) ?></div>
                        <span class="badge bg-primary mb-2"><?= ucfirst($otherUser['role']) ?></span>

                        <?php if (!empty($otherTags)): ?>
                        <div class="mb-2">
                            <?php foreach ($otherTags as $tag): ?>
                                <span class="tag-pill"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($otherUser['bio'])): ?>
                            <p class="text-muted small mb-2"><?= htmlspecialchars(substr($otherUser['bio'], 0, 120)) ?><?= strlen($otherUser['bio']) > 120 ? '…' : '' ?></p>
                        <?php endif; ?>

                        <?php if ($otherUser['role'] === 'student'): ?>
                            <div class="d-flex justify-content-center gap-2 mt-2">
                                <?php if ($otherIsPublic): ?>
                                    <a href="resume.php?user=<?= $otherId ?>" target="_blank"
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-file-person me-1"></i>View Resume
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" id="reqAccessBtn">
                                        <i class="bi bi-lock me-1"></i>Request Resume Access
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Resume preview (if public student) -->
                <?php if ($otherIsPublic && $otherUser['role'] === 'student'): ?>

                    <?php if (!empty($otherUser['skills'])): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-light py-2">
                            <strong class="small"><i class="bi bi-lightning me-1 text-primary"></i>Skills</strong>
                        </div>
                        <div class="card-body py-2 resume-section">
                            <?= nl2br(htmlspecialchars($otherUser['skills'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($otherExperience)): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-light py-2">
                            <strong class="small"><i class="bi bi-briefcase me-1 text-primary"></i>Experience</strong>
                        </div>
                        <div class="card-body py-2 resume-section">
                            <?php foreach ($otherExperience as $i => $exp): ?>
                            <div class="<?= $i > 0 ? 'border-top pt-2 mt-2' : '' ?>">
                                <div class="fw-semibold"><?= htmlspecialchars($exp['position']) ?></div>
                                <div class="text-muted"><?= htmlspecialchars($exp['company']) ?>
                                    <?php if ($exp['is_current']): ?>
                                        <span class="badge bg-success ms-1" style="font-size:.65rem;">Current</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted" style="font-size:.75rem;">
                                    <?= $exp['start_date'] ? date('M Y', strtotime($exp['start_date'])) : '' ?>
                                    <?= (!$exp['is_current'] && $exp['end_date']) ? ' – ' . date('M Y', strtotime($exp['end_date'])) : ($exp['is_current'] ? ' – Present' : '') ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($otherProjects)): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-light py-2">
                            <strong class="small"><i class="bi bi-code-slash me-1 text-primary"></i>Projects</strong>
                        </div>
                        <div class="card-body py-2 resume-section">
                            <?php foreach ($otherProjects as $i => $proj): ?>
                            <div class="<?= $i > 0 ? 'border-top pt-2 mt-2' : '' ?>">
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($proj['title']) ?>
                                    <?php if ($proj['url']): ?>
                                        <a href="<?= htmlspecialchars($proj['url']) ?>" target="_blank"
                                           class="ms-1 text-muted small"><i class="bi bi-box-arrow-up-right"></i></a>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($proj['description'])): ?>
                                    <div class="text-muted small"><?= htmlspecialchars(substr($proj['description'], 0, 80)) ?>…</div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($otherExperience) && empty($otherProjects)): ?>
                    <div class="alert alert-light border text-center small">
                        <i class="bi bi-person-lines-fill me-1"></i>
                        No experience or projects added yet.
                        <a href="resume.php?user=<?= $otherId ?>" target="_blank">View full resume</a>
                    </div>
                    <?php endif; ?>

                <?php elseif ($otherUser['role'] === 'student' && !$otherIsPublic): ?>
                <!-- Private resume notice -->
                <div class="card mb-3 border-warning">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-lock fs-2 text-warning d-block mb-2"></i>
                        <p class="mb-2 small text-muted">
                            <?= htmlspecialchars($otherUser['username']) ?>'s resume is <strong>private</strong>.
                            Send them a message asking them to make it public so you can view it.
                        </p>
                        <button class="btn btn-sm btn-warning" id="reqAccessBtn2">
                            <i class="bi bi-send me-1"></i>Request Resume Access
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Job summary card -->
                <?php if ($job): ?>
                <div class="card mb-3">
                    <div class="card-header bg-light py-2">
                        <strong class="small"><i class="bi bi-briefcase me-1 text-primary"></i>Job Details</strong>
                    </div>
                    <div class="card-body py-2 resume-section">
                        <div class="fw-semibold"><?= htmlspecialchars($job['title']) ?></div>
                        <div class="text-muted small mb-1">
                            <span class="badge bg-secondary me-1"><?= ucfirst($job['category']) ?></span>
                            <?php if ($job['pay'] > 0): ?>
                                <span class="text-success fw-semibold">
                                    $<?= number_format($job['pay'], 2) ?><?= $job['pay_type'] === 'hourly' ? '/hr' : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Volunteer / Unpaid</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($job['is_boosted'] || ($job['boost_amount'] ?? 0) > 0): ?>
                        <div class="text-warning small mb-1">
                            <i class="bi bi-lightning-fill me-1"></i>Boosted listing
                        </div>
                        <?php endif; ?>
                        <!-- Confirmation status always shown -->
                        <div class="mt-2 pt-2 border-top">
                            <span class="text-muted small">Confirmation: </span>
                            <?php if ($confirmation): ?>
                                <?= confirmBadge($confirmation['status']) ?>
                            <?php else: ?>
                                <span class="badge bg-secondary">Not Started</span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2">
                            <a href="job-detail.php?id=<?= $jobId ?>" class="btn btn-sm btn-outline-primary w-100">
                                <i class="bi bi-eye me-1"></i>Full Job Details
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const chatArea = document.getElementById('chatArea');
chatArea.scrollTop = chatArea.scrollHeight;

function checkEnter(e) { if (e.key === 'Enter') sendMessage(); }

function sendMessage(customText) {
    const input = document.getElementById('msgInput');
    const text  = customText || input.value.trim();
    if (!text) return;

    const fd = new FormData();
    fd.append('receiver_id', '<?= $otherId ?>');
    fd.append('job_id',      '<?= $jobId ?>');
    fd.append('content',     text);

    fetch('send_message.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            chatArea.innerHTML += `
                <div class="bubble me">
                    ${text.replace(/[<>&"]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]))}
                    <span class="time">Just now</span>
                </div>`;
            if (!customText) input.value = '';
            chatArea.scrollTop = chatArea.scrollHeight;
        }
    });
}

// Request resume access — sends an auto message in chat
function sendAccessRequest() {
    const msg = "Hi <?= addslashes(htmlspecialchars($otherFullName)) ?>, could you make your profile/resume public so I can view it? I'd love to learn more about your background.";
    sendMessage(msg);
}

const btn1 = document.getElementById('reqAccessBtn');
const btn2 = document.getElementById('reqAccessBtn2');
if (btn1) btn1.addEventListener('click', () => { sendAccessRequest(); btn1.textContent = 'Request sent!'; btn1.disabled = true; });
if (btn2) btn2.addEventListener('click', () => { sendAccessRequest(); btn2.textContent = 'Request sent!'; btn2.disabled = true; });
</script>
</body>
</html>
