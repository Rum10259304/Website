<?php
ini_set('session.cookie_path', '/');
session_start();
// Database Connection for Login System 
include('connect.php'); 
include 'admin/auto_log_function.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


$logout_reason = $_GET['reason'] ?? '';
$show_modal = ($logout_reason === 'timeout');

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Authenticate user
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Password is correct

            // Store session variables
			$_SESSION['user_id'] = $user['user_id']; // or whatever your PK column is
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email']; 
            $_SESSION['role'] = $user['role'];  
			$_SESSION['department'] = $user['department'];  
			$_SESSION['country'] = $user['country']; 

            // Generate OTP
            $otp = rand(100000, 999999);
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_time'] = time(); // Track when OTP was sent

            // Send OTP via PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'spamacc2306@gmail.com';       
                $mail->Password   = 'lfvc kyov oife mwze';         
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('spamacc2306@gmail.com', 'Verztec');
                $mail->addAddress($user['email'], $user['username']);
                $mail->isHTML(true);
                $mail->Subject = 'Your OTP Code';
                $mail->Body    = "Your OTP is: <b>$otp</b>. Please do not share this code.";

                $mail->send();

                // Redirect to OTP verification page
                header("Location: otp_form.php");
                exit();

            } catch (Exception $e) {
                $error = "Could not send OTP. Mail error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }

    $stmt->close();
}
?>
<!-- Database Connection for Login System -->








<!DOCTYPE html>
<html lang="en-US">
	<head>
		<!-- Meta setup -->
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta name="keywords" content="">
		<meta name="decription" content="">
		<!-- Title -->
		<title>Verztec</title>
		<!-- Fav Icon -->
		<link rel="icon" href="images/favicon.ico">	
		<!-- Include Bootstrap -->
		<link rel="stylesheet" href="css/bootstrap.css">
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
		<!-- link font awesome -->
		<link rel="stylesheet" href="css/font-awesome.css">
		<!-- Main StyleSheet -->
		<link rel="stylesheet" href="style.css">	
		<!-- Responsive CSS -->
		<link rel="stylesheet" href="css/responsive.css">
		

	</head>
	<body>
		<!-- Inactivity Logout Modal -->
		<div class="modal fade" id="inactivityModal" tabindex="-1" aria-labelledby="inactivityModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content text-center">
			<div class="modal-header">
				<h5 class="modal-title" id="inactivityModalLabel">Session Expired</h5>
			</div>
			<div class="modal-body">
				You were logged out due to 5 minutes of inactivity.
			</div>
			<div class="modal-footer justify-content-center">
				<a href="login.php" class="btn btn-primary">OK</a>
			</div>
			</div>
		</div>
		</div>
		
		
		<!-- login form area -->
		<main class="login-wrap bg-included">
			<div class="login-form">
				<form action="login.php" method="POST">
					<div class="login-logo px-4">
						<a href="#">
							<img src="images/logo.png" alt="">
						</a>
					</div>

					<?php if (!empty($error)): ?>
                		<p style="color:red; text-align:center;"><?php echo $error; ?></p>
            		<?php endif; ?>

					<div class="single-input pb-3 pb-md-4">
						<label for="a111">Username</label>
						<input id="a111" type="text" name="username" required>
					</div>
					<div class="single-input">
						<label for="a222">Password</label>
						<input id="a222" type="password" name="password" required>
					</div>
					<div class="forgot-password text-end pt-2">
						<a href="forgot_password/enter_username.php">Forgot Password?</a>
					</div>
					<div class="submit-btn">
						<button type="submit">Login</button>
					</div>
				</form>
			</div>
		</main>
		<!-- login form area  -->


		
		
		
		<!-- Main jQuery -->
		<script src="js/jquery-3.4.1.min.js"></script>
		
		<!-- Bootstrap.bundle Script -->
		<script src="js/bootstrap.bundle.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
		
		<!-- Custom jQuery -->
		<script src="js/scripts.js"></script>
		
		<?php if (isset($_GET['reason']) && $_GET['reason'] === 'timeout'): ?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var modal = new bootstrap.Modal(document.getElementById('inactivityModal'));
			modal.show();
		});
		</script>
		<?php endif; ?>

	</body>
</html>