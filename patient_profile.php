<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit;
}

$patient_id = $_SESSION['patient_id'];

// Fetch patient info
$stmt = $link->prepare("SELECT * FROM tblpatients WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

$success = '';
$error = '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Initialize update arrays
    $updates = [];
    $params = [];
    $types = "";

    // =========================
    // PASSWORD (PLAIN TEXT)
    // =========================
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    if ($new_password !== '' || $confirm_password !== '') {
        if ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            $updates[] = "password = ?";
            $params[] = $new_password;
            $types .= "s";
        }
    }

    // =========================
    // USERNAME
    // =========================
    $username = trim($_POST['username'] ?? '');
    if ($username !== '' && $username !== $patient['username']) {
        // Check if username already exists
        $stmt_check = $link->prepare("SELECT patient_id FROM tblpatients WHERE username = ? AND patient_id != ?");
        $stmt_check->bind_param("si", $username, $patient_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            $error = "Username already taken.";
        } else {
            $updates[] = "username = ?";
            $params[] = $username;
            $types .= "s";
        }
    }

    // =========================
    // OTHER FIELDS
    // =========================
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $birthday = trim($_POST['birthday'] ?? '');
    $gender = trim($_POST['gender'] ?? '');

    if ($full_name !== '') { $updates[] = "full_name=?"; $params[] = $full_name; $types .= "s"; }
    if ($email !== '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = "Invalid email format."; }
        else { $updates[] = "email=?"; $params[] = $email; $types .= "s"; }
    }
    if ($contact_number !== '') { $updates[] = "contact_number=?"; $params[] = $contact_number; $types .= "s"; }
    if ($address !== '') { $updates[] = "address=?"; $params[] = $address; $types .= "s"; }
    if ($birthday !== '') { $updates[] = "birthday=?"; $params[] = $birthday; $types .= "s"; }
    if ($gender !== '') { $updates[] = "gender=?"; $params[] = $gender; $types .= "s"; }

    // =========================
    // PROFILE PICTURE UPLOAD
    // =========================
    $profile_picture = $patient['profile_picture'];

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {

        $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
        $fileName = $_FILES['profile_picture']['name'];
        $fileSize = $_FILES['profile_picture']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($fileExtension, $allowedExtensions)) {
            $error = "Invalid file type. Allowed: jpg, jpeg, png, gif.";
        }

        if ($fileSize > 2 * 1024 * 1024) {
            $error = "File size must be below 2MB.";
        }

        if (!$error) {
            $newFileName = uniqid('patient_', true) . '.' . $fileExtension;
            $uploadDir = 'uploads/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {

                // Delete old file
                if ($patient['profile_picture'] && $patient['profile_picture'] !== 'default-avatar.png') {
                    $oldPath = $uploadDir . $patient['profile_picture'];
                    if (file_exists($oldPath)) unlink($oldPath);
                }

                $profile_picture = $newFileName;
                $updates[] = "profile_picture = ?";
                $params[] = $profile_picture;
                $types .= "s";

            } else {
                $error = "Error uploading file.";
            }
        }
    }

    // =========================
    // EXECUTE UPDATE
    // =========================
    if (!$error) {
        if (!empty($updates)) {

            $sql = "UPDATE tblpatients SET " . implode(", ", $updates) . " WHERE patient_id = ?";
            $params[] = $patient_id;
            $types .= "i";

            $stmt = $link->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $success = "Profile updated successfully!";

                // Refresh data
                $stmt = $link->prepare("SELECT * FROM tblpatients WHERE patient_id = ?");
                $stmt->bind_param("i", $patient_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $patient = $result->fetch_assoc();

            } else {
                $error = "Failed to update profile.";
            }

        } else {
            $error = "No changes made.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile</title>
<!-- BOOTSTRAP MUST BE HERE -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- OTHER CSS AFTER BOOTSTRAP -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --primary-color: #001BB7;
    --sidebar-bg: #001BB7;
    --sidebar-hover: rgba(255,255,255,0.15);
    --text-color: #fff;
    --card-bg: #fff;
    --card-shadow: 0 4px 18px rgba(0,0,0,0.08);
}

* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b;}

/* SIDEBAR */
.sidebar {
    width:250px;
    background: var(--sidebar-bg);
    color: var(--text-color);
    position:fixed;
    height:100%;
    padding:25px 15px;
    display:flex;
    flex-direction:column;
    transition:0.3s;
}
.sidebar h2 {
    text-align:center;
    margin-bottom:20px;
    font-size:24px;
    font-weight:700;
    color: #fff;
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
    transition:0.3s;
}
.sidebar a:hover {
    background: var(--sidebar-hover);
    padding-left:24px; /* slide effect */
    color:#fff;
}
.sidebar a:hover i {
    transform: rotate(15deg); /* rotate icon */
}
.sidebar a.active {
    background: rgba(255,255,255,0.25);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    font-weight:600;
}

/* MAIN */
.main {
    margin-left:250px;
    padding:30px;
    flex:1;
    transition: margin-left 0.3s;
}
h1 {margin-bottom:20px; color: var(--primary-color); font-weight:700;}
label {display:block; margin-top:15px; font-weight:600;}
input, textarea, select {
    width:100%;
    padding:10px;
    margin-top:5px;
    border:1px solid #ddd;
    border-radius:6px;
    font-size:14px;
}
button {
    margin-top:20px;
    padding:12px;
    background: var(--primary-color);
    color:#fff;
    border:none;
    border-radius:8px;
    cursor:pointer;
    
    font-size:15px;
    transition:0.3s;
}
button:hover {background:#000FA0;}
.card {
    background: var(--card-bg);
    padding:25px;
    border-radius:12px;
    box-shadow: var(--card-shadow);
    max-width:1200px;
    margin:auto;
}
@media(max-width:768px){
    .main{margin-left:0; padding:20px;}
}
.sidebar h2 i {
    font-size:28px;
    vertical-align:middle;
}
.sidebar h2 {
    font-size:24px;
    font-weight:700;
    text-align:center;
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
/* PASSWORD SHOW/HIDE */
.password-wrapper {
    position: relative;
}

.password-wrapper input {
    padding-right: 45px; /* space for icon */
}

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
.update-btn {
    background-color: var(--primary-color) !important;
    color: #fff !important;
    border: none !important;
    padding: 8px 18px;
    border-radius: 8px;
    transition: 0.3s ease;
}

.update-btn:hover {
    background-color: #000FA0 !important;
    transform: translateY(-2px);
}
</style>
</head>
<body>
<!-- Hamburger (mobile) -->
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

    <a href="patient_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="patient_profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
    <a href="patient_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="patient_request.php"><i class="fas fa-calendar-plus"></i> Request Appointment</a>
    <a href="patient_appointments.php"><i class="fas fa-calendar-alt"></i> My Appointments</a>
    <a href="patient_medical_history.php"><i class="fas fa-file-medical"></i> Medical History</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<!-- Overlay (dim background when menu open) -->
<div id="overlay" aria-hidden="true"></div>

<div class="main">
    <?php include 'patient_header.php'; ?>
    <div class="card">
        <h1>My Profile</h1>

        <form method="POST" enctype="multipart/form-data">
    <label>Profile Picture</label>
    <div style="text-align:center; margin-bottom:15px;">
        <img id="profilePreview" src="uploads/<?php echo htmlspecialchars($patient['profile_picture'] ?: 'default-avatar.png'); ?>" 
             alt="Profile Picture" width="120" height="120" style="border-radius:50%; object-fit:cover; display:block; margin:auto 0 10px;">
        <input type="file" name="profile_picture" accept="image/*" onchange="previewProfile(event)">
    </div>

    <label>Full Name</label>
    <input type="text" name="full_name" value="<?php echo htmlspecialchars($patient['full_name'] ?? ''); ?>">

    <label>Email</label>
    <input type="email" name="email" value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>">

    <label>Contact Number</label>
    <input type="text" name="contact_number" value="<?php echo htmlspecialchars($patient['contact_number'] ?? ''); ?>">

    <label>Address</label>
    <textarea name="address"><?php echo htmlspecialchars($patient['address'] ?? ''); ?></textarea>

    <label>Birthday</label>
    <input type="date" name="birthday" value="<?php echo htmlspecialchars($patient['birthday'] ?? ''); ?>">

    <label>Gender</label>
    <select name="gender">
    <option value="">--Select Gender--</option>
    <option value="Male" <?php echo (($patient['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
    <option value="Female" <?php echo (($patient['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
</select>
<hr>
<h5 style="color:#001BB7; font-weight:700; text-align:center;">
                <i class="fas fa-key"></i> Change Password or Username
            </h5>

<label>Username</label>
<input type="text" name="username" value="<?php echo htmlspecialchars($patient['username'] ?? ''); ?>">

<label>New Password</label>
<div class="password-wrapper">
    <input type="password" name="new_password" id="new_password" placeholder="Leave blank to keep current">
    <i class="fas fa-eye toggle-password" data-target="new_password"></i>
</div>

<label>Confirm New Password</label>
<div class="password-wrapper">
    <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter new password">
    <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
</div>
<button type="submit" class="btn btn-sm update-btn">
    <i class="fas fa-save"></i> Update Profile
</button>
</form>

    </div>
</div>

<?php if ($success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?php echo $success; ?>',
    confirmButtonColor: '#001BB7' // your theme color
});
</script>
<?php endif; ?>
<?php if ($error): ?>
<script>
Swal.fire({icon:'error',title:'Error',text:'<?php echo $error; ?>'});
</script>
<?php endif; ?>
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
</script>
<script>
function previewProfile(event) {
    const reader = new FileReader();
    reader.onload = function(){
        document.getElementById('profilePreview').src = reader.result;
    }
    reader.readAsDataURL(event.target.files[0]);
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// SHOW / HIDE PASSWORD
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

