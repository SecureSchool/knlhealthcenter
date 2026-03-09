<?php
session_start();
require_once "config.php";

$appointment_id = intval($_POST['appointment_id']);
$action = $_POST['action'] ?? '';
$patient_id = $_SESSION['patient_id'] ?? 0;

if($appointment_id && $action) {
    if($action === 'accept') {
        mysqli_query($link, "
            UPDATE tblappointments 
            SET reschedule_acknowledged=1, status='Reschedule Accepted'
            WHERE appointment_id=$appointment_id AND patient_id=$patient_id
        ");
    } elseif($action === 'cancel') {
        mysqli_query($link, "
            UPDATE tblappointments 
            SET reschedule_acknowledged=1, status='Cancelled'
            WHERE appointment_id=$appointment_id AND patient_id=$patient_id
        ");
    }
}
?>
