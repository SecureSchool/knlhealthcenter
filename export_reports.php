<?php
session_start();
require_once "config.php";

// Only allow staff
if(!isset($_SESSION['staff_id'])){
    header("Location: login.php");
    exit;
}

// Get filters from GET parameters (optional)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-6 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;

// Build query
$query = "
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, 
           p.full_name AS patient_name, s.service_name, a.status
    FROM tblappointments a
    LEFT JOIN tblpatients p ON a.patient_id = p.patient_id
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    WHERE appointment_date BETWEEN '$start_date' AND '$end_date'
";

// Apply service filter
if($service_id > 0){
    $query .= " AND a.service_id = $service_id";
}

$query .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$result = mysqli_query($link, $query);

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=appointments_report.csv');

// Open output stream
$output = fopen('php://output', 'w');

// CSV column headers
fputcsv($output, ['Appointment ID', 'Date', 'Time', 'Patient Name', 'Service', 'Status']);

// Output rows
$pending = $completed = 0;
while($row = mysqli_fetch_assoc($result)){
    fputcsv($output, [
        $row['appointment_id'],
        $row['appointment_date'],
        $row['appointment_time'],
        $row['patient_name'],
        $row['service_name'],
        $row['status']
    ]);

    // Count status for summary
    switch($row['status']){
        case 'Pending': $pending++; break;
        case 'Completed': $completed++; break;
        // No-Show removed
    }
}

// Add summary stats at the bottom
fputcsv($output, []); // empty row
fputcsv($output, ['Summary Stats']);
fputcsv($output, ['Total Pending', $pending]);
fputcsv($output, ['Total Completed', $completed]);

fclose($output);
exit;
