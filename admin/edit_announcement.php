<?php
session_start();
include __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = $_POST['id'] ?? '';
  $title = $_POST['title'] ?? '';
  $content = $_POST['content'] ?? '';

  if ($conn->connect_error) {
    die("Connection failed");
  }

  $stmt = $conn->prepare("UPDATE announcements SET title = ?, context = ? WHERE id = ?");
  $stmt->bind_param("ssi", $title, $content, $id);
  $stmt->execute();
  $stmt->close();
  $conn->close();

  header("Location: announcement.php");
  exit;
}
?>
