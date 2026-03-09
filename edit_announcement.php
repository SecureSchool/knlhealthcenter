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

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

if(!isset($_GET['id']) || empty($_GET['id'])){
    header("Location: announcement.php");
    exit;
}

$announcement_id = intval($_GET['id']);

// Fetch existing announcement
$result = mysqli_query($link, "SELECT * FROM tblannouncements WHERE id = $announcement_id");
if(mysqli_num_rows($result) === 0){
    header("Location: announcement.php");
    exit;
}
$announcement = mysqli_fetch_assoc($result);

if(isset($_POST['edit_announcement'])){
    $title = mysqli_real_escape_string($link, $_POST['title']);
    $content = mysqli_real_escape_string($link, $_POST['content']);

    mysqli_query($link, "UPDATE tblannouncements SET title='$title', content='$content' WHERE id=$announcement_id");

    // Log the edit
    logAction($link, "Update", "Announcements", $title, $_SESSION['admin_name']);

    // Set a flag in GET to trigger success alert
    header("Location: edit_announcement.php?id=$announcement_id&updated=1");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Announcement - Admin Dashboard</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
body{background:#f5f7fa;display:flex;min-height:100vh;color:#1e293b;}
.sidebar{width:250px;background:#001BB7;color:#fff;position:fixed;height:100%;padding:25px 15px;display:flex;flex-direction:column;}
.sidebar h2{text-align:center;margin-bottom:20px;font-size:24px;font-weight:700;}
.sidebar h2 i{font-size:28px; vertical-align:middle;}
.sidebar a{color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;}
.sidebar a i{margin-right:12px;font-size:18px;}
.sidebar a:hover{background:rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active{background:rgba(255,255,255,0.25); font-weight:600;}
.main{margin-left:250px; padding:30px; flex:1;}
h1,h2.page-title{color:#001BB7; margin-bottom:20px; display:flex; align-items:center; gap:10px;}
form input, form textarea{width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:8px;}
form button{padding:10px 15px; background:#001BB7; color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:5px;}
form button:hover{background:#001199;}
a.back-btn{display:inline-flex; align-items:center; color:#001BB7; font-weight:600; text-decoration:none; font-size:20px; margin-bottom:15px; transition:0.2s; gap:5px;}
a.back-btn i{font-size:20px;}
a.back-btn:hover{color:#001199;}
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

</style>
</head>
<body>

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
    <a href="announcement.php" class="active"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main">
    <?php include 'admin_header.php'; ?>
    <a href="announcement.php" class="back-btn"><i class="fas fa-chevron-left"></i> </a>
    <h2 class="page-title"><i class="fas fa-edit"></i> Edit Announcement</h2>

    <form method="POST" id="editForm">
        <input type="text" name="title" value="<?php echo htmlspecialchars($announcement['title']); ?>" placeholder="Title" required>
        <textarea name="content" rows="6" placeholder="Content" required><?php echo htmlspecialchars($announcement['content']); ?></textarea>
        
        <input type="hidden" name="edit_announcement" value="1">
        <button type="button" id="submitBtn"><i class="fas fa-save"></i> Update Announcement</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Submit confirmation
document.getElementById('submitBtn').addEventListener('click', function(){
    const title = document.querySelector('input[name="title"]').value.trim();
    const content = document.querySelector('textarea[name="content"]').value.trim();

    if(title === '' || content === ''){
        Swal.fire({
            icon:'error',
            title:'Oops!',
            text:'Please fill in both the title and content before submitting.',
            confirmButtonColor:'#001BB7'
        });
        return;
    }

    Swal.fire({
        title:'Are you sure?',
        text:'You are about to update this announcement!',
        icon:'question',
        showCancelButton:true,
        confirmButtonColor:'#001BB7',
        cancelButtonColor:'#d33',
        confirmButtonText:'Yes, update it!',
        cancelButtonText:'Cancel'
    }).then((result)=>{
        if(result.isConfirmed){
            document.getElementById('editForm').submit();
        }
    });
});

// Logout
document.getElementById('logoutBtn').addEventListener('click',function(e){
    e.preventDefault();
    Swal.fire({
        title:'Are you sure?',
        text:'You will be logged out from the system.',
        icon:'warning',
        showCancelButton:true,
        confirmButtonColor:'#001BB7',
        cancelButtonColor:'#d33',
        confirmButtonText:'Yes, log me out',
        cancelButtonText:'Cancel'
    }).then((result)=>{
        if(result.isConfirmed){window.location.href='logout.php';}
    });
});

// Success notification and redirect
<?php if(isset($_GET['updated']) && $_GET['updated'] == 1): ?>
Swal.fire({
    icon: 'success',
    title: 'Updated!',
    text: 'The announcement has been successfully updated.',
    confirmButtonColor: '#001BB7'
}).then(()=>{ window.location.href='announcement.php'; });
<?php endif; ?>
</script>

</body>
</html>
