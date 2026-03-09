<?php
session_start();
require_once "config.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/SMTP.php';


// Function to send email
function sendEmail($to, $subject, $body){
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'krusnaligashealthcenter@gmail.com'; // <-- palitan ng Gmail mo
        $mail->Password = 'nwbl trbm yphq dmyl';    // <-- 16-digit App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('krusnaligashealthcenter@gmail.com', 'KNL Health Center');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// --- LOG FUNCTION ---
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
if(isset($_GET['verify_id'])){
    $verify_id = $_GET['verify_id'];

    $stmt = mysqli_prepare($link, "UPDATE tblpatients SET status='Verified' WHERE patient_id=?");
    mysqli_stmt_bind_param($stmt, "s", $verify_id);

    if(mysqli_stmt_execute($stmt)){
        $_SESSION['success_update'] = "Patient marked as Verified!";
        logAction($link, "Verify", "Patients", $verify_id, $_SESSION['admin_name']);

        // Get patient email
        $res = mysqli_query($link, "SELECT email, full_name FROM tblpatients WHERE patient_id='$verify_id'");
        $row = mysqli_fetch_assoc($res);
        $email = $row['email'];
        $fullName = $row['full_name'];

        // Send verification email
$subject = "Your Account has been Verified";

$body = "
<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<title>Account Verified</title>
<style>
    body {font-family: Arial, sans-serif; background-color: #f5f7fa; margin: 0; padding: 0;}
    .email-container {max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);}
    .header {background-color: #001BB7; color: #ffffff; padding: 20px; text-align: center; font-size: 24px; font-weight: bold;}
    .content {padding: 30px; color: #333;}
    .content h3 {color: #001BB7;}
    .content p {line-height: 1.6; margin-bottom: 20px;}
    .button {display: inline-block; padding: 12px 25px; background-color: #27ae60; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;}
    .footer {padding: 20px; text-align: center; font-size: 12px; color: #777;}
    .footer a {color: #001BB7; text-decoration: none;}
</style>
</head>
<body>
    <div class='email-container'>
        <div class='header'>KNL Health Center</div>
        <div class='content'>
            <h3>Hello {$fullName},</h3>
            <p>Your account has been successfully <strong>verified</strong> by KNL Health Center. You can now log in and access your account.</p>
            <p style='text-align:center;'>
                <a href='https://your-website.com/login' class='button'>Login Now</a>
            </p>
            <p>If you did not request this action, please contact our support team immediately.</p>
        </div>
        <div class='footer'>
            KNL Health Center • Brgy Krus na Ligas, Quezon City<br>
                &copy; ".date('Y')." KNL Health Center. All rights reserved.
        </div>
    </div>
</body>
</html>
";
        sendEmail($email, $subject, $body);
    } else {
        $_SESSION['error_delete'] = "Failed to update patient status.";
    }

    mysqli_stmt_close($stmt);
    header("Location: patients.php");
    exit;
}

if(isset($_GET['unverify_id'])){
    $unverify_id = $_GET['unverify_id'];

    $stmt = mysqli_prepare($link, "UPDATE tblpatients SET status='Unverified' WHERE patient_id=?");
    mysqli_stmt_bind_param($stmt, "s", $unverify_id);

    if(mysqli_stmt_execute($stmt)){
        $_SESSION['success_update'] = "Patient marked as Unverified!";
        logAction($link, "Unverify", "Patients", $unverify_id, $_SESSION['admin_name']);

        // Get patient email
        $res = mysqli_query($link, "SELECT email, full_name FROM tblpatients WHERE patient_id='$unverify_id'");
        $row = mysqli_fetch_assoc($res);
        $email = $row['email'];
        $fullName = $row['full_name'];

        // Send unverification email
$subjectUnverify = "Your Account has been Unverified";

$bodyUnverify = "
<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<title>Account Unverified</title>
<style>
    body {font-family: Arial, sans-serif; background-color: #f5f7fa; margin: 0; padding: 0;}
    .email-container {max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);}
    .header {background-color: #c0392b; color: #ffffff; padding: 20px; text-align: center; font-size: 24px; font-weight: bold;}
    .content {padding: 30px; color: #333;}
    .content h3 {color: #c0392b;}
    .content p {line-height: 1.6; margin-bottom: 20px;}
    .button {display: inline-block; padding: 12px 25px; background-color: #001BB7; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;}
    .footer {padding: 20px; text-align: center; font-size: 12px; color: #777;}
    .footer a {color: #001BB7; text-decoration: none;}
</style>
</head>
<body>
    <div class='email-container'>
        <div class='header'>KNL Health Center</div>
        <div class='content'>
            <h3>Hello {$fullName},</h3>
            <p>Your account has been marked as <strong>Unverified</strong> by KNL Health Center. Please contact the admin for more details or if you believe this is a mistake.</p>
            <p style='text-align:center;'>
                <a href='https://your-website.com/contact' class='button'>Contact Support</a>
            </p>
            <p>If you did not request this action, please reach out immediately.</p>
        </div>
        <div class='footer'>
            KNL Health Center • Brgy Krus na Ligas, Quezon City<br>
                &copy; ".date('Y')." KNL Health Center. All rights reserved.
        </div>
    </div>
</body>
</html>
";
        sendEmail($email, $subjectUnverify, $bodyUnverify);

    } else {
        $_SESSION['error_delete'] = "Failed to update patient status.";
    }

    mysqli_stmt_close($stmt);
    header("Location: patients.php");
    exit;
}

// Redirect if not logged in as admin
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}
// Fetch pending patients (status = 'Unverified')
$pending_sql = "SELECT * FROM tblpatients WHERE status='Pending' ORDER BY date_registered ASC LIMIT 10";
$pending_result = mysqli_query($link, $pending_sql);
$pending_count = mysqli_num_rows($pending_result);

// Handle delete action
if(isset($_GET['delete_id'])){
    $delete_id = $_GET['delete_id'];

    // Get patient name first for logging
    $res = mysqli_query($link, "SELECT full_name FROM tblpatients WHERE patient_id='$delete_id'");
    $row = mysqli_fetch_assoc($res);
    $patient_name = $row['full_name'] ?? $delete_id;

    $stmt = mysqli_prepare($link, "DELETE FROM tblpatients WHERE patient_id = ?");
    mysqli_stmt_bind_param($stmt, "s", $delete_id);
    if(mysqli_stmt_execute($stmt)){
        $_SESSION['success_delete'] = "Patient deleted successfully!";
        // Log deletion
        logAction($link, "Delete", "Patients", $patient_name, $_SESSION['admin_name']);
    } else {
        $_SESSION['error_delete'] = "Failed to delete patient.";
    }
    mysqli_stmt_close($stmt);

    header("Location: patients.php");
    exit;
}

// Get search term and status filter
$search_name = $_GET['search_name'] ?? '';
$safe_name = mysqli_real_escape_string($link, $search_name);

$status_filter = $_GET['status_filter'] ?? ''; // '' = all
$safe_status = mysqli_real_escape_string($link, $status_filter);
$gender_filter = $_GET['gender_filter'] ?? ''; // '' = all
$safe_gender = mysqli_real_escape_string($link, $gender_filter);

$min_age = $_GET['min_age'] ?? '';
$max_age = $_GET['max_age'] ?? '';
$street_filter = $_GET['street_filter'] ?? '';
$safe_street = mysqli_real_escape_string($link, $street_filter);
$patient_status_filter = $_GET['patient_status_filter'] ?? '';
$safe_patient_status = mysqli_real_escape_string($link, $patient_status_filter);

$residency_filter = $_GET['residency_filter'] ?? '';
$safe_residency = mysqli_real_escape_string($link, $residency_filter);


// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Count total patients
$total_sql = "SELECT COUNT(*) as total FROM tblpatients WHERE 1";
if($safe_name) $total_sql .= " AND full_name LIKE '%$safe_name%'";
if($safe_patient_status) $total_sql .= " AND status='$safe_patient_status'";
if($safe_residency) $total_sql .= " AND residency_type='$safe_residency'";

if($safe_gender) $total_sql .= " AND gender='$safe_gender'";
if($min_age) $total_sql .= " AND age >= ".intval($min_age);
if($max_age) $total_sql .= " AND age <= ".intval($max_age);
if($safe_street) $total_sql .= " AND address LIKE '%$safe_street%'"; // <-- added

$total_result = mysqli_query($link, $total_sql);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch patients with LIMIT
$sql = "SELECT * FROM tblpatients WHERE 1";
if($safe_name) $sql .= " AND full_name LIKE '%$safe_name%'";
if($safe_patient_status) $sql .= " AND status='$safe_patient_status'";
if($safe_residency) $sql .= " AND residency_type='$safe_residency'";

if($safe_gender) $sql .= " AND gender='$safe_gender'";
if($min_age) $sql .= " AND age >= ".intval($min_age);
if($max_age) $sql .= " AND age <= ".intval($max_age);
if($safe_street) $sql .= " AND address LIKE '%$safe_street%'"; // <-- added
$sql .= " ORDER BY patient_id DESC LIMIT $limit OFFSET $offset";

$result = mysqli_query($link, $sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patients - Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b;}

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

/* Main content */
.main {margin-left:250px; padding:30px; flex:1;}
h1 {margin-bottom:20px; color:#001BB7; font-weight:700;}

/* Filter form */
.filter-form {margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;}
input[type=text] {padding:8px 10px; border:1px solid #ddd; border-radius:8px; flex:1;}
button.filter-btn {background:#001BB7; color:white; padding:8px 14px; border:none; border-radius:8px; cursor:pointer; display:flex; align-items:center; gap:5px;}
.add-btn {padding:8px 14px; background:#27ae60; color:white; border-radius:8px; text-decoration:none; font-weight:600; display:flex; align-items:center; gap:5px;}

/* Tables */
table {width:100%; border-collapse:collapse; background:white; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08); margin-bottom:10px;}
th, td {padding:12px; text-align:center; border-bottom:1px solid #eee;}
th {background:#001BB7; color:white; font-weight:600;}
tr:hover {background:#f1f5fb;}

/* Action buttons */
.action-btn {padding:6px 10px; border:none; border-radius:8px; cursor:pointer; font-size:14px; display:inline-flex; align-items:center; justify-content:center; margin-right:5px; transition:0.2s;}
.edit-btn {background:#27ae60; color:white;}
.delete-btn {background:#c0392b; color:white;}
.view-btn {background:#001BB7; color:white;}
.action-btn i {margin:0; font-size:16px;}

/* Pagination */
.pagination {margin-top:10px;}
.pagination .page-item.active .page-link {background-color:#001BB7; border-color:#001BB7; color:white;}
.pagination .page-link {color:#001BB7;}

/* Responsive */
@media(max-width:768px){.main{margin-left:0; padding:20px;} table th, table td {font-size:12px; padding:8px;}}

.sidebar h2 i {font-size:28px; vertical-align:middle;}
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
  padding: 10px 12px;
  cursor: pointer;
  z-index: 9999; /* VERY HIGH */
}
@media (max-width: 768px) {

  .sidebar {
    position: fixed;
  }

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
    transform: translateX(-100%);
    width: 250px;
    left: 0;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
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
.sidebar::-webkit-scrollbar{
  width:6px;
}
.sidebar::-webkit-scrollbar-thumb{
  background:rgba(255,255,255,0.35);
  border-radius:10px;
}
.sidebar::-webkit-scrollbar-track{
  background:transparent;
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
/* ===== DARK MODE FOR VIEW PATIENT MODAL ===== */
body.dark-mode .swal2-popup {
    background: #0b1220 !important;
    color: #f5f7fa !important;
}

body.dark-mode .swal2-popup .ehr-modal {
    color: #f5f7fa !important;
}

body.dark-mode .ehr-section {
    background: #111b2b !important;
    border: 1px solid rgba(255,255,255,0.18) !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.6) !important;
}

body.dark-mode .ehr-section button.ehr-toggle {
    background: #00122b !important;
    color: #fff !important;
}

body.dark-mode .ehr-section .ehr-content {
    background: #111b2b !important;
    color: #f5f7fa !important;
}

body.dark-mode .ehr-content p {
    color: #f5f7fa !important;
}

body.dark-mode .ehr-status {
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.18) !important;
}

body.dark-mode .ehr-modal .ehr-left img {
    border: 3px solid #0b6fb5 !important;
}

body.dark-mode .ehr-modal .ehr-right {
    background: transparent !important;
}

body.dark-mode .ehr-modal .ehr-left,
body.dark-mode .ehr-modal .ehr-right {
    color: #f5f7fa !important;
}
body.dark-mode .ehr-content a {
    color: #0b6fb5 !important;
    font-weight: bold;
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

<div class="sidebar">
    <h2>
    <a href="dashboard.php" style="text-decoration:none; color:white; display:flex; align-items:center; justify-content:center;">
        <img src="logo.png" alt="KNL Logo"
             style="width:45px; height:45px; margin-right:10px; border-radius:50%; object-fit:cover;">
        KNL Health Center
    </a>
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
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<!-- Overlay -->
<div id="overlay" aria-hidden="true"></div>

<div class="main">
    <?php include 'admin_header.php'; ?>
    <h1><i class="fas fa-users"></i> Patients Management</h1>
    <div style="margin-bottom: 15px; display:flex; gap:10px; flex-wrap:wrap;">
    <a href="export_patients.php?format=pdf&search_name=<?php echo urlencode($search_name); ?>&status_filter=<?php echo urlencode($status_filter); ?>&gender_filter=<?php echo urlencode($gender_filter); ?>&min_age=<?php echo urlencode($min_age); ?>&max_age=<?php echo urlencode($max_age); ?>&street_filter=<?php echo urlencode($street_filter); ?>" target="_blank" 
       class="add-btn" 
       style="background:#c0392b;"> <!-- Red PDF button -->
        <i class="fas fa-file-pdf"></i> Export PDF
    </a>
    <a href="export_patients.php?format=excel&search_name=<?php echo urlencode($search_name); ?>&status_filter=<?php echo urlencode($status_filter); ?>&gender_filter=<?php echo urlencode($gender_filter); ?>&min_age=<?php echo urlencode($min_age); ?>&max_age=<?php echo urlencode($max_age); ?>&street_filter=<?php echo urlencode($street_filter); ?>" target="_blank" 
       class="add-btn" 
       style="background:#27ae60;"> <!-- Green Excel button -->
        <i class="fas fa-file-excel"></i> Export Excel
    </a>
</div>

    <!-- Search and Filters Form -->
<form method="GET" class="filter-form">
    <input type="text" name="search_name" placeholder="Search by name" value="<?php echo htmlspecialchars($search_name); ?>">

    <!-- Filter by Verified / Unverified -->
<select name="patient_status_filter" style="padding:8px 10px; border-radius:8px; border:1px solid #ddd;">
    <option value="">All Patients</option>
    <option value="Verified" <?php if($patient_status_filter=='Verified') echo 'selected'; ?>>Verified</option>
    <option value="Unverified" <?php if($patient_status_filter=='Unverified') echo 'selected'; ?>>Unverified</option>
</select>

<!-- Filter by Residency Type -->
<select name="residency_filter" style="padding:8px 10px; border-radius:8px; border:1px solid #ddd;">
    <option value="">All Residency Types</option>
    <option value="Resident" <?php if($residency_filter=='Resident') echo 'selected'; ?>>Resident</option>
    <option value="Non-Resident" <?php if($residency_filter=='Non-Resident') echo 'selected'; ?>>Non-Resident</option>
</select>

    <select name="gender_filter" style="padding:8px 10px; border-radius:8px; border:1px solid #ddd;">
        <option value="">All Genders</option>
        <option value="Male" <?php if($gender_filter=='Male') echo 'selected'; ?>>Male</option>
        <option value="Female" <?php if($gender_filter=='Female') echo 'selected'; ?>>Female</option>
    </select>

    <input type="number" name="min_age" placeholder="Min Age" value="<?php echo htmlspecialchars($min_age); ?>" style="width:80px; padding:8px; border-radius:8px; border:1px solid #ddd;">
    <input type="number" name="max_age" placeholder="Max Age" value="<?php echo htmlspecialchars($max_age); ?>" style="width:80px; padding:8px; border-radius:8px; border:1px solid #ddd;">

    <select name="street_filter" style="padding:8px 10px; border-radius:8px; border:1px solid #ddd;">
    <option value="">All Streets</option>
    <?php 
    $streets = ["Angeles Street","Antipolo Street","B. Baluyot Street","E. Ramos Street","Emilio Jacinto Street","Eugenio Street","I. Francisco Alley","Kabalitang Street","Lieutenant J. Francisco Street","Lourdes Street","M. Dela Cruz Street","P. Fernando Street","P. Francisco Street","Plaza Hernandez Street","S. Flores Street","S. Salvador Street","S. Santos Street","Tiburcio Street","Tiburcio Street Extension","V. Francisco Street","V. Gonzales Street","V. Manansala Street"];
    foreach($streets as $street): ?>
        <option value="<?php echo htmlspecialchars($street); ?>" <?php if($street_filter == $street) echo 'selected'; ?>>
            <?php echo htmlspecialchars($street); ?>
        </option>
    <?php endforeach; ?>
</select>


    <button type="submit" class="filter-btn"><i class="fas fa-search"></i> Search</button>
</form>

<h2>Pending Patients <?php if($pending_count>0) echo "($pending_count)"; ?></h2>

<?php if($pending_count > 0): ?>
<table>
    <tr>
        <th>Profile</th>
        <th>Full Name</th>
        <th>Address</th>
        <th>Status</th>
        <th>Residency Type</th>
        <th>Color</th>
        <th>Actions</th>
    </tr>
    <?php while($row = mysqli_fetch_assoc($pending_result)): ?>
    <tr>
        <td class="text-center">
            <?php
            $profilePic = 'uploads/profile/default-avatar.png';
            if (!empty($row['profile_picture']) && file_exists('uploads/' . $row['profile_picture'])) {
                $profilePic = 'uploads/' . $row['profile_picture'];
            }
            ?>
            <img src="<?php echo htmlspecialchars($profilePic); ?>" width="40" height="40" style="border-radius:50%; object-fit:cover;">
        </td>
        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
        <td><?php echo htmlspecialchars($row['address']); ?></td>
        <td><?php echo htmlspecialchars($row['status']); ?></td>
        <td><?php echo htmlspecialchars($row['residency_type']); ?></td>
        <td style="text-align:center;">
            <div style="width:20px; height:20px; background:<?php echo htmlspecialchars($row['color_code']); ?>; border:1px solid #ddd; margin:auto;"></div>
        </td>
        <td>
            <!-- View Modal -->
            <button type="button" class="action-btn view-btn" 
                onclick="viewPatient(
                    '<?php echo htmlspecialchars($row['patient_id']); ?>',
                    '<?php echo htmlspecialchars($row['username']); ?>',
                    '<?php echo htmlspecialchars($row['password']); ?>',
                    '<?php echo htmlspecialchars($row['full_name']); ?>',
                    '<?php echo htmlspecialchars($row['address']); ?>',
                    '<?php echo htmlspecialchars($row['color_code']); ?>',
                    '<?php echo htmlspecialchars($row['birthday']); ?>',
                    '<?php echo htmlspecialchars($row['age']); ?>',
                    '<?php echo htmlspecialchars($row['contact_number']); ?>',
                    '<?php echo htmlspecialchars($row['email']); ?>',
                    '<?php echo htmlspecialchars($row['gender']); ?>',
                    '<?php echo htmlspecialchars($row['status']); ?>',
                    '<?php echo htmlspecialchars($row['residency_type']); ?>',
                    '<?php echo htmlspecialchars($row['date_registered']); ?>',
                    '<?php echo htmlspecialchars($row['profile_picture']); ?>',
                    '<?php echo htmlspecialchars($row['valid_id']); ?>',
                    '<?php echo htmlspecialchars($row['id_type']); ?>',
                    '<?php echo htmlspecialchars($row['guardian_name']); ?>',
                    '<?php echo htmlspecialchars($row['guardian_contact']); ?>'
                )">
                <i class="fas fa-eye"></i>
            </button>

            <!-- Verify (Check) -->
            <a href="#" class="action-btn verify-btn" data-id="<?php echo $row['patient_id']; ?>" data-action="verify" style="background:#27ae60; color:white;" title="Mark as Verified">
                <i class="fas fa-check"></i>
            </a>

            <!-- Unverify / Remove (X) -->
            <a href="#" class="action-btn verify-btn" data-id="<?php echo $row['patient_id']; ?>" data-action="unverify" style="background:#c0392b; color:white;" title="Mark as Unverified">
                <i class="fas fa-times"></i>
            </a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
<?php else: ?>
<p style="text-align:center; font-style:italic;">No pending patients at the moment.</p>
<?php endif; ?>

    <!-- Single Patients Table -->
    <h2>Patients</h2>
<table>
    <tr>
        <th>Profile</th> <!-- bagong column -->
        <th>Full Name</th>
        <th>Address</th>
        <th>Status</th>
<th>Residency Type</th>
<th>Color</th>

        <th>Actions</th>
    </tr>
    <?php if(mysqli_num_rows($result) > 0): ?>
        <?php while($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td class="text-center">
                <?php
                // Default avatar
                $profilePic = 'uploads/profile/default-avatar.png';
                if (!empty($row['profile_picture']) && file_exists('uploads/' . $row['profile_picture'])) {
                    $profilePic = 'uploads/' . $row['profile_picture'];
                }
                ?>
                <img src="<?php echo htmlspecialchars($profilePic); ?>" 
                     alt="Profile Picture" width="40" height="40" 
                     style="border-radius:50%; object-fit:cover;">
            </td>
            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
            <td><?php echo htmlspecialchars($row['address']); ?></td>
            <td><?php echo htmlspecialchars($row['status']); ?></td>
<td><?php echo htmlspecialchars($row['residency_type']); ?></td>
<td style="text-align:center;">
    <div style="width:20px; height:20px; background:<?php echo htmlspecialchars($row['color_code']); ?>; border:1px solid #ddd; margin:auto;"></div>
</td>

            <td>
                <button type="button" class="action-btn view-btn" 
    onclick="viewPatient(
        '<?php echo htmlspecialchars($row['patient_id']); ?>',
        '<?php echo htmlspecialchars($row['username']); ?>',
        '<?php echo htmlspecialchars($row['password']); ?>',
        '<?php echo htmlspecialchars($row['full_name']); ?>',
        '<?php echo htmlspecialchars($row['address']); ?>',
        '<?php echo htmlspecialchars($row['color_code']); ?>',
        '<?php echo htmlspecialchars($row['birthday']); ?>',
        '<?php echo htmlspecialchars($row['age']); ?>',
        '<?php echo htmlspecialchars($row['contact_number']); ?>',
        '<?php echo htmlspecialchars($row['email']); ?>',
        '<?php echo htmlspecialchars($row['gender']); ?>',
        '<?php echo htmlspecialchars($row['status']); ?>',
        '<?php echo htmlspecialchars($row['residency_type']); ?>',
        '<?php echo htmlspecialchars($row['date_registered']); ?>',
        '<?php echo htmlspecialchars($row['profile_picture']); ?>',
        '<?php echo htmlspecialchars($row['valid_id']); ?>',
        '<?php echo htmlspecialchars($row['id_type']); ?>',
        '<?php echo htmlspecialchars($row['guardian_name']); ?>',
        '<?php echo htmlspecialchars($row['guardian_contact']); ?>'
    )">
    <i class="fas fa-eye"></i>
</button>

                <a href="edit_patient.php?id=<?php echo urlencode($row['patient_id']); ?>" class="action-btn edit-btn"><i class="fas fa-edit"></i></a>
                <button type="button" class="action-btn delete-btn" onclick="confirmDelete('<?php echo $row['patient_id']; ?>')"><i class="fas fa-trash-alt"></i></button>
                <a href="patient_history_admin.php?id=<?php echo urlencode($row['patient_id']); ?>" 
   class="action-btn" 
   style="background:#6c63ff; color:white;">
   <i class="fas fa-notes-medical"></i>
</a>
<a href="#" class="action-btn verify-btn" data-id="<?php echo $row['patient_id']; ?>" data-action="verify" style="background:#27ae60; color:white;" title="Mark as Verified">
    <i class="fas fa-check"></i>
</a>

<a href="#" class="action-btn verify-btn" data-id="<?php echo $row['patient_id']; ?>" data-action="unverify" style="background:#c0392b; color:white;" title="Mark as Unverified">
    <i class="fas fa-times"></i>
</a>

            </td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="6" style="text-align:center;">No patients found.</td></tr>
    <?php endif; ?>
</table>


    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <nav aria-label="Patients Pagination">
    <ul class="pagination justify-content-end">
        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
            <a class="page-link" href="?search_name=<?php echo urlencode($search_name); ?>&status_filter=<?php echo urlencode($status_filter); ?>&gender_filter=<?php echo urlencode($gender_filter); ?>&min_age=<?php echo urlencode($min_age); ?>&max_age=<?php echo urlencode($max_age); ?>&page=<?php echo max(1, $page-1); ?>">Previous</a>
        </li>
        <li class="page-item active">
            <span class="page-link"><?php echo $page; ?></span>
        </li>
        <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
            <a class="page-link" href="?search_name=<?php echo urlencode($search_name); ?>&status_filter=<?php echo urlencode($status_filter); ?>&gender_filter=<?php echo urlencode($gender_filter); ?>&min_age=<?php echo urlencode($min_age); ?>&max_age=<?php echo urlencode($max_age); ?>&page=<?php echo min($total_pages, $page+1); ?>">Next</a>
        </li>
    </ul>
</nav>

    <?php endif; ?>

<script>
// Confirm delete
function confirmDelete(id){
    Swal.fire({
        title: 'Are you sure?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#c0392b',
        cancelButtonColor: '#001BB7',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if(result.isConfirmed){
            window.location.href = 'patients.php?delete_id=' + encodeURIComponent(id);
        }
    });
}
function viewPatient(patientId, username, password, fullName, address, colorCode, birthday, age, contact, email, gender, status, residencyType, dateRegistered, profilePicture, validId, idType, guardianName, guardianContact) {

    let imgPath = profilePicture && profilePicture.trim() !== "" 
        ? "uploads/" + profilePicture 
        : "uploads/profile/default-avatar.png";

    let idPath = validId && validId.trim() !== ""
        ? "uploads/" + validId
        : null;

    let guardianSection = "";
    if(age && parseInt(age) < 18) {
        guardianSection = `
        <div class="ehr-section">
            <button class="ehr-toggle"><i class="fas fa-user-friends" style="color:#001BB7; margin-right:5px;"></i> Guardian Information</button>
            <div class="ehr-content">
                <p><strong>Name:</strong> ${guardianName || '-'}</p>
                <p><strong>Contact:</strong> ${guardianContact || '-'}</p>
            </div>
        </div>`;
    }

    let idSection = idPath ? `
    <div class="ehr-section">
        <button class="ehr-toggle"><i class="fas fa-id-badge" style="color:#001BB7; margin-right:5px;"></i> Valid ID</button>
        <div class="ehr-content">
            <p><strong>ID Type:</strong> ${idType}</p>
            <p><strong>View ID:</strong> <a href="${idPath}" target="_blank" style="color:#001BB7; font-weight:bold;"><i class="fas fa-external-link-alt" style="margin-right:5px;"></i>View ID</a></p>
        </div>
    </div>` : '';

    Swal.fire({
        width: '90%',
        maxWidth: '900px',
        html: `
        <style>
    .ehr-modal {display:flex; flex-direction:column; font-family:Arial, sans-serif; color:#333; gap:20px;}
    @media(min-width:768px){ .ehr-modal{flex-direction:row;} }

    .ehr-left {flex:0 0 180px; text-align:left;} /* Changed to left */
    .ehr-left img {border-radius:50%; object-fit:cover; border:3px solid #001BB7; margin-bottom:10px;}
    .ehr-status {margin-top:10px; display:inline-block; padding:5px 10px; border-radius:8px; color:white; font-weight:600;}

    .ehr-right {flex:1; display:flex; flex-direction:column; gap:15px; text-align:left;} /* Ensure left-aligned */

    .ehr-section {border:1px solid #ddd; border-radius:10px; overflow:hidden; background:#fdfdfd; box-shadow:0 2px 6px rgba(0,0,0,0.08);}
    .ehr-section button.ehr-toggle {width:100%; text-align:left; background:#001BB7; color:white; padding:10px 15px; font-size:1rem; border:none; cursor:pointer; display:flex; align-items:center; gap:8px;}
    .ehr-section .ehr-content {padding:10px 15px; display:none; flex-direction:column; gap:5px; text-align:left;} /* Ensure content left-aligned */
</style>

        <div class="ehr-modal">
            <!-- Left Column: Profile -->
            <div class="ehr-left">
                <img src="${imgPath}" width="140" height="140" onerror="this.onerror=null; this.src='uploads/profile/default-avatar.png';">
                <div class="ehr-status" style="background:${status === 'Verified' ? '#27ae60' : '#c0392b'};">${status}</div>
            </div>

            <!-- Right Column: Info -->
            <div class="ehr-right">

                <!-- Full Name (larger, left-aligned) -->
                <div style="font-size:1.8rem; font-weight:bold; margin-bottom:10px; text-align:left;">
                    ${fullName}
                </div>

                <div class="ehr-section">
                    <button class="ehr-toggle"><i class="fas fa-id-card" style="color:#fff; margin-right:5px;"></i> Personal Information</button>
                    <div class="ehr-content">
                        <p><strong>Patient ID:</strong> ${patientId}</p>
                        <p><strong>Username:</strong> ${username}</p>
                        <p><strong>Password:</strong> ${password ? '••••••••' : ''}</p>
                        <p><strong>Birthday:</strong> ${birthday}</p>
                        <p><strong>Age:</strong> ${age} years old</p>
                        <p><strong>Gender:</strong> ${gender}</p>
                    </div>
                </div>

                <div class="ehr-section">
                    <button class="ehr-toggle"><i class="fas fa-phone-alt" style="color:#fff; margin-right:5px;"></i> Contact & Residency</button>
                    <div class="ehr-content">
                        <p>
    <i class="fas fa-map-marker-alt" style="color:#001BB7;"></i> 
    <strong>Address:</strong> ${address} 
    <span style="display:inline-block; width:24px; height:24px; background:${colorCode}; border-radius:50%; margin-left:5px; border:1px solid #ddd; vertical-align: middle;"></span>
</p>

                        <p><i class="fas fa-envelope" style="color:#001BB7;"></i> <strong>Email:</strong> ${email}</p>
                        <p><i class="fas fa-phone" style="color:#001BB7;"></i> <strong>Contact Number:</strong> ${contact}</p>
                        <p><i class="fas fa-home" style="color:#001BB7;"></i> <strong>Residency Type:</strong> ${residencyType}</p>
                    </div>
                </div>

                ${guardianSection}
                ${idSection}

                <div style="text-align:right; font-size:0.85rem; color:#666;">Registered on: ${dateRegistered}</div>
            </div>
        </div>
        `,
        showCloseButton: true,
        showConfirmButton: false,
        didOpen: () => {
            document.querySelectorAll('.ehr-toggle').forEach(btn => {
                btn.addEventListener('click', function(){
                    const content = this.nextElementSibling;
                    content.style.display = content.style.display === 'flex' ? 'none' : 'flex';
                });
            });
        }
    });
}

// SweetAlert notifications
<?php if(isset($_SESSION['success_update'])): ?>
Swal.fire({ icon:'success', title:'Updated!', text:'<?php echo $_SESSION['success_update']; ?>', timer:2000, showConfirmButton:false });
<?php unset($_SESSION['success_update']); endif; ?>

<?php if(isset($_SESSION['success_delete'])): ?>
Swal.fire({ icon:'success', title:'Deleted!', text:'<?php echo $_SESSION['success_delete']; ?>', timer:2000, showConfirmButton:false });
<?php unset($_SESSION['success_delete']); endif; ?>

<?php if(isset($_SESSION['error_delete'])): ?>
Swal.fire({ icon:'error', title:'Error!', text:'<?php echo $_SESSION['error_delete']; ?>', timer:2000, showConfirmButton:false });
<?php unset($_SESSION['error_delete']); endif; ?>

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

// Confirm Verify / Unverify
document.querySelectorAll('.verify-btn').forEach(btn => {
    btn.addEventListener('click', function(e){
        e.preventDefault();
        const patientId = this.getAttribute('data-id');
        const action = this.getAttribute('data-action'); // "verify" or "unverify"
        const actionText = action === 'verify' ? 'Verified' : 'Unverified';
        const actionColor = action === 'verify' ? '#27ae60' : '#c0392b';

        Swal.fire({
            title: `Are you sure?`,
            text: `You are about to mark this patient as ${actionText}.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: actionColor,
            cancelButtonColor: '#001BB7',
            confirmButtonText: `Yes, mark as ${actionText}`,
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if(result.isConfirmed){
                window.location.href = `patients.php?${action}_id=${patientId}`;
            }
        });
    });
});

</script>

</div>
</body>
</html>
