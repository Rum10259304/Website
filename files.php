<?php
session_start();
include('connect.php'); 
include 'admin/auto_log_function.php';
require __DIR__ . '/vendor/autoload.php';

header('Content-Type: text/html; charset=utf-8');

$message = "";
$directory = 'files';

// Determine user context
$user_id = $_SESSION['user_id'] ?? 1;
$role    = $_SESSION['role']    ?? '';
$dept    = $_SESSION['department'] ?? 'Your Department';
$country = $_SESSION['country'] ?? 'Your Country';

// Fetch unique departments
$deptResult = $conn->query("
  SELECT DISTINCT department
  FROM users
  WHERE department IS NOT NULL AND department != ''
  ORDER BY department ASC
");
$departments = [];
while($r = $deptResult->fetch_assoc()){
  $departments[] = $r['department'];
}

// Fetch countries
$countryResult = $conn->query("SELECT country FROM countries ORDER BY country ASC");
$countries = [];
while($r = $countryResult->fetch_assoc()){
  $countries[] = $r['country'];
}

// Build files query based on role
if($role==='ADMIN'){
  $stmt = $conn->prepare("SELECT * FROM files ORDER BY uploaded_at DESC");
}
elseif($role==='MANAGER'){
  $stmt = $conn->prepare("
    SELECT DISTINCT f.*
    FROM files f
    JOIN file_visibility v ON f.id=v.file_id
    WHERE v.visibility_scope='ALL'
      OR (v.visibility_scope='COUNTRY' AND v.category=?)
    ORDER BY f.uploaded_at DESC
  ");
  $stmt->bind_param("s",$country);
}
else {
  $stmt = $conn->prepare("
    SELECT DISTINCT f.*
    FROM files f
    JOIN file_visibility v ON f.id=v.file_id
    WHERE v.visibility_scope='ALL'
      OR (v.visibility_scope='COUNTRY' AND v.category=?)
      OR (v.visibility_scope='DEPARTMENT' AND v.category=?)
    ORDER BY f.uploaded_at DESC
  ");
  $stmt->bind_param("ss",$country,$dept);
}
$stmt->execute();
$files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Load visibility meta
$fileVisibilities = [];
foreach($files as $f){
  $vs = $conn->prepare("
    SELECT visibility_scope, category
    FROM file_visibility
    WHERE file_id=?
  ");
  $vs->bind_param("i",$f['id']);
  $vs->execute();
  $res = $vs->get_result();
  $fileVisibilities[$f['id']] = ['ALL'=>false,'DEPARTMENT'=>[],'COUNTRY'=>[]];
  while($row=$res->fetch_assoc()){
    if($row['visibility_scope']==='ALL'){
      $fileVisibilities[$f['id']]['ALL']=true;
    } elseif($row['visibility_scope']==='DEPARTMENT'){
      $fileVisibilities[$f['id']]['DEPARTMENT'][]=$row['category'];
    } else {
      $fileVisibilities[$f['id']]['COUNTRY'][]=$row['category'];
    }
  }
  $vs->close();
}

// Fetch username 
$username='Unknown';
$usr = $conn->prepare("SELECT username FROM users WHERE user_id=?");
$usr->bind_param("i",$user_id);
$usr->execute();
$usr->bind_result($username);
$usr->fetch();
$usr->close();

// Friendly file-type mapper → icon + color
function getIconClass($type){
  switch($type){
    case 'pdf': return 'file-pdf';
    case 'msword': return 'file-word';
    case 'excel': return 'file-excel';
    case 'powerpoint': return 'file-powerpoint';
    case 'jpeg': case 'png': case 'gif': return 'file-image';
    case 'text': return 'file-alt';
    case 'zip': case 'rar': return 'file-archive';
    default: return 'file';
  }
}
function getIconColor($type){
  switch($type){
    case 'pdf': return '#d9534f';
    case 'msword': return '#337ab7';
    case 'excel': return '#5cb85c';
    case 'powerpoint': return '#f0ad4e';
    case 'file-image': return '#5bc0de';
    case 'text': return '#777';
    case 'zip': case 'rar': return '#999';
    default: return '#666';
  }
}


// Friendly file type mapper for friendly display in Table
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

  // Scan /files and insert if not in DB
  $inserted = 0;

  foreach (scandir($directory) as $file) {
      if ($file === '.' || $file === '..') continue;

      $filePath = realpath("$directory/$file");
      $mimeType = mime_content_type($filePath);
      $fileType = getFriendlyFileType($mimeType);
      $fileSize = round(filesize($filePath) / 1024);
      $relativePath = 'files/' . $file; // Use web-safe relative path

      $check = $conn->prepare("SELECT id FROM files WHERE file_path = ?");
      $check->bind_param("s", $relativePath);
      $check->execute();
      $check->store_result();

      if ($check->num_rows === 0) {
          $stmt = $conn->prepare("INSERT INTO files (user_id, filename, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
          $stmt->bind_param("isssi", $user_id, $file, $relativePath, $fileType, $fileSize);

          if ($stmt->execute()) {
              $file_id = $stmt->insert_id;

              // Insert default visibility: ALL
              $visStmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope) VALUES (?, 'ALL')");
              $visStmt->bind_param("i", $file_id);
              $visStmt->execute();
              $visStmt->close();

              $inserted++;
          }
          $stmt->close();
      }
      $check->close();
  }

  $role = $_SESSION['role'] ?? '';
  $country = $_SESSION['country'] ?? '';
  $department = $_SESSION['department'] ?? '';

  if ($role === 'ADMIN') {
      $stmt = $conn->prepare("
          SELECT f.*
          FROM files f
          ORDER BY f.uploaded_at DESC
      ");
  } elseif ($role === 'MANAGER') {
      $stmt = $conn->prepare("
          SELECT DISTINCT f.*
          FROM files f
          JOIN file_visibility v ON f.id = v.file_id
          WHERE v.visibility_scope = 'ALL'
            OR (v.visibility_scope = 'COUNTRY' AND v.category = ?)
          ORDER BY f.uploaded_at DESC
      ");
      $stmt->bind_param("s", $country);
  } else { // USER
      $stmt = $conn->prepare("
          SELECT DISTINCT f.*
          FROM files f
          JOIN file_visibility v1 ON f.id = v1.file_id
          LEFT JOIN file_visibility v2 ON f.id = v2.file_id
          WHERE v1.visibility_scope = 'ALL'
            OR (v1.visibility_scope = 'COUNTRY' AND v1.category = ?)
            OR (v2.visibility_scope = 'DEPARTMENT' AND v2.category = ?)
          GROUP BY f.id
          HAVING 
              SUM(v1.visibility_scope = 'ALL') > 0 OR 
              (SUM(v1.visibility_scope = 'COUNTRY' AND v1.category = ?) > 0 AND SUM(v2.visibility_scope = 'DEPARTMENT' AND v2.category = ?) > 0)
          ORDER BY f.uploaded_at DESC
      ");
      $stmt->bind_param("ssss", $country, $department, $country, $department);
  }

  $stmt->execute();
  $result = $stmt->get_result();
  $files = $result->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
?>



<!-- Front-End -->
<!DOCTYPE html>
<html lang="en-US">
<head>
  <base href="../">
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verztec – Files</title>
  <link rel="icon" href="images/favicon.ico">
  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/font-awesome.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <style>
    body {
      background: #f2f3fa;
      padding-top: 6rem;    /* more top padding */
      padding-left: 2rem;   /* side padding */
      padding-right: 2rem;
      font-family: "Segoe UI", sans-serif;
    }
    /* HEADER (unchanged) */
    .header-area {
      position: fixed; top:0; left:0; width:100%;
      z-index:999; background:#fff;
      box-shadow:0 2px 4px rgba(0,0,0,0.1);
    }
    /* CONTROLS ROW */
    .controls-row {
      display:flex; flex-wrap:wrap;
      justify-content:space-between;
      align-items:center;
      margin-bottom:1.5rem;
    }
    .search-box {
      position:relative; width:320px;
    }
    .search-box input {
      width:100%; padding:.6rem 1rem .6rem 2.5rem;
      border:1px solid #ccc; border-radius:6px;
      background:#fff;
    }
    .search-box i {
      position:absolute; left:1rem; top:50%;
      transform:translateY(-50%); color:#aaa;
    }
    .filter-dropdown .dropdown-toggle {
      background:#fff; color:#333;
      border:1px solid #ccc; border-radius:6px;
      padding:.5rem 1rem;
      transition:.2s;
    }
    .filter-dropdown .dropdown-toggle:hover {
      background:#000; color:#fff; border-color:#000;
    }
    .filter-dropdown .dropdown-menu {
      max-height:200px; overflow-y:auto; padding:.5rem;
    }
    .btn-upload {
      background:#000; color:#fff;
      border:none; border-radius:6px;
      padding:.6rem 1.2rem;
      transition:.2s;
    }
    .btn-upload:hover {
      background:#333;
    }
    /* TABLE CONTAINER */
    .table-container {
      background:#fff; border-radius:8px;
      overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.1);
      max-height:60vh; overflow-y:auto;
    }
    #file-table {
      border-collapse: separate !important;
      width:100%;
    }
    #file-table thead th {
      position:sticky; top:0;
      background:#000; color:#fff;
      padding:.75rem 1rem;
      z-index:2;
    }
    #file-table thead th:first-child { border-top-left-radius:8px }
    #file-table thead th:last-child  { border-top-right-radius:8px }
    #file-table tbody tr { border-bottom:1px solid #eee }
    #file-table td {
      padding:.75rem 1rem; vertical-align:middle;
      font-size:.9rem; color:#333;
    }
    .file-icon { font-size:1.2rem; margin-right:.5rem }
  </style>
</head>
<body>

  <!-- NAVIGATION (unchanged) -->
  <header class="header-area">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-xl-3 col-md-4 col-6">
          <a href="home.php" class="page-logo-wp"><img src="images/logo.png" alt="Verztec"></a>
        </div>
        <div class="col-xl-6 col-md-5 order-3 order-md-2 d-flex justify-content-center justify-content-md-start">
          <div class="page-menu-wp">
            <ul>
              <li><a href="home.php">Home</a></li>
              <li><a href="chatbot.html">Chatbot</a></li>
              <li class="active"><a href="#">Files</a></li>
              <?php if ($_SESSION['role'] !== 'USER'): ?>
                <li><a href="admin/users.php">Admin</a></li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
        <div class="col-md-3 col-6 d-flex justify-content-end order-2 order-md-3">
          <div class="page-user-icon profile">
            <button><img src="images/Profile-Icon.svg" alt="Profile"></button>
            <div class="menu">
              <ul>
                <li><a href="#"><i class="fa-regular fa-user"></i> Profile</a></li>
                <li><a href="#"><i class="fa-regular fa-message-smile"></i> Inbox</a></li>
                <li><a href="#"><i class="fa-regular fa-gear"></i> Settings</a></li>
                <li><a href="#"><i class="fa-regular fa-square-question"></i> Help</a></li>
                <li><a href="login.php"><i class="fa-regular fa-right-from-bracket"></i> Sign Out</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="container-fluid" style="padding-top:2rem;">
    <div class="row">
      <div class="col-12">

        <!-- CONTROLS -->
        <div class="controls-row">
          <div class="search-box">
            <i class="fa fa-search"></i>
            <input type="text" id="tableSearch" placeholder="Search files…">
          </div>
          <div class="d-flex align-items-center gap-2">
            <div class="dropdown filter-dropdown">
              <button class="dropdown-toggle" id="typeFilterBtn" data-bs-toggle="dropdown">
                File Type: All
              </button>
              <div class="dropdown-menu" id="typeFilterMenu" aria-labelledby="typeFilterBtn">
                <!-- JS will inject checkboxes -->
              </div>
            </div>
            <button class="btn-upload" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
              <i class="fa fa-upload me-1"></i> Upload File
            </button>
            <form method="POST" enctype="multipart/form-data" style="display:none;">
              <input type="file" name="upload_file" id="upload_file"
                     accept=".doc,.docx,.xls,.xlsx,.ppt,.pptx,.pdf,.csv,.txt"
                     onchange="this.form.submit()">
            </form>
          </div>
        </div>

        <!-- FILES TABLE -->
        <div class="table-container">
          <table id="file-table" class="table table-hover mb-0 w-100">
            <thead>
              <tr>
                <th>Filename</th>
                <th>Modified At (UTC +8)</th>
                <th>Type</th>
                <th>Size (kb)</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($files as $file):
                $dt = new DateTime($file['uploaded_at'], new DateTimeZone('UTC'));
                $dt->setTimezone(new DateTimeZone('Asia/Singapore'));
                $icon = getIconClass($file['file_type']);
                $color= getIconColor($file['file_type']);
              ?>
                <tr>
                  <td>
                    <i class="fa fa-<?= $icon ?> file-icon" style="color:<?= $color ?>"></i>
                    <?= htmlspecialchars($file['filename']) ?>
                  </td>
                  <td><?= $dt->format('Y-m-d H:i:s') ?></td>
                  <td><?= htmlspecialchars($file['file_type']) ?></td>
                  <td><?= htmlspecialchars($file['file_size']) ?></td>
                  <td>
                    <div class="dropdown">
                      <button class="btn btn-light btn-sm dropdown-toggle"
                              type="button" data-bs-toggle="dropdown">
                        <i class="fa fa-ellipsis-v"></i>
                      </button>
                      <ul class="dropdown-menu">
                        <li>
                          <a class="dropdown-item preview-file"
                             href="file_preview.php?file_id=<?= $file['id'] ?>"
                             target="_blank">Preview</a>
                        </li>
                        <li>
                          <a class="dropdown-item"
                             href="/file_download.php?file_id=<?= $file
['id'] ?>"
                             download>Download</a>
                        </li>
                        <?php if(in_array($role,['MANAGER','ADMIN'])): ?>
                          <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">Edit</a>
                            <ul class="dropdown-menu">
                              <li>
                                <a class="dropdown-item rename-file" href="#"
                                   data-id="<?= $file['id'] ?>"
                                   data-name="<?= htmlspecialchars($file['filename']) ?>">
                                  Rename
                                </a>
                              </li>
                              <?php if($file['file_type']!=='pdf'): ?>
                                <li>
                                  <a class="dropdown-item"
                                     href="admin/edit_file.php?file_id=<?= $file['id'] ?>"
                                     target="_blank">Edit Content</a>
                                </li>
                              <?php endif; ?>
                              <li>
                                <a class="dropdown-item edit-visibility" href="#"
                                   data-bs-toggle="modal"
                                   data-bs-target="#editVisibilityModal<?= $file['id'] ?>">
                                  Visibility
                                </a>
                              </li>
                            </ul>
                          </li>
                          <li>
                            <a class="dropdown-item text-danger delete-file"
                               href="#" data-fileid="<?= $file['id'] ?>">Delete</a>
                          </li>
                        <?php endif; ?>
                      </ul>
                    </div>
                  </td>
                </tr>
                <!-- VISIBILITY MODAL (exactly as in your original code) -->
                <div class="modal fade" id="editVisibilityModal<?= $file['id'] ?>" tabindex="-1" aria-labelledby="editVisibilityModalLabel<?= $file['id'] ?>" aria-hidden="true">
                  <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                      <form method="POST" action="admin/edit_visibility.php">
                        <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                        <div class="modal-header">
                          <h5 class="modal-title" id="editVisibilityModalLabel<?= $file['id'] ?>">
                            Edit Visibility – <?= htmlspecialchars($file['filename']) ?>
                          </h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <?php $fv = $fileVisibilities[$file['id']]; ?>
                          <?php if($role==='ADMIN'): ?>
                            <div class="mb-3">
                              <label class="form-label fw-bold">Visibility</label><br>
                              <div class="form-check form-check-inline">
                                <input class="form-check-input visibility-radio" type="radio" name="visibility" id="vAll<?= $file['id'] ?>" value="all" <?= $fv['ALL']?'checked':''?>>
                                <label class="form-check-label" for="vAll<?= $file['id'] ?>">All</label>
                              </div>
                              <div class="form-check form-check-inline">
                                <input class="form-check-input visibility-radio" type="radio" name="visibility" id="vRest<?= $file['id'] ?>" value="restricted" <?= !$fv['ALL']?'checked':''?>>
                                <label class="form-check-label" for="vRest<?= $file['id'] ?>">Restricted</label>
                              </div>
                            </div>
                            <div id="editRestrictionOptions<?= $file['id'] ?>" class="<?= $fv['ALL']?'d-none':''?>">
                              <div class="mb-3">
                                <label class="form-label fw-bold">Restrict By</label><br>
                                <div class="form-check form-check-inline">
                                  <input class="form-check-input restrict-toggle" type="checkbox" id="rDept<?= $file['id'] ?>" value="department" data-file-id="<?= $file['id'] ?>" <?= !empty($fv['DEPARTMENT'])?'checked':''?>>
                                  <label class="form-check-label" for="rDept<?= $file['id'] ?>">Department</label>
                                </div>
                                <div class="form-check form-check-inline">
                                  <input class="form-check-input restrict-toggle" type="checkbox" id="rCountry<?= $file['id'] ?>" value="country" data-file-id="<?= $file['id'] ?>" <?= !empty($fv['COUNTRY'])?'checked':''?>>
                                  <label class="form-check-label" for="rCountry<?= $file['id'] ?>">Country</label>
                                </div>
                              </div>
                              <div class="mb-3 <?= empty($fv['DEPARTMENT'])?'d-none':''?>" id="deptDiv<?= $file['id'] ?>">
                                <label class="form-label">Select Departments</label>
                                <?php foreach($departments as $d): ?>
                                  <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="departments[]" value="<?= htmlspecialchars($d) ?>" id="dept<?= $file['id'].md5($d) ?>" <?= in_array($d,$fv['DEPARTMENT'])?'checked':''?>>
                                    <label class="form-check-label" for="dept<?= $file['id'].md5($d) ?>"><?= htmlspecialchars($d) ?></label>
                                  </div>
                                <?php endforeach; ?>
                              </div>
                              <div class="mb-3 <?= empty($fv['COUNTRY'])?'d-none':''?>" id="countryDiv<?= $file['id'] ?>">
                                <label class="form-label">Select Countries</label>
                                <?php foreach($countries as $c): ?>
                                  <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="countries[]" value="<?= htmlspecialchars($c) ?>" id="ctry<?= $file['id'].md5($c) ?>" <?= in_array($c,$fv['COUNTRY'])?'checked':''?>>
                                    <label class="form-check-label" for="ctry<?= $file['id'].md5($c) ?>"><?= htmlspecialchars($c) ?></label>
                                  </div>
                                <?php endforeach; ?>
                              </div>
                            </div>
                          <?php elseif($role==='MANAGER'): ?>
                            <input type="hidden" name="visibility" value="restricted">
                            <input type="hidden" name="departments[]" value="<?= htmlspecialchars($dept) ?>">
                            <div class="mb-3">
                              <label class="form-label fw-bold">Visibility</label><br>
                              <div class="form-check">
                                <input class="form-check-input" type="radio" name="manager_visibility" id="onlyDept<?= $file['id'] ?>" value="department" <?= !empty($fv['DEPARTMENT'])?'checked':''?>>
                                <label class="form-check-label" for="onlyDept<?= $file['id'] ?>">Only Department: <?= htmlspecialchars($dept) ?></label>
                              </div>
                              <div class="form-check">
                                <input class="form-check-input" type="radio" name="manager_visibility" id="wholeCtry<?= $file['id'] ?>" value="country" <?= !empty($fv['COUNTRY'])?'checked':''?>>
                                <label class="form-check-label" for="wholeCtry<?= $file['id'] ?>">Whole Country: <?= htmlspecialchars($country) ?></label>
                              </div>
                            </div>
                            <input type="hidden" name="countries[]" value="<?= htmlspecialchars($country) ?>" id="mgrCtry<?= $file['id'] ?>" <?= !empty($fv['COUNTRY'])?'':'disabled'?>>
                          <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                          <button type="submit" class="btn btn-primary">Save</button>
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>

  <!-- UPLOAD FILE MODAL -->
  <div class="modal fade" id="uploadFileModal" tabindex="-1" aria-labelledby="uploadFileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="uploadFileModalLabel">Upload File</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="uploadFileForm" action="admin/upload_file.php" method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="border border-dashed rounded p-4 text-center"
               style="border:2px dashed #ccc;"
               ondrop="handleDrop(event)" ondragover="event.preventDefault()">
            <p class="mb-2">Drag and drop your file(s) here or</p>
            <input type="file" id="fileInput" name="upload_file[]"
                   class="form-control d-inline-block" style="width:auto;" multiple required>
          </div>
          <hr class="my-4">
          <?php if($role==='ADMIN'): ?>
            <div class="mb-3">
              <label class="form-label fw-bold">Visibility</label><br>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="visibility" id="accessAll" value="all" checked>
                <label class="form-check-label" for="accessAll">All</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="visibility" id="accessRestricted" value="restricted">
                <label class="form-check-label" for="accessRestricted">Restricted</label>
              </div>
            </div>
            <div id="restrictionOptions" class="d-none">
              <div class="mb-3">
                <label class="form-label fw-bold">Restrict By</label><br>
                <div class="form-check form-check-inline">
                  <input class="form-check-input restrict-toggle" type="checkbox" id="restrictByDept" value="department">
                  <label class="form-check-label" for="restrictByDept">Department</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input restrict-toggle" type="checkbox" id="restrictByCountry" value="country">
                  <label class="form-check-label" for="restrictByCountry">Country</label>
                </div>
              </div>
              <div class="mb-3 d-none" id="restrictDepartmentDiv">
                <label class="form-label">Select Departments</label>
                <?php foreach($departments as $d): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="departments[]" value="<?= htmlspecialchars($d) ?>" id="dept<?= md5($d) ?>">
                    <label class="form-check-label" for="dept<?= md5($d) ?>"><?= htmlspecialchars($d) ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="mb-3 d-none" id="restrictCountryDiv">
                <label class="form-label">Select Countries</label>
                <?php foreach($countries as $c): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="countries[]" value="<?= htmlspecialchars($c) ?>" id="ctry<?= md5($c) ?>">
                    <label class="form-check-label" for="ctry<?= md5($c) ?>"><?= htmlspecialchars($c) ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php elseif($role==='MANAGER'): ?>
            <input type="hidden" name="visibility" value="restricted">
            <input type="hidden" name="departments[]" value="<?= htmlspecialchars($dept) ?>">
            <div class="mb-3">
              <label class="form-label fw-bold">Visibility</label><br>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="manager_visibility" id="onlyDepartment" value="department" checked>
                <label class="form-check-label" for="onlyDepartment">Only Department: <?= htmlspecialchars($dept) ?></label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="manager_visibility" id="wholeCountry" value="country">
                <label class="form-check-label" for="wholeCountry">Whole Country: <?= htmlspecialchars($country) ?></label>
              </div>
            </div>
            <input type="hidden" name="countries[]" value="<?= htmlspecialchars($country) ?>" id="mgrCountryInput" disabled>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-dark">Upload</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div></div>
  </div>

  <!-- DELETE CONFIRMATION MODAL -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">Are you sure you want to delete this file?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Yes, Delete</button>
      </div>
    </div></div>
  </div>

  <!-- RENAME FILE MODAL -->
  <div class="modal fade" id="renameModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="admin/rename_file.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Rename File</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="file_id" id="fileIdInput">
        <div class="mb-3">
          <label for="newFilename" class="form-label">New Filename</label>
          <input type="text" class="form-control" name="new_filename" id="newFilename" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-dark">Rename</button>
      </div>
    </form></div>
  </div>

  <!-- ALL YOUR ORIGINAL SCRIPTS BELOW (unchanged) -->
  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script>
    // DataTable init
    const table = $('#file-table').DataTable({ dom:'rt', paging:false, info:false, lengthChange:false });

    // SEARCH
    $('#tableSearch').on('input', function(){
      table.search(this.value).draw();
    });

    // FILTER
    const types = new Set();
    table.rows().every(function(){
      types.add(this.data()[2]);
    });
    let menuHtml='';
    [...types].sort().forEach(type=>{
      menuHtml+=`
        <div class="form-check">
          <input class="form-check-input type-checkbox" type="checkbox" value="${type}">
          <label class="form-check-label">${type}</label>
        </div>`;
    });
    $('#typeFilterMenu').html(menuHtml);
    $.fn.dataTable.ext.search.push((settings,row)=>{
      const sel = $('.type-checkbox:checked').map((_,e)=>e.value).get();
      return !sel.length||sel.includes(row[2]);
    });
    $('#typeFilterMenu').on('change','.type-checkbox',function(){
      table.draw();
      const chosen = $('.type-checkbox:checked').map((_,e)=>e.value).get();
      $('#typeFilterBtn').text('File Type: '+(chosen.length?chosen.join(', '):'All'));
    });

    // DELETE CONFIRM
    $(document).ready(function(){
      let toDelete=null;
      $('#file-table').on('click','.delete-file',function(e){
        e.preventDefault();
        toDelete=$(this).data('fileid');
        new bootstrap.Modal($('#deleteConfirmModal')).show();
      });
      $('#confirmDeleteBtn').on('click',function(){
        if(!toDelete) return;
        $.post('admin/delete_file.php',{file_id:toDelete},res=>{
          if(res.trim()==='success') location.reload();
          else alert('Delete failed: '+res);
        }).fail(()=>alert('Server error'));
        bootstrap.Modal.getInstance($('#deleteConfirmModal')).hide();
      });
    });

    // RENAME FILE
    document.querySelectorAll('.rename-file').forEach(link=>{
      link.addEventListener('click',function(e){
        e.preventDefault();
        const id=this.dataset.id, nm=this.dataset.name;
        $('#fileIdInput').val(id);
        $('#newFilename').val(nm);
        new bootstrap.Modal($('#renameModal')).show();
      });
    });

    // NESTED SUBMENU
    document.addEventListener('DOMContentLoaded',function(){
      document.querySelectorAll('.dropdown-submenu > .dropdown-toggle').forEach(el=>{
        el.addEventListener('click',function(e){
          e.preventDefault(); e.stopPropagation();
          let sm=this.nextElementSibling;
          document.querySelectorAll('.dropdown-submenu .dropdown-menu').forEach(m=>{ if(m!==sm) m.style.display='none'; });
          sm.style.display = sm.style.display==='block'?'none':'block';
        });
      });
      document.addEventListener('click',e=>{
        if(!e.target.closest('.dropdown'))
          document.querySelectorAll('.dropdown-submenu .dropdown-menu').forEach(m=>m.style.display='none');
      });
    });

    // SHOW/HIDE RESTRICTIONS
    document.querySelectorAll('input[name="visibility"]').forEach(el=>{
      el.addEventListener('change',()=>{
        document.getElementById('restrictionOptions').classList.toggle('d-none', !document.getElementById('accessRestricted')?.checked);
      });
    });
    document.querySelectorAll('.restrict-toggle').forEach(el=>{
      el.addEventListener('change',()=>{
        document.getElementById('restrictDepartmentDiv').classList.toggle('d-none', !document.getElementById('restrictByDept').checked);
        document.getElementById('restrictCountryDiv').classList.toggle('d-none', !document.getElementById('restrictByCountry').checked);
      });
    });

    // DRAG & DROP SUPPORT
    function handleDrop(event){
      event.preventDefault();
      const files = event.dataTransfer.files;
      if(files.length>0){
        document.getElementById('fileInput').files = files;
      }
    }
  </script>
  
  <!-- Session Timeout -->
  <script src="js/inactivity.js"></script>
</body>
</html>
