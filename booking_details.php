<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_login();
$pdo = get_pdo();

$id = (int)($_GET['id'] ?? 0);

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $bid = (int)($_POST['id'] ?? 0);
    if ($bid === $id && $bid > 0) {
        $bookingRow = get_booking_by_id($pdo, $bid);
        if ($bookingRow && (int)$bookingRow['user_id'] === (int)$_SESSION['user']['id']) {
            if ($bookingRow['status'] !== 'cancelled' && $bookingRow['status'] !== 'approved') {
                set_booking_status($pdo, $bid, 'cancelled');
                set_flash('success', 'Booking cancelled.');
            } else {
                set_flash('warning', 'This booking cannot be cancelled.');
            }
        }
    }
    redirect('/paradiseresort/booking_details.php?id=' . $id);
}
$booking = $id ? get_booking_by_id($pdo, $id) : null;
if (!$booking || (int)$booking['user_id'] !== (int)$_SESSION['user']['id']) {
    set_flash('danger', 'Booking not found.');
    redirect('/paradiseresort/my_bookings.php');
}

$nights = max(1, (int)round((strtotime($booking['check_out']) - strtotime($booking['check_in'])) / 86400));
$amount = (float)$booking['price_per_night'] * $nights;
$payment = get_latest_payment_for_booking($pdo, $id);

include __DIR__ . '/partials/header.php';
?>

<div class="container mt-5 pt-5" style="max-width: 980px;">
  <div class="booking-hero p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0">Booking Details</h3>
      <a class="btn btn-outline-light" href="/paradiseresort/my_bookings.php">Back</a>
    </div>
    <div class="row g-3">
      <div class="col-12 col-md-5">
        <?php $imgPath = resolve_image_path($booking['image_path']); ?>
        <div class="image-frame">
          <img src="/paradiseresort/<?= htmlspecialchars($imgPath) ?>" class="w-100 object-fit-cover room-media js-viewable" data-full="/paradiseresort/<?= htmlspecialchars($imgPath) ?>" alt="room">
          <div class="image-overlay"></div>
          <div class="price-chip">₱<?= number_format((float)$booking['price_per_night'] * $nights, 2) ?></div>
        </div>
      </div>
      <div class="col-12 col-md-7">
        <h5 class="mb-1"><?= htmlspecialchars($booking['room_name']) ?></h5>
        <div class="small text-muted mb-1">Status: <span class="text-capitalize"><?= htmlspecialchars($booking['status']) ?></span></div>
        <div class="small text-muted mb-2">Dates: <?= htmlspecialchars($booking['check_in']) ?> → <?= htmlspecialchars($booking['check_out']) ?> (<?= $nights ?> night<?= $nights>1?'s':'' ?>)</div>
        <div class="mb-2">Total: <span class="price fw-semibold">₱<?= number_format($amount,2) ?></span></div>
        <?php if ($payment): ?>
          <div class="small text-muted">Payment: <?= htmlspecialchars($payment['method']) ?> • <?= htmlspecialchars($payment['status']) ?> • Ref: <?= htmlspecialchars($payment['reference']) ?></div>
          <div class="small text-muted">Paid on: <?= htmlspecialchars($payment['created_at']) ?></div>
        <?php else: ?>
          <div class="alert alert-warning">No payment yet. <a href="/paradiseresort/payment.php?booking_id=<?= (int)$booking['id'] ?>">Pay now</a></div>
        <?php endif; ?>

        <?php if ($booking['status'] !== 'cancelled' && $booking['status'] !== 'approved'): ?>
          <form method="post" class="mt-3" onsubmit="return confirm('Cancel this booking?');">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="id" value="<?= (int)$booking['id'] ?>">
            <button class="btn btn-outline-light">Cancel booking</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <hr class="my-4">
    <div class="row">
      <div class="col-12 col-md-6">
        <h6 class="text-uppercase text-muted">Room details</h6>
        <p class="mb-2"><?= nl2br(htmlspecialchars($booking['room_description'] ?? '')) ?></p>
        <div class="small text-muted">Bed: <?= htmlspecialchars($booking['room_bed_type'] ?? '—') ?> • Size: <?= (int)($booking['room_size_sqm'] ?? 0) ?>sqm • View: <?= htmlspecialchars($booking['room_view'] ?? '—') ?></div>
      </div>
      <div class="col-12 col-md-6">
        <h6 class="text-uppercase text-muted">Amenities</h6>
        <?php
          $amenities = array_filter(array_map('trim', explode(',', (string)($booking['room_amenities'] ?? ''))));
        ?>
        <?php if ($amenities): ?>
          <div class="d-flex flex-wrap gap-2 mt-1">
            <?php foreach ($amenities as $a): ?>
              <span class="amenity-badge"><?= htmlspecialchars($a) ?></span>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-muted small">No amenities listed.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>


