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

// Redirect if not logged in as staff
if(!isset($_SESSION['staff_id'])){
    header("Location: login.php");
    exit;
}
// Fix: assign staff_name first
$staff_name = $_SESSION['staff_name'] ?? 'Unknown Staff';

// ---------- NO SHOW ----------
if(isset($_POST['no_show_id'])){
    $id = intval($_POST['no_show_id']);
    mysqli_query($link, "UPDATE tblappointments SET status='No Show' WHERE appointment_id='$id'");

    $patient_row = mysqli_fetch_assoc(mysqli_query($link, "SELECT p.full_name FROM tblappointments a JOIN tblpatients p ON a.patient_id = p.patient_id WHERE a.appointment_id='$id'"));
    $patient_name = $patient_row['full_name'] ?? 'Unknown';
    logAction($link, "Marked No Show", "Staff Appointments", $patient_name, $staff_name);

    $_SESSION['success'] = "Appointment marked as No Show.";
    header("Location: staff_appointments.php?search_name=".urlencode($search_name)."&status=".urlencode($status_filter)."&page=$page");
    exit;
}
// Get today's date
$today = date('Y-m-d');

// Fetch today's appointments
$todayAppointments = mysqli_query($link, "
    SELECT a.*, 
           p.full_name AS patient_name, 
           s.service_name, 
           d.fullname AS doctor_name,
           st.full_name AS assigned_by_name
    FROM tblappointments a
    JOIN tblpatients p ON a.patient_id = p.patient_id
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    LEFT JOIN tbldoctors d ON a.doctor_assigned = d.doctor_id
    LEFT JOIN tblstaff st ON a.assignedby = st.staff_id
    WHERE a.status IN ('Pending', 'Approved') AND a.appointment_date = '$today'
    ORDER BY a.appointment_time ASC
");

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Search & status filter
$search_name = $_GET['search_name'] ?? '';
$safe_name = mysqli_real_escape_string($link, $search_name);

$status_filter = $_GET['status'] ?? '';
$safe_status = mysqli_real_escape_string($link, $status_filter);

// Count total appointments (with filters)
$total_sql = "SELECT COUNT(*) as total
              FROM tblappointments a
              JOIN tblpatients p ON a.patient_id = p.patient_id
              WHERE 1";
if($safe_name) $total_sql .= " AND p.full_name LIKE '%$safe_name%'";
if($safe_status) $total_sql .= " AND a.status='$safe_status'";

$total_result = mysqli_query($link, $total_sql);
$total_records = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_records / $limit);
$total_pages = max(1, $total_pages);

// Adjust current page
if($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

// Fetch appointments with filters
$appointments = mysqli_query($link,"
SELECT a.*, 
       p.full_name AS patient_name, 
       s.service_name, 
       d.fullname AS doctor_name,
       st.full_name AS assigned_by_name
FROM tblappointments a
JOIN tblpatients p ON a.patient_id = p.patient_id
LEFT JOIN tblservices s ON a.service_id = s.service_id
LEFT JOIN tbldoctors d ON a.doctor_assigned = d.doctor_id
LEFT JOIN tblstaff st ON a.assignedby = st.staff_id
WHERE 1
".($safe_name ? " AND p.full_name LIKE '%$safe_name%'" : "")."
".($safe_status ? " AND a.status='$safe_status'" : "")."
ORDER BY a.date_created DESC
LIMIT $limit OFFSET $offset
");

// ------------------------ POST HANDLING ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------- CANCEL ----------
    if (isset($_POST['cancel_id'])) {
        $id = intval($_POST['cancel_id']);
        $reason = mysqli_real_escape_string($link, $_POST['cancel_reason']);

        mysqli_query($link,"UPDATE tblappointments SET status='Cancelled', cancel_reason='$reason' WHERE appointment_id='$id'");

        $patient_row = mysqli_fetch_assoc(mysqli_query($link, "
            SELECT p.patient_id, p.full_name, a.appointment_date, a.appointment_time 
            FROM tblappointments a 
            JOIN tblpatients p ON a.patient_id = p.patient_id 
            WHERE a.appointment_id='$id'
        "));
        $patient_id = $patient_row['patient_id'] ?? 0;
        $patient_name = $patient_row['full_name'] ?? 'Unknown';
        $date = $patient_row['appointment_date'] ?? '';
        $time = $patient_row['appointment_time'] ?? '';

        logAction($link, "Cancelled Appointment", "Staff Appointments", $patient_name, $staff_name);
        $_SESSION['success'] = "Appointment cancelled successfully.";
        header("Location: staff_appointments.php?search_name=".urlencode($search_name)."&status=".urlencode($status_filter)."&page=$page");
        exit;
    }
    // ---------- DISPENSE ----------
    if (isset($_POST['dispense_id'])) {
        $id = intval($_POST['dispense_id']);
        mysqli_query($link, "UPDATE tblappointments SET status='Dispensed' WHERE appointment_id='$id'");

        $patient_row = mysqli_fetch_assoc(mysqli_query($link, "SELECT p.full_name FROM tblappointments a JOIN tblpatients p ON a.patient_id = p.patient_id WHERE a.appointment_id='$id'"));
        $patient_name = $patient_row['full_name'] ?? 'Unknown';
        logAction($link, "Dispensed", "Staff Appointments", $patient_name, $staff_name);

        $_SESSION['success'] = "Appointment marked as dispensed.";
        header("Location: staff_appointments.php?search_name=".urlencode($search_name)."&status=".urlencode($status_filter)."&page=$page");
        exit;
    }
}

// ------------------------ DELETE ------------------------
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $check = mysqli_fetch_assoc(mysqli_query($link,"SELECT a.status, p.full_name 
                                                    FROM tblappointments a 
                                                    JOIN tblpatients p ON a.patient_id=p.patient_id 
                                                    WHERE appointment_id='$delete_id'"));
    if ($check && ($check['status'] === 'Cancelled' || $check['status'] === 'No Show')) {
        mysqli_query($link,"DELETE FROM tblappointments WHERE appointment_id='$delete_id'");
        $patient_name = $check['full_name'] ?? 'Unknown';
        logAction($link, "Delete", "Staff Appointments", $patient_name, $staff_name);
        $_SESSION['success'] = "Appointment deleted successfully.";
    } else {
        $_SESSION['error'] = "Only completed or cancelled appointments can be deleted.";
    }
    header("Location: staff_appointments.php?search_name=".urlencode($search_name)."&status=".urlencode($status_filter)."&page=$page");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Appointments</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --primary-color: #001BB7;
    --sidebar-bg: #001BB7;
    --sidebar-hover: rgba(255,255,255,0.15);
    --text-color: #fff;
    --card-bg: #fff;
    --card-shadow: 0 4px 18px rgba(0,0,0,0.08);
}

/* Reset */
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b;}

/* Sidebar */
.sidebar {
    width:250px;
    background: var(--sidebar-bg);
    color: var(--text-color);
    position:fixed;
    height:100%;
    padding:25px 15px;
    display:flex;
    flex-direction:column;
}
.sidebar h2 {
    text-align:center;
    margin-bottom:35px;
    font-size:24px;
    font-weight:700;
}
.sidebar a {
    color:#ffffffcc;
    display:flex;
    align-items:center;
    padding:12px 18px;
    margin:8px 0;
    text-decoration:none;
    border-radius:10px;
    transition:0.3s;
    font-weight:500;
}
.sidebar a i {margin-right:12px; font-size:18px;}
.sidebar a:hover {background: var(--sidebar-hover); padding-left:24px; color:#fff;}
.sidebar a.active {background: rgba(255,255,255,0.25); font-weight:600;}

/* Main */
.main { margin-left:250px; padding:40px; flex:1; }
h1 {margin-bottom:20px; color:#001BB7; font-weight:700;}

/* Card */
.card { background: var(--card-bg); padding:25px; border-radius:12px; box-shadow: var(--card-shadow); }

table {
    width:100%; border-collapse:collapse; margin-top:20px;
}
.table thead th {
    background-color: #001BB7 !important;
    color: white !important;
}

table th, table td {padding:12px 15px; border-bottom:1px solid #e2e8f0; text-align:center;}
table tr:hover {background:#f9fafb;}

/* Action buttons */
.action-btn {
    width:34px; height:34px; border:none; border-radius:8px;
    display:inline-flex; align-items:center; justify-content:center;
    font-size:15px; cursor:pointer; margin:0 2px; transition:0.2s;
}
.view-btn {background:#3498db; color:#fff;}
.approve-btn {background:#27ae60; color:#fff;}
.cancel-btn {background:#c0392b; color:#fff;}
.reschedule-btn {background:#f39c12; color:#fff;}
.delete-btn {background:#34495e; color:#fff;}
.assign-doctor-btn {background:#6c5ce7; color:#fff;}
.dispense-btn {background:#9333ea;}
.action-btn.disabled {background:#b0b0b0 !important; cursor:not-allowed; pointer-events:none;}

/* Modal */
.modal {display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);}
.modal-content {background:#fff; margin:10% auto; padding:20px; border-radius:10px; width:350px; position:relative;}
.close {position:absolute; right:10px; top:5px; cursor:pointer; font-size:25px;}
.modal-content label, .modal-content input, .modal-content select, .modal-content button {display:block; width:100%; margin-bottom:10px; padding:8px;}
/* Pagination */
.pagination {margin-top:10px;}
.pagination .page-item.active .page-link {background-color:#001BB7; border-color:#001BB7; color:white;}
.pagination .page-link {color:#001BB7;}
/* Hamburger for mobile */
.hamburger {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    font-size: 20px;
    color: var(--primary-color);
    background: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 10px;
    cursor: pointer;
    z-index: 1300;
}

#overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    display: none;
    z-index: 1000;
}

/* Mobile adjustments */
@media(max-width:768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        z-index: 1100;
    }

    .sidebar.active {
        transform: translateX(0);
    }

    .main {
        margin-left: 0;
        padding: 20px;
        overflow-x: auto;
    }

    table th,
    table td {
        padding: 8px 5px;
        font-size: 13px;
    }

    .hamburger { display: block; }
}

/* Extra: smooth scrolling for table */
.table-responsive {
    -webkit-overflow-scrolling: touch;
}
/* ====== GLOBAL ====== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}
body {
    background: #f5f7fa;
    display: flex;
    min-height: 100vh;
    color: #1e293b;
}

/* ====== SIDEBAR ====== */
.sidebar {
    width: 250px;
    background: var(--sidebar-bg);
    color: var(--text-color);
    position: fixed;
    height: 100%;
    padding: 25px 15px;
    display: flex;
    flex-direction: column;
    z-index: 1200;
}

.sidebar h2 {
    text-align: center;
    margin-bottom: 20px;
    font-size: 24px;
    font-weight: 700;
}
.sidebar a {
    color: #ffffffcc;
    display: flex;
    align-items: center;
    padding: 12px 18px;
    margin: 8px 0;
    text-decoration: none;
    border-radius: 10px;
    transition: 0.3s;
    font-weight: 500;
}
.sidebar a i {
    margin-right: 12px;
    font-size: 18px;
}
.sidebar a:hover {
    background: var(--sidebar-hover);
    padding-left: 24px;
    color: #fff;
}
.sidebar a.active {
    background: rgba(255, 255, 255, 0.25);
    font-weight: 600;
}

/* ====== MAIN ====== */
.main {
    margin-left: 250px;
    padding: 40px;
    flex: 1;
}
h1 {
    margin-bottom: 20px;
    color: #001BB7;
    font-weight: 700;
}

/* ====== TABLE ====== */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.table thead th {
    background-color: #001BB7 !important;
    color: white !important;
}
table th, table td {
    padding: 12px 15px;
    border-bottom: 1px solid #e2e8f0;
    text-align: center;
}
table tr:hover {
    background: #f9fafb;
}

/* ====== BUTTONS ====== */
.action-btn {
    width: 34px;
    height: 34px;
    border: none;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    cursor: pointer;
    margin: 0 2px;
    transition: 0.2s;
}
.action-btn.disabled {
    background: #b0b0b0 !important;
    cursor: not-allowed;
    pointer-events: none;
}

/* ====== MODAL ====== */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}
.modal-content {
    background: #fff;
    margin: 10% auto;
    padding: 20px;
    border-radius: 10px;
    width: 350px;
    position: relative;
}
.close {
    position: absolute;
    right: 10px;
    top: 5px;
    cursor: pointer;
    font-size: 25px;
}

/* ====== RESPONSIVE ====== */
.hamburger {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    font-size: 20px;
    color: var(--primary-color);
    background: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 10px;
    cursor: pointer;
    z-index: 1300;
}

#overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    display: none;
    z-index: 1000;
}

@media(max-width: 992px) {
    .main {
        padding: 20px;
    }
    table th, table td {
        padding: 10px 8px;
        font-size: 13px;
    }
}

@media(max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    .sidebar.active {
        transform: translateX(0);
    }

    .main {
        margin-left: 0;
        padding: 15px;
    }

    .hamburger {
        display: block;
    }
}
/* =========================
   DARK MODE STYLES (STAFF)
   ========================= */

body.dark-mode {
    background: #0b1220 !important;
    color: #f5f7fa !important;
    transition: 0.3s;
}

/* Sidebar */
body.dark-mode .sidebar {
    background: #0b1430 !important;
}
body.dark-mode .sidebar a {
    color: #e9f2ff !important;
}
body.dark-mode .sidebar a:hover {
    background: rgba(255,255,255,0.18) !important;
    color: #fff !important;
}
body.dark-mode .sidebar a.active {
    background: rgba(255,255,255,0.25) !important;
    color: #fff !important;
}

/* Main */
body.dark-mode .main {
    background: #0b1220 !important;
}

/* Header */
body.dark-mode .header-container {
    background: linear-gradient(90deg, #0f1a38, #00122b) !important;
    color: #fff !important;
}

/* Cards */
body.dark-mode .stat-card,
body.dark-mode .card {
    background: #111b2b !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.6) !important;
    color: #fff !important;
}
body.dark-mode .stat-card h3,
body.dark-mode .stat-card p,
body.dark-mode .card-header,
body.dark-mode .card-body,
body.dark-mode .card h3,
body.dark-mode .card p {
    color: #fff !important;
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

/* Inputs */
body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea {
    background: #1a2337 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.3) !important;
}

/* Buttons */
body.dark-mode .btn-primary,
body.dark-mode .btn-light,
body.dark-mode .btn-warning,
body.dark-mode .btn-info,
body.dark-mode .btn-success {
    color: #fff !important;
    border-color: rgba(255,255,255,0.25) !important;
}
body.dark-mode .btn-light {
    background: #1a2337 !important;
    color: #fff !important;
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

/* FullCalendar Dark Mode */
body.dark-mode #appointmentsCalendar {
    background: #111b2b !important;
    color: #fff !important;
}
body.dark-mode .fc {
    color: #fff !important;
}
body.dark-mode .fc .fc-button {
    background-color: #0b1430 !important;
    color: #fff !important;
}
body.dark-mode .fc .fc-button:hover {
    background-color: #00122b !important;
}
body.dark-mode .fc .fc-daygrid-day {
    background: #0b1220 !important;
}
body.dark-mode .fc .fc-day-today {
    background: rgba(255,255,255,0.12) !important;
}
/* Profile dropdown text */
body.dark-mode .dropdown .btn .fw-semibold,
body.dark-mode .dropdown-menu .dropdown-item,
body.dark-mode .dropdown-menu .dropdown-item-text {
    color: #fff !important;
}

/* Dropdown arrow */
body.dark-mode .dropdown .btn .fw-semibold {
    color: #fff !important;
}

/* Dropdown background */
body.dark-mode .dropdown-menu {
    background: #111b2b !important;
}
body.dark-mode .dropdown .btn {
    color: #fff !important;
}
/* =========================
   DARK MODE TABLE STYLES
   ========================= */

body.dark-mode table {
    background: #0b1220 !important;
    color: #fff !important;
}

body.dark-mode table thead th {
    background: #0b1430 !important;
    color: #fff !important;
    border-bottom: 1px solid rgba(255,255,255,0.2) !important;
}

body.dark-mode table tbody td {
    background: #0b1220 !important;
    color: #fff !important;
    border-bottom: 1px solid rgba(255,255,255,0.12) !important;
}

body.dark-mode table tr:hover {
    background: rgba(255,255,255,0.08) !important;
}

body.dark-mode .table-responsive {
    background: #0b1220 !important;
}
body.dark-mode table th,
body.dark-mode table td {
    border-color: rgba(255,255,255,0.18) !important;
}
/* Make Appointments H1 white in dark mode */
body.dark-mode h1 {
    color: #fff !important;
}

</style>
</head>
<body>
<!-- Hamburger for mobile -->
<button class="hamburger" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>
<div id="overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>
  <a href="staff_dashboard.php" style="text-decoration:none; color:white; display:flex; align-items:center; justify-content:center;">
    <img src="logo.png" alt="KNL Logo" 
         style="width:45px; height:45px; margin-right:10px; border-radius:50%; object-fit:cover;">
    KNL Health Center
  </a>
</h2>

    <a href="staff_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="staff_profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="patient_staff.php"><i class="fas fa-users"></i> Patients</a>
    <a href="staff_appointments.php" class="active"><i class="fas fa-calendar"></i> Appointments</a>
    <a href="staff_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="staff_reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Main -->
<div class="main">
    <?php include 'header_staff.php'; ?>
    <h1><i class="fas fa-calendar"></i> Appointments</h1>
    
    <!-- Filter form -->
    <form method="GET" class="filter-form d-flex gap-2 mb-3 flex-wrap">
        <input type="text" name="search_name" class="form-control" placeholder="Search by patient name"
               value="<?php echo htmlspecialchars($search_name); ?>" style="flex:10; min-width:150px;">

        <select name="status" class="form-select" style="flex:1; min-width:120px;">
            <option value="">All Status</option>
            <option value="Pending" <?php if($status_filter=='Pending') echo 'selected'; ?>>Pending</option>
            <option value="Cancelled" <?php if($status_filter=='Cancelled') echo 'selected'; ?>>Cancelled</option>
            <option value="Completed" <?php if($status_filter=='Completed') echo 'selected'; ?>>Completed</option>
            <option value="Dispensed" <?php if($status_filter=='Dispensed') echo 'selected'; ?>>Dispensed</option>
        </select>

        <button type="submit" class="btn" style="background-color:#001BB7; color:#fff;">
    <i class="fas fa-search"></i> Search
</button>
 <a href="staff_appointments_export_excel.php" class="btn btn-success">
        <i class="fas fa-file-csv"></i> Export Excel
    </a>

    <!-- Export to PDF -->
    <a href="staff_appointments_export_pdf.php" class="btn btn-danger">
        <i class="fas fa-file-pdf"></i> Export PDF
    </a>
    </form>
<div class="card mb-4">
    <h3>Today's Appointments (<?= date('F j, Y'); ?>)</h3>
    <?php if(mysqli_num_rows($todayAppointments) > 0): ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Service</th>
                    <th>Type</th>
                    <th>Queue #</th>
                    <th>Time</th>
                    <th>Doctor</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while($a = mysqli_fetch_assoc($todayAppointments)): 
                $status = $a['status'];
                $can_cancel = ($status === 'Pending' || $status === 'Rescheduled Accepted');

                // No Show eligibility
                $appointmentDateTime = $a['appointment_date'] . ' ' . $a['appointment_time'];
                $currentDateTime = date('Y-m-d H:i');
                $isNoShowEligible = ($status === 'Pending' || $status === 'Approved') && (strtotime($appointmentDateTime) <= strtotime($currentDateTime));
            ?>
            <tr>
                <td><?= htmlspecialchars($a['patient_name']); ?></td>
                <td><?= htmlspecialchars($a['service_name']); ?></td>
                <td><?= htmlspecialchars($a['appointment_type'] ?? 'N/A'); ?></td>
                <td><?= htmlspecialchars($a['queue_number'] ?? '—'); ?></td>
                <td><?= date("h:i A", strtotime($a['appointment_time'])); ?></td>

                <td><?= $a['doctor_name'] ?? 'Not assigned'; ?></td>
                <td><?= $a['status']; ?></td>
                <td>
                    <button class="action-btn view-btn" onclick="viewAppointment(
                        '<?= htmlspecialchars($a['patient_name']); ?>',
                        '<?= htmlspecialchars($a['service_name']); ?>',
                        '<?= htmlspecialchars($a['doctor_name'] ?? 'Not assigned'); ?>',
                        '<?= $a['appointment_date']; ?>',
                        '<?= $a['appointment_time']; ?>',
                        '<?= $a['status']; ?>',
                        '<?= htmlspecialchars($a['assigned_by_name'] ?? 'N/A'); ?>',
                        `<?= $a['cancel_reason'] ?? ''; ?>`,
                        `<?= htmlspecialchars($a['medicine_notes'] ?? ''); ?>`,
                        `<?= htmlspecialchars($a['reschedule_reason'] ?? ''); ?>`,
                        '<?= $a['appointment_id']; ?>',
                        `<?= htmlspecialchars($a['notes'] ?? ''); ?>`,
                        '<?= htmlspecialchars($a['appointment_type'] ?? 'N/A'); ?>',
                        '<?= htmlspecialchars($a['queue_number'] ?? '—'); ?>'
                    )"><i class="fas fa-eye"></i></button>

                    <a href="javascript:void(0);" onclick="cancelWithReason(<?= $a['appointment_id']; ?>)" class="action-btn cancel-btn <?= $can_cancel?'':'disabled'; ?>"><i class="fas fa-times"></i></a>

                    <button class="action-btn cancel-btn <?= $isNoShowEligible ? '' : 'disabled'; ?>" 
                            <?= $isNoShowEligible ? '' : 'disabled'; ?>
                            onclick="markNoShow(<?= $a['appointment_id']; ?>)">
                        <i class="fas fa-user-slash"></i>
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="padding:10px;">No appointments scheduled for today.</p>
    <?php endif; ?>
</div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Service</th>
                        <th>Type</th>   <!-- ADD THIS -->
                        <th>Queue #</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            <tbody>
            <?php if(mysqli_num_rows($appointments)>0): ?>
                <?php while($a=mysqli_fetch_assoc($appointments)):
                    $status = $a['status'];
                    $assigned = !empty($a['doctor_name']);
                    $can_cancel = ($status === 'Pending' || $status === 'Rescheduled Accepted');
                    $can_delete = ($status === 'No Show' || $status === 'Cancelled');
                ?>
                <tr>
                    <td><?= htmlspecialchars($a['patient_name']); ?></td>
                    <td><?= htmlspecialchars($a['service_name']); ?></td>
                    <td><?= htmlspecialchars($a['appointment_type'] ?? 'N/A'); ?></td>

    <!-- NEW: Queue Number -->
    <td><?= htmlspecialchars($a['queue_number'] ?? '—'); ?></td>
                    <td>
<?php
// Show original date if reschedule is requested
if($a['status'] === 'Reschedule Requested') {
    echo $a['appointment_date'];
} elseif(!empty($a['reschedule_date'])) {
    echo $a['reschedule_date'];
} else {
    echo $a['appointment_date'];
}
?>
</td>

<td>
<?php
$timeToShow = $a['appointment_time']; // default
if($a['status'] === 'Reschedule Requested') {
    $timeToShow = $a['appointment_time'];
} elseif(!empty($a['reschedule_time'])) {
    $timeToShow = $a['reschedule_time'];
}
echo date("h:i A", strtotime($timeToShow));
?>
</td>


                    <td><?= $a['doctor_name'] ?? 'Not assigned'; ?></td>
                    <td><?= $a['status']; ?></td>
                    <td>
                        <!-- Buttons: view, approve, cancel, reschedule, assign, delete, dispense -->
<button class="action-btn view-btn" onclick="viewAppointment(
    '<?= htmlspecialchars($a['patient_name']); ?>',
    '<?= htmlspecialchars($a['service_name']); ?>',
    '<?= htmlspecialchars($a['doctor_name'] ?? 'Not assigned'); ?>',
    '<?= $a['appointment_date']; ?>',
    '<?= $a['appointment_time']; ?>',
    '<?= $a['status']; ?>',
    '<?= htmlspecialchars($a['assigned_by_name'] ?? 'N/A'); ?>',
    `<?= $a['cancel_reason'] ?? ''; ?>`,
    `<?= htmlspecialchars($a['medicine_notes'] ?? ''); ?>`,
    `<?= htmlspecialchars($a['reschedule_reason'] ?? ''); ?>`,
    '<?= $a['appointment_id']; ?>',
    `<?= htmlspecialchars($a['notes'] ?? ''); ?>`,
    '<?= htmlspecialchars($a['appointment_type'] ?? 'N/A'); ?>',
    '<?= htmlspecialchars($a['queue_number'] ?? '—'); ?>'
)"
><i class="fas fa-eye"></i></button>

<?php if ($a['status'] === 'Approved' && $a['service_name'] === 'Free Medicine'): ?>
<button class="action-btn dispense-btn" onclick="markDispensed(<?= $a['appointment_id']; ?>)">
    <i class="fas fa-pills"></i>
</button>
<?php endif; ?>

<a href="javascript:void(0);" onclick="cancelWithReason(<?= $a['appointment_id']; ?>)" class="action-btn cancel-btn <?= $can_cancel?'':'disabled'; ?>"><i class="fas fa-times"></i></a>
<!-- NEW: No Show Button -->
<?php
// Get appointment datetime and current datetime
$appointmentDateTime = $a['appointment_date'] . ' ' . $a['appointment_time'];
$currentDateTime = date('Y-m-d H:i');

// Check if status allows No Show and appointment time has passed
$isNoShowEligible = ($status === 'Pending' || $status === 'Approved') && (strtotime($appointmentDateTime) <= strtotime($currentDateTime));
?>
<button class="action-btn cancel-btn <?= $isNoShowEligible ? '' : 'disabled'; ?>" 
        <?= $isNoShowEligible ? '' : 'disabled'; ?>
        onclick="markNoShow(<?= $a['appointment_id']; ?>)">
    <i class="fas fa-user-slash"></i>
</button>

<a href="javascript:void(0);" onclick="confirmDelete(<?= $a['appointment_id']; ?>)" class="action-btn delete-btn <?= $can_delete?'':'disabled'; ?>"><i class="fas fa-trash-alt"></i></a>

                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" style="text-align:center;">No appointments found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <!-- Simple Pagination -->
<?php if($total_pages > 1): ?>
<nav aria-label="Page navigation">
    <ul class="pagination justify-content-end mt-3">
        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
            <a class="page-link" href="?search_name=<?= urlencode($search_name); ?>&status=<?= urlencode($status_filter); ?>&page=<?= max(1, $page-1); ?>">Previous</a>
        </li>
        <li class="page-item active">
            <span class="page-link"><?= $page; ?></span>
        </li>
        <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
            <a class="page-link" href="?search_name=<?= urlencode($search_name); ?>&status=<?= urlencode($status_filter); ?>&page=<?= min($total_pages, $page+1); ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

    </div>
</div>

<!-- Hidden forms -->
<!-- Approve/Cancel form -->
<form method="POST" id="statusForm" style="display:none;">
    <input type="hidden" name="approve_id" id="approve_id">
    <input type="hidden" name="cancel_id" id="cancel_id">
    <input type="hidden" name="cancel_reason" id="cancel_reason">
</form>

<form method="POST" id="noShowForm" style="display:none;">
    <input type="hidden" name="no_show_id" id="no_show_id">
</form>

<form method="POST" id="dispenseForm" style="display:none;">
    <input type="hidden" name="dispense_id" id="dispense_id">
</form>

<script>
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
<script>
// SweetAlert + modals
function viewAppointment(patient, service, doctor, date, time, status, assignedBy, cancelReason, medicineNotes, rescheduleReason, appointmentId, notes, appointmentType, queueNumber) {

    const isDark = document.body.classList.contains('dark-mode');

    const statusColors = {
        "Pending": "#f1c40f",
        "Approved": "#3498db",
        "Assigned": "#9b59b6",
        "Completed": "#2ecc71",
        "Dispensed": "#16a085",
        "Cancelled": "#e74c3c",
        "Rescheduled accepted": "#f39c12",
        "Rescheduled requested": "#d35400",
        "No Show": "#7f8c8d"
    };

    const badgeColor = statusColors[status] || "#7f8c8d";

    let extra = "";
    if(service.toLowerCase() === "free medicine" && medicineNotes){
        extra += `<tr><td><i class="fas fa-pills"></i> <strong>Medicine Request:</strong></td><td>${medicineNotes}</td></tr>`;
    }
    if(cancelReason){
        extra += `<tr><td><i class="fas fa-times-circle"></i> <strong>Cancellation Reason:</strong></td><td>${cancelReason}</td></tr>`;
    }
    if((status === "Rescheduled accepted" || status === "Rescheduled requested") && rescheduleReason){
        extra += `<tr><td><i class="fas fa-calendar-alt"></i> <strong>Reschedule Reason:</strong></td><td>${rescheduleReason}</td></tr>`;
    }
    if(notes){
        extra += `<tr><td><i class="fas fa-sticky-note"></i> <strong>Notes:</strong></td><td>${notes}</td></tr>`;
    }

    Swal.fire({
        title: `<strong>Appointment Details</strong>`,
        html: `
        <div style="font-family:'Inter', sans-serif; text-align:left;">
            <div style="background:${isDark ? '#111b2b' : '#f5f7fa'}; padding:20px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.2);">

                <p style="margin:0; font-weight:700; font-size:28px; color:${isDark ? '#fff' : '#001BB7'};">${patient}</p>
                <p style="margin:2px 0; color:${isDark ? '#cbd5e1' : '#000'};"><i class="fas fa-id-badge"></i> Patient ID: ${appointmentId}</p>

                <div style="background:${isDark ? '#0b1220' : '#fff'}; padding:15px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.1); margin-bottom:15px;">
                    <p style="margin:5px 0; color:${isDark ? '#fff' : '#000'};"><i class="fas fa-user-md"></i> <strong>Doctor:</strong> ${doctor}</p>
                    <p style="margin:5px 0; color:${isDark ? '#fff' : '#000'};"><i class="fas fa-user-tie"></i> <strong>Assigned By:</strong> ${assignedBy}</p>
                    <p style="margin:5px 0; color:${isDark ? '#fff' : '#000'};"><i class="fas fa-stethoscope"></i> <strong>Service:</strong> ${service}</p>
                    <p style="margin:5px 0; color:${isDark ? '#fff' : '#000'};"><i class="fas fa-calendar-alt"></i> <strong>Date:</strong> ${date}</p>
                    <p style="margin:5px 0; color:${isDark ? '#fff' : '#000'};"><i class="fas fa-clock"></i> <strong>Time:</strong> ${time}</p>
                    <p style="margin:5px 0; color:${isDark ? '#fff' : '#000'};"><i class="fas fa-calendar-check"></i> <strong>Status:</strong> 
                        <span style="background:${badgeColor}; color:white; padding:4px 10px; border-radius:12px; font-weight:600;">${status}</span>
                    </p>
                    <p style="margin:5px 0; color:${isDark ? '#fff' : '#000'};"><i class="fas fa-info-circle"></i> <strong>Appointment Type:</strong> ${appointmentType}</p>
                    <p style="margin:5px 0; color:${isDark ? '#fff' : '#000'};"><i class="fas fa-list-ol"></i> <strong>Queue #:</strong> ${queueNumber}</p>
                </div>

                ${extra ? `<table style="width:100%; border-collapse: collapse; background:${isDark ? '#0b1220' : '#fff'}; border-radius:12px; padding:10px;">
                    ${extra}
                </table>` : ''}

            </div>
        </div>
        `,
        width: '700px',
        showCloseButton: true,
        confirmButtonText: 'Close',
        confirmButtonColor: '#001BB7',

        background: isDark ? '#111b2b' : '#fff',
        color: isDark ? '#fff' : '#000',

        didOpen: () => {
            const tableCells = Swal.getHtmlContainer().querySelectorAll('td');
            tableCells.forEach((td, index) => {
                td.style.padding = '8px 10px';
                td.style.color = isDark ? '#fff' : '#000';
                if(index % 2 === 0) td.style.fontWeight = '600';
            });
        }
    });
}


function confirmAction(action, id){
    Swal.fire({
        title:'Are you sure?',
        text:`Do you want to ${action.toLowerCase()} this appointment?`,
        icon:'warning', showCancelButton:true,
        confirmButtonText:`Yes, ${action}`
    }).then((result)=>{
        if(result.isConfirmed){
            if(action === "Approved"){
                document.getElementById('approve_id').value = id;
                document.getElementById('statusForm').submit();
            }
        }
    });
}

function cancelWithReason(id){
    Swal.fire({
        title:'Cancel Appointment',
        input:'textarea',
        inputLabel:'Reason for cancellation',
        inputPlaceholder:'Enter reason...',
        showCancelButton:true,
        confirmButtonText:'Cancel Appointment',
        confirmButtonColor:'#001BB7', // <-- change to blue
        inputValidator:(value)=>{ if(!value){ return 'You must provide a reason'; } }
    }).then((result)=>{
        if(result.isConfirmed){
            document.getElementById('cancel_id').value = id;
            document.getElementById('cancel_reason').value = result.value;
            document.getElementById('statusForm').submit();
        }
    });
}


function confirmDelete(id){
    Swal.fire({
        title:'Are you sure?',
        text:'Delete this appointment? This cannot be undone.',
        icon:'warning', 
        showCancelButton:true,
        confirmButtonText:'Yes, delete',
        confirmButtonColor:'#001BB7' // <-- change to blue
    }).then((result)=>{
        if(result.isConfirmed){
            window.location.href=`staff_appointments.php?delete_id=${id}`;
        }
    });
}

// Generate time slots
function generateTimeSlots(){
    const slots = [];
    for(let h=6; h<12; h++){
        for(let m=0; m<60; m+=15){
            slots.push(`${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}`);
        }
    }
    for(let h=13; h<17; h++){
        for(let m=0; m<60; m+=15){
            slots.push(`${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}`);
        }
    }
    slots.push("17:30");
    return slots;
}


function markDispensed(id){
    Swal.fire({
        title:'Are you sure?',
        text:'Mark this Free Medicine request as dispensed?',
        icon:'warning', showCancelButton:true,
        confirmButtonText:'Yes, mark as dispensed'
    }).then((result)=>{ if(result.isConfirmed){ document.getElementById('dispense_id').value = id; document.getElementById('dispenseForm').submit(); } });
}

<?php if(isset($_SESSION['success'])): ?>
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?= $_SESSION['success']; ?>',
    confirmButtonColor: '#001BB7'  // blue OK button
});
<?php unset($_SESSION['success']); endif; ?>

<?php if(isset($_SESSION['error'])): ?>
Swal.fire({icon:'error', title:'Error', text:'<?= $_SESSION['error']; ?>'});
<?php unset($_SESSION['error']); endif; ?>

</script>
<!-- FontAwesome for icons -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
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

function markNoShow(id){
    Swal.fire({
        title: 'Mark as No Show?',
        text: "This will mark the appointment as No Show.",
        icon: 'warning',
        showCancelButton: true,

        confirmButtonText: 'Yes, mark No Show',
        confirmButtonColor: '#001BB7',   // 🔴 red
        cancelButtonColor: '#6b7280'     // gray cancel

    }).then((result)=>{
        if(result.isConfirmed){
            document.getElementById('no_show_id').value = id;
            document.getElementById('noShowForm').submit();
        }
    });
}


</script>

</body>
</html>