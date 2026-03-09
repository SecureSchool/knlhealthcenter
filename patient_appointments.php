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

if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit;
}

$status_filter = $_GET['status'] ?? '';
$search_name = $_GET['search'] ?? '';
$patient_id = $_SESSION['patient_id'];

// Fetch patient info
$patient_result = mysqli_query($link, "SELECT * FROM tblpatients WHERE patient_id='$patient_id'");
$patient = mysqli_fetch_assoc($patient_result);
$patient_name = $patient['full_name'] ?? 'Unknown';

// Handle cancellation
$error = '';
$success = '';
if (isset($_POST['cancel_appointment_id'])) {
    $appointment_id = intval($_POST['cancel_appointment_id']);
    $cancel_reason = mysqli_real_escape_string($link, $_POST['cancel_reason']);

    $update_sql = "UPDATE tblappointments 
                   SET status='Cancelled', cancel_reason='$cancel_reason' 
                   WHERE appointment_id='$appointment_id' 
                   AND patient_id='$patient_id' 
                   AND status='Pending'";
    if (mysqli_query($link, $update_sql)) {
        $success = "Appointment cancelled successfully.";
        $appt_result = mysqli_query($link, "
            SELECT a.appointment_date, s.service_name
            FROM tblappointments a
            LEFT JOIN tblservices s ON a.service_id = s.service_id
            WHERE a.appointment_id='$appointment_id'
        ");
        $appt = mysqli_fetch_assoc($appt_result);
        $service_info = ($appt['service_name'] ?? '') . " on " . ($appt['appointment_date'] ?? '');
        logAction($link, "Cancelled Appointment", "Patient Appointment", $service_info, $patient_name);
    } else {
        $error = "Failed to cancel appointment: " . mysqli_error($link);
    }
}

// Pagination
$limit = 5;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = "WHERE a.patient_id='$patient_id'";
if ($status_filter) $where .= " AND a.status = '" . mysqli_real_escape_string($link, $status_filter) . "'";
if ($search_name) {
    $searchEscaped = mysqli_real_escape_string($link, $search_name);
    $where .= " AND (s.service_name LIKE '%$searchEscaped%' 
                    OR a.appointment_date LIKE '%$searchEscaped%' 
                    OR a.appointment_time LIKE '%$searchEscaped%')";
}

// Appointments query
$appointments = mysqli_query($link, "
    SELECT a.*, s.service_name, d.fullname AS doctor_name
    FROM tblappointments a
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    LEFT JOIN tbldoctors d ON a.doctor_assigned = d.doctor_id
    $where
    ORDER BY a.date_created DESC
    LIMIT $limit OFFSET $offset
");

// Count total pages
$count_sql = "SELECT COUNT(*) AS total FROM tblappointments WHERE patient_id='$patient_id'";
$total_rows = mysqli_fetch_assoc(mysqli_query($link, $count_sql))['total'];
$total_pages = ceil($total_rows / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Appointments</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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

* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b;}

/* SIDEBAR */
.sidebar {
    width:250px;
    background: var(--sidebar-bg);
    color: var(--text-color);
    position:fixed;
    height:100%;
    padding:25px 15px;
    display:flex;
    flex-direction:column;
    transition:0.3s;
}
.sidebar h2 {
    text-align:center;
    margin-bottom:20px;
    font-size:24px;
    font-weight:700;
    color: #fff;
}
.sidebar h2 img {
    width:45px; height:45px; margin-right:10px; vertical-align:middle; border-radius:50%; object-fit:cover;
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
.sidebar a i {margin-right:12px; font-size:18px; transition:0.3s;}
.sidebar a:hover {
    background: var(--sidebar-hover);
    padding-left:24px;
    color:#fff;
}
.sidebar a:hover i { transform: rotate(15deg); }
.sidebar a.active { background: rgba(255,255,255,0.25); box-shadow:0 4px 15px rgba(0,0,0,0.2); font-weight:600; }

/* MAIN */
.main {margin-left:250px; padding:30px; flex:1; transition: margin-left 0.3s;}
h1 {margin-bottom:20px; color: var(--primary-color); font-weight:700;}
.card {background: var(--card-bg); padding:25px; border-radius:16px; box-shadow: var(--card-shadow); margin-bottom:25px;}
table {width:100%; border-collapse:collapse; border-radius:16px; overflow:hidden;}
th, td {padding:14px; text-align:left; border-bottom:1px solid #e5e7eb;}
th {background: var(--primary-color); color:white; font-weight:600;}
tr:hover {background:#f1f5f9;}
.status-pending {color:#facc15; font-weight:600;}
.status-approved {color:#16a34a; font-weight:600;}
.status-completed {color:#3b82f6; font-weight:600;}
.status-cancelled {color:#ef4444; font-weight:600;}
.icon-btn {width:38px; height:38px; border:none; border-radius:8px; display:flex; align-items:center; justify-content:center; cursor:pointer;}
.icon-btn.view {background:#001BB7; color:#fff;}
.icon-btn.cancel {background:#ef4444; color:#fff;}
.icon-btn:disabled {background:#cbd5e1; cursor:not-allowed;}

/* Hamburger menu for mobile */
.hamburger {display:none; position:fixed; top:15px; left:15px; font-size:22px; color:#001BB7; background:#fff; border:none; border-radius:8px; padding:8px; cursor:pointer; z-index:1200;}
#overlay {position:fixed; inset:0; background:rgba(0,0,0,0.45); opacity:0; visibility:hidden; transition:0.25s; z-index:1050;}
#overlay.active {opacity:1; visibility:visible;}
@media (max-width:768px){
    .hamburger{display:block;}
    .sidebar{transform:translateX(-280px); transition:0.3s; z-index:1100;}
    .sidebar.active{transform:translateX(0);}
    .main{margin-left:0; padding:20px;}
}
.filter-form input, .filter-form select, .filter-form button {padding:10px; margin-right:5px; border-radius:8px; border:1px solid #cbd5e1;}
.filter-form button {background: var(--primary-color); color:#fff; border:none; cursor:pointer;}
/* Pagination */
.pagination-wrapper {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end; /* right align */
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
    border: 1px solid #001BB7;
    color: #001BB7;
    text-decoration: none;
    margin-left: -1px; /* removes gap between buttons */
    border-radius: 0; /* flat edges for middle buttons */
    transition: 0.2s;
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
    background: #f1f5f9;
}
.pagination .page-link:hover:not(.disabled):not(.active) {
    background: #0010a0;
    color: #fff;
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
/* ====== SWEETALERT DARK CARD ====== */
.swal-card{
    background: #f5f7fa;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.swal-title{
    margin: 0;
    font-weight: 700;
    font-size: 28px;
    color: #001BB7;
}

.swal-text{
    margin: 2px 0;
    color: #1e293b;
}

.swal-inner{
    background: #fff;
    padding: 15px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 15px;
}

.swal-table{
    width:100%;
    border-collapse: collapse;
    background:#fff;
    border-radius:12px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
    padding:10px;
}

.status-badge{
    color: #fff;
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 600;
}

/* ===== DARK MODE FOR MODAL CARD ===== */
body.dark-mode .swal-card{
    background: #111b2b !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.6) !important;
}

body.dark-mode .swal-title{
    color: #fff !important;
}

body.dark-mode .swal-text{
    color: #fff !important;
}

body.dark-mode .swal-inner{
    background: #0b1220 !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.6) !important;
}

body.dark-mode .swal-table{
    background: #0b1220 !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.6) !important;
}

body.dark-mode .swal2-popup{
    background: #111b2b !important;
    color: #fff !important;
}

body.dark-mode .swal2-title,
body.dark-mode .swal2-content{
    color: #fff !important;
}
body.dark-mode h1{
    color: white;
}

</style>
</head>
<body>

<!-- Hamburger Button -->
<button class="hamburger" aria-label="Open navigation menu" aria-expanded="false">
  <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
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
    <a href="patient_appointments.php" class="active"><i class="fas fa-calendar-alt"></i> My Appointments</a>
    <a href="patient_medical_history.php"><i class="fas fa-file-medical"></i> Medical History</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<div id="overlay"></div>

<!-- Main content -->
<div class="main">
    <?php include 'patient_header.php'; ?>
    <h1>My Appointments</h1>

    <!-- Filter Form -->
    <form method="GET" class="filter-form" style="margin-bottom:15px; display:flex; gap:5px;">
    <input type="text" name="search" placeholder="Search by service, date, or time"
           value="<?php echo htmlspecialchars($search_name); ?>" style="flex:1;">
    <select name="status">
        <option value="" <?php if(!$status_filter) echo 'selected'; ?>>All Status</option>
        <option value="Pending" <?php if($status_filter=='Pending') echo 'selected'; ?>>Pending</option>
        <option value="Completed" <?php if($status_filter=='Completed') echo 'selected'; ?>>Completed</option>
        <option value="Cancelled" <?php if($status_filter=='Cancelled') echo 'selected'; ?>>Cancelled</option>
    </select>
    <button type="submit"><i class="fas fa-search"></i> Search</button>
</form>

    <!-- Appointments Table -->
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($appointments)>0): ?>
                    <?php while($row=mysqli_fetch_assoc($appointments)): ?>
                    <tr data-cancel-reason="<?php echo htmlspecialchars($row['cancel_reason'] ?? ''); ?>">
                        <td><?php echo htmlspecialchars($row['service_name']); ?></td>
                        <td><?php echo $row['appointment_date']; ?></td>
                        <td><?php echo $row['appointment_time']; ?></td>
                        <td class="status-<?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></td>
                        <td style="display:flex; gap:5px;">
                            <button type="button" class="icon-btn view" onclick="viewDetailsFromRow(this.closest('tr'))"><i class="fas fa-eye"></i></button>
                            <button type="button" class="icon-btn cancel" onclick="cancelAppointment('<?php echo $row['appointment_id']; ?>')" <?php echo ($row['status']!=='Pending')?'disabled':''; ?>><i class="fas fa-times"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center;">No appointments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if($total_pages > 1): ?>
<div class="pagination-wrapper">
    <ul class="pagination">
        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
            <a class="page-link" href="?search=<?= urlencode($search_name); ?>&status=<?= urlencode($status_filter); ?>&page=<?= max(1, $page-1); ?>">Prev</a>
        </li>
        <li class="page-item active">
            <span class="page-link"><?= $page; ?></span>
        </li>
        <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
            <a class="page-link" href="?search=<?= urlencode($search_name); ?>&status=<?= urlencode($status_filter); ?>&page=<?= min($total_pages, $page+1); ?>">Next</a>
        </li>
    </ul>
</div>
<?php endif; ?>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
const hamburger = document.querySelector('.hamburger');
const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('overlay');

function openMenu() { sidebar.classList.add('active'); overlay.classList.add('active'); hamburger.setAttribute('aria-expanded','true'); document.body.style.overflow='hidden'; }
function closeMenu() { sidebar.classList.remove('active'); overlay.classList.remove('active'); hamburger.setAttribute('aria-expanded','false'); document.body.style.overflow=''; }
hamburger.addEventListener('click', ()=>{ sidebar.classList.contains('active')?closeMenu():openMenu(); });
overlay.addEventListener('click', closeMenu);
document.querySelectorAll('.sidebar a').forEach(link=>{ link.addEventListener('click', ()=>{ if(window.innerWidth<=768) closeMenu(); }); });
document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeMenu(); });

// Logout
document.getElementById('logoutBtn').addEventListener('click',(e)=>{ e.preventDefault(); Swal.fire({ title:'Logout?', text:'You will be logged out from the system.', icon:'warning', showCancelButton:true, confirmButtonColor:'#001BB7', cancelButtonColor:'#d33', confirmButtonText:'Yes, log me out', cancelButtonText:'Cancel' }).then((result)=>{ if(result.isConfirmed){ window.location.href='logout.php'; } }); });

// View details
function viewDetailsFromRow(tr) {
    const appointmentId = tr.dataset.id || '';
    const service = tr.cells[0].innerText;
    const date = tr.cells[1].innerText;
    const time = tr.cells[2].innerText;
    const status = tr.cells[3].innerText;
    const cancelReason = tr.dataset.cancelReason || '';
    const rescheduleReason = tr.dataset.reschedule || '';
    const notes = tr.dataset.notes || '';
    const medicineNotes = tr.dataset.medicine || '';
    const assignedBy = tr.dataset.assignedBy || 'N/A';

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
        <div class="swal-card">
            <p class="swal-title">${service}</p>
            <p class="swal-text"><i class="fas fa-id-badge"></i> Appointment ID: ${appointmentId}</p>

            <div class="swal-inner">
                <p><i class="fas fa-calendar-alt"></i> <strong>Date:</strong> ${date}</p>
                <p><i class="fas fa-clock"></i> <strong>Time:</strong> ${time}</p>
                <p><i class="fas fa-user-md"></i> <strong>Assigned By:</strong> ${assignedBy}</p>
                <p><i class="fas fa-info-circle"></i> <strong>Status:</strong> 
                    <span class="status-badge" style="background:${badgeColor};">${status}</span>
                </p>
            </div>

            ${extra ? `<table class="swal-table">
                ${extra}
            </table>` : ''}
        </div>
    </div>
    `,
    width: '700px',
    showCloseButton: true,
    confirmButtonText: 'Close',
    confirmButtonColor: '#001BB7',
    didOpen: () => {
        const tableCells = Swal.getHtmlContainer().querySelectorAll('td');
        tableCells.forEach((td, index) => {
            td.style.padding = '8px 10px';
            if(index % 2 === 0) td.style.fontWeight = '600';
        });
    }
});

}

// Cancel
function cancelAppointment(id){
    Swal.fire({
        title:'Cancel Appointment',
        input:'textarea',
        inputLabel:'Reason for cancellation',
        inputPlaceholder:'Type your reason here...',
        showCancelButton:true,
        confirmButtonText:'Yes, cancel it',
        cancelButtonText:'No, keep it',
        preConfirm:(reason)=>{if(!reason) Swal.showValidationMessage('Please enter a reason'); return reason;}
    }).then((result)=>{
        if(result.isConfirmed){
            const form=document.createElement('form'); form.method='POST'; form.action='';
            const idInput=document.createElement('input'); idInput.type='hidden'; idInput.name='cancel_appointment_id'; idInput.value=id;
            const reasonInput=document.createElement('input'); reasonInput.type='hidden'; reasonInput.name='cancel_reason'; reasonInput.value=result.value;
            form.appendChild(idInput); form.appendChild(reasonInput); document.body.appendChild(form); form.submit();
        }
    });
}

<?php if($success): ?>
Swal.fire({icon:'success', title:'Success', text:'<?php echo $success; ?>'});
<?php endif; ?>
<?php if($error): ?>
Swal.fire({icon:'error', title:'Error', text:'<?php echo $error; ?>'});
<?php endif; ?>
</script>
</body>
</html>
