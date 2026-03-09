<?php
session_start();
require_once "config.php";

// --- Redirect if not logged in as admin ---
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'];

// --- DATE RANGE FILTER ---
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date'] ?? null;

$dateFilter = "";
if ($start_date && $end_date) {
    $dateFilter = " AND appointment_date BETWEEN '$start_date' AND '$end_date' ";
}

// --- SUMMARY COUNTS ---
// Total appointments today (special case, hindi kasama sa filter)
$today = date('Y-m-d');
$res = mysqli_query($link, "SELECT COUNT(*) as total FROM tblappointments WHERE appointment_date = '$today'");
$totalAppointmentsToday = mysqli_fetch_assoc($res)['total'] ?? 0;

// Completed appointments
$res = mysqli_query($link, "SELECT COUNT(*) as total FROM tblappointments WHERE status = 'Completed' $dateFilter");
$completedAppointments = mysqli_fetch_assoc($res)['total'] ?? 0;

// Cancelled appointments
$res = mysqli_query($link, "SELECT COUNT(*) as total FROM tblappointments WHERE status = 'Cancelled' $dateFilter");
$cancelledAppointments = mysqli_fetch_assoc($res)['total'] ?? 0;

// No-shows
$res = mysqli_query($link, "SELECT COUNT(*) as total FROM tblappointments WHERE status = 'No Show' $dateFilter");
$noShowAppointments = mysqli_fetch_assoc($res)['total'] ?? 0;

// Registered patients (filtered by appointments if date range is set)
if ($start_date && $end_date) {
    $res = mysqli_query($link, "
        SELECT COUNT(DISTINCT p.patient_id) as total
        FROM tblpatients p
        JOIN tblappointments a ON p.patient_id = a.patient_id
        WHERE 1=1 $dateFilter
    ");
} else {
    $res = mysqli_query($link, "SELECT COUNT(*) as total FROM tblpatients");
}
$totalPatients = mysqli_fetch_assoc($res)['total'] ?? 0;

// --- APPOINTMENTS LAST 7 DAYS ---
$appointmentsData = [];
$res = mysqli_query($link, "
    SELECT appointment_date, COUNT(*) as total
    FROM tblappointments
    WHERE 1=1 $dateFilter
    GROUP BY appointment_date
    ORDER BY appointment_date
");
while($row = mysqli_fetch_assoc($res)){
    $appointmentsData[] = $row;
}

// --- STATUS BREAKDOWN ---
$statusData = [];
$res = mysqli_query($link, "
    SELECT status, COUNT(*) as total
    FROM tblappointments
    WHERE 1=1 $dateFilter
    GROUP BY status
");
while($row = mysqli_fetch_assoc($res)){
    $statusData[] = $row;
}

// --- PATIENTS BY GENDER ---
$genderData = [];
if ($start_date && $end_date) {
    $res = mysqli_query($link, "
        SELECT p.gender, COUNT(DISTINCT p.patient_id) as total
        FROM tblpatients p
        JOIN tblappointments a ON p.patient_id = a.patient_id
        WHERE 1=1 $dateFilter
        GROUP BY p.gender
    ");
} else {
    $res = mysqli_query($link, "
        SELECT gender, COUNT(*) as total
        FROM tblpatients
        GROUP BY gender
    ");
}
while($row = mysqli_fetch_assoc($res)){
    $genderData[] = $row;
}

// --- PATIENTS BY AGE GROUP ---
$ageGroups = [
    '0-18' => "TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 0 AND 18",
    '19-35' => "TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 19 AND 35",
    '36-60' => "TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 36 AND 60",
    '61+' => "TIMESTAMPDIFF(YEAR, birthday, CURDATE()) >= 61"
];
$ageData = [];
foreach($ageGroups as $label => $condition){
    if ($start_date && $end_date) {
        $res = mysqli_query($link, "
            SELECT COUNT(DISTINCT p.patient_id) as total
            FROM tblpatients p
            JOIN tblappointments a ON p.patient_id = a.patient_id
            WHERE $condition $dateFilter
        ");
    } else {
        $res = mysqli_query($link, "
            SELECT COUNT(*) as total
            FROM tblpatients
            WHERE $condition
        ");
    }
    $count = mysqli_fetch_assoc($res)['total'] ?? 0;
    $ageData[] = ['label' => $label, 'total' => $count];
}

// --- APPOINTMENTS PER DOCTOR ---
$doctorData = [];
$res = mysqli_query($link, "
    SELECT doctor_assigned, COUNT(*) as total
    FROM tblappointments
    WHERE 1=1 $dateFilter
    GROUP BY doctor_assigned
");
while($row = mysqli_fetch_assoc($res)){
    $doctorData[] = $row;
}

// --- MOST AVAILED SERVICES ---
$serviceData = [];
$res = mysqli_query($link, "
    SELECT s.service_name, COUNT(a.appointment_id) as total
    FROM tblappointments a
    JOIN tblservices s ON a.service_id = s.service_id
    WHERE 1=1 $dateFilter
    GROUP BY s.service_name
");
while($row = mysqli_fetch_assoc($res)){
    $serviceData[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Reports</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; min-height:100vh; color:#1e293b; transition:0.3s; display:flex;}

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
    transition:0.3s;
    z-index:1100; /* HIGHER than overlay */
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

/* Hamburger */
.hamburger {
  display:none;
  position:fixed;
  top:15px;
  left:15px;
  font-size:22px;
  color:#001BB7;
  background:#fff;
  border:none;
  border-radius:8px;
  padding:8px 10px;
  cursor:pointer;
  z-index:1200;
}

/* Overlay */
#overlay {
  position:fixed;
  inset:0;
  background:rgba(0,0,0,0.45);
  opacity:0;
  visibility:hidden;
  transition:opacity 0.25s ease, visibility 0.25s;
  z-index:1000; /* BELOW sidebar */
}
#overlay.active {
  opacity:1;
  visibility:visible;
}

/* Main */
.main {
    margin-left:250px;
    padding:30px;
    flex:1;
    transition:0.3s;
}
.card {border:none; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05);}
.summary-card {color:#fff;}
.summary-card .card-body {display:flex; align-items:center; justify-content:space-between;}
.summary-card i {font-size:28px;}
.chart-card {padding:15px; height:320px; display:flex; flex-direction:column; justify-content:center;}
.chart-card canvas {max-height:250px !important;}

/* Responsive */
@media(max-width:768px){
  .hamburger {display:block;}
  .sidebar {transform:translateX(-100%); width:250px; left:0; top:0; height:100%; z-index:1100;}
  .sidebar.active {transform:translateX(0);}
  .main {margin-left:0; padding:15px;}
}
/* ============================
   DARK MODE (MATCH DASHBOARD)
   ============================ */

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

/* Header (profile dropdown + button) */
body.dark-mode .header-container {
    background: linear-gradient(90deg, #0f1a38, #00122b) !important;
    color: #fff !important;
}

/* Profile dropdown */
body.dark-mode .profile-dropdown {
    background: #111b2b !important;
    color: #fff !important;
}
body.dark-mode .profile-dropdown a {
    color: #fff !important;
}
body.dark-mode .profile-dropdown a:hover {
    background: rgba(255,255,255,0.15) !important;
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

/* Summary Cards (same colors) */
body.dark-mode .summary-card.bg-primary { background: #0b1430 !important; }
body.dark-mode .summary-card.bg-success { background: #1f7a3d !important; }
body.dark-mode .summary-card.bg-danger  { background: #a31b1b !important; }
body.dark-mode .summary-card.bg-warning { background: #d97706 !important; }
body.dark-mode .summary-card.bg-info    { background: #0b6fb5 !important; }

/* Quick links (if any) */
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
body.dark-mode .btn-light,
body.dark-mode .btn-secondary,
body.dark-mode .btn-danger {
    color: #fff !important;
    border-color: rgba(255,255,255,0.25) !important;
}

/* Button colors */
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
body.dark-mode .btn-secondary {
    background: #1a2337 !important;
    border-color: #1a2337 !important;
}
body.dark-mode .btn-danger {
    background: #a31b1b !important;
    border-color: #a31b1b !important;
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
body.dark-mode small {
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

/* Inputs */
body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea {
    background: #1a2337 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.3) !important;
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

/* Toast / alerts (SweetAlert) */
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
/* ===== MOBILE SIDEBAR SCROLL FIX ===== */
@media(max-width:768px){

  .sidebar{
      height:100vh;
      overflow-y:auto;
      overflow-x:hidden;
      -webkit-overflow-scrolling: touch;
  }

}
.sidebar::-webkit-scrollbar{
  width:5px;
}
.sidebar::-webkit-scrollbar-thumb{
  background:rgba(255,255,255,0.4);
  border-radius:10px;
}
.sidebar::-webkit-scrollbar-track{
  background:transparent;
}
</style>
</head>
<body>

<!-- Hamburger -->
<button class="hamburger" aria-label="Open menu"><i class="fas fa-bars"></i></button>
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
    <a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a>
    <a href="services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="announcement.php"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="reports.php" class="active"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<div id="overlay"></div>

<div class="main">
    <?php include 'admin_header.php'; ?>
    <h2 class="mb-4"><i class="fas fa-chart-line me-2"></i> Reports & Analytics</h2>

    <!-- Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="card summary-card bg-primary"><div class="card-body"><i class="fas fa-calendar-day"></i><div><h5><?php echo $totalAppointmentsToday; ?></h5><small>Today</small></div></div></div></div>
        <div class="col-md-2"><div class="card summary-card bg-success"><div class="card-body"><i class="fas fa-check-circle"></i><div><h5><?php echo $completedAppointments; ?></h5><small>Completed</small></div></div></div></div>
        <div class="col-md-2"><div class="card summary-card bg-danger"><div class="card-body"><i class="fas fa-times-circle"></i><div><h5><?php echo $cancelledAppointments; ?></h5><small>Cancelled</small></div></div></div></div>
        <div class="col-md-2"><div class="card summary-card bg-warning"><div class="card-body"><i class="fas fa-ban"></i><div><h5><?php echo $noShowAppointments; ?></h5><small>No Shows</small></div></div></div></div>
        <div class="col-md-2"><div class="card summary-card bg-info"><div class="card-body"><i class="fas fa-users"></i><div><h5><?php echo $totalPatients; ?></h5><small>Patients</small></div></div></div></div>
    </div>

    <!-- Filter -->
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" value="<?php echo $_GET['start_date'] ?? ''; ?>" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" value="<?php echo $_GET['end_date'] ?? ''; ?>" class="form-control">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2"><i class="fas fa-filter"></i> Apply</button>
            <a href="reports.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
        </div>
    </form>

    <div class="mb-4">
        <a href="admin_export_reports.php?type=pdf&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
           class="btn btn-danger me-2"><i class="fas fa-file-pdf"></i> Export PDF</a>
        <a href="admin_export_reports.php?type=excel&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
           class="btn btn-success"><i class="fas fa-file-excel"></i> Export Excel</a>
    </div>

    <!-- Charts -->
    <div class="row g-3">
        <div class="col-md-6 col-lg-4"><div class="card chart-card"><h6>Appointments (Filtered)</h6><canvas id="appointmentsChart"></canvas></div></div>
        <div class="col-md-6 col-lg-4"><div class="card chart-card"><h6>Status Breakdown</h6><canvas id="statusChart"></canvas></div></div>
        <div class="col-md-6 col-lg-4"><div class="card chart-card"><h6>Patients by Gender</h6><canvas id="genderChart"></canvas></div></div>
        <div class="col-md-6 col-lg-4"><div class="card chart-card"><h6>Patients by Age</h6><canvas id="ageChart"></canvas></div></div>
        <div class="col-md-6 col-lg-4"><div class="card chart-card"><h6>Appointments per Doctor</h6><canvas id="doctorChart"></canvas></div></div>
        <div class="col-md-6 col-lg-4"><div class="card chart-card"><h6>Top Services</h6><canvas id="serviceChart"></canvas></div></div>
    </div>
</div>

<script>
// Toggle sidebar
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
// Charts
new Chart(document.getElementById('appointmentsChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($appointmentsData,'appointment_date')); ?>,
        datasets: [{label: 'Appointments', data: <?php echo json_encode(array_column($appointmentsData,'total')); ?>, borderColor: '#001BB7', fill: false}]
    }
});
new Chart(document.getElementById('statusChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_column($statusData,'status')); ?>,
        datasets: [{data: <?php echo json_encode(array_column($statusData,'total')); ?>, backgroundColor: ['#28a745','#dc3545','#ffc107','#17a2b8']}]
    }
});
new Chart(document.getElementById('genderChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_column($genderData,'gender')); ?>,
        datasets: [{data: <?php echo json_encode(array_column($genderData,'total')); ?>, backgroundColor: ['#e83e8c','#007bff']}]
    }
});
new Chart(document.getElementById('ageChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($ageData,'label')); ?>,
        datasets: [{label: 'Patients', data: <?php echo json_encode(array_column($ageData,'total')); ?>, backgroundColor: '#001BB7'}]
    }
});
new Chart(document.getElementById('doctorChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($doctorData,'doctor_assigned')); ?>,
        datasets: [{label: 'Appointments', data: <?php echo json_encode(array_column($doctorData,'total')); ?>, backgroundColor: '#28a745'}]
    }
});
new Chart(document.getElementById('serviceChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($serviceData,'service_name')); ?>,
        datasets: [{label: 'Services', data: <?php echo json_encode(array_column($serviceData,'total')); ?>, backgroundColor: '#17a2b8'}]
    }
});
</script>
</body>
</html>
