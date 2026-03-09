<?php
session_start();
require_once "config.php";

header('Content-Type: application/json');

if(!isset($_SESSION['patient_id'])) {
    echo json_encode(['success'=>false,'message'=>'Not logged in']);
    exit;
}

$patient_id = $_SESSION['patient_id'];
$appointment_id = intval($_POST['appointment_id'] ?? 0);
$status = $_POST['status'] ?? '';
$cancel_reason = mysqli_real_escape_string($link, $_POST['cancel_reason'] ?? '');

if(!$appointment_id || !$status) {
    echo json_encode(['success'=>false,'message'=>'Invalid data']);
    exit;
}

if($status === 'Cancelled') {
    $sql = "UPDATE tblappointments 
            SET status='Cancelled', cancel_reason='$cancel_reason' 
            WHERE appointment_id='$appointment_id' AND patient_id='$patient_id'";
} else {
    $sql = "UPDATE tblappointments 
            SET status='$status' 
            WHERE appointment_id='$appointment_id' AND patient_id='$patient_id'";
}

if(mysqli_query($link, $sql)) {
    echo json_encode(['success'=>true, 'message'=>"Appointment status updated to $status"]);
} else {
    echo json_encode(['success'=>false,'message'=>'Database error: '.mysqli_error($link)]);
}
