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

// Handle delete
if(isset($_GET['delete_id'])){
    $delete_id = intval($_GET['delete_id']);
    
    // Fetch announcement title for logging
    $ann_row = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM tblannouncements WHERE id='$delete_id'"));
    
    if($ann_row){
        mysqli_query($link, "DELETE FROM tblannouncements WHERE id='$delete_id'");
        
        // Log deletion
        logAction($link, "Delete", "Announcements", $ann_row['title'], $_SESSION['admin_name']);
        
        header("Location: announcement.php?deleted=1");
        exit;
    }
}

// Handle search
$search_title = $_GET['search_title'] ?? '';

// Pagination setup
$limit = 5; // number of rows per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count total announcements
$count_sql = "SELECT COUNT(*) AS total FROM tblannouncements WHERE 1";
if($search_title){
    $count_sql .= " AND title LIKE '%$search_title%'";
}
$count_result = mysqli_query($link, $count_sql);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch announcements (with limit & offset)
$sql = "SELECT * FROM tblannouncements WHERE 1";
if($search_title){
    $sql .= " AND title LIKE '%$search_title%'";
}
$sql .= " ORDER BY id DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($link, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements - Admin Dashboard</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
body{background:#f5f7fa;display:flex;min-height:100vh;color:#1e293b;}
.sidebar{width:250px;background:#001BB7;color:#fff;position:fixed;height:100%;padding:25px 15px;display:flex;flex-direction:column;}
.sidebar h2{text-align:center;margin-bottom:20px;font-size:24px;font-weight:700;}
.sidebar a{color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;}
.sidebar a i{margin-right:12px;font-size:18px;}
.sidebar a:hover{background:rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active{background:rgba(255,255,255,0.25); font-weight:600;}
/* Sidebar header icon size */
.sidebar h2 i {
    font-size:28px;
    vertical-align:middle;
}

.main{margin-left:250px; padding:30px; flex:1;}
h1{margin-bottom:20px;color:#001BB7;font-weight:700;}
form.filter-form{margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;}
input[type=text]{padding:8px 12px;border:1px solid #ddd;border-radius:8px;flex:1;}
button.filter-btn,.add-btn{padding:8px 14px;border:none;border-radius:8px;font-weight:600;cursor:pointer;transition:0.2s;}
button.filter-btn{background:#001BB7;color:white;}
.add-btn{background:#27ae60;color:white;text-decoration:none; display:flex; align-items:center; justify-content:center;}
button.filter-btn:hover{background:#001199;}
.add-btn:hover{background:#1e8449;}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.08);}
th,td{padding:14px;text-align:center;border-bottom:1px solid #e0e0e0;}
th{background:#001BB7;color:white;font-weight:600;}
tr:hover{background:#f1f3f6;}
.action-btn{padding:6px 10px;border:none;border-radius:6px;cursor:pointer;font-size:16px;display:inline-flex;align-items:center;justify-content:center;margin:2px;transition:0.2s;}
.edit-btn{background:#27ae60;color:white;}
.delete-btn{background:#c0392b;color:white;}
.view-btn{background:#001BB7;color:white;}
.action-btn i{margin:0;font-size:16px;}
.action-btn:hover{opacity:0.85;}
/* Pagination */
.pagination {margin-top:10px;}
.pagination .page-item.active .page-link {background-color:#001BB7; border-color:#001BB7; color:white;}
.pagination .page-link {color:#001BB7;}
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
  .hamburger { display: block; }

  .sidebar {
    transform: translateX(-100%);
    width: 250px;
    left: 0;
    top: 0;
    height: 100%;
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
/* ===== DARK MODE - VIEW MODAL ===== */
body.dark-mode .modal-content {
    background: #111b2b !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.2) !important;
}

body.dark-mode .modal-header {
    background: #0b1430 !important;
    color: #fff !important;
}

body.dark-mode .modal-body {
    background: #0b1220 !important;
    color: #fff !important;
}

body.dark-mode .modal-footer {
    background: #0b1220 !important;
    color: #fff !important;
}

body.dark-mode .modal-body p,
body.dark-mode .modal-body h6,
body.dark-mode .modal-body strong {
    color: #fff !important;
}

body.dark-mode .modal-body .border {
    border-color: rgba(255,255,255,0.2) !important;
    background: #1a2337 !important;
}

body.dark-mode .btn-close {
    filter: invert(1) !important;
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
/* ===== HAMBURGER SIDEBAR SCROLL FIX ===== */
@media (max-width:768px){

  .sidebar{
      height:100vh;
      overflow-y:auto;
      overflow-x:hidden;
      -webkit-overflow-scrolling: touch;
  }

}
.sidebar::-webkit-scrollbar{
  width:5px;
}
.sidebar::-webkit-scrollbar-thumb{
  background:rgba(255,255,255,0.35);
  border-radius:10px;
}
.sidebar::-webkit-scrollbar-track{
  background:transparent;
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
    <a href="announcement.php" class="active"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<!-- Overlay -->
<div id="overlay" aria-hidden="true"></div>

<div class="main">
    <?php include 'admin_header.php'; ?>
    <h1><i class="fas fa-bullhorn me-2"></i> Announcement Management</h1>

    <form method="GET" class="filter-form">
        <input type="text" name="search_title" placeholder="Search by title" value="<?php echo htmlspecialchars($search_title); ?>">
        <button type="submit" class="filter-btn"><i class="fas fa-search"></i> Search</button>
        <a href="add_announcement.php" class="add-btn"><i class="fas fa-plus"></i> Add Announcement</a>
    </form>

    <table>
        <tr>
            <th>Title</th>
            <th>Content</th>
            <th>Date Created</th>
            <th>Actions</th>
        </tr>
        <?php if(mysqli_num_rows($result) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars(substr($row['content'],0,50)).'...'; ?></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td>
                    <button type="button" class="action-btn view-btn" 
                        data-bs-toggle="modal" 
                        data-bs-target="#viewModal<?php echo $row['id']; ?>">
                        <i class="fas fa-eye"></i>
                    </button>
                    <a href="edit_announcement.php?id=<?php echo $row['id']; ?>" class="action-btn edit-btn"><i class="fas fa-edit"></i></a>
                    <button class="action-btn delete-btn" onclick="confirmDelete(<?php echo $row['id']; ?>)"><i class="fas fa-trash-alt"></i></button>
                </td>
            </tr>

            <!-- View Modal -->
<div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius:12px; overflow:hidden;">
      
      <!-- Modal Header -->
      <div class="modal-header" style="background:#001BB7; color:#fff;">
        <h5 class="modal-title">
          <i class="fas fa-bullhorn me-2"></i> 
          <?php echo htmlspecialchars($row['title']); ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      
      <!-- Modal Body -->
      <div class="modal-body" style="background:#f8f9fa; color:#1e293b;">
        <p><strong style="color:#001BB7;">ID:</strong> <?php echo $row['id']; ?></p>
        <p><strong style="color:#001BB7;">Date Created:</strong> <?php echo htmlspecialchars($row['created_at']); ?></p>
        <hr>
        <h6 style="color:#001BB7;"><i class="fas fa-align-left me-2"></i> Full Content</h6>
        <div class="p-3 border rounded" style="background:#fff; white-space:pre-line; max-height:300px; overflow-y:auto;">
          <?php echo nl2br(htmlspecialchars($row['content'])); ?>
        </div>
      </div>
      
      <!-- Modal Footer -->
      <div class="modal-footer" style="background:#f8f9fa;">
        <button type="button" class="btn" style="background:#001BB7; color:white;" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i> Close
        </button>
      </div>
    </div>
  </div>
</div>

            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5" style="text-align:center;">No announcements found.</td></tr>
        <?php endif; ?>
    </table>
    <!-- Simple Pagination -->
<?php if($total_pages > 1): ?>
<nav aria-label="Page navigation">
    <ul class="pagination justify-content-end mt-3">
        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
            <a class="page-link" href="?search_title=<?php echo urlencode($search_title); ?>&page=<?php echo max(1, $page-1); ?>">Previous</a>
        </li>
        <li class="page-item active">
            <span class="page-link"><?php echo $page; ?></span>
        </li>
        <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
            <a class="page-link" href="?search_title=<?php echo urlencode($search_title); ?>&page=<?php echo min($total_pages, $page+1); ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Delete confirmation
function confirmDelete(id){
    Swal.fire({
        title:'Are you sure?',
        text:'You are about to delete this announcement!',
        icon:'warning',
        showCancelButton:true,
        confirmButtonColor:'#c0392b',
        cancelButtonColor:'#7f8c8d',
        confirmButtonText:'Yes, delete it!',
        cancelButtonText:'Cancel'
    }).then((result)=>{
        if(result.isConfirmed){
            window.location.href='announcement.php?delete_id='+id;
        }
    });
}
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
    }).then((result)=>{if(result.isConfirmed){window.location.href='logout.php';}});
});
</script>

<?php if(isset($_GET['added']) && $_GET['added'] == 1): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Announcement Added!',
    text: 'Your announcement was successfully added.',
    confirmButtonColor: '#001BB7'
});
</script>
<?php endif; ?>

<?php if(isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Deleted!',
    text: 'The announcement has been deleted successfully.',
    confirmButtonColor: '#001BB7'
});
</script>
<?php endif; ?>

</body>
</html>
