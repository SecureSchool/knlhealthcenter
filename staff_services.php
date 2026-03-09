<?php
session_start();
require_once "config.php";

// Redirect if not logged in as staff
if(!isset($_SESSION['staff_id'])){
    header("Location: login.php");
    exit;
}

$staff_name = $_SESSION['staff_name'];

// Fetch all services
$services = mysqli_query($link, "SELECT * FROM tblservices ORDER BY service_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Services</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

/* Reset */
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b; overflow-x:hidden;}

/* Sidebar */
.sidebar {
    width:250px;
    background: var(--sidebar-bg);
    color: var(--text-color);
    position:fixed;
    height:100%;
    padding:25px 15px;
    display:flex;
    flex-direction:column;
    transition: transform 0.3s ease;
    z-index: 1000;
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
    margin:8px 0;
    text-decoration:none;
    border-radius:10px;
    transition:0.3s;
    font-weight:500;
}
.sidebar a i {
    margin-right:12px;
    font-size:18px;
}
.sidebar a:hover {
    background: var(--sidebar-hover);
    padding-left:24px;
    color:#fff;
}
.sidebar a.active {
    background: rgba(255,255,255,0.25);
    font-weight:600;
}

/* Hamburger */
.hamburger {
    display:none;
    position:fixed;
    top:15px;
    left:15px;
    font-size:20px;
    color: var(--primary-color);
    background:#fff;
    border:none;
    border-radius:8px;
    padding:8px 10px;
    cursor:pointer;
    z-index:1100;
}

/* Overlay */
#overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.25s ease, visibility 0.25s;
    z-index: 900;
}
#overlay.active {
    opacity:1;
    visibility:visible;
}

/* Main */
.main {
    margin-left:250px;
    padding:40px;
    flex:1;
    transition: margin-left 0.3s;
}

/* Responsive */
@media(max-width:768px){
    .hamburger {display:block;}
    .sidebar {transform:translateX(-280px);}
    .sidebar.active {transform:translateX(0);}
    .main {margin-left:0; padding:20px;}
}

h1 {margin-bottom:20px; color: var(--primary-color); font-weight:700;}

/* Search */
.search-bar {margin-bottom:30px;}
.search-bar input {
    width:100%; max-width:400px;
    padding:12px 16px; font-size:15px;
    border:1px solid #d1d5db; border-radius:8px; outline:none;
}
.search-bar input:focus {border-color: var(--primary-color); box-shadow:0 0 0 3px rgba(0,27,183,0.2);}

/* Service list */
.service-list {display:flex; flex-direction:column; gap:20px;}
.service-card {
    background:var(--card-bg);
    border-radius:12px;
    box-shadow:var(--card-shadow);
    border:1px solid #e5e7eb;
    padding:20px;
    display:flex;
    align-items:flex-start;
    gap:20px;
}
.service-card img {
    width:200px; height:150px;
    object-fit:cover;
    border-radius:10px;
}
.service-card h3 {font-size:18px; font-weight:600; color: var(--primary-color);}
.service-card p {font-size:14px; color:#6b7280; margin:8px 0;}
.service-card small {color:#9ca3af; font-size:12px;}

/* Modal images */
.modal-body img {
    max-width:100%; max-height:400px;
    object-fit:contain;
    display:block;
    margin:0 auto 15px auto;
    border-radius:8px;
}
#overlay.active {
    opacity:1;
    visibility:visible;
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
/* Service cards in dark mode */
body.dark-mode .service-card {
    background: #111b2b !important;
    border: 1px solid rgba(255,255,255,0.18) !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.6) !important;
}

body.dark-mode .service-card h3,
body.dark-mode .service-card p,
body.dark-mode .service-card small {
    color: #fff !important;
}

body.dark-mode .service-card img {
    filter: brightness(0.9);
}

body.dark-mode .service-card .btn-primary {
    background: #001BB7 !important;
    border: none !important;
}
/* H1 - All Medical Services (Dark Mode) */
body.dark-mode h1 {
    color: #fff !important;
}
/* MODAL DARK MODE */
body.dark-mode .modal-content {
    background: #111b2b !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.18) !important;
}

body.dark-mode .modal-header,
body.dark-mode .modal-body,
body.dark-mode .modal-footer {
    background: #111b2b !important;
    color: #fff !important;
}

body.dark-mode .modal-title {
    color: #fff !important;
}

body.dark-mode .btn-close {
    filter: invert(1);
}

body.dark-mode .modal-body p,
body.dark-mode .modal-body small {
    color: #fff !important;
}

body.dark-mode .carousel-control-prev-icon,
body.dark-mode .carousel-control-next-icon {
    filter: invert(1);
}

body.dark-mode .carousel-indicators [data-bs-target] {
    background-color: #fff !important;
}

</style>
</head>
<body>
<button class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
<div id="overlay" onclick="toggleSidebar()"></div>
<div id="overlay"></div>

<div class="sidebar">
    <h2>
  <a href="staff_dashboard.php" style="text-decoration:none; color:white; display:flex; align-items:center; justify-content:center;">
    <img src="logo.png" alt="KNL Logo" 
         style="width:45px; height:45px; margin-right:10px; border-radius:50%; object-fit:cover;">
    KNL Health Center
  </a>
</h2>

    <a href="staff_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="staff_profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="patient_staff.php"><i class="fas fa-users"></i> Patients</a>
    <a href="staff_appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
    <a href="staff_services.php" class="active"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="staff_reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>


<div class="main">
    <?php include 'header_staff.php'; ?>
    <h1><i class="fas fa-stethoscope me-2"></i> All Medical Services</h1>

    <div class="search-bar d-flex">
    <input type="text" id="serviceSearch" class="form-control me-2" placeholder="Search services...">
    <button class="btn btn-primary" id="searchBtn" style="background: #001BB7;"><i class="fas fa-search"></i> Search</button>
</div>


    <div class="service-list" id="serviceList">
        <?php if(mysqli_num_rows($services) > 0): ?>
            <?php while($service = mysqli_fetch_assoc($services)): ?>
                <?php
                $images = [];
                if (!empty($service['image1'])) $images[] = $service['image1'];
                if (!empty($service['image2'])) $images[] = $service['image2'];
                if (!empty($service['image3'])) $images[] = $service['image3'];
                if (empty($images)) $images[] = "uploads/services/default_service.png";

                $firstImg = $images[0];
                $shortDesc = substr($service['description'],0,120) . (strlen($service['description'])>120 ? '...' : '');
                ?>
                <div class="service-card">
                    <img src="<?php echo htmlspecialchars($firstImg); ?>" alt="service image">
                    <div>
                        <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
                        <p><?php echo htmlspecialchars($shortDesc); ?></p>
                        <small>Added on: <?php echo htmlspecialchars($service['date_created'] ?? 'N/A'); ?></small><br>
                        <button class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#modal<?php echo $service['service_id']; ?>" style = "background: #001BB7;">Read More</button>
                    </div>
                </div>

                <!-- Modal -->
                <div class="modal fade" id="modal<?php echo $service['service_id']; ?>" tabindex="-1">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title"><?php echo htmlspecialchars($service['service_name']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
  <!-- Bootstrap Carousel -->
  <div id="carousel<?php echo $service['service_id']; ?>" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-inner">
      <?php foreach($images as $index => $img): ?>
        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
          <img src="<?php echo htmlspecialchars($img); ?>" 
               alt="service img" 
               class="d-block w-100 rounded"
               style="max-height:400px; object-fit:contain;">
        </div>
      <?php endforeach; ?>
    </div>
    <?php if(count($images) > 1): ?>
  <button class="carousel-control-prev" type="button" data-bs-target="#carousel<?php echo $service['service_id']; ?>" data-bs-slide="prev">
    <span class="carousel-control-prev-icon" aria-hidden="true" style="filter: invert(1);"></span>
    <span class="visually-hidden">Previous</span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#carousel<?php echo $service['service_id']; ?>" data-bs-slide="next">
    <span class="carousel-control-next-icon" aria-hidden="true" style="filter: invert(1);"></span>
    <span class="visually-hidden">Next</span>
  </button>
<?php endif; ?>

  </div>

  <p class="mt-3"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
  <small class="text-muted">Added on: <?php echo htmlspecialchars($service['date_created'] ?? 'N/A'); ?></small>
</div>

                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No services available at the moment.</p>
        <?php endif; ?>
    </div>
</div>
<script>
function performSearch() {
    const searchValue = document.getElementById('serviceSearch').value.toLowerCase();
    const cards = document.querySelectorAll('.service-card');
    cards.forEach(card => {
        const name = card.querySelector('h3').textContent.toLowerCase();
        const desc = card.querySelector('p').textContent.toLowerCase();
        card.style.display = (name.includes(searchValue) || desc.includes(searchValue)) ? 'flex' : 'none';
    });
}

// Search button click
document.getElementById('searchBtn').addEventListener('click', performSearch);

// Enter key press
document.getElementById('serviceSearch').addEventListener('keydown', function(e) {
    if (e.key === "Enter") {
        e.preventDefault(); // para hindi mag-refresh form
        performSearch();
    }
});

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
document.getElementById('logoutBtn').addEventListener('click',function(e){
    e.preventDefault();
    Swal.fire({
        title:'Are you sure?',
        text:"You will be logged out from the system.",
        icon:'warning',
        showCancelButton:true,
        confirmButtonColor:'#001BB7',
        cancelButtonColor:'#d33',
        confirmButtonText:'Yes, log me out',
        cancelButtonText:'Cancel'
    }).then((result)=>{if(result.isConfirmed){window.location.href='logout.php';}});
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
