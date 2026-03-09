<?php
session_start();
require_once "config.php";

if(!isset($_SESSION['doctor_id'])) exit;

$doctor_id = $_SESSION['doctor_id'];

$sql = "SELECT a.appointment_date, a.appointment_time, a.status, 
        p.full_name AS patient_name, s.service_name 
        FROM tblappointments a
        INNER JOIN tblpatients p ON a.patient_id = p.patient_id
        INNER JOIN tblservices s ON a.service_id = s.service_id
        WHERE a.doctor_assigned = '$doctor_id'";

$res = mysqli_query($link, $sql);

$events = [];

while($row = mysqli_fetch_assoc($res)){
    $events[] = [
        'title' => $row['service_name'],
        'start' => $row['appointment_date'].'T'.$row['appointment_time'],
        'status' => $row['status'],
        'patient' => $row['patient_name'],
        'service' => $row['service_name'],
        'time' => $row['appointment_time'],
    ];
}

header('Content-Type: application/json');
echo json_encode($events);
?>
