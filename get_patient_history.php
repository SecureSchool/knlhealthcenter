<?php
session_start();
require_once "config.php";

$appointment_id = $_GET['appointment_id'] ?? '';

if (!$appointment_id) {
    echo "Invalid appointment ID.";
    exit;
}

// Fetch the patient_id from the appointment
$appt_sql = "SELECT patient_id FROM tblappointments WHERE appointment_id = ?";
$stmt = mysqli_prepare($link, $appt_sql);
mysqli_stmt_bind_param($stmt, "s", $appointment_id);
mysqli_stmt_execute($stmt);
$appt_result = mysqli_stmt_get_result($stmt);
$appt = mysqli_fetch_assoc($appt_result);

if (!$appt) {
    echo "Appointment not found.";
    exit;
}

$patient_id = $appt['patient_id'];

// Fetch medical history for this patient
$sql = "SELECT * FROM tblmedical_history WHERE patient_id = ? ORDER BY date_of_visit DESC";
$stmt2 = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt2, "s", $patient_id);
mysqli_stmt_execute($stmt2);
$result = mysqli_stmt_get_result($stmt2);

if (!$result || mysqli_num_rows($result) === 0) {
    echo "No medical history found for this patient.";
    exit;
}

while ($history = mysqli_fetch_assoc($result)) {
    echo "<b>Date of Visit:</b> " . htmlspecialchars($history['date_of_visit'] ?? '') . "<br>";
    echo "<b>Diagnosis:</b> " . htmlspecialchars($history['diagnosis'] ?? '') . "<br>";
    echo "<b>Treatment:</b> " . htmlspecialchars($history['treatment'] ?? '') . "<br>";
    echo "<b>Prescribed Medication:</b> " . htmlspecialchars($history['prescribed_medication'] ?? '') . "<br>";
    echo "<b>Doctor:</b> " . htmlspecialchars($history['doctor'] ?? '') . "<br>";
    echo "<b>Remarks:</b> " . htmlspecialchars($history['remarks'] ?? '') . "<hr>";
}
?>
