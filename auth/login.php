<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../utils.php';
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') {
        set_flash('warning', 'Email and password are required.');
    } else {
        $user = find_user_by_email($pdo, $email);
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = public_user_payload($user);
            set_flash('success', 'Welcome back, ' . ($user['full_name'] ?? ''));
            if (($user['role'] ?? 'customer') === 'admin') {
                redirect('/paradiseresort/admin/index.php');
            }
            redirect('/paradiseresort/index.php');
        } else {
            set_flash('danger', 'Invalid email or password.');
        }
    }
}

include __DIR__ . '/../partials/header.php';
?>

<div class="container mt-5 pt-5" style="max-width: 520px;">
  <div class="card room-card p-4">
    <h3 class="mb-3">Login</h3>

    <form method="post">
      
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" class="form-control" name="email" required>
      </div>

      <div class="mb-2">
        <label class="form-label">Password</label>

        <div class="position-relative">
          <input type="password" class="form-control pe-5" name="password" id="password" required>
          
          <button type="button"
                  class="btn btn-sm position-absolute top-50 end-0 translate-middle-y me-2"
                  onclick="togglePassword()"
                  style="border: none; background: transparent;">
            <span id="toggleText">Show</span>
          </button>
        </div>
      </div>

      <!-- Forgot Password BELOW -->
      <div class="text-end mb-3">
        <a href="/paradiseresort/auth/forgot-password.php" class="small">
          Forgot Password?
        </a>
      </div>

      <button class="btn btn-primary w-100" type="submit">Login</button>
    </form>

    <div class="text-center mt-3 small">
      No account? <a href="/paradiseresort/auth/register.php">Sign up</a>
    </div>
  </div>
</div>

<script>
function togglePassword() {
  const password = document.getElementById("password");
  const toggleText = document.getElementById("toggleText");

  if (password.type === "password") {
    password.type = "text";
    toggleText.textContent = "Hide";
  } else {
    password.type = "password";
    toggleText.textContent = "Show";
  }
}
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>

