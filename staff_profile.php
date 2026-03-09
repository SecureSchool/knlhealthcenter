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

// Redirect if not logged in
if(!isset($_SESSION['staff_id'])){
    header("Location: login.php");
    exit;
}

$staff_id = $_SESSION['staff_id'];
$staff_name = $_SESSION['staff_name'];
$success = false;
$errors = [];

// Fetch staff details
$sql = "SELECT * FROM tblstaff WHERE staff_id = '$staff_id' LIMIT 1";
$result = mysqli_query($link, $sql);
$staff = mysqli_fetch_assoc($result);

if(!$staff){
    die("Staff not found.");
}

// Handle form submit
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $fullname = trim(mysqli_real_escape_string($link, $_POST['fullname']));
    $username = trim(mysqli_real_escape_string($link, $_POST['username']));
    $email = trim(mysqli_real_escape_string($link, $_POST['email']));
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Profile picture
    $profile_pic = $staff['profile_pic'] ?? 'default.png';
    if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0){
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $new_name = 'staff_'.time().'.'.$ext;
        $upload_dir = 'uploads/staff/';
        if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        if(move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir.$new_name)){
            if($profile_pic !== 'default.png' && file_exists($upload_dir.$profile_pic)){
                unlink($upload_dir.$profile_pic);
            }
            $profile_pic = $new_name;
        }
    }

    // Validations
    if(empty($fullname)) $errors[] = "Full name is required.";
    if(empty($username)) $errors[] = "Username is required.";
    if(empty($email)) $errors[] = "Email is required.";
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Please enter a valid email address.";

    // Check if username is unique
    $check_username = mysqli_query($link, "SELECT staff_id FROM tblstaff WHERE username='$username' AND staff_id != '$staff_id'");
    if(mysqli_num_rows($check_username) > 0){
        $errors[] = "Username already taken.";
    }

    // Password validation
if(!empty($new_password) || !empty($confirm_password)){
    if($new_password !== $confirm_password){
        $errors[] = "Passwords do not match.";
    } else {
        // Store as plain text instead of hashed
        $hashed_password = $new_password;
    }
}

// Update database
if(count($errors) === 0){
    $update_sql = "UPDATE tblstaff SET 
                    full_name='$fullname', 
                    username='$username', 
                    email='$email', 
                    profile_pic='$profile_pic'";
    if(isset($hashed_password)) $update_sql .= ", password='$hashed_password'";
    $update_sql .= " WHERE staff_id='$staff_id'";

    if(mysqli_query($link, $update_sql)){
        $success = true;
        $_SESSION['staff_name'] = $fullname;
        $result = mysqli_query($link, $sql);
        $staff = mysqli_fetch_assoc($result);

        // Log profile update
        logAction($link, "Update Profile", "Staff Profile", $staff_name, $fullname);
    } else {
        $errors[] = "Failed to update profile. Please try again.";
    }
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Profile</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --primary-color: #001BB7;
    --sidebar-bg: #001BB7;
    --sidebar-hover: rgba(255,255,255,0.15);
    --text-color: #fff;
}
body { background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b; font-family:'Inter', sans-serif; }
.hamburger { display:none; position:fixed; top:15px; left:15px; font-size:20px; color: var(--primary-color); background:#fff; border:none; border-radius:8px; padding:8px 10px; cursor:pointer; z-index:1100; }
#overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s; z-index: 900; }
#overlay.active {opacity:1; visibility:visible;}
.sidebar { width:250px; background: var(--sidebar-bg); color: var(--text-color); position:fixed; height:100%; padding:25px 15px; display:flex; flex-direction:column; transition: transform 0.3s ease; z-index:1000; }
.sidebar h2 { text-align:center; margin-bottom:20px; font-size:24px; font-weight:700; }
.sidebar a { color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:8px 0; text-decoration:none; border-radius:10px; transition:0.3s; font-weight:500; }
.sidebar a i { margin-right:12px; font-size:18px; }
.sidebar a:hover { background: var(--sidebar-hover); padding-left:24px; color:#fff; }
.sidebar a.active { background: rgba(255,255,255,0.25); font-weight:600; }
.main { margin-left:250px; padding:40px; flex:1; transition: margin-left 0.3s; }

/* ---- STAFF CARD STYLE LIKE DOCTOR PROFILE ---- */
.card {
    background: #fff;
    padding: 35px;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.08);
    width: 100%; /* full width of .main */
    box-sizing: border-box; /* include padding in width */
    margin-bottom: 20px; /* spacing between cards */
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
    border: 4px solid #e2e8f0;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
h1.card-title {
    color: #001BB7;
    font-weight: 700;
    text-align: center;
    margin-bottom: 25px;
}
label {
    font-weight:600;
    margin-top:15px;
}
input, select, textarea {
    width:100%;
    padding:12px 14px;
    margin-top:6px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    font-size:15px;
    background:#f8fafc;
}
input:focus {
    outline:none;
    border-color: #001BB7;
    background:#fff;
    box-shadow:0 0 0 3px rgba(0,27,183,0.1);
}
button.btn-primary {
    background:#001BB7 !important;
    border:none;
    font-weight:600;
}
button.btn-primary:hover {background:#000FA0 !important;}

@media(max-width:768px){ .hamburger { display:block; } .sidebar { transform:translateX(-280px); } .sidebar.active { transform:translateX(0); } .main { margin-left:0; padding:20px; } }
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
/* Make Edit Profile title white in dark mode */
body.dark-mode h1.card-title {
    color: #fff !important;
}
.password-wrapper {
    position: relative;
}
.password-wrapper .toggle-password {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    cursor: pointer;
    color: #6b7280;
    font-size: 16px;
}
.password-wrapper .toggle-password:hover {
    color: #001BB7;
}
/* Remove default browser show/hide password icon */
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear,
input[type="password"]::-webkit-password-toggle-button {
    display: none;
    -webkit-appearance: none;
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
    <a href="staff_profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
    <a href="patient_staff.php"><i class="fas fa-users"></i> Patients</a>
    <a href="staff_appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
    <a href="staff_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="staff_reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Main -->
<div class="main">
    <?php include 'header_staff.php'; ?>

    <!-- Profile Card -->
    <div class="card">
        <h1 class="card-title"><i class="fas fa-user-cog"></i> Edit Profile</h1>

        <form method="POST" enctype="multipart/form-data">
            <label>Profile Picture</label>
            <div class="profile-pic-wrapper">
                <img id="previewImg" src="uploads/staff/<?php echo htmlspecialchars($staff['profile_pic'] ?? 'default.png'); ?>" alt="Profile Picture">
            </div>
            <input type="file" name="profile_pic" accept="image/*" onchange="previewProfile(event)">

            <label>Full Name *</label>
            <input type="text" name="fullname" value="<?php echo htmlspecialchars($staff['full_name']); ?>" required>

            <label>Email *</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($staff['email']); ?>" required>

            <label>Account Status</label>
            <input type="text" value="<?php echo htmlspecialchars($staff['status']); ?>" readonly>

            <label>Account Created On</label>
            <input type="text" value="<?php echo date("F j, Y", strtotime($staff['date_created'])); ?>" readonly>

            <hr>

            <h5 style="color:#001BB7; font-weight:700; text-align:center;">
                <i class="fas fa-key"></i> Change Password or Username
            </h5>

<label>Username *</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($staff['username']); ?>" required>
<label>New Password</label>
<div class="password-wrapper">
    <input type="password" name="new_password" id="new_password">
    <i class="fas fa-eye toggle-password" data-target="new_password"></i>
</div>

<label>Confirm Password</label>
<div class="password-wrapper">
    <input type="password" name="confirm_password" id="confirm_password">
    <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
</div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Profile</button>
        </form>
    </div>

    <!-- Recent Activity Card -->
    <div class="card">
        <h1 class="card-title"><i class="fas fa-history"></i> Recent Activity</h1>
        <ul>
        <?php
        $logs = mysqli_query($link, "
            SELECT * 
            FROM tbllogs 
            WHERE performedby='{$staff_name}' 
            ORDER BY datelog DESC, timelog DESC 
            LIMIT 5
        ");
        if(mysqli_num_rows($logs) > 0){
            while($log = mysqli_fetch_assoc($logs)){
                echo "<li>".htmlspecialchars($log['datelog']." ".$log['timelog']." - ".$log['action']." on ".$log['performedto'])."</li>";
            }
        } else {
            echo "<li>No recent activity found.</li>";
        }
        ?>
        </ul>
    </div>
</div>

<?php if($success): ?>
<script>
Swal.fire({ icon: 'success', title: 'Updated!', text: 'Your profile has been updated successfully.', confirmButtonColor: '#001BB7' });
</script>
<?php endif; ?>

<?php if(!empty($errors)): ?>
<script>
Swal.fire({ icon: 'error', title: 'Oops...', html: '<?php echo implode("<br>", array_map("htmlspecialchars", $errors)); ?>', confirmButtonColor: '#001BB7' });
</script>
<?php endif; ?>

<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Logout
document.getElementById('logoutBtn').addEventListener('click', function(e){
    e.preventDefault();
    Swal.fire({ title: 'Are you sure?', text: "You will be logged out.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#001BB7', cancelButtonColor: '#d33', confirmButtonText: 'Yes, log me out', cancelButtonText: 'Cancel' }).then((result) => { if(result.isConfirmed) window.location.href='logout.php'; });
});

// Hamburger menu
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

// Profile picture preview
function previewProfile(event){
    const reader = new FileReader();
    reader.onload = function(){ document.getElementById('previewImg').src = reader.result; }
    reader.readAsDataURL(event.target.files[0]);
}

// Client-side password match check
document.querySelector('form').addEventListener('submit', function(e){
    const password = this.new_password.value;
    const confirm = this.confirm_password.value;
    if(password && password !== confirm){
        e.preventDefault();
        Swal.fire({ icon: 'error', title: 'Oops...', text: 'Passwords do not match!', confirmButtonColor: '#001BB7' });
    }
});

// Show/Hide Password
document.querySelectorAll('.toggle-password').forEach(icon => {
    icon.addEventListener('click', function(){
        const targetInput = document.getElementById(this.dataset.target);
        if(targetInput.type === 'password'){
            targetInput.type = 'text';
            this.classList.remove('fa-eye');
            this.classList.add('fa-eye-slash');
        } else {
            targetInput.type = 'password';
            this.classList.remove('fa-eye-slash');
            this.classList.add('fa-eye');
        }
    });
});
</script>

</body>
</html>
