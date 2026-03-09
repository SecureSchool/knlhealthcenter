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

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$errors = [];
$patient_id = $_GET['id'] ?? '';
if (!$patient_id) {
    header("Location: patients.php");
    exit;
}

// Street color mapping
$streetColors = [
    "Angeles Street" => "#FF0000", "Antipolo Street" => "#0000FF", "B. Baluyot Street" => "#00FF00",
    "E. Ramos Street" => "#FFFF00", "Emilio Jacinto Street" => "#FFA500", "Eugenio Street" => "#800080",
    "I. Francisco Alley" => "#00FFFF", "Kabalitang Street" => "#FFC0CB", "Lieutenant J. Francisco Street" => "#A52A2A",
    "Lourdes Street" => "#008000", "M. Dela Cruz Street" => "#000080", "P. Fernando Street" => "#FFD700",
    "P. Francisco Street" => "#4B0082", "Plaza Hernandez Street" => "#FF4500", "S. Flores Street" => "#ADFF2F",
    "S. Salvador Street" => "#00FA9A", "S. Santos Street" => "#FF1493", "Tiburcio Street" => "#1E90FF",
    "Tiburcio Street Extension" => "#FF69B4", "V. Francisco Street" => "#7FFF00", "V. Gonzales Street" => "#8B0000",
    "V. Manansala Street" => "#FF8C00"
];

// Fetch existing data
$stmt = mysqli_prepare($link, "SELECT * FROM tblpatients WHERE patient_id = ?");
mysqli_stmt_bind_param($stmt, 's', $patient_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if (mysqli_num_rows($result) !== 1) {
    header("Location: patients.php");
    exit;
}
$patient = mysqli_fetch_assoc($result);

$username = $patient['username'];
$full_name = $patient['full_name'];
$address = $patient['address'];
$status = $patient['status'];
$color_code = $patient['color_code'];
$birthday = $patient['birthday'];
$contact_number = $patient['contact_number'];
$email = $patient['email'];
$gender = $patient['gender'];

mysqli_stmt_close($stmt);

if (isset($_POST['update'])) {
    $username = mysqli_real_escape_string($link, $_POST['username']);
    $full_name = mysqli_real_escape_string($link, $_POST['full_name']);
    $status = mysqli_real_escape_string($link, $_POST['status']);
    
    if ($status === "Resident") {
        $address = mysqli_real_escape_string($link, $_POST['address_dropdown'] ?? '');
        $color_code = $streetColors[$address] ?? "#000000";
    } else {
        $address = mysqli_real_escape_string($link, $_POST['address_text'] ?? '');
        $color_code = "#000000";
    }

    $birthday = mysqli_real_escape_string($link, $_POST['birthday']);
    $contact_number = mysqli_real_escape_string($link, $_POST['contact_number']);
    $email = mysqli_real_escape_string($link, $_POST['email']);
    $gender = mysqli_real_escape_string($link, $_POST['gender']);

    // Required fields
    if (empty($username)) $errors[] = "Username is required.";
    if (empty($full_name)) $errors[] = "Full Name is required.";
    if ($status === "Resident" && empty($address)) $errors[] = "Street is required for residents.";
    if ($status === "Non-Resident" && empty($address)) $errors[] = "Address is required for non-residents.";

    // Duplicate checks excluding current record
    if (empty($errors)) {
        $checks = [
            ['username', $username],
            ['full_name', $full_name],
        ];
        if ($email) $checks[] = ['email', $email];
        if ($contact_number) $checks[] = ['contact_number', $contact_number];

        foreach ($checks as [$field, $value]) {
            $stmt = mysqli_prepare($link, "SELECT COUNT(*) FROM tblpatients WHERE $field = ? AND patient_id <> ?");
            mysqli_stmt_bind_param($stmt, 'ss', $value, $patient_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $count);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            if ($count > 0) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " already exists.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($link, "
            UPDATE tblpatients
            SET username=?, full_name=?, address=?, color_code=?, birthday=?, contact_number=?, email=?, gender=?, status=?
            WHERE patient_id=?
        ");
        mysqli_stmt_bind_param(
            $stmt,
            'ssssssssss',
            $username,
            $full_name,
            $address,
            $color_code,
            $birthday,
            $contact_number,
            $email,
            $gender,
            $status,
            $patient_id
        );
        if (mysqli_stmt_execute($stmt)) {
            $admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';
            logAction($link, "Update", "Patients", $full_name, $admin_name);

            $_SESSION['success_update'] = "Patient updated successfully!";
            header("Location: patients.php");
            exit;
        } else {
            $errors[] = "Database update error: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Patient - Admin Dashboard</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
    body{background:#f5f7fa;display:flex;min-height:100vh;color:#1e293b;}
    .sidebar{width:250px;background:#001BB7;color:#fff;position:fixed;height:100%;padding:25px 15px;display:flex;flex-direction:column;}
    .sidebar h2{text-align:center;margin-bottom:20px;font-size:24px;font-weight:700;}
    .sidebar h2 i{font-size:28px; vertical-align:middle;}
    .sidebar a{color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;}
    .sidebar a i{margin-right:12px;font-size:18px;}
    .sidebar a:hover{background:rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
    .sidebar a.active{background:rgba(255,255,255,0.25); font-weight:600;}
    .main{margin-left:250px; padding:30px; flex:1;}
    h1,h2.page-title{color:#001BB7; margin-bottom:20px; display:flex; align-items:center; gap:10px;}

    label{display:block;margin-top:12px;font-weight:bold;}
    input[type=text],input[type=date],input[type=email],select{
      width:100%;padding:10px;margin-top:4px;
      border-radius:8px;border:1px solid #ddd;
    }
    .inline-flex{display:flex;align-items:center;}
    .inline-flex input[type="text"],.inline-flex select{flex:1;}
    .color-box{width:25px;height:25px;border-radius:4px;border:1px solid #aaa;margin-left:10px;}
    .status-group{margin-top:10px;}
    .status-group label{display:inline-block;margin-right:20px;font-weight:normal;}
    button{margin-top:20px;width:100%;background:#001BB7;border:none;padding:12px;color:#fff;font-size:16px;border-radius:8px;cursor:pointer;transition:background 0.3s;}
    button:hover{background:#001199;}
    .error{color:red;margin-bottom:10px;}
    .success{color:green;margin-bottom:10px;}
    .custom-dropdown{position:relative;width:calc(100% - 40px);}
    #selectedStreet{width:100%;box-sizing:border-box;}
    .custom-dropdown .options{display:none;position:absolute;top:100%;left:0;width:100%;border:1px solid #ddd;border-radius:6px;background:white;max-height:150px;overflow-y:auto;z-index:10;}
    .custom-dropdown .option{padding:8px 12px;cursor:pointer;}
    .custom-dropdown .option:hover{background:#f0f2f5;}
    /* ===== DARK MODE (PATIENT MANAGEMENT) ===== */
body.dark-mode {
    background: #0b1220 !important;
    color: #f5f7fa !important;
}

/* Sidebar */
body.dark-mode .sidebar {
    background: #0b1430 !important;
}
body.dark-mode .sidebar a {
    color: #e9f2ff !important;
}
body.dark-mode .sidebar a:hover,
body.dark-mode .sidebar a.active {
    background: rgba(255,255,255,0.18) !important;
    color: #fff !important;
}

/* Header */
body.dark-mode .header-container {
    background: linear-gradient(90deg, #0f1a38, #00122b) !important;
    color: #fff !important;
}

/* Main content */
body.dark-mode .main {
    background: #0b1220 !important;
}

/* Cards */
body.dark-mode .card {
    background: #111b2b !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.6) !important;
    color: #fff !important;
}

/* Table */
body.dark-mode table {
    background: #0b1220 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.18) !important;
}
body.dark-mode table th {
    background: #0b1430 !important;
    color: #fff !important;
}
body.dark-mode table td {
    border-bottom: 1px solid rgba(255,255,255,0.12) !important;
}
body.dark-mode table tr:hover {
    background: rgba(255,255,255,0.06) !important;
}

/* Text */
body.dark-mode h1,
body.dark-mode h2,
body.dark-mode h3,
body.dark-mode p,
body.dark-mode label,
body.dark-mode span,
body.dark-mode th,
body.dark-mode td {
    color: #f5f7fa !important;
}

/* Buttons */
body.dark-mode .btn {
    color: #fff !important;
    border-color: rgba(255,255,255,0.25) !important;
}

body.dark-mode .btn-primary {
    background-color: #0b1430 !important;
}
body.dark-mode .btn-success {
    background-color: #1f7a3d !important;
}
body.dark-mode .btn-warning {
    background-color: #d97706 !important;
}
body.dark-mode .btn-danger {
    background-color: #c0392b !important;
}
body.dark-mode .btn-info {
    background-color: #0b6fb5 !important;
}
body.dark-mode .btn-light {
    background: #1a2337 !important;
    color: #fff !important;
}

/* Review Patients Button (IMPORTANT) */
body.dark-mode .review-btn,
body.dark-mode .btn-review {
    background: #0b6fb5 !important;  /* strong color */
    color: #fff !important;
    border: 1px solid #0b6fb5 !important;
}

/* Pagination */
body.dark-mode .pagination .page-link {
    background: #1a2337 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.25) !important;
}
body.dark-mode .pagination .page-item.active .page-link {
    background: #0b1430 !important;
}

/* Inputs */
body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea {
    background: #1a2337 !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.3) !important;
}

/* Badges */
body.dark-mode .badge {
    background: #1a2337 !important;
    color: #fff !important;
}
body.dark-mode .badge-success {
    background: #1f7a3d !important;
}
body.dark-mode .badge-danger {
    background: #c0392b !important;
}

/* Modal */
body.dark-mode .modal-content {
    background: #111b2b !important;
    color: #fff !important;
}
body.dark-mode .modal-header,
body.dark-mode .modal-body,
body.dark-mode .modal-footer {
    color: #fff !important;
}
body.dark-mode .modal-backdrop {
    background: rgba(0,0,0,0.7) !important;
}

/* Dropdown */
body.dark-mode .dropdown-menu {
    background: #111b2b !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.2) !important;
}
body.dark-mode .dropdown-item {
    color: #fff !important;
}
body.dark-mode .dropdown-item:hover {
    background: rgba(255,255,255,0.15) !important;
}

/* SweetAlert */
body.dark-mode .swal2-popup {
    background: #111b2b !important;
    color: #fff !important;
}
body.dark-mode .swal2-title,
body.dark-mode .swal2-content {
    color: #fff !important;
}

  </style>
</head>
<body>

<div class="sidebar">
   <h2>
    <a href="dashboard.php" style="text-decoration:none; color:white; display:flex; align-items:center; justify-content:center;">
        <img src="logo.png" alt="KNL Logo"
             style="width:45px; height:45px; margin-right:10px; border-radius:50%; object-fit:cover;">
        KNL Health Center
    </a>
</h2>
  <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
  <a href="patients.php" class="active"><i class="fas fa-users"></i> Patients</a>
  <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
  <a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a>
  <a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a>
  <a href="services.php"><i class="fas fa-stethoscope"></i> Services</a>
  <a href="announcement.php"><i class="fas fa-bullhorn"></i> Announcements</a>
  <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
  <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
  <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main">
    <?php include 'admin_header.php'; ?>
  <h2 class="page-title"><i class="fas fa-user-edit"></i> Edit Patient</h2>

  <?php if (!empty($errors)): foreach ($errors as $err): ?>
    <div class="error"><?php echo htmlspecialchars($err); ?></div>
  <?php endforeach; endif; ?>

  <form method="POST">
    <label>Patient ID</label>
    <input type="text" value="<?php echo htmlspecialchars($patient_id); ?>" readonly style="background:#eee;">
    <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient_id); ?>">

    <label>Full Name</label>
    <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>

    <label>Username</label>
    <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>

    <div class="status-group">
      <label><input type="radio" name="status" value="Resident" <?php echo ($status==="Resident")?"checked":""; ?>> Resident</label>
      <label><input type="radio" name="status" value="Non-Resident" <?php echo ($status==="Non-Resident")?"checked":""; ?>> Non-Resident</label>
    </div>

    <div id="residentAddress">
      <label>Street</label>
      <div style="display:flex;align-items:center;margin-bottom:10px;">
        <div class="custom-dropdown" id="streetDropdownWrapper">
          <input type="text" id="selectedStreet" placeholder="Select Street" autocomplete="off"
                 value="<?php echo ($status === 'Resident') ? htmlspecialchars($address) : ''; ?>">
          <div class="options" id="streetOptions">
            <?php foreach(array_keys($streetColors) as $s){ echo "<div class='option' data-value='$s'>$s</div>"; } ?>
          </div>
          <input type="hidden" name="address_dropdown" id="addressInput"
                 value="<?php echo ($status === 'Resident') ? htmlspecialchars($address) : ''; ?>">
        </div>
        <div class="color-box" id="colorBox" style="background:<?php echo htmlspecialchars($color_code); ?>;"></div>
      </div>
    </div>

    <div id="nonResidentAddress">
      <label>Address</label>
      <div class="inline-flex">
        <input type="text" name="address_text" id="nonResidentText" value="<?php echo ($status === 'Non-Resident') ? htmlspecialchars($address) : ''; ?>">
        <span class="color-box" style="background:#000;"></span>
      </div>
    </div>

    <label>Birthday</label>
    <input type="date" name="birthday" value="<?php echo htmlspecialchars($birthday); ?>">

    <label>Contact Number</label>
    <input type="text" name="contact_number" value="<?php echo htmlspecialchars($contact_number); ?>">

    <label>Email</label>
    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>">

    <label>Gender</label>
    <select name="gender">
      <option value="">Select Gender</option>
      <option value="Male" <?php if($gender=="Male") echo "selected"; ?>>Male</option>
      <option value="Female" <?php if($gender=="Female") echo "selected"; ?>>Female</option>
    </select>

    <div style="display:flex;gap:10px;margin-top:20px;">
      <button type="submit" name="update" style="flex:1;"><i class="fas fa-save"></i> Update Patient</button>
      <a href="patients.php" style="flex:1;text-decoration:none;">
        <button type="button" style="width:100%;background:#ccc;color:#000;"><i class="fas fa-times"></i> Cancel</button>
      </a>
    </div>

    <input type="hidden" name="color_code" id="color_code" value="<?php echo htmlspecialchars($color_code); ?>">
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const streetColors = <?php echo json_encode($streetColors); ?>;
const statusRadios = document.querySelectorAll('input[name="status"]');
const residentDiv = document.getElementById('residentAddress');
const nonResidentDiv = document.getElementById('nonResidentAddress');
const streetSelectInput = document.getElementById('selectedStreet');
const streetDropdownWrapper = document.getElementById('streetDropdownWrapper');
const optionsContainer = document.getElementById('streetOptions');
const hiddenInput = document.getElementById('addressInput');
const colorBox = document.getElementById('colorBox');

function toggleForm() {
  const status = document.querySelector('input[name="status"]:checked').value;
  if (status === 'Resident') {
    residentDiv.style.display = 'block';
    nonResidentDiv.style.display = 'none';
  } else {
    residentDiv.style.display = 'none';
    nonResidentDiv.style.display = 'block';
  }
}

// Custom Dropdown Logic
streetSelectInput.addEventListener('focus', () => optionsContainer.style.display = 'block');
streetSelectInput.addEventListener('input', () => {
  const filter = streetSelectInput.value.toLowerCase();
  const options = optionsContainer.querySelectorAll('.option');
  options.forEach(opt => {
    opt.style.display = opt.textContent.toLowerCase().includes(filter) ? 'block' : 'none';
  });
  optionsContainer.style.display = 'block';
});
document.querySelectorAll('.option').forEach(option => {
  option.addEventListener('click', function() {
    streetSelectInput.value = this.textContent;
    hiddenInput.value = this.dataset.value;
    optionsContainer.style.display = 'none';
    const color = streetColors[this.dataset.value] || "#000000";
    colorBox.style.background = color;
    document.getElementById('color_code').value = color;
  });
});
document.addEventListener('click', function(e){
  if(!streetDropdownWrapper.contains(e.target)){
      optionsContainer.style.display = 'none';
  }
});

window.addEventListener('DOMContentLoaded', function() {
    toggleForm();
    const prefilledStreet = streetSelectInput.value;
    if(prefilledStreet){
        hiddenInput.value = prefilledStreet;
        const color = streetColors[prefilledStreet] || "#000000";
        colorBox.style.background = color;
        document.getElementById('color_code').value = color;
    }
});

statusRadios.forEach(r => r.addEventListener('change', toggleForm));

// Logout confirmation
document.getElementById('logoutBtn').addEventListener('click',function(e){
    e.preventDefault();
    Swal.fire({
        title:'Are you sure?',
        text:'You will be logged out from the system.',
        icon:'warning',
        showCancelButton:true,
        confirmButtonColor:'#001BB7',
        cancelButtonColor:'#d33',
        confirmButtonText:'Yes, log me out',
        cancelButtonText:'Cancel'
    }).then((result)=>{
        if(result.isConfirmed){window.location.href='logout.php';}
    });
});
</script>

</body>
</html>
