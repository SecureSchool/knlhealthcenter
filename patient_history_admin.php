<?php
session_start();
require_once "config.php";

// Redirect if not logged in as admin
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'];
$patient_id = $_GET['id'] ?? '';

// Fetch patient info
$patient_sql = "SELECT * FROM tblpatients WHERE patient_id = ?";
$stmt = mysqli_prepare($link, $patient_sql);
mysqli_stmt_bind_param($stmt, "i", $patient_id);
mysqli_stmt_execute($stmt);
$patient_result = mysqli_stmt_get_result($stmt);
$patient = mysqli_fetch_assoc($patient_result);

if(!$patient){
    echo "<h2 style='color:red; text-align:center;'>Patient not found.</h2>";
    exit;
}

// Handle search
$search = $_GET['search'] ?? '';
$safe_search = "%".mysqli_real_escape_string($link, $search)."%";

// --- Pagination settings ---
$limit = 5; // records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total appointments for this patient (with search)
$count_sql = "
    SELECT COUNT(*) AS total
    FROM tblappointments a
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    WHERE a.patient_id = ?
    AND (a.appointment_date LIKE ? OR s.service_name LIKE ?)
";
$stmt_count = mysqli_prepare($link, $count_sql);
mysqli_stmt_bind_param($stmt_count, "iss", $patient_id, $safe_search, $safe_search);
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$total_rows = mysqli_fetch_assoc($result_count)['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch appointments with LIMIT and OFFSET
$appointments_sql = "
    SELECT a.*, s.service_name, a.appointment_type
    FROM tblappointments a
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    WHERE a.patient_id = ?
    AND (a.appointment_date LIKE ? OR s.service_name LIKE ?)
    ORDER BY a.appointment_date DESC
    LIMIT ? OFFSET ?
";
$stmt2 = mysqli_prepare($link, $appointments_sql);
mysqli_stmt_bind_param($stmt2, "issii", $patient_id, $safe_search, $safe_search, $limit, $offset);
mysqli_stmt_execute($stmt2);
$appointments_result = mysqli_stmt_get_result($stmt2);


// AJAX handler for medical history + prescriptions
if(isset($_GET['ajax_history']) && isset($_GET['appointment_id'])){
    $appt_id = intval($_GET['appointment_id']);
    
    // Medical History
    $sql = "
        SELECT mh.*, d.fullname AS doctor_name
        FROM tblmedical_history mh
        LEFT JOIN tbldoctors d ON mh.doctor_id = d.doctor_id
        WHERE mh.appointment_id = ?
        ORDER BY mh.created_at DESC
    ";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $appt_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if(mysqli_num_rows($result) === 0){
        echo "<p>No medical history found for this appointment.</p>";
        exit;
    }

    while($h = mysqli_fetch_assoc($result)){
        // Medical History Card
        echo "<div class='history-card'>";
echo "<div class='history-card-header'>Medical History</div>";
echo "<div class='history-card-body'>";
echo "<p><span class='label'>Date:</span> ".htmlspecialchars($h['created_at'])."</p>";
echo "<p><span class='label'>Diagnosis:</span> ".htmlspecialchars($h['diagnosis'])."</p>";
echo "<p><span class='label'>Treatment:</span> ".htmlspecialchars($h['treatment'])."</p>";
echo "<p><span class='label'>Notes:</span> ".htmlspecialchars($h['notes'])."</p>";
echo "<p><span class='label'>Doctor:</span> ".htmlspecialchars($h['doctor_name'])."</p>";
echo "</div></div>";


        // Prescriptions Card
        $presc_sql = "SELECT * FROM tblprescriptions WHERE appointment_id = ?";
        $stmt2 = mysqli_prepare($link, $presc_sql);
        mysqli_stmt_bind_param($stmt2, "i", $appt_id);
        mysqli_stmt_execute($stmt2);
        $presc_result = mysqli_stmt_get_result($stmt2);

        if(mysqli_num_rows($presc_result) > 0){
            echo "<div style='background:#fff; border-radius:12px; box-shadow:0 3px 6px rgba(0,0,0,0.1); margin-bottom:15px;'>";
            echo "<div style='background:#28a745; color:#fff; padding:10px 15px; border-radius:12px 12px 0 0; font-weight:600;'>Prescriptions</div>";
            echo "<div class='table-responsive' style='padding:15px;'>";
echo "<table class='table table-bordered'>";
            echo "<thead><tr>
                    <th>Medication</th>
                    <th>Dosage</th>
                    <th>Instructions</th>
                    <th>Duration</th>
                    <th>Frequency</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Date Prescribed</th>
                  </tr></thead><tbody>";

            while($p = mysqli_fetch_assoc($presc_result)){
                echo "<tr>";
                echo "<td>".htmlspecialchars($p['medication'])."</td>";
                echo "<td>".htmlspecialchars($p['dosage'])."</td>";
                echo "<td>".htmlspecialchars($p['instructions'])."</td>";
                echo "<td>".htmlspecialchars($p['duration'])."</td>";
                echo "<td>".htmlspecialchars($p['frequency'])."</td>";
                echo "<td>".htmlspecialchars($p['start_date'])."</td>";
                echo "<td>".htmlspecialchars($p['end_date'])."</td>";
                echo "<td>".htmlspecialchars($p['date_prescribed'])."</td>";
                echo "</tr>";
            }

            echo "</tbody></table></div></div>";
        }
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($patient['full_name']); ?> - Appointments</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b;}
.main {margin-left:250px; padding:30px; flex:1;}

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
.sidebar h2 img {width:45px; height:45px; margin-right:10px; vertical-align:middle; border-radius:50%; object-fit:cover;}

/* Hamburger */
.hamburger {display:none; position:fixed; top:15px; left:15px; font-size:20px; color:#001BB7; background:#fff; border:none; border-radius:8px; padding:8px 10px; cursor:pointer; z-index:1200;}
#overlay {position:fixed; inset:0; background: rgba(0,0,0,0.45); opacity:0; visibility:hidden; transition:0.25s; z-index:1050;}
#overlay.active {opacity:1; visibility:visible;}
@media(max-width:768px){
    .hamburger {display:block;}
    .sidebar {transform:translateX(-280px); transition:0.3s; z-index:1100;}
    .sidebar.active {transform:translateX(0);}
    .main {margin-left:0; padding:20px;}
}

/* Buttons */
.icon-btn {width:38px; height:38px; border:none; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; cursor:pointer;}
.icon-btn.view {background:#001BB7; color:#fff;}
.icon-btn.view:hover {background:#0010a0;}
.btn-close-custom {background:#001BB7 !important; color:#fff !important; border:none; padding:8px 20px; border-radius:8px;}
.btn-close-custom:hover {background:#000F8C !important;}
/* TABLE HEADER COLOR */
.table thead th {
    background-color: #001BB7 !important;
    color: #fff !important;
    text-align: center;
    vertical-align: middle;
    padding: 12px;
    font-weight: 600;
}

/* HOVER EFFECT */
.table tbody tr:hover td {
    background-color: #e6ecff !important;
    transition: 0.2s ease-in-out;
    cursor: pointer;
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
/* ===== DARK MODE - TABLE ===== */
body.dark-mode .table {
    background: #0b1220 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.18) !important;
}

body.dark-mode .table thead th {
    background: #0b1430 !important;
    color: #fff !important;
    border-bottom: 1px solid rgba(255,255,255,0.2) !important;
}

body.dark-mode .table tbody td {
    background: #0b1220 !important;
    color: #fff !important;
    border-bottom: 1px solid rgba(255,255,255,0.12) !important;
}

body.dark-mode .table tbody tr:hover td {
    background: rgba(255,255,255,0.06) !important;
}

body.dark-mode .table-bordered {
    border: 1px solid rgba(255,255,255,0.18) !important;
}

body.dark-mode .table-bordered th,
body.dark-mode .table-bordered td {
    border: 1px solid rgba(255,255,255,0.12) !important;
}

/* ===== DARK MODE - VIEW MODAL ===== */
body.dark-mode .swal2-popup {
    background: #111b2b !important;
    color: #fff !important;
}

body.dark-mode .swal2-title,
body.dark-mode .swal2-content {
    color: #fff !important;
}

body.dark-mode .swal2-html-container {
    color: #fff !important;
}

body.dark-mode .swal2-modal {
    background: #111b2b !important;
}

body.dark-mode .swal2-confirm {
    background: #001BB7 !important;
    color: #fff !important;
    border: none !important;
}

body.dark-mode .swal2-cancel {
    background: #7f8c8d !important;
    color: #fff !important;
    border: none !important;
}

body.dark-mode .swal2-popup .swal2-styled {
    box-shadow: none !important;
}

body.dark-mode .swal2-html-container table {
    background: #0b1220 !important;
    color: #fff !important;
}

body.dark-mode .swal2-html-container table th {
    background: #0b1430 !important;
    color: #fff !important;
}

body.dark-mode .swal2-html-container table td {
    background: #0b1220 !important;
    color: #fff !important;
}

body.dark-mode .swal2-html-container .border {
    border-color: rgba(255,255,255,0.2) !important;
    background: #1a2337 !important;
}
/* ===== Medical History Card ===== */
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

/* ===== DARK MODE ===== */
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
.swal-card-body {
    max-height: none;
    overflow:auto;
    padding: 0;
    -webkit-overflow-scrolling: touch
}
@media (max-width:576px){
    .table th,
    .table td{
        font-size:12px;
        padding:8px;
        white-space:nowrap;
    }
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
.sidebar {
    width: 250px;
    background: #001BB7;
    color: #fff;
    position: fixed;
    height: 100%;
    padding: 25px 15px;
    display: flex;
    flex-direction: column;
    overflow-y: auto; /* Make it scrollable */
    scrollbar-width: thin; /* For Firefox */
    scrollbar-color: rgba(255,255,255,0.3) transparent; /* For Firefox */
}

/* Optional: customize scrollbar for Webkit browsers */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background-color: rgba(255,255,255,0.3);
    border-radius: 3px;
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
        <img src="logo.png" alt="KNL Logo"> KNL Health Center
    </h2>
    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="patients.php" class="active"><i class="fas fa-users"></i> Patients</a>
    <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a>
    <a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a>
    <a href="services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="announcement.php"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
    <a href="logout.php" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<div id="overlay" aria-hidden="true"></div>

<div class="main">
    <?php include 'admin_header.php'; ?>
    <h2><?= htmlspecialchars($patient['full_name']); ?> - Appointments</h2>
    <a href="patients.php" class="btn mb-3" style="background-color:#001BB7; color:#fff; border:none;">
        <i class="fas fa-arrow-left"></i>
    </a>

    <!-- Search Form -->
    <form method="GET" class="mb-3 d-flex" style="gap:5px; align-items:center;">
        <input type="hidden" name="id" value="<?= htmlspecialchars($patient_id); ?>">
        <input type="text" name="search" class="form-control me-2" placeholder="Search by date or service" 
               value="<?= htmlspecialchars($search); ?>" style="height:45px; font-size:13px;">
        <button type="submit" class="btn btn-primary" 
            style="background-color: #001BB7; height:45px; font-size:13px; display:flex; align-items:center; justify-content:center; gap:5px;">
            <i class="fas fa-search"></i> Search
        </button>
    </form>

    <?php if(mysqli_num_rows($appointments_result) > 0): ?>
    <table class="table table-bordered">
        <thead>
        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Service</th>
            <th>Type</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php while($row = mysqli_fetch_assoc($appointments_result)): ?>
        <tr>
            <td><?= htmlspecialchars($row['appointment_id']); ?></td>
            <td><?= htmlspecialchars($row['appointment_date']); ?></td>
            <td><?= htmlspecialchars($row['service_name']); ?></td>
            <td><?= htmlspecialchars($row['appointment_type']); ?></td>
            <td>
                <button class="icon-btn view" onclick="viewMedicalHistory('<?= $row['appointment_id']; ?>')">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php if($total_pages > 1): ?>
<div class="pagination-wrapper" style="margin-top:20px; display:flex; justify-content:flex-end;">
    <ul class="pagination" style="list-style:none; display:flex; padding:0; margin:0;">
        <!-- Previous -->
        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
            <a class="page-link" href="?id=<?= $patient_id; ?>&search=<?= urlencode($search); ?>&page=<?= max(1,$page-1); ?>" 
               style="padding:8px 16px; border:1px solid #ccc; color:#001BB7; text-decoration:none; margin-left:-1px; border-radius:0;">
               Prev
            </a>
        </li>

        <!-- Current Page -->
        <li class="page-item active">
            <span class="page-link" style="padding:8px 16px; border:1px solid #001BB7; background:#001BB7; color:#fff; margin-left:-1px;">
                <?= $page; ?>
            </span>
        </li>

        <!-- Next -->
        <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
            <a class="page-link" href="?id=<?= $patient_id; ?>&search=<?= urlencode($search); ?>&page=<?= min($total_pages,$page+1); ?>" 
               style="padding:8px 16px; border:1px solid #ccc; color:#001BB7; text-decoration:none; margin-left:-1px; border-radius:0;">
               Next
            </a>
        </li>
    </ul>
</div>
<?php endif; ?>

    <?php else: ?>
        <p>No appointments found.</p>
    <?php endif; ?>
</div>

<script>
// Sidebar toggle
const hamburger = document.querySelector('.hamburger');
const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('overlay');
function openMenu() {sidebar.classList.add('active'); overlay.classList.add('active'); hamburger.setAttribute('aria-expanded','true'); document.body.style.overflow='hidden';}
function closeMenu() {sidebar.classList.remove('active'); overlay.classList.remove('active'); hamburger.setAttribute('aria-expanded','false'); document.body.style.overflow='';}
hamburger.addEventListener('click', () => { sidebar.classList.contains('active') ? closeMenu() : openMenu(); });
overlay.addEventListener('click', closeMenu);
document.querySelectorAll('.sidebar a').forEach(link => { link.addEventListener('click', () => { if(window.innerWidth<=768) closeMenu(); }); });

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
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
});

// AJAX to view medical history
function viewMedicalHistory(appointmentId) {
    $.ajax({
        url: '',
        method: 'GET',
        data: { ajax_history: 1, appointment_id: appointmentId },
        success: function(response) {
            Swal.fire({
    title: 'Medical History',
    html: `<div class="swal-card-body">${response}</div>`,
    width: '90%',
    heightAuto: false,
    confirmButtonText: 'Close',
    customClass: { confirmButton: 'btn-close-custom' },
    buttonsStyling: false
});

        }
    });
}
</script>
</body>
</html>
