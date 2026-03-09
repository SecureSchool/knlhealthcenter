<?php
session_start();
require_once "config.php";

$service_id = intval($_GET['service_id'] ?? 0);
$date = $_GET['date'] ?? '';

if (!$service_id || !$date) {
    echo json_encode([]);
    exit;
}

// Fetch schedule for this service
$schedules = [];
$res = mysqli_query($link, "SELECT start_time FROM tbldoctor_schedules 
                            WHERE doctor_id = (SELECT doctor_id FROM tblservices WHERE service_id = $service_id) 
                            AND schedule_date = '$date' 
                            ORDER BY start_time ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $schedules[] = $row['start_time'];
}

// Fetch already booked appointments
$booked_times = [];
$res2 = mysqli_query($link, "SELECT appointment_time FROM tblappointments 
                             WHERE service_id = $service_id 
                             AND appointment_date = '$date' 
                             AND status IN ('Pending','Completed')");
while ($row = mysqli_fetch_assoc($res2)) {
    $booked_times[] = $row['appointment_time'];
}

// Prepare output
$output = [];
foreach ($schedules as $time) {
    $output[] = [
        'time' => $time,
        'booked' => in_array($time, $booked_times)
    ];
}

echo json_encode($output);
