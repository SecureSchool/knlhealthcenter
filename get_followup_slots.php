<?php
session_start();
require_once "config.php";

$patient_id = intval($_GET['patient_id'] ?? 0);
$date = $_GET['date'] ?? '';

if (!$patient_id || !$date) {
    echo json_encode([]);
    exit;
}

// Get the doctor assigned to the last appointment
$res = mysqli_query($link, "SELECT doctor_assigned FROM tblappointments WHERE patient_id = $patient_id ORDER BY appointment_id DESC LIMIT 1");
$doctor = mysqli_fetch_assoc($res)['doctor_assigned'] ?? 0;

if (!$doctor) {
    echo json_encode([]);
    exit;
}

// Get doctor schedules for that date
$schedules = [];
$res2 = mysqli_query($link, "SELECT start_time FROM tbldoctor_schedules WHERE doctor_id = $doctor AND schedule_date = '$date' ORDER BY start_time ASC");
while ($row = mysqli_fetch_assoc($res2)) {
    $schedules[] = $row['start_time'];
}

// Get already booked follow-ups or appointments
$booked_times = [];
$res3 = mysqli_query($link, "SELECT appointment_time FROM tblappointments WHERE doctor_assigned = $doctor AND appointment_date = '$date' AND status IN ('Pending','Completed')");
while ($row = mysqli_fetch_assoc($res3)) {
    $booked_times[] = $row['appointment_time'];
}

// Prepare output
$output = [];
foreach ($schedules as $time) {
    $output[] = [
        'time' => date("h:i A", strtotime($time)), // display 12hr
        'value' => $time, // keep original 24hr for saving
        'booked' => in_array($time, $booked_times)
    ];
}


echo json_encode($output);
