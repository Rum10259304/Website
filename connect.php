<?php
$servername = "db"; // Docker service name
$username = "user";
$password = "password";
$database = "Verztec";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
