<?php
session_start();
include __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = $_POST['id'] ?? '';

  if ($conn->connect_error) {
    die("Connection failed");
  }

  $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  $conn->close();

  header("Location: announcement.php");
  exit;
}
?>
