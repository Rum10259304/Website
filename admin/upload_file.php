<?php
require __DIR__ . '/../connect.php';
require __DIR__ . '/../admin/auto_log_function.php';

$user_id      = $_SESSION['user_id']   ?? null;
$user_role    = $_SESSION['role']      ?? '';
$user_dept    = $_SESSION['department']?? null;
$user_country = $_SESSION['country']   ?? null;

$directory = __DIR__ . '/../files';

// Map MIME types to simplified, friendly file types for classification
function getFriendlyFileType($mimeType) {
    $map = [
        'application/pdf' => 'pdf',
        'application/msword' => 'msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'msword',
        'application/vnd.ms-excel' => 'excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'excel',
        'application/vnd.ms-powerpoint' => 'powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'powerpoint',
        'image/jpeg' => 'jpeg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'text/plain' => 'text',
        'application/zip' => 'zip',
        'application/x-rar-compressed' => 'rar',
    ];
    return $map[$mimeType] ?? 'other';
}

// Main logic for processing multiple file uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_FILES['upload_files'])
    && !empty($_FILES['upload_files']['name'][0])) {

    $f = $_FILES['upload_files'];
    $fileCount = count($f['name']);

    for ($i = 0; $i < $fileCount; $i++) {

        // Skip any file that encountered an error during upload
        if ($f['error'][$i] !== UPLOAD_ERR_OK) continue;

        $originalName = basename($f['name'][$i]);

        // Make sure the target directory exists
        if (!is_dir($directory)) mkdir($directory, 0755, true);

        // Prepare the initial file path and set up renaming logic if a file already exists
        $pathInfo  = pathinfo($originalName);
        $filename  = $pathInfo['filename'];
        $extension = $pathInfo['extension'];

        $counter = 1;
        $targetPath   = "$directory/$originalName";
        $relativePath = "files/$originalName";

        // Avoid overwriting files with the same name
        while (file_exists($targetPath)) {
            $originalName = $filename . "($counter)." . $extension;
            $targetPath   = "$directory/$originalName";
            $relativePath = "files/$originalName";
            $counter++;
        }

        // Move file to the target directory
        if (!move_uploaded_file($f['tmp_name'][$i], $targetPath)) continue;

        // Get file details for database storage
        $fileSizeKb = round(filesize($targetPath) / 1024);
        $mimeType   = mime_content_type($targetPath);
        $fileType   = getFriendlyFileType($mimeType);

        // Insert the file record into the database
        $stmt = $conn->prepare(
            "INSERT INTO files (user_id, filename, file_path, file_type, file_size)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isssi",
            $user_id, $originalName, $relativePath, $fileType, $fileSizeKb);

        if (!$stmt->execute()) {
            $stmt->close();
            continue;
        }

        $file_id = $stmt->insert_id;
        $stmt->close();

        // Visibility logic for ADMIN users
        if ($user_role === 'ADMIN') {
            $visibility = $_POST['visibility'] ?? 'all';

            if ($visibility === 'all') {
                $conn->query("INSERT INTO file_visibility (file_id, visibility_scope)
                              VALUES ($file_id, 'ALL')");
            } else {
                // Department-level restriction (if any were selected)
                if (!empty($_POST['departments'])) {
                    $dStmt = $conn->prepare(
                        "INSERT INTO file_visibility
                         (file_id, visibility_scope, category) VALUES (?, 'DEPARTMENT', ?)"
                    );
                    foreach ($_POST['departments'] as $d) {
                        $d = trim($d);
                        if ($d !== '') {
                            $dStmt->bind_param("is", $file_id, $d);
                            $dStmt->execute();
                        }
                    }
                    $dStmt->close();
                }

                // Country-level restriction (if any were selected)
                if (!empty($_POST['countries'])) {
                    $cStmt = $conn->prepare(
                        "INSERT INTO file_visibility
                         (file_id, visibility_scope, category) VALUES (?, 'COUNTRY', ?)"
                    );
                    foreach ($_POST['countries'] as $c) {
                        $c = trim($c);
                        if ($c !== '') {
                            $cStmt->bind_param("is", $file_id, $c);
                            $cStmt->execute();
                        }
                    }
                    $cStmt->close();
                }
            }
        }

        // Visibility logic for MANAGER users
        elseif ($user_role === 'MANAGER') {
            $mgrVis = $_POST['manager_visibility'] ?? 'department';

            if ($mgrVis === 'department' && $user_dept) {
                $mStmt = $conn->prepare(
                    "INSERT INTO file_visibility
                     (file_id, visibility_scope, category) VALUES (?, 'DEPARTMENT', ?)"
                );
                $mStmt->bind_param("is", $file_id, $user_dept);
                $mStmt->execute();
                $mStmt->close();
            }

            if ($mgrVis === 'country' && $user_country) {
                $mStmt = $conn->prepare(
                    "INSERT INTO file_visibility
                     (file_id, visibility_scope, category) VALUES (?, 'COUNTRY', ?)"
                );
                $mStmt->bind_param("is", $file_id, $user_country);
                $mStmt->execute();
                $mStmt->close();
            }
        }

        // Log this upload action
        log_action($conn, $user_id, 'files', 'add',
                   "Uploaded file: $originalName ($fileType, $fileSizeKb KB).");
    }

    // After processing all files, redirect to the files page
    header("Location: ../files.php");
    exit;
}
?>
