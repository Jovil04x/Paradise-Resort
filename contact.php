<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($name === '' || $email === '' || $message === '') {
        set_flash('warning', 'Please complete all fields.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('warning', 'Please enter a valid email.');
    } else {
        $userId = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
        $stmt = $pdo->prepare('INSERT INTO contact_messages (user_id, name, email, message) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $name, $email, $message]);
        set_flash('success', 'Thanks for reaching out! We will get back to you soon.');
        redirect('/paradiseresort/contact.php');
    }
}

include __DIR__ . '/partials/header.php';
?>

<div class="container mt-5 pt-5" style="max-width: 980px;">
	<div class="row g-4 align-items-stretch">
		<div class="col-12 col-lg-6 d-flex">
			<div class="contact-card p-4 position-relative h-100 w-100">
        <h3 class="mb-2">Contact Us</h3>
        <p class="text-muted">Questions about bookings, rooms, or resort access? Send us a message.</p>
        <form method="post" class="mt-2">
          <div class="mb-3">
            <label class="form-label">Your name</label>
            <div class="position-relative">
              <span class="input-icon"><i class="bi bi-person"></i></span>
              <input type="text" name="name" class="form-control input-with-icon" placeholder="Juan Dela Cruz" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <div class="position-relative">
              <span class="input-icon"><i class="bi bi-envelope"></i></span>
              <input type="email" name="email" class="form-control input-with-icon" placeholder="you@email.com" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Message</label>
            <textarea name="message" class="form-control" rows="5" placeholder="Tell us how we can help..." required></textarea>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-gradient" type="submit">Send Message</button>
            <?php if (!empty($_SESSION['user']) && (($_SESSION['user']['role'] ?? 'customer') === 'admin')): ?>
              <a class="btn btn-outline-light" href="/paradiseresort/admin/messages.php">View Chat History</a>
            <?php endif; ?>
          </div>
        </form>
        <?php if (!empty($_SESSION['user']['id'])): ?>
          <hr>
          <a class="btn btn-outline-light w-100" href="/paradiseresort/user_messages.php">View all chats</a>
        <?php endif; ?>
      </div>
    </div>
		<div class="col-12 col-lg-6 d-flex">
			<div class="contact-card p-4 d-flex flex-column gap-3 h-100 w-100">
        <div>
          <div class="small text-muted">Resort</div>
          <h4 class="mb-1">Peterlyn's Resort</h4>
          <div class="text-muted">Open daily • Free resort access with hotel bookings</div>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <span class="contact-chip"><i class="bi bi-envelope"></i>support@peterlyn's.local</span>
          <span class="contact-chip"><i class="bi bi-telephone"></i>+63 900 000 0000</span>
          <span class="contact-chip"><i class="bi bi-geo-alt"></i>Seaside Blvd, PH</span>
        </div>

        <div class="row g-3">
          <div class="col-12 col-sm-6">
            <div class="small text-muted mb-1">Hours</div>
            <ul class="list-unstyled mb-0">
              <li>Mon–Fri: 9:00 AM – 6:00 PM</li>
              <li>Sat: 9:00 AM – 2:00 PM</li>
              <li>Sun: Closed</li>
            </ul>
          </div>
          <div class="col-12 col-sm-6">
            <div class="small text-muted mb-1">Response time</div>
            <ul class="list-unstyled mb-0">
              <li>Email: within 24 hours</li>
              <li>Phone: immediate during hours</li>
              <li>Emergency: 24/7 on‑site desk</li>
            </ul>
          </div>
        </div>

        
		

        <div>
          <div class="small text-muted mb-1">Follow us</div>
          <div class="d-flex flex-wrap gap-2">
            <a class="contact-chip text-decoration-none" href="#" aria-label="Facebook"><i class="bi bi-facebook"></i>Facebook</a>
            <a class="contact-chip text-decoration-none" href="#" aria-label="Instagram"><i class="bi bi-instagram"></i>Instagram</a>
            <a class="contact-chip text-decoration-none" href="#" aria-label="Twitter"><i class="bi bi-twitter-x"></i>Twitter</a>
          </div>
        </div>

        <!-- Map removed as requested. This card intentionally includes more helpful info to balance column height. -->
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>


