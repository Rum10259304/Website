<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';


if (!isset($_SESSION['otp']) || !isset($_SESSION['email'])) {
    // No OTP or email in session? redirect to login or email entry page
    header("Location: login.php");
    exit();
}

$otpError = "";
$otpValiditySeconds = 45;

if (!isset($_SESSION['otp_time'])) {
    $_SESSION['otp_time'] = time();
}
$timeRemaining = ($_SESSION['otp_time'] + $otpValiditySeconds) - time();
$timeRemaining = max($timeRemaining, 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['otp'])) {
        $enteredOtp = trim($_POST['otp']);
        if ($enteredOtp === (string)$_SESSION['otp']) {
            // success: clear OTP and go to home
            unset($_SESSION['otp'], $_SESSION['otp_time']);
            header("Location: reset_password.php");
            exit();
        } else {
            $otpError = "Invalid OTP. Please try again.";
        }
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
  <title>Verztec OTP Verification</title>
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="css/bootstrap.css" />
  <link rel="stylesheet" href="css/font-awesome.css" />
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="css/responsive.css" />
  <script>
    let countdown = <?php echo $timeRemaining; ?>;
    function updateCountdown() {
      const el = document.getElementById('countdown');
      if (!el) return;
      if (countdown > 0) {
        const m = Math.floor(countdown / 60), s = countdown % 60;
        const fmt = `${m < 10 ? '0' + m : m}:${s < 10 ? '0' + s : s}`;
        el.innerText = `Resend OTP in ${fmt}`;
        countdown--;
        setTimeout(updateCountdown, 1000);
      } else {
        el.innerHTML = '<a href="forgot_password/resend_otp.php" style="color:#007bff;text-decoration:none;">Resend OTP</a>';
      }
    }
    window.onload = updateCountdown;
  </script>
</head>
<body>
  <main class="login-wrap bg-included">
    <div class="login-form">
      <form action="forgot_password/verification.php" method="POST">
        <div class="login-logo px-4">
          <a href="#"><img src="images/logo.png" alt="Verztec" /></a>
        </div>

        <?php if (!empty($otpError)): ?>
          <p style="color:red; text-align:center; margin-bottom:1rem;">
            <?= htmlspecialchars($otpError) ?>
          </p>
        <?php endif; ?>

        <div class="single-input pb-3 pb-md-4 text-center">
          <p style="margin-bottom:.25rem;">A One Time Password (OTP) has been sent to</p>
          <p><strong><?= htmlspecialchars($_SESSION['email']) ?></strong></p>
        </div>

        <div class="single-input pb-3 pb-md-4">
          <label for="otp">OTP Code</label>
          <input id="otp" type="text" name="otp" required autofocus autocomplete="off" />
        </div>

        <div class="forgot-password text-end pt-2 mb-3">
          <span id="countdown" style="font-size:.9rem; color:#666;"></span>
        </div>

        <div class="submit-btn">
          <button type="submit">Verify OTP</button>
        </div>
      </form>
    </div>
  </main>

  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
</body>
</html>
