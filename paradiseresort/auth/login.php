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
            $_SESSION['user'] = $user;
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
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" class="form-control" name="password" required>
      </div>
      <button class="btn btn-primary w-100" type="submit">Login</button>
    </form>
    <div class="text-center mt-3 small">No account? <a href="/paradiseresort/auth/register.php">Sign up</a></div>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>


