<?php
require __DIR__ . '/../connect.php';
session_start(); // Needed to access session data
header('Content-Type: application/json');

$users = [];

// Default: return nothing if session not valid
if (!isset($_SESSION['role'])) {
    echo json_encode($users);
    exit;
}

$role = $_SESSION['role'];

if ($role === 'ADMIN') {
    // Admin sees all users
    $stmt = $conn->prepare("SELECT user_id, username, email, department, role, country FROM users ORDER BY user_id DESC");
} elseif ($role === 'MANAGER' && isset($_SESSION['department'])) {
    // Manager sees users from the same department
    $stmt = $conn->prepare("SELECT user_id, username, email, department, role, country FROM users WHERE department = ? ORDER BY user_id DESC");
    $stmt->bind_param("s", $_SESSION['department']);
} else {
    // Any other case: return empty
    echo json_encode($users);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'user_id'     => $row['user_id'],
            'username'    => $row['username'],
            'email'       => $row['email'],
            'department'  => $row['department'],
            'role'        => $row['role'],
            'country'     => $row['country'],
        ];
    }
}

echo json_encode($users);
?>
