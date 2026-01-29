<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../connect.php'; 

// Call audit log directly (optional for testing)
auto_log_action($conn); 

function log_action($conn, $user_id, $category,$action, $details = null) {
    if (!$conn) {
        error_log("No DB connection available for logging.");
        return;
    }

    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, category, action, details) VALUES (?, ?, ?, ?)");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return;
    }

    $stmt->bind_param("isss", $user_id, $category, $action, $details);
    if (!$stmt->execute()) {
        error_log("Execution failed: " . $stmt->error);
    }
    $stmt->close();
}

function auto_log_action($conn) {
    if (!isset($_SESSION)) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        error_log("Session user_id not set. Skipping log.");
        return;
    }

    $current_page = basename($_SERVER['PHP_SELF']);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $category = 'navigation';

    $important_pages = [
        'login.php',
        'otp_form.php',
        'home.php',
        'users.php',
        'files.php',
        'audit_log.php',
        'file_preview.php'
    ];

    if (!in_array($current_page, $important_pages)) {
        return;
    }

    $last_log_key = 'last_log_' . md5($current_page);
    $now = time();

    if (isset($_SESSION[$last_log_key]) && ($now - $_SESSION[$last_log_key]) < 60) {
        return; // Already logged recently
    }

    $_SESSION[$last_log_key] = $now;

    $details = "Page: $current_page from IP $ip";
    log_action($conn, $_SESSION['user_id'], "navigation", "visit", $details);
}
