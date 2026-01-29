<?php
session_start();
require_once __DIR__ . '/connect.php'; // adjust path if needed
include 'admin/auto_log_function.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized access.');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['file_id'])) {
    $file_id = intval($_GET['file_id']);
    $user_id = $_SESSION['user_id'];

    // Get file info from DB
    $stmt = $conn->prepare("SELECT filename, file_path FROM files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $stmt->bind_result($filename, $file_path);

    if ($stmt->fetch()) {
        $stmt->close();

        if (!$file_path) {
            http_response_code(404);
            exit('File path missing in database.');
        }

        $fullPath = realpath(__DIR__ . '/' . $file_path);

        if ($fullPath && file_exists($fullPath) && is_readable($fullPath)) {
            // Get the MIME type of the file dynamically
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fullPath);
            finfo_close($finfo);

            // Send headers for download
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($fullPath));

            // Clear output buffers to avoid corrupting the download
            if (ob_get_length()) {
                ob_end_clean();
            }

            // Read and output the file content
            readfile($fullPath);

            // Log the download action
            log_action($conn, $user_id, 'files', 'download', "Downloaded: $filename");
            exit;
        } else {
            http_response_code(404);
            exit('File not found on server.');
        }
    } else {
        $stmt->close();
        http_response_code(404);
        exit('File not found in database.');
    }
} else {
    http_response_code(400);
    exit('Invalid request.');
}
