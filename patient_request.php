<?php
session_start();
require_once "config.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
function sendAppointmentEmail($email, $name, $service, $date, $time, $queue) {
    $mail = new PHPMailer(true);

    try {
        // SMTP SETTINGS
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'krusnaligashealthcenter@gmail.com';
        $mail->Password   = 'nwbl trbm yphq dmyl';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // RECEIVER
        $mail->setFrom('krusnaligashealthcenter@gmail.com', 'KNL Health Center');
        $mail->addAddress($email, $name);

        // EMAIL CONTENT
        $mail->isHTML(true);
        $mail->Subject = "Appointment Confirmation - Queue #$queue";
$timeFormatted = date("g:i A", strtotime($time));

        $mail->Body = "
        <div style='font-family:Arial, sans-serif; max-width:600px; margin:0 auto; border:1px solid #e0e0e0; border-radius:8px; overflow:hidden;'>
            <div style='background-color:#001BB7; color:#fff; padding:20px; text-align:center;'>
                <h1 style='margin:0; font-size:24px;'>KNL Health Center</h1>
                <p style='margin:5px 0 0; font-size:14px;'>Appointment Confirmation</p>
            </div>
            <div style='padding:25px; color:#333; font-size:16px; line-height:1.6;'>
                <p>Hello <strong>$name</strong>,</p>
                <p>Your appointment has been successfully booked. Below are your appointment details:</p>
                
                <table style='width:100%; border-collapse:collapse; margin:20px 0;'>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Service</td>
                        <td style='padding:10px; border:1px solid #ddd;'>$service</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Date</td>
                        <td style='padding:10px; border:1px solid #ddd;'>$date</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Time</td>
        <td style='padding:10px; border:1px solid #ddd;'>$timeFormatted</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Queue Number</td>
                        <td style='padding:10px; border:1px solid #ddd; color:#001BB7; font-size:20px; font-weight:bold;'>$queue</td>
                    </tr>
                </table>

                <p>Please arrive 10 minutes before your scheduled time. If you have any questions, feel free to contact us.</p>

                <div style='text-align:center; margin-top:30px;'>
                    <a href='mailto:krusnaligashealthcenter@gmail.com' style='text-decoration:none; background:#001BB7; color:#fff; padding:12px 20px; border-radius:5px;'>Contact Support</a>
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

// --- Log function ---
function logAction($link, $action, $module, $performedto, $performedby) {
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

// --- Generate queue number with automatic priority insertion ---
function generateQueueNumber($link, $service_id, $appointment_date, $patient_priority = 0) {

    // ONLY Senior / PWD / Pregnant
    if ($patient_priority == 1) {

        $q = mysqli_query($link, "
            SELECT queue_number FROM tblappointments
            WHERE service_id='$service_id'
              AND appointment_date='$appointment_date'
              AND priority != 1
            ORDER BY queue_number ASC
            LIMIT 1
        ");

        if (mysqli_num_rows($q) == 0) {
            $last_priority = mysqli_query($link, "
                SELECT queue_number FROM tblappointments
                WHERE service_id='$service_id'
                  AND appointment_date='$appointment_date'
                  AND priority = 1
                ORDER BY queue_number DESC
                LIMIT 1
            ");

            return mysqli_num_rows($last_priority) == 0
                ? 1
                : mysqli_fetch_assoc($last_priority)['queue_number'] + 1;

        } else {
            $insert_position = mysqli_fetch_assoc($q)['queue_number'];

            mysqli_query($link, "
                UPDATE tblappointments
                SET queue_number = queue_number + 1
                WHERE service_id='$service_id'
                  AND appointment_date='$appointment_date'
                  AND queue_number >= $insert_position
            ");

            resendShiftedQueueEmails($link, $service_id, $appointment_date, $insert_position);
            return $insert_position;
        }
    }

    // REGULAR
    $q = mysqli_query($link, "
        SELECT queue_number FROM tblappointments
        WHERE service_id='$service_id'
          AND appointment_date='$appointment_date'
        ORDER BY queue_number DESC
        LIMIT 1
    ");

    return mysqli_num_rows($q) == 0
        ? 1
        : mysqli_fetch_assoc($q)['queue_number'] + 1;
}

// --- Fetch patient info ---
$patient_id = $_SESSION['patient_id'];
$patient_result = mysqli_query($link, "SELECT * FROM tblpatients WHERE patient_id='$patient_id'");
$patient = mysqli_fetch_assoc($patient_result);
$patient_priority = intval($patient['priority']); // get priority from tblpatients

$selected_service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : '';
$services = mysqli_query($link, "SELECT * FROM tblservices ORDER BY service_name ASC");

$error = '';
$success = '';
$queue_number = null;
$estimated_ahead = null;
$appointment_type_display = '';

if (isset($_POST['request_appointment']) && isset($_POST['confirmed']) && $_POST['confirmed'] == "1") {
    $service_id = intval($_POST['service_id']);
    $appointment_date = mysqli_real_escape_string($link, $_POST['appointment_date']);
    $appointment_time = mysqli_real_escape_string($link, $_POST['appointment_time']);
    $medicine_list = isset($_POST['medicine_list']) ? mysqli_real_escape_string($link, $_POST['medicine_list']) : '';

    // Get the doctor assigned to the service
    $doctor_result = mysqli_query($link, "SELECT doctor_id FROM tblservices WHERE service_id='$service_id'");
    $doctor_row = mysqli_fetch_assoc($doctor_result);
    $assigned_doctor = $doctor_row['doctor_id'] ?? null;

    if (!$service_id || !$appointment_date || !$appointment_time) {
        $error = "Please fill all required fields.";
    } elseif (!$assigned_doctor) {
        $error = "No doctor is assigned to the selected service.";
    } else {
        $datetime_selected = strtotime("$appointment_date $appointment_time");
        $now = time();
        if ($datetime_selected < $now) {
            $error = "Appointment date and time cannot be in the past.";
        } else {
            // --- Check if patient already has an appointment at the same date & time (any service) ---
            $check_patient_conflict_sql = "SELECT * FROM tblappointments 
                                           WHERE patient_id='$patient_id' 
                                           AND appointment_date='$appointment_date' 
                                           AND appointment_time='$appointment_time'
                                           AND status IN ('Pending','Approved')";
            $conflict_check = mysqli_query($link, $check_patient_conflict_sql);

            if (mysqli_num_rows($conflict_check) > 0) {
                $error = "You already have an appointment at this date and time for another service.";
            } else {
                // --- Check if this service slot is already booked ---
                $check_sql = "SELECT * FROM tblappointments 
                              WHERE service_id='$service_id' 
                              AND appointment_date='$appointment_date' 
                              AND appointment_time='$appointment_time' 
                              AND status IN ('Pending','Approved')";
                $check = mysqli_query($link, $check_sql);

                if (mysqli_num_rows($check) > 0) {
                    $error = "This slot is already booked.";
                } else {
                    // --- Generate queue number with priority ---
                    $queue_number = generateQueueNumber($link, $service_id, $appointment_date, $patient_priority);
                    $appointment_type = mysqli_real_escape_string($link, $_POST['appointment_type'] ?? 'regular');
                    $follow_up_for = !empty($_POST['follow_up_for']) ? intval($_POST['follow_up_for']) : "NULL";

                    // --- Insert appointment ---
                    $insert_sql = "INSERT INTO tblappointments 
                        (patient_id, doctor_assigned, assignedby, service_id, appointment_date, appointment_time, status, date_created, queue_number, priority, appointment_type, follow_up_for)
                        VALUES 
                        ('$patient_id', '$assigned_doctor', 'System', '$service_id', '$appointment_date', '$appointment_time', 'Pending', CURRENT_TIMESTAMP(), '$queue_number', '$patient_priority', '$appointment_type', $follow_up_for)";

                    if (mysqli_query($link, $insert_sql)) {
                        $success = "Appointment submitted! Your Queue Number is <b>$queue_number</b>";

                        // Get service name
                        $service_query = mysqli_query($link, "SELECT service_name FROM tblservices WHERE service_id='$service_id'");
                        $service_row = mysqli_fetch_assoc($service_query);
                        $service_name = $service_row['service_name'];

                        // Send email notification
                        sendAppointmentEmail(
                            $patient['email'],
                            $patient['full_name'],
                            $service_name,
                            $appointment_date,
                            $appointment_time,
                            $queue_number
                        );

                        logAction($link, "Requested Appointment (Queue #$queue_number)", "Appointment", $patient['full_name'], $patient['full_name']);
                        $_POST = [];
                        $selected_service_id = '';

                        // Compute estimated patients ahead
                        $ahead_q = mysqli_query($link, "
                            SELECT COUNT(*) as ahead FROM tblappointments
                            WHERE service_id='$service_id' 
                            AND appointment_date='$appointment_date'
                            AND queue_number < '$queue_number'
                            AND status IN ('Pending','Approved')
                        ");
                        $estimated_ahead = mysqli_fetch_assoc($ahead_q)['ahead'];
                        $appointment_type_display = ucfirst($appointment_type);

                        // Insert into patients_queue for display.php
                        $insert_queue_sql = "INSERT INTO patients_queue 
                            (ticket_no, patient_id, service, type, status, room, created_at)
                            VALUES ('$queue_number', '{$patient['patient_id']}', '$service_id', '$appointment_type', 'pending', '-', NOW())";
                        mysqli_query($link, $insert_queue_sql);
                    }
                }
            }
        }
    }
}


function sendShiftedQueueEmail($email, $name, $service, $date, $newQueue) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'krusnaligashealthcenter@gmail.com';
        $mail->Password   = 'nwbl trbm yphq dmyl';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('krusnaligashealthcenter@gmail.com', 'KNL Health Center');
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = "Updated Queue Number - Priority Adjustment";

        $mail->Body = "
        <div style='font-family:Arial, sans-serif; max-width:600px; margin:0 auto; border:1px solid #e0e0e0; border-radius:8px; overflow:hidden;'>
            <div style='background-color:#e74c3c; color:#fff; padding:20px; text-align:center;'>
                <h1 style='margin:0; font-size:24px;'>KNL Health Center</h1>
                <p style='margin:5px 0 0; font-size:14px;'>Queue Update Notification</p>
            </div>
            <div style='padding:25px; color:#333; font-size:16px; line-height:1.6;'>
                <p>Hello <strong>$name</strong>,</p>
                <p>Your queue number for the service <strong>$service</strong> on <strong>$date</strong> has been updated due to priority adjustments.</p>
                
                <div style='text-align:center; margin:20px 0; font-size:20px; color:#001BB7; font-weight:bold;'>
                    New Queue Number: $newQueue
                </div>

                <p>We apologize for any inconvenience caused. Thank you for your understanding.</p>

                <div style='text-align:center; margin-top:30px;'>
                    <a href='mailto:krusnaligashealthcenter@gmail.com' style='text-decoration:none; background:#001BB7; color:#fff; padding:12px 20px; border-radius:5px;'>Contact Support</a>
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
function resendShiftedQueueEmails($link, $service_id, $appointment_date, $insert_position) {

    $result = mysqli_query($link, "
        SELECT a.queue_number, p.full_name, p.email, s.service_name
        FROM tblappointments a
        JOIN tblpatients p ON a.patient_id = p.patient_id
        JOIN tblservices s ON s.service_id = a.service_id
        WHERE a.service_id='$service_id'
          AND a.appointment_date='$appointment_date'
          AND a.queue_number > $insert_position
        ORDER BY a.queue_number ASC
    ");

    while ($row = mysqli_fetch_assoc($result)) {
        sendShiftedQueueEmail(
            $row['email'],
            $row['full_name'],
            $row['service_name'],
            $appointment_date,
            $row['queue_number']
        );
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Request Appointment</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Choices.js CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">

<!-- Choices.js JS -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<style>
/* Basic Styles */
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b;}
.sidebar {width:250px; background:#001BB7; color:#fff; position:fixed; height:100%; padding:25px 15px; display:flex; flex-direction:column;}
.sidebar h2 {text-align:center; margin-bottom:20px; font-size:24px; font-weight:700;}
.sidebar a {color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;}
.sidebar a i {margin-right:12px; font-size:18px;}
.sidebar a:hover {background: rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active {background: rgba(255,255,255,0.25); font-weight:600;}
.main {margin-left:250px; padding:30px; flex:1;}
h1 {margin-bottom:10px; color:#001BB7; font-weight:700;}
p {color:#64748b; margin-bottom:25px; font-size:16px;}
.card {background:#fff; padding:25px; border-radius:16px; box-shadow:0 8px 20px rgba(0,0,0,0.08); margin-bottom:25px;}
label {display:block; margin:10px 0 5px; font-weight:500; color:#334155;}
input, select, button {width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px;}
input:focus, select:focus {border-color:#001BB7; outline:none;}
button {background:#001BB7; color:white; border:none; cursor:pointer; border-radius:8px; font-weight:600; margin-top:10px; transition:0.3s;}
button:hover {background:#000fa0;}
button[disabled] {background:#cbd5e1 !important; color:#666 !important; cursor:not-allowed !important;}
/* Sidebar toggle */
.hamburger {display:none; position: fixed; top: 15px; left: 15px; font-size: 22px; color: #001BB7; background:#fff; border:none; border-radius:8px; padding:8px; cursor:pointer; z-index:1200; width: 40px;}
#overlay {position: fixed; inset:0; background:rgba(0,0,0,0.45); opacity:0; visibility:hidden; transition:0.25s; z-index:1050;}
#overlay.active {opacity:1; visibility:visible;}
@media (max-width:768px) {
  .hamburger {display:block;}
  .sidebar {transform:translateX(-280px); transition:0.3s; z-index:1100;}
  .sidebar.active {transform:translateX(0);}
  .main {margin-left:0; padding:20px;}
}
/* Cine-style Slots */
#time_slots {display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-top: 5px;}
.slot-btn {padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.2s; text-align: center;}
.slot-btn.selected { box-shadow: 0 0 0 3px #001BB7 inset; }
.slot-btn:disabled { cursor: not-allowed; }
/* Queue Highlight */
.queue-highlight {background:#27ae60; color:white; padding:20px; text-align:center; border-radius:15px; animation: pulse 1.2s infinite; margin-bottom: 20px;}
.queue-highlight.priority { background:#e74c3c; }
@keyframes pulse {0%{transform:scale(1);}50%{transform:scale(1.05);}100%{transform:scale(1);}}
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
/* Fix dark mode toggle button width */
#darkModeToggle {
    width: auto !important;
    padding: 6px 12px !important;
    margin-top: 0 !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
p{
    color: white;
}
/* =========================
   DARK MODE — CHOICES.JS
   ========================= */

/* main container */
body.dark-mode .choices__inner{
    background:#1a2337 !important;
    color:#fff !important;
    border:1px solid rgba(255,255,255,0.3) !important;
}

/* dropdown list */
body.dark-mode .choices__list--dropdown{
    background:#111b2b !important;
    border:1px solid rgba(255,255,255,0.2) !important;
}

/* dropdown items */
body.dark-mode .choices__item--choice{
    color:#fff !important;
}

/* hover item */
body.dark-mode .choices__item--choice.is-highlighted{
    background:rgba(255,255,255,0.12) !important;
}

/* selected text */
body.dark-mode .choices__item{
    color:#fff !important;
}

/* search input */
body.dark-mode .choices__input{
    background:#1a2337 !important;
    color:#fff !important;
}

/* arrow icon */
body.dark-mode .choices[data-type*=select-one]::after{
    border-color:#fff transparent transparent transparent !important;
}

</style>
</head>
<body>
<button class="hamburger"><i class="fas fa-bars"></i></button>
<div class="sidebar">
    <h2>
    <a href="patient_dashboard.php" style="text-decoration:none; color:white;">
        <img src="logo.png" alt="KNL Logo" 
         style="width:45px; height:45px; margin-right:10px; vertical-align:middle; border-radius:50%; object-fit:cover;">
        KNL Health Center
    </a>
</h2>

    <a href="patient_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="patient_profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="patient_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="patient_request.php" class="active"><i class="fas fa-calendar-plus"></i> Request Appointment</a>
    <a href="patient_appointments.php"><i class="fas fa-calendar-alt"></i> My Appointments</a>
    <a href="patient_medical_history.php"><i class="fas fa-file-medical"></i> Medical History</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<div id="overlay"></div>

<div class="main">
    <?php include 'patient_header.php'; ?>
    <div class="card" style="background:#f0f4ff; border-left:5px solid #001BB7; margin-bottom:25px; height: 250px;">
    <h3 style="color:#001BB7; margin-bottom:10px;">How to Book an Appointment ?</h3>
    <ol style="padding-left:20px; color:#334155; font-size:14px; line-height:1.6;">
        <li>Select the <b>service</b> you want from the dropdown menu.</li>
        <li>Choose the <b>date</b> for your appointment.</li>
        <li>Click on an <b>available time slot</b> (green buttons) to select your preferred time.</li>
        <li>If applicable, specify any <b>medicines needed</b>.</li>
        <li>Click <b>"Request Appointment"</b> and confirm your booking in the pop-up window.</li>
        <li>After successful booking, your <b>queue number</b> will be displayed, and you will receive an email confirmation.</li>
        <li>Please arrive 10 minutes before your scheduled time.</li>
    </ol>
</div>

    <div class="card">
        <form id="appointmentForm" method="POST">
            <input type="hidden" name="confirmed" id="confirmed" value="0">
            <label>Service:</label>
            <select name="service_id" id="service_id" required>
                <option value="">--Select Service--</option>
                <?php while($s=mysqli_fetch_assoc($services)): ?>
                    <option value="<?php echo $s['service_id']; ?>" <?php echo ($s['service_id']==$selected_service_id)?'selected':''; ?>>
                        <?php echo htmlspecialchars($s['service_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <label>Date:</label>
            <input type="date" name="appointment_date" id="appointment_date" required>
            <label>Available Time Slots:</label>
            <div id="time_slots"></div>
            <input type="hidden" name="appointment_time" id="appointment_time">
            <div id="medicine-note" style="display:none;">
                <label>Specify Medicines Needed:</label>
                <input type="text" name="medicine_list" placeholder="Enter medicines">
            </div>
            <input type="hidden" name="appointment_type" value="regular">
            <input type="hidden" name="follow_up_for" value="">
            <button type="button" onclick="confirmBooking()"><i class="fas fa-calendar-plus"></i> Request Appointment</button>
            <input type="hidden" name="request_appointment" value="1">
        </form>
    </div>

   <!-- Queue Highlight -->
<?php if($success && isset($queue_number)): ?>
<div class="card queue-highlight <?php echo ($patient_priority > 0) ? 'priority' : ''; ?>">
    <h2>Queue Number:</h2>
    <h1><?php echo $queue_number; ?></h1>
    <p style="color:black;">
        Scheduled at 
        <?php echo $appointment_date; ?>, 
        <?php echo date("g:i A", strtotime($appointment_time)); ?>
    </p>
</div>
<?php endif; ?>


<script>
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
        title:'Are you sure?',
        text:"You will be logged out from the system.",
        icon:'warning',
        showCancelButton:true,
        confirmButtonColor:'#001BB7',
        cancelButtonColor:'#d33',
        confirmButtonText:'Yes, log me out',
        cancelButtonText:'Cancel'
    }).then((result)=>{ if(result.isConfirmed){ window.location.href='logout.php'; } });
});
function formatTime12Hour(timeStr){
    let [hours, minutes] = timeStr.split(':');
    hours = parseInt(hours);
    let ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // 0 => 12
    return hours + ':' + minutes + ' ' + ampm;
}
// Load available slots
function loadAvailableSlots(){
    let service_id = document.getElementById('service_id').value;
    let date = document.getElementById('appointment_date').value;
    let slotsDiv = document.getElementById('time_slots');
    slotsDiv.innerHTML='';
    document.getElementById('appointment_time').value='';

    if(service_id && date){
        fetch(`get_booked_slots.php?service_id=${service_id}&date=${date}`)
        .then(res=>res.json())
        .then(slots=>{
            let now=new Date();
            slots.forEach(slot=>{
                let btn=document.createElement('button');
                btn.type='button';
                btn.textContent = formatTime12Hour(slot.time);
                btn.className='slot-btn';

                let slotDateTime=new Date(date+' '+slot.time);
                if(slotDateTime<now){
                    btn.disabled=true; btn.style.backgroundColor='lightgray';
                } else if(slot.booked){
                    btn.disabled=true; btn.style.backgroundColor='red'; btn.style.color='#fff';
                } else {
                    btn.style.backgroundColor='green'; btn.style.color='#fff';
                    btn.addEventListener('click', ()=>{
                        document.querySelectorAll('.slot-btn').forEach(b=>b.classList.remove('selected'));
                        btn.classList.add('selected');
                        document.getElementById('appointment_time').value=slot.time;
                    });
                }
                slotsDiv.appendChild(btn);
            });
        });
    }
}
document.getElementById('service_id').addEventListener('change', loadAvailableSlots);
document.getElementById('appointment_date').addEventListener('change', loadAvailableSlots);

// Confirm booking
function confirmBooking(){
    const serviceSelect=document.getElementById('service_id');
    const date=document.getElementById('appointment_date').value;
    const time=document.getElementById('appointment_time').value;
    if(!serviceSelect.value||!date||!time){
        Swal.fire('Incomplete','Please select a service, date, and time slot.','warning'); 
        return;
    }
    Swal.fire({
        title:'Confirm Booking',
        html:`Service: <b>${serviceSelect.options[serviceSelect.selectedIndex].text}</b><br>Date: <b>${date}</b><br>Time: <b>${formatTime12Hour(time)}</b>`,
        icon:'question', showCancelButton:true, confirmButtonText:'Yes, Book it!'
    }).then(result=>{ if(result.isConfirmed){ document.getElementById('confirmed').value='1'; document.getElementById('appointmentForm').submit(); } });
}

// Alerts
<?php if($success): ?>
Swal.fire({icon:'success', title:'Success', html:'<?php echo $success; ?>'});
<?php endif; ?>
<?php if($error): ?>
Swal.fire({icon:'error', title:'Booking Failed', text:'<?php echo $error; ?>'});
<?php endif; ?>
<?php if($success && isset($queue_number)): ?>
Swal.fire({
    icon:'success',
    title:'Appointment Booked!',
    html:`Queue Number: <b><?php echo $queue_number; ?></b><br>
          <p style="color:black;">
Scheduled at <?php echo $appointment_date; ?>, <?php echo date("g:i A", strtotime($appointment_time)); ?>
</p>

});
<?php endif; ?>
</script>
<script>// Initialize Choices.js on Service Select
const serviceSelect = document.getElementById('service_id');
const choices = new Choices(serviceSelect, {
    searchEnabled: true,       // allow search
    itemSelectText: '',        // removes "Press to select"
    shouldSort: false,         // keep original order
    placeholderValue: '--Select Service--'
});
</script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
