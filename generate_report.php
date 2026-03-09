<?php
session_start();
require_once "config.php";

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'];
$error = '';
$success = '';
$report_preview = '';

if(isset($_POST['generate'])){
    $report_name = mysqli_real_escape_string($link, $_POST['report_name']);
    $report_type = $_POST['report_type'];
    $date_from = $_POST['date_from'] ?? '';
    $date_to = $_POST['date_to'] ?? '';
    $report_data = '';

    if($report_type == 'Appointments'){
        $sql = "SELECT a.*, p.full_name AS patient_name, d.fullname AS doctor_name, s.service_name 
                FROM tblappointments a
                LEFT JOIN tblpatients p ON a.patient_id=p.patient_id
                LEFT JOIN tbldoctors d ON a.doctor_assigned=d.doctor_id
                LEFT JOIN tblservices s ON a.service_id=s.service_id
                WHERE 1";
        if($date_from) $sql .= " AND a.appointment_date >= '$date_from'";
        if($date_to) $sql .= " AND a.appointment_date <= '$date_to'";
        $sql .= " ORDER BY a.appointment_date ASC";
        $result = mysqli_query($link,$sql);
        $report_data .= "Appointment ID,Patient,Doctor,Service,Date,Time,Status\n";
        while($row = mysqli_fetch_assoc($result)){
            $report_data .= "{$row['appointment_id']},{$row['patient_name']},{$row['doctor_name']},{$row['service_name']},{$row['appointment_date']},{$row['appointment_time']},{$row['status']}\n";
        }
    } elseif($report_type == 'Patients'){
        $sql = "SELECT * FROM tblpatients ORDER BY full_name ASC";
        $result = mysqli_query($link,$sql);
        $report_data .= "Patient ID,Full Name,Address,Contact,Email,Gender,Status\n";
        while($row=mysqli_fetch_assoc($result)){
            $report_data .= "{$row['patient_id']},{$row['full_name']},{$row['address']},{$row['contact_number']},{$row['email']},{$row['gender']},{$row['status']}\n";
        }
    } elseif($report_type == 'Doctors'){
        $sql = "SELECT * FROM tbldoctors ORDER BY fullname ASC";
        $result = mysqli_query($link,$sql);
        $report_data .= "Doctor ID,Full Name,Specialization,Contact,Email,Status\n";
        while($row=mysqli_fetch_assoc($result)){
            $report_data .= "{$row['doctor_id']},{$row['fullname']},{$row['specialization']},{$row['contact_number']},{$row['email']},{$row['status']}\n";
        }
    } else {
        $error = "Invalid report type.";
    }

    if($report_data != ''){
        $date_generated = date('Y-m-d');
        $time_generated = date('H:i:s');
        $filters_applied = '';
        if($date_from || $date_to) $filters_applied = "Date from: $date_from, Date to: $date_to";

        $stmt = mysqli_prepare($link,"INSERT INTO tblreports
            (report_name, report_type, generated_by, date_generated, time_generated, filters_applied, report_data)
            VALUES (?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt,"sssssss",$report_name,$report_type,$admin_name,$date_generated,$time_generated,$filters_applied,$report_data);
        mysqli_stmt_execute($stmt);

        $success = "Report generated successfully!";
        $report_preview = $report_data;
    } else {
        $error = "No data found for this report.";
    }

    if(isset($_POST['export_csv']) && $report_data){
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="'.$report_name.'.csv"');
        echo $report_data;
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Generate Report - Health Center</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
body{background:#f5f7fa;display:flex;min-height:100vh;color:#1e293b;}
.sidebar{width:250px;background:#001BB7;color:#fff;position:fixed;height:100%;padding:25px 15px;display:flex;flex-direction:column;}
.sidebar h2{text-align:center;margin-bottom:35px;font-size:24px;font-weight:700;}
.sidebar a{color:#ffffffcc;display:flex;align-items:center;padding:12px 18px;margin:10px 0;text-decoration:none;border-radius:12px;transition:0.3s;font-weight:500;}
.sidebar a i{margin-right:12px;font-size:18px;}
.sidebar a:hover{background:rgba(255,255,255,0.15);padding-left:24px;color:#fff;}
.sidebar a.active{background:rgba(255,255,255,0.25);font-weight:600;}
.main{margin-left:250px;padding:30px;flex:1;}
h1{margin-bottom:15px;color:#1e293b;font-weight:700;}
p{color:#4b5563;font-size:1rem;}
.btn{border-radius:8px;}
textarea{width:100%;min-height:300px;resize:none;padding:15px;border-radius:12px;border:1px solid #ccc;background:#f9f9f9;}
@media(max-width:768px){.sidebar{width:200px;padding:20px;}.main{margin-left:200px;padding:20px;}}
@media(max-width:480px){.sidebar{width:100%;position:relative;height:auto;flex-direction:row;overflow-x:auto;}.sidebar h2{display:none;}.sidebar a{flex:1;text-align:center;margin:0 5px;}.main{margin-left:0;padding:15px;}}
</style>
</head>
<body>

<div class="sidebar">
<h2>Health Center</h2>
<a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
<a href="patients.php"><i class="fas fa-users"></i> Patients</a>
<a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
<a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a>
<a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a>
<a href="services.php"><i class="fas fa-stethoscope"></i> Services</a>
<a href="reports.php" class="active"><i class="fas fa-chart-line"></i> Reports</a>
<a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
<a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main">
<h1>Generate Report</h1>
<p>Logged in as: <?=htmlspecialchars($admin_name)?></p>

<?php if($error): ?>
<div class="alert alert-danger"><?=$error?></div>
<?php endif; ?>
<?php if($success): ?>
<div class="alert alert-success"><?=$success?></div>
<?php endif; ?>

<form method="post" class="mb-3">
<div class="mb-3">
<label class="form-label">Report Name</label>
<input type="text" name="report_name" class="form-control" required>
</div>

<div class="mb-3">
<label class="form-label">Report Type</label>
<select name="report_type" class="form-select" required>
<option value="">Select Type</option>
<option value="Appointments">Appointments</option>
<option value="Patients">Patients</option>
<option value="Doctors">Doctors</option>
</select>
</div>

<div class="mb-3">
<label class="form-label">Optional Date Range (Appointments)</label>
<div class="d-flex gap-2">
<input type="date" name="date_from" class="form-control">
<input type="date" name="date_to" class="form-control">
</div>
</div>

<button type="submit" name="generate" class="btn btn-success">Generate</button>
<button type="submit" name="export_csv" class="btn btn-primary">Export CSV</button>
<a href="reports.php" class="btn btn-secondary">Back to Reports</a>
</form>

<?php if($report_preview): ?>
<h3>Preview</h3>
<textarea readonly><?=htmlspecialchars($report_preview)?></textarea>
<?php endif; ?>

</div>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
