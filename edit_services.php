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

// Redirect if not logged in as admin
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

// Get service ID
if(!isset($_GET['id'])){
    header("Location: services.php");
    exit;
}

$service_id = intval($_GET['id']);

// Fetch existing service data
$result = mysqli_query($link, "SELECT * FROM tblservices WHERE service_id='$service_id'");
if(mysqli_num_rows($result) == 0){
    header("Location: services.php");
    exit;
}
$service = mysqli_fetch_assoc($result);

// Handle form submission
if(isset($_POST['service_name'])){ // triggered via fetch()
    $service_name = mysqli_real_escape_string($link, $_POST['service_name']);
    $description = mysqli_real_escape_string($link, $_POST['description']);

    $uploadDir = 'uploads/services/';
    $image1Path = $service['image1'];
    $image2Path = $service['image2'];

    // Image 1 update
    if(isset($_FILES['image1']) && $_FILES['image1']['error'] == 0){
        $image1Name = time() . '_1_' . basename($_FILES['image1']['name']);
        $target1 = $uploadDir . $image1Name;
        if(move_uploaded_file($_FILES['image1']['tmp_name'], $target1)){
            if(file_exists($service['image1'])) unlink($service['image1']);
            $image1Path = $target1;
        } else {
            $error = "Failed to upload Image 1.";
        }
    }

    // Image 2 update
    if(isset($_FILES['image2']) && $_FILES['image2']['error'] == 0){
        $image2Name = time() . '_2_' . basename($_FILES['image2']['name']);
        $target2 = $uploadDir . $image2Name;
        if(move_uploaded_file($_FILES['image2']['tmp_name'], $target2)){
            if(file_exists($service['image2'])) unlink($service['image2']);
            $image2Path = $target2;
        } else {
            $error = "Failed to upload Image 2.";
        }
    }

    if(empty($service_name)){
        $error = "Service name cannot be empty.";
    }

    if(!$error){
        $update_sql = "UPDATE tblservices 
                       SET service_name='$service_name', description='$description', image1='$image1Path', image2='$image2Path' 
                       WHERE service_id='$service_id'";
        if(mysqli_query($link, $update_sql)){
            // --- LOG THE UPDATE ---
            $admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';
            logAction($link, "Update", "Services", $service_name, $admin_name);

            echo 'success'; // send response to fetch
        } else {
            echo 'Database error: ' . mysqli_error($link);
        }
    } else {
        echo $error;
    }
    exit; // stop further output for fetch()
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Service - Admin</title>
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
.sidebar a {color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;}
.sidebar a i {margin-right:12px; font-size:18px;}
.sidebar a:hover {background:rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active {background:rgba(255,255,255,0.25); font-weight:600;}
.sidebar h2 i {font-size:28px; vertical-align:middle;}

/* Main content */
.main {margin-left:250px; padding:30px; flex:1;}
h1 {margin-bottom:20px; color:#001BB7; font-weight:700;}
form {background:#fff; padding:25px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);}
label {display:block; margin:10px 0 5px;}
input, textarea {width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:15px;}
button.submit-btn {background:#001BB7; color:white; padding:10px 18px; border:none; border-radius:8px; cursor:pointer; font-weight:600;}
button.submit-btn:hover {background:#001199;}
.error {color:red; margin-bottom:10px;}
.success {color:green; margin-bottom:10px;}
img.preview {max-width:150px; margin-top:10px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.15);}
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
/* ===== SweetAlert ViewStaff Card ===== */
.swal-card{
    max-width: 400px;
    margin: auto;
    border-radius: 15px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.swal-header{
    background: #001BB7;
    padding: 20px;
    text-align: center;
    color: #fff;
}

.swal-avatar{
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid white;
}

.swal-name{
    margin: 10px 0 0 0;
    font-size: 24px;
    font-weight: 700;
}

.swal-status{
    display:inline-block;
    padding: 5px 12px;
    border-radius: 12px;
    font-weight: 600;
    margin-top: 5px;
}

.swal-body{
    padding: 20px;
    text-align: left;
    color: #1e293b;
}

.swal-body p{
    margin: 8px 0;
}

.swal-footer{
    text-align:center;
    padding:15px;
    background:#f5f7fa;
}

.swal-close-btn{
    background:#001BB7;
    color:white;
    border:none;
    padding:10px 25px;
    border-radius:10px;
    font-weight:600;
    cursor:pointer;
    transition:0.3s;
}
/* ===== DARK MODE for Edit Service Form ===== */
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

/* Main content */
body.dark-mode .main {
    background: #0b1220 !important;
}

/* Form container */
body.dark-mode form {
    background: #111b2b !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.6) !important;
    color: #fff !important;
}

/* Labels */
body.dark-mode label {
    color: #f5f7fa !important;
}

/* Inputs & Textarea */
body.dark-mode input,
body.dark-mode textarea {
    background: #1a2337 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.3) !important;
}

/* Buttons */
body.dark-mode .submit-btn {
    background: #0b1430 !important;
    border: 1px solid #0b1430 !important;
    color: #fff !important;
}
body.dark-mode .submit-btn:hover {
    background: #00122b !important;
}

/* Preview Images */
body.dark-mode img.preview {
    border: 1px solid rgba(255,255,255,0.2) !important;
}

/* Back button */
body.dark-mode .back-btn {
    color: #f5f7fa !important;
}
body.dark-mode .back-btn:hover {
    color: #e9f2ff !important;
}

/* SweetAlert (for dark mode) */
body.dark-mode .swal2-popup {
    background: #111b2b !important;
    color: #fff !important;
}
body.dark-mode .swal2-title,
body.dark-mode .swal2-content {
    color: #fff !important;
}

</style>
</head>
<body>

<div class="sidebar">
    <h2>
    <img src="logo.png" alt="KNL Logo" 
     style="width:45px; height:45px; margin-right:10px; vertical-align:middle; border-radius:50%; object-fit:cover;">
    KNL Health Center
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
    <h1><i class="fas fa-edit me-2"></i> Edit Service</h1>

    <form id="editServiceForm" method="POST" enctype="multipart/form-data">
        <label>Service Name</label>
        <input type="text" name="service_name" value="<?php echo htmlspecialchars($service['service_name']); ?>" required>

        <label>Description</label>
        <textarea name="description" rows="4"><?php echo htmlspecialchars($service['description']); ?></textarea>

        <label>Image 1</label>
        <?php if($service['image1']) echo "<br><img src='".$service['image1']."' class='preview'>"; ?>
        <input type="file" name="image1" accept="image/*">

        <label>Image 2</label>
        <?php if($service['image2']) echo "<br><img src='".$service['image2']."' class='preview'>"; ?>
        <input type="file" name="image2" accept="image/*">

        <button type="submit" class="submit-btn"><i class="fas fa-save me-2"></i> Update Service</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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

// Update confirmation
document.getElementById('editServiceForm').addEventListener('submit', function(e){
    e.preventDefault();

    Swal.fire({
        title: 'Are you sure?',
        text: "Do you want to update this service?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#001BB7',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            const formData = new FormData(this);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if(data.trim() === 'success'){
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated!',
                        text: 'Service successfully updated.',
                        confirmButtonColor: '#001BB7'
                    }).then(() => {
                        window.location.href = 'services.php';
                    });
                } else {
                    Swal.fire('Error', data, 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Something went wrong.', 'error');
            });
        }
    });
});
</script>
</body>
</html>
