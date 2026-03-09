<?php
session_start();
require_once "config.php";

if(!isset($_SESSION['doctor_id'])){
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['doctor_id'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=doctor_patients_list.csv');

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, ['Patient ID','Full Name','Username','Gender','Birthday','Age','Contact','Email','Address','Status','Date Registered']);

$sql = "SELECT DISTINCT p.*
        FROM tblappointments a
        INNER JOIN tblpatients p ON a.patient_id = p.patient_id
        WHERE a.doctor_assigned = '$doctor_id'
        ORDER BY p.full_name ASC";

$result = mysqli_query($link, $sql);

while($row = mysqli_fetch_assoc($result)){
    fputcsv($output, [
        $row['patient_id'],
        $row['full_name'],
        $row['username'],
        $row['gender'],
        $row['birthday'],
        $row['age'],
        $row['contact_number'],
        $row['email'],
        $row['address'],
        $row['status'],
        $row['date_registered']
    ]);
}

fclose($output);
exit;
?>
