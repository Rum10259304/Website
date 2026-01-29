<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/../connect.php';

$error = "";
$success = "";

// Ensure the user came here via OTP verification
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Hash password
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        $email = $_SESSION['email'];

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);

        if ($stmt->execute()) {
            $success = "Password reset successful.";
            unset($_SESSION['email'], $_SESSION['otp'], $_SESSION['otp_time']); // Clear session
        } else {
            $error = "Failed to reset password. Please try again.";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
  <base href="../">
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>Reset Password - Verztec</title>
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="css/bootstrap.css" />
  <link rel="stylesheet" href="css/font-awesome.css" />
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="css/responsive.css" />
</head>
<body>
  <main class="login-wrap bg-included">
    <div class="login-form">
      <form method="POST" action="forgot_password/reset_password.php">
        <div class="login-logo px-4">
          <a href="#"><img src="images/logo.png" alt="Verztec" /></a>
        </div>

        <!-- Instructions -->
        <div class="single-input pb-3 pb-md-4 text-center">
          <p style="margin-bottom:.25rem;">
            Please enter your new password.
          </p>
        </div>

        <!-- Password -->
        <?php if (!empty($error)): ?>
          <p style="color:red; text-align:center;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <div class="single-input pb-3 pb-md-4">
          <label for="new_password">New Password</label>
          <input type="password" name="new_password" id="new_password" required minlength="6" />
        </div>

        <div class="single-input pb-3 pb-md-4">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" name="confirm_password" id="confirm_password" required minlength="6" />
        </div>

        <div class="submit-btn">
          <button type="submit">Reset Password</button>
        </div>
      </form>
    </div>
  </main>

  <!-- Success Modal -->
  <div class="modal fade" id="successModal" tabindex="-1" role="dialog" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content text-center">
        <div class="modal-header">
          <h5 class="modal-title w-100">Password Reset Successful</h5>
        </div>
        <div class="modal-body">
          <p>Your password has been successfully reset.</p>
        </div>
        <div class="modal-footer justify-content-center">
          <a href="login.php" class="btn btn-dark">Go to Login</a>
        </div>
      </div>
    </div>
  </div>

  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>

  <?php if (!empty($success)): ?>
  <script>
    $(document).ready(function() {
      $('#successModal').modal({
        backdrop: 'static',
        keyboard: false
      });
      $('#successModal').modal('show');
    });
  </script>
  <?php endif; ?>
</body>
</html>
