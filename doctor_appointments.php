<?php
session_start();
require_once "config.php";

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

if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['doctor_id'];

// ---- HANDLE CANCELLATION ----
if(isset($_POST['cancel_appointment'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $reason = mysqli_real_escape_string($link, trim($_POST['reason']));

    if($appointment_id > 0 && !empty($reason)) {
        $update_sql = "UPDATE tblappointments 
                       SET status='Cancelled', cancel_reason='$reason' 
                       WHERE appointment_id=$appointment_id AND doctor_assigned='$doctor_id'";

        if(mysqli_query($link, $update_sql)){
            $res = mysqli_query($link, "SELECT p.full_name FROM tblappointments a 
                                        JOIN tblpatients p ON a.patient_id = p.patient_id 
                                        WHERE a.appointment_id=$appointment_id");
            $patient_name = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res)['full_name'] : 'N/A';
            $doctor_name = $_SESSION['doctor_name'] ?? 'Doctor';
            logAction($link, "Cancel", "Doctor Appointments", $patient_name, $doctor_name);
            echo "success";
        } else {
            echo "error";
        }
    } else {
        echo "invalid";
    }
    exit; 
}
// --- Today's Appointments ---
$today = date('Y-m-d');
$today_sql = "SELECT a.*, 
                     p.full_name AS patient_name,
                     p.birthday,
                     s.service_name, 
                     st.full_name AS assigned_by_name
              FROM tblappointments a
              JOIN tblpatients p ON a.patient_id = p.patient_id
              LEFT JOIN tblservices s ON a.service_id = s.service_id
              LEFT JOIN tblstaff st ON a.assignedby = st.staff_id
              WHERE a.doctor_assigned = '$doctor_id' AND a.appointment_date='$today'
              ORDER BY a.appointment_time ASC";

$today_result = mysqli_query($link, $today_sql);

// ---- FILTER + PAGINATION ----
$search_name = $_GET['search_name'] ?? '';
$safe_name = mysqli_real_escape_string($link, $search_name);
$status_filter = $_GET['status'] ?? '';
$safe_status = mysqli_real_escape_string($link, $status_filter);

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Count total
$total_sql = "SELECT COUNT(*) as total
              FROM tblappointments a
              JOIN tblpatients p ON a.patient_id = p.patient_id
              WHERE a.doctor_assigned = '$doctor_id'";
if($safe_name) $total_sql .= " AND p.full_name LIKE '%$safe_name%'";
if($safe_status) $total_sql .= " AND a.status='$safe_status'";

$total_result = mysqli_query($link, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = max(1, ceil($total_rows / $limit));
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

// Fetch appointments
$sql = "SELECT a.*, 
               p.full_name AS patient_name,
               p.birthday,
               s.service_name, 
               st.full_name AS assigned_by_name, 
               a.appointment_type
        FROM tblappointments a
        JOIN tblpatients p ON a.patient_id = p.patient_id
        LEFT JOIN tblservices s ON a.service_id = s.service_id
        LEFT JOIN tblstaff st ON a.assignedby = st.staff_id
        WHERE a.doctor_assigned = '$doctor_id'";
if($safe_name) $sql .= " AND p.full_name LIKE '%$safe_name%'";
if($safe_status) $sql .= " AND a.status='$safe_status'";
$sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC
          LIMIT $limit OFFSET $offset";

$result = mysqli_query($link, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Appointments</title>

<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- jQuery & SweetAlert2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- SheetJS & jsPDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<style>
:root {
    --primary-color: #001BB7;
    --sidebar-bg: #001BB7;
    --sidebar-hover: rgba(255,255,255,0.15);
    --text-color: #fff;
    --card-bg: #fff;
    --card-shadow: 0 4px 18px rgba(0,0,0,0.08);
}

* { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
body { background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b; }

/* Sidebar */
.sidebar { width:250px; background: var(--sidebar-bg); color: var(--text-color); position:fixed; height:100%; padding:25px 15px; display:flex; flex-direction:column; transition: transform 0.3s ease; z-index:1100;}
.sidebar h2 { text-align:center; margin-bottom:20px; font-size:24px; font-weight:700;}
.sidebar h2 img { width:45px; height:45px; margin-right:10px; vertical-align:middle; border-radius:50%; object-fit:cover;}
.sidebar a { color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;}
.sidebar a i { margin-right:12px; font-size:18px;}
.sidebar a:hover { background: var(--sidebar-hover); padding-left:24px; color:#fff;}
.sidebar a.active { background: rgba(255,255,255,0.25); font-weight:600;}

/* Hamburger */
.hamburger { display:none; position:fixed; top:15px; left:15px; font-size:20px; color: var(--primary-color); background:#fff; border:none; border-radius:8px; padding:8px 10px; cursor:pointer; z-index:1200;}

/* Overlay */
#overlay { position: fixed; inset:0; background: rgba(0,0,0,0.45); opacity:0; visibility:hidden; transition: opacity 0.25s ease, visibility 0.25s; z-index:1050;}
#overlay.active { opacity:1; visibility:visible;}

/* Main content */
.main { margin-left:250px; padding:30px; flex:1; transition: margin-left 0.3s ease; }
h1 { margin-bottom:20px; color: var(--primary-color); font-weight:700; }

/* Card */
.card { background: var(--card-bg); padding:25px; border-radius:12px; box-shadow: var(--card-shadow); }

/* Filter form */
.filter-form { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px; }
.filter-form input, .filter-form select, .filter-form button { padding:8px 12px; border:1px solid #ccc; border-radius:6px; font-size:14px; }
.filter-btn { background: var(--primary-color); color:white; border:none; display:flex; align-items:center; gap:5px; cursor:pointer; }

/* Export buttons */
.export-buttons {
    display:flex;
    gap:8px;
    margin-bottom:15px;
}

#exportExcel {
    background:#22c55e; /* green */
    color:#fff;
}

#exportExcel:hover {
    background:#16a34a;
}

#exportPDF {
    background:#ef4444; /* red */
    color:#fff;
}

#exportPDF:hover {
    background:#dc2626;
}

#printTable {
    background:#6b7280; /* gray */
    color:#fff;
}

#printTable:hover {
    background:#4b5563;
}

.btn-export {
    padding:6px 12px;
    border:none;
    border-radius:6px;
    cursor:pointer;
    transition:0.2s;
}


/* Table */
table { width:100%; border-collapse:collapse; margin-top:10px; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);}
table thead { background: var(--primary-color); color:#fff; }
table th, table td { padding:12px 15px; text-align:center; border-bottom:1px solid #e2e8f0; }
table tr:hover { background:#f9fafb; }

/* Badges */
.badge { padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; color:#fff; }
.badge.pending { background:#f59e0b; }
.badge.assigned { background:#22c55e; }
.badge.completed { background:#3b82f6; }
.badge.cancelled { background:#ef4444; }
.badge.noshow { background:#f97316; } /* orange color for No Show */

/* Buttons */
.btn { padding:6px 10px; border:none; border-radius:6px; cursor:pointer; font-size:14px; transition:0.3s; }
.btn-success { background:#22c55e; color:#fff; }
.btn-success:disabled { background:#9ca3af; cursor:not-allowed; }

/* Action buttons */
.action-buttons { display:flex; gap:6px; justify-content:center; }
.icon-btn { width:38px; height:38px; border:none; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; cursor:pointer; transition:0.2s; }
.icon-btn.view { background: var(--primary-color); color:#fff; }
.icon-btn.view:hover { background:#0010a0; }
.icon-btn:disabled { background:#cbd5e1; color:#666; cursor:not-allowed; }

/* Responsive */
@media(max-width:768px){
    .hamburger { display:block; }
    .sidebar { transform:translateX(-280px); }
    .sidebar.active { transform:translateX(0); }
    .main { margin-left:0; padding:20px; }
    table th, table td { font-size:12px; padding:8px; }
}
.page-link.custom-pagination {
    color: #001BB7;          /* text color */
    border-color: #001BB7;   /* border */
}

.page-item.active .page-link.custom-pagination {
    background-color: #001BB7;  /* active background */
    color: #fff;                 /* active text color */
    border-color: #001BB7;
}

.page-item.disabled .page-link.custom-pagination {
    color: #6c757d;  /* disabled text color */
    pointer-events: none;
    background-color: #e9ecef;
    border-color: #dee2e6;
}
.complete-header {
    background-color: #001BB7;
    border-bottom: none;
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
/* Dark Mode - Assigned Services Card */
body.dark-mode .service-card {
    background: #111b2b !important;
    box-shadow: 0 6px 14px rgba(0,0,0,0.5) !important;
    color: #fff !important;
}

body.dark-mode .service-card .service-icon {
    background: #001BB7 !important;
    color: #fff !important;
}

body.dark-mode .service-info h4,
body.dark-mode .service-info p {
    color: #fff !important;
}

/* Dark Mode - Upcoming Appointments Table */
body.dark-mode .table {
    background: #0b1220 !important;
    color: #fff !important;
}

body.dark-mode .table thead th {
    background: #0b1430 !important;
    color: #fff !important;
}

body.dark-mode .table tbody td {
    background: #111b2b !important;
    color: #fff !important;
    border-color: rgba(255,255,255,0.15) !important;
}

body.dark-mode .table-hover tbody tr:hover td {
    background: rgba(255,255,255,0.08) !important;
}

body.dark-mode .badge.bg-info {
    background: #0ea5e9 !important;
}
body.dark-mode h1 {
    color: #fff !important;
}
/* Dark Mode - Table Hover */
body.dark-mode table tr:hover {
    background: rgba(255,255,255,0.08) !important;
}
/* =========================
   SweetAlert View Modal - Dark Mode
   ========================= */
body.dark-mode .swal2-popup {
    background: #0b1220 !important;
    color: #fff !important;
}

body.dark-mode .swal2-title,
body.dark-mode .swal2-html-container {
    color: #fff !important;
}

/* Card inside View Details */
body.dark-mode .swal-view-card {
    background: #111b2b !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.6) !important;
}

/* Inner info box */
body.dark-mode .swal-view-box {
    background: #0b1430 !important;
    color: #fff !important;
}

/* Table */
body.dark-mode .swal-view-table {
    background: #0b1430 !important;
}

body.dark-mode .swal-view-table td {
    color: #fff !important;
    border-bottom: 1px solid rgba(255,255,255,0.15);
}
/* Assigned Services Pill Design */
.service-pill {
    background: linear-gradient(135deg, #001BB7, #0033ff);
    color: #fff;
    border-radius: 50px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.25);
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: default;
}

.service-pill i {
    font-size: 0.9rem;
}

/* Hover effect for better UX */
.service-pill:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.35);
}

/* Dark mode support */
body.dark-mode .service-pill {
    background: linear-gradient(135deg, #001BB7, #0040ff);
    box-shadow: 0 3px 10px rgba(0,0,0,0.5);
    color: #fff;
}
/* ==========================================
   DARK MODE - COMPLETE APPOINTMENT MODAL
   ========================================== */

/* Modal container */
body.dark-mode .modal-content {
    background: #0f172a !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.15);
}

/* Modal header */
body.dark-mode .modal-header {
    background: #0b1430 !important;
    border-bottom: 1px solid rgba(255,255,255,0.15);
}

/* Modal title */
body.dark-mode .modal-title {
    color: #fff !important;
}

/* Close button */
body.dark-mode .btn-close {
    filter: invert(1);
}

/* Labels */
body.dark-mode .modal-body label {
    color: #e2e8f0 !important;
}

/* Paragraph info (Patient / Age etc) */
body.dark-mode .modal-body p {
    color: #f1f5f9 !important;
}

/* Inputs */
body.dark-mode .modal-body input,
body.dark-mode .modal-body textarea,
body.dark-mode .modal-body select {
    background: #1e293b !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.25) !important;
}

/* Placeholder color */
body.dark-mode .modal-body input::placeholder,
body.dark-mode .modal-body textarea::placeholder {
    color: #94a3b8 !important;
}

/* Medication box */
body.dark-mode .medicationItem {
    background: #111b2b !important;
    border: 1px solid rgba(255,255,255,0.15) !important;
}

/* Remove button */
body.dark-mode .removeMedication {
    background: #ef4444 !important;
    border: none !important;
}

/* Add Medication button */
body.dark-mode #addMedication {
    background: #001BB7 !important;
    border: none !important;
}

/* Complete button */
body.dark-mode #prescriptionForm button[type="submit"] {
    background: #22c55e !important;
    border: none !important;
}

/* Divider */
body.dark-mode .modal-body hr {
    border-color: rgba(255,255,255,0.2) !important;
}
</style>
</head>
<body>

<button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
<div id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar">
    <h2>
  <a href="dashboard_doctors.php" style="text-decoration:none; color:white; display:flex; align-items:center; justify-content:center;">
    <img src="logo.png" alt="KNL Logo" 
         style="width:45px; height:45px; margin-right:10px; border-radius:50%; object-fit:cover;">
    KNL Health Center
  </a>
</h2>

    <a href="dashboard_doctors.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="doctor_profile.php"><i class="fas fa-user-md"></i> Profile</a>
    <a href="doctor_patients.php"><i class="fas fa-users"></i> My Patients</a>
    <a href="doctor_appointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="doctor_schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a>
    <a href="doctor_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="doctor_reports.php"><i class="fas fa-chart-line"></i> Reports </a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main">
    <?php include 'doctor_header.php'; ?>
<h1><i class="fas fa-calendar-check"></i> My Appointments</h1>

<!-- Filter Form -->
<form method="GET" class="filter-form">
<input type="text" name="search_name" placeholder="Search by patient/service" value="<?= htmlspecialchars($search_name); ?>">
<select name="status">
    <option value="">All Status</option>
    <option value="Pending" <?= $status_filter=='Pending'?'selected':''; ?>>Pending</option>
    <option value="Assigned" <?= $status_filter=='Assigned'?'selected':''; ?>>Assigned</option>
    <option value="Completed" <?= $status_filter=='Completed'?'selected':''; ?>>Completed</option>
    <option value="Cancelled" <?= $status_filter=='Cancelled'?'selected':''; ?>>Cancelled</option>
</select>
<button type="submit" class="filter-btn"><i class="fas fa-search"></i> Search</button>
<button class="btn-export" id="exportExcel"><i class="fas fa-file-excel"></i> Excel</button>
<button class="btn-export" id="exportPDF"><i class="fas fa-file-pdf"></i> PDF</button>
<button class="btn-export" id="printTable"><i class="fas fa-print"></i> Print</button>
</form>

<!-- Export Buttons -->

<!-- Today's Appointments -->
<div class="card mb-4">
    <h4 class="mb-3"><i class="fas fa-calendar-day"></i> Today's Appointments (<?= date('F j, Y'); ?>)</h4>
    <?php if(mysqli_num_rows($today_result) > 0): ?>
    <table class="table table-striped" id="todayAppointmentsTable">
        <thead>
            <tr>
                <th>Patient</th>
                <th>Service</th>
                <th>Type</th>
                <th>Queue #</th>
                <th>Time</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = mysqli_fetch_assoc($today_result)): ?>
            <tr data-assignedby="<?= htmlspecialchars($row['assigned_by_name'] ?? 'N/A'); ?>" 
                data-reason="<?= htmlspecialchars($row['cancel_reason'] ?? ''); ?>">
                <td><?= htmlspecialchars($row['patient_name']); ?></td>
                <td><?= htmlspecialchars($row['service_name']); ?></td>
                <td><?= htmlspecialchars($row['appointment_type']); ?></td>
                <td><?= htmlspecialchars($row['queue_number']); ?></td>
                <td><?= date("h:i A", strtotime($row['appointment_time'])); ?></td>

                <td>
                    <span class="badge <?= strtolower(str_replace(' ', '', $row['status'])); ?>">
                        <?= htmlspecialchars($row['status']); ?>
                    </span>
                </td>
                <td class="action-buttons">
                    <button class="btn btn-sm viewBtn" style="background-color:#001BB7; color:white;">
  <i class="fas fa-eye"></i>
</button>

                    <button class="btn btn-success completeBtn" 
    data-id="<?= $row['appointment_id']; ?>" 
    data-date="<?= $row['appointment_date']; ?>"
    data-patient="<?= htmlspecialchars($row['patient_name']); ?>"
    data-birthday="<?= htmlspecialchars($row['birthday']); ?>"
    <?= ($row['status']!=='Pending')?'disabled':'' ?>>
    <i class="fas fa-check"></i>
</button>

                    <button class="btn btn-danger cancelBtn" data-id="<?= $row['appointment_id']; ?>" data-patient="<?= htmlspecialchars($row['patient_name']); ?>" <?= ($row['status']!=='Pending')?'disabled':'' ?>><i class="fas fa-times"></i></button>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>No appointments scheduled for today.</p>
    <?php endif; ?>
</div>

<!-- Appointments Table -->
<div class="card">
<?php if(mysqli_num_rows($result) > 0): ?>
<table id="allAppointmentsTable">
<thead>
<tr>
<th>Patient</th>
<th>Service</th>
<th>Type</th> <!-- New column -->
<th>Queue #</th> 
<th>Date</th>
<th>Time</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php while($row = mysqli_fetch_assoc($result)): ?>
<tr data-assignedby="<?= htmlspecialchars($row['assigned_by_name'] ?? 'N/A'); ?>" 
    data-reason="<?= htmlspecialchars($row['cancel_reason'] ?? ''); ?>">
<td><?= htmlspecialchars($row['patient_name']); ?></td>
<td><?= htmlspecialchars($row['service_name']); ?></td>
<td><?= htmlspecialchars($row['appointment_type']); ?></td> <!-- New column value -->
    <td><?= htmlspecialchars($row['queue_number']); ?></td>
<td><?= htmlspecialchars($row['appointment_date']); ?></td>
<td><?= date("h:i A", strtotime($row['appointment_time'])); ?></td>

<td>
  <span class="badge <?= strtolower(str_replace(' ', '', $row['status'])); ?>">
    <?= htmlspecialchars($row['status']); ?>
  </span>
</td>

<td class="action-buttons">
   <button class="btn btn-sm viewBtn" style="background-color:#001BB7; color:white;">
  <i class="fas fa-eye"></i>
</button>

    <button class="btn btn-success completeBtn" 
        data-id="<?= $row['appointment_id']; ?>" 
        data-date="<?= $row['appointment_date']; ?>" 
        <?= ($row['status']!=='Pending')?'disabled':'' ?>>
        <i class="fas fa-check"></i>
    </button>
    <button class="btn btn-danger cancelBtn" data-id="<?= $row['appointment_id']; ?>" data-patient="<?= htmlspecialchars($row['patient_name']); ?>" <?= ($row['status']!=='Pending')?'disabled':'' ?>><i class="fas fa-times"></i></button>
</td>

</tr>
<?php endwhile; ?>
</tbody>

</table>
<?php if($total_pages > 1): ?>
<nav aria-label="Page navigation example">
  <ul class="pagination justify-content-end mt-3">

    <!-- Previous -->
    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
      <a class="page-link custom-pagination" href="?search_name=<?= urlencode($search_name) ?>&status=<?= urlencode($status_filter) ?>&page=<?= $page-1 ?>" tabindex="-1">Previous</a>
    </li>

    <!-- Current Page Only -->
    <li class="page-item active">
      <a class="page-link custom-pagination" href="#"><?= $page ?></a>
    </li>

    <!-- Next -->
    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
      <a class="page-link custom-pagination" href="?search_name=<?= urlencode($search_name) ?>&status=<?= urlencode($status_filter) ?>&page=<?= $page+1 ?>">Next</a>
    </li>

  </ul>
</nav>
<?php endif; ?>
<?php else: ?>
<p>No appointments found.</p>
<?php endif; ?>
</div>
</div>

<!-- Complete Appointment Modal -->
<div class="modal fade" id="prescriptionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header complete-header text-white">

        <h5 class="modal-title"><i class="fas fa-prescription-bottle"></i> Complete Appointment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="prescriptionForm">
          <input type="hidden" id="prescriptionAppointmentId" name="appointment_id">

          <p><b>Patient:</b> <span id="prescriptionPatientName"></span></p>
          <p><b>Birthday:</b> <span id="prescriptionPatientBirthday"></span></p>
<p><b>Age:</b> <span id="prescriptionPatientAge"></span></p>


          <!-- Diagnosis & Treatment -->
          <div class="row g-2">
            <div class="col-6 mb-2">
              <label for="diagnosis" class="form-label small mb-0">Diagnosis</label>
              <textarea id="diagnosis" name="diagnosis" class="form-control form-control-sm" rows="2" required></textarea>
            </div>
            <div class="col-6 mb-2">
              <label for="treatment" class="form-label small mb-0">Treatment</label>
              <textarea id="treatment" name="treatment" class="form-control form-control-sm" rows="2" required></textarea>
            </div>
          </div>

          <hr class="my-2">

          <!-- Medications -->
          <h6>Medications</h6>
          <div id="medicationsWrapper">
            <div class="medicationItem border p-2 mb-2 rounded">
              <div class="row g-2 mb-1">
                <div class="col-6">
                  <label class="form-label small mb-0">Medication</label>
                  <input type="text" name="medication[]" class="form-control form-control-sm" placeholder="Medication" required>
                </div>
                <div class="col-3">
                  <label class="form-label small mb-0">Dosage</label>
                  <input type="text" name="dosage[]" class="form-control form-control-sm" placeholder="Dosage" required>
                </div>
                <div class="col-3">
                  <label class="form-label small mb-0">Frequency</label>
                  <select name="frequency[]" class="form-select form-select-sm">
                    <option value="Once a day">Once a day</option>
                    <option value="Twice a day">Twice a day</option>
                    <option value="Every 5 hours">Every 5 hours</option>
                    <option value="Every 8 hours">Every 8 hours</option>
                  </select>
                </div>
              </div>
              <div class="row g-2 mb-1">
                <div class="col-4">
                  <label class="form-label small mb-0">Duration (days)</label>
                  <input type="number" min="1" name="duration[]" class="form-control form-control-sm" value="1" required>
                </div>
                <div class="col-4">
                  <label class="form-label small mb-0">Start Date</label>
                  <input type="date" name="start_date[]" class="form-control form-control-sm" required>
                </div>
                <div class="col-4">
                  <label class="form-label small mb-0">End Date</label>
                  <input type="date" name="end_date[]" class="form-control form-control-sm" readonly>
                </div>
              </div>
              <div class="mb-1">
                <label class="form-label small mb-0">Instructions</label>
                <textarea name="instructions[]" class="form-control form-control-sm" rows="1" placeholder="Instructions"></textarea>
              </div>
              <button type="button" class="btn btn-danger btn-sm mt-1 removeMedication">Remove</button>
            </div>
          </div>
          <button type="button" class="btn btn-primary btn-sm mb-2" id="addMedication">Add Medication</button>

          <!-- Remarks -->
          <div class="mb-2">
            <label for="doctorRemarks" class="form-label small mb-0">Remarks</label>
            <textarea id="doctorRemarks" name="remarks" class="form-control form-control-sm" rows="2"></textarea>
          </div>

          <button type="submit" class="btn btn-success w-100 btn-sm">Complete Appointment</button>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

    $(document).ready(function(){
    // ----- DYNAMIC MEDICATION ROWS -----
    $("#addMedication").click(function(){
        let newItem = $(".medicationItem:first").clone();
        newItem.find("input, textarea").val("");
        newItem.find("select").val("Once a day");
        $("#medicationsWrapper").append(newItem);
    });

    $(document).on("click", ".removeMedication", function(){
        if($(".medicationItem").length > 1){
            $(this).closest(".medicationItem").remove();
        } else {
            Swal.fire("Warning", "At least one medication is required.", "warning");
        }
    });

    // Auto-calculate end date based on start_date + duration
    $(document).on("input change", "input[name='start_date[]'], input[name='duration[]']", function(){
        let container = $(this).closest(".medicationItem");
        let start = container.find("input[name='start_date[]']").val();
        let duration = parseInt(container.find("input[name='duration[]']").val());
        if(start && duration > 0){
            let end = new Date(start);
            end.setDate(end.getDate() + duration - 1);
            let yyyy = end.getFullYear();
            let mm = String(end.getMonth()+1).padStart(2,'0');
            let dd = String(end.getDate()).padStart(2,'0');
            container.find("input[name='end_date[]']").val(`${yyyy}-${mm}-${dd}`);
        }
    });

    // ----- SUBMIT COMPLETE APPOINTMENT -----
    $("#prescriptionForm").submit(function(e){
        e.preventDefault();
        let data = $(this).serialize() + '&complete_appointment=1';
        $.post('complete_appointment.php', data, function(res){
            if(res=='success'){ 
                Swal.fire('Completed!','Appointment marked complete.','success').then(()=>location.reload()); 
            } else if(res=='invalid'){
                Swal.fire('Warning!','Please fill all required fields.','warning');
            } else {
                Swal.fire('Error!','Something went wrong.','error');
            }
        });
    });
});


// Hamburger toggle
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
// Logout
document.getElementById('logoutBtn').addEventListener('click', function(e){
    e.preventDefault();
    Swal.fire({
        title:'Are you sure?',
        text:'You will be logged out from the system.',
        icon:'warning',
        showCancelButton:true,
        confirmButtonColor:'#001BB7',
        cancelButtonColor:'#d33',
        confirmButtonText:'Yes, log me out',
        cancelButtonText:'Cancel'
    }).then(result=>{
        if(result.isConfirmed) window.location.href='logout.php';
    });
});

$(document).ready(function(){

    // ----- EXPORT EXCEL -----
    $("#exportExcel").click(function(){
        let wb = XLSX.utils.table_to_book(document.getElementById("allAppointmentsTable"), {sheet:"Appointments"});
        XLSX.writeFile(wb, "Appointments.xlsx");
    });

    // ----- EXPORT PDF -----
    $("#exportPDF").click(function(){
        const { jsPDF } = window.jspdf;
        let doc = new jsPDF();
        doc.autoTable({ html: "#allAppointmentsTable" });
        doc.save("Appointments.pdf");
    });

    // ----- PRINT -----
    $("#printTable").click(function(){ window.print(); });

    // ----- VIEW DETAILS -----
    $(".viewBtn").click(function(){
    let tr = $(this).closest("tr");
    let patient = tr.find("td:first").text();
    let service = tr.find("td:nth-child(2)").text();
    let appointmentType = tr.find("td:nth-child(3)").text();
    let queueNumber = tr.find("td:nth-child(4)").text();
    let date = tr.find("td:nth-child(5)").text();
    let time = tr.find("td:nth-child(6)").text();
    let status = tr.find("td:nth-child(7) .badge").text();
    let assignedBy = tr.data("assignedby") || 'N/A';
    let cancelReason = tr.data("reason") || '';
    let notes = tr.data("notes") || '';
    let rescheduleReason = tr.data("reschedule") || '';
    let medicineNotes = tr.data("medicine") || '';
    let appointmentId = tr.data("id") || '';

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
        <div class="swal-view-card p-4 rounded">

            <p style="margin:0; font-weight:700; font-size:26px; color:#001BB7;">
                ${patient}
            </p>
            <p><i class="fas fa-id-badge"></i> Appointment ID: ${appointmentId}</p>

            <div class="swal-view-box p-3 rounded mb-3">
                <p><i class="fas fa-stethoscope"></i> <strong>Service:</strong> ${service}</p>
                <p><i class="fas fa-calendar-alt"></i> <strong>Date:</strong> ${date}</p>
                <p><i class="fas fa-clock"></i> <strong>Time:</strong> ${time}</p>
                <p><i class="fas fa-user-md"></i> <strong>Assigned By:</strong> ${assignedBy}</p>
                <p><i class="fas fa-info-circle"></i> <strong>Appointment Type:</strong> ${appointmentType}</p>
                <p><i class="fas fa-list-ol"></i> <strong>Queue #:</strong> ${queueNumber}</p>
                <p>
                    <i class="fas fa-calendar-check"></i> <strong>Status:</strong>
                    <span style="background:${badgeColor}; color:white; padding:4px 10px; border-radius:12px;">
                        ${status}
                    </span>
                </p>
            </div>

            ${extra ? `
            <table class="swal-view-table w-100 rounded">
                ${extra}
            </table>` : ''}
        </div>
    </div>
    `,
    width: '700px',
    showCloseButton: true,
    confirmButtonText: 'Close',
    confirmButtonColor: '#001BB7'
});

});

    // ----- CANCEL -----
    $(".cancelBtn").click(function(){
        let appointment_id = $(this).data("id");
        let patient_name = $(this).data("patient");
        Swal.fire({
            title:`Cancel Appointment for ${patient_name}?`,
            input:'text',
            inputPlaceholder:'Enter reason',
            icon:'warning',
            showCancelButton:true,
            confirmButtonColor:'#001BB7',
            cancelButtonColor:'#d33',
            confirmButtonText:'Yes, cancel'
        }).then(result=>{
            if(result.isConfirmed && result.value){
                $.post('doctor_appointments.php', {cancel_appointment:true, appointment_id, reason:result.value}, function(res){
                    if(res=='success'){ Swal.fire('Cancelled!','Appointment cancelled.','success').then(()=>location.reload()); }
                    else{ Swal.fire('Error!','Something went wrong.','error'); }
                });
            }
        });
    });

    // ----- COMPLETE -----
    $(".completeBtn").click(function(){
    let appointment_id = $(this).data("id");
    let patient_name = $(this).data("patient");
    let birthday = $(this).data("birthday");

    $("#prescriptionAppointmentId").val(appointment_id);
    $("#prescriptionPatientName").text(patient_name);

    if(birthday){
        let birth = new Date(birthday);
        let today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        let m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
            age--;
        }

        $("#prescriptionPatientBirthday").text(birthday);
        $("#prescriptionPatientAge").text(age + " years old");
    } else {
        $("#prescriptionPatientBirthday").text("N/A");
        $("#prescriptionPatientAge").text("N/A");
    }

    let modal = new bootstrap.Modal(document.getElementById('prescriptionModal'));
    modal.show();
});

});
// Disable Complete button if appointment date not yet arrived
$(".completeBtn").each(function(){
    let btn = $(this);
    let appointmentDate = btn.data("date"); // YYYY-MM-DD
    let today = new Date();
    today.setSeconds(0,0); // ignore seconds
    let apptDate = new Date(appointmentDate + "T00:00:00");

    if(apptDate > today){
        btn.prop("disabled", true);
        btn.attr("title", "Cannot complete before appointment date");
        btn.addClass("btn-secondary").removeClass("btn-success");
    }
});

var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
})

</script>
</body>
</html>