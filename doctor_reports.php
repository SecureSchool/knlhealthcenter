<?php
session_start();
require_once "config.php";

// Redirect if not logged in as doctor
if(!isset($_SESSION['doctor_id'])){
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['doctor_id'];
$doctor_name = $_SESSION['doctor_name'];

// Handle AJAX report save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_report') {
    $report_name = $_POST['report_name'] ?? 'Unknown';
    $report_type = $_POST['report_type'] ?? 'CSV';
    $performedby = $_POST['performedby'] ?? $doctor_name;

    $stmt = $link->prepare("INSERT INTO tblreports (report_name, report_type, performedby, created_at) VALUES (?,?,?,NOW())");
    $stmt->bind_param("sss", $report_name, $report_type, $performedby);
    $stmt->execute();
    echo "Logged";
    exit;
}

// Quick stats
$total_patients = mysqli_fetch_assoc(mysqli_query($link, "
    SELECT COUNT(DISTINCT patient_id) AS count 
    FROM tblappointments 
    WHERE doctor_assigned = '$doctor_id'
"))['count'];

$total_appointments = mysqli_fetch_assoc(mysqli_query($link, "
    SELECT COUNT(*) AS count 
    FROM tblappointments 
    WHERE doctor_assigned = '$doctor_id'
"))['count'];

$completed_appointments = mysqli_fetch_assoc(mysqli_query($link, "
    SELECT COUNT(*) AS count 
    FROM tblappointments 
    WHERE doctor_assigned = '$doctor_id' AND status='Completed'
"))['count'];

// Avg daily appointments
$avg_daily_appointments = mysqli_fetch_assoc(mysqli_query($link, "
    SELECT AVG(daily_count) as avg_daily
    FROM (
        SELECT COUNT(*) AS daily_count
        FROM tblappointments
        WHERE doctor_assigned='$doctor_id'
        GROUP BY appointment_date
    ) AS sub
"))['avg_daily'];

// Services assigned
$services_list = [];
$res_services = mysqli_query($link, "SELECT service_id, service_name FROM tblservices WHERE doctor_id='$doctor_id' ORDER BY service_name ASC");
while($row = mysqli_fetch_assoc($res_services)){
    $services_list[] = $row;
}

// Filters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$selected_service = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;

// Generate dates array
$period = new DatePeriod(
    new DateTime($start_date),
    new DateInterval('P1D'),
    (new DateTime($end_date))->modify('+1 day')
);
$days = [];
foreach($period as $date){
    $days[] = $date->format('Y-m-d');
}

// Appointments per day
$appointments_daily = array_fill(0, count($days), 0);
$filter_sql = "WHERE doctor_assigned='$doctor_id' AND appointment_date BETWEEN '$start_date' AND '$end_date'";
if($selected_service>0) $filter_sql .= " AND service_id=$selected_service";

$res = mysqli_query($link, "
    SELECT appointment_date, COUNT(*) AS total
    FROM tblappointments
    $filter_sql
    GROUP BY appointment_date
    ORDER BY appointment_date ASC
");
$appointments_map = [];
while($row = mysqli_fetch_assoc($res)){
    $appointments_map[$row['appointment_date']] = (int)$row['total'];
}
foreach($days as $i=>$day){
    $appointments_daily[$i] = $appointments_map[$day] ?? 0;
}

// Completed appointments per day
$completed_daily = array_fill(0, count($days), 0);
$res = mysqli_query($link, "
    SELECT appointment_date, COUNT(*) AS total
    FROM tblappointments
    WHERE status='Completed' AND doctor_assigned='$doctor_id' AND appointment_date BETWEEN '$start_date' AND '$end_date'
    ".($selected_service>0?" AND service_id=$selected_service":"")."
    GROUP BY appointment_date
    ORDER BY appointment_date ASC
");
$completed_map = [];
while($row = mysqli_fetch_assoc($res)){
    $completed_map[$row['appointment_date']] = (int)$row['total'];
}
foreach($days as $i=>$day){
    $completed_daily[$i] = $completed_map[$day] ?? 0;
}

// Service utilization
$services = [];
$service_counts = [];
$res = mysqli_query($link, "
    SELECT s.service_name, COUNT(*) AS total
    FROM tblappointments a
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    WHERE a.doctor_assigned='$doctor_id' AND appointment_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY s.service_name
");
while($row = mysqli_fetch_assoc($res)){
    $services[] = $row['service_name'];
    $service_counts[] = (int)$row['total'];
}

// Upcoming appointments next 7 days
$upcoming_appointments = [];
$res = mysqli_query($link, "
    SELECT a.*, p.full_name AS patient_name, s.service_name
    FROM tblappointments a
    LEFT JOIN tblpatients p ON a.patient_id=p.patient_id
    LEFT JOIN tblservices s ON a.service_id=s.service_id
    WHERE a.doctor_assigned='$doctor_id' AND a.appointment_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY
    ORDER BY appointment_date ASC, appointment_time ASC
");
while($row = mysqli_fetch_assoc($res)){
    $upcoming_appointments[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Reports Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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

/* Base */
body {
    background:#f5f7fa;
    font-family:'Inter',sans-serif;
    margin:0;
    padding:0;
}

/* Sidebar */
.sidebar {
    width:250px;
    background:var(--sidebar-bg);
    color:var(--text-color);
    position:fixed;
    height:100%;
    padding:25px 15px;
    display:flex;
    flex-direction:column;
    z-index: 1100; /* important */
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

.sidebar a:hover {
    background:var(--sidebar-hover);
    padding-left:24px;
    color:#fff;
}

.sidebar a.active {
    background:rgba(255,255,255,0.25);
    font-weight:600;
}

/* Main */
.main {
    margin-left:250px;
    padding:30px;
    flex:1;
}

/* Stat Cards */
.stat-card {
    background:#fff;
    padding:20px;
    border-radius:12px;
    text-align:center;
    box-shadow:0 3px 6px rgba(0,0,0,0.05);
    margin-bottom:20px;
}

.stat-card h3 {
    font-size:28px;
    color:#001BB7;
}

.stat-card p {
    margin:0;
    color:#555;
}

/* Card header */
.card-header {
    background:#001BB7;
    color:#fff;
    border-radius:12px 12px 0 0;
}

/* Hamburger */
.hamburger {
    display:none;
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
    z-index: 1200;
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
    opacity:1;
    visibility: visible;
}

/* Responsive */
@media(max-width:992px){
    .main {
        padding:20px;
    }
}

@media(max-width:768px){
    .hamburger {display:block;}
    .sidebar {
        transform:translateX(-280px);
        transition: transform 0.3s ease;
    }
    .sidebar.active {
        transform:translateX(0);
    }
    .main {
        margin-left:0;
        padding:20px;
    }
}

/* Table */
.table {
    background:#fff;
    border-radius:12px;
    overflow:hidden;
}

.table th,
.table td {
    padding:12px;
    font-size:14px;
}

/* Buttons */
.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: #0033ff;
    border-color: #0033ff;
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

</style>

</head>
<body>
<div id="overlay"></div>
<button class="hamburger"><i class="fas fa-bars"></i></button>

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
    <a href="doctor_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="doctor_schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a>
    <a href="doctor_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="doctor_reports.php" class="active"><i class="fas fa-chart-line"></i> Reports </a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main" id="reportContent">
    <?php include 'doctor_header.php'; ?>
    <h2 class="mb-4 fw-bold" style="color:#001BB7;">
    <i class="fas fa-file-alt me-2"></i> Reports Management
</h2>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3"><div class="stat-card"><h3><?= $total_patients; ?></h3><p>Total Patients</p></div></div>
        <div class="col-md-3"><div class="stat-card"><h3><?= $total_appointments; ?></h3><p>Total Appointments</p></div></div>
        <div class="col-md-3"><div class="stat-card"><h3><?= $completed_appointments; ?></h3><p>Completed</p></div></div>
        <div class="col-md-3"><div class="stat-card"><h3><?= round($avg_daily_appointments,1); ?></h3><p>Avg/Day</p></div></div>
    </div>

    <!-- Filters & Export Buttons -->
    <form method="GET" class="row g-2 mb-4 align-items-end">
        <div class="col-auto"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date); ?>" required></div>
        <div class="col-auto"><label>End Date</label><input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date); ?>" required></div>
        <div class="col-auto"><label>Service</label><select name="service_id" class="form-select">
            <option value="0">All Services</option>
            <?php foreach($services_list as $svc): ?>
                <option value="<?= $svc['service_id']; ?>" <?= $selected_service==$svc['service_id']?'selected':''; ?>><?= htmlspecialchars($svc['service_name']); ?></option>
            <?php endforeach; ?>
        </select></div>
        <div class="col-auto"><button type="submit" class="btn btn-primary">Apply Filter</button></div>
        <div class="col-auto"><button type="button" class="btn btn-secondary" id="printPdfBtn"><i class="fas fa-file-pdf"></i> Print PDF</button></div>
        <div class="col-auto"><button type="button" class="btn btn-success" id="exportCsvBtn"><i class="fas fa-file-csv"></i> Export CSV</button></div>
    </form>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">Appointments per Day</div>
                <div class="card-body"><canvas id="appointmentsChart"></canvas></div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">Completed Appointments per Day</div>
                <div class="card-body"><canvas id="completedChart"></canvas></div>
            </div>
        </div>
    <!-- Upcoming Appointments -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Upcoming Appointments (Next 7 Days)</div>
                <div class="card-body">
                    <table class="table table-bordered text-center" id="appointmentsTable">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Service</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($upcoming_appointments as $appt): ?>
                                <tr>
                                    <td><?= htmlspecialchars($appt['patient_name']); ?></td>
                                    <td><?= htmlspecialchars($appt['service_name']); ?></td>
                                    <td><?= $appt['appointment_date']; ?></td>
                                    <td><?= $appt['appointment_time']; ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($appt['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script>
// Sidebar toggle
const hamburger = document.querySelector('.hamburger');
const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('overlay');

function openMenu(){
    sidebar.classList.add('active');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeMenu(){
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

hamburger.addEventListener('click', () => {
    sidebar.classList.contains('active') ? closeMenu() : openMenu();
});

overlay.addEventListener('click', closeMenu);

document.querySelectorAll('.sidebar a').forEach(link => {
    link.addEventListener('click', () => {
        if(window.innerWidth <= 768) closeMenu();
    });
});

document.addEventListener('keydown', (e) => {
    if(e.key === 'Escape') closeMenu();
});

// Logout
document.getElementById('logoutBtn').addEventListener('click', function(e){
    e.preventDefault();
    Swal.fire({
        title:'Are you sure?', text:'You will be logged out.', icon:'warning',
        showCancelButton:true, confirmButtonColor:'#001BB7', cancelButtonColor:'#d33',
        confirmButtonText:'Yes, log me out'
    }).then((result)=>{ if(result.isConfirmed) window.location.href='logout.php'; });
});

// Charts
const labels = <?= json_encode($days); ?>;
const appointmentsData = <?= json_encode($appointments_daily); ?>;
const completedData = <?= json_encode($completed_daily); ?>;
const servicesLabels = <?= json_encode($services); ?>;
const servicesData = <?= json_encode($service_counts); ?>;

new Chart(document.getElementById('appointmentsChart'), {
    type:'line', data:{labels:labels,datasets:[{label:'Appointments',data:appointmentsData,borderColor:'#001BB7',backgroundColor:'rgba(0,27,183,0.2)',tension:0.3,fill:true,pointRadius:5,pointBackgroundColor:'#001BB7'}]},
    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
});
new Chart(document.getElementById('completedChart'), {
    type:'line', data:{labels:labels,datasets:[{label:'Completed',data:completedData,borderColor:'#28a745',backgroundColor:'rgba(40,167,69,0.2)',tension:0.3,fill:true,pointRadius:5,pointBackgroundColor:'#28a745'}]},
    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
});
new Chart(document.getElementById('serviceChart'), {
    type:'bar', data:{labels:servicesLabels,datasets:[{label:'Appointments',data:servicesData,backgroundColor:'#001BB7'}]},
    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
});

// PDF
document.getElementById('printPdfBtn').addEventListener('click', function(){
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p','pt','a4');
    html2canvas(document.getElementById('reportContent'),{scale:2}).then(canvas=>{
        const imgData = canvas.toDataURL('image/png');
        const imgProps = doc.getImageProperties(imgData);
        const pdfWidth = doc.internal.pageSize.getWidth();
        const pdfHeight = (imgProps.height*pdfWidth)/imgProps.width;
        doc.addImage(imgData,'PNG',0,0,pdfWidth,pdfHeight);
        doc.save('doctor_report.pdf');
        Swal.fire({icon:'success',title:'PDF Generated',text:'Report PDF generated successfully!',timer:2000,showConfirmButton:false});
    });
});

// CSV export with save
document.getElementById('exportCsvBtn').addEventListener('click', function(){
    let csvContent = "data:text/csv;charset=utf-8,";
    document.querySelectorAll("#appointmentsTable tr").forEach(row=>{
        csvContent += Array.from(row.querySelectorAll("th, td")).map(cell=>'"'+cell.textContent.replace(/"/g,'""')+'"').join(",") + "\r\n";
    });
    const link = document.createElement("a");
    const filename = "appointments_report_<?= date('Ymd_His'); ?>.csv";
    link.setAttribute("href", encodeURI(csvContent));
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    fetch(window.location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=save_report&report_name=${encodeURIComponent(filename)}&report_type=CSV&performedby=<?= urlencode($doctor_name); ?>`});
    Swal.fire({icon:'success',title:'CSV Exported',text:'Report CSV exported successfully!',timer:2000,showConfirmButton:false});
});
</script>

</body>
</html>
