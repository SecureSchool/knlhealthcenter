<?php
session_start();
require_once "config.php";

// Check admin login
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

// Get filters
$search_name = $_GET['search_name'] ?? '';
$status_filter = $_GET['status'] ?? '';
$service_filter = $_GET['service'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=appointments_list.csv');

// Open output stream
$output = fopen('php://output', 'w');

// Column headers
fputcsv($output, ['Appointment ID','Patient','Doctor','Service','Date','Time','Status','Appointment Type','Queue #']);

// Fetch appointments
$sql = "SELECT a.appointment_id, p.full_name AS patient_name, d.fullname AS doctor_name, 
               s.service_name, a.appointment_date, a.appointment_time, a.status,
               a.appointment_type, a.queue_number
        FROM tblappointments a
        JOIN tblpatients p ON a.patient_id = p.patient_id
        LEFT JOIN tbldoctors d ON a.doctor_assigned = d.doctor_id
        LEFT JOIN tblservices s ON a.service_id = s.service_id
        WHERE 1";

if($search_name){
    $safe = mysqli_real_escape_string($link, $search_name);
    $sql .= " AND p.full_name LIKE '%$safe%'";
}
if($status_filter){
    $safe = mysqli_real_escape_string($link, $status_filter);
    $sql .= " AND a.status='$safe'";
}
if($service_filter){
    $safe = mysqli_real_escape_string($link, $service_filter);
    $sql .= " AND a.service_id='$safe'";
}
if($start_date && $end_date){
    $sql .= " AND a.appointment_date BETWEEN '$start_date' AND '$end_date'";
} elseif($start_date){
    $sql .= " AND a.appointment_date >= '$start_date'";
} elseif($end_date){
    $sql .= " AND a.appointment_date <= '$end_date'";
}

$sql .= " ORDER BY a.date_created DESC";

$result = mysqli_query($link, $sql);

// Output rows
while($row = mysqli_fetch_assoc($result)){
    fputcsv($output, [
        $row['appointment_id'],
        $row['patient_name'],
        $row['doctor_name'] ?? 'Not assigned',
        $row['service_name'] ?? 'N/A',
        $row['appointment_date'],
        $row['appointment_time'],
        $row['status'],
        $row['appointment_type'] ?? 'N/A',
        $row['queue_number'] ?? '—'
    ]);
}

fclose($output);
exit;
