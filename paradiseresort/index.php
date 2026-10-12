<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
$pdo = get_pdo();
// Optional search by availability and guests
$checkIn = $_GET['check_in'] ?? '';
$checkOut = $_GET['check_out'] ?? '';
$guests = (int)($_GET['guests'] ?? 0);
// Normalize dd/mm/yyyy to yyyy-mm-dd if needed
if ($checkIn && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn)) {
  $ts = strtotime(str_replace('/', '-', $checkIn));
  if ($ts) { $checkIn = date('Y-m-d', $ts); }
}
if ($checkOut && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOut)) {
  $ts = strtotime(str_replace('/', '-', $checkOut));
  if ($ts) { $checkOut = date('Y-m-d', $ts); }
}
if ($checkIn && $checkOut && preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOut) && $guests > 0) {
  $rooms = get_available_rooms($pdo, $checkIn, $checkOut, $guests);
} elseif ($guests > 0) {
  $rooms = get_rooms_by_min_capacity($pdo, $guests);
} else {
  $rooms = get_all_active_rooms($pdo);
}
include __DIR__ . '/partials/header.php';
?>

<div class="hero-full" style="background-image:url('/paradiseresort/images/landing.jpg')">
  <div class="container">
  <div class="intro floating-card" style="max-width: 560px;">
    <!-- <span class="kicker">Paradise Resort</span> -->
    <h1 class="title serif-title">Just Experience A New Level Of Luxury.</h1>
    <p class="subtitle">Discover sunlit rooms, tranquil spaces, and free access to exclusive resort amenities. Let your next escape begin here.</p>
    <div class="search-chips mt-3">
      <a class="btn btn-primary chip-cta" href="#rooms">Explore Rooms</a>
    </div>
  </div>
  </div>
</div>

<section id="rooms" class="container py-5">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="section-heading">Available Rooms</h2>
    <div class="text-muted small">Free resort access with every booking</div>
  </div>
  <form class="card room-card p-3 mb-4" method="get" action="/paradiseresort/index.php#rooms" onsubmit="location.hash='#rooms'">
    <div class="row g-3 align-items-end">
      <div class="col-12 col-md-3">
        <label class="form-label">Check-in</label>
        <input type="date" name="check_in" value="<?= htmlspecialchars($checkIn) ?>" class="form-control">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Check-out</label>
        <input type="date" name="check_out" value="<?= htmlspecialchars($checkOut) ?>" class="form-control">
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">Guests</label>
        <input type="number" name="guests" min="1" value="<?= $guests ?: '' ?>" class="form-control">
      </div>
      <div class="col-12 col-md-4">
        <button class="btn btn-primary" type="submit">Search Availability</button>
        <a class="btn btn-outline-light ms-2" href="/paradiseresort/index.php#rooms">Reset</a>
      </div>
    </div>
  </form>
  <div class="row g-4">
    <?php if (empty($rooms)): ?>
      <div class="col-12">
        <div class="card room-card p-4 text-center">
          <div class="h5 mb-1">No rooms available</div>
          <div class="text-secondary">Try different dates or number of guests.</div>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($rooms as $room): ?>
        <div class="col-12 col-sm-6 col-lg-4">
          <div class="card room-card h-100 shadow-sm">
            <?php $imgPath = resolve_image_path($room['image_path']); ?>
            <img src="/paradiseresort/<?= htmlspecialchars($imgPath) ?>" class="card-img-top object-fit-cover js-viewable" data-full="/paradiseresort/<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($room['name']) ?>" style="height: 200px;">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title mb-1"><?= htmlspecialchars($room['name']) ?></h5>
              <div class="mb-2 small text-muted">Capacity: <?= (int)$room['capacity'] ?> guests</div>
              <div class="mb-2 d-flex flex-wrap gap-2">
                <span class="amenity-badge"><i class="bi bi-wifi"></i>Wi‑Fi</span>
                <span class="amenity-badge"><i class="bi bi-water"></i>Pool</span>
                <span class="amenity-badge"><i class="bi bi-wind"></i>AC</span>
                <span class="amenity-badge"><i class="bi bi-cup-hot"></i>Breakfast</span>
              </div>
              <p class="card-text flex-grow-1"><?= htmlspecialchars($room['description']) ?></p>
              <div class="d-flex align-items-center justify-content-between mt-2">
                <div class="price fw-semibold">₱<?= number_format((float)$room['price_per_night'], 2) ?>/night</div>
                <a href="/paradiseresort/booking.php?room_id=<?= (int)$room['id'] ?>" class="btn btn-primary">Book</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>


