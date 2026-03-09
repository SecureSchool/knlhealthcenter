<?php
session_start();
require_once "config.php";

// Redirect if not logged in as staff
if(!isset($_SESSION['staff_id'])){
    header("Location: login.php");
    exit;
}

$staff_name = $_SESSION['staff_name'];

// Handle search & status filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$registration_filter = $_GET['registration'] ?? '';

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM tblpatients WHERE 1";
if($search) $count_sql .= " AND (full_name LIKE '%$search%' OR username LIKE '%$search%')";
if($status_filter) $count_sql .= " AND status='$status_filter'";
if($registration_filter) $count_sql .= " AND registration_source='$registration_filter'"; // added

$count_result = mysqli_query($link, $count_sql);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Fetch current page records
$sql = "SELECT * FROM tblpatients WHERE 1";
if($search) $sql .= " AND (full_name LIKE '%$search%' OR username LIKE '%$search%')";
if($status_filter) $sql .= " AND status='$status_filter'";
if($registration_filter) $sql .= " AND registration_source='$registration_filter'"; // added
$sql .= " ORDER BY full_name ASC LIMIT $limit OFFSET $offset";

$patients = mysqli_query($link, $sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patients - Staff</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --primary-color: #001BB7;
    --sidebar-bg: #001BB7;
    --sidebar-hover: rgba(255,255,255,0.15);
    --text-color: #fff;
}

/* Reset */
* {
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Inter', sans-serif;
}

body {
    background:#f5f7fa;
    display:flex;
    min-height:100vh;
    color:#1e293b;
}

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

/* Main */
.main {
    margin-left:250px;
    padding:40px;
    flex:1;
}

.card {
    background:#fff;
    padding:25px;
    border-radius:12px;
    box-shadow:0 4px 18px rgba(0,0,0,0.08);
}

h1 {
    margin-bottom:20px;
    color:#001BB7;
    font-weight:700;
}

/* Table */
.table-responsive {
    width:100%;
    overflow-x:auto;
}

table {
    width:100%;
    border-collapse:collapse;
}

table thead {
    background: var(--primary-color);
    color:#fff;
}

table th,
table td {
    padding:12px 15px;
    border-bottom:1px solid #e2e8f0;
    text-align:left;
}

table tr:hover {
    background:#f9fafb;
}

/* Buttons */
.icon-btn {
    width:36px;
    height:36px;
    border:none;
    border-radius:8px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:15px;
    cursor:pointer;
    transition:0.2s;
}

.icon-btn.view {
    background: var(--primary-color);
    color:#fff;
}

.icon-btn.view:hover {
    background:#0010a0;
}

/* Filters */
form.filter-form {
    margin-bottom:15px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

input[type=text], select {
    padding:8px 10px;
    border:1px solid #ddd;
    border-radius:5px;
    flex:1;
    min-width:120px;
}

button.filter-btn {
    padding:8px 12px;
    background:#2575fc;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

/* Pagination */
.pagination {
    margin-top:10px;
}

.pagination .page-item.active .page-link {
    background-color:#001BB7;
    border-color:#001BB7;
    color:white;
}

.pagination .page-link {
    color:#001BB7;
}

/* Hamburger for mobile */
.hamburger {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    font-size: 20px;
    color: var(--primary-color);
    background: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 10px;
    cursor: pointer;
    z-index: 1100;
}

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

/* Mobile styles */
@media(max-width:992px){
    .main {
        padding:20px;
    }

    table th,
    table td {
        padding:10px 8px;
        font-size:13px;
    }
}

@media(max-width:768px){
    .sidebar {
        transform: translateX(-280px);
        transition: transform 0.3s ease;
    }

    .sidebar.active {
        transform: translateX(0);
    }

    .main {
        margin-left:0;
        padding:15px;
    }

    .hamburger {
        display:block;
    }
}

/* Buttons */
.btn-primary {
    background-color: #001BB7;
    border-color: #001BB7;
}

.btn-primary:hover {
    background-color: #0033ff;
    border-color: #0033ff;
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
/* Table hover - dark mode */
body.dark-mode table tr:hover {
    background: rgba(255, 255, 255, 0.06) !important;
}
/* Patient List Title (dark mode) */
body.dark-mode h1 {
    color: #fff !important;
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
    <a href="staff_profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="patient_staff.php" class="active"><i class="fas fa-users"></i> Patients</a>
    <a href="staff_appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
    <a href="staff_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="staff_reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Main -->
<div class="main">
    <?php include 'header_staff.php'; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="fas fa-users"></i> Patients List</h1>
    <a href="walkin_register.php" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Add Walk-in Patient
    </a>
</div>

    <!-- Search & Filter -->
    <form method="GET" class="filter-form">
    <input type="text" name="search" placeholder="Search by name or username" value="<?= htmlspecialchars($search); ?>">
    
    <select name="status">
        <option value="">All Status</option>
        <option value="Resident" <?= $status_filter=='Resident' ? 'selected' : ''; ?>>Resident</option>
        <option value="Non-Resident" <?= $status_filter=='Non-Resident' ? 'selected' : ''; ?>>Non-Resident</option>
    </select>

    <select name="registration">
        <option value="">All Sources</option>
        <option value="Walk In" <?= $registration_filter=='Walk In' ? 'selected' : ''; ?>>Walk-in</option>
        <option value="Online" <?= $registration_filter=='Online' ? 'selected' : ''; ?>>Online</option>
    </select>

    <button type="submit" class="filter-btn" 
        style="background-color:#001BB7; color:#fff; display:flex; align-items:center; gap:5px;">
    <i class="fas fa-search"></i> Search
</button>

<!-- Export buttons -->
    <a href="patients_export_excel.php?search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&registration=<?= urlencode($registration_filter) ?>" 
       class="btn btn-success" style="display:flex; align-items:center; gap:5px;">
        <i class="fas fa-file-excel"></i> Export Excel
    </a>

    <a href="patients_export_pdf.php?search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&registration=<?= urlencode($registration_filter) ?>" 
       class="btn btn-danger" style="display:flex; align-items:center; gap:5px;">
        <i class="fas fa-file-pdf"></i> Export PDF
    </a>
</form>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Profile</th>
                        <th>Full Name</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(mysqli_num_rows($patients) > 0): ?>
                    <?php while($p = mysqli_fetch_assoc($patients)): ?>
                    <tr>
                        <td>
<?php
$profilePic = 'uploads/profile/default-avatar.png'; // fallback
if (!empty($p['profile_picture']) && file_exists('uploads/' . $p['profile_picture'])) {
    $profilePic = 'uploads/' . $p['profile_picture'];
}
?>
<img src="<?= htmlspecialchars($profilePic); ?>" 
     alt="Profile" 
     style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:1px solid #ccc;">
</td>

                        <td><?= htmlspecialchars($p['full_name']); ?></td>
                        <td><?= htmlspecialchars($p['contact_number']); ?></td>
                        <td><?= htmlspecialchars($p['status']); ?></td>
                        <td>
    <div style="display:flex; gap:5px;">
        <!-- View Patient Button -->
        <button type="button" class="icon-btn view viewBtn" title="View Patient"
            data-profile="<?= htmlspecialchars($profilePic); ?>"
            data-username="<?= htmlspecialchars($p['username']); ?>"
            data-fullname="<?= htmlspecialchars($p['full_name']); ?>"
            data-address="<?= htmlspecialchars($p['address']); ?>"
            data-birthday="<?= htmlspecialchars($p['birthday']); ?>"
            data-age="<?= htmlspecialchars($p['age']); ?>"
            data-contact="<?= htmlspecialchars($p['contact_number']); ?>"
            data-email="<?= htmlspecialchars($p['email']); ?>"
            data-gender="<?= htmlspecialchars($p['gender']); ?>"
            data-status="<?= htmlspecialchars($p['status']); ?>"
            data-date="<?= htmlspecialchars($p['date_registered']); ?>"
            data-color="<?= htmlspecialchars($p['color_code']); ?>">
            <i class="fas fa-eye"></i>
        </button>

        <!-- Medical History Button -->
        <a href="patient_history_staff.php?id=<?= $p['patient_id']; ?>" 
           class="icon-btn" 
           title="Medical History" 
           style="background:#10b981; color:#fff; display:flex; align-items:center; justify-content:center;">
           <i class="fas fa-notes-medical"></i>
        </a>
    </div>
</td>

                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center;">No patients found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-end mt-3">
                <li class="page-item <?= $page<=1?'disabled':''; ?>">
                    <a class="page-link" href="?search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&page=<?= max(1,$page-1) ?>">Previous</a>
                </li>
                <li class="page-item active"><span class="page-link"><?= $page ?></span></li>
                <li class="page-item <?= $page>=$total_pages?'disabled':''; ?>">
                    <a class="page-link" href="?search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&page=<?= min($total_pages,$page+1) ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
// View patient details
// View patient details
$(".viewBtn").click(function(){
    let btn = $(this);

    // Profile picture with fallback
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

        .ehr-left {flex:0 0 180px; text-align:center;} /* center column */
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
                        <p><i class="fas fa-home" style="color:#001BB7;"></i> <strong>Status:</strong> ${btn.data("status")}</p>
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
    }).then((result) => { if(result.isConfirmed){ window.location.href='logout.php'; } });
});
</script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
