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

// Quick Stats
$pending_count = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as cnt FROM tblappointments WHERE patient_id='$patient_id' AND status='Pending'"))['cnt'];
$completed_count = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as cnt FROM tblappointments WHERE patient_id='$patient_id' AND status='Completed'"))['cnt'];
$cancelled_count = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as cnt FROM tblappointments WHERE patient_id='$patient_id' AND status='Cancelled'"))['cnt'];

// Fetch upcoming appointments (Pending)
$upcoming_appointments = mysqli_query($link, "
    SELECT a.*, s.service_name, d.fullname AS doctor_name
    FROM tblappointments a
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    LEFT JOIN tbldoctors d ON a.doctor_assigned = d.doctor_id
    WHERE a.patient_id='$patient_id' AND a.status='Pending'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$next_appointment = null;

$queue_result = mysqli_query($link, "
    SELECT queue_number, appointment_date, appointment_time, s.service_name
    FROM tblappointments a
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    WHERE a.patient_id='$patient_id' AND a.status='Pending' 
    ORDER BY a.appointment_date ASC, a.appointment_time ASC 
    LIMIT 1
");

if($queue_result && mysqli_num_rows($queue_result) > 0){
    $next_appointment = mysqli_fetch_assoc($queue_result);
}
$all_pending_queues = mysqli_query($link, "
    SELECT queue_number, appointment_date, appointment_time, s.service_name
    FROM tblappointments a
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    WHERE a.patient_id='$patient_id' AND a.status='Pending'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$ahead_count = 0;

if($next_appointment) {
    $appointment_date = $next_appointment['appointment_date'];
    $queue_number = $next_appointment['queue_number'];
    $service_id = mysqli_fetch_assoc(mysqli_query($link, "SELECT service_id FROM tblservices WHERE service_name='".mysqli_real_escape_string($link, $next_appointment['service_name'])."'"))['service_id'];

    $ahead_query = mysqli_query($link, "
        SELECT COUNT(*) AS cnt
        FROM tblappointments
        WHERE status='Pending'
          AND service_id='$service_id'
          AND appointment_date='$appointment_date'
          AND queue_number < '$queue_number'
    ");
    $ahead_count = mysqli_fetch_assoc($ahead_query)['cnt'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* GENERAL */
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b;}

/* SIDEBAR */
.sidebar {
    width:250px; background: #001BB7; color:#fff; position:fixed; height:100%; padding:25px 15px; display:flex; flex-direction:column;
}
.sidebar h2 {text-align:center; margin-bottom:35px; font-size:24px; font-weight:700;}
.sidebar a {color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;}
.sidebar a i {margin-right:12px; font-size:18px;}
.sidebar a:hover {background: rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active {background: rgba(255,255,255,0.25); font-weight:600;}

/* MAIN */
.main {margin-left:250px; padding:30px; flex:1;}
h1 {margin-bottom:10px; color:#001BB7; font-weight:700;}
p {color:#64748b; margin-bottom:25px; font-size:16px;}

/* CARDS */
.card {background:#fff; padding:25px; border-radius:16px; box-shadow:0 8px 20px rgba(0,0,0,0.08); margin-bottom:25px;}
.card h2 {margin-bottom:15px; color:#001BB7;}

/* QUICK STATS */
.stats {display:flex; gap:20px; flex-wrap:wrap;}
.stat-card {flex:1; min-width:150px; border-radius:12px; padding:20px; color:#fff; text-align:center; box-shadow:0 4px 15px rgba(0,0,0,0.1);}
.stat-card h3 {font-size:18px; margin-bottom:8px; font-weight:600;}
.stat-card p {font-size:22px; font-weight:700; color: #fff;}
.stat-pending {background:#001BB7;}    /* deep blue */
.stat-completed {background:#16a34a;}  /* green */
.stat-cancelled {background:#ef4444;}  /* red */

/* TABLE */
table {width:100%; border-collapse:collapse; border-radius:16px; overflow:hidden; box-shadow:0 4px 14px rgba(0,0,0,0.05);}
th, td {padding:14px; text-align:left; border-bottom:1px solid #e5e7eb;}
th {background:#001BB7; color:white; font-weight:600;}
.card table tbody tr:hover{
    background:#f1f5f9;
}

td {font-size:14px;}
.status-pending {color:#facc15; font-weight:600;}
.status-assigned {color:#16a34a; font-weight:600;}
.status-completed {color:#3b82f6; font-weight:600;}
.status-cancelled {color:#ef4444; font-weight:600;}
.sidebar h2 i {
    font-size:28px;
    vertical-align:middle;
}
.sidebar h2 {
    font-size:24px;
    font-weight:700;
    text-align:center;
    margin-bottom: 20px;
}
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
/* Notification Bell Base */
.notif-container {
    position: relative;
}

.notif-bell {
    position: relative;
    cursor: pointer;
    font-size: 22px;
    color: #001BB7;
}

.notif-bell:hover {
    opacity: 0.7;
}

/* Red counter badge */
.notif-badge {
    background: red;
    color: white;
    font-size: 12px;
    padding: 2px 6px;
    border-radius: 50%;
    position: absolute;
    top: -6px;
    right: -10px;
}

/* Dropdown Box */
.notif-dropdown {
    position: absolute;
    top: 35px;
    right: 0;
    width: 320px;
    max-height: 350px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    padding: 0;
    overflow-y: auto;
    display: none; /* Hidden by default */
    z-index: 9999;
}

.notif-dropdown.show {
    display: block;
}

/* Header */
.notif-header {
    padding: 10px;
    font-weight: bold;
    text-align: center;
    background: #001BB7;
    color: white;
    border-radius: 12px 12px 0 0;
}

/* Notification item */
.notif-item {
    padding: 10px 12px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    color: #001BB7;
}

.notif-item:hover {
    background: #f5f7fa;
}

.notif-item .small {
    font-size: 12px;
    color: gray;
}
/* Unread notification highlight */
.notif-item.unread {
    background: #e0f2ff; /* light blue background */
    font-weight: 600;
    transition: background 0.3s, color 0.3s;
}

/* Hover effect for all notifications */
.notif-item:hover {
    background: #f5f7fa;
}

/* Optional: fade effect when read */
.notif-item.read {
    background: white;
    font-weight: 400;
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
/* ================================
   DARK MODE – UPCOMING APPOINTMENT
   ================================ */

/* Upcoming Appointment card */
body.dark-mode div[style*="Upcoming Appointment"],
body.dark-mode div[style*="#e9f0ff"],
body.dark-mode div[style*="Upcoming Appointment"] * {
    background: #111b2b !important;
    color: #ffffff !important;
}

/* Headings inside upcoming appointment */
body.dark-mode div[style*="Upcoming Appointment"] h2 {
    color: #60a5fa !important;
}

/* Service name */
body.dark-mode div[style*="Upcoming Appointment"] p {
    color: #e5e7eb !important;
}

/* Queue number */
body.dark-mode div[style*="Queue Number"] b {
    color: #ffffff !important;
}

/* Countdown */
body.dark-mode #appointmentCountdown {
    color: #60a5fa !important;
}

/* ================================
   DARK MODE – OTHER PENDING QUEUES
   ================================ */

body.dark-mode div[style*="Other Pending Appointments"] {
    background: #111b2b !important;
    color: #ffffff !important;
}

body.dark-mode div[style*="Queue Number:"] {
    color: #ffffff !important;
}

/* Past appointments highlight */
body.dark-mode div[style*="#fee2e2"] {
    background: #3b0f14 !important;
}
/* ===============================
   FORCE WHITE TEXT – DARK MODE
   =============================== */

body.dark-mode,
body.dark-mode * {
    color: #ffffff !important;
}

/* Muted / secondary text */
body.dark-mode small,
body.dark-mode .text-muted,
body.dark-mode p {
    color: #e5e7eb !important;
}

/* Headings */
body.dark-mode h1,
body.dark-mode h2,
body.dark-mode h3,
body.dark-mode h4,
body.dark-mode h5,
body.dark-mode h6 {
    color: #ffffff !important;
}

/* Links */
body.dark-mode a {
    color: #93c5fd !important;
}
body.dark-mode a:hover {
    color: #bfdbfe !important;
}
/* =========================
   DARK MODE – NOTIFICATIONS
   ========================= */

body.dark-mode .notif-bell {
    color: #e5e7eb !important;
}

/* Dropdown box */
body.dark-mode .notif-dropdown {
    background: #111b2b !important;
    border: 1px solid rgba(255,255,255,0.15) !important;
    box-shadow: 0 6px 16px rgba(0,0,0,0.6) !important;
}

/* Header */
body.dark-mode .notif-header {
    background: #0b1430 !important;
    color: #ffffff !important;
}

/* Notification items */
body.dark-mode .notif-item {
    background: #111b2b !important;
    color: #e5e7eb !important;
    border-bottom: 1px solid rgba(255,255,255,0.08) !important;
}

/* Hover */
body.dark-mode .notif-item:hover {
    background: rgba(255,255,255,0.08) !important;
}

/* Unread highlight */
body.dark-mode .notif-item.unread {
    background: #1e293b !important;
    color: #ffffff !important;
}

/* Read items */
body.dark-mode .notif-item.read {
    background: #111b2b !important;
    color: #cbd5f1 !important;
}

/* Small text */
body.dark-mode .notif-item .small {
    color: #94a3b8 !important;
}
.other-appointments-card{
    text-align:center;
    margin:25px 0;
    background:white;
    padding:25px;
    border-radius:16px;
    box-shadow:0 4px 12px rgba(0,0,0,0.06);
}

body.dark-mode .other-appointments-card{
    background:#111b2b;
    color:#fff;
    box-shadow:0 4px 12px rgba(0,0,0,0.6);
}
.queue-item{
    margin:15px 0;
    padding:10px;
    border-bottom:1px solid #e5e7eb;
    border-radius:8px;
}

.queue-item.past{
    background:#fee2e2;
}

/* DARK MODE */
body.dark-mode .queue-item{
    border-bottom:1px solid rgba(255,255,255,0.15);
}

body.dark-mode .queue-item.past{
    background:#3b0f14;
}
p{
    color: white;
}
/* FORCE DARK TABLE HOVER FIX */
body.dark-mode .card table tbody tr:hover{
    background:#1e293b !important;
}

body.dark-mode .card table tbody tr:hover td{
    background:#1e293b !important;
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
    <a href="patient_dashboard.php" style="text-decoration:none; color:white;">
        <img src="logo.png" alt="KNL Logo" 
         style="width:45px; height:45px; margin-right:10px; vertical-align:middle; border-radius:50%; object-fit:cover;">
        KNL Health Center
    </a>
</h2>

    <a href="patient_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
    <a href="patient_profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="patient_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="patient_request.php"><i class="fas fa-calendar-plus"></i> Request Appointment</a>
    <a href="patient_appointments.php"><i class="fas fa-calendar-alt"></i> My Appointments</a>
    <a href="patient_medical_history.php"><i class="fas fa-file-medical"></i> Medical History</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Overlay -->
<div id="overlay" aria-hidden="true"></div>

<div class="main">
    <?php include 'patient_header.php'; ?>

    <p>View your quick stats and upcoming appointments below:</p>

    <!-- Quick Stats -->
    <div class="card">
        <h2>Quick Stats</h2>
        <div class="stats">
            <div class="stat-card stat-pending">
                <h3>Pending</h3>
                <p><?php echo $pending_count; ?></p>
            </div>
            <div class="stat-card stat-completed">
                <h3>Completed</h3>
                <p><?php echo $completed_count; ?></p>
            </div>
            <div class="stat-card stat-cancelled">
                <h3>Cancelled</h3>
                <p><?php echo $cancelled_count; ?></p>
            </div>
        </div>
    </div>
    <!-- EARLIEST APPOINTMENT DISPLAY -->
<div style="text-align:center; margin:25px 0; padding:25px; border-radius:16px; box-shadow:0 4px 12px rgba(0,0,0,0.07); background:<?php echo $next_appointment ? '#e9f0ff' : '#fff'; ?>;">

    <h2 style="margin-bottom:10px; color:#001BB7; font-weight:700;">
        Upcoming Appointment
    </h2>

    <?php if($next_appointment): ?>
        <p style="font-size:22px; font-weight:600; color:#001BB7; margin-bottom:6px;">
            <?php echo htmlspecialchars($next_appointment['service_name']); ?>
        </p>

        <p style="font-size:18px; color:#333; margin-bottom:8px;">
            <?php echo $next_appointment['appointment_date']; ?> 
            at <?php echo $next_appointment['appointment_time']; ?>
        </p>

        <p style="font-size:16px; color:#001BB7; margin-top:10px;">
    Queue Number: <b style="font-size:28px; color:black;"><?php echo $next_appointment['queue_number']; ?></b>
    <?php if($ahead_count > 0): ?>
        (<span style="font-size:16px; color:#64748b;"><?php echo $ahead_count; ?> ahead of you</span>)
    <?php else: ?>
        (<span style="font-size:16px; color:#16a34a;">You're next!</span>)
    <?php endif; ?>
</p>


        <p style="font-size:16px; color:#001BB7; margin-top:10px;">
            Time remaining: <span id="appointmentCountdown">Loading...</span>
        </p>
    <?php else: ?>
        <p style="font-size:16px; color:#64748b; margin-top:10px;">
            You have no upcoming appointments. <a href="patient_request.php" style="color:#001BB7; font-weight:600;">Book one now</a>!
        </p>
    <?php endif; ?>

</div>

<!-- OTHER PENDING QUEUE NUMBERS -->
<?php if(mysqli_num_rows($all_pending_queues) > 1): ?>
<div class="other-appointments-card">

    <h2 style="margin-bottom:15px; color:#001BB7; font-weight:700;">
        Other Pending Appointments
    </h2>

    <?php 
        $now = new DateTime(); // current datetime

        while($row = mysqli_fetch_assoc($all_pending_queues)) {

            // Skip the earliest appointment
            if($row['queue_number'] == $next_appointment['queue_number']) continue;

            $appointmentDateTime = new DateTime($row['appointment_date'] . ' ' . $row['appointment_time']);
            $isPast = $appointmentDateTime < $now;

            echo "
            <div style='margin:15px 0; padding:10px; border-bottom:1px solid #e5e7eb; class='queue-item ".($isPast ? "past" : "")."'
 border-radius:8px;'>
                <div style=\"font-size:20px; font-weight:700; color:" . ($isPast ? "#b91c1c" : "black") . "; margin-bottom:3px;\">
                    Queue Number: {$row['queue_number']} " . ($isPast ? "(Past)" : "") . "
                </div>

                <div style='font-size:17px; color:" . ($isPast ? "#b91c1c" : "#001BB7") . "; font-weight:600;'>
                    ".htmlspecialchars($row['service_name'])."
                </div>

                <div style='font-size:14px; color:#64748b; margin-top:3px;'>
                    {$row['appointment_date']} at {$row['appointment_time']}
                </div>
            </div>
            ";
        }
    ?>

</div>
<?php endif; ?>

    <!-- Upcoming Appointments -->
    <div class="card">
        <h2>Upcoming Appointments</h2>
        <table>
            <tr>
                <th>Service</th>
                <th>Date</th>
                <th>Time</th>
                <th>Doctor</th>
                <th>Status</th>
            </tr>
            <?php if(mysqli_num_rows($upcoming_appointments) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($upcoming_appointments)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['service_name']); ?></td>
                    <td><?php echo $row['appointment_date']; ?></td>
                    <td><?php echo $row['appointment_time']; ?></td>
                    <td><?php echo htmlspecialchars($row['doctor_name'] ?? 'Not assigned'); ?></td>
                    <td class="status-<?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center;">No upcoming appointments.</td></tr>
            <?php endif; ?>
        </table>
    </div>

</div>
<script>
// Function to format date and time
function updateDateTime() {
    const now = new Date();
    const options = { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', 
        hour: '2-digit', minute: '2-digit', second: '2-digit' 
    };
    document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
}

// Update every second
setInterval(updateDateTime, 1000);
updateDateTime(); // initial call
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
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Toggle dropdown visibility
document.getElementById("notifBell").addEventListener("click", function (e) {
    e.stopPropagation();
    document.getElementById("notifDropdownMenu").classList.toggle("show");
});

// Close dropdown when clicking outside
document.addEventListener("click", function () {
    document.getElementById("notifDropdownMenu").classList.remove("show");
});

// Load notifications from server
function loadNotifications() {
    $.get("fetch_notifications.php", function (data) {
        let res = JSON.parse(data);

        // Update counter
        if (res.count > 0) {
            $("#notifCount").text(res.count).show();
        } else {
            $("#notifCount").hide();
        }

        // Render list
        let html = "";
        res.notifications.forEach(n => {
            html += `
                <div class="notif-item ${n.is_read == 0 ? 'unread' : 'read'}" data-id="${n.notif_id}">
                    <div class="small">${n.created_at}</div>
                    <div><b>${n.title}</b></div>
                    <div class="small">${n.message}</div>
                </div>
            `;
        });

        $("#notifList").html(html);
    });
}

// Mark notification as read when clicked
$(document).on("click", ".notif-item.unread", function () {
    let id = $(this).data("id");
    let item = $(this);

    $.post("read_notification.php", { id: id }, function () {
        // Update visual: fade highlight
        item.removeClass("unread").addClass("read");
        loadNotifications(); // refresh counter & list
    });
});

// Refresh notifications every 5 seconds
setInterval(loadNotifications, 5000);
loadNotifications(); // initial load

let shownMeds = new Set(); // Keep track of medicine IDs already shown this session

function checkMedicineReminders() {
    $.get("fetch_med_reminders.php", function(data) {
        let meds = JSON.parse(data);

        // Filter out meds that have already been shown
        meds = meds.filter(med => !shownMeds.has(med.prescription_id));
        if (meds.length === 0) return; // no new reminders

        let index = 0;

        function showNextReminder() {
            if (index >= meds.length) return;

            let med = meds[index];
            shownMeds.add(med.prescription_id); // mark as shown in this session

            Swal.fire({
    title: `Time to take your medicine!`,
    html: `<b>${med.medication}</b><br>Dosage: ${med.dosage}<br>Instructions: ${med.instructions}`,
    icon: 'info',
    showCancelButton: true,
    confirmButtonText: 'Taken',
    cancelButtonText: 'Remind me later',
    confirmButtonColor: '#001BB7', // blue color for "Taken"
    cancelButtonColor: '#6b7280',  // gray color for "Remind me later"
    allowOutsideClick: false,
    allowEscapeKey: false
}).then((result) => {
                if (result.isConfirmed) {
                    $.post("mark_taken.php", { prescription_id: med.prescription_id }, function(res) {
                        if(res === "success") {
    Swal.fire({
        title: 'Good!',
        text: 'Marked as taken.',
        icon: 'success',
        confirmButtonText: 'OK',
        confirmButtonColor: '#001BB7' // <-- this changes the OK button color
    }).then(() => {
        index++;
        showNextReminder();
    });
} else {
    Swal.fire({
        title: 'Error!',
        text: 'Could not mark as taken.',
        icon: 'error',
        confirmButtonText: 'OK',
        confirmButtonColor: '#ef4444' // optional: red for error
    }).then(() => {
        index++;
        showNextReminder();
    });
}

                    });
                } else {
                    // "Remind me later" clicked, move to next reminder
                    index++;
                    showNextReminder();
                }
            });
        }

        // start showing reminders
        showNextReminder();
    });
}

// Check every 5 minutes
setInterval(checkMedicineReminders, 300000);
checkMedicineReminders(); // initial check

</script>
<script>
// Countdown for upcoming appointment
<?php if($next_appointment): ?>
const appointmentDate = "<?php echo $next_appointment['appointment_date'] . ' ' . $next_appointment['appointment_time']; ?>";
const appointmentTime = new Date(appointmentDate);

function updateCountdown() {
    const now = new Date();
    let diff = appointmentTime - now;

    const countdownElement = document.getElementById("appointmentCountdown");

    if(diff < 0){
        countdownElement.textContent = "Appointment passed";
        countdownElement.style.color = "#ef4444"; // red text for past
        clearInterval(countdownInterval);
        return;
    } else if(diff === 0){
        countdownElement.textContent = "Appointment time!";
        countdownElement.style.color = "#16a34a"; // green text for now
        clearInterval(countdownInterval);
        return;
    }

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
    const minutes = Math.floor((diff / (1000 * 60)) % 60);
    const seconds = Math.floor((diff / 1000) % 60);

    let countdownText = "";
    if(days > 0) countdownText += days + "d ";
    countdownText += hours.toString().padStart(2,'0') + "h ";
    countdownText += minutes.toString().padStart(2,'0') + "m ";
    countdownText += seconds.toString().padStart(2,'0') + "s";

    countdownElement.textContent = countdownText;
    countdownElement.style.color = "#001BB7"; // normal blue for upcoming
}

const countdownInterval = setInterval(updateCountdown, 1000);
updateCountdown(); // initial call
<?php endif; ?>
</script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
