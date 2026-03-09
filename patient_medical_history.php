<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit;
}

$patient_id = $_SESSION['patient_id'];

// Fetch patient info
$patient_result = mysqli_query($link, "SELECT * FROM tblpatients WHERE patient_id='$patient_id'");
$patient = mysqli_fetch_assoc($patient_result);

// Handle search
$search = $_GET['search'] ?? '';
$safe_search = "%".mysqli_real_escape_string($link, $search)."%" ;

// Pagination settings
$limit = 3; // records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total records
$count_sql = "
    SELECT COUNT(*) AS total
    FROM tblappointments a
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    LEFT JOIN tbldoctors d ON a.doctor_assigned = d.doctor_id
    WHERE a.patient_id = ? AND (a.appointment_date LIKE ? OR s.service_name LIKE ? OR d.fullname LIKE ?)
";
$stmt_count = mysqli_prepare($link, $count_sql);
mysqli_stmt_bind_param($stmt_count, "isss", $patient_id, $safe_search, $safe_search, $safe_search);
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$total_rows = mysqli_fetch_assoc($result_count)['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch appointments with limit
$history_sql = "
    SELECT a.appointment_id, a.appointment_date, s.service_name, d.fullname AS doctor_name,
           mh.diagnosis, mh.treatment, mh.notes
    FROM tblappointments a
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    LEFT JOIN tbldoctors d ON a.doctor_assigned = d.doctor_id
    LEFT JOIN tblmedical_history mh ON a.appointment_id = mh.appointment_id
    WHERE a.patient_id = ? AND (a.appointment_date LIKE ? OR s.service_name LIKE ? OR d.fullname LIKE ?)
    ORDER BY a.appointment_date DESC
    LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($link, $history_sql);
mysqli_stmt_bind_param($stmt, "isssii", $patient_id, $safe_search, $safe_search, $safe_search, $limit, $offset);
mysqli_stmt_execute($stmt);
$history_result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medical History - <?= htmlspecialchars($patient['full_name']); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* GENERAL */
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b;}

/* SIDEBAR */
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
.sidebar h2 {text-align:center; margin-bottom:20px; font-size:24px; font-weight:700;}
.sidebar h2 img {width:45px; height:45px; margin-right:10px; vertical-align:middle; border-radius:50%; object-fit:cover;}
.sidebar a {color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;}
.sidebar a i {margin-right:12px; font-size:18px;}
.sidebar a:hover {background: rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active {background: rgba(255,255,255,0.25); font-weight:600;}

/* MAIN CONTENT */
.main {margin-left:250px; padding:30px; flex:1;}
.main h2 {margin-bottom:20px; color:#001BB7; font-weight:700;}

/* SEARCH BAR */
form input[type="text"] {
    width:100%;
    max-width:1090px;
    padding:12px 16px;
    font-size:15px;
    border:1px solid #d1d5db;
    border-radius:8px;
    outline:none;
    transition:0.2s;
}
form input[type="text"]:focus {border-color: #001BB7; box-shadow:0 0 0 3px rgba(0,27,183,0.2);}
form button {background:#001BB7; color:#fff; border:none; padding:12px 18px; border-radius:8px; font-weight:500; cursor:pointer; transition:0.2s;}
form button:hover {background:#0010a0;}

/* TABLE DESIGN */
.table {
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
    background:#fff;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 8px 20px rgba(0,0,0,0.08);
}
.table th, .table td {
    padding:14px 16px;
    text-align:left;
}
.table th {
    background:#001BB7;
    color:#fff;
    font-weight:600;
    font-size:14px;
}
.table tbody tr {
    border-bottom:1px solid #e5e7eb;
    transition:0.2s;
}
.table tbody tr:hover {
    background:#f1f5f9;
}

/* VIEW DETAILS BUTTON */
.btn-view {
    background:#001BB7;
    color:#fff;
    padding:8px 12px;
    border:none;
    border-radius:8px;
    font-size:14px;
    cursor:pointer;
    transition:0.2s;
}
.btn-view:hover {background:#0010a0;}

/* HAMBURGER & OVERLAY */
.hamburger { display: none; position: fixed; top:15px; left:15px; font-size:22px; color:#001BB7; background:#fff; border:none; border-radius:8px; padding:8px; cursor:pointer; z-index:1201; }
#overlay { position:fixed; inset:0; background:rgba(0,0,0,0.45); opacity:0; visibility:hidden; transition:0.25s ease; z-index:1200; }
#overlay.active { opacity:1; visibility:visible; }

/* RESPONSIVE */
@media(max-width:768px) {
  .sidebar {transform:translateX(-100%); transition: transform 0.3s ease; width:230px; z-index:1200;}
  .sidebar.active {transform:translateX(0);}
  .hamburger {display:block;}
  .main {margin-left:0; padding:20px;}
}
/* SWEETALERT CLOSE BUTTON DESIGN */
.btn-close-custom {
    background: #001BB7;
    color: #fff;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    font-size: 14px;
    transition: 0.2s;
}
.btn-close-custom:hover {
    background: #0010a0;
}
/* TABLE DESIGN */
.table {
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
    background:#fff;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 8px 20px rgba(0,0,0,0.08);
    border: 1px solid #ccc; /* Added border for table */
}
.table th, .table td {
    padding:14px 16px;
    text-align:left;
    border: 1px solid #ccc; /* Added border for cells */
}
.table th {
    background:#001BB7;
    color:#fff;
    font-weight:600;
    font-size:14px;
}
.table tbody tr {
    border-bottom:1px solid #e5e7eb;
    transition:0.2s;
}
.table tbody tr:hover {
    background:#f1f5f9;
}
/* Pagination button group style */
.pagination-wrapper {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end; /* align to right */
}
.pagination {
    list-style: none;
    display: flex;
    padding: 0;
    margin: 0;
}
.pagination .page-item {
    display: inline;
}
.pagination .page-link {
    padding: 8px 16px;
    border: 1px solid #ccc;
    color: #001BB7;
    text-decoration: none;
    transition: 0.2s;
    margin-left: -1px; /* overlap borders to remove gap */
    border-radius: 0; /* flat edges for button group */
}
.pagination .page-item:first-child .page-link {
    border-top-left-radius: 6px;
    border-bottom-left-radius: 6px;
    margin-left: 0;
}
.pagination .page-item:last-child .page-link {
    border-top-right-radius: 6px;
    border-bottom-right-radius: 6px;
}
.pagination .page-item.active .page-link {
    background-color: #001BB7;
    color: white;
    border-color: #001BB7;
}
.pagination .page-item.disabled .page-link {
    color: #999;
    pointer-events: none;
    cursor: default;
    background: #f1f5f9;
}
.pagination .page-link:hover:not(.disabled):not(.active) {
    background: #0010a0;
    color: #fff;
}
.header-container h2 {
    color: white !important;
}
/* HAMBURGER & OVERLAY */
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
    padding: 8px;
    cursor: pointer;
    z-index: 1201;
}

#overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    opacity: 0;
    visibility: hidden;
    transition: 0.25s ease;
    z-index: 1200;
}

#overlay.active {
    opacity: 1;
    visibility: visible;
}

@media(max-width:768px) {
  .sidebar {
      transform: translateX(-100%);
      transition: transform 0.3s ease;
      width: 230px;
      z-index: 1202;
  }
  .sidebar.active {
      transform: translateX(0);
  }
  .hamburger { display:block; }
  .main { margin-left:0; padding:20px; }
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
/* ===== DARK MODE: Pagination ===== */
body.dark-mode .pagination-wrapper {
    justify-content: flex-end;
}

body.dark-mode .pagination .page-link {
    background: #0b1220 !important;
    color: #fff !important;
    border-color: rgba(255,255,255,0.2) !important;
}

body.dark-mode .pagination .page-item.active .page-link {
    background-color: #001BB7 !important;
    color: #fff !important;
    border-color: #001BB7 !important;
}

body.dark-mode .pagination .page-link:hover {
    background: rgba(255,255,255,0.1) !important;
    color: #fff !important;
}

/* ===== DARK MODE: Table Hover ===== */
body.dark-mode tr:hover {
    background: rgba(255,255,255,0.08) !important;
}
/* =========================
   MEDICAL HISTORY CARD (MODAL)
   ========================= */

.history-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 3px 6px rgba(0,0,0,0.1);
    margin-bottom: 15px;
    overflow: hidden;
}

.history-card-header {
    background: #001BB7;
    color: #fff;
    padding: 10px 15px;
    font-weight: 600;
}

.history-card-body {
    padding: 15px;
}

.history-card-body p {
    margin: 8px 0;
    font-size: 15px;
}

.history-card-body .label {
    font-weight: 700;
    color: #001BB7;
}

/* =========================
   DARK MODE
   ========================= */

body.dark-mode .history-card {
    background: #111b2b !important;
    border: 1px solid rgba(255,255,255,0.18);
    box-shadow: 0 3px 6px rgba(0,0,0,0.6);
}

body.dark-mode .history-card-header {
    background: linear-gradient(90deg, #0f1a38, #00122b);
}

body.dark-mode .history-card-body {
    color: #fff;
}

body.dark-mode .history-card-body .label {
    color: #fff;
}
body.dark-mode h2{
    color: white;
}
/* ===== RESPONSIVE PRESCRIPTION TABLE ===== */
.prescription-table-wrapper{
    width:100%;
    overflow-x:auto;
    -webkit-overflow-scrolling:touch;
    margin-top:10px;
}

.prescription-table{
    width:100%;
    min-width:750px;
    border-collapse:collapse;
    border:1px solid #000;
}

.prescription-table th,
.prescription-table td{
    border:1px solid #000;
    padding:8px;
}

/* Tablet */
@media(max-width:768px){
    .prescription-table{
        min-width:700px;
    }
    .prescription-table th,
    .prescription-table td{
        font-size:13px;
        padding:7px;
        white-space:nowrap;
    }
}

/* Phone */
@media(max-width:480px){
    .prescription-table{
        min-width:650px;
    }
    .prescription-table th,
    .prescription-table td{
        font-size:12px;
        padding:6px;
    }
}
</style>
</head>
<body>

<!-- HAMBURGER BUTTON -->
<button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>
    <a href="patient_dashboard.php" style="text-decoration:none; color:white;">
        <img src="logo.png" alt="KNL Logo" 
         style="width:45px; height:45px; margin-right:10px; vertical-align:middle; border-radius:50%; object-fit:cover;">
        KNL Health Center
    </a>
</h2>

    <a href="patient_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="patient_profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="patient_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="patient_request.php"><i class="fas fa-calendar-plus"></i> Request Appointment</a>
    <a href="patient_appointments.php"><i class="fas fa-calendar-alt"></i> My Appointments</a>
    <a href="patient_medical_history.php" class="active"><i class="fas fa-file-medical"></i> Medical History</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- OVERLAY -->
<div id="overlay"></div>

<!-- MAIN CONTENT -->
<div class="main">
    <?php include 'patient_header.php'; ?>
    <h2>Medical History - <?= htmlspecialchars($patient['full_name']); ?></h2>

    <form method="GET" class="mb-3 d-flex" style="gap:10px; flex-wrap:wrap;">
    <input type="text" name="search" placeholder="Search by date, service, or doctor" value="<?= htmlspecialchars($search); ?>">
    <button type="submit"><i class="fas fa-search" style="margin-right:8px;"></i>Search</button>
</form>

    <table class="table">
        <thead>
            <tr>
                <th>Appointment Date</th>
                <th>Service</th>
                <th>Doctor</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if(mysqli_num_rows($history_result) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($history_result)): ?>
            <tr>
                <td><?= htmlspecialchars($row['appointment_date']); ?></td>
                <td><?= htmlspecialchars($row['service_name']); ?></td>
                <td><?= htmlspecialchars($row['doctor_name'] ?? 'Not assigned'); ?></td>
                <td>
                    <button class="btn-view" onclick="viewHistory(<?= $row['appointment_id']; ?>)">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4" style="text-align:center; padding:20px;">No medical history records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php if($total_pages > 1): ?>
<div class="pagination-wrapper">
    <ul class="pagination">
        <!-- Previous Button -->
        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
            <a class="page-link" href="?search=<?= urlencode($search); ?>&page=<?= max(1, $page-1); ?>">Prev</a>
        </li>

        <!-- Current Page -->
        <li class="page-item active">
            <span class="page-link"><?= $page; ?></span>
        </li>

        <!-- Next Button -->
        <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
            <a class="page-link" href="?search=<?= urlencode($search); ?>&page=<?= min($total_pages, $page+1); ?>">Next</a>
        </li>
    </ul>
</div>
<?php endif; ?>

</div>

<script>
// Logout confirmation
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
    }).then((result) => { if(result.isConfirmed) window.location.href='logout.php'; });
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
// AJAX view history
function viewHistory(appointmentId) {
    $.ajax({
        url: 'patient_medical_history_ajax.php',
        method: 'GET',
        data: { appointment_id: appointmentId },
        success: function(response){
            Swal.fire({
                title: 'Medical History',
                html: `<div style="max-height:none; overflow:auto;">${response}</div>`,
                width: '90%',
                heightAuto: false,
                showConfirmButton: true,
                confirmButtonText: 'Close',
                customClass: {
                    confirmButton: 'btn-close-custom'
                },
                buttonsStyling: false
            });
        }
    });
}

</script>

</body>
</html>
