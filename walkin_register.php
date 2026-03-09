<?php
session_start();
require_once "config.php";

// Prevent public access
if (!isset($_SESSION['staff_id'])) {
    die("<h2 style='color:red;text-align:center'>ACCESS DENIED: Staff only</h2>");
}

$staff_name = $_SESSION['staff_name'];

// Log function
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
function generateQueueNumber($link, $service_id, $appointment_date, $patient_priority = 0) {

    // PRIORITY PATIENT (Senior / PWD / Pregnant)
    if ($patient_priority == 1) {

        // Hanapin ang unang REGULAR
        $q = mysqli_query($link, "
            SELECT queue_number FROM tblappointments
            WHERE service_id='$service_id'
              AND appointment_date='$appointment_date'
              AND priority != 1
            ORDER BY queue_number ASC
            LIMIT 1
        ");

        // Walang regular → append after last priority
        if (mysqli_num_rows($q) == 0) {

            $last_priority = mysqli_query($link, "
                SELECT queue_number FROM tblappointments
                WHERE service_id='$service_id'
                  AND appointment_date='$appointment_date'
                  AND priority = 1
                ORDER BY queue_number DESC
                LIMIT 1
            ");

            if (mysqli_num_rows($last_priority) == 0) {
                return 1;
            } else {
                $row = mysqli_fetch_assoc($last_priority);
                return $row['queue_number'] + 1;
            }

        } else {
            // Insert BEFORE first regular
            $row = mysqli_fetch_assoc($q);
            $insert_position = $row['queue_number'];

            mysqli_query($link, "
                UPDATE tblappointments
                SET queue_number = queue_number + 1
                WHERE service_id='$service_id'
                  AND appointment_date='$appointment_date'
                  AND queue_number >= $insert_position
            ");

            return $insert_position;
        }

    } 
    // REGULAR PATIENT
    else {
        $q = mysqli_query($link, "
            SELECT queue_number FROM tblappointments
            WHERE service_id='$service_id'
              AND appointment_date='$appointment_date'
            ORDER BY queue_number DESC
            LIMIT 1
        ");

        if (mysqli_num_rows($q) == 0) {
            return 1;
        } else {
            $row = mysqli_fetch_assoc($q);
            return $row['queue_number'] + 1;
        }
    }
}

$error = '';
$success = '';

if (isset($_POST['register_walkin'])) {

    // AUTO GENERATED ACCOUNT
    $username = "walkin_" . time();
    $password = "WI-" . rand(10000, 99999);

    // Sanitize
    $full_name      = mysqli_real_escape_string($link, $_POST['full_name'] ?? '');
    $address        = mysqli_real_escape_string($link, $_POST['address'] ?? '');
    $color_code     = mysqli_real_escape_string($link, $_POST['color_code'] ?? '');
    $birthday       = mysqli_real_escape_string($link, $_POST['birthday'] ?? '');
    $age            = mysqli_real_escape_string($link, $_POST['age'] ?? '');
    $gender         = mysqli_real_escape_string($link, $_POST['gender'] ?? '');
    $contact_number = mysqli_real_escape_string($link, $_POST['contact_number'] ?? '');
    $residency_type = mysqli_real_escape_string($link, $_POST['residency_type'] ?? 'Resident'); // default Resident
    $registration_source = 'Walk In';
    $service_id         = intval($_POST['service_id'] ?? 0);
    $appointment_date   = mysqli_real_escape_string($link, $_POST['appointment_date'] ?? date('Y-m-d'));
    $appointment_time   = mysqli_real_escape_string($link, $_POST['appointment_time'] ?? '');
    
    $errors = [];
    $is_pregnant = isset($_POST['is_pregnant']) ? 1 : 0;
$is_pwd     = isset($_POST['is_pwd']) ? 1 : 0;
$priority = 0; // default: Not Priority
$priority_label = '';

if ($residency_type == 'Resident') {
    if ($age >= 60 || $is_pregnant || $is_pwd) {
        $priority = 1;
        $priority_label = "High Priority (Senior / Pregnant / PWD)";
    } else {
        $priority = 2;
        $priority_label = "Regular Resident";
    }
} else { // Non-Resident
    if ($age >= 60 || $is_pregnant || $is_pwd) {
        $priority = 1;
        $priority_label = "High Priority (Senior / Pregnant / PWD)";
    } else {
        $priority = 0;
        $priority_label = "Not Priority";
    }
}

    $assigned_doctor = null;
if ($service_id) {
    $doctor_result = mysqli_query($link, "SELECT doctor_id FROM tblservices WHERE service_id='$service_id' LIMIT 1");
    $doctor_row = mysqli_fetch_assoc($doctor_result);
    $assigned_doctor = $doctor_row['doctor_id'] ?? null;

    if (!$assigned_doctor) {
        $errors[] = "No doctor is assigned to the selected service.";
    }
}

    // Required validation
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($birthday))  $errors[] = "Birthday is required.";
    if (empty($age))       $errors[] = "Age is required.";
    if (empty($gender))    $errors[] = "Gender is required.";
    if (empty($address))   $errors[] = "Street is required.";

    // Contact format
    if (!empty($contact_number) && !preg_match('/^[0-9]{11}$/', $contact_number)) {
        $errors[] = "Contact number must be 11 digits.";
    }

    // Insert if no errors
    if (empty($errors)) {

        $sql = "INSERT INTO tblpatients 
(username, password, full_name, address, color_code, birthday, age, contact_number, gender, status, residency_type, registration_source, date_registered, priority)
VALUES
('$username', '$password', '$full_name', '$address', '$color_code', '$birthday', '$age', '$contact_number', '$gender', 'Verified', '$residency_type', '$registration_source', CURDATE(), '$priority')";


        if (mysqli_query($link, $sql)) {
            $patient_id = mysqli_insert_id($link); // Get new patient ID
            logAction($link, "Walk-In Registration", "Patient", $full_name, $_SESSION['staff_id']);

            $success = "Walk-in patient registered successfully!";

            if (empty($errors) && $service_id && $appointment_date && $appointment_time && $assigned_doctor) {

    $queue_number = generateQueueNumber($link, $service_id, $appointment_date, $priority);

    $appointment_type = mysqli_real_escape_string($link, $_POST['appointment_type'] ?? 'regular');
$follow_up_for    = !empty($_POST['follow_up_for']) ? intval($_POST['follow_up_for']) : 'NULL';

$insert_appt = "INSERT INTO tblappointments 
    (patient_id, doctor_assigned, assignedby, service_id, appointment_date, appointment_time, status, date_created, queue_number, appointment_type, follow_up_for, priority)
    VALUES 
    ('$patient_id', '$assigned_doctor', '$staff_name', '$service_id', '$appointment_date', '$appointment_time', 'Pending', NOW(), '$queue_number', '$appointment_type', $follow_up_for, '$priority')";

    if (mysqli_query($link, $insert_appt)) {
        logAction($link, "Assigned Queue #$queue_number to Dr.#$assigned_doctor", "Queueing", $full_name, $_SESSION['staff_id']);
        $generated_queue = $queue_number;
    }
}

$_POST = [];

        } else {
            $errors[] = "Database error: " . mysqli_error($link);
            logAction($link, "Registration Failed", "Patient", $full_name, $_SESSION['staff_id']);
        }
    }

    if (!empty($errors)) {
        $error = '<div class="error"><ul>';
        foreach ($errors as $err) $error .= "<li>$err</li>";
        $error .= '</ul></div>';
    }
}

// Fetch services
$services = mysqli_query($link, "SELECT * FROM tblservices ORDER BY service_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Walk-in Registration</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --primary-color: #001BB7;
    --sidebar-bg: #001BB7;
    --sidebar-hover: rgba(255,255,255,0.15);
    --text-color: #fff;
}
* { margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif; }
body { background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b; }

/* Sidebar */
.sidebar { width:250px; background: var(--sidebar-bg); color: var(--text-color); position:fixed; height:100%; padding:25px 15px; display:flex; flex-direction:column; transition: transform 0.3s ease; z-index: 1000; }
.sidebar h2 { text-align:center; margin-bottom:35px; font-size:24px; font-weight:700; }
.sidebar a { color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:8px 0; text-decoration:none; border-radius:10px; transition:0.3s; font-weight:500; }
.sidebar a i { margin-right:12px; font-size:18px; }
.sidebar a:hover { background: var(--sidebar-hover); padding-left:24px; color:#fff; }
.sidebar a.active { background: rgba(255,255,255,0.25); font-weight:600; }

/* Hamburger */
.hamburger { display:none; position:fixed; top:15px; left:15px; font-size:20px; color: var(--primary-color); background:#fff; border:none; border-radius:8px; padding:8px 10px; cursor:pointer; z-index:1100; }

/* Overlay */
#overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s; z-index: 900; }
#overlay.active {opacity:1; visibility:visible;}

/* Main */
.main { margin-left:250px; padding:40px; flex:1; transition: margin-left 0.3s; }

/* Card for form */
.register-card{
  background:white;
  padding:28px 30px;
  border-radius:16px;
  width:100%;
  max-width:900px;          /* ✅ wider card */
  box-shadow:0 8px 25px rgba(0,0,0,0.12);
  margin:0 auto;
  position:relative;
}

.register-card h2{text-align:center;color:#001BB7;margin-bottom:5px;}
.register-card p{text-align:center;color:#444;margin-bottom:20px;}

.error{color:red;font-size:13px;margin-bottom:10px;}
.success{color:green;font-size:13px;margin-bottom:10px;}

form{
  display:grid;
  grid-template-columns:repeat(2, 1fr);
  gap:8px 20px;   /* less vertical, more horizontal */
}


label{font-size:13px;font-weight:600;margin-bottom:3px;display:block;}
input, select{
  width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:8px;font-size:14px;
}

button{
  grid-column:1/3;padding:12px;background:#001BB7;color:white;font-weight:600;
  border:none;border-radius:10px;cursor:pointer;font-size:16px;
}
button:hover{background:#FFD43B;color:#001BB7;}

.street-color{width:25px;height:25px;border-radius:6px;margin-left:10px;border:1px solid #ccc;}
.custom-dropdown{position:relative;width:100%;}
.options{
  display:none;position:absolute;top:100%;left:0;width:100%;max-height:120px;
  overflow-y:auto;background:white;border:1px solid #ccc;border-radius:6px;z-index:10;
}
.option{padding:6px 10px;cursor:pointer;}
.option:hover{background:#eee;}

.back-btn{position:absolute;top:15px;left:20px;background:#001BB7;color:white;
padding:6px 12px;border-radius:8px;text-decoration:none;font-weight:600;}
.back-btn:hover{background:#FFD43B;color:#001BB7;}

@media(max-width:768px){
    .hamburger {display:block;}
    .sidebar {transform:translateX(-280px);}
    .sidebar.active {transform:translateX(0);}
    .main {margin-left:0; padding:20px;}
}
/* Time slot buttons */
.slot-btn {
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    margin: 5px 5px 0 0;
    transition: 0.2s;
    color: #fff;
}

/* Past slots */
.slot-btn.past {
    background-color: lightgray;
    cursor: not-allowed;
}

/* Booked slots */
.slot-btn.booked {
    background-color: red;
    cursor: not-allowed;
}

/* Available slots */
.slot-btn.available {
    background-color: green;
}

/* Selected slot */
.slot-btn.selected {
    box-shadow: 0 0 0 3px #FFD43B inset; /* yellow border inside button */
    background-color: #001BB7 !important; /* dark blue */
}
.residency-wrapper {
    display: flex;
    align-items: center;
    gap: 25px;
}

.radio-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 600;
}
.main {
    margin-left:250px;
    padding:40px;
    flex:1;
    transition: margin-left 0.3s;
    position: relative; /* add this */
}
.priority-wrapper {
    display: flex;
    align-items: center;
    gap: 20px; /* space between checkboxes */
    margin-top: 5px;
}

.priority-wrapper .radio-item {
    display: flex;
    align-items: center;
    gap: 6px; /* space between checkbox and label text */
    font-size: 14px;
    font-weight: 600;
}
.full-width{
    grid-column: 1 / -1; /* span all columns */
}
@media (max-width: 768px){
    form{
        grid-template-columns: 1fr;
    }

    button{
        grid-column: 1;
    }
}
@media(max-width:768px){
    .hamburger {display:block;}
    .sidebar {transform:translateX(-280px);}
    .sidebar.active {transform:translateX(0);}
    .main {margin-left:0; padding:20px;}
}

</style>
</head>
<body>

<button class="hamburger"><i class="fas fa-bars"></i></button>
<div id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar">
    <h2>
        <img src="logo.png" alt="KNL Logo" style="width:45px; height:45px; margin-right:10px; vertical-align:middle; border-radius:50%; object-fit:cover;">
        KNL Health Center
    </h2>
    <a href="staff_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="staff_profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="patient_staff.php" class="active"><i class="fas fa-users"></i> Patients</a>
    <a href="staff_appointments.php"><i class="fas fa-calendar"></i> Appointments</a>
    <a href="staff_services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="staff_reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Main -->
<div class="main">
    <?php include 'header_staff.php'; ?>

<div class="register-card">
    <a href="#" class="back-btn" id="backExitBtn">
    <i class="fas fa-arrow-left"></i> 
</a>

    <h2>Walk-in Registration</h2>

    <?php if($error != '') echo $error; ?>

    <form method="POST">
        <div class="full-width">
            <label>Full Name *</label>
            <input type="text" name="full_name" required 
            value="<?php echo $_POST['full_name'] ?? '' ?>">
        </div>

        <div>
            <label>Birthday *</label>
            <input type="date" name="birthday" id="birthday" required 
            value="<?php echo $_POST['birthday'] ?? '' ?>">
        </div>

        <div>
            <label>Age</label>
            <input type="text" id="age_display" readonly 
            value="<?php echo $_POST['age'] ?? '' ?>">
            <input type="hidden" name="age" id="age" 
            value="<?php echo $_POST['age'] ?? '' ?>">
        </div>

        <div>
            <label>Gender *</label>
            <select name="gender" required>
                <option value="">Select</option>
                <option value="Male" <?php if(@$_POST['gender']=="Male") echo "selected"; ?>>Male</option>
                <option value="Female" <?php if(@$_POST['gender']=="Female") echo "selected"; ?>>Female</option>
            </select>
        </div>

        <div>
            <label>Contact Number</label>
            <input type="text" name="contact_number" 
            value="<?php echo $_POST['contact_number'] ?? '' ?>">
        </div>
        <div class="full-width">
    <label>Residency Type *</label>
    <div class="residency-wrapper">
        <label class="radio-item">
            <input type="radio" name="residency_type" value="Resident" id="resResident"
<?php if(($_POST['residency_type'] ?? 'Resident')=='Resident') echo 'checked'; ?>>

            Resident
        </label>

        <label class="radio-item">
            <input type="radio" name="residency_type" value="Non-Resident" id="resNonResident"
            <?php if(($_POST['residency_type'] ?? '')=='Non-Resident') echo 'checked'; ?>>
            Non-Resident
        </label>
    </div>
</div>

<div class="full-width">
    <label>Street / Address *</label>
    <div style="display:flex;align-items:center;" id="streetContainer">
        <div class="custom-dropdown" id="streetDropdownWrapper">
            <input type="text" id="selectedStreet" autocomplete="off" placeholder="Select Street"
            value="<?php echo $_POST['address'] ?? '' ?>">
            <div class="options" id="streetOptions">
                <?php
                $streets = [
                  "Angeles Street","Antipolo Street","B. Baluyot Street","E. Ramos Street","Emilio Jacinto Street",
                  "Eugenio Street","I. Francisco Alley","Kabalitang Street","Lieutenant J. Francisco Street",
                  "Lourdes Street","M. Dela Cruz Street","P. Fernando Street","P. Francisco Street",
                  "Plaza Hernandez Street","S. Flores Street","S. Salvador Street","S. Santos Street",
                  "Tiburcio Street","Tiburcio Street Extension","V. Francisco Street","V. Gonzales Street","V. Manansala Street"
                ];
                foreach($streets as $s){
                    echo "<div class='option' data-value='$s'>$s</div>";
                }
                ?>
            </div>
        </div>

        <input type="text" id="manualAddress" style="display:none;" placeholder="Enter address manually"
        value="<?php echo $_POST['address'] ?? '' ?>" />

        <div class="street-color" id="colorBox"></div>
    </div>

    <input type="hidden" name="address" id="addressInput" 
    value="<?php echo $_POST['address'] ?? '' ?>">
    <input type="hidden" name="color_code" id="colorCode" 
    value="<?php echo $_POST['color_code'] ?? '#000000' ?>">
</div>
<div class="full-width">
    <h5>Special Conditions / Priority</h5>
    <div class="priority-wrapper">
        <label class="radio-item">
            <input type="checkbox" id="is_pregnant" name="is_pregnant" value="1" <?php if(@$_POST['is_pregnant']) echo 'checked'; ?>>
            Pregnant
        </label>
        <label class="radio-item">
            <input type="checkbox" id="is_pwd" name="is_pwd" value="1" <?php if(@$_POST['is_pwd']) echo 'checked'; ?>>
            PWD
        </label>
    </div>

    <div style="margin-top:8px;">
        <strong>Calculated Priority:</strong>
        <input type="text" id="priorityDisplay" readonly 
            value="2 - Regular Resident" 
            style="width:250px; text-align:center; border:none; font-weight:bold; padding:4px; border-radius:5px;">
        <input type="hidden" name="priority" id="priorityInput" value="2">
    </div>
</div>

<div class="full-width">
    <label>Service</label>
    <select name="service_id" id="service_id" required>
        <option value="">--Select Service--</option>
        <?php 
        $services = mysqli_query($link, "SELECT * FROM tblservices ORDER BY service_name ASC");
        while($s=mysqli_fetch_assoc($services)): ?>
            <option value="<?php echo $s['service_id']; ?>" <?php echo (($_POST['service_id'] ?? '')==$s['service_id'])?'selected':''; ?>>
                <?php echo htmlspecialchars($s['service_name']); ?>
            </option>
        <?php endwhile; ?>
    </select>
</div>

<div class="full-width">
    <label>Date</label>
    <input type="date" name="appointment_date" id="appointment_date" value="<?php echo $_POST['appointment_date'] ?? date('Y-m-d'); ?>" required>
</div>

<div class="full-width">
    <label>Available Time Slots</label>
    <div id="time_slots"></div>
    <input type="hidden" name="appointment_time" id="appointment_time">
</div>
<input type="hidden" name="appointment_type" value="regular">
<input type="hidden" name="follow_up_for" value="">


        <button type="submit" name="register_walkin">Register Walk-in</button>
    </form>
</div>
</div>

<script>
    const resResident = document.getElementById('resResident');
const resNonResident = document.getElementById('resNonResident');
const streetDropdownWrapper = document.getElementById('streetDropdownWrapper');
const manualAddress = document.getElementById('manualAddress');
const addressInput = document.getElementById('addressInput');
const colorBox = document.getElementById('colorBox');
const colorCode = document.getElementById('colorCode');

function updateStreetField() {
    if(resResident.checked){
        streetDropdownWrapper.style.display = 'block';
        manualAddress.style.display = 'none';
        // Restore color if street selected
        let street = document.getElementById('selectedStreet').value;
        const streetsColor = {
            "Angeles Street": "#FF0000","Antipolo Street": "#0000FF","B. Baluyot Street": "#00FF00",
            "E. Ramos Street": "#FFFF00","Emilio Jacinto Street": "#FFA500","Eugenio Street": "#800080",
            "I. Francisco Alley": "#00FFFF","Kabalitang Street": "#FFC0CB","Lieutenant J. Francisco Street": "#A52A2A",
            "Lourdes Street": "#008000","M. Dela Cruz Street": "#000080","P. Fernando Street": "#FFD700",
            "P. Francisco Street": "#4B0082","Plaza Hernandez Street": "#FF4500","S. Flores Street": "#ADFF2F",
            "S. Salvador Street": "#00FA9A","S. Santos Street": "#FF1493","Tiburcio Street": "#1E90FF",
            "Tiburcio Street Extension": "#FF69B4","V. Francisco Street": "#7FFF00","V. Gonzales Street": "#8B0000",
            "V. Manansala Street": "#FF8C00"
        };
        colorBox.style.background = streetsColor[street] ?? "#000";
        colorCode.value = streetsColor[street] ?? "#000";
        addressInput.value = street;
    } else if(resNonResident.checked){
        streetDropdownWrapper.style.display = 'none';
        manualAddress.style.display = 'block';
        colorBox.style.background = "#000"; // black for non-residents
        colorCode.value = "#000";
        addressInput.value = manualAddress.value;
    }
}

// Event listeners
resResident.addEventListener('change', updateStreetField);
resNonResident.addEventListener('change', updateStreetField);

// Update hidden input when typing manual address
manualAddress.addEventListener('input', ()=> {
    addressInput.value = manualAddress.value;
});

// Run on page load to set initial state
window.addEventListener('DOMContentLoaded', updateStreetField);

// Hamburger toggle
const hamburger = document.querySelector('.hamburger');
const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('overlay');
hamburger.addEventListener('click', () => { sidebar.classList.toggle('active'); overlay.classList.toggle('active'); });
overlay.addEventListener('click', () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); });

// Logout
document.getElementById('logoutBtn').addEventListener('click', function(e){
    e.preventDefault();
    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out from the system.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#001BB7',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, log me out',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
});

// Age calculation
const birthdayInput = document.getElementById("birthday");
const ageDisplay = document.getElementById("age_display");
const ageHidden = document.getElementById("age");

function calcAge(bday){
    let today = new Date();
    let birth = new Date(bday);
    let age = today.getFullYear() - birth.getFullYear();
    if(today.getMonth() < birth.getMonth() || 
      (today.getMonth()==birth.getMonth() && today.getDate() < birth.getDate())){
        age--;
    }
    return age;
}
birthdayInput.addEventListener("change", ()=>{
    if(birthdayInput.value){
        let a = calcAge(birthdayInput.value);
        ageDisplay.value = a;
        ageHidden.value = a;
        calculatePriority(); // <-- ADD THIS
    }
});

// Streets & color
const dropdown = document.getElementById('streetDropdownWrapper');
const input = document.getElementById('selectedStreet');
const optionsContainer = document.getElementById('streetOptions');
const hiddenInput = document.getElementById('addressInput');

const streetsColor = {
    "Angeles Street": "#FF0000","Antipolo Street": "#0000FF","B. Baluyot Street": "#00FF00",
    "E. Ramos Street": "#FFFF00","Emilio Jacinto Street": "#FFA500","Eugenio Street": "#800080",
    "I. Francisco Alley": "#00FFFF","Kabalitang Street": "#FFC0CB","Lieutenant J. Francisco Street": "#A52A2A",
    "Lourdes Street": "#008000","M. Dela Cruz Street": "#000080","P. Fernando Street": "#FFD700",
    "P. Francisco Street": "#4B0082","Plaza Hernandez Street": "#FF4500","S. Flores Street": "#ADFF2F",
    "S. Salvador Street": "#00FA9A","S. Santos Street": "#FF1493","Tiburcio Street": "#1E90FF",
    "Tiburcio Street Extension": "#FF69B4","V. Francisco Street": "#7FFF00","V. Gonzales Street": "#8B0000",
    "V. Manansala Street": "#FF8C00"
};

input.addEventListener('focus', ()=> optionsContainer.style.display="block");

document.querySelectorAll('.option').forEach(opt=>{
    opt.addEventListener('click', function(){
        input.value = this.textContent;
        hiddenInput.value = this.dataset.value;

        const c = streetsColor[this.dataset.value] ?? "#000";
        document.getElementById('colorBox').style.background = c;
        document.getElementById('colorCode').value = c;

        optionsContainer.style.display="none";
    });
});

// Auto apply saved color
window.addEventListener("DOMContentLoaded", ()=>{
    let street = input.value;
    if(street){
        hiddenInput.value = street;
        document.getElementById('colorBox').style.background = streetsColor[street] ?? "#000";
    }
});

<?php if ($success != ""): ?>
Swal.fire({
    icon: "success",
    title: "Walk-In Registered!",
    html: `
        <div style="font-size:18px;">
            A walk-in patient has been added successfully.<br><br>
            <b style="font-size:22px; color:#001BB7;">Queue Number: <?php echo $generated_queue ?? 'N/A'; ?></b>
        </div>
    `,
    confirmButtonText: "OK",
    allowOutsideClick: false
}).then(()=> window.location.href="patient_staff.php");
<?php endif; ?>


function format12Hour(time24) {
    // time24 = "HH:MM"
    let [h, m] = time24.split(':');
    h = parseInt(h);
    let ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    if (h === 0) h = 12;
    return h + ':' + m + ' ' + ampm;
}

function loadAvailableSlots(){
    let service_id = document.getElementById('service_id').value;
    let date = document.getElementById('appointment_date').value;
    let slotsDiv = document.getElementById('time_slots');
    slotsDiv.innerHTML = '';
    document.getElementById('appointment_time').value = '';

    if(service_id && date){
        fetch(`get_booked_slots.php?service_id=${service_id}&date=${date}`)
        .then(res => res.json())
        .then(slots => {
            let now = new Date();
            slots.forEach(slot => {
                let btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = format12Hour(slot.time); // display in 12-hour
                btn.className = 'slot-btn';

                let slotDateTime = new Date(date + ' ' + slot.time);

                if(slotDateTime < new Date()){  // Past slot
                    btn.classList.add('past');
                    btn.disabled = true;
                } else if(slot.booked){         // Already booked
                    btn.classList.add('booked');
                    btn.disabled = true;
                } else {                         // Available slot
                    btn.classList.add('available');
                    btn.addEventListener('click', ()=> {
                        // Remove previous selection
                        document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
                        // Highlight selected slot
                        btn.classList.add('selected');
                        // Store selected time in hidden input as original 24-hour
                        document.getElementById('appointment_time').value = slot.time;
                    });
                }

                slotsDiv.appendChild(btn);
            });
        });
    }
}


document.getElementById('service_id').addEventListener('change', loadAvailableSlots);
document.getElementById('appointment_date').addEventListener('change', loadAvailableSlots);

// Auto-load slots for today's date on page load
window.addEventListener('DOMContentLoaded', loadAvailableSlots);

const priorityDisplay = document.getElementById('priorityDisplay');
const priorityInput = document.getElementById('priorityInput');
const isPregnant = document.getElementById('is_pregnant');
const isPWD = document.getElementById('is_pwd');
const ageField = document.getElementById('age');

const priorityLabels = {
    1: "High (Senior / Pregnant / PWD)",
    2: "Regular Resident"
};

function calculatePriority() {
    let age = parseInt(ageField.value || 0);
    let isResident = resResident.checked;
    let priority = 0;
    let label = '';

    if (isResident) {
        if(age >= 60 || isPregnant.checked || isPWD.checked){
            priority = 1;
            label = "High Priority (Senior / Pregnant / PWD)";
        } else {
            priority = 2;
            label = "Regular Resident";
        }
    } else { // Non-Resident
        if(age >= 60 || isPregnant.checked || isPWD.checked){
            priority = 1;
            label = "High Priority (Senior / Pregnant / PWD)";
        } else {
            priority = 0;
            label = "Not Priority";
        }
    }

    // Update display with color
    priorityDisplay.value = `${priority} - ${label}`;
    if(priority === 1){
        priorityDisplay.style.backgroundColor = "#FF4D4D"; // red
    } else if(priority === 2){
        priorityDisplay.style.backgroundColor = "#4CAF50"; // green
    } else {
        priorityDisplay.style.backgroundColor = "#999"; // gray
    }
    priorityDisplay.style.color = "#fff";

    // Store for PHP
    priorityInput.value = priority;
}

// Event listeners
ageField.addEventListener('input', calculatePriority);
isPregnant.addEventListener('change', calculatePriority);
isPWD.addEventListener('change', calculatePriority);
birthdayInput.addEventListener('change', () => {
    ageField.value = calcAge(birthdayInput.value);
    ageDisplay.value = ageField.value;
    calculatePriority();
});
resResident.addEventListener('change', calculatePriority);
resNonResident.addEventListener('change', calculatePriority);

// Initial calculation
window.addEventListener('DOMContentLoaded', calculatePriority);

</script>
<script>
document.getElementById('backExitBtn').addEventListener('click', function(e){
    e.preventDefault();

    Swal.fire({
        title: 'Leave this form?',
        text: 'Any entered information will be lost.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#001BB7',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, leave',
        cancelButtonText: 'Stay'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'patient_staff.php';
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
