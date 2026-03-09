<?php
session_start();
require_once "config.php";

// Redirect if not logged in as doctor
if(!isset($_SESSION['doctor_id'])){
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['doctor_id'];
$doctor_name = $_SESSION['doctor_name'];
// Get upcoming week dates (Monday to Saturday)
$upcomingWeek = [];
for($i=0;$i<6;$i++){
    $upcomingWeek[] = date('Y-m-d', strtotime('next monday +' . $i . ' days'));
}

// Find first day without schedule
$firstEmptyDay = null;
foreach($upcomingWeek as $day){
    $check = mysqli_query($link, "
        SELECT COUNT(*) AS total
        FROM tbldoctor_schedules
        WHERE doctor_id = '$doctor_id'
          AND schedule_date = '$day'
    ");
    $row = mysqli_fetch_assoc($check);
    if($row['total'] == 0){
        $firstEmptyDay = $day;
        break;
    }
}
$services = [];
$serviceQuery = mysqli_query($link, "SELECT service_id, service_name FROM tblservices WHERE doctor_id = '$doctor_id'");

while($row = mysqli_fetch_assoc($serviceQuery)){
    $services[] = $row;
}
$colorPalette = ['#1BB700','#0033FF','#FF8800','#6A0DAD','#00CED1','#FFD700','#FF1493','#008080','#800000','#00FF7F'];
$serviceColors = [];
$i = 0;
foreach($services as $s){
    $serviceColors[$s['service_id']] = $colorPalette[$i % count($colorPalette)];
    $i++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Schedule</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<style>
:root {
    --primary-color: #001BB7;
    --sidebar-bg: #001BB7;
    --sidebar-hover: rgba(255,255,255,0.15);
    --text-color: #fff;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

body {
    background: #f5f7fa;
    display: flex;
    min-height: 100vh;
    color: #1e293b;
}

/* Sidebar */
.sidebar {
    width: 250px;
    background: var(--sidebar-bg);
    color: var(--text-color);
    position: fixed;
    height: 100%;
    padding: 25px 15px;
    display: flex;
    flex-direction: column;
    z-index: 1100;
}

.sidebar h2 {
    text-align: center;
    margin-bottom: 20px;
    font-size: 24px;
    font-weight: 700;
}

.sidebar a {
    color: #ffffffcc;
    display: flex;
    align-items: center;
    padding: 12px 18px;
    margin: 10px 0;
    text-decoration: none;
    border-radius: 12px;
    transition: 0.3s;
    font-weight: 500;
}

.sidebar a i {
    margin-right: 12px;
    font-size: 18px;
}

.sidebar a:hover {
    background: var(--sidebar-hover);
    padding-left: 24px;
    color: #fff;
}

.sidebar a.active {
    background: rgba(255,255,255,0.25);
    font-weight: 600;
}

/* Main */
.main {
    margin-left: 250px;
    padding: 30px;
    flex: 1;
}

/* Hamburger */
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
    z-index: 1200;
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

/* Mobile Responsive */
@media (max-width: 768px) {
    .hamburger {
        display: block;
    }

    .sidebar {
        transform: translateX(-280px);
        transition: transform 0.3s ease;
    }

    .sidebar.active {
        transform: translateX(0);
    }

    .main {
        margin-left: 0;
        padding: 20px;
    }
}

/* SweetAlert fields */
.swal-field {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    margin-bottom: 10px;
}

.swal-field label {
    margin-bottom: 4px;
    font-weight: 500;
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
/* =========================
   CUSTOM SCHEDULE MODAL
   ========================= */

.schedule-modal {
    text-align: left;
    padding: 5px 10px;
}

.schedule-group {
    margin-bottom: 14px;
}

.schedule-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
    display: block;
}

.schedule-input {
    width: 100%;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    font-size: 0.9rem;
    transition: 0.2s ease;
}

.schedule-input:focus {
    border-color: #001BB7;
    box-shadow: 0 0 0 3px rgba(0, 27, 183, 0.15);
    outline: none;
}

/* SweetAlert Buttons */
.swal2-confirm {
    border-radius: 8px !important;
    padding: 8px 18px !important;
    font-weight: 600 !important;
}

.swal2-cancel {
    border-radius: 8px !important;
    padding: 8px 18px !important;
}

/* Dark Mode */
body.dark-mode .schedule-label {
    color: #e2e8f0 !important;
}

body.dark-mode .schedule-input {
    background: #1a2337 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.25) !important;
}

body.dark-mode .schedule-input:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.3) !important;
}
</style>

</head>
<body>

<!-- Hamburger -->
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
<a href="doctor_schedule.php" class="active"><i class="fas fa-calendar-alt"></i> My Schedule</a>
<a href="doctor_services.php"><i class="fas fa-stethoscope"></i> Services</a>
<a href="doctor_reports.php"><i class="fas fa-chart-line"></i> Reports </a>
<a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Overlay -->
<div id="overlay" aria-hidden="true"></div>

<!-- Main -->
<div class="main">
    <?php include 'doctor_header.php'; ?>
<div id="service-legend" class="mb-3 d-flex flex-wrap gap-2">
    <?php foreach($services as $s): 
        $color = $serviceColors[$s['service_id']] ?? '#1BB700'; ?>
        <div class="service-pill" style="background: <?php echo $color; ?>;">
            <?php echo htmlspecialchars($s['service_name']); ?>
        </div>
    <?php endforeach; ?>
    <div class="service-pill" style="background: #FF6B6B;">Booked</div>
    <div class="service-pill" style="background: #FFA500;">Lunch Break</div>
</div>
<div id="calendar"></div>
</div>

<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script>
// Logout
document.getElementById('logoutBtn').addEventListener('click',function(e){
    e.preventDefault();
    Swal.fire({
        title:'Are you sure?',
        text:'You will be logged out.',
        icon:'warning',
        showCancelButton:true,
        confirmButtonColor:'#001BB7',
        cancelButtonColor:'#d33',
        confirmButtonText:'Yes, log out'
    }).then((result)=>{ if(result.isConfirmed) window.location.href='logout.php'; });
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

// FullCalendar
// =======================
// Doctor Schedule JS
// =======================
document.addEventListener('DOMContentLoaded', function () {

    const doctorServices = <?php echo json_encode($services); ?>;
    const calendarEl = document.getElementById('calendar');

    const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'timeGridWeek',
    selectable: true,
    selectLongPressDelay: 100,
    nowIndicator: true,

    slotDuration: '00:30:00',

    slotMinTime: "07:00:00",   // ✅ start view at 7 AM
    slotMaxTime: "17:00:00",   // ✅ end view at 5 PM
    scrollTime: "07:00:00",    // ✅ auto scroll to 7 AM

    allDaySlot: false,
    height: "auto",

    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'timeGridWeek,timeGridDay'
    },

    events: 'doctor_schedule_events.php',

        // =======================
        // ADD SCHEDULE
        // =======================
        select: function(info) {
            let now = new Date();
    let selectedStart = new Date(info.startStr);

    // ❌ Prevent selecting past date/time
    if(selectedStart < now){
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Schedule',
            text: 'You cannot set a schedule on past dates or times.',
            confirmButtonColor: '#001BB7'
        });
        calendar.unselect();
        return;
    }

    if(doctorServices.length === 0){
        Swal.fire('Notice', 'You cannot set a schedule because no service is assigned.', 'warning');
        calendar.unselect();
        return;
    }

    let start = info.startStr;
    let end = info.endStr;

    // Build service options
    let serviceOptions = doctorServices.map(s =>
        `<option value="${s.service_id}">${s.service_name}</option>`
    ).join('');

    Swal.fire({
        title: '<i class="fas fa-calendar-plus" style="color:#001BB7;margin-right:8px;"></i> Add Schedule',
        html: `
<div class="schedule-modal">

    <div class="schedule-group">
        <label class="schedule-label">
            <i class="fas fa-stethoscope me-1"></i> Service
        </label>
        <select id="service" class="schedule-input">
            <option value="">Select Service</option>
            ${serviceOptions}
        </select>
    </div>

    <div class="schedule-group">
        <label class="schedule-label">
            <i class="fas fa-calendar-day me-1"></i> Date
        </label>
        <input type="date" 
               id="date" 
               class="schedule-input" 
               value="${start.split('T')[0]}">
    </div>

    <div class="schedule-group">
        <label class="schedule-label">
            <i class="fas fa-clock me-1"></i> Start Time
        </label>
        <input type="time" 
               id="start" 
               class="schedule-input" 
               value="${start.split('T')[1].substring(0,5)}">
    </div>

    <div class="schedule-group">
        <label class="schedule-label">
            <i class="fas fa-clock me-1"></i> End Time
        </label>
        <input type="time" 
               id="end" 
               class="schedule-input" 
               value="${end.split('T')[1].substring(0,5)}">
    </div>

</div>
`,
        showCancelButton: true,
        confirmButtonText: 'Save Schedule',
        confirmButtonColor: '#001BB7',
cancelButtonColor: '#6b7280',

        preConfirm: () => {
    const service = document.getElementById('service').value;
    const date = document.getElementById('date').value;
    const startTime = document.getElementById('start').value; // e.g., "07:30"
    const endTime = document.getElementById('end').value;     // e.g., "17:00"

    const clinicStart = "07:00";
    const clinicEnd = "17:00";

    // Convert 24-hour string to minutes for comparison
    const timeToMinutes = (t) => {
        const [h, m] = t.split(':').map(Number);
        return h * 60 + m;
    };

    const startMin = timeToMinutes(startTime);
    const endMin = timeToMinutes(endTime);
    const clinicStartMin = timeToMinutes(clinicStart);
    const clinicEndMin = timeToMinutes(clinicEnd);

    // Convert 24h to 12h string
    const to12Hour = (t) => {
        let [h, m] = t.split(':').map(Number);
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12;
        if(h === 0) h = 12;
        return `${h}:${m.toString().padStart(2,'0')} ${ampm}`;
    };

    // Validate working hours
    if(startMin < clinicStartMin || startMin >= clinicEndMin){
        Swal.showValidationMessage(`Start time must be between ${to12Hour(clinicStart)} and ${to12Hour(clinicEnd)}`);
        return false;
    }

    if(endMin <= clinicStartMin || endMin > clinicEndMin){
        Swal.showValidationMessage(`End time must be between ${to12Hour(clinicStart)} and ${to12Hour(clinicEnd)}`);
        return false;
    }

    // Other validations
    if(!service){
        Swal.showValidationMessage('Please select a service');
        return false;
    }

    if(endMin <= startMin){
        Swal.showValidationMessage('End time must be after start time');
        return false;
    }

    if(new Date(`${date}T${startTime}`) < new Date()){
        Swal.showValidationMessage('Start time cannot be in the past');
        return false;
    }

    return { service_id: service, date, start: startTime, end: endTime };
}

    }).then((result)=>{
        if(result.isConfirmed){
            fetch('doctor_schedule_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', data: result.value })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success'){
                    Swal.fire({
    title: 'Saved',
    text: data.msg,
    icon: 'success',
    confirmButtonColor: '#001BB7'
});
                    calendar.refetchEvents();
                } else {
                    Swal.fire('Error', data.msg, 'error');
                }
            });
        }
    });
},

        // =======================
        // DELETE SCHEDULE
        // =======================
        eventClick: function(info){
    // Skip lunch break
    if(info.event.id.startsWith('lunch_break')) return;

    // Extract details from the event
    const title = info.event.title; // e.g., "Available [Consultation]"
    const start = info.event.start; // Date object
    const end = info.event.end;     // Date object
    const service = title.match(/\[(.*)\]/)[1]; // Extract service name from brackets

    // Format date/time nicely
    const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
    const startDateStr = start.toLocaleDateString(undefined, options);
    const startTimeStr = start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    const endTimeStr = end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

    Swal.fire({
        title: 'Schedule Details',
        html: `
            <p><strong>Service:</strong> ${service}</p>
            <p><strong>Date:</strong> ${startDateStr}</p>
            <p><strong>Time:</strong> ${startTimeStr} - ${endTimeStr}</p>
            <p><strong>Status:</strong> ${title.startsWith('Booked') ? 'Booked' : 'Available'}</p>
        `,
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Edit',
        denyButtonText: 'Delete',
        cancelButtonText: 'Close',
        confirmButtonColor: '#001BB7',
        denyButtonColor: '#d33'
    }).then((result)=>{
        if(result.isConfirmed){
            // Edit schedule - you can open your existing Swal form for editing
            openEditScheduleModal(info.event);
        } else if(result.isDenied){
            // Delete schedule
            Swal.fire({
                title: 'Delete Schedule?',
                text: 'This schedule will be removed.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete'
            }).then((res)=>{
                if(res.isConfirmed){
                    fetch('doctor_schedule_action.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'delete',
                            id: info.event.id
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.status === 'success'){
                            Swal.fire({
                                title: 'Deleted',
                                text: data.msg,
                                icon: 'success',
                                confirmButtonColor: '#001BB7'
                            });
                            info.event.remove(); // remove from calendar
                        } else {
                            Swal.fire('Error', data.msg, 'error');
                        }
                    });
                }
            });
        }
    });
}
    });

    calendar.render();

});
<?php if($firstEmptyDay): ?>
Swal.fire({
    title: 'Reminder!',
    text: 'You have not set your schedule for the upcoming week. Let’s add your available slots.',
    icon: 'info',
    showCancelButton: true,
    confirmButtonText: 'Add Schedule',
    cancelButtonText: 'Later',
    confirmButtonColor: '#001BB7',
    cancelButtonColor: '#6b7280'
}).then((result)=>{

    if(result.isConfirmed){

        const doctorServices = <?php echo json_encode($services); ?>;

        if(doctorServices.length === 0){
            Swal.fire('Notice', 'You cannot set a schedule because no service is assigned.', 'warning');
            return;
        }

        let serviceOptions = doctorServices.map(s =>
            `<option value="${s.service_id}">${s.service_name}</option>`
        ).join('');

        let defaultDate = '<?php echo $firstEmptyDay; ?>';

        Swal.fire({
            title: '<i class="fas fa-calendar-plus" style="color:#001BB7;margin-right:8px;"></i> Add Schedule',
            html: `
<div class="schedule-modal">

    <div class="schedule-group">
        <label class="schedule-label">
            <i class="fas fa-stethoscope me-1"></i> Service
        </label>
        <select id="service" class="schedule-input">
            <option value="">Select Service</option>
            ${serviceOptions}
        </select>
    </div>

    <div class="schedule-group">
        <label class="schedule-label">
            <i class="fas fa-calendar-day me-1"></i> Date
        </label>
        <input type="date" 
               id="date" 
               class="schedule-input" 
               value="${defaultDate}">
    </div>

    <div class="schedule-group">
        <label class="schedule-label">
            <i class="fas fa-clock me-1"></i> Start Time
        </label>
        <input type="time" 
               id="start" 
               class="schedule-input" 
               value="07:00">
    </div>

    <div class="schedule-group">
        <label class="schedule-label">
            <i class="fas fa-clock me-1"></i> End Time
        </label>
        <input type="time" 
               id="end" 
               class="schedule-input" 
               value="08:00">
    </div>

</div>
`,
            showCancelButton: true,
            confirmButtonText: 'Save Schedule',
            confirmButtonColor: '#001BB7',
cancelButtonColor: '#6b7280',

            preConfirm: () => {
    const service = document.getElementById('service').value;
    const date = document.getElementById('date').value;
    const startTime = document.getElementById('start').value; // e.g., "07:30"
    const endTime = document.getElementById('end').value;     // e.g., "17:00"

    const clinicStart = "07:00";
    const clinicEnd = "17:00";

    // Convert 24-hour string to minutes for comparison
    const timeToMinutes = (t) => {
        const [h, m] = t.split(':').map(Number);
        return h * 60 + m;
    };

    const startMin = timeToMinutes(startTime);
    const endMin = timeToMinutes(endTime);
    const clinicStartMin = timeToMinutes(clinicStart);
    const clinicEndMin = timeToMinutes(clinicEnd);

    // Convert 24h to 12h string
    const to12Hour = (t) => {
        let [h, m] = t.split(':').map(Number);
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12;
        if(h === 0) h = 12;
        return `${h}:${m.toString().padStart(2,'0')} ${ampm}`;
    };

    // Validate working hours
    if(startMin < clinicStartMin || startMin >= clinicEndMin){
        Swal.showValidationMessage(`Start time must be between ${to12Hour(clinicStart)} and ${to12Hour(clinicEnd)}`);
        return false;
    }

    if(endMin <= clinicStartMin || endMin > clinicEndMin){
        Swal.showValidationMessage(`End time must be between ${to12Hour(clinicStart)} and ${to12Hour(clinicEnd)}`);
        return false;
    }

    // Other validations
    if(!service){
        Swal.showValidationMessage('Please select a service');
        return false;
    }

    if(endMin <= startMin){
        Swal.showValidationMessage('End time must be after start time');
        return false;
    }

    if(new Date(`${date}T${startTime}`) < new Date()){
        Swal.showValidationMessage('Start time cannot be in the past');
        return false;
    }

    return { service_id: service, date, start: startTime, end: endTime };
}
        }).then((res)=>{
            if(res.isConfirmed){
                fetch('doctor_schedule_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'add', 
                        data: res.value 
                    })
                }).then(r=>r.json()).then(r=>{
                    if(r.status=='success'){
                        Swal.fire({
    title: 'Success!',
    text: r.msg,
    icon: 'success',
    confirmButtonColor: '#001BB7'
});
                        location.reload(); // reload para safe
                    }else{
                        Swal.fire('Error', r.msg, 'error');
                    }
                });
            }
        });
    }
});
<?php endif; ?>
function openEditScheduleModal(event) {
    const doctorServices = <?php echo json_encode($services); ?>;

    // Extract current event info
    const currentService = doctorServices.find(s => event.title.includes(s.service_name));
    const serviceId = currentService?.service_id || '';
    const date = event.start.toISOString().split('T')[0];
    const startTime = event.start.toTimeString().substring(0,5);
    const endTime = event.end.toTimeString().substring(0,5);

    const now = new Date();
    const eventStart = new Date(`${date}T${startTime}`);

    // Determine if past or booked
    const isPast = eventStart < now;
    const isBooked = event.title.toLowerCase().startsWith('booked');

    // Build service options
    let serviceOptions = doctorServices.map(s =>
        `<option value="${s.service_id}" ${s.service_id == serviceId ? 'selected' : ''}>${s.service_name}</option>`
    ).join('');

    // Disable inputs if past or booked
    const disabledAttr = (isPast || isBooked) ? 'disabled' : '';

    Swal.fire({
        title: '<i class="fas fa-edit" style="color:#001BB7;margin-right:8px;"></i> Edit Schedule',
        html: `
<div class="schedule-modal">
    ${(isPast || isBooked) ? `<p style="color:red;font-weight:600;">
        ${isBooked ? 'This slot is booked and cannot be edited.' : 'This schedule is in the past and cannot be edited.'}
    </p>` : ''}
    <div class="schedule-group">
        <label class="schedule-label"><i class="fas fa-stethoscope me-1"></i> Service</label>
        <select id="service" class="schedule-input" ${disabledAttr}>
            <option value="">Select Service</option>
            ${serviceOptions}
        </select>
    </div>
    <div class="schedule-group">
        <label class="schedule-label"><i class="fas fa-calendar-day me-1"></i> Date</label>
        <input type="date" id="date" class="schedule-input" value="${date}" ${disabledAttr}>
    </div>
    <div class="schedule-group">
        <label class="schedule-label"><i class="fas fa-clock me-1"></i> Start Time</label>
        <input type="time" id="start" class="schedule-input" value="${startTime}" ${disabledAttr}>
    </div>
    <div class="schedule-group">
        <label class="schedule-label"><i class="fas fa-clock me-1"></i> End Time</label>
        <input type="time" id="end" class="schedule-input" value="${endTime}" ${disabledAttr}>
    </div>
</div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Save Changes',
        confirmButtonColor: '#001BB7',
        cancelButtonColor: '#6b7280',
        showDenyButton: !isPast && !isBooked,
        denyButtonText: 'Delete',
        preConfirm: () => {
            if(isPast || isBooked) return false; // block saving

            const service = document.getElementById('service').value;
            const newDate = document.getElementById('date').value;
            const newStart = document.getElementById('start').value;
            const newEnd = document.getElementById('end').value;

            const clinicStart = "07:00";
            const clinicEnd = "17:00";
            const timeToMinutes = t => t.split(':').map(Number).reduce((h,m)=>h*60+m);
            const startMin = timeToMinutes(newStart);
            const endMin = timeToMinutes(newEnd);
            const clinicStartMin = timeToMinutes(clinicStart);
            const clinicEndMin = timeToMinutes(clinicEnd);

            if(!service) return Swal.showValidationMessage('Please select a service');
            if(endMin <= startMin) return Swal.showValidationMessage('End time must be after start time');
            if(startMin < clinicStartMin || startMin >= clinicEndMin) return Swal.showValidationMessage('Start time must be within clinic hours');
            if(endMin <= clinicStartMin || endMin > clinicEndMin) return Swal.showValidationMessage('End time must be within clinic hours');
            if(new Date(`${newDate}T${newStart}`) < new Date()) return Swal.showValidationMessage('Start time cannot be in the past');

            return { service_id: service, date: newDate, start: newStart, end: newEnd };
        }
    }).then(result => {
        if(result.isConfirmed && !isPast && !isBooked){
            fetch('doctor_schedule_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'edit',
                    id: event.id,
                    data: result.value
                })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success'){
                    Swal.fire({
                        title: 'Updated!',
                        text: data.msg,
                        icon: 'success',
                        confirmButtonColor: '#001BB7'
                    });

                    const newService = doctorServices.find(s => s.service_id == result.value.service_id);
                    const newTitle = `Available [${newService.service_name}]`;
                    event.setProp('title', newTitle);
                    event.setStart(result.value.date + 'T' + result.value.start);
                    event.setEnd(result.value.date + 'T' + result.value.end);

                    const serviceColors = <?php echo json_encode($serviceColors); ?>;
                    const newColor = serviceColors[result.value.service_id] || '#1BB700';
                    event.setProp('color', newColor);
                } else {
                    Swal.fire('Error', data.msg, 'error');
                }
            });
        } else if(result.isDenied && !isPast && !isBooked){
            // delete logic
            Swal.fire({
                title: 'Delete Schedule?',
                text: 'This schedule will be removed.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete'
            }).then(res => {
                if(res.isConfirmed){
                    fetch('doctor_schedule_action.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete', id: event.id })
                    }).then(r=>r.json()).then(r=>{
                        if(r.status=='success'){
                            Swal.fire({
                                title: 'Deleted',
                                text: r.msg,
                                icon: 'success',
                                confirmButtonColor: '#001BB7'
                            });
                            event.remove();
                        } else {
                            Swal.fire('Error', r.msg, 'error');
                        }
                    });
                }
            });
        }
    });
}
</script>
</body>
</html>