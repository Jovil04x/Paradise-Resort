<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../utils.php';
require_admin();
$pdo = get_pdo();

// Handle status confirmation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'set_status') {
        $paymentId = (int)($_POST['id'] ?? 0);
        $status = strtolower(trim($_POST['status'] ?? ''));
        $allowed = ['pending', 'success', 'failed'];
        if ($paymentId && in_array($status, $allowed, true)) {
            $stmt = $pdo->prepare('UPDATE payments SET status = ? WHERE id = ?');
            $stmt->execute([$status, $paymentId]);
            set_flash('success', 'Payment status updated to ' . htmlspecialchars($status) . '.');
        } else {
            set_flash('danger', 'Invalid payment update.');
        }
        redirect('/paradiseresort/admin/payments.php');
    }
}

$rows = $pdo->query("SELECT p.*, b.user_id, b.check_in, b.check_out, r.name AS room_name, u.full_name, u.email
FROM payments p
JOIN bookings b ON b.id = p.booking_id
JOIN rooms r ON r.id = b.room_id
JOIN users u ON u.id = b.user_id
ORDER BY p.created_at DESC")->fetchAll();

include __DIR__ . '/../partials/header.php';
?>

<div class="container mt-5 pt-5">
  <h2 class="section-heading mb-4">Payments</h2>
  <div class="table-responsive card room-card p-0 overflow-hidden">
    <table class="table table-dark table-striped align-middle mb-0">
      <thead>
        <tr>
          <th>Ref</th><th>Guest</th><th>Room</th><th>Dates</th><th>Amount</th><th>Method</th><th>Status</th><th>When</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['reference']) ?></td>
            <td><?= htmlspecialchars($r['full_name']) ?><br><small class="text-muted"><?= htmlspecialchars($r['email']) ?></small></td>
            <td><?= htmlspecialchars($r['room_name']) ?></td>
            <td><?= htmlspecialchars($r['check_in']) ?> → <?= htmlspecialchars($r['check_out']) ?></td>
            <td>₱<?= number_format((float)$r['amount'], 2) ?></td>
            <td><?= htmlspecialchars($r['method']) ?></td>
            <td class="text-capitalize">
              <?php if ($r['status'] === 'success'): ?>
                <span class="badge text-bg-success">success</span>
              <?php elseif ($r['status'] === 'failed'): ?>
                <span class="badge text-bg-danger">failed</span>
              <?php else: ?>
                <span class="badge text-bg-secondary">pending</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
            <td style="white-space:nowrap;">
              <?php if ($r['status'] !== 'success'): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Mark this payment as received? This will set status to SUCCESS.');">
                  <input type="hidden" name="action" value="set_status">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="status" value="success">
                  <button class="btn btn-primary btn-sm" type="submit">Mark received</button>
                </form>
              <?php endif; ?>
              <?php if ($r['status'] !== 'failed'): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Mark this payment as FAILED?');">
                  <input type="hidden" name="action" value="set_status">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="status" value="failed">
                  <button class="btn btn-outline-light btn-sm" type="submit">Mark failed</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>


