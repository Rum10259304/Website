<?php
require __DIR__ . '/../connect.php';
require __DIR__ . '/../admin/auto_log_function.php'; 



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);

    // fetch the username to include in the log
    $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($username);
    $stmt->fetch();
    $stmt->close();

    // delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $userId);

    if ($stmt->execute()) {
        echo 'success';

        // Log the deletion
        if (isset($_SESSION['user_id'])) {
            $adminId = $_SESSION['user_id']; // The admin performing the delete
            $details = "Deleted user '$username' (ID: $userId)";
            log_action($conn, $adminId, 'users','delete', $details);
        }
    } else {
        echo 'fail';
    }

    $stmt->close();
    $conn->close();
} else {
    echo 'invalid';
}
