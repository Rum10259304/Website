<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['email']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$otp = rand(100000, 999999);
$_SESSION['otp'] = $otp;
$_SESSION['otp_time'] = time(); // Refresh timer

// Send OTP email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'spamacc2306@gmail.com';
    $mail->Password = 'lfvc kyov oife mwze';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->setFrom('spamacc2306@gmail.com', 'Verztec');
    $mail->addAddress($_SESSION['email'], $_SESSION['username']);
    $mail->isHTML(true);
    $mail->Subject = 'Resent OTP Code';
    $mail->Body = "Your new OTP is: <b>$otp</b>. Please do not share this code.";

    $mail->send();
    header("Location: verification.php");
    exit();

} catch (Exception $e) {
    echo "Could not resend OTP. Mailer Error: {$mail->ErrorInfo}";
}
