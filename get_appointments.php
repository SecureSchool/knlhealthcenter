<?php
session_start();
require_once "config.php";

// Only allow staff
if(!isset($_SESSION['staff_id'])){
    http_response_code(403);
    exit;
}

// Fetch all appointments
$sql = "
    SELECT a.appointment_id, a.appointment_date, a.appointment_time,
           p.full_name AS patient_name,
           s.service_name,
           d.fullname AS doctor_name,
           a.status
    FROM tblappointments a
    LEFT JOIN tblpatients p ON a.patient_id = p.patient_id
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    LEFT JOIN tbldoctors d ON a.doctor_assigned = d.doctor_id
    ORDER BY a.appointment_date, a.appointment_time
";

$result = mysqli_query($link, $sql);

$events = [];
while($row = mysqli_fetch_assoc($result)){
    $title = $row['patient_name'] . ' - ' . $row['service_name'] . ' - ' . $row['appointment_time'];

    // Set color based on status
    switch($row['status']){
        case 'Pending':
            $color = '#FFC107'; // Yellow
            $textColor = '#000';
            break;
        case 'Approved':
            $color = '#28A745'; // Green
            $textColor = '#fff';
            break;
        case 'Cancelled':
            $color = '#DC3545'; // Red
            $textColor = '#fff';
            break;
        case 'Dispensed':
            $color = '#17a2b8'; // Blue-ish
            $textColor = '#fff';
            break;
        case 'Completed':
            $color = '#6c757d'; // Grey
            $textColor = '#fff';
            break;
        default:
            $color = '#6c757d';
            $textColor = '#fff';
    }

    $events[] = [
        'title' => $title,
        'start' => $row['appointment_date'] . 'T' . $row['appointment_time'],
        'color' => $color,
        'textColor' => $textColor,
        'status' => $row['status'],
        'time' => $row['appointment_time'],
        'patient' => $row['patient_name'],
        'service' => $row['service_name'],
        'doctor' => $row['doctor_name'] ?? 'Not assigned'
    ];
}

header('Content-Type: application/json');
echo json_encode($events);
