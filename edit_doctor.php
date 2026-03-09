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

// Redirect if not logged in as admin
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

// Get doctor ID
if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: doctors.php");
    exit;
}
$doctor_id = intval($_GET['id']);

// Fetch doctor info
$doctor_result = mysqli_query($link, "SELECT * FROM tbldoctors WHERE doctor_id='$doctor_id'");
if(mysqli_num_rows($doctor_result) === 0){
    header("Location: doctors.php");
    exit;
}
$doctor = mysqli_fetch_assoc($doctor_result);

// Initialize variables
$fullname = $doctor['fullname'];
$username = $doctor['username'];
$contact_number = $doctor['contact_number'];
$email = $doctor['email'];
$specializations = explode(',', $doctor['specialization']);
$errors = [];
$success = false;

if(isset($_POST['update'])){
    $fullname = trim(mysqli_real_escape_string($link, $_POST['fullname']));
    $username = trim(mysqli_real_escape_string($link, $_POST['username']));
    $contact_number = trim(mysqli_real_escape_string($link, $_POST['contact_number']));
    $email = trim(mysqli_real_escape_string($link, $_POST['email']));
    $specializations = isset($_POST['specialization']) ? $_POST['specialization'] : [];
    $specialization_str = implode(',', $specializations);

    // Handle profile picture upload
$profile_pic_name = $doctor['profile_pic']; // current picture
if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK){
    $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
    $fileName = $_FILES['profile_pic']['name'];
    $fileSize = $_FILES['profile_pic']['size'];
    $fileType = $_FILES['profile_pic']['type'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    if(in_array($fileExtension, $allowedExtensions)){
        $newFileName = "doctor_".$doctor_id."_".time().".".$fileExtension;
        $uploadFileDir = 'uploads/doctors/';
        if(!is_dir($uploadFileDir)) mkdir($uploadFileDir, 0755, true);
        $dest_path = $uploadFileDir.$newFileName;

        if(move_uploaded_file($fileTmpPath, $dest_path)){
            // Delete old picture if not default
            if(!empty($doctor['profile_pic']) && $doctor['profile_pic'] != 'default.png' && file_exists($uploadFileDir.$doctor['profile_pic'])){
                unlink($uploadFileDir.$doctor['profile_pic']);
            }
            $profile_pic_name = $newFileName;
        } else {
            $errors[] = "There was an error uploading the profile picture.";
        }
    } else {
        $errors[] = "Invalid file type. Only jpg, jpeg, png, gif allowed.";
    }
}

    // Validation
    if(empty($fullname)) $errors[] = "Full name is required.";
    if(empty($username)) $errors[] = "Username is required.";
    if(empty($specializations)) $errors[] = "At least one specialization is required.";

    if(!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)){
        $errors[] = "Username must be 3-20 characters and contain only letters, numbers, or underscores.";
    }
    if(!empty($contact_number) && !preg_match('/^\d{11}$/', $contact_number)){
        $errors[] = "Contact number must be numeric and exactly 11 digits.";
    }
    if(!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)){
        $errors[] = "Invalid email address format.";
    }

    // Duplicate check excluding current doctor
    if(count($errors) === 0){
        $dup_sql = "SELECT * FROM tbldoctors WHERE (fullname='$fullname' OR username='$username' OR email='$email' OR contact_number='$contact_number') AND doctor_id != '$doctor_id'";
        $dup_res = mysqli_query($link, $dup_sql);
        if(mysqli_num_rows($dup_res) > 0){
            $errors[] = "Another doctor with the same name, username, email, or contact number exists.";
        }
    }

    // Update DB
    if(count($errors) === 0){
        $update_sql = "UPDATE tbldoctors SET 
    fullname='$fullname', 
    username='$username', 
    specialization='$specialization_str', 
    contact_number='$contact_number', 
    email='$email',
    profile_pic='$profile_pic_name' 
    WHERE doctor_id='$doctor_id'";

        if(mysqli_query($link, $update_sql)){
            $success = true;
            $admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';
            logAction($link, "Update", "Doctors", $fullname, $admin_name);
        } else {
            $errors[] = "Database error: ".mysqli_error($link);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Doctor - Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif;}
body {background:#f5f7fa; display:flex; min-height:100vh; color:#1e293b;}

/* Sidebar */
.sidebar {
    width:250px;
    background:#001BB7;
    color:#fff;
    position:fixed;
    height:100%;
    padding:25px 15px;
    display:flex;
    flex-direction:column;
}
.sidebar h2 {text-align:center; margin-bottom:35px; font-size:24px; font-weight:700;}
.sidebar a {color:#ffffffcc; display:flex; align-items:center; padding:12px 18px; margin:10px 0; text-decoration:none; border-radius:12px; transition:0.3s; font-weight:500;}
.sidebar a i {margin-right:12px; font-size:18px;}
.sidebar a:hover {background:rgba(255,255,255,0.15); padding-left:24px; color:#fff;}
.sidebar a.active {background:rgba(255,255,255,0.25); font-weight:600;}
.sidebar h2 i {font-size:28px; vertical-align:middle;}

/* Main content */
.main {margin-left:250px; padding:30px; flex:1;}
h1 {margin-bottom:20px; color:#001BB7; font-weight:700;}
form {background:#fff; padding:25px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);}
label {display:block; margin:10px 0 5px;}
input, button {width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:15px;}
button.submit-btn {background:#001BB7; color:white; padding:10px; border:none; border-radius:8px; cursor:pointer; font-weight:600;}
button.submit-btn:hover {background:#001199;}
.error {color:red; margin-bottom:10px;}
.checkbox-group {display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px;}
.checkbox-btn {display:flex; align-items:center; border:1px solid #ddd; padding:8px 12px; border-radius:6px; cursor:pointer; background:#f9f9f9; transition:0.2s;}
.checkbox-btn input {margin:0 6px 0 0; vertical-align: middle;}
.checkbox-btn input:checked + span {font-weight:600; color:#001BB7;}
.checkbox-btn span {line-height:1.2;}
.checkbox-btn:hover {background:#eef2ff; border-color:#001BB7;}
.back-btn {
    display:inline-flex;
    align-items:center;
    color:#001BB7;
    font-weight:600;
    text-decoration:none;
    font-size:20px;
    margin-bottom:15px;
    transition:0.2s;
}
.back-btn i {
    font-size:20px;
}
.back-btn:hover {
    color:#001199;
}
</style>
</head>
<body>

<div class="sidebar">
    <h2><i class="fas fa-hospital" style="margin-right:10px;"></i> KNL Health Center</h2>
    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="patients.php"><i class="fas fa-users"></i> Patients</a>
    <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
    <a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a>
    <a href="doctors.php" class="active"><i class="fas fa-user-md"></i> Doctors</a>
    <a href="services.php"><i class="fas fa-stethoscope"></i> Services</a>
    <a href="announcement.php"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
    <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main">
    <a href="doctors.php" class="back-btn"><i class="fas fa-chevron-left"></i></a>
    <h1><i class="fas fa-edit me-2"></i> Edit Doctor</h1>

    <?php if(!empty($errors)): ?>
    <div class="error">
        <ul>
        <?php foreach($errors as $err): ?>
            <li><?php echo htmlspecialchars($err); ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Profile Picture</label>
<div style="margin-bottom:15px;">
    <img src="uploads/doctors/<?php echo htmlspecialchars($doctor['profile_pic'] ?? 'default.png'); ?>" 
         alt="Profile" width="100" height="100" 
         style="border-radius:50%; object-fit:cover; display:block; margin-bottom:10px;">
    <input type="file" name="profile_pic" accept="image/*">
</div>

        <label>Full Name *</label>
        <input type="text" name="fullname" value="<?php echo htmlspecialchars($fullname); ?>" required>

        <label>Username *</label>
        <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>

        <label>Specialization *</label>
        <div class="checkbox-group">
            <?php
            $all_specializations = ['Dental','Check Up','Vaccine'];
            foreach($all_specializations as $spec):
            ?>
            <label class="checkbox-btn">
                <input type="checkbox" name="specialization[]" value="<?php echo $spec; ?>" <?php if(in_array($spec,$specializations)) echo 'checked'; ?>>
                <span><?php echo $spec; ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <label>Contact Number</label>
        <input type="text" name="contact_number" value="<?php echo htmlspecialchars($contact_number); ?>">

        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>">

        <button type="submit" name="update" class="submit-btn"><i class="fas fa-save me-2"></i> Update Doctor</button>
    </form>
</div>

<script>
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
        if(result.isConfirmed){ window.location.href='logout.php'; }
    });
});

<?php if($success): ?>
Swal.fire({
    icon:'success',
    title:'Success',
    text:'Doctor updated successfully!',
    confirmButtonColor: '#001BB7'
}).then(()=>{ window.location.href='doctors.php'; });
<?php endif; ?>
</script>
</body>
</html>
