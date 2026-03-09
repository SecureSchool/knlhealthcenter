<?php
session_start();
require_once "config.php";
function saveReport($link, $report_name, $report_type, $generated_by, $generated_for, $filters_applied, $report_data) {
    $date_generated = date('Y-m-d');
    $time_generated = date('H:i:s');
    $stmt = mysqli_prepare($link, 
        "INSERT INTO tblreports 
        (report_name, report_type, generated_by, generated_for, date_generated, time_generated, filters_applied, report_data) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "ssssssss", 
        $report_name, 
        $report_type, 
        $generated_by, 
        $generated_for, 
        $date_generated, 
        $time_generated, 
        $filters_applied, 
        $report_data
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_report'])) {
    $report_name = $_POST['report_name'] ?? 'Report';
    $report_type = $_POST['report_type'] ?? 'Unknown';
    $performedto = $_POST['performedto'] ?? '';
    $report_data = $_POST['report_data'] ?? '';

    saveReport($link, $report_name, $report_type, $_SESSION['staff_name'], $performedto, "Filters: $performedto", $report_data);
    exit;
}

// Redirect if not logged in as staff
if(!isset($_SESSION['staff_id'])){
    header("Location: login.php");
    exit;
}

$staff_name = $_SESSION['staff_name'];

// Quick stats
$patients_count = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM tblpatients"))['total'];
$appointments_count = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM tblappointments"))['total'];
$pending_appointments_count = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM tblappointments WHERE status='Pending'"))['total'];
$no_show_count = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM tblappointments WHERE status='No-Show'"))['total'];

// Average daily appointments
$avg_daily_appointments = mysqli_fetch_assoc(mysqli_query($link, "
    SELECT AVG(daily_count) as avg_daily 
    FROM (
        SELECT COUNT(*) AS daily_count
        FROM tblappointments
        GROUP BY appointment_date
    ) as sub
"))['avg_daily'];

// Fetch services for dropdown
$services_list = [];
$services_result = mysqli_query($link, "SELECT service_id, service_name FROM tblservices ORDER BY service_name ASC");
while($row = mysqli_fetch_assoc($services_result)){
    $services_list[] = $row;
}

// Get earliest and latest appointment dates from database
$dates_result = mysqli_query($link, "SELECT MIN(appointment_date) AS min_date, MAX(appointment_date) AS max_date FROM tblappointments");
$dates_row = mysqli_fetch_assoc($dates_result);
$min_date = $dates_row['min_date'] ?? date('Y-m-d');
$max_date = $dates_row['max_date'] ?? date('Y-m-d');

// Handle filters (date range + service)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $min_date;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $max_date;
$selected_service = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;

// Generate array of all dates in range
$period = new DatePeriod(
    new DateTime($start_date),
    new DateInterval('P1D'),
    (new DateTime($end_date))->modify('+1 day')
);
$days = [];
foreach($period as $date){
    $days[] = $date->format('Y-m-d');
}

// Appointments per day (fill 0 for days with no appointments)
$appointments_daily = array_fill(0, count($days), 0);
$filter_sql = "WHERE appointment_date BETWEEN '$start_date' AND '$end_date'";
if($selected_service > 0) $filter_sql .= " AND service_id=$selected_service";

$result = mysqli_query($link, "
    SELECT appointment_date, COUNT(*) AS total
    FROM tblappointments
    $filter_sql
    GROUP BY appointment_date
    ORDER BY appointment_date ASC
");
$appointments_map = [];
while($row = mysqli_fetch_assoc($result)){
    $appointments_map[$row['appointment_date']] = (int)$row['total'];
}
foreach($days as $i => $day){
    $appointments_daily[$i] = $appointments_map[$day] ?? 0;
}

// Completed appointments per day (fill 0 for missing days)
$completed_daily = array_fill(0, count($days), 0);
$result = mysqli_query($link, "
    SELECT appointment_date, COUNT(*) AS total
    FROM tblappointments
    WHERE status='Completed' AND appointment_date BETWEEN '$start_date' AND '$end_date'
    ".($selected_service>0?" AND service_id=$selected_service":"")."
    GROUP BY appointment_date
    ORDER BY appointment_date ASC
");
$completed_map = [];
while($row = mysqli_fetch_assoc($result)){
    $completed_map[$row['appointment_date']] = (int)$row['total'];
}
foreach($days as $i => $day){
    $completed_daily[$i] = $completed_map[$day] ?? 0;
}

// Service utilization (filtered)
$services = [];
$service_counts = [];
$result = mysqli_query($link, "
    SELECT s.service_name, COUNT(*) AS total
    FROM tblappointments a
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    WHERE appointment_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY s.service_name
");
while($row = mysqli_fetch_assoc($result)){
    $services[] = $row['service_name'];
    $service_counts[] = (int)$row['total'];
}

// Upcoming appointments (next 7 days)
$upcoming_appointments = [];
$result = mysqli_query($link, "
    SELECT appointment_date, appointment_time, p.full_name AS patient_name, s.service_name
    FROM tblappointments a
    LEFT JOIN tblpatients p ON a.patient_id = p.patient_id
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    WHERE appointment_date >= CURDATE() AND appointment_date <= CURDATE() + INTERVAL 7 DAY
    ORDER BY appointment_date ASC, appointment_time ASC
");
while($row = mysqli_fetch_assoc($result)){
    $upcoming_appointments[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Reports</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
:root {
    --primary-color: #001BB7;
    --sidebar-bg: #001BB7;
    --sidebar-hover: rgba(255,255,255,0.15);
    --text-color: #fff;
}
* { margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif; }
body { background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b; }

/* Sidebar */
.sidebar { width:250px; background: var(--sidebar-bg); color: var(--text-color); position:fixed; height:100%; padding:25px 15px; display:flex; flex-direction:column; transition: transform 0.3s ease; z-index: 1000; }
.sidebar h2 { text-align:center; margin-bottom:20px; font-size:24px; font-weight:700; }
.sidebar a { color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:8px 0; text-decoration:none; border-radius:10px; transition:0.3s; font-weight:500; }
.sidebar a i { margin-right:12px; font-size:18px; }
.sidebar a:hover { background: var(--sidebar-hover); padding-left:24px; color:#fff; }
.sidebar a.active { background: rgba(255,255,255,0.25); font-weight:600; }

/* Hamburger */
.hamburger { display:none; position:fixed; top:15px; left:15px; font-size:20px; color: var(--primary-color); background:#fff; border:none; border-radius:8px; padding:8px 10px; cursor:pointer; z-index:1100; }

/* Overlay */
#overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s; z-index: 900; }
#overlay.active {opacity:1; visibility:visible;}

/* Main */
.main { margin-left:250px; padding:40px; flex:1; transition: margin-left 0.3s; }

/* Cards / Stats */
.stat-card { background:#fff; padding:20px; border-radius:12px; text-align:center; box-shadow:0 3px 6px rgba(0,0,0,0.05); }
.stat-card h3 { font-size:28px; margin-bottom:5px; color:#001BB7; }
.stat-card p { margin:0; font-weight:500; color:#555; }

/* Card */
.card { border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.05); margin-bottom:30px; }
.card-header { border-radius:12px 12px 0 0; color:#fff; background-color: var(--primary-color); padding:12px 20px; font-weight:600; }

/* Reduce chart heights */
.card-body canvas { max-height: 300px; }

/* Responsive */
@media(max-width:768px){
    .hamburger {display:block;}
    .sidebar {transform:translateX(-280px);}
    .sidebar.active {transform:translateX(0);}
    .main {margin-left:0; padding:20px;}
}
.stat-card {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 15px; /* space between icon and text */
    text-align: left;
}

.stat-card i {
    font-size: 32px;
    color: #001BB7;
    flex-shrink: 0;
}

.stat-card h3 {
    margin: 0;
    font-size: 24px;
    font-weight: bold;
}

.stat-card p {
    margin: 0;
    font-size: 14px;
    color: #666;
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
/* Upcoming Appointments Table Dark Mode */
body.dark-mode .card-body table {
    background: #0b1220 !important;
    color: #fff !important;
}

body.dark-mode .card-body table thead th {
    background: #0b1430 !important;
    color: #fff !important;
    border-bottom: 1px solid rgba(255,255,255,0.18) !important;
}

body.dark-mode .card-body table tbody td {
    background: #0b1220 !important;
    color: #fff !important;
    border-color: rgba(255,255,255,0.18) !important;
}

body.dark-mode .card-body table tbody tr:nth-child(odd) {
    background: #0b1220 !important;
}

body.dark-mode .card-body table tbody tr:nth-child(even) {
    background: #0a1020 !important;
}

body.dark-mode .card-body table tbody tr:hover {
    background: rgba(255,255,255,0.08) !important;
}

</style>
</head>
<body>

<button class="hamburger"><i class="fas fa-bars"></i></button>
<div id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar">
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
    <a href="staff_appointments.php">
        <i class="fas fa-calendar"></i> Appointments
        <?php if($pending_appointments_count > 0): ?>
            <span class="badge bg-danger ms-2"><?= $pending_appointments_count; ?></span>
        <?php endif; ?>
    </a>
    <a href="staff_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="staff_reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Main -->
<div class="main" id="reportContent">
    <?php include 'header_staff.php'; ?>
    <h2 class="mb-4">Reports</h2>
    <!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <div>
                <h3><?= $patients_count; ?></h3>
                <p>Total Patients</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <i class="fas fa-calendar-check"></i>
            <div>
                <h3><?= $appointments_count; ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <i class="fas fa-hourglass-half"></i>
            <div>
                <h3><?= $pending_appointments_count; ?></h3>
                <p>Pending</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <i class="fas fa-chart-line"></i>
            <div>
                <h3><?= round($avg_daily_appointments,1); ?></h3>
                <p>Avg/Day</p>
            </div>
        </div>
    </div>
</div>

    <!-- Filters -->
    <form method="GET" class="row g-2 mb-4 align-items-end">
        <div class="col-auto">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date); ?>" required>
        </div>
        <div class="col-auto">
            <label>End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date); ?>" required>
        </div>
        <div class="col-auto">
            <label>Service</label>
            <select name="service_id" class="form-select">
                <option value="0">All Services</option>
                <?php foreach($services_list as $svc): ?>
                    <option value="<?= $svc['service_id']; ?>" <?= $selected_service==$svc['service_id']?'selected':''; ?>>
                        <?= htmlspecialchars($svc['service_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Apply Filter</button>
        </div>
        <div class="col-auto">
            <a href="export_reports.php?start_date=<?= $start_date; ?>&end_date=<?= $end_date; ?>&service_id=<?= $selected_service; ?>" 
   class="btn btn-success" 
   id="exportCsvBtn">
   <i class="fas fa-download"></i> Export CSV
</a>

        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-secondary" id="printPdfBtn">
                <i class="fas fa-file-pdf"></i> Print PDF
            </button>
        </div>
    </form>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Appointments in Selected Range</div>
                <div class="card-body">
                    <canvas id="appointmentsChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Completed Appointments in Selected Range</div>
                <div class="card-body">
                    <canvas id="completedChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Service Utilization</div>
                <div class="card-body">
                    <canvas id="serviceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Appointments -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Upcoming Appointments (Next 7 Days)</div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Service</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($upcoming_appointments as $appt): ?>
                            <tr>
                                <td><?= $appt['appointment_date']; ?></td>
                                <td><?= $appt['appointment_time']; ?></td>
                                <td><?= htmlspecialchars($appt['patient_name']); ?></td>
                                <td><?= htmlspecialchars($appt['service_name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Logout
document.getElementById('logoutBtn').addEventListener('click', function(e){
    e.preventDefault();
    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#001BB7',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, log me out'
    }).then((result) => {
        if(result.isConfirmed) window.location.href='logout.php';
    });
});
// Helper to get performedto string
function getPerformedTo() {
    const service = document.querySelector('select[name="service_id"]').selectedOptions[0].text;
    const startDate = document.querySelector('input[name="start_date"]').value;
    const endDate = document.querySelector('input[name="end_date"]').value;
    return `${service} (${startDate} to ${endDate})`;
}

// CSV Export with logging
document.getElementById('exportCsvBtn').addEventListener('click', function(e){
    e.preventDefault();
    const url = this.href;
    const performedto = getPerformedTo();
    const reportName = 'Appointments Report CSV';
    const reportType = 'CSV';
    const reportData = 'CSV Export: ' + performedto; // You can make this more detailed if needed

    // Log CSV export via POST to same page
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `log_action=Exported CSV Report&performedto=${encodeURIComponent(performedto)}&save_report=1&report_name=${encodeURIComponent(reportName)}&report_type=${reportType}&report_data=${encodeURIComponent(reportData)}`
    }).then(() => {
        window.location.href = url;
        Swal.fire({
            icon: 'success',
            title: 'CSV Exported',
            text: 'Your report has been exported successfully!',
            timer: 2000,
            showConfirmButton: false
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
// Charts
const ctxAppointments = document.getElementById('appointmentsChart').getContext('2d');
new Chart(ctxAppointments, {
    type:'line',
    data:{
        labels: <?= json_encode($days); ?>,
        datasets:[{
            label:'Appointments',
            data: <?= json_encode($appointments_daily); ?>,
            backgroundColor:'rgba(0,27,183,0.2)',
            borderColor:'#001BB7',
            borderWidth:2,
            fill:true,
            tension:0.2
        }]
    },
    options:{ responsive:true, plugins:{ tooltip:{mode:'index', intersect:false} }, scales:{ y:{ beginAtZero:true } } }
});

const ctxCompleted = document.getElementById('completedChart').getContext('2d');
new Chart(ctxCompleted, {
    type:'line',
    data:{
        labels: <?= json_encode($days); ?>,
        datasets:[{
            label:'Completed Appointments',
            data: <?= json_encode($completed_daily); ?>,
            backgroundColor:'rgba(40,167,69,0.2)',
            borderColor:'#28a745',
            borderWidth:2,
            fill:true,
            tension:0.2
        }]
    },
    options:{ responsive:true, plugins:{ tooltip:{mode:'index', intersect:false} }, scales:{ y:{ beginAtZero:true } } }
});

const ctxService = document.getElementById('serviceChart').getContext('2d');
new Chart(ctxService, {
    type:'doughnut',
    data:{
        labels: <?= json_encode($services); ?>,
        datasets:[{
            label:'Appointments',
            data: <?= json_encode($service_counts); ?>,
            backgroundColor:['#001BB7','#28a745','#ffc107','#dc3545','#6610f2','#17a2b8','#6c757d']
        }]
    },
    options:{ responsive:true, plugins:{ legend:{ position:'bottom' } } }
});

// Print PDF
// Print PDF
document.getElementById('printPdfBtn').addEventListener('click', function(){
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'pt', 'a4');
    const element = document.getElementById('reportContent');
    const performedto = getPerformedTo();
    const reportName = 'Appointments Report PDF';
    const reportType = 'PDF';
    const reportData = element.innerHTML; // save HTML content as report data

    html2canvas(element, { scale: 2 }).then((canvas) => {
        const imgData = canvas.toDataURL('image/png');
        const imgProps = doc.getImageProperties(imgData);
        const pdfWidth = doc.internal.pageSize.getWidth();
        const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

        let heightLeft = pdfHeight;
        let position = 0;
        doc.addImage(imgData, 'PNG', 0, position, pdfWidth, pdfHeight);
        heightLeft -= doc.internal.pageSize.getHeight();
        while (heightLeft > 0) {
            position = heightLeft - pdfHeight;
            doc.addPage();
            doc.addImage(imgData, 'PNG', 0, position, pdfWidth, pdfHeight);
            heightLeft -= doc.internal.pageSize.getHeight();
        }

        doc.save('staff_report.pdf');

        Swal.fire({
            icon: 'success',
            title: 'PDF Generated',
            text: 'Your report PDF has been generated successfully!',
            timer: 2000,
            showConfirmButton: false
        });

        // Log and save report to database
        fetch(window.location.href, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: `log_action=Generated PDF Report&performedto=${encodeURIComponent(performedto)}&save_report=1&report_name=${encodeURIComponent(reportName)}&report_type=${reportType}&report_data=${encodeURIComponent(reportData)}`
        });
    });
});

</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
