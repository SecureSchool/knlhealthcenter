<?php
session_start();
require_once "config.php";

// Redirect if not logged in as staff
if(!isset($_SESSION['staff_id'])){
    header("Location: login.php");
    exit;
}

$staff_id = $_SESSION['staff_id'];
$staff_name = $_SESSION['staff_name'];

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

// Pagination settings
$limit = 5; // appointments per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total appointments
$count_sql = "
    SELECT COUNT(*) AS total
    FROM tblappointments a
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    WHERE a.patient_id = ? AND (a.appointment_date LIKE ? OR s.service_name LIKE ?)
";
$stmt_count = mysqli_prepare($link, $count_sql);
mysqli_stmt_bind_param($stmt_count, "iss", $patient_id, $safe_search, $safe_search);
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$total_rows = mysqli_fetch_assoc($result_count)['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch appointments with limit & offset
$appointments_sql = "
    SELECT a.*, s.service_name
    FROM tblappointments a
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    WHERE a.patient_id = ? AND (a.appointment_date LIKE ? OR s.service_name LIKE ?)
    ORDER BY a.appointment_date DESC
    LIMIT ? OFFSET ?
";
$stmt2 = mysqli_prepare($link, $appointments_sql);
mysqli_stmt_bind_param($stmt2, "issii", $patient_id, $safe_search, $safe_search, $limit, $offset);
mysqli_stmt_execute($stmt2);
$appointments_result = mysqli_stmt_get_result($stmt2);

// AJAX: medical history
if(isset($_GET['ajax_history']) && isset($_GET['appointment_id'])){
    $appt_id = intval($_GET['appointment_id']);

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
        echo "<div class='history-card'>";
echo "<div class='card-header'>Medical History</div>";
echo "<div class='card-body'>";
echo "<p><b>Date:</b> ".htmlspecialchars($h['created_at'])."</p>";
echo "<p><b>Diagnosis:</b> ".htmlspecialchars($h['diagnosis'])."</p>";
echo "<p><b>Treatment:</b> ".htmlspecialchars($h['treatment'])."</p>";
echo "<p><b>Notes:</b> ".htmlspecialchars($h['notes'])."</p>";
echo "<p><b>Doctor:</b> ".htmlspecialchars($h['doctor_name'])."</p>";
echo "</div></div>";


        // Prescriptions
        $presc_sql = "SELECT * FROM tblprescriptions WHERE appointment_id = ?";
        $stmt2 = mysqli_prepare($link, $presc_sql);
        mysqli_stmt_bind_param($stmt2, "i", $appt_id);
        mysqli_stmt_execute($stmt2);
        $presc_result = mysqli_stmt_get_result($stmt2);

        if(mysqli_num_rows($presc_result) > 0){
            echo "<div class='prescription-card'>";
echo "<div class='card-header prescription-header'>Prescriptions</div>";
echo "<div class='card-body'>";
echo "<div class='table-responsive-wrapper'>";
echo "<table class='table table-bordered prescription-table'>";

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
            echo "</tbody></table>";
echo "</div>"; // ← ito yung wrapper closing
echo "</div></div>";
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
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --primary-color: #001BB7;
    --sidebar-bg: #001BB7;
    --sidebar-hover: rgba(255,255,255,0.15);
    --text-color: #fff;
}
body { background:#f5f7fa; display:flex; min-height:100vh; font-family:'Inter', sans-serif; color:#1e293b; margin:0; }

/* Sidebar */
.sidebar {
    width:250px; background:var(--sidebar-bg); color:var(--text-color); position:fixed; height:100%; padding:25px 15px; display:flex; flex-direction:column; transition: transform 0.3s;
    z-index:1000;
}
.sidebar h2 { text-align:center; margin-bottom:35px; font-size:24px; font-weight:700; }
.sidebar h2 img { width:45px; height:45px; margin-right:10px; vertical-align:middle; border-radius:50%; object-fit:cover; }
.sidebar a { color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:8px 0; text-decoration:none; border-radius:10px; transition:0.3s; font-weight:500; }
.sidebar a i { margin-right:12px; font-size:18px; }
.sidebar a:hover { background: var(--sidebar-hover); padding-left:24px; color:#fff; }
.sidebar a.active { background: rgba(255,255,255,0.25); font-weight:600; }

/* Main */
.main { margin-left:250px; padding:30px; flex:1; transition: margin-left 0.3s; }

/* Hamburger */
.hamburger { display:none; position:fixed; top:15px; left:15px; font-size:20px; color: var(--primary-color); background:#fff; border:none; border-radius:8px; padding:8px 10px; cursor:pointer; z-index:1100; }
#overlay { position: fixed; inset:0; background: rgba(0,0,0,0.45); opacity:0; visibility:hidden; transition:0.25s; z-index:900; }
#overlay.active { opacity:1; visibility:visible; }

/* Table buttons */
.icon-btn {width:38px; height:38px; border:none; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; cursor:pointer; background:#001BB7; color:#fff; }
.icon-btn:hover { background:#000F8C; }

/* Responsive */
@media(max-width:768px){
    .hamburger { display:block; }
    .sidebar { transform:translateX(-280px); }
    .sidebar.active { transform:translateX(0); }
    .main { margin-left:0; padding:20px; }
}
.swal2-custom-close-btn {
    background-color: #001BB7 !important;
    color: #fff !important;
    font-weight: 600;
    padding: 8px 20px !important;
    border-radius: 8px !important;
    border: none !important;
    font-size: 16px !important;
    cursor: pointer;
    transition: 0.3s;
}
.swal2-custom-close-btn:hover {
    background-color: #000F8C !important;
}
table.table thead th {
    background: #001BB7;
    color: #fff;
    text-align: center;
    vertical-align: middle;
    font-weight: 600;
    padding: 12px;
}
.table-hover tbody tr:hover, 
.table tbody tr:hover td {
    background-color: #e6ecff !important;
}
/* Search button style */
.btn-search {
    background-color: #001BB7;  /* same as table header */
    color: #fff;
    border: none;
    height: 45px;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px; /* space between text and icon */
    padding: 0 15px;
    border-radius: 8px;
    transition: 0.3s;
}

.btn-search:hover {
    background-color: #000F8C;
    color: #fff;
}
.pagination-wrapper {margin-top:20px; display:flex; justify-content:flex-end;}
.pagination {list-style:none; display:flex; padding:0; margin:0;}
.pagination .page-item {display:inline;}
.pagination .page-link {padding:8px 16px; border:1px solid #ccc; color:#001BB7; text-decoration:none; transition:0.2s; margin-left:-1px; border-radius:0;}
.pagination .page-item:first-child .page-link {border-radius:6px 0 0 6px; margin-left:0;}
.pagination .page-item:last-child .page-link {border-radius:0 6px 6px 0;}
.pagination .page-item.active .page-link {background-color:#001BB7; color:white; border-color:#001BB7;}
.pagination .page-item.disabled .page-link {color:#999; pointer-events:none; background:#f1f5f9;}
.pagination .page-link:hover:not(.disabled):not(.active) {background:#0010a0; color:#fff;}
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
/* Appointments Table Dark Mode */
body.dark-mode table.table {
    background: #0b1220 !important;
    color: #fff !important;
}

body.dark-mode table.table thead th {
    background: #00122b !important;
    color: #fff !important;
    border-color: rgba(255,255,255,0.18) !important;
}

body.dark-mode table.table tbody td {
    background: #0b1220 !important;
    color: #fff !important;
    border-color: rgba(255,255,255,0.18) !important;
}

body.dark-mode table.table tbody tr:nth-child(even) td {
    background: #0a1020 !important;
}

body.dark-mode table.table tbody tr:hover td {
    background: rgba(255,255,255,0.08) !important;
}
/* Medical History Card */
.history-card,
.prescription-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 3px 6px rgba(0,0,0,0.1);
    margin-bottom: 15px;
}

.card-header {
    background: #001BB7;
    color: #fff;
    padding: 10px 15px;
    border-radius: 12px 12px 0 0;
    font-weight: 600;
}

.prescription-header {
    background: #28a745;
}

.card-body {
    padding: 15px;
}

/* Prescription Table */
.prescription-table th,
.prescription-table td {
    border: 1px solid #000;
}
/* Medical History Card - Dark Mode */
body.dark-mode .history-card,
body.dark-mode .prescription-card {
    background: #111b2b !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.6) !important;
}

body.dark-mode .history-card .card-header,
body.dark-mode .prescription-card .card-header {
    background: #001BB7 !important;
}

body.dark-mode .prescription-card .prescription-header {
    background: #28a745 !important;
}

body.dark-mode .card-body {
    background: #111b2b !important;
    color: #fff !important;
}

/* Prescription Table Dark Mode */
body.dark-mode .prescription-table {
    background: #111b2b !important;
    color: #fff !important;
}

body.dark-mode .prescription-table th,
body.dark-mode .prescription-table td {
    border-color: rgba(255,255,255,0.18) !important;
    color: #fff !important;
}
/* Responsive Prescription Table */
.table-responsive-wrapper{
    width:100%;
    overflow-x:auto;
    -webkit-overflow-scrolling:touch;
}

.prescription-table{
    min-width:800px; /* keeps structure */
}

/* Mobile optimization */
@media (max-width:768px){
    .prescription-table th,
    .prescription-table td{
        font-size:13px;
        padding:8px;
        white-space:nowrap;
    }
}

/* Extra small phones */
@media (max-width:480px){
    .prescription-table th,
    .prescription-table td{
        font-size:12px;
        padding:6px;
    }
}
</style>
</head>
<body>

<button class="hamburger"><i class="fas fa-bars"></i></button>
<div id="overlay"></div>

<div class="sidebar">
    <h2><img src="logo.png" alt="KNL Logo">KNL Health Center</h2>
    <a href="staff_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="staff_profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="patient_staff.php" class="active"><i class="fas fa-users"></i> Patients</a>
    <a href="staff_appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
    <a href="staff_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="staff_reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main">
    <?php include 'header_staff.php'; ?>
    <h2><?= htmlspecialchars($patient['full_name']); ?> - Appointments</h2>
<a href="patient_staff.php" class="btn mb-3" style="background-color:#001BB7; color:#fff; border:none;">
        <i class="fas fa-arrow-left"></i>
    </a>
    <!-- Search Form -->
   <form method="GET" class="mb-3 d-flex gap-2 align-items-center">
    <input type="hidden" name="id" value="<?= htmlspecialchars($patient_id); ?>">
    <input type="text" name="search" class="form-control" placeholder="Search by date or service" 
           value="<?= htmlspecialchars($search); ?>" style="height:45px; font-size:14px;">
    <button type="submit" class="btn btn-search">
        Search <i class="fas fa-search"></i>
    </button>
</form>


    <?php if(mysqli_num_rows($appointments_result) > 0): ?>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th><th>Date</th><th>Service</th><th>Type</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = mysqli_fetch_assoc($appointments_result)): ?>
            <tr>
                <td><?= htmlspecialchars($row['appointment_id']); ?></td>
                <td><?= htmlspecialchars($row['appointment_date']); ?></td>
                <td><?= htmlspecialchars($row['service_name']); ?></td>
                <td><?= htmlspecialchars($row['appointment_type']); ?></td>
                <td><button class="icon-btn" onclick="viewMedicalHistory('<?= $row['appointment_id']; ?>')"><i class="fas fa-eye"></i></button></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php if($total_pages > 1): ?>
<div class="pagination-wrapper">
    <ul class="pagination">
        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
            <a class="page-link" href="?id=<?= $patient_id; ?>&search=<?= urlencode($search); ?>&page=<?= max(1, $page-1); ?>">Prev</a>
        </li>

        <li class="page-item active">
            <span class="page-link"><?= $page; ?></span>
        </li>

        <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
            <a class="page-link" href="?id=<?= $patient_id; ?>&search=<?= urlencode($search); ?>&page=<?= min($total_pages, $page+1); ?>">Next</a>
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

hamburger.addEventListener('click', () => { sidebar.classList.toggle('active'); overlay.classList.toggle('active'); });
overlay.addEventListener('click', () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); });

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
    }).then((result)=>{if(result.isConfirmed){window.location.href='logout.php';}});
});

// AJAX view
function viewMedicalHistory(id){
    $.get('', {ajax_history:1, appointment_id:id}, function(response){
        Swal.fire({
            title:'Medical History',
            html:`<div style="max-height:none; overflow:auto;">${response}</div>`,
            width:'90%',
            heightAuto:false,
            confirmButtonText:'Close',
            customClass: {
                confirmButton: 'swal2-custom-close-btn'
            },
            buttonsStyling: false
        });
    });
}
</script>
</body>
</html>
