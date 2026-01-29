<?php
session_start();
include __DIR__ . '/../connect.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Common inputs
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $target_audience = $_POST['target_audience'] ?? '';
    $priority = $_POST['priority'] ?? '';

    if ($conn->connect_error) {
        $error = 'Database connection error.';
    } else {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO announcements (title, context, target_audience, priority) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $title, $content, $target_audience, $priority);

        } else if ($action === 'edit' && $id > 0) {
            $stmt = $conn->prepare("UPDATE announcements SET title=?, context=?, target_audience=?, priority=? WHERE id=?");
            $stmt->bind_param("ssssi", $title, $content, $target_audience, $priority, $id);

        } else if ($action === 'delete' && $id > 0) {
            $stmt = $conn->prepare("DELETE FROM announcements WHERE id=?");
            $stmt->bind_param("i", $id);

        } else {
            $error = 'Invalid action or missing ID.';
        }

        if (isset($stmt)) {
            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = 'Database operation failed: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch announcements to display
$sql = "SELECT id, title, context, priority, target_audience, timestamp FROM announcements ORDER BY timestamp DESC";
$result = $conn->query($sql);

if ($result) {
    $announcements = $result->fetch_all(MYSQLI_ASSOC);
    $count = count($announcements);
    $result->free();
} else {
    $announcements = [];
    $count = 0;
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en-US">
<head>
  <base href="../" />
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>Verztec Admin – Announcements</title>
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="css/bootstrap.css" />
  <link rel="stylesheet" href="css/font-awesome.css" />
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="css/responsive.css" />
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
  <style>
    html, body { height:100%; margin:0; }
    body {
      background: #f2f3fa;
      padding-top: 160px;
      padding-bottom: 160px;
    }
    .sidebar-card {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      margin: 1rem;
      padding: 1rem;
      min-height: calc(100vh - 320px);
    }
    .sidebar-card .nav-link {
      color: #333;
      margin-bottom: .75rem;
      border-radius: 6px;
      padding: .75rem 1rem;
    }
    .sidebar-card .nav-link.active {
      background-color: #FFD050;
      color: #000;
    }
    .search-box {
      position: relative;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      width: 250px;
    }
    .search-box input {
      border: none;
      padding: .375rem .75rem .375rem 2.5rem;
      width: 100%;
      border-radius: 8px;
    }
    .search-box i {
      position: absolute;
      left: .75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
    }
    .table-container {
      background: #fff;
      border-radius: 8px;
      overflow-y: auto;
      height: 900px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }
    .table-container table thead th {
      background: #212529;
      color: #fff;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    .table-container table thead th:first-child {
      border-top-left-radius: 8px;
    }
    .table-container table thead th:last-child {
      border-top-right-radius: 8px;
    }
    .btn-link {
      text-decoration: none;
      cursor: pointer;
      font-size: 1.2rem;
    }
    /* Make the last column (actions) wider */
    #announcementTable th:last-child,
    #announcementTable td:last-child {
      width: 120px;  /* Adjust this width as needed */
      text-align: center;
      white-space: nowrap; /* prevent line breaks inside buttons */
    }
    #announcementTable th:nth-child(3),
    #announcementTable td:nth-child(3),
    #announcementTable th:nth-child(4),
    #announcementTable td:nth-child(4) {
      text-align: left;
    }
    #announcementTable th:nth-child(4),
    #announcementTable td:nth-child(4) {
      width: 180px;
      text-align: left;
      white-space: nowrap;
    }
    #announcementTable td:nth-child(5),
    #announcementTable th:nth-child(5) {
      min-width: 80px; /* or whatever width you want */
      white-space: nowrap; /* so timestamp text stays on one line */
    }
    #announcementTable {
      width: 100%;
    }
    #announcementTable td {
      white-space: normal !important; /* allow wrapping */
      word-break: normal; /* default behavior, no breaking inside words */
      overflow-wrap: break-word; /* this helps wrapping at spaces if needed */
    }
    #announcementTable td:nth-child(2) { /* message column */
      max-width: 450px; /* limit width */
    }
    #announcementTable td:nth-child(1) {
      max-width: 150px; /* limit width for title */
    }
  </style>
</head>
<body>

  <!-- Fixed Header -->
  <header class="header-area" style="position:fixed;top:0;left:0;width:100%;z-index:999;background:white;">
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
                <i class="fa fa-clock-rotate-left me-2"></i> Audit log
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link active d-flex align-items-center" href="admin/announcement.php">
                <i class="fa fa-bullhorn me-2"></i> Announcements
              </a>
            </li>
          </ul>
        </div>
      </div>

      <!-- Main -->
      <div class="col-md-10 d-flex flex-column px-4" style="height:calc(100vh - 320px);">
        <div class="mb-2">
          <h4 class="fw-bold">Announcements (<?= $count ?>)</h4>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="search-box">
            <i class="fa fa-search"></i>
            <input type="text" id="tableSearch" class="form-control" placeholder="Search announcements" />
          </div>
          <button class="btn btn-dark" id="add-ann-btn">
            <i class="fa fa-plus me-2"></i> Add Announcement
          </button>
        </div>

        <div class="table-container">
          <table class="table" id="announcementTable">
            <thead>
              <tr>
                <th>Title</th>
                <th>Message</th>
                <th>Priority</th>
                <th>Target Audience</th>
                <th>Date</th>
                <th></th> <!-- No header label for actions -->
              </tr>
            </thead>
            <tbody>
              <?php foreach ($announcements as $row): ?>
              <tr
                data-id="<?= $row['id'] ?>"
                data-title="<?= htmlspecialchars($row['title'] ?? '', ENT_QUOTES) ?>"
                data-content="<?= htmlspecialchars($row['context'] ?? '', ENT_QUOTES) ?>"
                data-priority="<?= htmlspecialchars($row['priority'] ?? '', ENT_QUOTES) ?>"
                data-target_audience="<?= htmlspecialchars($row['target_audience'] ?? '', ENT_QUOTES) ?>"
              >
                <td><?= htmlspecialchars($row['title'] ?? 'Untitled') ?></td>
                <td><?= nl2br(htmlspecialchars($row['context'] ?? 'No message')) ?></td>
                <td><?= htmlspecialchars($row['priority'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['target_audience'] ?? '') ?></td>
                <td>
                  <?= isset($row['timestamp']) ? date("d M Y, H:i", strtotime($row['timestamp'])) : 'N/A' ?>
                </td>
                <td>
                  <button
                    class="edit-announcement btn-link"
                    data-id="<?= $row['id'] ?>"
                    data-title="<?= htmlspecialchars($row['title'] ?? '', ENT_QUOTES) ?>"
                    data-content="<?= htmlspecialchars($row['context'] ?? '', ENT_QUOTES) ?>"
                    data-priority="<?= htmlspecialchars($row['priority'] ?? '', ENT_QUOTES) ?>"
                    data-target_audience="<?= htmlspecialchars($row['target_audience'] ?? '', ENT_QUOTES) ?>"
                    title="Edit Announcement"
                  >
                    <i class="fa fa-edit"></i>
                  </button>
                  <button
                    class="delete-announcement btn-link ms-2"
                    data-id="<?= $row['id'] ?>"
                    data-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>"
                    title="Delete Announcement"
                  >
                    <i class="fa fa-trash"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ADD MODAL -->
  <div class="modal fade" id="addAnnouncementModal" tabindex="-1" aria-labelledby="addAnnouncementModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form id="addAnnouncementForm" method="POST" action="admin/announcement.php">
        <input type="hidden" name="action" value="add" />
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addAnnouncementModalLabel">Add Announcement</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <label for="add-title" class="form-label">Title</label>
            <input type="text" class="form-control mb-3" id="add-title" name="title" required />
            <label for="add-content" class="form-label">Message</label>
            <textarea class="form-control mb-3" id="add-content" name="content" rows="4" required></textarea>
            <label for="add-priority" class="form-label">Priority</label>
            <select class="form-select mb-3" id="add-priority" name="priority" required>
              <option value="Low">Low</option>
              <option value="Medium" selected>Medium</option>
              <option value="High">High</option>
            </select>
            <label for="add-target-audience" class="form-label">Target Audience</label>
            <select class="form-select" id="add-target-audience" name="target_audience" required>
              <option value="Users" selected>Users</option>
              <option value="Managers">Managers</option>
              <option value="Admins">Admins</option>
            </select>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-dark">Add</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- EDIT MODAL -->
  <div class="modal fade" id="editAnnouncementModal" tabindex="-1" aria-labelledby="editAnnouncementModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form id="editAnnouncementForm" method="POST" action="admin/announcement.php">
        <input type="hidden" name="action" value="edit" />
        <input type="hidden" id="edit-id" name="id" />
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="editAnnouncementModalLabel">Edit Announcement</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <label for="edit-title" class="form-label">Title</label>
            <input type="text" class="form-control mb-3" id="edit-title" name="title" required />
            <label for="edit-content" class="form-label">Message</label>
            <textarea class="form-control mb-3" id="edit-content" name="content" rows="4" required></textarea>
            <label for="edit-priority" class="form-label">Priority</label>
            <select class="form-select mb-3" id="edit-priority" name="priority" required>
              <option value="Low">Low</option>
              <option value="Medium">Medium</option>
              <option value="High">High</option>
            </select>
            <label for="edit-target-audience" class="form-label">Target Audience</label>
            <select class="form-select" id="edit-target-audience" name="target_audience" required>
              <option value="Users">Users</option>
              <option value="Managers">Managers</option>
              <option value="Admins">Admins</option>
            </select>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-dark">Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- DELETE MODAL -->
  <div class="modal fade" id="deleteAnnouncementModal" tabindex="-1" aria-labelledby="deleteAnnouncementModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form id="deleteAnnouncementForm" method="POST" action="admin/announcement.php">
        <input type="hidden" name="action" value="delete" />
        <input type="hidden" id="delete-id" name="id" />
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteAnnouncementModalLabel">Delete Announcement</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete this announcement?</p>
            <p><strong id="delete-title"></strong></p>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-danger">Delete</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

  <script>
    $(document).ready(function () {
      const table = $('#announcementTable').DataTable({
        paging: false,
        dom: "rt",
      });

      $('#tableSearch').on('input', function () {
        table.search(this.value).draw();
      });

      // Show Add modal
      $('#add-ann-btn').on('click', function () {
        $('#addAnnouncementForm')[0].reset();
        $('#addAnnouncementModal').modal('show');
      });

      // Fill Edit modal
      $('#announcementTable').on('click', '.edit-announcement', function () {
        const tr = $(this).closest('tr');
        $('#edit-id').val(tr.data('id'));
        $('#edit-title').val(tr.data('title'));
        $('#edit-content').val(tr.data('content'));
        $('#edit-priority').val(tr.data('priority'));
        $('#edit-target-audience').val(tr.data('target_audience'));
        $('#editAnnouncementModal').modal('show');
      });

      // Fill Delete modal
      $('#announcementTable').on('click', '.delete-announcement', function () {
        const tr = $(this).closest('tr');
        $('#delete-id').val(tr.data('id'));
        $('#delete-title').text(tr.data('title'));
        $('#deleteAnnouncementModal').modal('show');
      });
    });
  </script>

  <!-- Session Timeout -->
  <script src="js/inactivity.js"></script>
</body>

</html>

