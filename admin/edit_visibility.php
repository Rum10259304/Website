<?php
session_start();
require_once __DIR__ . '/../connect.php';
include 'auto_log_function.php';

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';
if (!$user_id || $role !== 'ADMIN') {
    header("Location: ../files.php");
    exit;
}

$file_id = intval($_POST['file_id'] ?? 0);
$visibility = $_POST['visibility'] ?? 'all';
$departments = $_POST['departments'] ?? [];
$countries = $_POST['countries'] ?? [];

if ($file_id <= 0) {
    header("Location: ../files.php");
    exit;
}

// Delete old visibility
$deleteStmt = $conn->prepare("DELETE FROM file_visibility WHERE file_id = ?");
$deleteStmt->bind_param("i", $file_id);
$deleteStmt->execute();
$deleteStmt->close();

// Insert new visibility
if ($visibility === 'all') {
    $stmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope) VALUES (?, 'ALL')");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $stmt->close();
} else {
    if (!empty($departments)) {
        $stmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'DEPARTMENT', ?)");
        foreach ($departments as $dept) {
            $cleanDept = trim($dept);
            if ($cleanDept !== '') {
                $stmt->bind_param("is", $file_id, $cleanDept);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    if (!empty($countries)) {
        $stmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'COUNTRY', ?)");
        foreach ($countries as $country) {
            $cleanCountry = trim($country);
            if ($cleanCountry !== '') {
                $stmt->bind_param("is", $file_id, $cleanCountry);
                $stmt->execute();
            }
        }
        $stmt->close();
    }
}

// Update the uploaded_at timestamp
$updateTimeStmt = $conn->prepare("UPDATE files SET uploaded_at = NOW() WHERE id = ?");
$updateTimeStmt->bind_param("i", $file_id);
$updateTimeStmt->execute();
$updateTimeStmt->close();


// Get the filename for logging
$nameStmt = $conn->prepare("SELECT filename FROM files WHERE id = ?");
$nameStmt->bind_param("i", $file_id);
$nameStmt->execute();
$nameStmt->bind_result($filename);
$nameStmt->fetch();
$nameStmt->close();

// Logging
log_action($conn, $user_id, 'files', 'edit', "Edited visibility for $filename (file ID: $file_id)");

header("Location: ../files.php");
exit;
