<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_login();
$pdo = get_pdo();
$userId = (int)$_SESSION['user']['id'];
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /paradiseresort/user_messages.php'); exit; }

// Post reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $text = trim($_POST['reply'] ?? '');
  if ($text !== '') {
    $stmt = $pdo->prepare('INSERT INTO contact_replies (message_id, user_id, reply) VALUES (?, ?, ?)');
    $stmt->execute([$id, $userId, $text]);
    set_flash('success', 'Message sent.');
    header('Location: /paradiseresort/user_message_view.php?id=' . $id);
    exit;
  } else {
    set_flash('warning', 'Enter a message.');
  }
}

$m = $pdo->prepare('SELECT * FROM contact_messages WHERE id = ? AND user_id = ?');
$m->execute([$id, $userId]);
$msg = $m->fetch();
if (!$msg) { set_flash('danger', 'Conversation not found.'); header('Location: /paradiseresort/user_messages.php'); exit; }

include __DIR__ . '/partials/header.php';
?>

<div class="container mt-5 pt-5" style="max-width: 920px;">
  <a class="btn btn-outline-light mb-3" href="/paradiseresort/user_messages.php">← Back</a>
  <div class="card room-card">
    <div class="card-body">
      <h5 class="mb-1">Conversation with Support</h5>
      <div class="small text-muted mb-3">Started <?= htmlspecialchars($msg['created_at']) ?></div>
      <div class="mb-3 p-3 rounded" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1);">
        <div class="fw-semibold mb-1">You</div>
        <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
      </div>
      <?php
        $r = $pdo->prepare('SELECT r.*, u.full_name FROM contact_replies r LEFT JOIN users u ON u.id=r.admin_user_id WHERE r.message_id = ? ORDER BY r.created_at ASC');
        $r->execute([$id]);
        $rows = $r->fetchAll();
      ?>
      <div class="d-grid gap-3">
        <?php foreach ($rows as $re): ?>
          <div class="p-3 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
            <div class="text-muted small mb-1">Reply by <?= htmlspecialchars($re['full_name'] ?? 'Admin') ?> • <?= htmlspecialchars($re['created_at']) ?></div>
            <div><?= nl2br(htmlspecialchars($re['reply'])) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?><div class="text-secondary small">No replies yet.</div><?php endif; ?>
      </div>
      <form method="post" class="mt-3 d-grid gap-2">
        <textarea class="form-control" name="reply" rows="3" placeholder="Write a message..."></textarea>
        <button class="btn btn-primary" type="submit">Send</button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>



