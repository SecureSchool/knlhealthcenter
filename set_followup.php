<?php
session_start();
require_once "config.php";

if (!isset($_POST['patient_id'], $_POST['followup_date'], $_POST['followup_time'])) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Missing required fields']);
    exit;
}

$patient_id = intval($_POST['patient_id']);
$followup_date = mysqli_real_escape_string($link, $_POST['followup_date']);
$followup_time = mysqli_real_escape_string($link, $_POST['followup_time']);

// Fetch patient info for priority
$patient_result = mysqli_query($link, "SELECT * FROM tblpatients WHERE patient_id='$patient_id'");
if(mysqli_num_rows($patient_result)==0){
    echo json_encode(['status'=>'error','message'=>'Patient not found']);
    exit;
}
$patient = mysqli_fetch_assoc($patient_result);
$patient_priority = intval($patient['priority']);

// Assume follow-up service is the last visited service (or choose a default)
$service_result = mysqli_query($link, "
    SELECT service_id FROM tblappointments
    WHERE patient_id='$patient_id'
    ORDER BY appointment_date DESC, appointment_time DESC
    LIMIT 1
");
$service_row = mysqli_fetch_assoc($service_result);
$service_id = $service_row['service_id'] ?? 1; // fallback to 1 if none

// Get doctor assigned
$doctor_result = mysqli_query($link, "SELECT doctor_id FROM tblservices WHERE service_id='$service_id'");
$doctor_row = mysqli_fetch_assoc($doctor_result);
$assigned_doctor = $doctor_row['doctor_id'] ?? null;

if(!$assigned_doctor){
    echo json_encode(['status'=>'error','message'=>'No doctor assigned to service']);
    exit;
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function sendFollowUpEmail($email, $name, $date, $time, $queue_number, $service = 'Follow-up Check-up') {
    $mail = new PHPMailer(true);

    try {
        // SMTP SETTINGS
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'krusnaligashealthcenter@gmail.com';  
        $mail->Password = 'nwbl trbm yphq dmyl';    
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Email FROM & TO
        $mail->setFrom('krusnaligashealthcenter@gmail.com', 'KNL Health Center');
        $mail->addAddress($email, $name);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Your Follow-up Appointment is Scheduled';

        $mail->Body = "
        <div style='font-family:Arial, sans-serif; max-width:600px; margin:0 auto; border:1px solid #e0e0e0; border-radius:8px; overflow:hidden;'>
            <div style='background-color:#001BB7; color:#fff; padding:20px; text-align:center;'>
                <h1 style='margin:0; font-size:24px;'>KNL Health Center</h1>
                <p style='margin:5px 0 0; font-size:14px;'>Follow-up Appointment Confirmation</p>
            </div>
            <div style='padding:25px; color:#333; font-size:16px; line-height:1.6;'>
                <p>Hello <strong>$name</strong>,</p>
                <p>Your follow-up appointment has been scheduled. Here are the details:</p>
                
                <table style='width:100%; border-collapse:collapse; margin:20px 0;'>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Service</td>
                        <td style='padding:10px; border:1px solid #ddd;'>$service</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Date</td>
                        <td style='padding:10px; border:1px solid #ddd;'>$date</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Time</td>
                        <td style='padding:10px; border:1px solid #ddd;'>$time</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Queue Number</td>
                        <td style='padding:10px; border:1px solid #ddd; color:#001BB7; font-size:20px; font-weight:bold;'>$queue_number</td>
                    </tr>
                </table>

                <p>Please arrive 10–15 minutes before your scheduled time. If you have questions, contact us anytime.</p>

                <div style='text-align:center; margin-top:30px;'>
                    <a href='mailto:krusnaligashealthcenter@gmail.com' style='text-decoration:none; background:#001BB7; color:#fff; padding:12px 20px; border-radius:5px;'>Contact Support</a>
                </div>
            </div>
            <div style='background:#f0f0f0; color:#555; font-size:12px; text-align:center; padding:15px;'>
                KNL Health Center • Brgy Krus na Ligas, Quezon City<br>
                &copy; ".date('Y')." KNL Health Center. All rights reserved.
            </div>
        </div>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return false;
    }
}
function generateQueueNumber($link, $service_id, $appointment_date, $patient_priority = 0) {

    // ===============================
    // PRIORITY PATIENT (Senior / PWD / Pregnant)
    // priority = 1 ONLY
    // ===============================
    if ($patient_priority == 1) {

        // Hanapin ang pinakaunang REGULAR patient
        $q = mysqli_query($link, "
            SELECT queue_number
            FROM tblappointments
            WHERE service_id = '$service_id'
              AND appointment_date = '$appointment_date'
              AND priority != 1
            ORDER BY queue_number ASC
            LIMIT 1
        ");

        // Kung wala pang regular → idikit sa dulo ng priority
        if (mysqli_num_rows($q) == 0) {

            $last_priority = mysqli_query($link, "
                SELECT queue_number
                FROM tblappointments
                WHERE service_id = '$service_id'
                  AND appointment_date = '$appointment_date'
                  AND priority = 1
                ORDER BY queue_number DESC
                LIMIT 1
            ");

            // Kung wala pang kahit anong priority
            if (mysqli_num_rows($last_priority) == 0) {
                return 1;
            }

            $row = mysqli_fetch_assoc($last_priority);
            return $row['queue_number'] + 1;
        }

        // May regular → insert BEFORE first regular
        $row = mysqli_fetch_assoc($q);
        $insert_position = $row['queue_number'];

        // I-shift pababa ang lahat ng queue >= insert position
        mysqli_query($link, "
            UPDATE tblappointments
            SET queue_number = queue_number + 1
            WHERE service_id = '$service_id'
              AND appointment_date = '$appointment_date'
              AND queue_number >= $insert_position
        ");

        return $insert_position;
    }

    // ===============================
    // REGULAR / NON-PRIORITY PATIENT
    // priority = 0 or others
    // ===============================
    $q = mysqli_query($link, "
        SELECT queue_number
        FROM tblappointments
        WHERE service_id = '$service_id'
          AND appointment_date = '$appointment_date'
        ORDER BY queue_number DESC
        LIMIT 1
    ");

    // Kung wala pang appointment
    if (mysqli_num_rows($q) == 0) {
        return 1;
    }

    $row = mysqli_fetch_assoc($q);
    return $row['queue_number'] + 1;
}


$queue_number = generateQueueNumber($link, $service_id, $followup_date, $patient_priority);

// Insert follow-up appointment
$insert_sql = "
INSERT INTO tblappointments
(patient_id, doctor_assigned, assignedby, service_id, appointment_date, appointment_time, status, date_created, queue_number, priority, appointment_type, follow_up_for)
VALUES
('$patient_id', '$assigned_doctor', 'System', '$service_id', '$followup_date', '$followup_time', 'Pending', CURRENT_TIMESTAMP(), '$queue_number', '$patient_priority', 'follow-up', NULL)
";

if(mysqli_query($link, $insert_sql)){
    // Optional: insert into patients_queue for display
    mysqli_query($link, "
        INSERT INTO patients_queue (ticket_no, patient_id, service, type, status, room, created_at)
        VALUES ('$queue_number', '$patient_id', '$service_id', 'follow-up', 'pending', '-', NOW())
    ");
    // Fetch patient email & name
    $email = $patient['email'];
    $name = $patient['full_name'];

    // SEND EMAIL NOTIFICATION
    // Convert to 12-hour format (for email only)
$formatted_time = date("h:i A", strtotime($followup_time));

// SEND EMAIL NOTIFICATION
sendFollowUpEmail($email, $name, $followup_date, $formatted_time, $queue_number);

    echo json_encode(['status'=>'success','queue_number'=>$queue_number]);
} else {
    echo json_encode(['status'=>'error','message'=>'Failed to save follow-up']);
}
?>
