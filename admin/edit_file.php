<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../admin/auto_log_function.php'; 
require_once __DIR__ . '/../connect.php';
use Firebase\JWT\JWT;

$filesFolderPath = '/var/www/html/files/';
$baseFileUrl = 'http://web:80/files/';
$onlyOfficeUrl = 'http://localhost:8081/';
$jwtSecret = 'my_jwt_secret';

// If it's a POST request from OnlyOffice (callback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['callback']) && $_GET['callback'] == '1') {
    $logFile = __DIR__ . '/onlyoffice_callback.log';

    if (!isset($_GET['file_id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing file_id"]);
        exit;
    }

    $file_id = intval($_GET['file_id']);

    $stmt = $conn->prepare('SELECT filename FROM files WHERE id = ?');
    $stmt->bind_param('i', $file_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        http_response_code(404);
        echo json_encode(["error" => "File not found"]);
        exit;
    }

    $filename = basename($row['filename']);
    $save_path = $filesFolderPath . $filename;
    $data = json_decode(file_get_contents("php://input"), true);

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Callback: " . json_encode($data) . PHP_EOL, FILE_APPEND);

    if (isset($data['status'])) {
        switch ($data['status']) {
            case 1:
                echo json_encode(["error" => 0]);
                exit;

            case 2:
            case 3:
            case 6:
            case 7:
                if (isset($data['url'])) {
                    $fileContents = file_get_contents($data['url']);
                    if ($fileContents !== false) {
                        file_put_contents($save_path, $fileContents);
                        echo json_encode(["error" => 0]);
                        exit;
                    }
                }
                break;

            case 4:
                echo json_encode(["error" => 0]);
                exit;
        }
    }

    echo json_encode(["error" => "Unhandled status or failed to save document"]);
    exit;
}


// GET request for loading editor
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

$fileUrl = $baseFileUrl . rawurlencode($filename);
$docKey = md5($filename . filemtime($file_path));


// Log the file editor
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $details = "Opened file editor: $filename";
    log_action($conn, $user_id, "files", "edit", $details);
}

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
        'mode' => 'edit',
        'callbackUrl' => 'http://web/admin/edit_file.php?callback=1&file_id=' . $file_id
    ]
];

$token = JWT::encode($config, $jwtSecret, 'HS256');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Edit: <?= htmlspecialchars($filename) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        iframe, #onlyoffice-editor { width: 100%; height: 90vh; border: none; }
    </style>
    <?php if (in_array($ext, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'])): ?>
        <script type="text/javascript" src="http://localhost:8081/web-apps/apps/api/documents/api.js"></script>
    <?php endif; ?>
</head>
<body>

<h2>Edit: <?= htmlspecialchars($filename) ?></h2>

<?php
switch ($ext) {
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
        echo '<p>This file type is not editable in OnlyOffice.</p>';
}
?>

</body>
</html>
