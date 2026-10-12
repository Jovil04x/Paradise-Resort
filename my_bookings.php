<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_login();
$pdo = get_pdo();

// Handle cancellation from card
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id) {
    $b = get_booking_by_id($pdo, $id);
    if ($b && (int)$b['user_id'] === (int)$_SESSION['user']['id']) {
      $isPending = ($b['status'] === 'pending');
      $paid = (get_latest_payment_for_booking($pdo, $id)['status'] ?? '') === 'success';
      if ($isPending && !$paid) {
        set_booking_status($pdo, $id, 'cancelled');
        set_flash('success', 'Booking cancelled.');
      } else {
        set_flash('warning', 'This booking can\'t be cancelled.');
      }
    }
  }
  redirect('/paradiseresort/my_bookings.php');
}

$bookings = get_user_bookings($pdo, (int)$_SESSION['user']['id']);

// Filter tabs
$filter = strtolower((string)($_GET['filter'] ?? 'all'));
$filtered = array_values(array_filter($bookings, function($b) use ($filter) {
  $status = strtolower((string)($b['status'] ?? ''));
  $paid = strtolower((string)($b['payment_status'] ?? '')) === 'success';
  switch ($filter) {
    case 'pending': return $status === 'pending';
    case 'approved': return $status === 'approved';
    case 'cancelled': return $status === 'cancelled';
    case 'paid': return $paid;
    case 'unpaid': return !$paid;
    default: return true;
  }
}));
include __DIR__ . '/partials/header.php';
?>

<div class="container mt-5 pt-5">
  <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
    <h2 class="section-heading mb-0">My Bookings</h2>
    <?php
      $mk = function($key,$label) use ($filter){
        $active = $filter === $key ? 'active' : '';
        return "<a class=\"btn btn-outline-light btn-sm $active\" href=\"/paradiseresort/my_bookings.php?filter=$key\">$label</a>";
      };
    ?>
    <div class="d-flex flex-wrap gap-2">
      <?= $mk('all','All') ?>
      <?= $mk('pending','Pending') ?>
      <?= $mk('approved','Approved') ?>
      <?= $mk('cancelled','Cancelled') ?>
      <?= $mk('paid','Paid') ?>
      <?= $mk('unpaid','Unpaid') ?>
    </div>
  </div>
  <div class="mb-3">
    <a class="btn btn-outline-light btn-sm" href="/paradiseresort/user_messages.php">View Contact History</a>
  </div>
  <?php if (!$filtered): ?>
    <div class="card room-card p-4">No bookings yet. <a href="/paradiseresort/index.php#rooms">Browse rooms</a>.</div>
  <?php else: ?>
    <div class="row g-4">
      <?php $__i = 0; foreach ($filtered as $b): $__i++; if ($__i > 9) break; ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card room-card h-100">
            <?php $imgPath = resolve_image_path($b['image_path']); ?>
            <img src="/paradiseresort/<?= htmlspecialchars($imgPath) ?>" class="card-img-top object-fit-cover js-viewable" data-full="/paradiseresort/<?= htmlspecialchars($imgPath) ?>" style="height: 180px;" alt="Room image">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title mb-1"><?= htmlspecialchars($b['room_name']) ?></h5>
              <div class="d-flex align-items-center gap-2 small text-muted mb-2">
                <span class="badge <?= $b['status']==='approved'?'text-bg-success':($b['status']==='cancelled'?'text-bg-secondary':'text-bg-warning text-dark') ?> text-capitalize"><?= htmlspecialchars($b['status']) ?></span>
                <?php if (($b['payment_status'] ?? '') === 'success'): ?>
                  <span class="badge text-bg-success">paid</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">unpaid</span>
                <?php endif; ?>
              </div>
              <div class="mb-2">From <strong><?= htmlspecialchars($b['check_in']) ?></strong> to <strong><?= htmlspecialchars($b['check_out']) ?></strong></div>
              <div class="mt-auto d-flex justify-content-between align-items-center stack-sm">
                <a class="btn btn-outline-light btn-sm" href="/paradiseresort/booking_details.php?id=<?= (int)$b['id'] ?>">View details</a>
                <div class="d-flex gap-2">
                  <?php if (($b['status'] ?? '') === 'pending' && ($b['payment_status'] ?? '') !== 'success'): ?>
                    <form method="post" onsubmit="return confirm('Cancel this booking?');">
                      <input type="hidden" name="action" value="cancel">
                      <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                      <button class="btn btn-outline-light btn-sm" type="submit">Cancel</button>
                    </form>
                    <a class="btn btn-primary btn-sm" href="/paradiseresort/payment.php?booking_id=<?= (int)$b['id'] ?>">Pay now</a>
                  <?php elseif (($b['status'] ?? '') === 'pending' && ($b['payment_status'] ?? '') === 'success'): ?>
                    <span class="text-muted small">Awaiting approval…</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="small text-muted">Guests: <?= (int)$b['guests'] ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>


