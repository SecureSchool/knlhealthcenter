<?php
session_start();
require_once "config.php";
header('Content-Type: application/json');

if(!isset($_SESSION['admin_id'])){
    echo json_encode(['error'=>'Unauthorized']);
    exit;
}

if(!isset($_GET['id'])){
    echo json_encode(['error'=>'No ID']);
    exit;
}

$appt_id = intval($_GET['id']);

$sql = "SELECT a.*, p.full_name, p.contact_number, p.email, a.assignedby
        FROM tblappointments a
        JOIN tblpatients p ON a.patient_id = p.patient_id
        WHERE a.appointment_id='$appt_id'";

$res = mysqli_query($link, $sql);
if(mysqli_num_rows($res) > 0){
    $row = mysqli_fetch_assoc($res);
    echo json_encode($row);
}else{
    echo json_encode(['error'=>'Appointment not found']);
}
