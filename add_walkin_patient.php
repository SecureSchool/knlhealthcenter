<?php
session_start();
require_once "config.php";

$errors = [];
$success = '';

// Initialize variables
$username = $full_name = $address = $color_code = $birthdate = $contact_number = $email = $gender = '';
$status = "Non-Resident"; // default selected

// Street colors
$streetColors = [
    "Alley 22" => "#FF5733", "Alley 44" => "#33FF57", "Angeles Street" => "#3357FF",
    "Antipolo Street" => "#F1C40F", "B. Baluyot Street" => "#9B59B6",
    "Carlos P. Garcia Avenue" => "#E67E22", "E. Ramos Street" => "#1ABC9C",
    "Emilio Jacinto Street" => "#C0392B", "Eugenio Street" => "#2C3E50", "Fatima Street" => "#7F8C8D"
];

if(isset($_POST['register'])){
    $username = mysqli_real_escape_string($link, $_POST['username']);
    $password = mysqli_real_escape_string($link, $_POST['password']);
    $full_name = mysqli_real_escape_string($link, $_POST['full_name']);
    $status = mysqli_real_escape_string($link, $_POST['status']);
    
    if($status === "Resident"){
        $address = isset($_POST['address_dropdown']) ? mysqli_real_escape_string($link, $_POST['address_dropdown']) : '';
        $color_code = $streetColors[$address] ?? "#000000";
    } else {
        $address = isset($_POST['address_text']) ? mysqli_real_escape_string($link, $_POST['address_text']) : '';
        $color_code = "#000000"; // Non-resident always black
    }

    $birthdate = mysqli_real_escape_string($link, $_POST['birthdate']);
    $contact_number = mysqli_real_escape_string($link, $_POST['contact_number']);
    $email = mysqli_real_escape_string($link, $_POST['email']);
    $gender = mysqli_real_escape_string($link, $_POST['gender']);

    // Validations
    if(empty($username)) $errors[] = "Username is required.";
    if(empty($password)) $errors[] = "Password is required.";
    if(empty($full_name)) $errors[] = "Full Name is required.";
    
    if($status === "Resident" && empty($address)) $errors[] = "Please select a street for residents.";
    if($status === "Non-Resident" && empty($address)) $errors[] = "Please enter an address for non-residents.";

    if(empty($errors)){
        $res = mysqli_query($link, "SELECT username FROM tblpatients WHERE username='$username'");
        if(mysqli_num_rows($res) > 0) $errors[] = "Username already exists.";

        $res = mysqli_query($link, "SELECT full_name FROM tblpatients WHERE full_name='$full_name'");
        if(mysqli_num_rows($res) > 0) $errors[] = "Full Name already exists.";

        if(!empty($email)){
            $res = mysqli_query($link, "SELECT email FROM tblpatients WHERE email='$email'");
            if(mysqli_num_rows($res) > 0) $errors[] = "Email already exists.";
        }

        if(!empty($contact_number)){
            $res = mysqli_query($link, "SELECT contact_number FROM tblpatients WHERE contact_number='$contact_number'");
            if(mysqli_num_rows($res) > 0) $errors[] = "Contact Number already exists.";
        }
    }

    // Insert and redirect
    if(empty($errors)){
        $sql = "INSERT INTO tblpatients (username, password, full_name, address, color_code, birthdate, contact_number, email, gender, status) 
                VALUES ('$username', '$password', '$full_name', '$address', '$color_code', '$birthdate', '$contact_number', '$email', '$gender', '$status')";

        if(mysqli_query($link, $sql)){
            $_SESSION['success_add'] = "Patient registered successfully!";
            header("Location: patients.php");
            exit;
        } else {
            $errors[] = "Database error: " . mysqli_error($link);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Walk-in Patient Registration</title>
<style>
/* same styling as before */
body, html { font-family: Arial, sans-serif; background:#f0f2f5; margin:0; padding:0; }
.container { max-width: 500px; margin: 50px auto; background:#fff; padding: 50px; border-radius: 6px; box-shadow: 0 0 10px #ccc;}
h2 { text-align:center; margin-bottom: 20px; }
label { display:block; margin-top: 10px; }
input[type=text], input[type=password], input[type=date], input[type=email], select { width:100%; padding:8px; margin-top:4px; border-radius:4px; border:1px solid #ccc; }
.color-box { display:inline-block; width:25px; height:25px; vertical-align: middle; border:1px solid #aaa; margin-left:10px; border-radius:4px; }
.status-group { margin-top: 10px; }
.status-group label { display:inline-block; margin-right: 20px; }
button { margin-top: 20px; width:100%; background:#2575fc; border:none; padding: 12px; color:#fff; font-size:16px; border-radius:5px; cursor:pointer; }
button:hover { background:#1a5dc9; }
.error { color:red; margin-bottom:10px; }
.success { color:green; margin-bottom:10px; }
.inline-flex { display:flex; align-items:center; }
.inline-flex input[type="text"] { flex:1; }
</style>
</head>
<body>

<div class="container">
<h2>Walk-in Patient Registration</h2>

<?php if(!empty($errors)): ?>
    <?php foreach($errors as $err): ?>
      <div class="error"><?php echo $err; ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<form method="POST" id="walkinForm">
<div class="status-group">
  <label><input type="radio" name="status" value="Resident" <?php echo ($status==="Resident")?"checked":""; ?>> Resident</label>
  <label><input type="radio" name="status" value="Non-Resident" <?php echo ($status==="Non-Resident")?"checked":""; ?>> Walk-in / Non-Resident</label>
</div>

<label>Full Name</label>
<input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>

<label>Username</label>
<input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>

<label>Password</label>
<input type="password" name="password" required>

<!-- Resident street select -->
<div id="residentAddress" style="margin-top:10px;">
  <label>Street</label>
  <div class="inline-flex">
    <select name="address_dropdown" id="streetSelect">
      <option value="">Select Street</option>
      <?php
      foreach($streetColors as $street => $color){
        $sel = ($address === $street) ? "selected" : "";
        echo "<option value=\"$street\" $sel>$street</option>";
      }
      ?>
    </select>
    <span class="color-box" id="residentColorBox" style="background:#000;"></span>
  </div>
</div>

<!-- Non-Resident address text -->
<div id="nonResidentAddress" style="margin-top:10px; display:none;">
  <label>Address</label>
  <div class="inline-flex">
    <input type="text" name="address_text" id="nonResidentText" value="<?php echo ($status === 'Non-Resident') ? htmlspecialchars($address) : ''; ?>" >
    <span class="color-box" style="background:#000000;"></span>
  </div>
</div>

<input type="hidden" name="color_code" id="color_code" value="">

<label>Birthdate</label>
<input type="date" name="birthdate" value="<?php echo htmlspecialchars($birthdate); ?>">

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

<div style="display: flex; gap: 10px; margin-top: 20px;">
  <button type="submit" name="register" style="flex: 1;">Register</button>
  <a href="patients.php" style="flex: 1; text-decoration: none;">
    <button type="button" style="width: 100%; background: #ccc; color: #000;">Cancel</button>
  </a>
</div>
</form>
</div>

<script>
const streetColors = <?php echo json_encode($streetColors); ?>;
const statusRadios = document.querySelectorAll('input[name="status"]');
const residentDiv = document.getElementById('residentAddress');
const nonResidentDiv = document.getElementById('nonResidentAddress');
const streetSelect = document.getElementById('streetSelect');
const residentColorBox = document.getElementById('residentColorBox');
const colorInput = document.getElementById('color_code');

function updateForm() {
  const status = document.querySelector('input[name="status"]:checked').value;
  if(status === 'Resident'){
    residentDiv.style.display = 'block';
    nonResidentDiv.style.display = 'none';
    const street = streetSelect.value;
    const color = streetColors[street] || '#000000';
    residentColorBox.style.background = color;
    colorInput.value = color;
    streetSelect.required = true;
    document.getElementById('nonResidentText').required = false;
  } else {
    residentDiv.style.display = 'none';
    nonResidentDiv.style.display = 'block';
    residentColorBox.style.background = '#000000';
    colorInput.value = '#000000';
    streetSelect.required = false;
    document.getElementById('nonResidentText').required = true;
  }
}

streetSelect.addEventListener('change', () => {
  if(document.querySelector('input[name="status"]:checked').value === 'Resident'){
    const color = streetColors[streetSelect.value] || '#000000';
    residentColorBox.style.background = color;
    colorInput.value = color;
  }
});

statusRadios.forEach(radio => {
  radio.addEventListener('change', updateForm);
});

window.onload = updateForm;
</script>
</body>
</html>
