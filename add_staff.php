<?php
session_start();
require_once "config.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
function sendStaffWelcomeEmail($email, $full_name, $username, $password) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'krusnaligashealthcenter@gmail.com'; // change
        $mail->Password   = 'nwbl trbm yphq dmyl'; // change
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('krusnaligashealthcenter@gmail.com', 'KNL Health Center');
        $mail->addAddress($email, $full_name);

        $mail->isHTML(true);
        $mail->Subject = "Welcome to KNL Health Center";

        $mail->Body = "
        <div style='font-family:Arial, sans-serif; max-width:600px; margin:0 auto; border:1px solid #e0e0e0; border-radius:8px; overflow:hidden;'>
            <div style='background-color:#001BB7; color:#fff; padding:20px; text-align:center;'>
                <h1 style='margin:0; font-size:24px;'>Welcome to KNL Health Center</h1>
            </div>
            <div style='padding:25px; color:#333; font-size:16px; line-height:1.6;'>
                <p>Hello <strong>$full_name</strong>,</p>
                <p>Your staff account has been successfully created. You can log in using the credentials below:</p>
                
                <table style='width:100%; border-collapse:collapse; margin:20px 0;'>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Username</td>
                        <td style='padding:10px; border:1px solid #ddd;'>$username</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Password</td>
                        <td style='padding:10px; border:1px solid #ddd;'>$password</td>
                    </tr>
                </table>

                <p><strong>Important:</strong> For security, please change your password immediately after logging in.</p>

                <div style='text-align:center; margin-top:30px;'>
                    <a href='http://yourdomain.com/staff_login.php' style='text-decoration:none; background:#001BB7; color:#fff; padding:12px 20px; border-radius:5px;'>Login to Account</a>
                </div>
            </div>
            <div style='background:#f0f0f0; color:#555; font-size:12px; text-align:center; padding:15px;'>
                KNL Health Center • Brgy Krus na Ligas, Quezon City<br>
                &copy; ".date('Y')." KNL Health Center. All rights reserved.
            </div>
        </div>
        ";

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

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

$errors = [];
$success = false;

$full_name = '';
$username = '';
$password = '';
$email = '';
$contact_number = '';

if(isset($_POST['add_staff'])){
    $full_name = trim(mysqli_real_escape_string($link, $_POST['full_name']));
    $username = trim(mysqli_real_escape_string($link, $_POST['username']));
    $password = trim($_POST['password']);
    $email = trim(mysqli_real_escape_string($link, $_POST['email']));
    $contact_number = trim(mysqli_real_escape_string($link, $_POST['contact_number']));
    $profile_pic = NULL; // default if no file uploaded

if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK){
    $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
    $fileName = $_FILES['profile_pic']['name'];
    $fileSize = $_FILES['profile_pic']['size'];
    $fileType = $_FILES['profile_pic']['type'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    if(in_array($fileExtension, $allowedfileExtensions)){
        $newFileName = uniqid('staff_', true) . '.' . $fileExtension;
        $uploadFileDir = 'uploads/staff/';
        if(!is_dir($uploadFileDir)) mkdir($uploadFileDir, 0755, true);
        $dest_path = $uploadFileDir . $newFileName;

        if(move_uploaded_file($fileTmpPath, $dest_path)){
            $profile_pic = $newFileName;
        } else {
            $errors[] = "There was an error moving the uploaded file.";
        }
    } else {
        $errors[] = "Upload failed. Allowed file types: " . implode(", ", $allowedfileExtensions);
    }
}

    if(empty($full_name)) $errors[] = "Full name is required.";
    if(empty($username)) $errors[] = "Username is required.";
    if(empty($password)) $errors[] = "Password is required.";
    if(empty($email)) $errors[] = "Email is required.";
    if(empty($contact_number)) $errors[] = "Contact number is required.";

    if(!empty($username) && !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)){
        $errors[] = "Username must be 3-20 characters and contain only letters, numbers, or underscores.";
    }

    if(!empty($password) && strlen($password) < 6){
        $errors[] = "Password must be at least 6 characters long.";
    }

    if(!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)){
        $errors[] = "Invalid email address format.";
    }

    if(!empty($contact_number) && !preg_match('/^\d{11}$/', $contact_number)){
        $errors[] = "Contact number must be numeric and exactly 11 digits.";
    }

    if(count($errors) === 0){
        $dup_check_sql = "SELECT * FROM tblstaff WHERE username='$username' OR email='$email' OR contact_number='$contact_number' LIMIT 1";
        $dup_result = mysqli_query($link, $dup_check_sql);
        if(mysqli_num_rows($dup_result) > 0){
            $errors[] = "A staff member with the same username, email, or contact number already exists.";
        }
    }

    if(count($errors) === 0){
    $insert_sql = "INSERT INTO tblstaff (full_name, username, password, email, contact_number, profile_pic, date_created, status) 
               VALUES ('$full_name','$username','$password','$email','$contact_number','$profile_pic',NOW(),'Active')";

    if(mysqli_query($link, $insert_sql)){
        sendStaffWelcomeEmail($email, $full_name, $username, $password);
        $admin_name = $_SESSION['admin_name']; // <-- added this
        logAction($link, "Add", "Staff", $full_name, $admin_name);
        $success = true;
    } else {
        $errors[] = "Database error: " . mysqli_error($link);
    }
}

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Staff - Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
.sidebar h2 {text-align:center; margin-bottom:20px; font-size:24px; font-weight:700;}
.sidebar h2 i {font-size:28px; vertical-align:middle; margin-right:10px;}
.sidebar a {color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;}
.sidebar a i {margin-right:12px; font-size:18px;}
.sidebar a:hover {background:rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active {background:rgba(255,255,255,0.25); font-weight:600;}

/* Main content */
.main {margin-left:250px; padding:30px; flex:1;}
h1 {margin-bottom:20px; color:#001BB7; font-weight:700;}
form {background:#fff; padding:25px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);}
label {display:block; margin:10px 0 5px;}
input {
    width:100%;
    padding:10px;
    border:1px solid #ddd;
    border-radius:8px;
    margin-bottom:15px;
}

button.submit-btn {
    width:100%;
    padding:10px;
    border:1px solid #ddd;
    border-radius:8px;
    margin-bottom:15px;
}
button.submit-btn {background:#001BB7; color:white; padding:10px; border:none; border-radius:8px; cursor:pointer; font-weight:600;}
button.submit-btn:hover {background:#001199;}
.error {color:red; margin-bottom:10px;}
.back-btn {
    display:inline-flex;
    align-items:center;
    color:#001BB7;
    font-weight:600;
    text-decoration:none;
    font-size:20px;
    margin-bottom:15px;
    transition:0.2s;
}
.back-btn i {
    font-size:20px;
}
.back-btn:hover {
    color:#001199;
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
/* ===== DARK FORM ===== */
body.dark-mode form {
    background: #0f1a2e;
    border: 1px solid rgba(255,255,255,0.15);
    box-shadow: 0 4px 12px rgba(0,0,0,0.5);
}

body.dark-mode label {
    color: #e9f2ff;
}

body.dark-mode input,
body.dark-mode textarea,
body.dark-mode select {
    background: #1a2337;
    color: #fff;
    border: 1px solid rgba(255,255,255,0.25);
}

body.dark-mode input::placeholder,
body.dark-mode textarea::placeholder {
    color: rgba(255,255,255,0.55);
}

body.dark-mode .submit-btn {
    background: #0b6fb5;
    border: 1px solid #0b6fb5;
}

body.dark-mode .submit-btn:hover {
    background: #0a5d99;
}

/* Error box */
body.dark-mode .error {
    background: rgba(255, 0, 0, 0.08);
    border: 1px solid rgba(255, 0, 0, 0.25);
    padding: 15px;
    border-radius: 10px;
    color: #fff;
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
  width: 40px;
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
.dark-btn {
    width: 38px !important;
    height: 38px !important;
    padding: 0 !important;
    display: flex !important;
    align-items: center;
    justify-content: center;
    border-radius: 50px !important;
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
    <a href="staff.php" class="active"><i class="fas fa-user-tie"></i> Staff</a>
    <a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a>
    <a href="services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="announcement.php"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main">
    <?php include 'admin_header.php'; ?>
    <a href="staff.php" class="back-btn"><i class="fas fa-chevron-left"></i></a>
    <h1><i class="fas fa-user-plus me-2"></i> Add Staff</h1>

    <?php if(!empty($errors)): ?>
    <div class="error">
        <ul>
        <?php foreach($errors as $err): ?>
            <li><?php echo htmlspecialchars($err); ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <img src="uploads/staff/<?php echo htmlspecialchars($row['profile_pic']); ?>" alt="Profile" width="50" height="50">

    <label>Full Name *</label>
    <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>

    <label>Username *</label>
    <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>

    <label>Password *</label>
    <div style="position:relative; display:flex; align-items:center;">
    <input type="password" name="password" id="passwordField" required style="margin-bottom:0;">

    <button type="button" id="togglePassword"
        style="
        position:absolute;
        right:12px;
        top:50%;
        transform:translateY(-50%);
        border:none;
        background:none;
        cursor:pointer;
        color:#555;">
        <i class="fas fa-eye"></i>
    </button>
</div>
    <label>Contact Number *</label>
    <input type="text" name="contact_number" value="<?php echo htmlspecialchars($contact_number); ?>" required>

    <label>Email *</label>
    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>

    <label>Profile Picture</label>
    <input type="file" name="profile_pic" accept="image/*">

    <button type="submit" name="add_staff" class="submit-btn"><i class="fas fa-plus me-2"></i> Add Staff</button>
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
        if(result.isConfirmed){ window.location.href='logout.php'; }
    });
});

<?php if($success): ?>
Swal.fire({
    icon:'success',
    title:'Success',
    text:'Staff added successfully!',
    confirmButtonColor: '#001BB7'
}).then(()=>{ window.location.href='staff.php'; });
<?php endif; ?>
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
<script>
const togglePassword = document.getElementById("togglePassword");
const passwordField = document.getElementById("passwordField");

togglePassword.addEventListener("click", function () {
    const type = passwordField.getAttribute("type") === "password" ? "text" : "password";
    passwordField.setAttribute("type", type);

    this.innerHTML = type === "password"
        ? '<i class="fas fa-eye"></i>'
        : '<i class="fas fa-eye-slash"></i>';
});
</script>
</body>
</html>
