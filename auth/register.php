<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../utils.php';
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $email === '' || $password === '' || $confirm === '') {
        set_flash('warning', 'All fields are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('warning', 'Please enter a valid email.');
    } elseif ($password !== $confirm) {
        set_flash('warning', 'Passwords do not match.');
    } else {
        $exists = find_user_by_email($pdo, $email);
        if ($exists) {
            set_flash('danger', 'Email is already registered.');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'customer')");
            $stmt->execute([$fullName, $email, $hash]);
            set_flash('success', 'Account created. Please login.');
            redirect('/paradiseresort/auth/login.php');
        }
    }
}

include __DIR__ . '/../partials/header.php';
?>

<div class="container mt-5 pt-5" style="max-width: 560px;">
  <div class="card room-card p-4">
    <h3 class="mb-3">Create Account</h3>
    <form method="post">
      <div class="mb-3">
        <label class="form-label">Full name</label>
        <input type="text" class="form-control" name="full_name" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" class="form-control" name="email" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" class="form-control" name="password" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Confirm password</label>
        <input type="password" class="form-control" name="confirm_password" required>
      </div>
      <button class="btn btn-primary w-100" type="submit">Sign up</button>
    </form>
    <div class="text-center mt-3 small">Already have an account? <a href="/paradiseresort/auth/login.php">Login</a></div>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>


