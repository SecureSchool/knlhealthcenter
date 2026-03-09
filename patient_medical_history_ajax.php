<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['patient_id']) || !isset($_GET['appointment_id'])) {
    echo "<p>Invalid request.</p>";
    exit;
}

$patient_id = $_SESSION['patient_id'];
$appointment_id = intval($_GET['appointment_id']);

// Verify appointment belongs to patient
$stmt = mysqli_prepare($link, "SELECT * FROM tblappointments WHERE appointment_id=? AND patient_id=?");
mysqli_stmt_bind_param($stmt, "ii", $appointment_id, $patient_id);
mysqli_stmt_execute($stmt);
$appt_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($appt_result) === 0){
    echo "<p>Appointment not found.</p>";
    exit;
}

// Fetch medical history entries
$stmt = mysqli_prepare($link, "
    SELECT mh.*, d.fullname AS doctor_name
    FROM tblmedical_history mh
    LEFT JOIN tbldoctors d ON mh.doctor_id = d.doctor_id
    WHERE mh.appointment_id=?
    ORDER BY mh.created_at DESC
");
mysqli_stmt_bind_param($stmt, "i", $appointment_id);
mysqli_stmt_execute($stmt);
$history_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($history_result) === 0){
    echo "<p>No medical history found for this appointment.</p>";
} else {
    while($h = mysqli_fetch_assoc($history_result)){
        echo "<div class='history-card'>";
        echo "<div class='history-card-header'>Medical History</div>";
        echo "<div class='history-card-body'>";
        echo "<p><span class='label'>Date:</span> ".htmlspecialchars($h['created_at'])."</p>";
        echo "<p><span class='label'>Diagnosis:</span> ".htmlspecialchars($h['diagnosis'])."</p>";
        echo "<p><span class='label'>Treatment:</span> ".htmlspecialchars($h['treatment'])."</p>";
        echo "<p><span class='label'>Notes:</span> ".htmlspecialchars($h['notes'])."</p>";
        echo "<p><span class='label'>Doctor:</span> ".htmlspecialchars($h['doctor_name'])."</p>";
        echo "</div></div>";
    }
}


// Fetch prescriptions for this appointment
$stmt2 = mysqli_prepare($link, "SELECT * FROM tblprescriptions WHERE appointment_id=?");
mysqli_stmt_bind_param($stmt2, "i", $appointment_id);
mysqli_stmt_execute($stmt2);
$presc_result = mysqli_stmt_get_result($stmt2);

if(mysqli_num_rows($presc_result) > 0){
    echo "<div style='background:#fff; border-radius:12px; box-shadow:0 3px 6px rgba(0,0,0,0.1); margin-bottom:15px;'>";
    echo "<div style='background:#28a745; color:#fff; padding:10px 15px; border-radius:12px 12px 0 0; font-weight:600;'>Prescriptions</div>";
    echo "<div class='prescription-table-wrapper'>";

    // FULL BORDER TABLE
    echo "<table class='prescription-table'>";
    echo "<thead>
            <tr>
                <th style='border:1px solid #000; padding:8px;'>Medication</th>
                <th style='border:1px solid #000; padding:8px;'>Dosage</th>
                <th style='border:1px solid #000; padding:8px;'>Instructions</th>
                <th style='border:1px solid #000; padding:8px;'>Duration</th>
                <th style='border:1px solid #000; padding:8px;'>Frequency</th>
                <th style='border:1px solid #000; padding:8px;'>Start Date</th>
                <th style='border:1px solid #000; padding:8px;'>End Date</th>
                <th style='border:1px solid #000; padding:8px;'>Date Prescribed</th>
            </tr>
          </thead><tbody>";

    while($p = mysqli_fetch_assoc($presc_result)){
        echo "<tr>";
        echo "<td style='border:1px solid #000; padding:8px;'>".htmlspecialchars($p['medication'])."</td>";
        echo "<td style='border:1px solid #000; padding:8px;'>".htmlspecialchars($p['dosage'])."</td>";
        echo "<td style='border:1px solid #000; padding:8px;'>".htmlspecialchars($p['instructions'])."</td>";
        echo "<td style='border:1px solid #000; padding:8px;'>".htmlspecialchars($p['duration'])."</td>";
        echo "<td style='border:1px solid #000; padding:8px;'>".htmlspecialchars($p['frequency'])."</td>";
        echo "<td style='border:1px solid #000; padding:8px;'>".htmlspecialchars($p['start_date'])."</td>";
        echo "<td style='border:1px solid #000; padding:8px;'>".htmlspecialchars($p['end_date'])."</td>";
        echo "<td style='border:1px solid #000; padding:8px;'>".htmlspecialchars($p['date_prescribed'])."</td>";
        echo "</tr>";
    }

    echo "</tbody></table></div></div>";
}

?>
