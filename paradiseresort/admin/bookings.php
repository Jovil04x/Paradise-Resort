<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../utils.php';
require_admin();
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $allowed = ['approve','reject','cancel'];
    if ($id && in_array($action, $allowed, true)) {
        $map = [
          'approve' => 'approved',
          'reject' => 'rejected',
          'cancel' => 'cancelled',
        ];
        $status = $map[$action];
        $stmt = $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        set_flash('success', 'Booking updated.');
        audit_log($pdo, (int)($_SESSION['user']['id'] ?? 0), 'booking_status', 'bookings', $id, ['to' => $status]);
    }
}

// Initial render
$rows = $pdo->query('SELECT b.*, u.full_name, u.email, r.name AS room_name FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id ORDER BY b.created_at DESC')->fetchAll();

include __DIR__ . '/../partials/header.php';
?>

<div class="container mt-5 pt-5">
  <h2 class="section-heading mb-4">Manage Bookings</h2>

  <div class="table-responsive card room-card p-0 overflow-hidden">
    <table class="table table-dark table-striped align-middle mb-0">
      <thead><tr>
        <th>ID</th><th>Guest</th><th>Room</th><th>Dates</th><th>Guests</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['full_name']) ?><br><small class="text-muted"><?= htmlspecialchars($r['email']) ?></small></td>
            <td><?= htmlspecialchars($r['room_name']) ?></td>
            <td><?= htmlspecialchars($r['check_in']) ?> → <?= htmlspecialchars($r['check_out']) ?></td>
            <td><?= (int)$r['guests'] ?></td>
            <td class="text-capitalize"><?= htmlspecialchars($r['status']) ?></td>
            <td>
              <div class="d-flex gap-2 flex-wrap" data-id="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-success js-action" data-action="approve" <?= $r['status'] !== 'pending' ? 'disabled' : '' ?>>Approve</button>
                <button class="btn btn-sm btn-warning js-action" data-action="reject" <?= $r['status'] !== 'pending' ? 'disabled' : '' ?>>Reject</button>
                <button class="btn btn-sm btn-outline-light js-action" data-action="cancel" <?= $r['status'] === 'cancelled' ? 'disabled' : '' ?>>Cancel</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>

<script>
// Lightweight realtime updates using periodic refresh and fetch API
const tbody = document.querySelector('tbody');

async function refreshRows() {
  try {
    const res = await fetch('bookings_api.php');
    const data = await res.json();
    if (!data.ok) return;
    const rows = data.rows;
    tbody.innerHTML = rows.map(r => `
      <tr>
        <td>#${r.id}</td>
        <td>${escapeHtml(r.full_name)}<br><small class="text-muted">${escapeHtml(r.email)}</small></td>
        <td>${escapeHtml(r.room_name)}</td>
        <td>${r.check_in} → ${r.check_out}</td>
        <td>${r.guests}</td>
        <td class="text-capitalize">${r.status}</td>
        <td>
          <div class="d-flex gap-2" data-id="${r.id}">
            <button class="btn btn-sm btn-success js-action" data-action="approve" ${r.status !== 'pending' ? 'disabled' : ''}>Approve</button>
            <button class="btn btn-sm btn-warning js-action" data-action="reject" ${r.status !== 'pending' ? 'disabled' : ''}>Reject</button>
            <button class="btn btn-sm btn-outline-light js-action" data-action="cancel" ${r.status === 'cancelled' ? 'disabled' : ''}>Cancel</button>
          </div>
        </td>
      </tr>
    `).join('');
  } catch (e) {
    // ignore
  }
}

function escapeHtml(s){return String(s).replace(/[&<>"]/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c]))}

async function sendAction(id, action){
  const fd = new FormData();
  fd.append('id', id); fd.append('action', action);
  await fetch('bookings_api.php', {method:'POST', body: fd});
  refreshRows();
}

tbody.addEventListener('click', (e)=>{
  const btn = e.target.closest('.js-action');
  if (!btn) return;
  const id = btn.closest('[data-id]').getAttribute('data-id');
  const action = btn.getAttribute('data-action');
  if (action === 'cancel') {
    const ok = confirm('Cancel this booking? This action cannot be undone.');
    if (!ok) return;
  }
  sendAction(id, action);
});

setInterval(refreshRows, 4000); // auto-refresh every 4s
</script>


