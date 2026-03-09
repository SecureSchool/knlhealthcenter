<?php
session_start();
require_once "config.php"; // your DB connection

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=doctors_list.csv');

// Open output stream
$output = fopen('php://output', 'w');

// Column headers
fputcsv($output, ['Doctor ID', 'Full Name', 'Username', 'Specialization', 'Contact Number', 'Email', 'Status', 'Date Created']);

// Fetch all doctors
$sql = "SELECT * FROM tbldoctors ORDER BY doctor_id DESC";
$result = mysqli_query($link, $sql);

if(mysqli_num_rows($result) > 0){
    while($row = mysqli_fetch_assoc($result)){
        fputcsv($output, [
            $row['doctor_id'],
            $row['fullname'],
            $row['username'],
            $row['specialization'],
            $row['contact_number'],
            $row['email'],
            $row['status'],
            $row['date_created']
        ]);
    }
}

// Close output
fclose($output);
exit;
?>
