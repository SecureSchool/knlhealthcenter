<?php
session_start();
require_once "config.php";
// --- LOG FUNCTION ---
function logAction($link, $action, $module, $performedto, $performedby){
    $date = date('Y-m-d');
    $time = date('H:i:s');

    $stmt = mysqli_prepare($link, 
        "INSERT INTO tbllogs (datelog, timelog, action, module, performedto, performedby) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "ssssss", $date, $time, $action, $module, $performedto, $performedby);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Redirect if not logged in as admin
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

// Handle delete action (prevent deleting Active doctors)
if(isset($_GET['delete_id'])){
    $delete_id = intval($_GET['delete_id']);
    $row = mysqli_fetch_assoc(mysqli_query($link, "SELECT status, fullname FROM tbldoctors WHERE doctor_id='$delete_id'"));
    
    if($row['status'] !== 'Active'){
        mysqli_query($link, "DELETE FROM tbldoctors WHERE doctor_id='$delete_id'");

        // Log delete action
        $admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';
        logAction($link, "Delete", "Doctors", $row['fullname'], $admin_name);

        header("Location: doctors.php?deleted=1");
        exit;
    } else {
        header("Location: doctors.php?error_active=1");
        exit;
    }
}

// Handle toggle status action
if(isset($_GET['toggle_id'])){
    $toggle_id = intval($_GET['toggle_id']);
    $row = mysqli_fetch_assoc(mysqli_query($link, "SELECT status, fullname FROM tbldoctors WHERE doctor_id='$toggle_id'"));
    $new_status = ($row['status'] === 'Active') ? 'Inactive' : 'Active';
    mysqli_query($link, "UPDATE tbldoctors SET status='$new_status' WHERE doctor_id='$toggle_id'");

    // Log status change
    $admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';
    $action_text = $new_status === 'Active' ? "Activate" : "Deactivate";
    logAction($link, $action_text, "Doctors", $row['fullname'], $admin_name);

    header("Location: doctors.php?status_changed=1");
    exit;
}

// Handle search
$search_name = $_GET['search_name'] ?? '';

// Pagination setup
$limit = 5; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;

// Count total
$count_sql = "SELECT COUNT(*) as total FROM tbldoctors WHERE 1";
if($search_name){
    $count_sql .= " AND fullname LIKE '%$search_name%'";
}
$count_res = mysqli_query($link, $count_sql);
$total_rows = mysqli_fetch_assoc($count_res)['total'];
$total_pages = ceil($total_rows / $limit);
if($total_pages < 1) $total_pages = 1;
if($page > $total_pages) $page = $total_pages;

$offset = ($page - 1) * $limit;

// Fetch doctors
$sql = "SELECT * FROM tbldoctors WHERE 1";
if($search_name){
    $sql .= " AND fullname LIKE '%$search_name%'";
}
$sql .= " ORDER BY doctor_id DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($link, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctors - Admin Dashboard</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b;}

/* Sidebar */
.sidebar {
    width:250px;
    background:#001BB7;
    color:#fff;
    position:fixed;
    height:100%;
    padding:25px 15px;
    display:flex;
    flex-direction:column;
}
.sidebar h2 {
    text-align:center;
    margin-bottom:20px;
    font-size:24px;
    font-weight:700;
}
.sidebar a {
    color:#ffffffcc;
    display:flex;
    align-items:center;
    padding:12px 18px;
    margin:10px 0;
    text-decoration:none;
    border-radius:12px;
    transition:0.3s;
    font-weight:500;
}
.sidebar a i {
    margin-right:12px;
    font-size:18px;
}
.sidebar a:hover {background:rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active {background:rgba(255,255,255,0.25); font-weight:600;}

/* Main content */
.main {margin-left:250px; padding:30px; flex:1;}
h1 {margin-bottom:20px; color:#001BB7; font-weight:700;}
form.filter-form {margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;}
input[type=text] {padding:8px 12px; border:1px solid #ddd; border-radius:8px; flex:1;}
button.filter-btn, .add-btn {padding:8px 14px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:0.2s;}
button.filter-btn {background:#001BB7; color:white;}
.add-btn {background:#27ae60; color:white; text-decoration:none; display:flex; align-items:center; justify-content:center;}
button.filter-btn:hover {background:#001199;}
.add-btn:hover {background:#1e8449;}

/* Table */
table {width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);}
th, td {padding:14px; text-align:center; border-bottom:1px solid #e0e0e0;}
th {background:#001BB7; color:white; font-weight:600;}
tr:hover {background:#f1f3f6;}
.status-active {color:#27ae60; font-weight:600;}
.status-inactive {color:#c0392b; font-weight:600;}

/* Action buttons */
.action-btn {padding:6px 10px; border:none; border-radius:6px; cursor:pointer; font-size:16px; display:inline-flex; align-items:center; justify-content:center; margin:2px; transition:0.2s;}
.edit-btn {background:#27ae60; color:white;}
.delete-btn {background:#c0392b; color:white;}
.delete-btn.disabled {background:#bdc3c7; cursor:not-allowed;}
.toggle-btn {background:#e67e22; color:white;}
.view-btn {background:#001BB7; color:white;}
.action-btn i {margin:0; font-size:16px;}
.action-btn:hover:not(.disabled) {opacity:0.85;}

/* Responsive */
@media(max-width:1024px){.main {margin-left:250px; padding:25px;}}
@media(max-width:768px){.sidebar {width:200px; padding:20px;} .main {margin-left:200px; padding:20px;}}
@media(max-width:480px){.sidebar {width:100%; position:relative; height:auto; flex-direction:row; overflow-x:auto;} .sidebar h2 {display:none;} .sidebar a {flex:1; text-align:center; margin:0 5px;} .main {margin-left:0; padding:15px;}}
.sidebar h2 i {
    font-size:28px;
    vertical-align:middle;
}
.sidebar h2 {
    font-size:24px;
    font-weight:700;
    text-align:center;
}
.pagination{margin-top:10px;}
.pagination .page-item.active .page-link{background-color:#001BB7;border-color:#001BB7;color:white;}
.pagination .page-link{color:#001BB7;}
/* Hamburger base */
.hamburger {
  display: none;
  position: fixed;
  top: 15px;
  left: 15px;
  font-size: 20px;
  color: #001BB7;
  background: #fff;
  border: none;
  border-radius: 8px;
  padding: 8px 10px;
  cursor: pointer;
  z-index: 1200; /* above everything */
}

/* Overlay */
#overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.25s ease, visibility 0.25s;
  z-index: 1050;
}
#overlay.active {
  opacity: 1;
  visibility: visible;
}

/* Responsive sidebar for tablets and phones */
@media (max-width:1024px) {
  .hamburger { display:block; }

  .sidebar {
      transform: translateX(-250px); /* hidden by default, matches width */
      position: fixed;
      top: 0;
      left: 0;
      width: 250px;
      height: 100%;
      background: #001BB7;
      color: #fff;
      padding: 25px 15px;
      display: flex;
      flex-direction: column;
      transition: transform 0.3s ease;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      z-index: 1100;
  }

  .sidebar.active {
      transform: translateX(0);
  }

  .main {
      margin-left: 0;  /* reset margin for smaller screens */
      padding: 15px;
  }

  .cards {
      grid-template-columns: 1fr;
  }

  ul.quick-links {
      grid-template-columns: 1fr;
  }
}

  table th, table td {font-size: 12px; padding: 8px;}
}
/* DARK MODE STYLES (CLEAR TEXT) */
body.dark-mode {
    background: #0b1220 !important;
    color: #f5f7fa !important;
}

/* Sidebar */
body.dark-mode .sidebar {
    background: #0b1430 !important;
}
body.dark-mode .sidebar a {
    color: #e9f2ff !important; /* brighter */
}
body.dark-mode .sidebar a:hover {
    background: rgba(255,255,255,0.18) !important; /* brighter hover */
    color: #fff !important;
}
body.dark-mode .sidebar a.active {
    background: rgba(255,255,255,0.25) !important;
    color: #fff !important;
}

/* Main content */
body.dark-mode .main {
    background: #0b1220 !important;
}

/* Header */
body.dark-mode .header-container {
    background: linear-gradient(90deg, #0f1a38, #00122b) !important;
    color: #fff !important;
}

/* Page Title */
body.dark-mode h1 {
    color: #e9f2ff !important;
}

/* Cards */
body.dark-mode .card {
    background: #111b2b !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.6) !important;
    color: #fff !important;
}
body.dark-mode .card h3,
body.dark-mode .card p {
    color: #fff !important;
}

/* Quick links */
body.dark-mode .quick-links li {
    background: #1a2a5c !important;
}
body.dark-mode .quick-links li:hover {
    background: #22356f !important;
}
body.dark-mode .quick-links li a {
    color: #fff !important;
}

/* Buttons */
body.dark-mode .btn-primary,
body.dark-mode .btn-success,
body.dark-mode .btn-warning,
body.dark-mode .btn-info,
body.dark-mode .btn-light {
    color: #fff !important;
    border-color: rgba(255,255,255,0.25) !important;
}

/* Specific button colors for readability */
body.dark-mode .btn-primary {
    background-color: #0b1430 !important;
    border-color: #0b1430 !important;
}
body.dark-mode .btn-success {
    background-color: #1f7a3d !important;
    border-color: #1f7a3d !important;
}
body.dark-mode .btn-warning {
    background-color: #d97706 !important;
    border-color: #d97706 !important;
}
body.dark-mode .btn-info {
    background-color: #0b6fb5 !important;
    border-color: #0b6fb5 !important;
}
body.dark-mode .btn-light {
    background: #1a2337 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.3) !important;
}

/* Texts */
body.dark-mode h1,
body.dark-mode h2,
body.dark-mode h3,
body.dark-mode p,
body.dark-mode td,
body.dark-mode th,
body.dark-mode span,
body.dark-mode label,
body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea {
    color: #f5f7fa !important;
}

/* Tables */
body.dark-mode table {
    background: #0b1220 !important;
    color: #fff !important;
}
body.dark-mode table th,
body.dark-mode table td {
    border-color: rgba(255,255,255,0.18) !important;
}
body.dark-mode table th {
    background: #0b1430 !important;
    color: #fff !important;
}
body.dark-mode tr:hover {
    background: rgba(255,255,255,0.08) !important;
}

/* Inputs */
body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea {
    background: #1a2337 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.3) !important;
}

/* Pagination */
body.dark-mode .pagination .page-link {
    color: #e9f2ff !important;
    background: #0b1430 !important;
    border: 1px solid rgba(255,255,255,0.2) !important;
}
body.dark-mode .pagination .page-item.active .page-link {
    background: #0b1430 !important;
    color: #fff !important;
    border-color: #0b1430 !important;
}

/* Action buttons */
body.dark-mode .action-btn {
    color: #fff !important;
}
body.dark-mode .edit-btn { background: #1f7a3d !important; }
body.dark-mode .delete-btn { background: #c0392b !important; }
body.dark-mode .toggle-btn { background: #e67e22 !important; }
body.dark-mode .view-btn { background: #0b1430 !important; }

/* Disabled button */
body.dark-mode .delete-btn.disabled {
    background: #444 !important;
    color: #ddd !important;
}

/* Dropdown menu */
body.dark-mode .dropdown-menu {
    background: #111b2b !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.2) !important;
}
body.dark-mode .dropdown-item {
    color: #fff !important;
}
body.dark-mode .dropdown-item:hover {
    background: rgba(255,255,255,0.15) !important;
}

/* SweetAlert */
body.dark-mode .swal2-popup {
    background: #111b2b !important;
    color: #fff !important;
}
body.dark-mode .swal2-title,
body.dark-mode .swal2-content {
    color: #fff !important;
}
#sendReminderBtn{
    background: linear-gradient(45deg,#001BB7,#4f7cff);
    border:none;
    color:white;
}
#sendReminderBtn:hover{
    background: linear-gradient(45deg,#001199,#3b5bff);
}
/* DARK MODE — EXACT SAME STYLE AS btn-light */
body.dark-mode #sendReminderBtn{
    background:#1a2337 !important;
    color:#ffffff !important;
    border:1px solid rgba(255,255,255,0.3) !important;
}

body.dark-mode #sendReminderBtn:hover{
    background:#24304d !important;
}
</style>
</head>
<body>
<!-- Hamburger Button (visible on mobile/tablet) -->
<button class="hamburger" aria-label="Open navigation menu" aria-expanded="false">
  <i class="fas fa-bars"></i>
</button>

<div class="sidebar">
    <h2>
    <a href="dashboard.php" style="text-decoration:none; color:white; display:flex; align-items:center; justify-content:center;">
        <img src="logo.png" alt="KNL Logo"
             style="width:45px; height:45px; margin-right:10px; border-radius:50%; object-fit:cover;">
        KNL Health Center
    </a>
</h2>

    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="patients.php"><i class="fas fa-users"></i> Patients</a>
    <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a>
    <a href="doctors.php" class="active"><i class="fas fa-user-md"></i> Doctors</a>
    <a href="services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="announcement.php"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<!-- Overlay -->
<div id="overlay" aria-hidden="true"></div>

<div class="main">
    <?php include 'admin_header.php'; ?>
    <h1><i class="fas fa-user-md me-2"></i> Doctors Management</h1>


    <form method="GET" class="filter-form">
        <input type="text" name="search_name" placeholder="Search by name" value="<?php echo htmlspecialchars($search_name); ?>">
        <button type="submit" class="filter-btn">
    <i class="fas fa-search"></i> Search
</button>

        <a href="add_doctor.php" class="add-btn">+ Add Doctor</a>
        <a href="doctors_excel.php?search_name=<?php echo urlencode($search_name); ?>" 
       class="btn btn-success me-2">
        <i class="fas fa-file-excel"></i> Export Excel
    </a>

    <a href="doctors_pdf.php?search_name=<?php echo urlencode($search_name); ?>" 
       class="btn btn-danger">
        <i class="fas fa-file-pdf"></i> Export PDF
    </a>

    </form>

    <table>
    <tr>
        <th>Profile</th>
        <th>Full Name</th>
        <th>Specialization</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
    <?php if(mysqli_num_rows($result) > 0): ?>
        <?php while($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td>
                <img src="uploads/doctors/<?php echo htmlspecialchars($row['profile_pic'] ?? 'default.png'); ?>" 
                     alt="Profile" width="50" height="50" 
                     style="border-radius:50%; object-fit:cover;">
            </td>
            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
            <td><?php echo htmlspecialchars($row['specialization']); ?></td>
            <td>
                <?php if($row['status'] === 'Active'): ?>
                    <span class="status-active">Active</span>
                <?php else: ?>
                    <span class="status-inactive">Inactive</span>
                <?php endif; ?>
            </td>
            <td>
                <!-- Actions remain unchanged -->

                    <button class="action-btn view-btn" 
    onclick="viewDoctor('<?php echo $row['doctor_id']; ?>', 
                        '<?php echo htmlspecialchars($row['fullname']); ?>', 
                        '<?php echo htmlspecialchars($row['username']); ?>', 
                        '<?php echo htmlspecialchars($row['specialization']); ?>', 
                        '<?php echo htmlspecialchars($row['contact_number']); ?>', 
                        '<?php echo htmlspecialchars($row['email']); ?>', 
                        '<?php echo htmlspecialchars($row['date_created']); ?>', 
                        '<?php echo $row['status']; ?>',
                        '<?php echo htmlspecialchars($row['profile_pic']); ?>')" 
    title="View"><i class="fas fa-eye"></i></button>


                    <a href="edit_doctor.php?id=<?php echo $row['doctor_id']; ?>" class="action-btn edit-btn" title="Edit"><i class="fas fa-edit"></i></a>

                    <?php if($row['status'] === 'Active'): ?>
                        <button class="action-btn delete-btn disabled" title="Cannot delete active doctor" disabled><i class="fas fa-trash-alt"></i></button>
                    <?php else: ?>
                        <button class="action-btn delete-btn" onclick="confirmDelete(<?php echo $row['doctor_id']; ?>)"><i class="fas fa-trash-alt"></i></button>
                    <?php endif; ?>

                    <button class="action-btn toggle-btn" onclick="confirmToggle(<?php echo $row['doctor_id']; ?>, '<?php echo $row['status']; ?>')" title="<?php echo ($row['status'] === 'Active') ? 'Deactivate' : 'Activate'; ?>"><i class="fas fa-exchange-alt"></i></button>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6" style="text-align:center;">No doctors found.</td></tr>
        <?php endif; ?>
    </table>
        <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-end mt-3">
            <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                <a class="page-link" href="?search_name=<?php echo urlencode($search_name); ?>&page=<?php echo max(1, $page-1); ?>">Previous</a>
            </li>
            <li class="page-item active">
                <span class="page-link"><?php echo $page; ?></span>
            </li>
            <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
                <a class="page-link" href="?search_name=<?php echo urlencode($search_name); ?>&page=<?php echo min($total_pages, $page+1); ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

</div>

<script>
function confirmDelete(id){
    Swal.fire({
        title: 'Are you sure?',
        text: "You are about to delete this doctor!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#c0392b',
        cancelButtonColor: '#7f8c8d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => { if(result.isConfirmed){ window.location.href = 'doctors.php?delete_id=' + id; } });
}

function confirmToggle(id, currentStatus){
    let action = currentStatus === 'Active' ? 'deactivate' : 'activate';
    Swal.fire({
        title: 'Are you sure?',
        text: `You are about to ${action} this doctor!`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e67e22',
        cancelButtonColor: '#7f8c8d',
        confirmButtonText: `Yes, ${action} it!`,
        cancelButtonText: 'Cancel'
    }).then((result) => { if(result.isConfirmed){ window.location.href = 'doctors.php?toggle_id=' + id; } });
}
function viewDoctor(doctorId, fullName, username, specialization, contact, email, dateCreated, status, profilePic){
    let imgSrc = profilePic ? `uploads/doctors/${profilePic}` : 'uploads/doctors/default.png';
    let statusColor = status === 'Active' ? '#27ae60' : '#c0392b';

    // Detect dark mode
    const isDark = document.body.classList.contains('dark-mode');

    Swal.fire({
        html: `
        <div style="
            max-width:400px; 
            margin:auto; 
            font-family:'Inter', sans-serif; 
            background:${isDark ? '#111b2b' : 'white'}; 
            border-radius:15px; 
            box-shadow:0 8px 20px rgba(0,0,0,0.15); 
            overflow:hidden;
        ">
            <!-- Header -->
            <div style="
                background:${isDark ? '#0b1430' : '#001BB7'}; 
                padding:20px; 
                text-align:center; 
                color:white;
            ">
                <img src="${imgSrc}" alt="Profile" style="width:120px; height:120px; border-radius:50%; object-fit:cover; border:3px solid white;">
                <h2 style="margin:10px 0 0 0; font-size:24px; font-weight:700;">${fullName}</h2>
                <span style="display:inline-block; padding:5px 12px; background:${statusColor}; color:white; border-radius:12px; font-weight:600; margin-top:5px;">${status}</span>
            </div>

            <!-- Body -->
            <div style="
                padding:20px; 
                text-align:left; 
                color:${isDark ? '#f5f7fa' : '#1e293b'};
                background:${isDark ? '#111b2b' : '#fff'};
            ">
                <p style="margin:8px 0;"><strong>Doctor ID:</strong> ${doctorId}</p>
                <p style="margin:8px 0;"><strong>Username:</strong> ${username}</p>
                <p style="margin:8px 0;"><strong>Specialization:</strong> ${specialization}</p>
                <p style="margin:8px 0;"><strong>Contact:</strong> ${contact}</p>
                <p style="margin:8px 0;"><strong>Email:</strong> ${email}</p>
                <p style="margin:8px 0;"><strong>Date Created:</strong> ${dateCreated}</p>
            </div>

            <!-- Footer -->
            <div style="
                text-align:center; 
                padding:15px; 
                background:${isDark ? '#0b1220' : '#f5f7fa'};
            ">
                <button style="
                    background:${isDark ? '#0b1430' : '#001BB7'}; 
                    color:white; 
                    border:none; 
                    padding:10px 25px; 
                    border-radius:10px; 
                    font-weight:600; 
                    cursor:pointer;
                    transition:0.3s;
                " onclick="Swal.close()">Close</button>
            </div>
        </div>
        `,
        showConfirmButton: false,
        width: 450,
        padding: 0
    });
}

<?php if(isset($_GET['deleted'])): ?>
Swal.fire({icon:'success', title:'Deleted!', text:'Doctor successfully deleted.', timer:2000, showConfirmButton:false});
<?php endif; ?>
<?php if(isset($_GET['status_changed'])): ?>
Swal.fire({icon:'success', title:'Status Changed!', text:'Doctor status successfully updated.', timer:2000, showConfirmButton:false});
<?php endif; ?>
<?php if(isset($_GET['error_active'])): ?>
Swal.fire({icon:'error', title:'Error!', text:'Cannot delete an active doctor.', timer:3000, showConfirmButton:false});
<?php endif; ?>
</script>
<script>
document.getElementById('logoutBtn').addEventListener('click', function(e){
    e.preventDefault(); // Prevent immediate redirect
    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out from the system.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#001BB7',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, log me out',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
});
// Sidebar toggle
// Sidebar toggle
const hamburger = document.querySelector('.hamburger');
const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('overlay');

hamburger.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
});

overlay.addEventListener('click', () => {
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
});

// NEW: Close sidebar on link click
const sidebarLinks = document.querySelectorAll('.sidebar a');
sidebarLinks.forEach(link => {
    link.addEventListener('click', () => {
        if (sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    });
});

</script>

</body>
</html>
