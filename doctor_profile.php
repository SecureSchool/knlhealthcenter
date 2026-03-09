<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['doctor_id'];

// Fetch doctor info
$doctor_result = mysqli_query($link, "SELECT * FROM tbldoctors WHERE doctor_id='$doctor_id'");
$doctor = mysqli_fetch_assoc($doctor_result);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = mysqli_real_escape_string($link, $_POST['fullname']);
    $specialization = mysqli_real_escape_string($link, $_POST['specialization']);
    $email = mysqli_real_escape_string($link, $_POST['email']);
    $contact_number = mysqli_real_escape_string($link, $_POST['contact_number']);
    $username = mysqli_real_escape_string($link, $_POST['username']); // NEW

    $password_sql = ""; // default empty
    $error = '';

    // Check username uniqueness
    $check_user = mysqli_query($link, "SELECT doctor_id FROM tbldoctors WHERE username='$username' AND doctor_id != '$doctor_id'");
    if(mysqli_num_rows($check_user) > 0){
        $error = "Username already taken!";
    }

    // Password change (optional)
    if (!$error && !empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password === $confirm_password) {
            // save plain text
            $password_sql = ", password='$new_password'";
        } else {
            $error = "Passwords do not match!";
        }
    }

    // Handle profile picture
    $profile_pic = $doctor['profile_pic']; // keep old if no new upload
    if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0){
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $new_name = 'doctor_'.$doctor_id.'_'.time().'.'.$ext;
        $upload_dir = 'uploads/doctors/';
        if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        if(move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir.$new_name)){
            $profile_pic = $new_name;
        }
    }

    if (!$error) {
        $update_sql = "
        UPDATE tbldoctors SET
            fullname='$fullname',
            specialization='$specialization',
            email='$email',
            contact_number='$contact_number',
            username='$username',
            profile_pic='$profile_pic'
            $password_sql
        WHERE doctor_id='$doctor_id'
        ";

        if (mysqli_query($link, $update_sql)) {
            $success = "Profile updated successfully!";
            // refresh doctor info
            $doctor_result = mysqli_query($link, "SELECT * FROM tbldoctors WHERE doctor_id='$doctor_id'");
            $doctor = mysqli_fetch_assoc($doctor_result);
        } else {
            $error = "Failed to update profile: " . mysqli_error($link);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Profile</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b;}

/* Sidebar - dashboard style */
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
.sidebar a:hover {
    background:rgba(255,255,255,0.15);
    padding-left:24px;
    color:#fff;
}
.sidebar a.active {
    background:rgba(255,255,255,0.25);
    font-weight:600;
}
.sidebar h2 i {
    font-size:28px;
    vertical-align:middle;
}
/* Main content */
.main {
    margin-left:250px;
    padding:30px;
    flex:1;
}
h1 {
    margin-bottom:20px;
    color:#001BB7;
    font-weight:700;
    text-align:center;
}
label {
    display:block;
    margin-top:15px;
    font-weight:600;
    color:#334155;
}
input, textarea, select {
    width:100%;
    padding:12px 14px;
    margin-top:6px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    font-size:15px;
    background:#f8fafc;
    transition:0.2s;
}
input:focus, textarea:focus, select:focus {
    outline:none;
    border-color: #001BB7;
    background:#fff;
    box-shadow:0 0 0 3px rgba(0,27,183,0.1);
}
button {
    margin-top:25px;
    padding:14px;
    background:#001BB7;
    color:#fff;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-size:16px;
    font-weight:600;
    letter-spacing:0.5px;
    transition:0.3s;
}

button i {margin-right:8px;}
button:hover {background:#000FA0;}
.card {
    background: #fff;
    padding: 35px;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.08);
    width: 100%;           /* full width of .main */
    box-sizing: border-box; /* include padding in width */
    margin-bottom: 20px;    /* spacing below card */
}

@media(max-width:768px){
    .main{margin-left:0; padding:20px;}
}
/* HAMBURGER */
.hamburger {
  display: none;
  position: fixed;
  top: 15px;
  left: 15px;
  font-size: 22px;
  color: #001BB7;
  background: #fff;
  border: none;
  border-radius: 8px;
  padding: 8px;
  cursor: pointer;
  z-index: 1200;
  width: auto;       /* para hindi 100% */
  height: auto;      /* maliit lang */
  line-height: 1;    /* compact */
}

/* OVERLAY */
#overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.25s ease, visibility 0.25s;
  z-index: 1050;
}
#overlay.active { opacity: 1; visibility: visible; }

/* MOBILE / TABLET */
@media (max-width: 768px) {
  .hamburger { display: block; }
  .sidebar {
    transform: translateX(-280px);
    transition: transform 0.3s ease;
    z-index: 1100;
  }
  .sidebar.active { transform: translateX(0); }
  .main { margin-left: 0; padding: 20px; }
}
.profile-pic-wrapper {
    display: flex;
    justify-content: center;
    margin-bottom: 15px;
}

.profile-pic-wrapper img {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #e2e8f0; /* light gray border */
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
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
/* Password wrapper */
.password-wrapper {
    position: relative;
}

.password-wrapper input {
    padding-right: 45px; /* space for icon */
}

/* Eye icon */
.password-wrapper .toggle-password {
    position: absolute;
    top: 50%;
    right: 15px;
    transform: translateY(-50%);
    cursor: pointer;
    color: #64748b;
    font-size: 16px;
    transition: 0.2s;
}

.password-wrapper .toggle-password:hover {
    color: #001BB7;
}

/* Remove default browser eye (prevents double icon) */
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear {
    display: none;
}
</style>
</head>
<body>
<!-- Hamburger (mobile) -->
<button class="hamburger" aria-label="Open navigation menu" aria-expanded="false">
  <i class="fas fa-bars"></i>
</button>

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
    <a href="doctor_profile.php" class="active"><i class="fas fa-user-md"></i> Profile</a>
    <a href="doctor_patients.php"><i class="fas fa-users"></i> My Patients</a>
    <a href="doctor_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="doctor_schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a>
    <a href="doctor_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="doctor_reports.php"><i class="fas fa-chart-line"></i> Reports </a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<!-- Overlay (dim background when menu open) -->
<div id="overlay" aria-hidden="true"></div>

<!-- Main -->
<div class="main">
    <?php include 'doctor_header.php'; ?>
    <div class="card">
        <h1><i class="fas fa-user-cog"></i> Edit Profile</h1>
        <form id="profileForm" method="POST" enctype="multipart/form-data">
    <!-- Current Profile Picture -->
    <label>Profile Picture</label>
<div class="profile-pic-wrapper">
    <img src="uploads/doctors/<?php echo htmlspecialchars($doctor['profile_pic'] ?? 'default-avatar.png'); ?>" 
         alt="Profile Picture">
</div>
<input type="file" name="profile_pic" accept="image/*">


    <label>Full Name</label>
    <input type="text" name="fullname" value="<?php echo htmlspecialchars($doctor['fullname'] ?? ''); ?>" required>

    <label>Specialization</label>
    <input type="text" name="specialization" value="<?php echo htmlspecialchars($doctor['specialization'] ?? ''); ?>" required>

    <label>Email</label>
    <input type="email" name="email" value="<?php echo htmlspecialchars($doctor['email'] ?? ''); ?>" required>

    <label>Contact Number</label>
    <input type="text" name="contact_number" value="<?php echo htmlspecialchars($doctor['contact_number'] ?? ''); ?>">

    <hr style="margin:25px 0;">

<h5 style="color:#001BB7; font-weight:700; text-align:center;">
                <i class="fas fa-key"></i> Change Password or Username
            </h5>
<label>Username</label>
<input type="text" name="username" value="<?php echo htmlspecialchars($doctor['username'] ?? ''); ?>" required>
<label>New Password</label>
<div class="password-wrapper">
    <input type="password" name="new_password" id="new_password" placeholder="Enter new password">
    <i class="fas fa-eye toggle-password" data-target="new_password"></i>
</div>

<label>Confirm Password</label>
<div class="password-wrapper">
    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password">
    <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
</div>
    <button type="submit"><i class="fas fa-save"></i> Update Profile</button>
</form>

    </div>
</div>

<?php if ($success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?php echo $success; ?>',
    confirmButtonColor: '#001BB7' // ← your system theme color
});
</script>
<?php endif; ?>
<?php if ($error): ?>
<script>
Swal.fire({icon:'error',title:'Error',text:'<?php echo $error; ?>'});
</script>
<?php endif; ?>

<!-- Scripts -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script>
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

// Profile update confirmation
document.getElementById('profileForm').addEventListener('submit', function(e){
    e.preventDefault();
    Swal.fire({
        title: 'Are you sure?',
        text: "Do you want to save these changes?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#001BB7',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update it',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            this.submit(); // submit form if confirmed
        }
    });
});
</script>
<script>
// Elements
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

// Toggle on hamburger click
hamburger.addEventListener('click', () => {
  if (sidebar.classList.contains('active')) closeMenu();
  else openMenu();
});

// Close by clicking overlay
overlay.addEventListener('click', closeMenu);

// Close after clicking any sidebar link (mobile-friendly)
document.querySelectorAll('.sidebar a').forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 768) closeMenu();
  });
});

// Optional: close on ESC key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeMenu();
});

document.getElementById('profileForm').addEventListener('submit', function(e){
    e.preventDefault();

    let newPass = this.querySelector('[name="new_password"]').value;
    let confirmPass = this.querySelector('[name="confirm_password"]').value;

    if(newPass && confirmPass && newPass !== confirmPass){
        Swal.fire({icon:'error',title:'Oops',text:'Passwords do not match!'});
        return;
    }

    Swal.fire({
        title: 'Are you sure?',
        text: "Do you want to save these changes?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#001BB7',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update it',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            this.submit();
        }
    });
});

// Show / Hide Password Toggle
document.querySelectorAll('.toggle-password').forEach(icon => {
    icon.addEventListener('click', function(){
        const input = document.getElementById(this.dataset.target);

        if(input.type === "password"){
            input.type = "text";
            this.classList.remove("fa-eye");
            this.classList.add("fa-eye-slash");
        } else {
            input.type = "password";
            this.classList.remove("fa-eye-slash");
            this.classList.add("fa-eye");
        }
    });
});
</script>

</body>
</html>
