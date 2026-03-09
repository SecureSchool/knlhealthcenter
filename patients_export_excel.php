<?php
session_start();
require_once "config.php";

// Redirect if not logged in as staff
if(!isset($_SESSION['staff_id'])){
    header("Location: login.php");
    exit;
}

// Filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$registration_filter = $_GET['registration'] ?? '';

// CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=patients_list.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Patient ID', 'Full Name', 'Username', 'Contact', 'Email', 'Status', 'Registration Source', 'Date Registered']);

// Fetch patients
$sql = "SELECT * FROM tblpatients WHERE 1";
if($search) $sql .= " AND (full_name LIKE '%$search%' OR username LIKE '%$search%')";
if($status_filter) $sql .= " AND status='$status_filter'";
if($registration_filter) $sql .= " AND registration_source='$registration_filter'";
$sql .= " ORDER BY full_name ASC";

$result = mysqli_query($link, $sql);

while($row = mysqli_fetch_assoc($result)){
    fputcsv($output, [
        $row['patient_id'],
        $row['full_name'],
        $row['username'],
        $row['contact_number'],
        $row['email'],
        $row['status'],
        $row['registration_source'],
        $row['date_registered']
    ]);
}

fclose($output);
exit;
