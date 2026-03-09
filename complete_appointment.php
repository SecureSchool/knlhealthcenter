<?php
session_start();
require_once "config.php";

// --- LOG FUNCTION ---
function logAction($link, $action, $module, $performedto, $performedby){
    $date = date('Y-m-d');
    $time = date('H:i:s');
    $stmt = mysqli_prepare($link, 
        "INSERT INTO tbllogs (datelog, timelog, action, module, performedto, performedby) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "ssssss", $date, $time, $action, $module, $performedto, $performedby);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// --- Check doctor login ---
if(!isset($_SESSION['doctor_id'])){
    echo "unauthorized";
    exit;
}

$doctor_id = $_SESSION['doctor_id'];
$doctor_name = $_SESSION['doctor_name'] ?? 'Doctor';

// --- Handle complete appointment ---
if(isset($_POST['complete_appointment'])){

    $appointment_id = intval($_POST['appointment_id']);
    $diagnosis = mysqli_real_escape_string($link, trim($_POST['diagnosis']));
    $treatment = mysqli_real_escape_string($link, trim($_POST['treatment']));
    $remarks = mysqli_real_escape_string($link, trim($_POST['remarks']));

    if($appointment_id <= 0 || empty($diagnosis) || empty($treatment)){
        echo "invalid";
        exit;
    }

    // --- Get patient_id from appointment ---
    $res = mysqli_query($link, "SELECT patient_id FROM tblappointments WHERE appointment_id = $appointment_id");
    if(!$res || mysqli_num_rows($res) == 0){
        echo "invalid_patient";
        exit;
    }
    $patient_id = mysqli_fetch_assoc($res)['patient_id'];

    // --- Insert into tblmedical_history ---
    $stmt = mysqli_prepare($link, 
        "INSERT INTO tblmedical_history (appointment_id, patient_id, doctor_id, diagnosis, treatment, notes, created_at)
         SELECT appointment_id, patient_id, ?, ?, ?, ?, NOW() 
         FROM tblappointments WHERE appointment_id=? AND doctor_assigned=?"
    );

    if(!$stmt){
        echo "Prepare failed (history): " . mysqli_error($link);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "isssii", 
        $doctor_id, 
        $diagnosis, 
        $treatment, 
        $remarks, 
        $appointment_id, 
        $doctor_id
    );

    if(!mysqli_stmt_execute($stmt)){
        echo "Execute failed (history): " . mysqli_stmt_error($stmt);
        exit;
    }
    mysqli_stmt_close($stmt);

    // --- Insert medications into tblprescriptions ---
    if(isset($_POST['medication']) && is_array($_POST['medication'])){

        $medications = $_POST['medication'];
        $dosages = $_POST['dosage'];
        $instructions = $_POST['instructions'];
        $frequencies = $_POST['frequency'];
        $start_dates = $_POST['start_date'];
        $durations = $_POST['duration'];

        for($i = 0; $i < count($medications); $i++){

            $med = mysqli_real_escape_string($link, trim($medications[$i]));
            $dos = mysqli_real_escape_string($link, trim($dosages[$i]));
            $inst = mysqli_real_escape_string($link, trim($instructions[$i]));
            $freq = mysqli_real_escape_string($link, trim($frequencies[$i]));
            $start = mysqli_real_escape_string($link, trim($start_dates[$i]));
            $dur = intval($durations[$i]);

            if(empty($med) || empty($dos) || empty($start) || $dur <= 0) continue;

            $end_date = date('Y-m-d', strtotime($start . " +".($dur-1)." days"));

            $stmt = mysqli_prepare($link, 
                "INSERT INTO tblprescriptions 
                (appointment_id, patient_id, doctor_id, medication, dosage, instructions, duration, frequency, start_date, end_date, date_prescribed)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            if(!$stmt){
                echo "Prepare failed (prescription): " . mysqli_error($link);
                exit;
            }

            mysqli_stmt_bind_param(
                $stmt,
                "iiisssisss",
                $appointment_id,
                $patient_id,
                $doctor_id,
                $med,
                $dos,
                $inst,
                $dur,
                $freq,
                $start,
                $end_date
            );

            if(!mysqli_stmt_execute($stmt)){
                echo "Execute failed (prescription): " . mysqli_stmt_error($stmt);
                exit;
            }

            mysqli_stmt_close($stmt);

            // --- Insert Notification for Medication ---
            $notif_title = "New Medication Prescribed";
            $notif_msg = "Your doctor has prescribed: $med ($dos). Please follow instructions.";

            mysqli_query($link, 
                "INSERT INTO tblnotifications (patient_id, title, message, notif_type)
                 VALUES ($patient_id, '$notif_title', '$notif_msg', 'Medication')"
            );

        }
    }

    // --- Update appointment status ---
    $stmt = mysqli_prepare($link, 
        "UPDATE tblappointments SET status='Completed' WHERE appointment_id=? AND doctor_assigned=?"
    );

    if(!$stmt){
        echo "Prepare failed (update): " . mysqli_error($link);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "ii", $appointment_id, $doctor_id);

    if(!mysqli_stmt_execute($stmt)){
        echo "Execute failed (update): " . mysqli_stmt_error($stmt);
        exit;
    }

    mysqli_stmt_close($stmt);

    // --- Log Action ---
    $patres = mysqli_query($link, 
        "SELECT full_name FROM tblpatients WHERE patient_id=$patient_id"
    );
    $patient_name = ($patres && mysqli_num_rows($patres) > 0) 
        ? mysqli_fetch_assoc($patres)['full_name'] 
        : "Patient";

    logAction($link, "Complete", "Doctor Appointments", $patient_name, $doctor_name);

    echo "success";
    exit;
}
?>
