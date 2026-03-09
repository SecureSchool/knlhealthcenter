<?php
session_start();
require_once "config.php";

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
$tempProfile = '';
$error = '';
$success = '';

if (isset($_POST['register'])) {

    // Sanitize inputs
    $username = mysqli_real_escape_string($link, trim($_POST['username']));
    $password = mysqli_real_escape_string($link, trim($_POST['password']));
    $full_name = mysqli_real_escape_string($link, trim($_POST['full_name']));
    $address = mysqli_real_escape_string($link, trim($_POST['address']));
    $color_code = mysqli_real_escape_string($link, trim($_POST['color_code']));
    $birthday = mysqli_real_escape_string($link, trim($_POST['birthday']));
    $age = mysqli_real_escape_string($link, trim($_POST['age']));
    $contact_number = mysqli_real_escape_string($link, trim($_POST['contact_number']));
    $email = mysqli_real_escape_string($link, trim($_POST['email']));
    $gender = mysqli_real_escape_string($link, trim($_POST['gender']));
    $id_type = mysqli_real_escape_string($link, trim($_POST['id_type']));

// Guardian (may value only if minor)
$guardian_name = mysqli_real_escape_string($link, trim($_POST['guardian_name'] ?? ''));
$guardian_contact = mysqli_real_escape_string($link, trim($_POST['guardian_contact'] ?? ''));

// Terms and Conditions
$user_consent = isset($_POST['user_consent']) ? 1 : 0;

// Profile picture handling
// Profile picture handling
$tempProfile = $_POST['temp_profile'] ?? '';
if (!empty($_FILES['profile_picture']['name'])) {
    $targetDir = "uploads/temp/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = time() . "_" . basename($_FILES["profile_picture"]["name"]);
    $targetFilePath = $targetDir . $fileName;
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    $allowTypes = ['jpg','jpeg','png','gif'];

    if (in_array($fileType, $allowTypes)) {
        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFilePath)) {
            $tempProfile = "temp/" . $fileName; // relative path
        } else {
            $errors[] = "Failed to upload profile picture.";
        }
    } else {
        $errors[] = "Only JPG, JPEG, PNG, and GIF files are allowed.";
    }
} else {
    // Kung walang file, gamitin ang default avatar
    $tempProfile = 'default-avatar.png';
}

// ✅ Dito pa lang i-initialize ang errors array

    $errors = [];

    // 1️⃣ Required field validation
    if (empty($username)) $errors[] = "Username is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($address)) $errors[] = "Street is required.";
    if (empty($birthday)) $errors[] = "Birthday is required.";
    if (empty($age)) $errors[] = "Age is required.";
    if (empty($contact_number)) $errors[] = "Contact number is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (empty($gender)) $errors[] = "Gender is required.";
if (empty($id_type)) $errors[] = "Type of valid ID is required.";

    // If minor, guardian info is required
    if ($age < 18) {
        if (empty($guardian_name)) $errors[] = "Guardian name is required for minors.";
        if (empty($guardian_contact)) $errors[] = "Guardian contact number is required for minors.";
    }
    // 2️⃣ Contact number format validation
    if (!empty($contact_number) && !preg_match('/^[0-9]{11}$/', $contact_number)) {
        $errors[] = "Contact number must be exactly 11 digits.";
    }

    // 3️⃣ Only check duplicates if no required field errors
    if (empty($errors)) {
        $checkConditions = [];
        if (!empty($username)) $checkConditions[] = "username = '$username'";
        if (!empty($email)) $checkConditions[] = "email = '$email'";
        if (!empty($contact_number)) $checkConditions[] = "contact_number = '$contact_number'";
        if (!empty($full_name)) $checkConditions[] = "full_name = '$full_name'";

        if (!empty($checkConditions)) {
            $sql_check = "SELECT * FROM tblpatients WHERE " . implode(" OR ", $checkConditions);
            $check = mysqli_query($link, $sql_check);

            while ($existing = mysqli_fetch_assoc($check)) {
                if ($existing['username'] === $username) $errors[] = "Username already exists.";
                if ($existing['email'] === $email) $errors[] = "Email already exists.";
                if ($existing['contact_number'] === $contact_number) $errors[] = "Contact number already exists.";
                if ($existing['full_name'] === $full_name) $errors[] = "Full name already exists.";
            }
        }
    }
$validIDPath = NULL;
if (!empty($_FILES['valid_id']['name'])) {
    $targetDir = "uploads/valid_ids/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $fileName = time() . "_" . basename($_FILES['valid_id']['name']);
    $targetFile = $targetDir . $fileName;

    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $allowedTypes = ['jpg','jpeg','png','gif','pdf'];

    if (in_array($fileType, $allowedTypes)) {
        if (move_uploaded_file($_FILES['valid_id']['tmp_name'], $targetFile)) {
            $validIDPath = "valid_ids/" . $fileName;
        } else $errors[] = "Failed to upload valid ID.";
    } else $errors[] = "Valid ID must be JPG, PNG, GIF, or PDF.";
}
    if (empty($errors)) {
        $date_registered = date('Y-m-d');
       $residency_type = 'Non-Resident';
$priority = NULL; // Only set if special conditions are met

// Special conditions (Senior, Pregnant, PWD)
if ($age >= 60 || (isset($_POST['is_pregnant']) && $_POST['is_pregnant']==1) || 
    (isset($_POST['is_pwd']) && $_POST['is_pwd']==1)) {
    $priority = 1; // High priority
}

        $sql = "INSERT INTO tblpatients 
(username, password, full_name, address, color_code, birthday, age, contact_number, email, gender, status, residency_type, date_registered, profile_picture, valid_id, id_type, guardian_name, guardian_contact, priority)
VALUES
('$username', '$password', '$full_name', '$address', '$color_code', '$birthday', '$age', '$contact_number', '$email', '$gender', 'Pending', '$residency_type', '$date_registered', '$tempProfile', '$validIDPath', '$id_type', '$guardian_name', '$guardian_contact', '$priority')";



        if (mysqli_query($link, $sql)) {
            $success = "Registration successful! You can now <a href='login.php'>login</a>.";

                // Move uploaded picture from temp to profile folder
    if (!empty($tempProfile) && file_exists("uploads/" . $tempProfile)) {
        $finalDir = "uploads/profile/";
        if (!is_dir($finalDir)) mkdir($finalDir, 0777, true);

        $finalPath = $finalDir . basename($tempProfile);
        rename("uploads/" . $tempProfile, $finalPath);

        // update DB with new path
        $lastId = mysqli_insert_id($link);
        mysqli_query($link, "UPDATE tblpatients SET profile_picture = 'profile/" . basename($tempProfile) . "' WHERE patient_id = '$lastId'");
    }

            // Log success
            logAction($link, "Registered Non-Resident", "Patient Module", $username, "System");

            $_POST = [];
        } else {
            $errors[] = "Database error: " . mysqli_error($link);
            logAction($link, "Failed Non-Resident Registration", "Patient Module", $username, "System");
        }
    } else {
        // Log validation errors
        logAction($link, "Failed Non-Resident Registration (Validation)", "Patient Module", $username ?: 'N/A', "System");
    }

    // Prepare error output
    if (!empty($errors)) {
        $error = '<div class="error"><ul>';
        foreach ($errors as $err) {
            $error .= "<li>$err</li>";
        }
        $error .= '</ul></div>';
    }
}
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$backUrl = 'index.php'; // default
$backText = 'Home';

if(strpos($referrer, 'login.php') !== false){
    $backUrl = 'login.php';
    $backText = 'Login';
} elseif(strpos($referrer, 'index.php') !== false){
    $backUrl = 'index.php';
    $backText = 'Home';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Non-Resident Registration - Brgy Krus na Ligas Health Center</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
*{margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
body,html{height:100%; background:#f0f2f5; display:flex; justify-content:center; align-items:center;}

/* Card */
.register-card{
  background:white;
  padding:30px 25px;
  border-radius:15px;
  width:100%;
  max-width:700px;
  box-shadow:0 8px 25px rgba(0,0,0,0.15);
  animation:fadeIn 0.6s ease;
}
@keyframes fadeIn{from{opacity:0; transform:translateY(20px);} to{opacity:1; transform:translateY(0);}}

/* Logo and Title */
.register-card img{
  width:80px; height:80px;
  border-radius:50%;
  margin-bottom:15px;
  border:3px solid #001BB7;
  box-shadow:0 4px 10px rgba(0,0,0,0.1);
}
.register-card h2{font-size:22px; color:#001BB7; margin-bottom:5px; text-align:center;}
.register-card p.system-name{font-size:14px; color:#555; margin-bottom:20px; text-align:center;}

/* Error/Success */
.error{color:red; font-size:13px; margin-bottom:10px;}
.success{color:green; font-size:13px; margin-bottom:10px;}
a.back-login{display:block; margin-top:12px; text-align:center; color:#001BB7; text-decoration:none; font-weight:600;}
a.back-login:hover{text-decoration:underline;}

/* Form grid */
.register-card form{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px 15px;
}
.register-card form .full-width{grid-column:1/3;}
.register-card label{font-size:13px; font-weight:500; margin-bottom:3px; display:block;}
.register-card input, .register-card select{
  width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:8px; font-size:14px; transition:0.3s;
}
.register-card input:focus, .register-card select:focus{
  border-color:#001BB7;
  box-shadow:0 0 6px rgba(0,27,183,0.2);
  outline:none;
}

/* Button */
.register-card button{
  grid-column:1/3;
  padding:12px;
  background:#001BB7;
  color:white;
  font-size:15px;
  font-weight:600;
  border:none;
  border-radius:10px;
  cursor:pointer;
  transition:0.3s;
}
.register-card button:hover{background:#FFD43B; color:#001BB7;}

/* Street Color Box */
.street-color{width:25px; height:25px; display:inline-block; vertical-align:middle; border:1px solid #ddd; margin-left:8px; border-radius:5px; background:#000000;}

.register-card img{
  width:80px; 
  height:80px;
  border-radius:50%;
  margin:0 auto 15px auto; /* <-- ito ang nag-cecenter horizontally */
  display:block;           /* kailangan para gumana ang margin:auto */
  border:3px solid #001BB7;
  box-shadow:0 4px 10px rgba(0,0,0,0.1);
}
.back-btn {
    position: absolute;
    top: 15px;
    left: 20px;
    background-color: #001BB7;
    color: #fff;
    padding: 6px 12px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: 0.3s;
}

.back-btn:hover {
    background-color: #FFD43B;
    color: #001BB7;
}

.register-card {
    position: relative; /* para gumana ang absolute positioning */
}
.checkbox-wrapper {
    display: flex;
    align-items: center; /* Center checkbox vertically with text */
    gap: 8px;            /* Space between checkbox and text */
    margin-bottom: 6px;  /* Space between options */
    font-size: 14px;
    cursor: pointer;
}

.checkbox-wrapper input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #001BB7; /* Checkbox color */
}

</style>
</head>
<body>
<a href="<?php echo $backUrl; ?>" class="back-btn">&lt; <?php echo $backText; ?></a>

<div class="register-card">
  <img src="logo.png" alt="Health Center Logo">
  <h2>Non-Resident Registration</h2>
  <p class="system-name">Brgy Krus na Ligas Health Center</p>

  <?php if($error != ''): ?><p class="error"><?php echo $error; ?></p><?php endif; ?>

  <form method="POST" enctype="multipart/form-data">

    <div class="full-width">
      <label>Full Name</label>
      <input type="text" name="full_name" placeholder="Enter Full Name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
    </div>

    <div>
      <label>Username</label>
      <input type="text" name="username" placeholder="Enter Username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
    </div>

    <div>
      <label>Password</label>
      <input type="password" name="password" placeholder="Enter Password" value="<?php echo isset($_POST['password']) ? htmlspecialchars($_POST['password']) : ''; ?>" required>
    </div>

    <div>
      <label>Birthday</label>
      <input type="date" name="birthday" min="1900-01-01" max="2025-12-31" value="<?php echo isset($_POST['birthday']) ? htmlspecialchars($_POST['birthday']) : ''; ?>">
    </div>
<div>
  <label>Age</label>
  <input type="text" id="ageDisplay" readonly 
         value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>">
  <input type="hidden" name="age" id="age" 
         value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>">
</div>

    <div>
      <label>Contact Number</label>
      <input type="text" name="contact_number" placeholder="Enter Contact Number" value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>">
    </div>

    <div>
      <label>Email</label>
      <input type="email" name="email" placeholder="Enter Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
    </div>

    <div>
      <label>Gender</label>
      <select name="gender" required>
        <option value="">Select Gender</option>
        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender']=='Male') ? 'selected' : ''; ?>>Male</option>
        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender']=='Female') ? 'selected' : ''; ?>>Female</option>
      </select>
    </div>

    <div class="full-width">
      <label>Street</label>
      <div style="display:flex; align-items:center;">
        <input type="text" name="address" placeholder="Enter Street" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" required style="flex:1;">
        <div class="street-color"></div>
      </div>
      <input type="hidden" name="color_code" value="#000000">
    </div>


    <div class="full-width">
  <label>Profile Picture</label>
  <input type="file" name="profile_picture" id="profile_picture" accept="image/*">
  <input type="hidden" name="temp_profile" value="<?php echo htmlspecialchars($tempProfile); ?>">
  <div style="margin-top:10px; text-align:center;">
      <img id="previewImage" 
     src="<?php echo !empty($tempProfile) ? 'uploads/' . htmlspecialchars($tempProfile) : 'default-avatar.png'; ?>" 
     alt="Preview" width="120" height="120" 
     style="border-radius:50%; border:2px solid #001BB7;">

  </div>
</div>
<!-- VALID ID TYPE -->
<div>
    <label>Type of Valid ID *</label>
    <select name="id_type" id="id_type" required>
        <option value="">-- Select ID --</option>
        <option value="Barangay ID">Barangay ID</option>
        <option value="School ID">School ID</option>
        <option value="National ID">National ID</option>
        <option value="PhilHealth ID">PhilHealth ID</option>
        <option value="Other ID">Other ID</option>
    </select>
</div>

<!-- VALID ID UPLOAD -->
<div>
    <label>Upload Valid ID *</label>
    <input type="file" name="valid_id" accept=".jpg,.jpeg,.png,.pdf" required>
</div>

<!-- GUARDIAN SECTION (AUTO SHOW IF MINOR) -->
<div id="guardian_section" class="full-width"
     style="display:none; border:1px solid #ccc; padding:12px; border-radius:10px; margin-top:10px;">
    
    <h4 style="margin-bottom:8px;">Guardian Information (Required for Minors)</h4>

    <label>Guardian Full Name *</label>
    <input type="text" name="guardian_name">

    <label>Guardian Contact Number *</label>
    <input type="text" name="guardian_contact">
</div>
<div class="full-width" style="margin-top:10px; display:flex; align-items:center; gap:6px;">
    <input type="checkbox" id="user_consent" name="user_consent" value="1" required style="width:auto;">
    <label for="user_consent" style="margin:0; font-size:14px;">
        I agree to the <a href="terms.html" target="_blank">Terms and Conditions</a> and 
        <a href="privacy.html" target="_blank">Privacy Policy</a>.
    </label>
</div>
<div class="full-width" style="margin-top:10px; border:1px solid #ccc; padding:12px; border-radius:10px;">
  <h4 style="margin-bottom:8px;">Special Conditions (affects priority)</h4>

  <label class="checkbox-wrapper">
    <input type="checkbox" name="is_pwd" id="is_pwd" value="1">
    <span>Person with Disability (PWD)</span>
  </label>

  <label class="checkbox-wrapper">
    <input type="checkbox" name="is_pregnant" id="is_pregnant" value="1">
    <span>Pregnant</span>
  </label>

  <div style="margin-top:8px;">
    <strong>Calculated Priority:</strong>
    <input type="text" id="priorityDisplay" readonly 
           value="3 - Regular Non-Resident" 
           style="width:250px; text-align:center; border:none; font-weight:bold; padding:4px; border-radius:5px;">
  </div>
</div>

    <button type="submit" name="register">Register</button>
  </form>
</div>

<?php if($success != ''): ?>
<script>
    <?php if($success != ''): ?>
Swal.fire({
    icon: 'info',
    title: 'Registration Submitted!',
    html: 'Your account is <b>pending verification</b>. Please wait for admin approval before you can log in.',
    confirmButtonText: 'OK',
    allowOutsideClick: false
}).then((result)=>{
    if(result.isConfirmed) window.location.href='login.php';
});
<?php endif; ?>
</script>
<?php endif; ?>
<script>
const birthdayInput = document.querySelector('input[name="birthday"]');
const ageDisplay = document.getElementById('ageDisplay');
const ageHidden = document.getElementById('age');

function calculateAge(birthday){
    const today = new Date();
    const birth = new Date(birthday);
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age;
}

birthdayInput.addEventListener('change', function(){
    if(this.value){
        const age = calculateAge(this.value);
        ageDisplay.value = age;
        ageHidden.value = age;
    } else {
        ageDisplay.value = '';
        ageHidden.value = '';
    }
});

// prefill if value exists on reload
window.addEventListener('DOMContentLoaded', function(){
    if(birthdayInput.value){
        const age = calculateAge(birthdayInput.value);
        ageDisplay.value = age;
        ageHidden.value = age;
    }
});

const profileInput = document.getElementById('profile_picture');
const previewImage = document.getElementById('previewImage');

profileInput.addEventListener('change', function(){
    const file = this.files[0];
    if(file){
        const reader = new FileReader();
        reader.onload = function(e){
            previewImage.src = e.target.result;
        }
        reader.readAsDataURL(file);
    } else {
        previewImage.src = "default-avatar.png"; // make sure path is correct
    }
});
function checkGuardianSection() {
    let age = parseInt(document.getElementById('age').value || 0);

    if (age < 18 && age > 0) {
        document.getElementById('guardian_section').style.display = 'block';
    } else {
        document.getElementById('guardian_section').style.display = 'none';
    }
}

birthdayInput.addEventListener("change", checkGuardianSection);

// Run on page load (for form errors)
document.addEventListener("DOMContentLoaded", checkGuardianSection);

const ageInput = document.getElementById('age');
const priorityDisplay = document.getElementById('priorityDisplay');
const isPregnant = document.getElementById('is_pregnant');
const isPWD = document.getElementById('is_pwd');

const priorityLabels = {
    1: "High (Senior / Pregnant / PWD)",
};

// Priority colors
const priorityColors = {
    1: "#FF4D4D",  // Red
};
function calculatePriority() {
    let applicablePriorities = [];

    // Senior Citizen
    let age = parseInt(ageInput.value || 0);
    if(age >= 60) applicablePriorities.push(1);

    // Pregnant
    if(isPregnant.checked) applicablePriorities.push(1);

    // PWD
    if(isPWD.checked) applicablePriorities.push(1);

    if(applicablePriorities.length === 0){
    priorityDisplay.value = ""; // empty if no special condition
    priorityDisplay.style.backgroundColor = "#fff";
    priorityDisplay.style.color = "#000";
} else {
    priorityDisplay.value = "1 - High (Senior / Pregnant / PWD)";
    priorityDisplay.style.backgroundColor = "#FF4D4D"; // red
    priorityDisplay.style.color = "#fff";
}

}

// Event listeners
ageInput.addEventListener('input', calculatePriority);
isPregnant.addEventListener('change', calculatePriority);
isPWD.addEventListener('change', calculatePriority);
window.addEventListener('DOMContentLoaded', calculatePriority);

// Run on page load (for pre-filled values)
window.addEventListener('DOMContentLoaded', calculatePriority);

// Event listeners
ageInput.addEventListener('input', calculatePriority);
isPregnant.addEventListener('change', calculatePriority);
isPWD.addEventListener('change', calculatePriority);

// Run on page load (for pre-filled values)
window.addEventListener('DOMContentLoaded', calculatePriority);

birthdayInput.addEventListener('change', function(){
    if(this.value){
        const age = calculateAge(this.value);
        ageDisplay.value = age;
        ageHidden.value = age;

        // Recalculate priority whenever age changes
        calculatePriority();
        checkGuardianSection(); // also show/hide guardian section dynamically
    } else {
        ageDisplay.value = '';
        ageHidden.value = '';
        calculatePriority();
        checkGuardianSection();
    }
});
window.addEventListener('DOMContentLoaded', function(){
    if(birthdayInput.value){
        const age = calculateAge(birthdayInput.value);
        ageDisplay.value = age;
        ageHidden.value = age;
    }
    // Initial calculation for priority and guardian section
    calculatePriority();
    checkGuardianSection();
});

</script>

</body>
</html>
