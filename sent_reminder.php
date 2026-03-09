<?php
if(!isset($_SESSION)) { session_start(); }
require_once "config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

/* =====================================================
   SEND REMINDER EMAIL FUNCTION
===================================================== */
function sendReminderEmail($email, $name, $service, $date, $time){
    $mail = new PHPMailer(true);

    try{
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'krusnaligashealthcenter@gmail.com';
        $mail->Password   = 'nwbl trbm yphq dmyl';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('krusnaligashealthcenter@gmail.com','KNL Health Center');
        $mail->addAddress($email,$name);

        $mail->isHTML(true);
        $mail->Subject = "Appointment Reminder - Tomorrow";

        $timeFormatted = date("g:i A", strtotime($time));

        $mail->Body = "
        <div style='font-family:Arial;max-width:600px;margin:auto;border:1px solid #ddd;border-radius:8px;overflow:hidden'>
            <div style='background:#001BB7;color:#fff;padding:20px;text-align:center'>
                <h2 style='margin:0'>KNL Health Center</h2>
                <small>Appointment Reminder</small>
            </div>
            <div style='padding:25px;color:#333'>
                Hello <b>$name</b>,<br><br>
                This is a friendly reminder that you have an appointment scheduled <b>tomorrow</b>.<br><br>
                <table style='width:100%;border-collapse:collapse'>
                    <tr>
                        <td style='border:1px solid #ddd;padding:10px'><b>Service</b></td>
                        <td style='border:1px solid #ddd;padding:10px'>$service</td>
                    </tr>
                    <tr>
                        <td style='border:1px solid #ddd;padding:10px'><b>Date</b></td>
                        <td style='border:1px solid #ddd;padding:10px'>$date</td>
                    </tr>
                    <tr>
                        <td style='border:1px solid #ddd;padding:10px'><b>Time</b></td>
                        <td style='border:1px solid #ddd;padding:10px'>$timeFormatted</td>
                    </tr>
                </table>
                <br>
                Please arrive at least <b>10 minutes early</b>.<br><br>
                Thank you,<br>
                <b>KNL Health Center</b>
            </div>
            <div style='background:#f0f0f0;text-align:center;padding:12px;font-size:12px'>
                Brgy Krus na Ligas, Quezon City<br>
                &copy; ".date('Y')." KNL Health Center
            </div>
        </div>
        ";

        $mail->send();
        return true;

    }catch(Exception $e){
        return false;
    }
}

/* =====================================================
   GET ALL APPOINTMENTS TOMORROW
===================================================== */
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$query = mysqli_query($link,"
    SELECT 
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        p.full_name,
        p.email,
        s.service_name
    FROM tblappointments a
    JOIN tblpatients p ON a.patient_id = p.patient_id
    JOIN tblservices s ON a.service_id = s.service_id
    WHERE a.appointment_date = '$tomorrow'
    AND a.status IN ('Pending','Approved')
    AND a.reminder_sent = 0
");

/* =====================================================
   LOOP + SEND EMAIL
===================================================== */
$allSent = true;

while($row = mysqli_fetch_assoc($query)){

    $sent = sendReminderEmail(
        $row['email'],
        $row['full_name'],
        $row['service_name'],
        $row['appointment_date'],
        $row['appointment_time']
    );

    if($sent){
        mysqli_query($link,"
            UPDATE tblappointments
            SET reminder_sent = 1
            WHERE appointment_id = '{$row['appointment_id']}'
        ");
    } else {
        $allSent = false;
    }
}

/* =====================================================
   SWEETALERT SUCCESS WITH THEME STYLE
===================================================== */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reminder Sent</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Override SweetAlert2 styles to match your theme */
        .swal2-popup {
            font-family: 'Poppins', sans-serif;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .swal2-title {
            color: #001BB7;
            font-weight: 700;
        }
        .swal2-content {
            color: #333;
            font-size: 1rem;
        }
        .swal2-confirm {
            background: #001BB7 !important;
            color: white !important;
            font-weight: 600;
            padding: 8px 24px !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
<script>
Swal.fire({
    icon: 'success',
    title: 'Reminders Sent!',
    text: 'All appointment reminders for tomorrow have been sent successfully.',
    confirmButtonColor: '#001BB7'
}).then(() => {
    window.location.href = 'dashboard.php';
});
</script>
</body>
</html>
