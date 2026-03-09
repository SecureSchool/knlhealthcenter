<?php
session_start();
require_once "config.php";

// Redirect if not logged in as admin
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

if(isset($_POST['add_service'])){
    $service_name = mysqli_real_escape_string($link, $_POST['service_name']);
    $description = mysqli_real_escape_string($link, $_POST['description']);
    $date_created = date('Y-m-d');
    $doctor_id = intval($_POST['doctor_id']);

    // Backend validation: Check if doctor already has a service
    $checkDoctor = mysqli_query($link, "SELECT * FROM tblservices WHERE doctor_id = '$doctor_id'");
    if(mysqli_num_rows($checkDoctor) > 0){
        $error = "Selected doctor already has a service assigned.";
    }

    $uploadDir = 'uploads/services/';
    $image1Path = '';
    $image2Path = '';

    if(isset($_FILES['image1']) && $_FILES['image1']['error'] == 0){
        $image1Name = time() . '_1_' . basename($_FILES['image1']['name']);
        $target1 = $uploadDir . $image1Name;
        if(move_uploaded_file($_FILES['image1']['tmp_name'], $target1)){
            $image1Path = $target1;
        } else {
            $error = "Failed to upload Image 1.";
        }
    }

    if(isset($_FILES['image2']) && $_FILES['image2']['error'] == 0){
        $image2Name = time() . '_2_' . basename($_FILES['image2']['name']);
        $target2 = $uploadDir . $image2Name;
        if(move_uploaded_file($_FILES['image2']['tmp_name'], $target2)){
            $image2Path = $target2;
        } else {
            $error = "Failed to upload Image 2.";
        }
    }

    if(empty($service_name)){
        $error = "Service name cannot be empty.";
    }

    if(!$error){
        $insert_sql = "INSERT INTO tblservices (service_name, doctor_id, description, image1, image2, date_created) 
               VALUES ('$service_name', '$doctor_id', '$description', '$image1Path', '$image2Path', '$date_created')";

        if(mysqli_query($link, $insert_sql)){
            header("Location: services.php?added=1");
            exit;
        } else {
            $error = "Database error: " . mysqli_error($link);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Service - Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b;}
.sidebar {width:250px; background:#001BB7; color:#fff; position:fixed; height:100%; padding:25px 15px; display:flex; flex-direction:column;}
.sidebar h2 {text-align:center; margin-bottom:20px; font-size:24px; font-weight:700;}
.sidebar a {color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;}
.sidebar a i {margin-right:12px; font-size:18px;}
.sidebar a:hover {background:rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active {background:rgba(255,255,255,0.25); font-weight:600;}
.sidebar h2 i {font-size:28px; vertical-align:middle;}
.main {margin-left:250px; padding:30px; flex:1;}
h1 {margin-bottom:20px; color:#001BB7; font-weight:700;}
form {background:#fff; padding:25px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);}
label {display:block; margin:10px 0 5px;}
input, textarea, select {width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:15px;}
button.submit-btn {background:#001BB7; color:white; padding:10px 18px; border:none; border-radius:8px; cursor:pointer; font-weight:600;}
button.submit-btn:hover {background:#001199;}
.error {color:red; margin-bottom:10px;}
.success {color:green; margin-bottom:10px;}
.back-btn {display:inline-flex; align-items:center; color:#001BB7; font-weight:600; text-decoration:none; font-size:20px; margin-bottom:15px; transition:0.2s;}
.back-btn i {font-size:20px;}
.back-btn:hover {color:#001199;}
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

/* Page Title */
body.dark-mode h1 {
    color: #e9f2ff !important;
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
body.dark-mode label,
body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea {
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
body.dark-mode table th {
    background: #0b1430 !important;
    color: #fff !important;
}
body.dark-mode tr:hover {
    background: rgba(255,255,255,0.08) !important;
}

/* Inputs */
body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea {
    background: #1a2337 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.3) !important;
}

/* Pagination */
body.dark-mode .pagination .page-link {
    color: #e9f2ff !important;
    background: #0b1430 !important;
    border: 1px solid rgba(255,255,255,0.2) !important;
}
body.dark-mode .pagination .page-item.active .page-link {
    background: #0b1430 !important;
    color: #fff !important;
    border-color: #0b1430 !important;
}

/* Action buttons */
body.dark-mode .action-btn {
    color: #fff !important;
}
body.dark-mode .edit-btn { background: #1f7a3d !important; }
body.dark-mode .delete-btn { background: #c0392b !important; }
body.dark-mode .toggle-btn { background: #e67e22 !important; }
body.dark-mode .view-btn { background: #0b1430 !important; }

/* Disabled button */
body.dark-mode .delete-btn.disabled {
    background: #444 !important;
    color: #ddd !important;
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

/* SweetAlert */
body.dark-mode .swal2-popup {
    background: #111b2b !important;
    color: #fff !important;
}
body.dark-mode .swal2-title,
body.dark-mode .swal2-content {
    color: #fff !important;
}
/* ===== DARK MODE FORM ===== */
body.dark-mode form {
    background: #111b2b !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.6) !important;
}

body.dark-mode form label {
    color: #f5f7fa !important;
}

body.dark-mode form input,
body.dark-mode form select,
body.dark-mode form textarea {
    background: #1a2337 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.3) !important;
}

body.dark-mode form input::placeholder,
body.dark-mode form textarea::placeholder {
    color: rgba(255,255,255,0.6) !important;
}

body.dark-mode form button.submit-btn {
    background: #0b6fb5 !important;
    color: #fff !important;
    border: 1px solid #0b6fb5 !important;
}

body.dark-mode form button.submit-btn:hover {
    background: #0a5f9c !important;
}
/* Hamburger visible only on mobile */
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
  z-index: 1500;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

/* Overlay */
#overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.25s ease, visibility 0.25s;
  z-index: 1400;
}
#overlay.active {
  opacity: 1;
  visibility: visible;
}

/* Mobile Sidebar */
@media (max-width: 768px) {
  .hamburger {
    display: block;
  }

  .sidebar {
    position: fixed;
    top: 0;
    left: -250px; /* hidden initially */
    width: 250px;
    height: 100%;
    background: #001BB7;
    padding: 25px 15px;
    flex-direction: column;
    transition: left 0.3s ease;
    z-index: 1450;
    overflow-y: auto;
  }

  .sidebar.active {
    left: 0;
  }

  .main {
    margin-left: 0;
    padding: 20px;
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
</style>
</head>
<body>
<!-- Hamburger Button (visible on mobile/tablet) -->
<button class="hamburger" aria-label="Open navigation menu" aria-expanded="false">
  <i class="fas fa-bars"></i>
</button>

<!-- Overlay -->
<div id="overlay" aria-hidden="true"></div>
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
    <a href="services.php" class="active"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="announcement.php"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main">
    <?php include 'admin_header.php'; ?>
    <a href="services.php" class="back-btn"><i class="fas fa-chevron-left"></i></a>
    <h1><i class="fas fa-stethoscope me-2"></i> Add New Service</h1>

    <?php if($error) echo "<p class='error'>$error</p>"; ?>
    <?php if($success) echo "<p class='success'>$success</p>"; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Service Name</label>
        <input type="text" name="service_name" required>

        <label>Assigned Doctor</label>
        <select name="doctor_id" required class="form-select mb-3">
            <option value="">-- Select Doctor --</option>
            <?php
            $doctorQuery = mysqli_query($link, "
                SELECT d.doctor_id, d.fullname, s.service_id 
                FROM tbldoctors d
                LEFT JOIN tblservices s ON d.doctor_id = s.doctor_id
                WHERE d.status='Active'
            ");
            while($doc = mysqli_fetch_assoc($doctorQuery)){
                $disabled = $doc['service_id'] ? 'disabled' : '';
                $note = $doc['service_id'] ? ' (Already Assigned)' : '';
                echo "<option value='{$doc['doctor_id']}' $disabled>{$doc['fullname']}{$note}</option>";
            }
            ?>
        </select>

        <label>Description</label>
        <textarea name="description" rows="4"></textarea>

        <label>Image 1</label>
        <input type="file" name="image1" accept="image/*">

        <label>Image 2</label>
        <input type="file" name="image2" accept="image/*">

        <button type="submit" name="add_service" class="submit-btn"><i class="fas fa-plus me-2"></i> Add Service</button>
    </form>
</div>

<script>
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
</script>
<script>
const hamburger = document.querySelector('.hamburger');
const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('overlay');

hamburger.addEventListener('click', () => {
  sidebar.classList.toggle('active');
  overlay.classList.toggle('active');
  hamburger.setAttribute('aria-expanded', sidebar.classList.contains('active'));
});

overlay.addEventListener('click', () => {
  sidebar.classList.remove('active');
  overlay.classList.remove('active');
  hamburger.setAttribute('aria-expanded', 'false');
});
</script>

</body>
</html>
