<?php
session_start();
require_once __DIR__ . '/../connect.php';
include 'auto_log_function.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['file_id'], $_POST['new_filename'])) {
    header("Location: ../files.php");
    exit;
}

$file_id = intval($_POST['file_id']);
$new_filename = trim($_POST['new_filename']);
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'unknown';

if ($new_filename === '') {
    header("Location: ../files.php");
    exit;
}

$stmt = $conn->prepare("SELECT file_path FROM files WHERE id = ?");
$stmt->bind_param("i", $file_id);
$stmt->execute();
$stmt->bind_result($old_path);
$stmt->fetch();
$stmt->close();

if (!$old_path) {
    header("Location: ../files.php");
    exit;
}

$old_full_path = realpath(__DIR__ . '/../' . $old_path);
$directory = dirname($old_path);
$new_relative_path = $directory . '/' . $new_filename;
$new_full_path = realpath(__DIR__ . '/../' . $directory) . '/' . $new_filename;

if (!rename($old_full_path, $new_full_path)) {
    header("Location: ../files.php");
    exit;
}

$stmt = $conn->prepare("UPDATE files SET filename = ?, file_path = ?, uploaded_at = NOW() WHERE id = ?");
$stmt->bind_param("ssi", $new_filename, $new_relative_path, $file_id);

if ($stmt->execute()) {
    log_action($conn, $user_id, 'files', 'edit', "Renamed file ID $file_id to '$new_filename'");
}

$stmt->close();
$conn->close();

header("Location: ../files.php");
exit;
