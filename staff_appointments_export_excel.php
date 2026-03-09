<?php
session_start();
require_once "config.php";

if(!isset($_SESSION['staff_id'])){
    header("Location: login.php");
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=staff_appointments_export_excel.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Appointment ID','Patient','Service','Type','Queue #','Date','Time','Doctor','Status','Assigned By','Notes']);

$sql = "SELECT 
            a.appointment_id,
            p.full_name AS patient_name,
            s.service_name AS service,
            a.appointment_type,
            a.queue_number,
            a.appointment_date,
            a.appointment_time,
            a.doctor_assigned,
            a.status,
            a.assignedby,
            a.cancel_reason AS notes
        FROM tblappointments a
        LEFT JOIN tblpatients p ON a.patient_id = p.patient_id
        LEFT JOIN tblservices s ON a.service_id = s.service_id
        ORDER BY a.appointment_date DESC, a.appointment_time ASC";

$result = mysqli_query($link, $sql);

while($row = mysqli_fetch_assoc($result)){
    fputcsv($output, [
        $row['appointment_id'],
        $row['patient_name'],
        $row['service'],
        $row['appointment_type'],
        $row['queue_number'],
        $row['appointment_date'],
        $row['appointment_time'],
        $row['doctor_assigned'],
        $row['status'],
        $row['assignedby'],
        $row['notes']
    ]);
}

fclose($output);
exit;
?>
