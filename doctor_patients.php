<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['doctor_id'];

// Handle search & gender filter
$search = $_GET['search'] ?? '';
$gender_filter = $_GET['gender'] ?? '';

$safe_search = mysqli_real_escape_string($link, $search);
$safe_gender = mysqli_real_escape_string($link, $gender_filter);

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total patients
$count_sql = "
    SELECT COUNT(DISTINCT p.patient_id) AS total
    FROM tblappointments a
    INNER JOIN tblpatients p ON a.patient_id = p.patient_id
    WHERE a.doctor_assigned = '$doctor_id'
";
if ($safe_search) {
    $count_sql .= " AND (p.full_name LIKE '%$safe_search%' OR p.username LIKE '%$safe_search%')";
}
if ($safe_gender) {
    $count_sql .= " AND p.gender = '$safe_gender'";
}

$count_result = mysqli_query($link, $count_sql);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch patients
$sql = "
    SELECT DISTINCT p.*
    FROM tblappointments a
    INNER JOIN tblpatients p ON a.patient_id = p.patient_id
    WHERE a.doctor_assigned = '$doctor_id'
";
if ($safe_search) {
    $sql .= " AND (p.full_name LIKE '%$safe_search%' OR p.username LIKE '%$safe_search%')";
}
if ($safe_gender) {
    $sql .= " AND p.gender = '$safe_gender'";
}
$sql .= " ORDER BY p.full_name ASC LIMIT $limit OFFSET $offset";
$result = mysqli_query($link, $sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Patients</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

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
.sidebar {
    width:250px; background: var(--sidebar-bg); color: var(--text-color);
    position:fixed; height:100%; padding:25px 15px; display:flex; flex-direction:column;
}
.sidebar h2 {text-align:center; margin-bottom:20px; font-size:24px; font-weight:700;}
.sidebar a {
    color:#ffffffcc; display:flex; align-items:center; padding:12px 18px;
    margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;
}
.sidebar a i {margin-right:12px; font-size:18px;}
.sidebar a:hover {background: var(--sidebar-hover); padding-left:24px; color:#fff;}
.sidebar a.active {background: rgba(255,255,255,0.25); font-weight:600;}
.sidebar h2 i {
    font-size:28px;
    vertical-align:middle;
}
.main {margin-left:250px; padding:30px; flex:1;}
h1 {margin-bottom:20px; color:#001BB7; font-weight:700;}
.card {background: var(--card-bg); padding:25px; border-radius:12px; box-shadow: var(--card-shadow);}
.top-controls {display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; gap:10px; flex-wrap:wrap;}
.top-controls input, .top-controls select, .top-controls button {padding:8px 12px; border:1px solid #ccc; border-radius:6px; font-size:14px;}
.export-buttons {display:flex; gap:8px;}
table {width:100%; border-collapse:collapse; margin-top:10px;}
table thead {background: var(--primary-color); color:#fff;}
table th, table td {padding:12px 15px; border-bottom:1px solid #e2e8f0; text-align:center;}
table tr:hover {background:#f9fafb;}
.badge {padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; color:#fff;}
.badge.male {background:#3b82f6;}
.badge.female {background:#ec4899;}
.icon-btn {width:38px; height:38px; border:none; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; cursor:pointer; transition:0.2s;}
.icon-btn.view {background: var(--primary-color); color:#fff;}
.icon-btn.view:hover {background:#0010a0;}
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

  table th, table td {font-size: 12px; padding: 8px;}
}
.slot-btn.selected {
    box-shadow: 0 0 0 3px #001BB7 inset;
}
.slot-btn:disabled {
    cursor: not-allowed;
}
.icon-btn {
    display: inline-flex; /* changed from flex to inline-flex */
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    transition: 0.2s;
    margin-right: 5px; /* spacing between buttons */
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
/* Dark Mode - Table Hover */
body.dark-mode table tr:hover {
    background: rgba(255,255,255,0.08) !important;
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

</style>
</head>
<body>
<!-- Hamburger Button (visible on mobile/tablet) -->
<button class="hamburger" aria-label="Open navigation menu" aria-expanded="false">
  <i class="fas fa-bars"></i>
</button>


<div class="sidebar">
    <h2>
  <a href="dashboard_doctors.php" style="text-decoration:none; color:white; display:flex; align-items:center; justify-content:center;">
    <img src="logo.png" alt="KNL Logo" 
         style="width:45px; height:45px; margin-right:10px; border-radius:50%; object-fit:cover;">
    KNL Health Center
  </a>
</h2>

    <a href="dashboard_doctors.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="doctor_profile.php"><i class="fas fa-user-md"></i> Profile</a>
    <a href="doctor_patients.php" class="active"><i class="fas fa-users"></i> My Patients</a>
    <a href="doctor_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="doctor_schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a>
    <a href="doctor_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="doctor_reports.php"><i class="fas fa-chart-line"></i> Reports </a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<!-- Overlay -->
<div id="overlay" aria-hidden="true"></div>

<div class="main">
    <?php include 'doctor_header.php'; ?>
    <h1><i class="fas fa-users"></i> My Patients</h1>

    <!-- Filter form -->
<form method="GET" class="filter-form" style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;">
    <input type="text" name="search" placeholder="Search by name or username" 
           value="<?= htmlspecialchars($search); ?>" 
           style="padding:8px 10px; border-radius:8px; border:1px solid #ddd; flex:10;">

    <select name="gender" style="padding:8px 10px; border-radius:8px; border:1px solid #ddd; flex:1;">
        <option value="">All Gender</option>
        <option value="Male" <?= $gender_filter=='Male'?'selected':''; ?>>Male</option>
        <option value="Female" <?= $gender_filter=='Female'?'selected':''; ?>>Female</option>
    </select>

    <button type="submit" class="filter-btn" style="background:#001BB7; color:white; padding:8px 14px; border:none; border-radius:8px; display:flex; align-items:center; gap:5px;">
        <i class="fas fa-search"></i> Search
    </button>

    <a href="doctors_patients_export_excel.php?search=<?= urlencode($search); ?>&gender=<?= urlencode($gender_filter); ?>" 
       target="_blank" class="btn btn-success me-2">
        <i class="fas fa-file-excel"></i> Export Excel
    </a>

    <a href="doctors_patients_export_pdf.php?search=<?= urlencode($search); ?>&gender=<?= urlencode($gender_filter); ?>" 
       target="_blank" class="btn btn-danger">
        <i class="fas fa-file-pdf"></i> Export PDF
    </a>
</form>
<div class="card">
    <?php if(mysqli_num_rows($result) > 0): ?>
    <table>
        <thead>
            <tr>
        <th>Profile</th> <!-- bagong column -->
        <th>Full Name</th>
        <th>Gender</th>
        <th>Action</th>
        <th>Medical History</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = mysqli_fetch_assoc($result)): ?>
    <tr>
    <td>
        <?php
        $profilePic = 'uploads/profile/default-avatar.png';
        if (!empty($row['profile_picture']) && file_exists('uploads/' . $row['profile_picture'])) {
            $profilePic = 'uploads/' . $row['profile_picture'];
        }
        ?>
        <img src="<?= htmlspecialchars($profilePic); ?>" 
             alt="Profile" 
             style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:1px solid #ccc;">
    </td>
    <td><?= htmlspecialchars($row['full_name']); ?></td>
    <td><span class="badge <?= strtolower($row['gender']); ?>"><?= htmlspecialchars($row['gender']); ?></span></td>
<td style="display:flex; gap:5px; justify-content:center; align-items:center;">
    <!-- VIEW BUTTON -->
    <button type="button" class="icon-btn view viewBtn"
            data-profile="uploads/<?= htmlspecialchars($row['profile_picture']); ?>"
            data-username="<?= htmlspecialchars($row['username']); ?>"
            data-fullname="<?= htmlspecialchars($row['full_name']); ?>"
            data-address="<?= htmlspecialchars($row['address']); ?>"
            data-birthday="<?= htmlspecialchars($row['birthday']); ?>"
            data-age="<?= htmlspecialchars($row['age']); ?>"
            data-contact="<?= htmlspecialchars($row['contact_number']); ?>"
            data-email="<?= htmlspecialchars($row['email']); ?>"
            data-gender="<?= htmlspecialchars($row['gender']); ?>"
            data-status="<?= htmlspecialchars($row['status']); ?>"
            data-date="<?= htmlspecialchars($row['date_registered']); ?>"
            data-color="<?= htmlspecialchars($row['color_code']); ?>">
        <i class="fas fa-eye"></i>
    </button>

    <!-- FOLLOW-UP BUTTON -->
    <button type="button" class="icon-btn followUpBtn"
            data-patient-id="<?= $row['patient_id']; ?>"
            title="Set Follow-up Check-up"
            style="background:#16a34a; color:white;">
        <i class="fas fa-calendar-plus"></i>
    </button>
</td>

    <td>
    <button type="button" class="icon-btn historyPageBtn"
            onclick="window.location.href='patient_history_doctor.php?patient_id=<?= $row['patient_id']; ?>'"
            title="View Appointments & History">
        <i class="fas fa-file-medical"></i>
    </button>
</td>

</tr>

        <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Simple Pagination -->
    <?php if($total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-end mt-3">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?search=<?= urlencode($search); ?>&gender=<?= urlencode($gender_filter); ?>&page=<?= max(1,$page-1); ?>">Previous</a>
            </li>
            <li class="page-item active">
                <span class="page-link"><?= $page; ?></span>
            </li>
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?search=<?= urlencode($search); ?>&gender=<?= urlencode($gender_filter); ?>&page=<?= min($total_pages,$page+1); ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

    <?php else: ?>
        <p style="text-align:center;">No patients found.</p>
    <?php endif; ?>
</div>

<script>
$(".viewBtn").click(function(){
    let btn = $(this);

    // Profile picture fallback
    let profilePath = btn.data("profile") && btn.data("profile").trim() !== "" 
        ? btn.data("profile") 
        : "uploads/profile/default-avatar.png";

    // Status color
    let statusColor = btn.data("status") === 'Verified' ? '#27ae60' : '#c0392b';

    // HTML modal
    Swal.fire({
        width: '90%',
        maxWidth: '900px',
        html: `
        <style>
        .ehr-modal {display:flex; flex-direction:column; font-family:Arial, sans-serif; color:#333; gap:20px;}
        @media(min-width:768px){ .ehr-modal{flex-direction:row;} }

        .ehr-left {flex:0 0 180px; text-align:center;}
        .ehr-left img {border-radius:50%; object-fit:cover; border:3px solid #001BB7; width:140px; height:140px; margin-bottom:10px;}
        .ehr-status {
            display:inline-block;
            margin-top:5px;
            padding:5px 10px;
            border-radius:12px;
            color:white;
            font-weight:600;
            font-size:0.85rem;
            background:${statusColor};
            box-shadow:0 2px 6px rgba(0,0,0,0.2);
        }

        .ehr-right {flex:1; display:flex; flex-direction:column; gap:15px; text-align:left;}

        .ehr-section {border:1px solid #ddd; border-radius:10px; overflow:hidden; background:#fdfdfd; box-shadow:0 2px 6px rgba(0,0,0,0.08);}
        .ehr-section button.ehr-toggle {width:100%; text-align:left; background:#001BB7; color:white; padding:10px 15px; font-size:1rem; border:none; cursor:pointer; display:flex; align-items:center; gap:8px;}
        .ehr-section .ehr-content {padding:10px 15px; display:none; flex-direction:column; gap:5px; text-align:left;}
        </style>

        <div class="ehr-modal">
            <div class="ehr-left">
                <img src="${profilePath}" onerror="this.onerror=null; this.src='uploads/profile/default-avatar.png';">
                <div class="ehr-status">${btn.data("status")}</div>
            </div>

            <div class="ehr-right">
                <div style="font-size:1.8rem; font-weight:bold; margin-bottom:10px; text-align:left;">
                    ${btn.data("fullname")}
                </div>

                <div class="ehr-section">
                    <button class="ehr-toggle"><i class="fas fa-id-card" style="color:#fff; margin-right:5px;"></i> Personal Information</button>
                    <div class="ehr-content">
                        <p><strong>Patient ID:</strong> ${btn.closest("tr").find("td:eq(0)").text()}</p>
                        <p><strong>Username:</strong> ${btn.data("username")}</p>
                        <p><strong>Birthday:</strong> ${btn.data("birthday")}</p>
                        <p><strong>Age:</strong> ${btn.data("age")} years old</p>
                        <p><strong>Gender:</strong> ${btn.data("gender")}</p>
                    </div>
                </div>

                <div class="ehr-section">
                    <button class="ehr-toggle"><i class="fas fa-phone-alt" style="color:#fff; margin-right:5px;"></i> Contact & Residency</button>
                    <div class="ehr-content">
                        <p>
                            <i class="fas fa-map-marker-alt" style="color:#001BB7;"></i> 
                            <strong>Address:</strong> ${btn.data("address")} 
                            <span style="display:inline-block; width:20px; height:20px; background:${btn.data("color")}; border-radius:50%; margin-left:5px; border:1px solid #ccc; vertical-align: middle;"></span>
                        </p>
                        <p><i class="fas fa-envelope" style="color:#001BB7;"></i> <strong>Email:</strong> ${btn.data("email")}</p>
                        <p><i class="fas fa-phone" style="color:#001BB7;"></i> <strong>Contact Number:</strong> ${btn.data("contact")}</p>
                        <p><strong>Date Registered:</strong> ${btn.data("date")}</p>
                    </div>
                </div>
            </div>
        </div>
        `,
        showCloseButton: true,
        showConfirmButton: false,
        didOpen: () => {
            document.querySelectorAll('.ehr-toggle').forEach(btn => {
                btn.addEventListener('click', function(){
                    const content = this.nextElementSibling;
                    content.style.display = content.style.display === 'flex' ? 'none' : 'flex';
                });
            });
        }
    });
});

document.getElementById('logoutBtn').addEventListener('click', function(e){
    e.preventDefault();
    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out from the system.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#001BB7',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, log me out'
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

// View Medical History
$(".historyBtn").click(function(){
    let patientId = $(this).data("patientid");

    $.ajax({
        url: "get_patient_history.php",
        method: "POST",
        data: { patient_id: patientId },
        dataType: "json",
        success: function(data){
            if(data.length === 0){
                Swal.fire("No History", "This patient has no appointment history yet.", "info");
                return;
            }

            let html = '<table style="width:100%; border-collapse:collapse;">';
            html += '<thead><tr style="background:#001BB7; color:#fff;">';
            html += '<th>Date</th><th>Time</th><th>Service</th><th>Status</th><th>Doctor</th></tr></thead><tbody>';

            data.forEach(item => {
                html += `<tr>
                            <td>${item.date}</td>
                            <td>${item.time}</td>
                            <td>${item.service}</td>
                            <td>${item.status}</td>
                            <td>${item.doctor}</td>
                        </tr>`;
            });

            html += '</tbody></table>';

            Swal.fire({
                title: 'Medical History',
                html: html,
                width: 700,
                confirmButtonText: 'Close'
            });
        },
        error: function(){
            Swal.fire("Error", "Failed to fetch medical history.", "error");
        }
    });
});
$(".followUpBtn").click(function() {
    let patientId = $(this).data("patient-id");

    Swal.fire({
        title: 'Set Follow-up Check-up',
        html: `
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div style="display:flex; flex-direction:column;">
                    <label style="font-weight:500;">Duration until follow-up (e.g., 1 week, 3 days):</label>
                    <input type="text" id="followDuration" class="swal2-input" placeholder="e.g. 1 week">
                </div>
                <div style="display:flex; flex-direction:column;">
                    <label style="font-weight:500;">Follow-up date:</label>
                    <input type="date" id="followDate" class="swal2-input">
                </div>
                <div style="display:flex; flex-direction:column;">
                    <label style="font-weight:500;">Available Time Slots:</label>
                    <div id="follow_time_slots" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap:8px; margin-top:6px;"></div>
                </div>
            </div>
        `,
        width: 600,
        confirmButtonText: 'Save',
        showCancelButton: true,
        confirmButtonColor: '#001BB7', // Blue Save button
        cancelButtonColor: '#d33',
        didOpen: () => {
            const durationInput = document.getElementById('followDuration');
            const dateInput = document.getElementById('followDate');
            const slotsDiv = document.getElementById('follow_time_slots');

            function calculateDate() {
                let duration = durationInput.value.trim().toLowerCase();
                let newDate = new Date();

                if(duration.includes("week")) {
                    let num = parseInt(duration) || 1;
                    newDate.setDate(newDate.getDate() + (num * 7));
                } else if(duration.includes("day")) {
                    let num = parseInt(duration) || 1;
                    newDate.setDate(newDate.getDate() + num);
                } else if(duration.includes("month")) {
                    let num = parseInt(duration) || 1;
                    newDate.setMonth(newDate.getMonth() + num);
                }

                let yyyy = newDate.getFullYear();
                let mm = ("0" + (newDate.getMonth() + 1)).slice(-2);
                let dd = ("0" + newDate.getDate()).slice(-2);
                dateInput.value = `${yyyy}-${mm}-${dd}`;

                loadSlots(); // fetch slots for the new date
            }

            function loadSlots() {
                const date = dateInput.value;
                if(!date) return;

                slotsDiv.innerHTML = '';
                slotsDiv.dataset.selectedTime = '';

                fetch(`get_followup_slots.php?patient_id=${patientId}&date=${date}`)
                    .then(res => res.json())
                    .then(slots => {
                        let now = new Date();
                        slots.forEach(slot => {
                            let btn = document.createElement('button');
                            btn.type = 'button';
                            btn.textContent = slot.time;
                            btn.className = 'slot-btn';
                            btn.style.padding = '10px';
                            btn.style.borderRadius = '8px';
                            btn.style.border = 'none';
                            btn.style.fontWeight = '600';
                            btn.style.cursor = 'pointer';
                            btn.style.transition = '0.2s';
                            btn.style.textAlign = 'center';

                            const slotDateTime = new Date(date + ' ' + slot.time);

                            if(slotDateTime < now || slot.booked){
                                btn.disabled = true;
                                btn.style.backgroundColor = slot.booked ? 'red' : 'lightgray';
                                btn.style.color = '#fff';
                            } else {
                                btn.style.backgroundColor = 'green';
                                btn.style.color = '#fff';
                                btn.addEventListener('click', () => {
                                    document.querySelectorAll('#follow_time_slots .slot-btn').forEach(b => b.classList.remove('selected'));
                                    btn.classList.add('selected');
                                    btn.style.boxShadow = '0 0 0 3px #001BB7 inset';
                                    slotsDiv.dataset.selectedTime = slot.time;
                                });
                            }

                            slotsDiv.appendChild(btn);
                        });
                    });
            }

            // Calculate date initially if doctor types duration
            durationInput.addEventListener('input', calculateDate);
        },
        preConfirm: () => {
            const date = document.getElementById('followDate').value;
            const time = document.getElementById('follow_time_slots').dataset.selectedTime;
            if (!date || !time) Swal.showValidationMessage("Please choose a date and an available time slot");
            return { date, time };
        }
    }).then(result => {
        if(result.isConfirmed) {
            $.ajax({
    url: "set_followup.php",
    method: "POST",
    data: {
        patient_id: patientId,
        followup_date: result.value.date,
        followup_time: result.value.time
    },
    success: function(response) {
        let res = JSON.parse(response);
        if(res.status==='success'){
            Swal.fire({
                icon:'success',
                title:'Follow-up Scheduled',
                html:`Queue Number: <b>${res.queue_number}</b><br>
                      Scheduled at: <b>${result.value.date}, ${result.value.time}</b>`
            });
        } else {
            Swal.fire('Error','Failed to set follow-up: '+res.message,'error');
        }
    },
    error: function() {
        Swal.fire('Error','Failed to set follow-up','error');
    }
});

        }
    });
});

</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
