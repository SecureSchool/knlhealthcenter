<?php
session_start();
require_once "config.php";

if(!isset($_SESSION["admin_id"])){
    header("location: login.php");
    exit;
}

// Handle form submission
$message = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $full_name = mysqli_real_escape_string($link, $_POST['full_name']);
    $age = mysqli_real_escape_string($link, $_POST['age']); // optional
    $gender = mysqli_real_escape_string($link, $_POST['gender']);
    $address = mysqli_real_escape_string($link, $_POST['address']);
    $contact_number = mysqli_real_escape_string($link, $_POST['contact_number']);
    $service_needs = mysqli_real_escape_string($link, $_POST['service_needs']);
    $appointment_date = mysqli_real_escape_string($link, $_POST['appointment_date']);
    $appointment_time = mysqli_real_escape_string($link, $_POST['appointment_time']);
    $status = 'Pending';

    // Insert patient into tblpatients
    $insert_patient = mysqli_query($link, "
        INSERT INTO tblpatients (full_name, birthdate, gender, street, barangay, contact_number)
        VALUES ('$full_name', '', '$gender', '$address', '', '$contact_number')
    ");

    if($insert_patient){
        $patient_id = mysqli_insert_id($link);

        // Insert appointment into tblappointments
        $insert_appointment = mysqli_query($link, "
            INSERT INTO tblappointments (patient_id, service_needs, appointment_date, appointment_time, status)
            VALUES ('$patient_id', '$service_needs', '$appointment_date', '$appointment_time', '$status')
        ");

        if($insert_appointment){
            header("Location: appointments.php?added=success");
            exit;
        } else {
            $message = "Error adding appointment: ".mysqli_error($link);
        }
    } else {
        $message = "Error adding patient: ".mysqli_error($link);
    }
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Appointment - BKNL Health Center</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f7f9;margin:0;}
.sidebar{width:220px;background:#2c3e50;height:100vh;position:fixed;color:white;padding-top:20px;}
.sidebar .logo{text-align:center;margin-bottom:20px;}
.sidebar .logo img{width:80px;}
.sidebar a{display:block;color:white;text-decoration:none;padding:12px 20px;margin:5px 0;border-radius:6px;}
.sidebar a:hover{background:#34495e;}
.main{margin-left:240px;padding:20px;}
h1{color:#2c3e50;}
form{background:white;padding:20px;border-radius:8px;box-shadow:0 0 10px rgba(0,0,0,0.1);max-width:700px;}
form label{display:block;margin-top:10px;font-weight:bold;color:#2c3e50;}
form input, form select{width:100%;padding:8px;margin-top:5px;border-radius:6px;border:1px solid #ccc;}
form button{margin-top:15px;background:#27ae60;color:white;padding:10px 15px;border:none;border-radius:6px;cursor:pointer;font-weight:bold;}
form button:hover{background:#2ecc71;}
.message{margin-bottom:15px;color:#e74c3c;font-weight:bold;}
</style>
</head>
<body>
<div class="sidebar">
    <div class="logo">
        <img src="logo.png" alt="Logo">
        <h2>BKNL Health Center</h2>
    </div>
    <a href="dashboard.php">Dashboard</a>
    <a href="patients.php">Patient List</a>
    <a href="appointments.php">Appointments</a>
    <a href="services.php">Services</a>
    <a href="users.php">Users</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">Settings</a>
    <div style="position:absolute; bottom:20px; left:20px;">
        <a href="logout.php" style="background:#e74c3c; padding:10px 15px; border-radius:6px; color:white;">Logout</a>
    </div>
</div>

<div class="main">
<h1>Add Appointment</h1>

<?php if($message) echo "<div class='message'>$message</div>"; ?>

<form method="POST">
    <label for="full_name">Name:</label>
    <input type="text" name="full_name" required>

    <label for="age">Age:</label>
    <input type="number" name="age" required>

    <label for="gender">Gender:</label>
    <select name="gender" required>
        <option value="">-- Select Gender --</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
    </select>

    <label for="address">Address:</label>
    <input type="text" name="address" required>

    <label for="contact_number">Contact Number:</label>
    <input type="text" name="contact_number" required>

    <label for="service_needs">Service Needs:</label>
    <select name="service_needs" required>
        <option value="">-- Select Service --</option>
        <option value="Check Up">Check Up</option>
        <option value="Dental Service">Dental Service</option>
        <option value="Vaccine">Vaccine</option>
        <option value="Medicine">Medicine</option>
    </select>

    <label for="appointment_date">Date:</label>
    <input type="date" name="appointment_date" required>

    <label for="appointment_time">Time:</label>
    <input type="time" name="appointment_time" required>

    <button type="submit">Add Appointment</button>
</form>
</div>
</body>
</html>
