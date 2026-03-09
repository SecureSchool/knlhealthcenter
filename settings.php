<?php
session_start();
require_once "config.php";
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

// Redirect if not admin
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$error = '';
$success = '';

// Fetch admin info
$sql = "SELECT * FROM tbladmin WHERE admin_id='$admin_id' LIMIT 1";
$result = mysqli_query($link, $sql);
$admin = mysqli_fetch_assoc($result);
if(isset($_POST['update_profile'])){
    $full_name = mysqli_real_escape_string($link, $_POST['full_name']);
    $email = mysqli_real_escape_string($link, $_POST['email']);
// Handle profile picture upload
if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0){
    $file_name = $_FILES['profile_picture']['name'];
    $file_tmp = $_FILES['profile_picture']['tmp_name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif'];

    if(in_array($file_ext, $allowed)){
        $new_name = 'profile_'.$admin_id.'_'.time().'.'.$file_ext;
        $upload_path = 'uploads/'.$new_name; // make sure 'uploads' folder exists
        if(move_uploaded_file($file_tmp, $upload_path)){
            $update_sql = "UPDATE tbladmin SET profile_picture='$new_name' WHERE admin_id='$admin_id'";
            mysqli_query($link, $update_sql);
            $admin['profile_picture'] = $new_name; // Update current variable
        } else {
            $error = "Failed to upload profile picture.";
        }
    } else {
        $error = "Invalid file type. Only JPG, PNG, GIF allowed.";
    }
}

    if(empty($full_name)){
        $error = "Full name cannot be empty.";
    } else {
        $update_sql = "UPDATE tbladmin SET full_name='$full_name', email='$email' WHERE admin_id='$admin_id'";
        if(mysqli_query($link, $update_sql)){
            $success = "Profile updated successfully!";
            $admin = mysqli_fetch_assoc(mysqli_query($link, $sql));

            // Log profile update
            logAction($link, "Update Profile", "Admin Settings", $full_name, $_SESSION['admin_name']);

            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            <script>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '$success',
                confirmButtonText: 'OK'
            });
            </script>";
        } else {
            $error = "Database error: " . mysqli_error($link);
        }
    }
}

// Handle password change
if(isset($_POST['change_password'])){
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    $username = mysqli_real_escape_string($link, $_POST['username']);

    // Verify current password
    if($current_pass !== $admin['password']){
        $error = "Current password is incorrect.";
    } elseif($new_pass !== $confirm_pass){
        $error = "New password and confirm password do not match.";
    } elseif(empty($new_pass)){
        $error = "New password cannot be empty.";
    } else {
        $update_sql = "UPDATE tbladmin SET username='$username', password='$new_pass' WHERE admin_id='$admin_id'";
        if(mysqli_query($link, $update_sql)){
            $success = "Username and password updated successfully!";
            
            // Update session if needed
            $_SESSION['admin_name'] = $username;
            $admin['username'] = $username;
            $admin['password'] = $new_pass;

            // Log the update
            logAction($link, "Change Username & Password", "Admin Settings", $admin['full_name'], $_SESSION['admin_name']);

            echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '$success',
                confirmButtonText: 'OK'
            });
            </script>";
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
<title>Settings - Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
.sidebar a i {margin-right:12px; font-size:18px;}
.sidebar a:hover {background:rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active {background:rgba(255,255,255,0.25); font-weight:600;}

/* Main content */
.main {margin-left:250px; padding:30px; flex:1;}
h1 {margin-bottom:20px; color:#001BB7; font-weight:700;}

/* Containers for forms */
.container {
    background:#fff;
    padding:25px;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    margin-bottom:30px;
}
.container h2 {margin-bottom:15px; color:#001BB7;}
label {display:block; margin-top:10px; font-weight:500;}
input {width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:10px;}
button {
    margin-top:10px;
    padding:10px 16px;
    background:#001BB7;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:500;
    transition:0.3s;
}
button:hover {background:#001199;}
.error {color:#d63031; margin-top:10px;}
.success {color:#00b894; margin-top:10px;}

/* Responsive */


.sidebar h2 i {
    font-size:28px;
    vertical-align:middle;
}
.sidebar h2 {
    font-size:24px;
    font-weight:700;
    text-align:center;
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

  .hamburger {
    display: block;
  }

  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100%;
    transform: translateX(-250px); /* MUST MATCH WIDTH */
    transition: transform .3s ease;
    z-index: 1100;
  }

  .sidebar.active {
    transform: translateX(0);
  }

  .main {
    margin-left: 0;
    padding: 20px;
  }
}

/* ============================
   DARK MODE STYLES (SETTINGS)
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

/* Header (profile dropdown + dark mode button) */
body.dark-mode .header-container {
    background: linear-gradient(90deg, #0f1a38, #00122b) !important;
    color: #fff !important;
}

/* Cards / Containers */
body.dark-mode .container {
    background: #111b2b !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.6) !important;
}

/* Text */
body.dark-mode h1,
body.dark-mode h2,
body.dark-mode label,
body.dark-mode p,
body.dark-mode small,
body.dark-mode input,
body.dark-mode textarea,
body.dark-mode select,
body.dark-mode td,
body.dark-mode th {
    color: #f5f7fa !important;
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
body.dark-mode button,
body.dark-mode .btn {
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.25) !important;
    background: #0b1430 !important;
}
body.dark-mode button:hover {
    background: #0b1220 !important;
}

/* Profile Picture Border */
body.dark-mode .container img {
    border: 2px solid #0b1430 !important;
}

/* Error / Success messages */
body.dark-mode .error {
    color: #ff6b6b !important;
}
body.dark-mode .success {
    color: #34d399 !important;
}

/* Hamburger button */
body.dark-mode .hamburger {
    background: #0b1430 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.25) !important;
}

/* Overlay */
body.dark-mode #overlay {
    background: rgba(0,0,0,0.6) !important;
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
#sendReminderBtn{
    background: linear-gradient(45deg,#001BB7,#4f7cff);
    border:none;
    color:white;
}
#sendReminderBtn:hover{
    background: linear-gradient(45deg,#001199,#3b5bff);
}
/* ===== CLEAN SIDEBAR SYSTEM ===== */

.sidebar{
  transition: transform .3s ease;
}

/* Mobile Sidebar */
@media (max-width:768px){

  .hamburger{
    display:block;
  }

  .sidebar{
    position:fixed;
    top:0;
    left:0;
    width:250px;
    height:100%;
    transform:translateX(-100%);
    z-index:1100;
  }

  .sidebar.active{
    transform:translateX(0);
  }

  .main{
    margin-left:0;
  }

}
.sidebar{
  overflow-y:auto;
  overflow-x:hidden;
  scrollbar-width:thin; /* Firefox */
}
.sidebar::-webkit-scrollbar{
  width:6px;
}
.sidebar::-webkit-scrollbar-thumb{
  background:#ffffff55;
  border-radius:10px;
}
.sidebar::-webkit-scrollbar-track{
  background:transparent;
}
@media (max-width:768px){
  .sidebar{
    height:100vh;
    overflow-y:auto;
  }
}
.toggle-pass{
    position:absolute;
    right:12px;
    top:50%;
    transform:translateY(-50%);
    cursor:pointer;
    color:#64748b;
}
.toggle-pass:hover{
    color:#001BB7;
}
/* Remove default browser eye (prevents double icon) */
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear {
    display: none;
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
    <a href="patients.php"><i class="fas fa-users"></i> Patients</a>
    <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a>
    <a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a>
    <a href="services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="announcement.php"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<!-- Overlay -->
<div id="overlay" aria-hidden="true"></div>

<div class="main">
    <?php include 'admin_header.php'; ?>
    <h1><i class="fas fa-cog me-2"></i> Admin Settings</h1>


    <?php if($error) echo "<p class='error'>$error</p>"; ?>

    <div class="container">
    <h2>Update Profile</h2>

    <!-- Current Profile Picture -->
    <div style="text-align:center; margin-bottom:15px;">
        <img src="uploads/<?php echo htmlspecialchars($admin['profile_picture'] ?? 'default.png'); ?>" 
             alt="Profile Picture" style="width:120px; height:120px; border-radius:50%; object-fit:cover; border:2px solid #001BB7;">
    </div>

    <form method="POST" enctype="multipart/form-data">
        <label>Profile Picture</label>
        <input type="file" name="profile_picture" accept="image/*">

        <label>Full Name</label>
        <input type="text" name="full_name" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>">

        <button type="submit" name="update_profile"><i class="fas fa-save"></i> Update Profile</button>
    </form>
</div>

    <div class="container">
    <h2>Change Username & Password</h2>
    <form method="POST">
        <label>Current Password</label>
<div style="position:relative;">
    <input type="password" name="current_password" id="current_password" required>
    <i class="fas fa-eye toggle-pass" data-target="current_password"></i>
</div>

<label>New Password</label>
<div style="position:relative;">
    <input type="password" name="new_password" id="new_password" required>
    <i class="fas fa-eye toggle-pass" data-target="new_password"></i>
</div>

<label>Confirm New Password</label>
<div style="position:relative;">
    <input type="password" name="confirm_password" id="confirm_password" required>
    <i class="fas fa-eye toggle-pass" data-target="confirm_password"></i>
</div>

        <button type="submit" name="change_password"><i class="fas fa-key"></i> Update</button>
    </form>
</div>
</div>
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
  if (sidebar.classList.contains('active')) closeMenu();
  else openMenu();
});

overlay.addEventListener('click', closeMenu);

document.querySelectorAll('.sidebar a').forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 768) closeMenu();
  });
});

</script>
<script>
document.querySelectorAll('.toggle-pass').forEach(icon=>{
    icon.addEventListener('click',()=>{
        const input = document.getElementById(icon.dataset.target);
        if(input.type === "password"){
            input.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            input.type = "password";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    });
});
</script>
</body>
</html>
