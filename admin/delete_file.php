<?php

require_once __DIR__ . '/../connect.php';
require __DIR__ . '/../admin/auto_log_function.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_id'])) {
    $file_id = intval($_POST['file_id']);

    // Get the file path from the database
    $stmt = $conn->prepare("SELECT file_path FROM files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $stmt->bind_result($file_path);

    if ($stmt->fetch()) {
        $stmt->close();

        $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $file_path;

        // Try deleting the physical file if it exists
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        // Delete from the database
        $del = $conn->prepare("DELETE FROM files WHERE id = ?");
        $del->bind_param("i", $file_id);
        if ($del->execute()) {
            // Logging deletion
            if (isset($_SESSION['user_id'])) {
                $filename = basename($file_path);
                $details = "Deleted file: $filename";
                log_action($conn, $_SESSION['user_id'], 'files', 'delete', $details);
            }

            echo 'success';
        } else {
            echo 'Database delete failed.';
        }
        $del->close();

    } else {
        echo 'File not found in database.';
    }
} else {
    echo 'Invalid request.';
}
