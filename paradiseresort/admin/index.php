<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../utils.php';
require_admin();
$pdo = get_pdo();

// Inline booking status change from dashboard quick actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'set_status') {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($id && in_array($status, ['approved','rejected','cancelled'], true)) {
      $stmt = $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?');
      $stmt->execute([$status, $id]);
      set_flash('success', 'Booking #'.$id.' updated to '.htmlspecialchars($status).'.');
    }
  }
}

// KPIs
$stats = [
  'users' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
  'rooms' => (int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn(),
  'bookings' => (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
  'pending' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(),
  'revenue' => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success'")->fetchColumn(),
  'paid' => (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='success'")->fetchColumn(),
];

// Lists
$recent = $pdo->query("SELECT b.*, u.full_name, r.name AS room_name,
  (SELECT status FROM payments p WHERE p.booking_id=b.id ORDER BY p.created_at DESC LIMIT 1) AS payment_status
  FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id
  ORDER BY b.created_at DESC LIMIT 8")->fetchAll();

$pending = $pdo->query("SELECT b.id, u.full_name, r.name AS room_name, b.check_in, b.check_out
  FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id
  WHERE b.status='pending' ORDER BY b.created_at DESC LIMIT 5")->fetchAll();

include __DIR__ . '/../partials/header.php';
?>

<div class="container mt-5 pt-5">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h2 class="section-heading mb-0">Admin Dashboard</h2>
    <div class="d-flex gap-2">
      <a class="btn btn-primary" href="/paradiseresort/admin/rooms.php">Manage Rooms</a>
      <a class="btn btn-outline-light" href="/paradiseresort/admin/bookings.php">Manage Bookings</a>
      <a class="btn btn-outline-light" href="/paradiseresort/admin/payments.php">Payments</a>
    </div>
  </div>

  <!-- KPI cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
      <div class="card room-card p-3 text-center h-100">
        <div class="small text-muted">Users</div>
        <div class="display-6"><?= $stats['users'] ?></div>
      </div>
    </div>
    <div class="col-6 col-lg-2">
      <div class="card room-card p-3 text-center h-100">
        <div class="small text-muted">Rooms</div>
        <div class="display-6"><?= $stats['rooms'] ?></div>
      </div>
    </div>
    <div class="col-6 col-lg-2">
      <div class="card room-card p-3 text-center h-100">
        <div class="small text-muted">Bookings</div>
        <div class="display-6"><?= $stats['bookings'] ?></div>
      </div>
    </div>
    <div class="col-6 col-lg-2">
      <div class="card room-card p-3 text-center h-100">
        <div class="small text-muted">Pending</div>
        <div class="display-6"><?= $stats['pending'] ?></div>
      </div>
    </div>
    <div class="col-6 col-lg-2">
      <div class="card room-card p-3 text-center h-100">
        <div class="small text-muted">Paid</div>
        <div class="display-6"><?= $stats['paid'] ?></div>
      </div>
    </div>
    <div class="col-6 col-lg-2">
      <div class="card room-card p-3 text-center h-100">
        <div class="small text-muted">Revenue</div>
        <div class="h3 mb-0">₱<?= number_format($stats['revenue'], 2) ?></div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Recent bookings -->
    <div class="col-12 col-xl-8">
      <div class="card room-card p-0 overflow-hidden">
        <div class="p-3 d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Recent bookings</h5>
          <a class="btn btn-sm btn-outline-light" href="/paradiseresort/admin/bookings.php">View all</a>
        </div>
        <div class="table-responsive">
          <table class="table table-dark align-middle mb-0">
            <thead>
              <tr><th>ID</th><th>Guest</th><th>Room</th><th>Dates</th><th>Status</th><th>Payment</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recent as $r): ?>
                <tr>
                  <td>#<?= (int)$r['id'] ?></td>
                  <td><?= htmlspecialchars($r['full_name']) ?></td>
                  <td><?= htmlspecialchars($r['room_name']) ?></td>
                  <td><?= htmlspecialchars($r['check_in']) ?> → <?= htmlspecialchars($r['check_out']) ?></td>
                  <td class="text-capitalize">
                    <span class="badge <?= $r['status']==='approved'?'text-bg-success':($r['status']==='cancelled'?'text-bg-secondary':'text-bg-warning text-dark') ?>"><?= htmlspecialchars($r['status']) ?></span>
                  </td>
                  <td>
                    <?php if (($r['payment_status'] ?? '') === 'success'): ?>
                      <span class="badge text-bg-success">paid</span>
                    <?php elseif (($r['payment_status'] ?? '') === 'failed'): ?>
                      <span class="badge text-bg-danger">failed</span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary">unpaid</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Pending approvals -->
    <div class="col-12 col-xl-4">
      <div class="card room-card p-3 h-100">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">Pending approvals</h5>
          <span class="badge text-bg-warning text-dark"><?= $stats['pending'] ?></span>
        </div>
        <?php if (!$pending): ?>
          <div class="text-muted">No pending bookings.</div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($pending as $p): ?>
              <div class="list-group-item bg-transparent text-white d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-semibold">#<?= (int)$p['id'] ?> • <?= htmlspecialchars($p['room_name']) ?></div>
                  <div class="small text-muted"><?= htmlspecialchars($p['full_name']) ?> • <?= htmlspecialchars($p['check_in']) ?> → <?= htmlspecialchars($p['check_out']) ?></div>
                </div>
                <div class="d-flex gap-1">
                  <form method="post">
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="status" value="approved">
                    <button class="btn btn-sm btn-success" type="submit">Approve</button>
                  </form>
                  <form method="post" onsubmit="return confirm('Reject this booking?');">
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="status" value="rejected">
                    <button class="btn btn-sm btn-warning" type="submit">Reject</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>


