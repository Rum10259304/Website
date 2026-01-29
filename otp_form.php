<?php
session_start();
include 'admin/auto_log_function.php';
if (!isset($_SESSION['otp']) || !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$otpError = "";
// OTP valid for 45 seconds
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
            header("Location: home.php");
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
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Verztec OTP Verification</title>
  <link rel="icon" href="images/favicon.ico">
  <!-- same CSS as login -->
  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/font-awesome.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <style>
    /* allow positioning of back button */
    .login-form { position: relative; }

    /* back button styling */
    .back-btn {
      position: absolute;
      top: 1rem;
      left: 1rem;
      width: 2.5rem;
      height: 2.5rem;
      padding: 0;
      border: none;
      border-radius: 0.5rem;
      background: #fff;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }
    .back-btn i {
      color: #000;
      font-size: 1rem;
    }
  </style>
  <script>
    // countdown logic
    let countdown = <?php echo $timeRemaining; ?>;
    function updateCountdown() {
      const el = document.getElementById('countdown');
      if (countdown > 0) {
        const m = Math.floor(countdown/60), s = countdown%60;
        const fmt = `${m<10?'0'+m:m}:${s<10?'0'+s:s}`;
        el.innerText = `Resend OTP in ${fmt}`;
        countdown--;
        setTimeout(updateCountdown,1000);
      } else {
        el.innerHTML =
          '<a href="otp_resend.php" style="color:#007bff;text-decoration:none;">Resend OTP</a>';
      }
    }
    window.onload = updateCountdown;
  </script>
</head>
<body>

  <!-- exactly same wrapper as login page -->
  <main class="login-wrap bg-included">
    <div class="login-form">

      <!-- back button -->
      <button type="button" class="back-btn" onclick="location.href='login.php';">
        <i class="fa fa-arrow-left"></i>
      </button>

      <form action="otp_form.php" method="POST">
        <div class="login-logo px-4">
          <a href="#"><img src="images/logo.png" alt="Verztec"></a>
        </div>

        <!-- PHP error -->
        <?php if (!empty($otpError)): ?>
          <p style="color:red; text-align:center; margin-bottom:1rem;">
            <?= htmlspecialchars($otpError) ?>
          </p>
        <?php endif; ?>

        <!-- OTP instructions -->
        <div class="single-input pb-3 pb-md-4 text-center">
          <p style="margin-bottom:.25rem;">
            A One Time Password (OTP) has been sent to
          </p>
          <p><strong><?= htmlspecialchars($_SESSION['email']) ?></strong></p>
        </div>

        <!-- OTP entry -->
        <div class="single-input pb-3 pb-md-4">
          <label for="otp">OTP Code</label>
          <input id="otp" type="text" name="otp" required>
        </div>

        <!-- countdown / resend link -->
        <div class="forgot-password text-end pt-2 mb-3">
          <span id="countdown" style="font-size:.9rem; color:#666;"></span>
        </div>

        <!-- verify button -->
        <div class="submit-btn">
          <button type="submit">Verify OTP</button>
        </div>
      </form>
    </div>
  </main>

  <!-- same scripts as login -->
  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
</body>
</html>
