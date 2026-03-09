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
if(isset($_GET['delete_id'])){
    $delete_id = $_GET['delete_id'];

    // First, get some info about the appointment for logging
    $stmt = mysqli_prepare($link, "SELECT a.appointment_id, p.full_name AS patient_name 
                                  FROM tblappointments a 
                                  JOIN tblpatients p ON a.patient_id = p.patient_id 
                                  WHERE a.appointment_id = ?");
    mysqli_stmt_bind_param($stmt, "s", $delete_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $appointment = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($appointment) {
        $stmt = mysqli_prepare($link, "DELETE FROM tblappointments WHERE appointment_id = ?");
        mysqli_stmt_bind_param($stmt, "s", $delete_id);
        if(mysqli_stmt_execute($stmt)){
            // Log the deletion
            $admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';
            $patient_name = $appointment['patient_name'] ?? 'Unknown Patient';
            logAction($link, "Delete", "Appointments", $patient_name, $admin_name);

            $_SESSION['success_delete'] = "Appointment deleted successfully!";
        } else {
            $_SESSION['error_delete'] = "Failed to delete appointment.";
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_delete'] = "Appointment not found.";
    }

    header("Location: appointments.php");
    exit;
}

// Search & status filter
$search_name = $_GET['search_name'] ?? '';
$safe_name = mysqli_real_escape_string($link, $search_name);

$status_filter = $_GET['status'] ?? '';
$safe_status = mysqli_real_escape_string($link, $status_filter);
$service_filter = $_GET['service'] ?? '';
$safe_service = mysqli_real_escape_string($link, $service_filter);
$start_date = $_GET['start_date'] ?? ''; // YYYY-MM-DD
$end_date = $_GET['end_date'] ?? '';     // YYYY-MM-DD
$safe_start_date = mysqli_real_escape_string($link, $start_date);
$safe_end_date = mysqli_real_escape_string($link, $end_date);

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Count total appointments
$total_sql = "SELECT COUNT(*) as total
              FROM tblappointments a
              JOIN tblpatients p ON a.patient_id = p.patient_id
              WHERE 1";
if($safe_name) $total_sql .= " AND p.full_name LIKE '%$safe_name%'";
if($safe_status) $total_sql .= " AND a.status='$safe_status'";
if($safe_service) $total_sql .= " AND a.service_id='$safe_service'";
if($safe_start_date && $safe_end_date) {
    $total_sql .= " AND a.appointment_date BETWEEN '$safe_start_date' AND '$safe_end_date'";
} elseif($safe_start_date) {
    $total_sql .= " AND a.appointment_date >= '$safe_start_date'";
} elseif($safe_end_date) {
    $total_sql .= " AND a.appointment_date <= '$safe_end_date'";
}


$total_result = mysqli_query($link, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);
$total_pages = max(1, $total_pages); // avoid zero pages

// Adjust current page
if($page < 1) $page = 1;
if($page > $total_pages) $page = $total_pages;

// Offset
$offset = ($page - 1) * $limit;

// Fetch appointments
$sql = "SELECT a.*, p.full_name AS patient_name, d.fullname AS doctor_name, 
        st.full_name AS assigned_by_name, s.service_name, a.cancel_reason
        FROM tblappointments a
        JOIN tblpatients p ON a.patient_id = p.patient_id
        LEFT JOIN tbldoctors d ON a.doctor_assigned = d.doctor_id
        LEFT JOIN tblstaff st ON a.assignedby = st.staff_id
        LEFT JOIN tblservices s ON a.service_id = s.service_id
        WHERE 1";

if($safe_name) $sql .= " AND p.full_name LIKE '%$safe_name%'";
if($safe_status) $sql .= " AND a.status='$safe_status'";
if($safe_service) $sql .= " AND a.service_id='$safe_service'";
if($safe_start_date && $safe_end_date) {
    $sql .= " AND a.appointment_date BETWEEN '$safe_start_date' AND '$safe_end_date'";
} elseif($safe_start_date) {
    $sql .= " AND a.appointment_date >= '$safe_start_date'";
} elseif($safe_end_date) {
    $sql .= " AND a.appointment_date <= '$safe_end_date'";
}

$sql .= " ORDER BY a.date_created DESC LIMIT $limit OFFSET $offset";

$result = mysqli_query($link, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appointments - Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
.sidebar a i {margin-right:12px; font-size:18px;}
.sidebar a:hover {background:rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active {background:rgba(255,255,255,0.25); font-weight:600;}

/* Main content */
.main {margin-left:250px; padding:30px; flex:1;}
h1 {margin-bottom:20px; color:#001BB7; font-weight:700;}

/* Filter form */
.filter-form {margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;}
input[type=text], select {padding:8px 10px; border:1px solid #ddd; border-radius:8px; flex:1;}
button.filter-btn {background:#001BB7; color:white; padding:8px 14px; border:none; border-radius:8px; cursor:pointer; display:flex; align-items:center; gap:5px;}

/* Tables */
table {width:100%; border-collapse:collapse; background:white; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);}
th, td {padding:12px; border-bottom:1px solid #eee; text-align:center;}
th {background:#001BB7; color:white; font-weight:600;}
tr:hover {background:#f1f5fb;}

/* Action buttons */
.view-btn, .delete-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:36px;
    height:36px;
    border-radius:8px;
    margin:2px 2px 2px 0;
    color:white;
    font-size:16px;
    cursor:pointer;
    transition:0.2s;
    text-decoration:none;
}
.view-btn {background:#001BB7;}
.delete-btn {background:#c0392b;}
.delete-btn.disabled {background:#7f8c8d; cursor:not-allowed;}
.view-btn:hover, .delete-btn:hover:not(.disabled){opacity:0.85;}

/* Responsive */
@media(max-width:768px){.main{margin-left:0; padding:20px;} table th, table td{font-size:12px; padding:8px;}}
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

/* Responsive Sidebar */
@media (max-width: 768px) {
  .hamburger { display: block; }

  .sidebar {
    transform: translateX(-280px);
    width: 250px;
    left: 0;
    top: 0;
    height: 100%;
    transition: transform 0.3s ease;
    z-index: 1100;
  }
  .sidebar.active { transform: translateX(0); }

  .main {
    margin-left: 0;
    padding: 20px;
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
/* ===== SweetAlert View Dark Mode ===== */
.swal-body {
    font-family: 'Inter', sans-serif;
    text-align: left;
}

.swal-card {
    background: #f5f7fa;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.swal-patient {
    margin: 0;
    font-weight: 700;
    font-size: 28px;
    color: #001BB7;
}

.swal-sub {
    margin: 2px 0;
}

.swal-info {
    background: #fff;
    padding: 15px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 15px;
}

.status-badge {
    color: #fff;
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 600;
}

.swal-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.swal-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #eee;
}

.swal-table td.info-title {
    font-weight: 600;
}

/* ===== DARK MODE for the Swal view ===== */
body.dark-mode .swal-card,
body.dark-mode .swal-info,
body.dark-mode .swal-table {
    background: #111b2b !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.6) !important;
}

body.dark-mode .swal-patient {
    color: #fff !important;
}

body.dark-mode .swal-sub,
body.dark-mode .swal-info p,
body.dark-mode .swal-table td {
    color: #f5f7fa !important;
}

body.dark-mode .swal-table td {
    border-bottom: 1px solid rgba(255,255,255,0.12) !important;
}

body.dark-mode .swal2-popup {
    background: #111b2b !important;
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
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch; /* smooth scrolling on iOS */
}
@media(max-width:768px){
    .filter-form {
        flex-direction: column;
        gap: 6px;
    }
    .filter-form input,
    .filter-form select,
    .filter-form button,
    .filter-form a {
        flex: 1 1 100%;
        width: 100%;
    }
}
@media(max-width:768px){
    .view-btn, .delete-btn {
        width: 28px;
        height: 28px;
        font-size: 14px;
    }
}
@media(max-width:768px){
    .pagination li.page-item:not(:first-child):not(:last-child){
        display:none; /* only Previous & Next visible */
    }
    .pagination .page-item.active{
        display:none;
    }
}
@media(max-width:1024px){ /* iPad */
    .sidebar {
        transform: translateX(-280px);
    }
    .sidebar.active {
        transform: translateX(0);
    }
    .main {
        margin-left: 0;
    }
    .hamburger {
        display: block;
    }
}
@media(max-width:768px){
    .filter-form a.btn {
        flex: 1 1 100%;
    }
}
@media(max-width:768px){
    table th:nth-child(4),
    table th:nth-child(5),
    table td:nth-child(4),
    table td:nth-child(5){
        display:none;
    }
}
@media(max-width:768px){
    table th:nth-child(4),
    table th:nth-child(5),
    table td:nth-child(4),
    table td:nth-child(5){
        display:none; /* hide Appointment Type & Queue # */
    }
}
@media(max-width:768px){
    table th, table td {
        font-size: 12px;
        padding: 6px 4px; /* smaller padding */
    }
}
@media(max-width:768px){
    .view-btn, .delete-btn {
        width: 28px;
        height: 28px;
        font-size: 14px;
        margin: 0 2px;
    }
}
@media (max-width: 768px) {
    .sidebar {
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        height: 100vh; /* ensure full viewport height */
    }
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
    <a href="appointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a>
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
    <h1><i class="fas fa-calendar"></i> Appointments Management</h1>
    <?php
// Fetch services for the dropdown
$services_result = mysqli_query($link, "SELECT service_id, service_name FROM tblservices ORDER BY service_name ASC");
?>

<!-- Filter form -->
<form method="GET" class="filter-form">
    <input type="text" name="search_name" placeholder="Search by patient name" 
           value="<?php echo htmlspecialchars($search_name); ?>" 
           style="padding:8px 10px; border-radius:8px; border:1px solid #ddd; flex:10;"> <!-- Longer input -->

    <select name="status" 
            style="padding:8px 10px; border-radius:8px; border:1px solid #ddd; flex:1;"> <!-- Smaller select -->
        <option value="">All Status</option>
        <option value="Pending" <?php if($status_filter=='Pending') echo 'selected'; ?>>Pending</option>
        <option value="Completed" <?php if($status_filter=='Completed') echo 'selected'; ?>>Completed</option>
        <option value="Dispensed" <?php if($status_filter=='Dispensed') echo 'selected'; ?>>Dispensed</option>
        <option value="Cancelled" <?php if($status_filter=='Cancelled') echo 'selected'; ?>>Cancelled</option>
    </select>

    <select name="service" style="padding:8px 10px; border-radius:8px; border:1px solid #ddd; flex:1;">
    <option value="">All Services</option>
    <?php while($service = mysqli_fetch_assoc($services_result)): ?>
        <option value="<?php echo $service['service_id']; ?>" 
            <?php if(($safe_service ?? '') == $service['service_id']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($service['service_name']); ?>
        </option>
    <?php endwhile; ?>
</select>

<input type="date" name="start_date" 
       value="<?php echo htmlspecialchars($start_date); ?>" 
       style="padding:8px 10px; border-radius:8px; border:1px solid #ddd; flex:1;"
       placeholder="Start Date">

<input type="date" name="end_date" 
       value="<?php echo htmlspecialchars($end_date); ?>" 
       style="padding:8px 10px; border-radius:8px; border:1px solid #ddd; flex:1;"
       placeholder="End Date">

    <button type="submit" class="filter-btn"><i class="fas fa-search"></i> Search</button>
    <!-- 🔹 Reset Button -->
    <button type="button" class="filter-btn" id="resetBtn" style="background:#7f8c8d;"><i class="fas fa-undo"></i> Reset</button>
    <!-- Export Buttons -->

    <a href="appointments_export_excel.php?search_name=<?php echo urlencode($search_name); ?>&status=<?php echo urlencode($status_filter); ?>&service=<?php echo urlencode($service_filter); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" 
       class="btn btn-success me-2">
        <i class="fas fa-file-excel"></i> Export Excel
    </a>

    <a href="appointments_export_pdf.php?search_name=<?php echo urlencode($search_name); ?>&status=<?php echo urlencode($status_filter); ?>&service=<?php echo urlencode($service_filter); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" 
       class="btn btn-danger">
        <i class="fas fa-file-pdf"></i> Export PDF
    </a>


</form>

<div class="table-responsive">
    <table>
        <tr>
    <th>Patient</th>
    <th>Doctor</th>
    <th>Services</th>
    <th>Appointment Type</th>
    <th>Queue #</th>
    <th>Date</th>
    <th>Time</th>
    <th>Status</th>
    <th>Actions</th>
</tr>

        <?php if(mysqli_num_rows($result) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
            <?php $isCompleted = ($row['status'] === 'Completed'); ?>
            <tr>
    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
    <td><?php echo htmlspecialchars($row['doctor_name'] ?? 'Not assigned'); ?></td>
    <td><?php echo htmlspecialchars($row['service_name'] ?? 'N/A'); ?></td>
    <td><?php echo htmlspecialchars($row['appointment_type'] ?? 'N/A'); ?></td>
    <td><?php echo htmlspecialchars($row['queue_number'] ?? '—'); ?></td>
    <td><?php echo $row['appointment_date']; ?></td>
    <td>
<?php 
    // Convert 24-hour time to 12-hour format with AM/PM
    echo date("h:i A", strtotime($row['appointment_time']));
?>
</td>
    <td><?php echo $row['status']; ?></td>
    <td>
        <span class="view-btn" onclick="viewAppointment(
            '<?php echo $row['appointment_id']; ?>',   // 🔹 Added ID
            '<?php echo htmlspecialchars($row['patient_name']); ?>',
            '<?php echo htmlspecialchars($row['doctor_name'] ?? 'Not assigned'); ?>',
            '<?php echo htmlspecialchars($row['assigned_by_name'] ?? 'N/A'); ?>',
            '<?php echo $row['appointment_date']; ?>',
            '<?php echo $row['appointment_time']; ?>',
            '<?php echo $row['status']; ?>',
            '<?php echo $row['date_created']; ?>',
            '<?php echo htmlspecialchars($row['service_name'] ?? 'N/A'); ?>',
            '<?php echo htmlspecialchars($row['medicine_notes'] ?? ''); ?>',
            '<?php echo htmlspecialchars($row['cancel_reason'] ?? ''); ?>',
        )" title="View"><i class="fas fa-eye"></i></span>

        <?php if($isCompleted): ?>
            <a href="#" class="delete-btn" data-id="<?php echo $row['appointment_id']; ?>" title="Delete"><i class="fas fa-trash-alt"></i></a>

        <?php else: ?>
            <span class="delete-btn disabled" title="Can only delete completed appointments"><i class="fas fa-trash-alt"></i></span>
        <?php endif; ?>
    </td>
</tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7" style="text-align:center;">No appointments found.</td></tr>
        <?php endif; ?>
    </table>
    </div>
<!-- Pagination -->
<?php if($total_pages > 1): ?>
<nav aria-label="Appointments Pagination">
    <ul class="pagination justify-content-end">
        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
            <a class="page-link" href="?search_name=<?php echo urlencode($search_name); ?>&status=<?php echo urlencode($status_filter); ?>&service=<?php echo urlencode($service_filter); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&page=<?php echo max(1,$page-1); ?>">Previous</a>

        </li>
        <li class="page-item active"><span class="page-link"><?php echo $page; ?></span></li>
        <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
            <a class="page-link" href="?search_name=<?php echo urlencode($search_name); ?>&status=<?php echo urlencode($status_filter); ?>&page=<?php echo min($total_pages,$page+1); ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
</div>

<script>
function viewAppointment(patient_id, patient, doctor, assignedby, date, time, status, created, service, medicine, cancelReason, reschedReason){
    const statusColors = {
        "Pending": "#f1c40f",
        "Approved": "#3498db",
        "Assigned": "#9b59b6",
        "Completed": "#2ecc71",
        "Dispensed": "#16a085",
        "Cancelled": "#e74c3c",
        "Rescheduled accepted": "#f39c12",
        "Rescheduled requested": "#d35400"
    };
    let badgeColor = statusColors[status] || "#7f8c8d";

    let extra = "";
    if(service === "Free Medicine" && medicine){
        extra += `<tr><td class="info-title"><i class="fas fa-pills"></i> Medicine Request:</td><td>${medicine}</td></tr>`;
    }
    if(status === "Cancelled" && cancelReason){
        extra += `<tr><td class="info-title"><i class="fas fa-times-circle"></i> Cancellation Reason:</td><td>${cancelReason}</td></tr>`;
    }
    if((status === "Rescheduled accepted" || status === "Rescheduled requested") && reschedReason){
        extra += `<tr><td class="info-title"><i class="fas fa-calendar-alt"></i> Reschedule Reason:</td><td>${reschedReason}</td></tr>`;
    }

    Swal.fire({
        title: '<strong>Appointment Details</strong>',
        html: `
        <div class="swal-body">
            <div class="swal-card">

                <p class="swal-patient">${patient}</p>
                <p class="swal-sub"><i class="fas fa-id-badge"></i> Patient ID: ${patient_id}</p>

                <div class="swal-info">
                    <p><i class="fas fa-user-md"></i> <strong>Doctor:</strong> ${doctor}</p>
                    <p><i class="fas fa-user-tie"></i> <strong>Assigned By:</strong> ${assignedby}</p>
                    <p><i class="fas fa-stethoscope"></i> <strong>Service:</strong> ${service}</p>
                    <p><i class="fas fa-calendar-alt"></i> <strong>Date:</strong> ${date}</p>
                    <p><i class="fas fa-clock"></i> <strong>Time:</strong> ${time}</p>
                    <p><i class="fas fa-info-circle"></i> <strong>Status:</strong> 
                        <span class="status-badge" style="background:${badgeColor};">${status}</span>
                    </p>
                    <p><i class="fas fa-calendar-check"></i> <strong>Created On:</strong> ${created}</p>
                </div>

                ${extra ? `<table class="swal-table">${extra}</table>` : ''}

            </div>
        </div>
        `,
        width: '700px',
        showCloseButton: true,
        confirmButtonText: 'Close',
        confirmButtonColor: '#001BB7'
    });
}

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

// SweetAlert delete confirmation
document.querySelectorAll('.delete-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault(); // prevent default link

        // Only allow if the button is not disabled
        if(btn.classList.contains('disabled')) return;

        let appointmentId = btn.getAttribute('data-id');

        Swal.fire({
            title: 'Are you sure?',
            text: "You are about to delete this appointment.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#001BB7',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if(result.isConfirmed){
                // Redirect to the PHP delete URL
                window.location.href = 'appointments.php?delete_id=' + appointmentId;
            }
        });
    });
});
// Sidebar toggle
const hamburger = document.querySelector('.hamburger');
const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('overlay');

function openMenu() {
  sidebar.classList.add('active');
  overlay.classList.add('active');
  hamburger.setAttribute('aria-expanded', 'true');
  document.body.style.overflow = 'hidden';
}

function closeMenu() {
  sidebar.classList.remove('active');
  overlay.classList.remove('active');
  hamburger.setAttribute('aria-expanded', 'false');
  document.body.style.overflow = '';
}

hamburger.addEventListener('click', () => {
  if (sidebar.classList.contains('active')) {
    closeMenu();
  } else {
    openMenu();
  }
});

overlay.addEventListener('click', closeMenu);

// Close menu when clicking a sidebar link (mobile only)
document.querySelectorAll('.sidebar a').forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 768) closeMenu();
  });
});

document.getElementById('resetBtn').addEventListener('click', function() {
    // Clear all input values
    document.querySelector('input[name="search_name"]').value = '';
    document.querySelector('select[name="status"]').value = '';
    document.querySelector('select[name="service"]').value = '';
    document.querySelector('input[name="start_date"]').value = '';
    document.querySelector('input[name="end_date"]').value = '';

    // Submit form without any GET parameters
    window.location.href = 'appointments.php';
});

</script>
<?php if(isset($_SESSION['success_delete'])): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?php echo $_SESSION['success_delete']; ?>'
});
</script>
<?php unset($_SESSION['success_delete']); endif; ?>

<?php if(isset($_SESSION['error_delete'])): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: '<?php echo $_SESSION['error_delete']; ?>'
});
</script>
<?php unset($_SESSION['error_delete']); endif; ?>


</body>
</html>
