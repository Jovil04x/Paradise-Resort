<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_login();
$pdo = get_pdo();

$roomId = (int)($_GET['room_id'] ?? $_POST['room_id'] ?? 0);
$room = $roomId ? get_room_by_id($pdo, $roomId) : null;
if (!$room) {
    set_flash('danger', 'Room not found.');
    redirect('/paradiseresort/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkIn = $_POST['check_in'] ?? '';
    $checkOut = $_POST['check_out'] ?? '';
    $guests = (int)($_POST['guests'] ?? 1);

    if (!$checkIn || !$checkOut) {
        set_flash('warning', 'Please select check-in and check-out dates.');
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOut)) {
        set_flash('danger', 'Invalid date format.');
    } elseif (strtotime($checkOut) <= strtotime($checkIn)) {
        set_flash('danger', 'Check-out must be after check-in.');
    } elseif ($guests < 1 || $guests > (int)$room['capacity']) {
        set_flash('danger', 'Guests must be between 1 and room capacity.');
    } else {
        // Check availability first
        if (!is_room_available($pdo, $roomId, $checkIn, $checkOut)) {
            set_flash('danger', 'Selected dates are no longer available for this room. Please choose different dates.');
        } else {
            $bookingId = create_booking($pdo, (int)$_SESSION['user']['id'], $roomId, $checkIn, $checkOut, $guests);
        // Redirect to payment to simulate checkout flow
            set_flash('success', 'Booking created. Please complete payment to confirm.');
            redirect('/paradiseresort/payment.php?booking_id=' . $bookingId);
        }
    }
}

include __DIR__ . '/partials/header.php';
?>

<div class="container mt-5 pt-5" style="max-width: 980px;">
  <div class="booking-hero p-3 p-md-4">
    <div class="row g-4 align-items-start">
      <div class="col-12 col-lg-6">
        <?php $imgPath = resolve_image_path($room['image_path']); ?>
        <div class="image-frame">
          <img src="/paradiseresort/<?= htmlspecialchars($imgPath) ?>" class="w-100 object-fit-cover room-media js-viewable" data-full="/paradiseresort/<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($room['name']) ?>">
          <div class="image-overlay"></div>
          <div class="price-chip">₱<?= number_format((float)$room['price_per_night'], 2) ?>/night</div>
        </div>
        <div class="mt-3">
          <a class="btn btn-outline-light" href="/paradiseresort/index.php#rooms"><i class="bi bi-arrow-left"></i> Back to rooms</a>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card room-card p-4 h-100">
        <h3 class="mb-1"><?= htmlspecialchars($room['name']) ?></h3>
        <div class="small text-muted mb-2">Capacity: <?= (int)$room['capacity'] ?> guests</div>
        <?php if (!empty($room['bed_type']) || !empty($room['size_sqm']) || !empty($room['view'])): ?>
          <div class="small text-muted mb-2">Bed: <?= htmlspecialchars($room['bed_type'] ?? '—') ?> • Size: <?= (int)($room['size_sqm'] ?? 0) ?>sqm • View: <?= htmlspecialchars($room['view'] ?? '—') ?></div>
        <?php endif; ?>
        <?php if (!empty($room['amenities'])): ?>
          <div class="mb-2 d-flex flex-wrap gap-2">
            <?php foreach (explode(',', $room['amenities']) as $am): $am=trim($am); if(!$am) continue; ?>
              <span class="amenity-badge"><i class="bi bi-check2-circle"></i><?= htmlspecialchars($am) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <p class="mb-3"><?= htmlspecialchars($room['description']) ?></p>
        <form method="post">
          <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Check-in</label>
              <input type="date" class="form-control" name="check_in" required>
            </div>
            <div class="col-6">
              <label class="form-label">Check-out</label>
              <input type="date" class="form-control" name="check_out" required>
            </div>
            <div class="col-12">
              <label class="form-label">Guests</label>
              <input type="number" min="1" max="<?= (int)$room['capacity'] ?>" class="form-control" name="guests" value="1" required>
            </div>
          </div>
          <button class="btn btn-primary w-100 mt-3" type="submit">Request Booking</button>
        </form>
        <div class="mt-2 small text-muted">All bookings include free resort access.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>


