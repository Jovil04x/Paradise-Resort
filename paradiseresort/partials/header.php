<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$appTitle = 'Paradise Resort';
// Detect if current page is within the admin area and the user is an admin
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isAdminArea = !empty($_SESSION['user'])
  && (($_SESSION['user']['role'] ?? '') === 'admin')
  && (strpos($requestUri, '/paradiseresort/admin/') !== false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($appTitle) ?></title>
  <link href="/paradiseresort/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/paradiseresort/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="/paradiseresort/assets/style.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>
  <?php
    $publicNavClass = 'nav-transparent';
    $navClass = $isAdminArea ? 'bg-dark-glass' : $publicNavClass;
  ?>
  <nav class="navbar navbar-expand-lg navbar-dark <?= $navClass ?> fixed-top nav-upper">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="<?= $isAdminArea ? '/paradiseresort/admin/index.php' : '/paradiseresort/index.php' ?>">
        <img src="/paradiseresort/logo/logo.jpeg" onerror="this.onerror=null;this.src='/paradiseresort/logo/logo.jpg'" alt="Logo" class="me-2 rounded-circle object-fit-cover">
        <span class="fw-semibold">Peterlyn's Resort</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsMain" aria-controls="navbarsMain" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarsMain">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
          <?php if ($isAdminArea): ?>
            <li class="nav-item"><a class="nav-link" href="/paradiseresort/admin/index.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="/paradiseresort/admin/rooms.php">Rooms</a></li>
            <li class="nav-item"><a class="nav-link" href="/paradiseresort/admin/bookings.php">Bookings</a></li>
            <li class="nav-item"><a class="nav-link" href="/paradiseresort/admin/payments.php">Payments</a></li>
            <li class="nav-item"><a class="nav-link" href="/paradiseresort/admin/messages.php">Messages</a></li>
            <li class="nav-item"><a class="btn btn-outline-light ms-lg-2" href="/paradiseresort/logout.php">Logout</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="/paradiseresort/index.php">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="/paradiseresort/index.php#rooms">ROOM</a></li>
            <li class="nav-item"><a class="nav-link" href="/paradiseresort/contact.php">Contact</a></li>
            <?php if (!empty($_SESSION['user'])): ?>
              <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
                <li class="nav-item"><a class="nav-link" href="/paradiseresort/admin/index.php">Admin Panel</a></li>
              <?php endif; ?>
              <li class="nav-item"><a class="nav-link" href="/paradiseresort/my_bookings.php">My Bookings</a></li>
              <li class="nav-item"><a class="btn btn-outline-light ms-lg-2" href="/paradiseresort/logout.php">Logout</a></li>
            <?php else: ?>
              <li class="nav-item"><a class="btn btn-book ms-lg-3" href="/paradiseresort/auth/login.php">Book Now</a></li>
            <?php endif; ?>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>
  <main class="flex-grow-1">
  <?php if (!$isAdminArea): ?>
  <script>
    // Solidify navbar background after leaving the hero section
    document.addEventListener('DOMContentLoaded', function(){
      const nav = document.querySelector('nav.navbar');
      const hero = document.querySelector('.hero-full, .hero-curve, .hero');
      function onScroll(){
        const y = window.scrollY || window.pageYOffset || 0;
        let threshold = 60;
        if (hero) {
          const rect = hero.getBoundingClientRect();
          const top = rect.top + window.scrollY;
          threshold = Math.max(60, (top + rect.height) - 100);
        }
        if (y > threshold) nav.classList.add('scrolled'); else nav.classList.remove('scrolled');
      }
      onScroll();
      window.addEventListener('scroll', onScroll, {passive: true});
    });
  </script>
  <?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="flash-container">
    <?php foreach ($_SESSION['flash'] as $type => $messages): ?>
      <?php foreach ((array)$messages as $message): ?>
        <div class="alert alert-<?= htmlspecialchars($type) ?> alert-dismissible fade show" role="alert">
          <?= htmlspecialchars($message) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endforeach; ?>
    <?php endforeach; unset($_SESSION['flash']); ?>
  </div>
<?php endif; ?>


