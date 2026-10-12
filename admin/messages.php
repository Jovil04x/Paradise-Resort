<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../utils.php';
require_admin();
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $stmt = $pdo->prepare('DELETE FROM contact_messages WHERE id = ?');
    $stmt->execute([$id]);
    set_flash('success', 'Message deleted.');
}

// Create reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message_id'])) {
    $msgId = (int)$_POST['reply_message_id'];
    $reply = trim($_POST['reply_text'] ?? '');
    if ($msgId && $reply !== '') {
        $stmt = $pdo->prepare('INSERT INTO contact_replies (message_id, admin_user_id, reply) VALUES (?, ?, ?)');
        $stmt->execute([$msgId, (int)($_SESSION['user']['id'] ?? 0), $reply]);
        set_flash('success', 'Reply posted.');
        header('Location: /paradiseresort/admin/messages.php');
        exit;
    } else {
        set_flash('warning', 'Please enter a reply.');
    }
}

$contactMessages = $pdo->query('SELECT * FROM contact_messages ORDER BY created_at DESC')->fetchAll();

include __DIR__ . '/../partials/header.php';
?>

<div class="container mt-5 pt-5">
  <h2 class="section-heading mb-4">Contact Messages</h2>
  <div class="table-responsive card room-card p-0 overflow-hidden">
    <table class="table table-dark table-striped align-middle mb-0">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Message</th>
          <th>Received</th>
          <th>Replies</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($contactMessages as $m): ?>
          <tr>
            <td>#<?= (int)$m['id'] ?></td>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><?= htmlspecialchars($m['email']) ?></td>
            <td style="max-width: 520px;"><?= nl2br(htmlspecialchars($m['message'])) ?></td>
            <td><?= htmlspecialchars($m['created_at']) ?></td>
            <td style="min-width:200px;">
              <a class="btn btn-primary btn-sm" href="/paradiseresort/admin/message_view.php?id=<?= (int)$m['id'] ?>">View chat</a>
            </td>
            <td>
              <form method="post" onsubmit="return confirm('Delete this message?')">
                <input type="hidden" name="delete_id" value="<?= (int)$m['id'] ?>">
                <button class="btn btn-sm btn-outline-light" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>


