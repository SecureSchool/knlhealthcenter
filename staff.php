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

// Handle delete action
if(isset($_GET['delete_id'])){
    $delete_id = intval($_GET['delete_id']);
    $row = mysqli_fetch_assoc(mysqli_query($link, "SELECT status, full_name FROM tblstaff WHERE staff_id='$delete_id'"));
    
    if($row['status'] !== 'Active'){
        mysqli_query($link, "DELETE FROM tblstaff WHERE staff_id='$delete_id'");
        
        // Log deletion
        $admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';
        logAction($link, "Delete", "Staff", $row['full_name'], $admin_name);

        header("Location: staff.php?deleted=1");
        exit;
    } else {
        header("Location: staff.php?error_active=1");
        exit;
    }
}

// Handle toggle status action
if(isset($_GET['toggle_id'])){
    $toggle_id = intval($_GET['toggle_id']);
    $row = mysqli_fetch_assoc(mysqli_query($link, "SELECT status, full_name FROM tblstaff WHERE staff_id='$toggle_id'"));
    $new_status = ($row['status'] === 'Active') ? 'Inactive' : 'Active';
    mysqli_query($link, "UPDATE tblstaff SET status='$new_status' WHERE staff_id='$toggle_id'");

    // Log status change
    $admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';
    $action_text = $new_status === 'Active' ? "Activate" : "Deactivate";
    logAction($link, $action_text, "Staff", $row['full_name'], $admin_name);

    header("Location: staff.php?status_changed=1");
    exit;
}
// Handle search
$search_name = $_GET['search_name'] ?? '';

// Pagination settings
$limit = 10; // number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;

// Count total staff
$count_sql = "SELECT COUNT(*) as total FROM tblstaff WHERE 1";
if($search_name){
    $safe_name = mysqli_real_escape_string($link, $search_name);
    $count_sql .= " AND full_name LIKE '%$safe_name%'";
}
$count_res = mysqli_query($link, $count_sql);
$total_rows = mysqli_fetch_assoc($count_res)['total'];
$total_pages = ceil($total_rows / $limit);
if($total_pages < 1) $total_pages = 1;
if($page > $total_pages) $page = $total_pages;

$offset = ($page - 1) * $limit;

// Fetch staff members
$sql = "SELECT * FROM tblstaff WHERE 1";
if($search_name){
    $sql .= " AND full_name LIKE '%$safe_name%'";
}
$sql .= " ORDER BY staff_id DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($link, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff - Admin Dashboard</title>
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
.sidebar h2 {text-align:center; margin-bottom:35px; font-size:24px; font-weight:700;}
.sidebar a {color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;}
.sidebar a i {margin-right:12px; font-size:18px;}
.sidebar a:hover {background:rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active {background:rgba(255,255,255,0.25); font-weight:600;}

/* Main content */
.main {margin-left:250px; padding:30px; flex:1;}
h1 {margin-bottom:20px; color:#001BB7; font-weight:700;}
form.filter-form {margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;}
input[type=text] {padding:8px 12px; border:1px solid #ddd; border-radius:8px; flex:1;}
button.filter-btn {background:#001BB7; color:white; padding:8px 15px; border:none; border-radius:8px; cursor:pointer; font-weight:600;}
.add-btn {background:#27ae60; color:white; padding:8px 15px; border-radius:8px; text-decoration:none; font-weight:600;}
table {width:100%; border-collapse:collapse; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08); background:white;}
th, td {padding:12px; text-align:center; border-bottom:1px solid #eee;}
th {background:#001BB7; color:white; font-weight:600;}
tr:hover {background:#f1f1f1;}

/* Buttons */
.action-btn {padding:6px 10px; border:none; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; margin:2px;}
.edit-btn {background:#27ae60; color:white;}
.delete-btn {background:#c0392b; color:white;}
.delete-btn.disabled {background:#bdc3c7; cursor:not-allowed;}
.toggle-btn {background:#e67e22; color:white;}
.view-btn {background:#001BB7; color:white;}
.status-active {color:green; font-weight:600;}
.status-inactive {color:red; font-weight:600;}
.action-btn i {margin:0; font-size:16px;}

/* Responsive */
@media(max-width:1024px){.main{margin-left:250px; padding:25px;}}
@media(max-width:768px){.sidebar{width:200px; padding:20px;} .main{margin-left:200px; padding:20px;}}
@media(max-width:480px){.sidebar{width:100%; position:relative; height:auto; flex-direction:row; overflow-x:auto;} .sidebar h2{display:none;} .sidebar a{flex:1; text-align:center; margin:0 5px;} .main{margin-left:0; padding:15px;}}
.sidebar h2 i {font-size:28px; vertical-align:middle;}
.sidebar h2 {font-size:24px; font-weight:700; text-align:center; margin-bottom: 20px;}
.pagination{margin-top:10px;}
.pagination .page-item.active .page-link{background-color:#001BB7;border-color:#001BB7;color:white;}
.pagination .page-link{color:#001BB7;}
/* Hamburger visible only on mobile */
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
  z-index: 1500;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

/* Overlay */
#overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.25s ease, visibility 0.25s;
  z-index: 1400;
}
#overlay.active {
  opacity: 1;
  visibility: visible;
}

/* Mobile Sidebar */
@media (max-width: 768px) {
  .hamburger {
    display: block;
  }

  .sidebar {
    position: fixed;
    top: 0;
    left: -250px; /* hidden initially */
    width: 250px;
    height: 100%;
    background: #001BB7;
    padding: 25px 15px;
    flex-direction: column;
    transition: left 0.3s ease;
    z-index: 1450;
    overflow-y: auto;
  }

  .sidebar.active {
    left: 0;
  }

  .main {
    margin-left: 0;
    padding: 20px;
  }
}
  table th, table td {font-size: 12px; padding: 8px;}
}
/* ===== DARK MODE (PATIENT MANAGEMENT) ===== */
body.dark-mode {
    background: #0b1220 !important;
    color: #f5f7fa !important;
}

/* Sidebar */
body.dark-mode .sidebar {
    background: #0b1430 !important;
}
body.dark-mode .sidebar a {
    color: #e9f2ff !important;
}
body.dark-mode .sidebar a:hover,
body.dark-mode .sidebar a.active {
    background: rgba(255,255,255,0.18) !important;
    color: #fff !important;
}

/* Header */
body.dark-mode .header-container {
    background: linear-gradient(90deg, #0f1a38, #00122b) !important;
    color: #fff !important;
}

/* Main content */
body.dark-mode .main {
    background: #0b1220 !important;
}

/* Cards */
body.dark-mode .card {
    background: #111b2b !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.6) !important;
    color: #fff !important;
}

/* Table */
body.dark-mode table {
    background: #0b1220 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.18) !important;
}
body.dark-mode table th {
    background: #0b1430 !important;
    color: #fff !important;
}
body.dark-mode table td {
    border-bottom: 1px solid rgba(255,255,255,0.12) !important;
}
body.dark-mode table tr:hover {
    background: rgba(255,255,255,0.06) !important;
}

/* Text */
body.dark-mode h1,
body.dark-mode h2,
body.dark-mode h3,
body.dark-mode p,
body.dark-mode label,
body.dark-mode span,
body.dark-mode th,
body.dark-mode td {
    color: #f5f7fa !important;
}

/* Buttons */
body.dark-mode .btn {
    color: #fff !important;
    border-color: rgba(255,255,255,0.25) !important;
}

body.dark-mode .btn-primary {
    background-color: #0b1430 !important;
}
body.dark-mode .btn-success {
    background-color: #1f7a3d !important;
}
body.dark-mode .btn-warning {
    background-color: #d97706 !important;
}
body.dark-mode .btn-danger {
    background-color: #c0392b !important;
}
body.dark-mode .btn-info {
    background-color: #0b6fb5 !important;
}
body.dark-mode .btn-light {
    background: #1a2337 !important;
    color: #fff !important;
}

/* Review Patients Button (IMPORTANT) */
body.dark-mode .review-btn,
body.dark-mode .btn-review {
    background: #0b6fb5 !important;  /* strong color */
    color: #fff !important;
    border: 1px solid #0b6fb5 !important;
}

/* Pagination */
body.dark-mode .pagination .page-link {
    background: #1a2337 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.25) !important;
}
body.dark-mode .pagination .page-item.active .page-link {
    background: #0b1430 !important;
}

/* Inputs */
body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea {
    background: #1a2337 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.3) !important;
}

/* Badges */
body.dark-mode .badge {
    background: #1a2337 !important;
    color: #fff !important;
}
body.dark-mode .badge-success {
    background: #1f7a3d !important;
}
body.dark-mode .badge-danger {
    background: #c0392b !important;
}

/* Modal */
body.dark-mode .modal-content {
    background: #111b2b !important;
    color: #fff !important;
}
body.dark-mode .modal-header,
body.dark-mode .modal-body,
body.dark-mode .modal-footer {
    color: #fff !important;
}
body.dark-mode .modal-backdrop {
    background: rgba(0,0,0,0.7) !important;
}

/* Dropdown */
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
/* ===== SweetAlert ViewStaff Card ===== */
.swal-card{
    max-width: 400px;
    margin: auto;
    border-radius: 15px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.swal-header{
    background: #001BB7;
    padding: 20px;
    text-align: center;
    color: #fff;
}

.swal-avatar{
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid white;
}

.swal-name{
    margin: 10px 0 0 0;
    font-size: 24px;
    font-weight: 700;
}

.swal-status{
    display:inline-block;
    padding: 5px 12px;
    border-radius: 12px;
    font-weight: 600;
    margin-top: 5px;
}

.swal-body{
    padding: 20px;
    text-align: left;
    color: #1e293b;
}

.swal-body p{
    margin: 8px 0;
}

.swal-footer{
    text-align:center;
    padding:15px;
    background:#f5f7fa;
}

.swal-close-btn{
    background:#001BB7;
    color:white;
    border:none;
    padding:10px 25px;
    border-radius:10px;
    font-weight:600;
    cursor:pointer;
    transition:0.3s;
}

/* ===== DARK MODE for ViewStaff ===== */
body.dark-mode .swal-card{
    background: #111b2b !important;
    color: #fff !important;
    box-shadow: 0 8px 20px rgba(0,0,0,0.6) !important;
}

body.dark-mode .swal-header{
    background: #0b1430 !important;
}

body.dark-mode .swal-body{
    color: #f5f7fa !important;
}

body.dark-mode .swal-footer{
    background: #0b1220 !important;
}

body.dark-mode .swal-close-btn{
    background: #0b1430 !important;
}
body.dark-mode .btn-success { background:#1f7a3d !important; }
body.dark-mode .btn-danger { background:#c0392b !important; }
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
    <a href="staff.php" class="active"><i class="fas fa-user-tie"></i> Staff</a>
    <a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a>
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
    <h1><i class="fas fa-user-tie me-2"></i> Staff Management</h1>

    <form method="GET" class="filter-form">
        <input type="text" name="search_name" placeholder="Search by name" value="<?php echo htmlspecialchars($search_name); ?>">
        <button type="submit" class="filter-btn" title="Search">
    <i class="fas fa-search"></i> Search
</button>

        <a href="add_staff.php" class="add-btn">+ Add Staff</a>
    <a href="staff_export_excel.php?search_name=<?php echo urlencode($search_name); ?>" 
       class="btn btn-success">
        <i class="fas fa-file-excel"></i> Export Excel
    </a>

    <a href="staff_export_pdf.php?search_name=<?php echo urlencode($search_name); ?>" 
       class="btn btn-danger">
        <i class="fas fa-file-pdf"></i> Export PDF
    </a>

    </form>
    <table>
    <tr>
        <th>Profile</th>
        <th>Full Name</th>
        <th>Email</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
    <?php if(mysqli_num_rows($result) > 0): ?>
        <?php while($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td>
                <?php if(!empty($row['profile_pic'])): ?>
                    <img src="uploads/staff/<?php echo htmlspecialchars($row['profile_pic']); ?>" 
                         alt="Profile" style="width:50px; height:50px; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                    <img src="uploads/staff/default.png" 
                         alt="Profile" style="width:50px; height:50px; border-radius:50%; object-fit:cover;">
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
            <td><?php echo htmlspecialchars($row['email']); ?></td>
            <td>
                <?php if($row['status'] === 'Active'): ?>
                    <span class="status-active">Active</span>
                <?php else: ?>
                    <span class="status-inactive">Inactive</span>
                <?php endif; ?>
            </td>
            <td>
                <button class="action-btn view-btn"
                    onclick="viewStaff('<?php echo $row['staff_id']; ?>', '<?php echo htmlspecialchars($row['full_name']); ?>', '<?php echo htmlspecialchars($row['username']); ?>', '<?php echo htmlspecialchars($row['email']); ?>', '<?php echo htmlspecialchars($row['date_created']); ?>', '<?php echo $row['status']; ?>', '<?php echo $row['profile_pic']; ?>')">
                    <i class="fas fa-eye"></i>
                </button>
                <a href="edit_staff.php?id=<?php echo $row['staff_id']; ?>" class="action-btn edit-btn"><i class="fas fa-edit"></i></a>
                <?php if($row['status'] === 'Active'): ?>
                    <button class="action-btn delete-btn disabled" title="Cannot delete active staff" disabled>
                        <i class="fas fa-trash-alt"></i>
                    </button>
                <?php else: ?>
                    <button class="action-btn delete-btn" onclick="confirmDelete(<?php echo $row['staff_id']; ?>)">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                <?php endif; ?>
                <button class="action-btn toggle-btn" onclick="confirmToggle(<?php echo $row['staff_id']; ?>, '<?php echo $row['status']; ?>')"
                    title="<?php echo ($row['status'] === 'Active') ? 'Deactivate' : 'Activate'; ?>">
                    <i class="fas fa-exchange-alt"></i>
                </button>
            </td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="7" style="text-align:center;">No staff members found.</td></tr>
    <?php endif; ?>
</table>

    <!-- Simple Pagination -->
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
        text: "You are about to delete this staff member!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#c0392b',
        cancelButtonColor: '#7f8c8d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            window.location.href = 'staff.php?delete_id=' + id;
        }
    });
}

function confirmToggle(id, currentStatus){
    let action = currentStatus === 'Active' ? 'deactivate' : 'activate';
    Swal.fire({
        title: 'Are you sure?',
        text: `You are about to ${action} this staff member!`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e67e22',
        cancelButtonColor: '#7f8c8d',
        confirmButtonText: `Yes, ${action} it!`,
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            window.location.href = 'staff.php?toggle_id=' + id;
        }
    });
}
function viewStaff(staffId, fullName, username, email, dateCreated, status, profilePic){
    let imgSrc = profilePic ? `uploads/staff/${profilePic}` : 'uploads/staff/default.png';
    let statusColor = status === 'Active' ? '#27ae60' : '#c0392b';

    Swal.fire({
        html: `
        <div class="swal-card">
            <div class="swal-header">
                <img src="${imgSrc}" alt="Profile" class="swal-avatar">
                <h2 class="swal-name">${fullName}</h2>
                <span class="swal-status" style="background:${statusColor};">${status}</span>
            </div>

            <div class="swal-body">
                <p><strong>Staff ID:</strong> ${staffId}</p>
                <p><strong>Username:</strong> ${username}</p>
                <p><strong>Email:</strong> ${email}</p>
                <p><strong>Date Created:</strong> ${dateCreated}</p>
            </div>

            <div class="swal-footer">
                <button class="swal-close-btn" onclick="Swal.close()">Close</button>
            </div>
        </div>
        `,
        showConfirmButton: false,
        width: 450,
        padding: 0
    });
}


// Notifications
<?php if(isset($_GET['added'])): ?>
Swal.fire({icon:'success', title:'Added!', text:'Staff member successfully added.', timer:2000, showConfirmButton:false});
<?php endif; ?>
<?php if(isset($_GET['updated'])): ?>
Swal.fire({icon:'success', title:'Updated!', text:'Staff member successfully updated.', timer:2000, showConfirmButton:false});
<?php endif; ?>
<?php if(isset($_GET['deleted'])): ?>
Swal.fire({icon:'success', title:'Deleted!', text:'Staff member successfully deleted.', timer:2000, showConfirmButton:false});
<?php endif; ?>
<?php if(isset($_GET['status_changed'])): ?>
Swal.fire({icon:'success', title:'Status Changed!', text:'Staff status successfully updated.', timer:2000, showConfirmButton:false});
<?php endif; ?>
<?php if(isset($_GET['error_active'])): ?>
Swal.fire({icon:'error', title:'Error!', text:'Cannot delete an active staff member.', timer:3000, showConfirmButton:false});
<?php endif; ?>
</script>
<script>
document.getElementById('logoutBtn').addEventListener('click', function(e){
    e.preventDefault();
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
  const expanded = sidebar.classList.contains('active');
  hamburger.setAttribute('aria-expanded', expanded);
  document.body.style.overflow = expanded ? 'hidden' : '';
});

overlay.addEventListener('click', () => {
  sidebar.classList.remove('active');
  overlay.classList.remove('active');
  hamburger.setAttribute('aria-expanded', 'false');
  document.body.style.overflow = '';
});

// Close sidebar when clicking links on mobile
document.querySelectorAll('.sidebar a').forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 768) {
      sidebar.classList.remove('active');
      overlay.classList.remove('active');
      hamburger.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }
  });
});
</script>

</body>
</html>
