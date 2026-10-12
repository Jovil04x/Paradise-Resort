<?php
require_once '../config/db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $token = bin2hex(random_bytes(50));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $stmt = $conn->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE email=?");
        $stmt->bind_param("sss", $token, $expires, $email);
        $stmt->execute();

        $resetLink = "http://localhost/paradiseresort/auth/reset-password.php?token=$token";

        // Simple mail (use PHPMailer in production)
        mail($email, "Password Reset", "Click here: $resetLink");

        $message = "Reset link sent to your email.";
    } else {
        $message = "Email not found.";
    }
}
?>

<div class="container mt-5" style="max-width:500px;">
  <div class="card p-4">
    <h4>Forgot Password</h4>

    <?php if ($message): ?>
      <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="email" name="email" class="form-control mb-3" placeholder="Enter your email" required>
      <button class="btn btn-primary w-100">Send Reset Link</button>
    </form>
  </div>
</div>