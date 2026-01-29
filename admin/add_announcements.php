<?php
session_start();
include __DIR__ . '/../connect.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $target_audience = $_POST['target_audience'] ?? '';
    $priority = $_POST['priority'] ?? '';

    if ($conn->connect_error) {
        $error = 'Database connection error.';
    } else {
        $stmt = $conn->prepare("INSERT INTO announcements (title, context, target_audience, priority) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $title, $content, $target_audience, $priority);

        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = 'Upload failed. Please try again.';
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en-US">
<head>
  <base href="../">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Verztec Admin – Add Announcement</title>
  <link rel="icon" href="images/favicon.ico" />

  <!-- Stylesheets -->
  <link rel="stylesheet" href="css/bootstrap.css" />
  <link rel="stylesheet" href="css/font-awesome.css" />
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="css/responsive.css" />

  <style>
    html, body {
      height: 100%;
      margin: 0;
    }
    body {
      background: #f2f3fa;
      padding-top: 160px;
    }
    .sidebar-card {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      margin: 1rem;
      padding: 1rem;
      min-height: calc(100vh - 320px);;
    }
    .sidebar-card .nav-link {
      color: #333;
      margin-bottom: 0.75rem;
      border-radius: 6px;
      padding: 0.75rem 1rem;
    }
    .sidebar-card .nav-link.active {
      background-color: #FFD050;
      color: #000;
    }
    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      min-height: calc(100vh - 320px);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .card-header {
      background-color: transparent;
      padding: 2rem 1.5rem 1rem 1.5rem;
    }
    .card-header h1 {
      font-size: 1.8rem;
      color: #000;
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
    }
    label {
      color: #000;
      font-weight: 500;
    }
    .btn-primary {
      background-color: #FBBD1E;
      border-color: #FBBD1E;
      color: #000;
      font-weight: bold;
    }
    .btn-primary:hover {
      background-color: #e0a500;
      border-color: #e0a500;
    }
    .card-footer {
      background-color: transparent;
      font-size: 0.85rem;
      color: #666;
      padding: 1rem 1.5rem;
    }
  </style>
</head>
<body>

  <!-- Fixed Header -->
  <header class="header-area" style="position:fixed; top:0; left:0; width:100%; z-index:999; background:white;">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-xl-3 col-md-4 col-6">
          <a href="home.php" class="page-logo-wp">
            <img src="images/logo.png" alt="Verztec" />
          </a>
        </div>
        <div class="col-xl-6 col-md-5 order-3 order-md-2 d-flex justify-content-center justify-content-md-start">
          <div class="page-menu-wp">
            <ul>
              <li><a href="home.php">Home</a></li>
              <li><a href="chatbot.html">Chatbot</a></li>
              <li><a href="files.php">Files</a></li>
              <li class="active"><a href="#">Admin</a></li>
            </ul>
          </div>
        </div>
        <div class="col-md-3 col-6 d-flex justify-content-end order-2 order-md-3">
          <div class="page-user-icon profile">
            <button><img src="images/Profile-Icon.svg" alt="Profile" /></button>
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

  <!-- Main Layout -->
  <div class="container-fluid">
    <div class="row">
      
      <!-- Sidebar -->
      <div class="col-md-2">
        <div class="sidebar-card">
          <ul class="nav flex-column">
            <li class="nav-item">
              <a class="nav-link d-flex align-items-center" href="admin/users.php">
                <i class="fa fa-users me-2"></i> Users
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link d-flex align-items-center" href="admin/audit_log.php">
                <i class="fa fa-clock-rotate-left me-2"></i> Audit Log
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link active d-flex align-items-center" href="admin/add_announcements.php">
                <i class="fa fa-bullhorn me-2"></i> Announcements
              </a>
            </li>
          </ul>
        </div>
      </div>

      <!-- Main Content -->
      <div class="col-md-10 d-flex justify-content-center align-items-start px-4 mt-3">
        <div class="card w-100">
          <div class="card-header text-center">
            <h1 class="d-flex align-items-center justify-content-center gap-3 m-0" style="font-size: 1.8rem;">
              <img src="images/speakerphone.png" alt="Speakerphone" style="height: 40px; position: relative; top: -5px;" />
              <span>Upload Announcements</span>
            </h1>
          </div>
          <div class="card-body px-4">

            <?php if ($success): ?>
              <div class="alert alert-success alert-dismissible fade show" role="alert">
                ✅ Announcement uploaded successfully!
              </div>
            <?php elseif (!empty($error)): ?>
              <div class="alert alert-danger alert-dismissible fade show" role="alert">
                ⚠️ <?= htmlspecialchars($error) ?>
              </div>
            <?php endif; ?>

            <form action="admin/add_announcements.php" method="post">
              <div class="mb-3">
                <label for="title" class="form-label">Title</label>
                <input type="text" class="form-control" id="title" name="title" required />
              </div>
              <div class="mb-3">
                <label for="content" class="form-label">Content</label>
                <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
              </div>
              <div class="mb-3">
                <label for="target_audience" class="form-label">Target Audience</label>
                <select class="form-select" id="target_audience" name="target_audience" required>
                  <option value="" disabled selected>Select audience</option>
                  <option value="users">Users</option>
                  <option value="managers">Managers</option>
                  <option value="admins">Admins</option>
                </select>
              </div>
              <div class="mb-4">
                <label for="priority" class="form-label">Priority</label>
                <select class="form-select" id="priority" name="priority">
                  <option value="High">High</option>
                  <option value="Medium" selected>Medium</option>
                  <option value="Low">Low</option>
                </select>
              </div>
              <div class="text-center">
                <button type="submit" class="btn btn-primary w-100">Submit</button>
              </div>
            </form>

          </div>
          <div class="card-footer text-start">
            <small>&copy; Verztec</small>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- JS -->
  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>

  <!-- Auto-dismiss alert -->
  <script>
    setTimeout(() => {
      const alert = document.querySelector('.alert-success');
      if (alert) {
        alert.style.transition = 'opacity 0.5s ease-out';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
      }
    }, 5000);
  </script>
</body>
</html>
