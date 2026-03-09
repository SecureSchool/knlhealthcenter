<?php
session_start();
require_once "config.php";

// Redirect if not logged in
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'];

// Count pending (Unverified) patients
$pending_patients_count = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM tblpatients WHERE status='Pending'"))['total'];

// Fetch summary stats
$patients_count = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM tblpatients"))['total'];
$appointments_count = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM tblappointments"))['total'];
$staff_count = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM tblstaff"))['total'];
$doctors_count = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM tbldoctors"))['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; min-height:100vh; color:#1e293b; transition:0.3s;}

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
    transition: 0.3s;
    z-index: 1100;
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
.sidebar a:hover {background:rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active {background:rgba(255,255,255,0.25); font-weight:600;}

/* Hamburger Button */
.hamburger {
    display:none;
    position:fixed;
    top:15px;
    left:15px;
    font-size:20px;
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
  z-index:1050;
}
#overlay.active {
  opacity:1;
  visibility:visible;
}

/* Main content */
.main {margin-left:250px; padding:30px; transition:0.3s;}
.section {margin-bottom:25px;}
h1 {margin-bottom:15px; color:#1e293b; font-weight:700;}
p {color:#4b5563; font-size:1rem;}

/* Cards */
.cards {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-bottom:30px;
}
.card {
    background:#fff;
    padding:25px;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    transition:0.3s;
    text-align:center;
}
.card:hover {transform:translateY(-5px);}
.card h3 {font-size:20px; margin-bottom:10px; color:#001BB7;}
.card p {font-size:28px; font-weight:700; color:#1e293b;}

/* Quick Links */
ul.quick-links {
    list-style:none;
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:15px;
    padding:0;
}
ul.quick-links li {
    background:#001BB7;
    padding:15px;
    border-radius:12px;
    text-align:center;
    transition:0.3s;
}
ul.quick-links li a {
    color:white;
    text-decoration:none;
    font-weight:500;
}
ul.quick-links li:hover {background:#001199;}

/* Sidebar icon */
.sidebar h2 i {font-size:28px; vertical-align:middle;}

/* Responsive sidebar for phones and tablets */
@media (max-width:1024px) {
  .hamburger { display:block; }

  .sidebar {
      position: fixed;                 /* stay fixed */
      top: 0;
      left: 0;
      width: 250px;
      height: 100%;
      transform: translateX(-280px);   /* hidden by default */
      background: #001BB7;
      color: #fff;
      padding: 25px 15px;
      display: flex;
      flex-direction: column;
      transition: transform 0.3s ease;
      overflow-y: auto;                /* enable scrolling */
      -webkit-overflow-scrolling: touch; /* smooth scroll on iOS */
      z-index: 1100;
  }

  .sidebar.active {
      transform: translateX(0);
  }

  .main {
      margin-left: 0;  /* reset margin for smaller screens */
      padding: 15px;
  }

  .cards {
      grid-template-columns: 1fr;      /* stack cards vertically */
  }

  ul.quick-links {
      grid-template-columns: 1fr;      /* stack links vertically */
  }
}

/* Optional: extra padding at bottom so last links aren't cut off */
.sidebar { padding-bottom: 50px; }
.btn-primary {
  background-color: #001BB7;
  border-color: #001BB7;
}
.btn-primary:hover {
  background-color: #001199;
}

.btn-success {
  background-color: #16a34a;
  border-color: #16a34a;
}
.btn-success:hover {
  background-color: #15803d;
}

.btn-warning {
  background-color: #f59e0b;
  border-color: #f59e0b;
  color: #fff;
}
.btn-warning:hover {
  background-color: #d97706;
}

.btn-info {
  background-color: #0ea5e9;
  border-color: #0ea5e9;
}
.btn-info:hover {
  background-color: #0284c7;
}
/* DARK MODE STYLES (CLEAR TEXT) */
body.dark-mode {
    background: #0b1220 !important;
    color: #f5f7fa !important;
}

/* Sidebar */
body.dark-mode .sidebar {
    background: #0b1430 !important;
}
body.dark-mode .sidebar a {
    color: #e9f2ff !important; /* brighter */
}
body.dark-mode .sidebar a:hover {
    background: rgba(255,255,255,0.18) !important; /* brighter hover */
    color: #fff !important;
}
body.dark-mode .sidebar a.active {
    background: rgba(255,255,255,0.25) !important;
    color: #fff !important;
}

/* Main content */
body.dark-mode .main {
    background: #0b1220 !important;
}

/* Header */
body.dark-mode .header-container {
    background: linear-gradient(90deg, #0f1a38, #00122b) !important;
    color: #fff !important;
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

/* Quick links */
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
body.dark-mode .btn-light {
    color: #fff !important;
    border-color: rgba(255,255,255,0.25) !important;
}

/* Specific button colors for readability */
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

/* Texts */
body.dark-mode h1,
body.dark-mode h2,
body.dark-mode h3,
body.dark-mode p,
body.dark-mode td,
body.dark-mode th,
body.dark-mode span,
body.dark-mode label {
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

</style>
</head>
<body>

<!-- Hamburger -->
<button class="hamburger" aria-label="Open navigation menu" aria-expanded="false">
  <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
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
    <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Overlay -->
<div id="overlay" aria-hidden="true"></div>

<!-- Main Content -->
<div class="main">
    <?php include 'admin_header.php'; ?>

    <p>Overview of your health center data:</p>
<div class="mb-4 d-flex gap-2 flex-wrap">
  
  <a href="add_doctor.php" class="btn btn-primary">
    <i class="fas fa-user-md"></i> Add Doctor
  </a>

  <a href="add_staff.php" class="btn btn-success">
    <i class="fas fa-user-nurse"></i> Add Staff
  </a>

  <a href="add_announcement.php" class="btn btn-warning">
    <i class="fas fa-bullhorn"></i> Post Announcement
  </a>

  <a href="add_service.php" class="btn btn-info text-white">
    <i class="fas fa-stethoscope"></i> Add Service
  </a>

</div>

    <div class="cards">
        <div class="card"><h3>Patients</h3><p><?php echo $patients_count; ?></p></div>
        <div class="card"><h3>Appointments</h3><p><?php echo $appointments_count; ?></p></div>
        <div class="card"><h3>Staff</h3><p><?php echo $staff_count; ?></p></div>
        <div class="card"><h3>Doctors</h3><p><?php echo $doctors_count; ?></p></div>
    </div>

    <h2>Quick Access</h2>
    <ul class="quick-links">
        <li><a href="patients.php">Manage Patients</a></li>
        <li><a href="appointments.php">Manage Appointments</a></li>
        <li><a href="staff.php">Manage Staff</a></li>
        <li><a href="doctors.php">Manage Doctors</a></li>
        <li><a href="reports.php">View Reports</a></li>
        <li><a href="settings.php">Settings</a></li>
    </ul>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle
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

// Pending patients alert
<?php if($pending_patients_count > 0): ?>
Swal.fire({
    icon: 'info',
    title: 'Pending Patients',
    html: 'You have <b><?php echo $pending_patients_count; ?></b> patient(s) pending review. <br><a href="patients.php" style="color:#001BB7; text-decoration:underline;">Review now</a>',
    showCloseButton: true,
    showConfirmButton: false,
    timer: 8000
});
<?php endif; ?>

// Update date & time
function updateDateTime() {
    const now = new Date();
    const options = { weekday:'long', year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit' };
    const el = document.getElementById('currentDateTime');
    if(el) el.textContent = now.toLocaleDateString('en-US', options);
}
setInterval(updateDateTime, 1000);
updateDateTime();
</script>
</body>
</html>
