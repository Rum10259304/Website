<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/../connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);

    if (!empty($username)) {
        $stmt = $conn->prepare("SELECT email FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $email = $user['email'];

            // Set session variables
            $_SESSION['otp']       = rand(100000, 999999);
            $_SESSION['otp_time']  = time();
            $_SESSION['email']     = $email;
            $_SESSION['username']  = $username;

            // Send OTP via email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'spamacc2306@gmail.com';
                $mail->Password   = 'lfvc kyov oife mwze';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                $mail->setFrom('spamacc2306@gmail.com', 'Verztec');
                $mail->addAddress($email, $username);
                $mail->isHTML(true);
                $mail->Subject = 'Your OTP Code';
                $mail->Body    = "Your OTP code is: <b>{$_SESSION['otp']}</b>. Please do not share this with anyone.";

                $mail->send();

                header("Location: verification.php");
                exit();
            } catch (Exception $e) {
                $error = "Could not send OTP. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "Username not found.";
        }

        $stmt->close();
    } else {
        $error = "Please enter your username.";
    }
}
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
  <base href="../">
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
  <title>Verztec – Enter Username</title>
  <link rel="icon" href="images/favicon.ico"/>
  <link rel="stylesheet" href="css/bootstrap.css"/>
  <link rel="stylesheet" href="css/font-awesome.css"/>
  <link rel="stylesheet" href="style.css"/>
  <link rel="stylesheet" href="css/responsive.css"/>
  <style>
    .login-form { position: relative; }
    .back-btn {
      position: absolute;
      top: 1rem;
      left: 1rem;
      width: 3rem;
      height: 3rem;
      padding: 0;
      border: none;
      border-radius: .75rem;
      background: #fff;
      box-shadow: 0 2px 6px rgba(0,0,0,0.12);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background .2s, box-shadow .2s;
    }
    .back-btn:hover {
      background: #f8f8f8;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .back-btn i {
      color: #000;
      font-size: 1.5rem;
      line-height: 1;
    }
    /* Ensure instruction lines don't wrap awkwardly */
    .otp-instructions p {
      margin-bottom: 0.5rem;
      white-space: normal;
      text-align: center;
    }
    .otp-instructions p:last-child {
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
  <main class="login-wrap bg-included">
    <div class="login-form">

      <!-- Back button with chevron icon -->
      <button
        type="button"
        class="back-btn"
        onclick="location.href='login.php';"
        aria-label="Go back to login"
      >
        <i class="fa fa-chevron-left"></i>
      </button>

      <form action="forgot_password/enter_username.php" method="POST" novalidate>
        <div class="login-logo px-4">
          <a href="#"><img src="images/logo.png" alt="Verztec"/></a>
        </div>

        <!-- Instructions -->
      <div class="single-input pb-3 pb-md-4 otp-instructions">
        <p>
          To reset your password, please confirm your account by entering <span style="white-space:nowrap;">your username.</span>
        </p>
        <p>
          A One Time Password (OTP) will be sent to your registered email address.
        </p>
      </div>

        <!-- Error display -->
        <?php if (!empty($error)): ?>
        <p style="color:red; text-align:center; margin-bottom:1rem;">
          <?= htmlspecialchars($error) ?>
        </p>
        <?php endif; ?>

        <!-- Username input -->
        <div class="single-input pb-3 pb-md-4">
          <label for="username">Enter your username to receive OTP</label>
          <input id="username" name="username" type="text" required />
        </div>

        <!-- Send OTP button -->
        <div class="submit-btn">
          <button type="submit">Send OTP</button>
        </div>
      </form>
    </div>
  </main>

  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
</body>
</html>