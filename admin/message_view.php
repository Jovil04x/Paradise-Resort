<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../utils.php';
require_admin();
$pdo = get_pdo();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /paradiseresort/admin/messages.php'); exit; }

// Handle reply postback
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $reply = trim($_POST['reply_text'] ?? '');
  if ($reply !== '') {
    $stmt = $pdo->prepare('INSERT INTO contact_replies (message_id, admin_user_id, reply) VALUES (?, ?, ?)');
    $stmt->execute([$id, (int)($_SESSION['user']['id'] ?? 0), $reply]);
    set_flash('success', 'Reply posted.');
    header('Location: /paradiseresort/admin/message_view.php?id=' . $id);
    exit;
  } else {
    set_flash('warning', 'Please enter a reply.');
  }
}

$stmt = $pdo->prepare('SELECT * FROM contact_messages WHERE id = ?');
$stmt->execute([$id]);
$msg = $stmt->fetch();
if (!$msg) { set_flash('danger', 'Message not found.'); header('Location: /paradiseresort/admin/messages.php'); exit; }

include __DIR__ . '/../partials/header.php';
?>

<div class="container mt-5 pt-5" style="max-width: 920px;">
  <a class="btn btn-outline-light mb-3" href="/paradiseresort/admin/messages.php">← Back</a>
  <div class="card room-card">
    <div class="card-body">
      <div class="d-flex align-items-start gap-3">
        <div class="flex-grow-1">
          <h5 class="mb-1">Conversation with <?= htmlspecialchars($msg['name']) ?> <small class="text-muted">(<?= htmlspecialchars($msg['email']) ?>)</small></h5>
          <div class="small text-muted mb-3">Started <?= htmlspecialchars($msg['created_at']) ?></div>
          <div class="mb-3 p-3 rounded" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1);">
            <div class="fw-semibold mb-1">Initial message</div>
            <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
          </div>

          <?php
            $r = $pdo->prepare('SELECT r.*, u.full_name FROM contact_replies r LEFT JOIN users u ON u.id=r.admin_user_id WHERE r.message_id = ? ORDER BY r.created_at ASC');
            $r->execute([$id]);
            $replies = $r->fetchAll();
          ?>
          <div class="d-grid gap-3">
            <?php foreach ($replies as $re): ?>
              <div class="p-3 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
                <div class="text-muted small mb-1">Reply by <?= htmlspecialchars($re['full_name'] ?? 'Admin') ?> • <?= htmlspecialchars($re['created_at']) ?></div>
                <div><?= nl2br(htmlspecialchars($re['reply'])) ?></div>
              </div>
            <?php endforeach; ?>
            <?php if (!$replies): ?><div class="text-secondary small">No replies yet.</div><?php endif; ?>
          </div>

          <form method="post" class="mt-3 d-grid gap-2">
            <textarea class="form-control" name="reply_text" rows="3" placeholder="Write a reply..."></textarea>
            <button class="btn btn-primary" type="submit">Send reply</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>



