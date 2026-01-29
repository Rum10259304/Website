<?php
require __DIR__ . '/../vendor/autoload.php';

include 'auto_log_function.php';
include __DIR__ . '/../connect.php';

$message = "";
// Assuming session is started and manager's department is stored in session
$managerDept = $_SESSION['department'] ?? null;

// Load all valid countries from the countries table into an array
$validCountries = [];
$result = $conn->query("SELECT country FROM countries");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $validCountries[] = $row['country'];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['excel_file']['tmp_name'])) {
    $file          = $_FILES['excel_file']['tmp_name'];
    $fileName      = $_FILES['excel_file']['name'];
    $insertedCount = 0;

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $data        = $spreadsheet->getActiveSheet()->toArray();

        // Validation loop: Check department and country before insertion
        for ($i = 1; $i < count($data); $i++) {
            $deptRaw = trim($data[$i][3]);    // 4th column = department
            $countryRaw = trim($data[$i][5]); // 6th column = country

            // Manager department check
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'MANAGER' && $deptRaw !== $managerDept) {
                throw new Exception("All users must belong to your department: $managerDept");
            }

            // Country validation check
            if (!in_array($countryRaw, $validCountries)) {
                throw new Exception("Please ensure that all users are assigned to a country that has been created in the system.");
            }
        }

        // If validation passed, insert rows
        for ($i = 1; $i < count($data); $i++) {
            list($nameRaw, $passRaw, $emailRaw, $deptRaw, $roleRaw, $countryRaw) = $data[$i];
            $name       = $conn->real_escape_string($nameRaw);
            $password   = password_hash($passRaw, PASSWORD_DEFAULT);
            $email      = $conn->real_escape_string($emailRaw);
            $department = $conn->real_escape_string($deptRaw);
            $role       = $conn->real_escape_string($roleRaw);
            $country    = $conn->real_escape_string($countryRaw);

            $sql = "
                INSERT INTO users 
                    (username, password, email, department, role, country)
                VALUES 
                    ('$name', '$password', '$email', '$department', '$role', '$country')
            ";
            if ($conn->query($sql)) {
                $insertedCount++;
            }
        }

        $message = "Import completed. $insertedCount users added.";

        if (isset($_SESSION['user_id'])) {
            $adminId = $_SESSION['user_id'];
            $details = "Uploaded user file '$fileName'. $insertedCount users added.";
            log_action($conn, $adminId, 'users', 'add', $details);
        }
    } catch (Exception $e) {
        $message = "Import failed: " . $e->getMessage();
    }
}


if (!empty($message)) {
    echo "<script>
      document.addEventListener('DOMContentLoaded', function() {
        const modalBody = document.getElementById('excelMessageBody');
        modalBody.textContent = " . json_encode($message) . ";
        const modal = new bootstrap.Modal(document.getElementById('excelMessageModal'));
        modal.show();
      });
    </script>";
}


// Fetch total user count for header
$res       = $conn->query("SELECT COUNT(*) AS cnt FROM users");
$userCount = $res->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
  <base href="../">
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Verztec Admin</title>
  <link rel="icon" href="images/favicon.ico">
  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/font-awesome.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  
  <style>
    html, body { height:100%; margin:0 }
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
      width: 240px;
    }
    .search-box input {
      border: none;
      padding: .375rem .75rem .375rem 2.5rem;
      border-radius: 8px;
      width: 100%;
    }
    .search-box i {
      position: absolute; left:.75rem; top:50%;
      transform:translateY(-50%); color:#999;
    }
    .filter-dropdown .dropdown-toggle {
      background: #fff; border:1px solid #ddd;
      border-radius:8px; color:#333;
    }
    .filter-dropdown .dropdown-toggle::after {
      margin-left:.5em;
      border-top:.3em solid #333;
      border-right:.3em solid transparent;
      border-left:.3em solid transparent;
    }
    .filter-dropdown .dropdown-toggle:hover {
      background:#000; color:#fff; border-color:#000;
    }
    .filter-dropdown .dropdown-toggle:hover::after {
      border-top-color:#fff;
    }
    .table-container {
      background: #fff;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      flex-grow: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      height: 500px; /* Set a fixed height for scrolling */
    }

    .table-container table {
      margin: 0;
      border-collapse: collapse;
      width: 100%;
    }

    .table-container table thead th {
      background: #212529;
      color: #fff;
      position: sticky;
      top: 0;
      z-index: 1;
    }

    .table-container table thead th:first-child {
      border-top-left-radius: 8px;
    }

    .table-container table thead th:last-child {
      border-top-right-radius: 8px;
    }
 
    #users-table thead th {
      position: sticky;
      top: 0;
      background: #212529;
      color: white;
      z-index: 10;
    }


  </style>
</head>
<body>
    <!-- page header area -->
    <header class="header-area" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 999; background: white;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-xl-3 col-md-4 col-6">
                    <div class="page-logo-wp">
                        <a href="home.php">
                            <img src="images/logo.png" alt="">
                        </a>
                    </div>
                </div>
                <div class="col-xl-6 col-md-5 order-3 order-md-2 d-flex justify-content-center justify-content-md-start">
                    <div class="page-menu-wp">
                        <ul>
                            <li><a href="home.php">Home</a></li>
                            <li><a href="chatbot.html">Chatbot</a></li>
                            <li><a href="files.php">Files</a></li>
                            <li class = "active"><a href="#">Admin</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-3 col-6 d-flex justify-content-end order-2 order-md-3">
                    <div class="page-user-icon profile">
                        <button>
                            <img src="images/Profile-Icon.svg" alt="">
                        </button>
                        <div class="menu">
                            <ul>
                                <li><a href="#"><i class="fa-regular fa-user"></i><span>Profile</span></a></li>
                                <li><a href="#"><i class="fa-regular fa-message-smile"></i><span>Inbox</span></a></li>
                                <li><a href="#"><i class="fa-regular fa-gear"></i><span>Settings</span></a></li>
                                <li><a href="#"><i class="fa-regular fa-square-question"></i><span>Help</span></a></li>
                                <li><a href="login.php"><i class="fa-regular fa-right-from-bracket"></i><span>Sign Out</span></a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- page header area -->

  <div class="container-fluid">
    <div class="row">
      <!-- Sidebar -->
      <div class="col-md-2">
        <div class="sidebar-card">
          <ul class="nav flex-column">
            <li class="nav-item">
              <a class="nav-link active d-flex align-items-center" href="#">
                <i class="fa fa-users me-2"></i> Users
              </a>
            </li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN'): ?>
              <li class="nav-item">
                <a class="nav-link d-flex align-items-center" href="admin/audit_log.php">
                  <i class="fa fa-clock-rotate-left me-2"></i> Audit Log
                </a>
              </li>
            <?php endif; ?>
            <li class="nav-item">
              <a class="nav-link d-flex align-items-center" href="admin/announcement.php">
                <i class="fa fa-bullhorn me-2"></i> Announcements
              </a>
            </li>
          </ul>
        </div>
      </div>

      <!-- Main -->
      <div class="col-md-10 d-flex flex-column px-4" style="height:calc(100vh - 320px);">
        <!-- Header + Count -->
        <div class="mb-2">
          <h4 class="fw-bold">
            Users (<span id="userCount"><?= $userCount ?></span>)
          </h4>
        </div>

        <!-- Controls -->
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="search-box">
            <i class="fa fa-search"></i>
            <input type="text" id="tableSearch" placeholder="Search user">
          </div>
          <div class="d-flex align-items-center gap-2">
            <div class="dropdown filter-dropdown">
              <button class="btn dropdown-toggle" id="deptFilterBtn" data-bs-toggle="dropdown">
                Department: All
              </button>
              <div class="dropdown-menu p-3" id="deptFilterMenu" style="max-height:300px;overflow-y:auto;"></div>
            </div>
            <div class="dropdown filter-dropdown">
              <button class="btn dropdown-toggle" id="countryFilterBtn" data-bs-toggle="dropdown">
                Country: All
              </button>
              <div class="dropdown-menu p-3" id="countryFilterMenu" style="max-height:300px;overflow-y:auto;"></div>
            </div>
            <button class="btn btn-dark d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addCountryModal">
              <i class="fa fa-globe me-2"></i> Add Country
            </button>
            <button class="btn btn-dark d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addUserModal">
              <i class="fa fa-user-plus me-2"></i> Add User
            </button>
            <button class="btn btn-dark d-flex align-items-center" onclick="document.getElementById('excel_file').click();">
              <i class="fa fa-upload me-2"></i> Upload File
            </button>
            <form method="POST" enctype="multipart/form-data" style="display:none;">
              <input type="file" name="excel_file" id="excel_file" accept=".xls,.xlsx" onchange="this.form.submit()">
            </form>
          </div>
        </div>

        <!-- Table -->
        <div class="table-container">
          <table id="users-table" class="table table-hover mb-0 w-100">
            <thead id="users-table" class="table-dark">
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Department</th>
                <th>Role</th>
                <th>Country</th>
                <th></th>
              </tr>
            </thead>
          </table>
        </div>
      </div>
    </div>
  </div>


  <!-- FETCH DISTINCT COUNTIRES FROM DATABASE -->
  <?php
    $countries = [];
    $result = $conn->query("SELECT DISTINCT country FROM countries WHERE country IS NOT NULL AND country != '' ORDER BY country ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $countries[] = $row['country'];
        }
    }
  ?>

  <!-- ADD USER MODAL -->
  <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="addUserForm">
          <div class="modal-header">
            <h5 class="modal-title" id="addUserModalLabel">Add User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body bg-white">
            <div class="mb-3">
              <label>Username</label>
              <input type="text" class="form-control" id="add-username" name="username" required>
            </div>
            <div class="mb-3">
              <label>Password</label>
              <input type="password" class="form-control" id="add-password" name="password" required>
            </div>
            <div class="mb-3">
              <label>Email</label>
              <input type="email" class="form-control" id="add-email" name="email" required>
            </div>
            <div class="mb-3">
              <label>Department</label>
              <input 
                type="text" 
                class="form-control" 
                id="add-department" 
                name="department"
                <?php
                  if (isset($_SESSION['role']) && $_SESSION['role'] === 'MANAGER') {
                      $dept = isset($_SESSION['department']) ? htmlspecialchars($_SESSION['department']) : '';
                      echo 'value="' . $dept . '" disabled readonly';
                  }
                ?>
              >
            </div>
            <div class="mb-3">
              <label>Role</label>
              <select class="form-select" id="add-role" name="role" required>
                <option>ADMIN</option>
                <option>MANAGER</option>
                <option selected>USER</option>
              </select>
            </div>
            <div class="mb-3">
              <label>Country</label>
              <select class="form-select" id="add-country" name="country" required>
                <option value="">Select a country</option>
                <?php foreach ($countries as $country): ?>
                  <option value="<?= htmlspecialchars($country) ?>"><?= htmlspecialchars($country) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-dark">Add User</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- EDIT USER MODAL -->
  <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="editUserForm">
          <div class="modal-header">
            <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="edit-user-id" name="user_id">
            <div class="mb-3">
              <label>Username</label>
              <input type="text" class="form-control" id="edit-username" name="username" required>
            </div>
            <div class="mb-3">
              <label>Password</label>
              <div class="input-group">
                <input type="password" class="form-control" id="edit-password" name="password" disabled readonly>
                <button type="button" class="btn btn-outline-secondary" id="reset-password-btn">Reset</button>
              </div>
              <small class="form-text text-muted">Click reset to change password.</small>
            </div>
            <div class="mb-3">
              <label>Email</label>
              <input type="email" class="form-control" id="edit-email" name="email" required>
            </div>
            <div class="mb-3">
              <label>Department</label>
              <input 
                type="text" 
                class="form-control" 
                id="edit-department" 
                name="department" required
                <?php
                  if (isset($_SESSION['role']) && $_SESSION['role'] === 'MANAGER') {
                      $dept = isset($_SESSION['department']) ? htmlspecialchars($_SESSION['department']) : '';
                      echo 'value="' . $dept . '" disabled readonly';
                  }
                ?>
              >
            </div>
            <div class="mb-3">
              <label>Role</label>
              <select class="form-control" id="edit-role" name="role">
                <option>ADMIN</option>
                <option>MANAGER</option>
                <option>USER</option>
              </select>
            </div>
            <div class="mb-3">
              <label>Country</label>
              <div class="d-flex gap-2">
                <select class="form-select flex-grow-1" id="edit-country" name="country" required>
                  <option value="">Select a country</option>
                  <?php foreach ($countries as $country): ?>
                    <option value="<?= htmlspecialchars($country) ?>"><?= htmlspecialchars($country) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-dark">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ADD COUNTRY MODAL -->
  <div class="modal fade" id="addCountryModal" tabindex="-1" aria-labelledby="addCountryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form id="addCountryForm">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add New Country</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="new-country-name" class="form-label">Country Name</label>
              <input type="text" class="form-control" id="new-country-name" name="new_country" required>
            </div>
            <div id="country-error" class="text-danger small d-none"></div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-dark">Add Country</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- COUNTRY SUCCESS MESSAGE MODEL -->
  <div class="modal fade" id="countryMessageModal" tabindex="-1" aria-labelledby="countryMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="countryMessageModalLabel">Status</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="countryMessageBody">
          <!-- Message inserted dynamically -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Excel Upload Message Modal -->
  <div class="modal fade" id="excelMessageModal" tabindex="-1" aria-labelledby="excelMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="excelMessageModalLabel">Upload Status</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="excelMessageBody">
          <!-- Message goes here -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>

  

  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script>
    // Add User AJAX
    $('#addUserForm').on('submit', function(e){
      e.preventDefault();
      let valid = true;
      ['username','password','email','department','role','country'].forEach(f=>{
        const el = $('#add-'+f);
        if(!el.val().trim()){ el.addClass('is-invalid'); valid=false; }
        else el.removeClass('is-invalid');
      });
      if(!valid) return;
      fetch('admin/add_user.php',{method:'POST',body:new FormData(this)})
        .then(r=>r.text()).then(d=>{
          if(d.trim()==='success'){
            $('#addUserModal').modal('hide');
            this.reset();
            $('#add-role').val('USER');
            table.ajax.reload(null,false);
          } else alert('Error: '+d);
        }).catch(err=>alert(err));
    });

    // Initialize DataTable
    const table = $('#users-table').DataTable({
      dom: 'rt',
      paging: false,
      info: false,
      lengthChange: false,
      ajax: {url:'admin/fetch_users.php', dataSrc:''},
      columns: [
        {data:'user_id'}, {data:'username'}, {data:'email'},
        {data:'department'}, {data:'role'}, {data:'country'},
        {
          data:null, orderable:false,
          render:d=>`
            <button class="edit-user btn-link" data-userid="${d.user_id}"
                    data-username="${d.username}"
                    data-email="${d.email}"
                    data-department="${d.department}"
                    data-role="${d.role}"
                    data-country="${d.country}">
              <i class="fa fa-edit"></i>
            </button>
            <button class="delete-user btn-link" data-userid="${d.user_id}">
              <i class="fa fa-trash"></i>
            </button>`
        }
      ]
    });

    // Update count
    table.on('xhr.dt', function(e, s, json){
      $('#userCount').text(json.length);
    });

    // Search hook
    $('#tableSearch').on('input', ()=>table.search($('#tableSearch').val()).draw());

    // Populate filters
    table.on('xhr.dt', function(){
      const data = table.ajax.json();
      $('#deptFilterMenu').empty().append(
        [...new Set(data.map(u=>u.department))].sort().map(v=>`
          <div class="form-check">
            <input class="form-check-input dept-checkbox" type="checkbox" value="${v}">
            <label class="form-check-label">${v}</label>
          </div>`).join('')
      );
      $('#countryFilterMenu').empty().append(
        [...new Set(data.map(u=>u.country))].sort().map(v=>`
          <div class="form-check">
            <input class="form-check-input country-checkbox" type="checkbox" value="${v}">
            <label class="form-check-label">${v}</label>
          </div>`).join('')
      );
    });


    // Custom filter
    $.fn.dataTable.ext.search.push((settings,row)=>{
      const sd = $('.dept-checkbox:checked').map((_,e)=>e.value).get();
      const sc = $('.country-checkbox:checked').map((_,e)=>e.value).get();
      return (sd.length===0||sd.includes(row[3]))
          &&(sc.length===0||sc.includes(row[5]));
    });

    // update button labels on filter change
    $('#deptFilterMenu').on('change','input[type="checkbox"]', function(){
      const sel = $('.dept-checkbox:checked').map((_,e)=>e.value).get();
      $('#deptFilterBtn').text('Department: ' + (sel.length ? sel.join(', ') : 'All'));
      table.draw();
    });
    $('#countryFilterMenu').on('change','input[type="checkbox"]', function(){
      const sel = $('.country-checkbox:checked').map((_,e)=>e.value).get();
      $('#countryFilterBtn').text('Country: ' + (sel.length ? sel.join(', ') : 'All'));
      table.draw();
    });

    // Delete
    $('#users-table').on('click','.delete-user',function(){
      const id=$(this).data('userid');
      if(confirm('Delete this user?')){
        $.post('admin/delete_users.php',{user_id:id},res=>{
          if(res.trim()==='success') table.ajax.reload(null,false);
          else alert('Delete failed: '+res);
        });
      }
    });

    // Edit
    $('#users-table').on('click','.edit-user',function(){
      const d=$(this).data();
      $('#edit-user-id').val(d.userid);
      $('#edit-username').val(d.username);
      $('#edit-password').val('•••••••••').prop('disabled',true).prop('readonly',true);
      $('#edit-email').val(d.email);
      $('#edit-department').val(d.department);
      $('#edit-role').val(d.role);
      $('#edit-country').val(d.country);
      $('#editUserModal').modal('show');
    });

    // Reset password
    $('#reset-password-btn').click(()=>{
      $('#edit-password').prop('disabled',false).prop('readonly',false).val('').focus();
    });

    // Submit edit
    $('#editUserForm').on('submit', function(e){
      e.preventDefault();
      if($('#edit-password').prop('disabled')){
        $('#edit-password').prop('disabled',false).val('');
      }
      $.post('admin/update_user.php',$(this).serialize(),res=>{
        if(res.trim()==='success'){
          alert('Updated');
          $('#editUserModal').modal('hide');
          table.ajax.reload(null,false);
        } else alert('Update failed: '+res);
      });
    });

   

  </script>
  <script>
    document.getElementById("addCountryForm").addEventListener("submit", function(e) {
      e.preventDefault();

      const newCountry = document.getElementById("new-country-name").value.trim();
      const errorBox = document.getElementById("country-error");
      const messageBody = document.getElementById("countryMessageBody");
      const messageModal = new bootstrap.Modal(document.getElementById("countryMessageModal"));

      // Hide previous error message if any
      errorBox.classList.add('d-none');

      if (!newCountry) {
        errorBox.textContent = 'Please enter a country name.';
        errorBox.classList.remove('d-none');
        return;
      }

      fetch('admin/add_country.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'new_country=' + encodeURIComponent(newCountry)
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Add to dropdown
          const countrySelect = document.getElementById("add-country");
          const option = new Option(newCountry, newCountry, true, true);
          countrySelect.add(option);

          // Reset form and close add modal
          document.getElementById("new-country-name").value = '';
          bootstrap.Modal.getInstance(document.getElementById("addCountryModal")).hide();

          // Show success modal
          messageBody.textContent = 'Country added successfully!';
          messageModal.show();
        } else {
          errorBox.textContent = data.message || 'Country already exists or error occurred.';
          errorBox.classList.remove('d-none');
        }
      })
      .catch(() => {
        errorBox.textContent = 'Error adding country.';
        errorBox.classList.remove('d-none');
      });
    });
    </script>

    <!-- FixedHeader CSS and JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.dataTables.min.css">
    <script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>

    <!-- Session Timeout -->
    <script src="js/inactivity.js"></script>

</body>
</html>
