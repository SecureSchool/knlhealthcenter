<?php
session_start();
require_once "config.php";

if(!isset($_SESSION['staff_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit;
}

$appt_id = intval($_POST['id']);
$resched_date = $_POST['reschedule_date'] ?? null;
$resched_time = $_POST['reschedule_time'] ?? null;

if($resched_date && $resched_time){
    $query = "UPDATE tblappointments 
              SET status='Approved', appointment_date='$resched_date', appointment_time='$resched_time', reschedule_date=NULL, reschedule_time=NULL, cancel_reason=NULL
              WHERE appointment_id='$appt_id'";
} else {
    $query = "UPDATE tblappointments 
              SET status='Approved', cancel_reason=NULL 
              WHERE appointment_id='$appt_id'";
}

if(mysqli_query($link,$query)){
    echo json_encode(['status'=>'success','new_date'=>$resched_date,'new_time'=>$resched_time]);
}else{
    echo json_encode(['status'=>'error','message'=>'Failed to approve appointment']);
}
?>
