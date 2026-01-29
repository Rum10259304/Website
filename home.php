<?php
session_start();
include 'admin/auto_log_function.php';

if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'USER';
}

if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = 'John Doe';
}

// Function to get greeting based on time
function getGreeting() {
    $hour = date('H'); // Get current hour in 24-hour format
    
    if ($hour >= 5 && $hour < 12) {
        return 'Good Morning';
    } elseif ($hour >= 12 && $hour < 17) {
        return 'Good Afternoon';
    } elseif ($hour >= 17 && $hour < 22) {
        return 'Good Evening';
    } else {
        return 'Good Night';
    }
}

// Function to capitalize each word in a name
function capitalizeName($name) {
    return ucwords(strtolower(trim($name)));
}

$greeting = getGreeting();
$capitalizedName = capitalizeName($_SESSION['username']);
?> 

<!DOCTYPE html>
<html lang="en-US">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Verztec</title>
  <link rel="icon" href="images/favicon.ico">
  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/font-awesome.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <style>
    /* Override main layout to fit viewport */
    .page-content-wp {
      padding-top: 170px !important;
      padding-bottom: 20px !important;
    }
    
    /* Profile picture positioning */
    .sidebar-top {
      margin-top: 20px !important;
      padding: 20px !important;
    }
    
    .right-content-wp h2 {
      font-size: 40px !important;
      font-weight: 500 !important;
      margin-bottom: 15px !important;
    }
    
    .rc-content-box {
      box-shadow: 0px 4px 4px 0px #B6BBDB40;
      background-color: #fff;
      border-radius: 10px;
      padding: 25px 20px !important;
      margin-top: 15px !important;
      min-height: auto !important;
      height: calc(320px + 250px) !important;
    }
    
    .rc-content-box .row {
      margin-top: 20px;
    }
    
    .rc-content-box .contents {
      background: transparent !important;
      padding: 0 !important;
      margin-bottom: 0 !important;
    }
    
    .input-box {
      position: relative;
      margin-bottom: 1rem;
    }
    
    .input-box input {
      width: 100%;
      padding: .75rem 1rem;
      padding-left: 2rem;
      border-radius: 50px;
      border: 1px solid transparent;
      background:transparent;
      font-size: 1rem;
      color: #333;
      outline: none;
    }
    
    .input-box .search-icon {
      position: absolute;
      top: 50%;
      left: 1rem;
      transform: translateY(-50%);
      color: #999;
      font-size: 1.2rem;
      pointer-events: none;
    }
    
    .announcements-wp {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      margin-top: 1rem;
      transition: box-shadow 0.3s ease;
      height: 320px !important;
      overflow: hidden;
      overflow-y: auto;
      position: relative;
      display: flex;
      flex-direction: column;
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
    
    .announcements-wp::-webkit-scrollbar {
      display: none;
    }
    
    .announcements-wp h3 {
      font-size: 1.4rem;
      margin: 0;
      padding: 1rem 1.2rem;
      color: #fff;
      background-color: #81869E;
      border-radius: 8px 8px 0 0;
      border-bottom: 1px solid #ddd;
      position: sticky;
      top: 0;
      z-index: 1;
      user-select: none;
    }
    
    .announcement {
      border-bottom: 1px solid #eee;
      padding: 1rem 1.2rem;
      flex-shrink: 0;
    }
    
    .announcement:last-child {
      border-bottom: none;
      border-radius: 0 0 8px 8px;
      background-color: #fff;
    }
    
    .announcement-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.5rem;
    }
    .announcement-header h4 {
      font-size: 1.2rem;
      font-weight: 600;
      color: #000;
      margin: 0;
      flex: 1;
    }
    .priority-btn {
      border: none;
      border-radius: 20px;
      padding: 0.45rem 1.2rem;
      font-size: 1rem;
      font-weight: bold;
      color: #fff;
      cursor: default;
      user-select: none;
      white-space: nowrap;
      margin-left: 1rem;
      flex-shrink: 0;
    }
    .priority-high { background-color: #d9534f; }
    .priority-medium { background-color: #f0ad4e; }
    .priority-low { background-color: #5bc0de; }
    .announcement p {
      font-size: 1rem;
      color: #444;
      margin: 0;
      line-height: 1.5;
      white-space: normal;
    }
    .read-more {
      font-family: 'Gudea', sans-serif;
      font-style: italic;
      color: #2a4d9c;
    }
    .modal-content {
      border-radius: 12px; /* match your header's 12px */
      background-color: #fff; /* white background for modal content */
      overflow: hidden; /* clip overflow to avoid white corner issues */
      border: none !important;
      box-shadow: none !important;
    }
    /* Modal header with rounded top corners */
    .modal-header {
      background-color: #81869E; /* match header color */
      color: #fff;
      border-radius: 12px 12px 0 0;
      border-bottom: none; /* remove border if any */
    }
    /* Modal body should have no extra border radius, full width */
    .modal-body {
      background-color: #fff; /* ensure white */
      padding: 1rem 1.5rem;
      padding-left: 1rem;
      padding-right: 1rem;
      border-radius: 0; /* reset any inherited radius */
    }
    .modal-dialog {
      max-width: 600px; /* or your preferred width */
      margin: 1.75rem auto; /* centers the modal */
      padding-left: 1rem;
      padding-right: 1rem;
    }

    /* Additional responsive adjustments */
    @media (max-height: 800px) {
      .page-content-wp {
        padding-top: 150px !important;
        padding-bottom: 15px !important;
      }
      
      .right-content-wp h2 {
        font-size: 32px !important;
        margin-bottom: 10px !important;
      }
      
      .announcements-wp {
        height: 280px !important;
      }
      
      .rc-content-box {
        height: calc(280px + 210px) !important;
        padding: 20px 15px !important;
        margin-top: 10px !important;
      }
    }
    
    @media (max-height: 700px) {
      .page-content-wp {
        padding-top: 130px !important;
        padding-bottom: 10px !important;
      }
      
      .right-content-wp h2 {
        font-size: 28px !important;
        margin-bottom: 8px !important;
      }
      
      .announcements-wp {
        height: 240px !important;
      }
      
      .rc-content-box {
        height: calc(240px + 170px) !important;
        padding: 15px 10px !important;
        margin-top: 8px !important;
      }
    }

  </style>
</head>
<body>
  <header class="header-area">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-xl-3 col-md-4 col-6">
          <a href="home.php" class="page-logo-wp">
            <img src="images/logo.png" alt="Verztec">
          </a>
        </div>
        <div class="col-xl-6 col-md-5 order-3 order-md-2 d-flex justify-content-center justify-content-md-start">
          <div class="page-menu-wp">
            <ul>
              <li class="active"><a href="#">Home</a></li>
              <li><a href="chatbot.html">Chatbot</a></li>
              <li><a href="files.php">Files</a></li>
              <?php if ($_SESSION['role'] !== 'USER'): ?>
              <li><a href="admin/users.php">Admin</a></li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
        <div class="col-md-3 col-6 d-flex justify-content-end order-2 order-md-3">
          <div class="page-user-icon profile">
            <button><img src="images/Profile-Icon.svg" alt=""></button>
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

  <main class="page-content-wp">
    <div class="container-fluid">
      <div class="row gap-x-40">
        <div class="col-lg-3">
          <div class="left-sidebar">
            <div class="sidebar-top">
              <figure><img src="images/ellipse.png" alt=""></figure>
              <div class="content">
                <h3><?= htmlspecialchars($_SESSION['username']) ?></h3>
                <span><?= htmlspecialchars($_SESSION['department']) ?>/<?= htmlspecialchars($_SESSION['role']) ?></span>
                <div class="d-flex align-items-center gap-2 justify-content-center pt-1">
                  <img src="images/location.svg" alt="">
                  <span><?= htmlspecialchars($_SESSION['country']) ?></span>
                </div>
              </div>
            </div>

            <div class="announcements-wp">
              <h3>Announcements</h3>
              <?php
require_once 'connect.php';
$sql = "SELECT title, context, priority, target_audience, timestamp FROM announcements ORDER BY 
          CASE LOWER(priority)
              WHEN 'high' THEN 1
              WHEN 'medium' THEN 2
              WHEN 'low' THEN 3
              ELSE 4
          END, timestamp DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0):
  while ($row = $result->fetch_assoc()):
    $priority = strtolower($row['priority']);
    $priorityClass = 'priority-low';
    if ($priority === 'high') $priorityClass = 'priority-high';
    else if ($priority === 'medium') $priorityClass = 'priority-medium';

    $shortContent = mb_strimwidth(strip_tags($row['context']), 0, 70, '...');
    $safeFullContent = htmlspecialchars($row['context']);
    $safeTitle = htmlspecialchars($row['title']);
    $formattedDate = date('M d, Y h:i A', strtotime($row['timestamp']));
    $targetAudience = htmlspecialchars($row['target_audience']);
?>
  <div class="announcement">
    <div class="announcement-header">
      <h4><?= $safeTitle ?></h4>
      <button class="priority-btn <?= $priorityClass ?>"><?= ucfirst($priority) ?></button>
    </div>
    <p class="mb-1">Date: <?= $formattedDate ?></p>
    <p>
      <?= $shortContent ?>
      <a href="#" class="read-more" 
         data-bs-toggle="modal" 
         data-bs-target="#announcementModal"
         data-title="<?= $safeTitle ?>"
         data-full="<?= $safeFullContent ?>"
         data-priority="<?= ucfirst($priority) ?>"
         data-audience="<?= $targetAudience ?>"
         data-timestamp="<?= $formattedDate ?>">
         Read More
      </a>
    </p>
  </div>
<?php endwhile; else:
  echo "<p style='font-size: 1rem; padding: 1rem;'>No announcements found.</p>";
endif;
$conn->close();
?>
            </div>
          </div>
          
          <!-- Modal with additional fields -->
          <div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header" style="background-color:#81869E; color:#fff; border-radius: 12px 12px 0 0;">
                  <h5 class="modal-title" id="announcementModalLabel" style="font-family: 'Gudea', sans-serif; font-weight: normal;">Announcement</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="font-family: 'Open Sans', sans-serif; color:#000; line-height:1.6; font-size:1rem; padding: 1rem 1.5rem;">
                  <h5 id="modalTitle"></h5>
                  <p id="modalContent"></p>
                  <hr>
                  <p><strong>Target Audience:</strong> <span id="modalAudience"></span></p>
                  <p><strong>Priority:</strong> <span id="modalPriority"></span></p>
                  <p><strong>Posted:</strong> <span id="modalTimestamp"></span></p>
                </div>
              </div>
            </div>
          </div>
        </div>  <!-- End of col-lg-3 -->

        <div class="col-lg-9">
          <div class="right-content-wp">
            <h2><?= $greeting ?>, <?= htmlspecialchars($capitalizedName) ?>!</h2>
            <div class="rc-content-box">
              <div class="contents">
                <div class="input-box">
                  <i class="fa fa-search search-icon"></i>
                  <input id="activitySearch" type="text" placeholder="What are you looking for today?">
                </div>
                <div class="row g-x-4">
                  <div class="col-xl-4 col-lg-6">
                    <div class="single-acti-box">
                      <div class="d-flex align-items-center gap-2">
                        <img src="images/tabler_message-chatbot-filled.svg" alt="">
                        <p>AI Chat bot for all <br> your inquiries</p>
                      </div>
                      <div class="text-end"><a href="chatbot.html">Go Now</a></div>
                    </div>
                  </div>
                  <div class="col-xl-4 col-lg-6">
                    <div class="single-acti-box bg-green">
                      <div class="d-flex align-items-center gap-2">
                        <img src="images/mdi_files.svg" alt="">
                        <p>Files and Policies</p>
                      </div>
                      <div class="text-end"><a href="files.php">Go Now</a></div>
                    </div>
                  </div>
                  <?php if ($_SESSION['role'] !== 'USER'): ?>
                  <div class="col-xl-4 col-lg-6">
                    <div class="single-acti-box" style="background:#FCBD33;">
                      <div class="d-flex align-items-center gap-2">
                        <img src="images/mdi_settings.svg" alt="">
                        <p>Admin Page</p>
                      </div>
                      <div class="text-end"><a href="admin/users.php">Go Now</a></div>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>  <!-- End of col-lg-9 -->
      </div>
    </div>
  </main>

  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
  <script>
    (function(){
      const input = document.getElementById('activitySearch');
      input.addEventListener('input', function(){
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('.single-acti-box').forEach(box => {
          const txt = box.textContent.toLowerCase();
          box.parentElement.style.display = txt.includes(q) ? '' : 'none';
        });
      });
    })();

    const modal = document.getElementById('announcementModal');
    modal.addEventListener('show.bs.modal', function (event) {
      const trigger = event.relatedTarget;
      document.getElementById('modalTitle').textContent = trigger.getAttribute('data-title');
      document.getElementById('modalContent').textContent = trigger.getAttribute('data-full');
      document.getElementById('modalAudience').textContent = trigger.getAttribute('data-audience');
      document.getElementById('modalPriority').textContent = trigger.getAttribute('data-priority');
      document.getElementById('modalTimestamp').textContent = trigger.getAttribute('data-timestamp');
    });
  </script>
  <script src="js/inactivity.js"></script>
</body>
</html>


