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
    
    // Fetch service name for logging
    $service_row = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM tblservices WHERE service_id='$delete_id'"));
    
    if($service_row){
        mysqli_query($link, "DELETE FROM tblservices WHERE service_id='$delete_id'");
        
        // Log the deletion
        logAction($link, "Delete", "Services", $service_row['service_name'], $_SESSION['admin_name']);
        
        header("Location: services.php?deleted=1");
        exit;
    }
}


// Pagination setup
$limit = 10; // number of rows per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count total services
$count_sql = "SELECT COUNT(*) AS total FROM tblservices";
$count_result = mysqli_query($link, $count_sql);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch services (with limit & offset)
$search = isset($_GET['search']) ? mysqli_real_escape_string($link, $_GET['search']) : '';
$where = $search ? "WHERE service_name LIKE '%$search%' OR description LIKE '%$search%'" : "";

$services_result = mysqli_query($link, 
    "SELECT * FROM tblservices $where ORDER BY service_name ASC LIMIT $limit OFFSET $offset"
);

// Update count for pagination
$count_sql = "SELECT COUNT(*) AS total FROM tblservices $where";
$count_result = mysqli_query($link, $count_sql);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $limit);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Services - Admin Dashboard</title>
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
.sidebar h2 i {font-size:28px; vertical-align:middle;}

.main {margin-left:250px; padding:30px; flex:1;}
h1 {margin-bottom:20px; color:#001BB7; font-weight:700;}

/* Table styling */
table {width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);}
th, td {padding:12px 15px; text-align:left; border-bottom:1px solid #e2e8f0;}
th {background:#001BB7; color:white;}
tr:hover {background:#f1f5f9;}
.add-btn {
    padding:8px 14px;
    background:#001BB7;
    color:white;
    border-radius:8px;
    text-decoration:none;
    font-weight:600;
    margin-bottom:15px;
    display:inline-block;
    transition:0.3s;
}
.add-btn:hover {background:#001199;}
.action-buttons {display:flex; gap:8px;}
.action-btn {
    padding:6px 10px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-size:16px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    transition:0.3s;
}
.edit-btn {background:#00b894; color:white;}
.edit-btn:hover {background:#019f7b;}
.delete-btn {background:#d63031; color:white;}
.delete-btn:hover {background:#c12a26;}
.view-btn {background:#001BB7; color:white;}
.view-btn:hover {background:#001199;}
.action-btn i {margin:0; font-size:16px;}

/* Pagination styling */
.pagination{margin-top:15px;}
.pagination .page-item.active .page-link{background-color:#001BB7;border-color:#001BB7;color:white;}
.pagination .page-link{color:#001BB7;}
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
    transform: translateX(-280px);
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
.sidebar.active {
    transform: translateX(0);
}
  table th, table td {font-size: 12px; padding: 8px;}
}
.sidebar::-webkit-scrollbar {
    width: 6px;
}
.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.35);
    border-radius: 10px;
}
.sidebar::-webkit-scrollbar-track {
    background: transparent;
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
<!-- Overlay -->
<div id="overlay" aria-hidden="true"></div>

<div class="main">
    <?php include 'admin_header.php'; ?>
    <h1><i class="fas fa-stethoscope me-2"></i> Services Management</h1>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <!-- Search Form -->
    <form method="GET" class="d-flex flex-grow-1 me-2" style="max-width:1250px;">
        <input type="text" name="search" class="form-control me-2"
               placeholder="Search service..."
               style="height:42px;"
               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
        <button type="submit" class="btn btn-primary d-flex align-items-center justify-content-center" 
        style="background:#001BB7; border:none; height:42px; font-weight: bold;">
    <i class="fas fa-search me-2"></i> Search
</button>

    </form>

    <!-- Add Service -->
    <a href="add_service.php" 
       class="btn btn-primary d-flex align-items-center justify-content-center" 
       style="background:#27ae60; border:none; height:42px; font-weight: bold;">
       + Add New Service
    </a>
</div>


    <table>
        <tr>
            <th>Service Name</th>
            <th>Description</th>
            <th>Date Created</th>
            <th>Actions</th>
        </tr>
        <?php if(mysqli_num_rows($services_result) > 0): ?>
            <?php while($service = mysqli_fetch_assoc($services_result)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                    <td title="<?php echo htmlspecialchars($service['description']); ?>">
    <?php 
        $desc = strip_tags($service['description']); 
        echo (strlen($desc) > 50) ? htmlspecialchars(substr($desc, 0, 50)) . "..." : htmlspecialchars($desc); 
    ?>
</td>

                    <td><?php echo htmlspecialchars($service['date_created']); ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn view-btn" 
                                data-name="<?php echo htmlspecialchars($service['service_name']); ?>" 
                                data-desc="<?php echo htmlspecialchars($service['description']); ?>" 
                                data-date="<?php echo htmlspecialchars($service['date_created']); ?>"
                                data-img1="<?php echo htmlspecialchars($service['image1']); ?>"
                                data-img2="<?php echo htmlspecialchars($service['image2']); ?>"
                                title="View"><i class="fas fa-eye"></i></button>
                            <a href="edit_services.php?id=<?php echo $service['service_id']; ?>" class="action-btn edit-btn" title="Edit"><i class="fas fa-edit"></i></a>
                            <button class="action-btn delete-btn swal-delete" data-id="<?php echo $service['service_id']; ?>" title="Delete"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5" style="text-align:center;">No services found.</td></tr>
        <?php endif; ?>
    </table>

    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-end mt-3">
            <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                <a class="page-link" href="?page=<?php echo max(1, $page-1); ?>">Previous</a>
            </li>
            <li class="page-item active">
                <span class="page-link"><?php echo $page; ?></span>
            </li>
            <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
                <a class="page-link" href="?page=<?php echo min($total_pages, $page+1); ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- SweetAlerts -->
<?php if(isset($_GET['added'])): ?>
<script>Swal.fire({icon:'success', title:'Added!', text:'Service successfully added.', confirmButtonText:'OK'});</script>
<?php endif; ?>
<?php if(isset($_GET['updated'])): ?>
<script>Swal.fire({icon:'success', title:'Updated!', text:'Service successfully updated.', confirmButtonText:'OK'});</script>
<?php endif; ?>
<?php if(isset($_GET['deleted'])): ?>
<script>Swal.fire({icon:'success', title:'Deleted!', text:'Service successfully deleted.', confirmButtonText:'OK'});</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Logout
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

// Delete confirmation
document.querySelectorAll('.swal-delete').forEach(button => {
    button.addEventListener('click', () => {
        const serviceId = button.dataset.id;
        Swal.fire({
            title: 'Are you sure?',
            text: "This service will be permanently deleted!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#001BB7',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'services.php?delete_id=' + serviceId;
            }
        });
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

// View modal
document.querySelectorAll('.view-btn').forEach(button => {
    button.addEventListener('click', () => {
        const name = button.dataset.name;
        const desc = button.dataset.desc;
        const date = button.dataset.date;
        const img1 = button.dataset.img1;
        const img2 = button.dataset.img2;

        document.getElementById('modalServiceName').innerText = name;
        document.getElementById('modalServiceDesc').innerHTML = desc.replace(/\n/g, "<br>");
        document.getElementById('modalServiceDate').innerText = date;

        let imagesHTML = '';
        const images = [img1, img2];
        images.forEach(img => {
            if(img) {
                imagesHTML += `<img src="${img}" alt="Service Image" style="max-width:48%; height:200px; object-fit:cover; border-radius:8px; margin:5px;">`;
            }
        });
        document.getElementById('modalServiceImages').innerHTML = imagesHTML;

        const myModal = new bootstrap.Modal(document.getElementById('serviceModal'));
        myModal.show();
    });
});
</script>

<!-- Service View Modal -->
<div class="modal fade" id="serviceModal" tabindex="-1" aria-labelledby="serviceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
      <div class="modal-header" style="background:#001BB7; color:white; border-bottom:none;">
        <h5 class="modal-title" id="serviceModalLabel"><i class="fas fa-stethoscope me-2"></i> Service Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:25px;">
        <p><strong>Service Name:</strong> <span id="modalServiceName"></span></p>
        <p><strong>Description:</strong> <span id="modalServiceDesc"></span></p>
        <p><strong>Date Created:</strong> <span id="modalServiceDate"></span></p>
        <div id="modalServiceImages" style="display:flex; flex-wrap:wrap; gap:10px; margin-top:15px;"></div>
      </div>
      <div class="modal-footer" style="border-top:none;">
        <button type="button" class="btn" style="background:#001BB7; color:white;" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

</body>
</html>
