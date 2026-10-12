<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_login();
$pdo = get_pdo();

$bookingId = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$booking = $bookingId ? get_booking_by_id($pdo, $bookingId) : null;
if (!$booking || (int)$booking['user_id'] !== (int)$_SESSION['user']['id']) {
    set_flash('danger', 'Booking not found.');
    redirect('/juliusproject/my_bookings.php');
}

// Calculate nights
$pricing = compute_total_with_rules($pdo, (float)$booking['price_per_night'], $booking['check_in'], $booking['check_out'], null);
$nights = $pricing['nights'];
$amount = $pricing['total'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['method'] ?? 'card';
    $reference = 'PMT-' . strtoupper(bin2hex(random_bytes(6)));
    create_payment($pdo, $bookingId, $amount, $method, 'success', $reference);
    set_booking_status($pdo, $bookingId, 'approved');
    set_flash('success', 'Payment recorded. Ref: ' . $reference);
    redirect('/juliusproject/my_bookings.php');
}

include __DIR__ . '/partials/header.php';
?>

<div class="container mt-5 pt-5" style="max-width: 820px;">
  <div class="card room-card p-4">
    <h3 class="mb-3">Complete Payment</h3>
    <div class="row g-3 align-items-center">
      <div class="col-12 col-md-5">
        <?php $imgPath = resolve_image_path($booking['image_path']); ?>
        <img src="/juliusproject/<?= htmlspecialchars($imgPath) ?>" class="w-100 rounded object-fit-cover" style="height:220px" alt="room">
      </div>
      <div class="col-12 col-md-7">
        <div class="mb-2 fw-semibold"><?= htmlspecialchars($booking['room_name']) ?></div>
        <div class="small text-muted mb-1">
          Stay: <?= htmlspecialchars($booking['check_in']) ?> to <?= htmlspecialchars($booking['check_out']) ?> 
          (<?= $nights ?> night<?= $nights > 1 ? 's' : '' ?>)
        </div>
        <div class="mb-3">
          Total: <span class="price fw-bold">₱<?= number_format($amount, 2) ?></span>
        </div>

        <form method="post" class="d-grid gap-3">
          <input type="hidden" name="booking_id" value="<?= (int)$bookingId ?>">

          <!-- Payment method -->
          <div>
            <label class="form-label">Payment method</label>
            <select name="method" id="paymentMethod" class="form-select">
              <option value="card">Credit/Debit Card</option>
              <option value="gcash">GCash</option>
              <option value="paymaya">Maya</option>
            </select>
          </div>

          <!-- Dynamic input -->
          <div class="row g-2">
            <div class="col-12">
              <input type="text" id="paymentInput" name="payment_detail" class="form-control" placeholder="Card number (16 digits)" required>
            </div>
            <div class="col-12">
              <button class="btn btn-primary w-100" type="submit">
                Pay ₱<?= number_format($amount, 2) ?>
              </button>
            </div>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<script>
const paymentMethod = document.getElementById('paymentMethod');
const paymentInput = document.getElementById('paymentInput');

paymentMethod.addEventListener('change', updatePaymentField);
paymentInput.addEventListener('input', formatPaymentInput);

function updatePaymentField() {
    let method = paymentMethod.value;

    if (method === 'card') {
        paymentInput.placeholder = 'Card number (16 digits)';
        paymentInput.type = 'tel';
        paymentInput.maxLength = 19; // with spaces
    } else if (method === 'gcash') {
        paymentInput.placeholder = 'GCash number (09## ### ####)';
        paymentInput.type = 'tel';
        paymentInput.maxLength = 13; // with spaces
    } else if (method === 'paymaya') {
        paymentInput.placeholder = 'Maya number (09## ### ####)';
        paymentInput.type = 'tel';
        paymentInput.maxLength = 13; // with spaces
    }
    paymentInput.value = ''; // clear field
}

function formatPaymentInput() {
    let method = paymentMethod.value;
    let value = paymentInput.value.replace(/\D/g, ''); // only digits

    if (method === 'card') {
        // Format as 1234 5678 9012 3456
        value = value.substring(0, 16).replace(/(\d{4})(?=\d)/g, '$1 ');
    } else {
        // Format as PH mobile: 09## ### ####
        value = value.substring(0, 11)
                     .replace(/(\d{4})(\d{3})(\d{0,4})/, (match, p1, p2, p3) => {
                         return p3 ? `${p1} ${p2} ${p3}` : p2 ? `${p1} ${p2}` : p1;
                     });
    }
    paymentInput.value = value.trim();
}

// Initial setup
updatePaymentField();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
