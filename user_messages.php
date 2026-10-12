<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_login();
$pdo = get_pdo();

$userId = (int)$_SESSION['user']['id'];

// Allow user to send follow-up reply in their conversation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $msgId = (int)($_POST['message_id'] ?? 0);
  $content = trim($_POST['reply'] ?? '');
  if ($msgId && $content !== '') {
    $stmt = $pdo->prepare('INSERT INTO contact_replies (message_id, user_id, reply) VALUES (?, ?, ?)');
    $stmt->execute([$msgId, $userId, $content]);
    set_flash('success', 'Message sent.');
    header('Location: /paradiseresort/user_messages.php');
    exit;
  } else {
    set_flash('warning', 'Enter a message first.');
  }
}

// Fetch user's contact messages and replies
$stmt = $pdo->prepare('SELECT * FROM contact_messages WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$convos = $stmt->fetchAll();

include __DIR__ . '/partials/header.php';
?>

<div class="container mt-5 pt-5">
  <h2 class="section-heading mb-3">My Contact History</h2>
  <?php if (!$convos): ?>
    <div class="card room-card p-4">No messages yet. Use the form on the Contact page.</div>
  <?php else: ?>
    <div class="card room-card">
      <div class="list-group list-group-flush">
        <?php foreach ($convos as $c): ?>
          <?php $snippet = trim((string)$c['message']); if (strlen($snippet) > 120) { $snippet = substr($snippet,0,117).'…'; } ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="/paradiseresort/user_message_view.php?id=<?= (int)$c['id'] ?>" style="background: transparent; color: inherit;">
            <div>
              <div class="small text-muted"><?= htmlspecialchars($c['created_at']) ?></div>
              <div><?= htmlspecialchars($snippet) ?></div>
            </div>
            <span class="btn btn-outline-light btn-sm">View chat</span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>



