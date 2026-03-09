<?php
session_start();
require_once "config.php";

if(!isset($_SESSION['patient_id'])){
    echo "unauthorized";
    exit;
}

$prescription_id = intval($_POST['prescription_id']);
$patient_id = $_SESSION['patient_id'];

if($prescription_id > 0){
    $sql = "UPDATE tblprescriptions SET last_taken = NOW() WHERE prescription_id = ? AND patient_id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $prescription_id, $patient_id);
    if(mysqli_stmt_execute($stmt)){
        echo "success";
    } else {
        echo "error";
    }
} else {
    echo "invalid";
}
?>
