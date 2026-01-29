<?php
session_start();
include 'connect.php'; // Assumes $conn is defined here
require 'vendor/autoload.php'; // Load Composer packages
require_once 'admin/auto_log_function.php'; // Add this for logging

use Firebase\JWT\JWT;

$filesFolderPath = '/var/www/html/files/';
$baseFileUrl = 'http://host.docker.internal:8080/files/';
$onlyOfficeUrl = 'http://localhost:8081/';
$jwtSecret = 'my_jwt_secret';

// Ensure file_id is provided
if (!isset($_GET['file_id'])) {
    die("No file_id specified.");
}

$file_id = intval($_GET['file_id']);

$stmt = $conn->prepare('SELECT filename FROM files WHERE id = ?');
$stmt->bind_param('i', $file_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("File not found in database.");
}

$filename = basename($row['filename']);
$file_path = $filesFolderPath . $filename;
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!file_exists($file_path)) {
    die("File does not exist on disk.");
}

// Log the preview
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $details = "Previewed file: $filename";
    log_action($conn, $user_id, "files", "open", $details);
}

$fileUrl = $baseFileUrl . rawurlencode($filename);
$docKey = md5($filename . filemtime($file_path)); // Unique key per version

// Build OnlyOffice config
$config = [
    'document' => [
        'fileType' => $ext,
        'key' => $docKey,
        'title' => $filename,
        'url' => $fileUrl
    ],
    'documentType' => in_array($ext, ['doc', 'docx']) ? 'word' :
                      (in_array($ext, ['ppt', 'pptx']) ? 'slide' : 'cell'),
    'editorConfig' => [
        'mode' => 'view'
    ]
];

$token = JWT::encode($config, $jwtSecret, 'HS256');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Preview: <?= htmlspecialchars($filename) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        iframe, #onlyoffice-editor { width: 100%; height: 90vh; border: none; }
        img { max-width: 100%; height: auto; }
        pre { background: #eee; padding: 10px; white-space: pre-wrap; }
    </style>
    <?php if (in_array($ext, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'])): ?>
        <script type="text/javascript" src="<?= $onlyOfficeUrl ?>web-apps/apps/api/documents/api.js"></script>
    <?php endif; ?>
</head>
<body>

<h2>Preview: <?= htmlspecialchars($filename) ?></h2>

<?php
switch ($ext) {
    case 'pdf':
        echo '<iframe src="' . htmlspecialchars($fileUrl) . '"></iframe>';
        break;

    case 'jpg':
    case 'jpeg':
    case 'png':
    case 'gif':
        echo '<img src="' . htmlspecialchars($fileUrl) . '" alt="Image preview">';
        break;

    case 'txt':
    case 'csv':
    case 'log':
        echo '<pre>' . htmlspecialchars(file_get_contents($file_path)) . '</pre>';
        break;

    case 'doc':
    case 'docx':
    case 'ppt':
    case 'pptx':
    case 'xls':
    case 'xlsx':
        echo '<div id="onlyoffice-editor"></div>';
        ?>
        <script>
            const config = <?= json_encode($config, JSON_UNESCAPED_SLASHES) ?>;
            const token = "<?= $token ?>";
            const docEditor = new DocsAPI.DocEditor("onlyoffice-editor", {
                ...config,
                token: token
            });
        </script>
        <?php
        break;

    default:
        echo '<p>Preview not available for this file type.</p>';
}
?>

</body>
</html>
