<?php
session_start();
require_once "config.php";

// Redirect if not logged in as doctor
if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['doctor_id'];
$doctor_name = $_SESSION['doctor_name'];

// Fetch doctor info
$sql_doctor = "SELECT * FROM tbldoctors WHERE doctor_id = '$doctor_id'";
$res_doctor = mysqli_query($link, $sql_doctor);
$doctor = mysqli_fetch_assoc($res_doctor);

// Quick stats
$total_patients = mysqli_fetch_assoc(mysqli_query($link, "
    SELECT COUNT(DISTINCT patient_id) AS count 
    FROM tblappointments 
    WHERE doctor_assigned = '$doctor_id'
"))['count'];

$total_appointments = mysqli_fetch_assoc(mysqli_query($link, "
    SELECT COUNT(*) AS count 
    FROM tblappointments 
    WHERE doctor_assigned = '$doctor_id'
"))['count'];

// Fetch upcoming 5 appointments
$sql_recent = "
    SELECT a.*, p.full_name AS patient_name, s.service_name 
    FROM tblappointments a
    INNER JOIN tblpatients p ON a.patient_id = p.patient_id
    INNER JOIN tblservices s ON a.service_id = s.service_id
    WHERE a.doctor_assigned = '$doctor_id'
    AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
";
$res_recent = mysqli_query($link, $sql_recent);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Dashboard</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>

<style>
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b;}
.sidebar {width:250px; background:#001BB7; color:#fff; position:fixed; height:100%; padding:25px 15px; display:flex; flex-direction:column;}
.sidebar h2 {text-align:center; margin-bottom:20px; font-size:24px; font-weight:700;}
.sidebar a {color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;}
.sidebar a i {margin-right:12px; font-size:18px;}
.sidebar a:hover {background:rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active {background:rgba(255,255,255,0.25); font-weight:600;}
.sidebar h2 i {
    font-size:28px;
    vertical-align:middle;
}
.main {margin-left:250px; padding:30px; flex:1;}
.card {border-radius:12px; box-shadow:0 4px 8px rgba(0,0,0,0.05);}
.card-header {border-radius:12px 12px 0 0;}
.stat-card {background:#fff; padding:20px; border-radius:12px; text-align:center; box-shadow:0 3px 6px rgba(0,0,0,0.05);}
.stat-card h3 {font-size:28px; margin-bottom:5px; color:#001BB7;}
.stat-card p {margin:0; font-weight:500; color:#555;}
/* HAMBURGER */
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
  z-index: 1200;
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
.stat-card {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 15px;
    background:#fff;
    padding:20px;
    border-radius:12px;
    text-align:left;
    box-shadow:0 3px 6px rgba(0,0,0,0.05);
}

.stat-card i {
    font-size: 32px;
    color: #001BB7;
    flex-shrink: 0;
}

.stat-card h3 {
    margin: 0;
    font-size: 24px;
    font-weight: bold;
    color:#001BB7;
}

.stat-card p {
    margin: 0;
    font-size: 14px;
    color: #555;
}
.service-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 20px;
    border-radius: 15px;
    background: #f0f4ff;
    transition: transform 0.3s, box-shadow 0.3s;
    height: 100%;
}

.service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.service-icon {
    background: #001BB7;
    color: #fff;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin-bottom: 15px;
    font-size: 28px;
}

.service-info h4 {
    font-size: 18px;
    margin-bottom: 8px;
    color: #001BB7;
}

.service-info p {
    font-size: 14px;
    color: #333;
    line-height: 1.4;
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
/* Calendar Card */
#doctorAppointmentsCalendar {
    max-width: 100%;
    margin: 0 auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    padding: 15px;
    font-size: 14px;
}
.fc .fc-day-today { background-color: rgba(0, 27, 183, 0.1); border-radius: 8px; }
.fc .fc-day-past { background-color: #f0f0f0; color: #999; }
.fc-event { border: none; border-radius: 8px !important; font-weight: 500; font-size: 0.85rem; padding: 4px 6px; }
.fc-header-toolbar { margin-bottom: 15px; }
.fc .fc-button { background-color: #001BB7; color: #fff; border: none; border-radius: 6px; font-weight: 500; }
.fc .fc-button:hover { background-color: #0033ff; }

/* Legend */
.calendar-legend { display: flex; gap: 15px; margin-bottom: 10px; flex-wrap: wrap; }
.legend-item { display: flex; align-items: center; gap: 5px; font-size: 0.9rem; }
.legend-color { width: 18px; height: 18px; border-radius: 4px; }
/* FullCalendar Dark Mode */
body.dark-mode #doctorAppointmentsCalendar {
    background: #0b1220 !important;
    color: #fff !important;
}

/* Header toolbar buttons */
body.dark-mode .fc .fc-toolbar-title {
    color: #fff !important;
}
body.dark-mode .fc .fc-button {
    background-color: #001BB7 !important;
    color: #fff !important;
    border: none;
}
body.dark-mode .fc .fc-button:hover {
    background-color: #0033ff !important;
    color: #fff !important;
}

/* Day grid */
body.dark-mode .fc .fc-daygrid-day {
    background-color: #111b2b !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 6px;
}
body.dark-mode .fc .fc-daygrid-day.fc-day-today {
    background-color: rgba(0, 27, 183, 0.15) !important;
}

/* Events */
body.dark-mode .fc-event {
    border: none !important;
    border-radius: 6px !important;
    font-weight: 500;
    font-size: 0.85rem;
    padding: 4px 6px;
    color: #fff !important;
}

/* Event popover */
body.dark-mode .fc-popover {
    background: #111b2b !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 8px;
}

/* TimeGrid view */
body.dark-mode .fc-timegrid-slot {
    border-color: rgba(255,255,255,0.1) !important;
}
body.dark-mode .fc-timegrid-event {
    border: none !important;
    border-radius: 6px !important;
    color: #fff !important;
}

/* Scrollbar for calendar (optional) */
body.dark-mode #doctorAppointmentsCalendar .fc-scroller-harness {
    scrollbar-color: #555 #0b1220 !important;
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
<!-- Hamburger (mobile) -->
<button class="hamburger" aria-label="Open navigation menu" aria-expanded="false">
  <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
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
    <a href="doctor_patients.php"><i class="fas fa-users"></i> My Patients</a>
    <a href="doctor_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="doctor_schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a>
    <a href="doctor_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="doctor_reports.php"><i class="fas fa-chart-line"></i> Reports </a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<!-- Overlay (dim background when menu open) -->
<div id="overlay" aria-hidden="true"></div>

<!-- Main -->
<div class="main">
    <?php include 'doctor_header.php'; ?>
    <!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <div>
                <h3><?php echo $total_patients; ?></h3>
                <p>Total Patients</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <i class="fas fa-calendar-check"></i>
            <div>
                <h3><?php echo $total_appointments; ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
    </div>


<!-- Doctor Appointments Calendar -->
<div class="card mb-4">
    <div class="card-header text-white fw-bold" style="background: #001BB7;">
    My Appointments Calendar
</div>

    <div class="card-body">
        <div class="calendar-legend mb-3">
    <div class="legend-item"><div class="legend-color" style="background:#ffc107"></div> Pending</div>
    <div class="legend-item"><div class="legend-color" style="background:#dc3545"></div> Cancelled</div>
    <div class="legend-item"><div class="legend-color" style="background:#6c757d"></div> Completed</div>
    <div class="legend-item"><div class="legend-color" style="background:#fd7e14"></div> No Show</div> <!-- New -->
</div>

        <div id="doctorAppointmentsCalendar"></div>
    </div>
</div>

    <!-- Upcoming Appointments -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            Upcoming Appointments
        </div>
        <div class="card-body">
            <?php if ($res_recent && mysqli_num_rows($res_recent) > 0): ?>
                <table class="table table-hover text-center align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Patient</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($res_recent)): ?>
    <tr>
        <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
        <td><?php echo htmlspecialchars($row['service_name']); ?></td>
        <td><?php echo htmlspecialchars($row['appointment_date']); ?></td>
        <td><?php echo htmlspecialchars($row['appointment_time']); ?></td>
        <td>
            <?php 
                $status = $row['status'];
                // Set colors for each status
                $statusColors = [
                    'Pending'   => '#ffc107', // yellow
                    'Approved'  => '#28a745', // green
                    'Cancelled' => '#dc3545', // red
                    'Dispensed' => '#17a2b8', // blue
                    'Completed' => '#6c757d', // gray
                    'No Show'   => '#fd7e14', // orange
                ];
                $color = $statusColors[$status] ?? '#0ea5e9'; // fallback: info blue
            ?>
            <span class="badge" style="background: <?php echo $color; ?>; color: #fff;">
                <?php echo htmlspecialchars($status); ?>
            </span>
        </td>
    </tr>
<?php endwhile; ?>

                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No upcoming appointments.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
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

// Update date & time
function updateDateTime() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
    document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
}
setInterval(updateDateTime, 1000);
updateDateTime();


document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('doctorAppointmentsCalendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        initialDate: new Date(),
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: 'get_doctor_appointments.php', // JSON for doctor's appointments
        navLinks: true,
        editable: false,
        eventDisplay: 'block',
        height: 'auto',
        eventDidMount: function(info) {
    switch(info.event.extendedProps.status) {
        case 'Pending': 
            info.el.style.backgroundColor = '#ffc107'; 
            info.el.style.color = '#000'; 
            break;
        case 'Cancelled': 
            info.el.style.backgroundColor = '#dc3545'; 
            info.el.style.color = '#fff'; 
            info.el.style.textDecoration = 'line-through'; 
            break;
        case 'Completed': 
            info.el.style.backgroundColor = '#6c757d'; 
            info.el.style.color = '#fff'; 
            break;
        case 'No Show':  // <-- add this
            info.el.style.backgroundColor = '#fd7e14'; // Orange color
            info.el.style.color = '#fff';
            info.el.style.fontWeight = '600'; // optional: make it stand out
            break;
    }
},

        eventClick: function(info) {
            Swal.fire({
                title: info.event.title,
                html: `
                    <p><strong>Date:</strong> ${info.event.start.toLocaleDateString()}</p>
                    <p><strong>Time:</strong> ${info.event.extendedProps.time}</p>
                    <p><strong>Patient:</strong> ${info.event.extendedProps.patient}</p>
                    <p><strong>Service:</strong> ${info.event.extendedProps.service}</p>
                    <p><strong>Status:</strong> ${info.event.extendedProps.status}</p>
                `,
                icon: 'info',
                confirmButtonColor: '#001BB7'
            });
        }
    });
    calendar.render();
});

</script>

</body>
</html>
