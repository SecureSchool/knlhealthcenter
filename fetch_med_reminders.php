<?php
session_start();
require_once "config.php";

if(!isset($_SESSION['patient_id'])){
    echo json_encode([]);
    exit;
}

$patient_id = $_SESSION['patient_id'];

// Fetch all active prescriptions
$sql = "SELECT prescription_id, medication, dosage, instructions, frequency, start_date, end_date, last_taken
        FROM tblprescriptions
        WHERE patient_id = ? AND end_date >= CURDATE()
        ORDER BY start_date ASC";

$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $patient_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$meds = [];
while($row = mysqli_fetch_assoc($result)){

    $now = new DateTime();
    $last_taken = $row['last_taken'] ? new DateTime($row['last_taken']) : null;
    $start = new DateTime($row['start_date']);

    // Determine interval based on frequency
    $interval_hours = 24; // default once/day
    if($row['frequency'] == "twice_a_day") $interval_hours = 12;
    elseif($row['frequency'] == "every_8_hours") $interval_hours = 8;
    elseif($row['frequency'] == "once_a_day") $interval_hours = 24;

    // Calculate next dose time
    if($last_taken){
        $next_dose = clone $last_taken;
        $next_dose->modify("+{$interval_hours} hours");
    } else {
        $next_dose = clone $start;
    }

    // Only include medicines due now or past due
    if($now >= $next_dose && $now <= new DateTime($row['end_date'])){
        $row['next_dose'] = $next_dose->format('Y-m-d H:i:s');
        $meds[] = $row;
    }
}

echo json_encode($meds);
?>
