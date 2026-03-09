<?php
session_start();
require_once "config.php";

$error = '';
$login_success = '';
$old_role = $_POST['role'] ?? '';
$old_username = $_POST['username'] ?? '';

if(isset($_SESSION['login_success'])){
    $login_success = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}

if(isset($_POST['login'])){
    $role = $_POST['role'];
    $username = mysqli_real_escape_string($link, $_POST['username']);
    $password = $_POST['password'];
    $error = "Invalid username or password.";

    if($role == 'admin'){
        $res = mysqli_query($link, "SELECT * FROM tbladmin WHERE username='$username' LIMIT 1");
        if($row = mysqli_fetch_assoc($res)){
            if($password === $row['password']){
                $_SESSION['admin_id'] = $row['admin_id'];
                $_SESSION['admin_name'] = $row['full_name'];
                $_SESSION['login_success'] = "Welcome back, ".$row['full_name']."!";
                header("Location: login.php"); exit;
            }
        }
    }
    elseif($role == 'staff'){
        $res = mysqli_query($link, "SELECT * FROM tblstaff WHERE username='$username' LIMIT 1");
        if($row = mysqli_fetch_assoc($res)){
            if($row['status'] !== 'Active'){
                $error = "Your staff account is inactive. Please contact the admin.";
            } elseif($password === $row['password']){
                $_SESSION['staff_id'] = $row['staff_id'];
                $_SESSION['staff_name'] = $row['full_name'];
                $_SESSION['login_success'] = "Welcome back, ".$row['full_name']."!";
                header("Location: login.php"); exit;
            }
        }
    }
    elseif($role == 'doctor'){
        $res = mysqli_query($link, "SELECT * FROM tbldoctors WHERE username='$username' LIMIT 1");
        if($row = mysqli_fetch_assoc($res)){
            if($row['status'] !== 'Active'){
                $error = "Your doctor account is inactive. Please contact the admin.";
            } elseif($password === $row['password']){
                $_SESSION['doctor_id'] = $row['doctor_id'];
                $_SESSION['doctor_name'] = $row['fullname'];
                $_SESSION['login_success'] = "Welcome back, Dr. ".$row['fullname']."!";
                header("Location: login.php"); exit;
            }
        }
    }
    elseif($role == 'patient'){
    $res = mysqli_query($link, "SELECT * FROM tblpatients WHERE username='$username' LIMIT 1");
    if($row = mysqli_fetch_assoc($res)){
        if($row['status'] === 'Pending'){
            $error = "Your account is pending verification. Please wait for admin approval.";
        } elseif($row['status'] === 'Unverified'){
            $error = "Your account is unverified. Please contact the admin.";
        } elseif($row['status'] === 'Verified'){
            if($password === $row['password']){
                $_SESSION['patient_id'] = $row['patient_id'];
                $_SESSION['patient_name'] = $row['full_name'];
                $_SESSION['login_success'] = "Welcome, ".$row['full_name']."!";
                header("Location: login.php"); 
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Your account status is invalid. Please contact admin.";
        }
    } else {
        $error = "Invalid username or password.";
    }
}

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Brgy Krus na Ligas Health Center</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
*{margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
body,html{height:100%; background:#f0f2f5;}

/* Container */
.container{display:flex; height:100vh; justify-content:center; align-items:center; background:#eef2ff;}

/* Card */
.login-card{
  background:white;
  padding:50px 40px;
  border-radius:15px;
  box-shadow:0 6px 18px rgba(0,0,0,0.15);
  width:100%;
  max-width:420px;
  text-align:center;
  animation:fadeIn 0.6s ease;
}
@keyframes fadeIn{from{opacity:0; transform:translateY(20px);} to{opacity:1; transform:translateY(0);}}

/* Logo */
.login-card img{
  width:100px; height:100px; border-radius:50%;
  margin-bottom:20px; border:4px solid #001BB7;
  box-shadow:0 4px 10px rgba(0,0,0,0.15);
}

/* Titles */
.login-card h2{font-size:24px; font-weight:700; color:#001BB7; margin-bottom:5px;}
.login-card p{font-size:14px; color:#555; margin-bottom:25px;}

/* Inputs */
.login-card form{display:flex; flex-direction:column; gap:18px;}
.login-card input, .login-card select{
  width:100%; padding:14px;
  border:1px solid #ccc;
  border-radius:10px;
  font-size:15px;
  transition:0.3s;
}
.login-card input:focus, .login-card select:focus{
  border-color:#001BB7;
  box-shadow:0 0 6px rgba(0,27,183,0.3);
  outline:none;
}

/* Button */
.login-card button{
  padding:14px;
  background:#001BB7;
  color:white;
  font-size:16px;
  font-weight:600;
  border:none;
  border-radius:10px;
  cursor:pointer;
  transition:0.3s;
}
.login-card button:hover{background:#FFD43B; color:#001BB7;}

/* Register + Forgot */
#registerBox{margin-top:15px; display:none; font-size:14px;}
#registerBox a{color:#001BB7; font-weight:600; text-decoration:none;}
#registerBox a:hover{text-decoration:underline;}
#forgotPassword{margin-top:15px; font-size:14px;}
#forgotPassword a{color:#001BB7; text-decoration:none;}
#forgotPassword a:hover{text-decoration:underline;}

.btn-back-fixed {
  position: fixed;
  top: 20px;
  left: 20px;
  padding: 12px 25px;
  background: #FFD43B;
  color: #001BB7;
  font-weight: 600;
  border-radius: 25px;
  text-decoration: none;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  z-index: 10000;
  transition: 0.3s;
  display: flex;
  align-items: center;
  gap: 8px; /* space between arrow and text */
}
.password-wrapper {
  position: relative;
  width: 100%;
}

.password-wrapper input {
  padding-right: 45px;
}

.toggle-password {
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  cursor: pointer;
  font-size: 16px;
  color: #555;
  user-select: none;
}

.toggle-password:hover {
  color: #001BB7;
}
.toggle-password{
  z-index: 10;
  pointer-events: auto;
}
</style>
</head>
<body>

<a href="index.php" class="btn-back-fixed">< Home</a>

<div class="container">
  <div class="login-card">
    <img src="logo.png" alt="Health Center Logo">
    <h2>KNL Health Center</h2>
    <p>Login to continue</p>

    <form method="POST">
      <select name="role" required>
  <option value="">Select Role</option>
  <option value="admin" <?php if($old_role=='admin') echo 'selected'; ?>>Admin</option>
  <option value="staff" <?php if($old_role=='staff') echo 'selected'; ?>>Staff</option>
  <option value="doctor" <?php if($old_role=='doctor') echo 'selected'; ?>>Doctor</option>
  <option value="patient" <?php if($old_role=='patient') echo 'selected'; ?>>Patient</option>
</select>

      <input type="text" name="username" placeholder="Enter your username" required value="<?php echo htmlspecialchars($old_username); ?>">
      <div class="password-wrapper">
  <input type="password" name="password" id="password" placeholder="Enter your password" required>
  <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
</div>
      <button type="submit" name="login">Login</button>
    </form>

    <!-- Register box (only for patients) -->
    <div id="registerBox">
      <p>Don’t have an account?
        <a href="register_resident.php">Resident</a> |
        <a href="register_nonresident.php">Non-Resident</a>
      </p>
    </div>

    <!-- Forgot password -->
    <div id="forgotPassword">
      <a href="#" id="forgotPasswordLink">Forgot Password?</a>
    </div>
  </div>
</div>

<script>
// show register only if role=patient
document.querySelector('select[name="role"]').addEventListener('change', function(){
  document.getElementById('registerBox').style.display = (this.value === 'patient') ? 'block' : 'none';
});

// forgot password modal
document.getElementById('forgotPasswordLink').addEventListener('click', function(e){
  e.preventDefault();
  Swal.fire({
    title: 'Forgot Password',
    html: `
      <select id="fp_role" class="swal2-input">
        <option value="">Select Role</option>
        <option value="admin">Admin</option>
        <option value="staff">Staff</option>
        <option value="doctor">Doctor</option>
        <option value="patient">Patient</option>
      </select>
      <input type="text" id="fp_username" class="swal2-input" placeholder="Enter your username">
    `,
    showCancelButton: true,
    confirmButtonText: 'Next',
    preConfirm: () => {
      const role = document.getElementById('fp_role').value;
      const username = document.getElementById('fp_username').value;
      if(!role || !username){
        Swal.showValidationMessage("Please select role and enter username");
        return false;
      }
      return { role, username };
    }
  }).then((result) => {
    if(result.isConfirmed){
      const {role, username} = result.value;
      Swal.fire({
        title: 'Reset Password',
        html: `
          <input type="password" id="new_password" class="swal2-input" placeholder="Enter new password">
          <input type="password" id="confirm_password" class="swal2-input" placeholder="Confirm new password">
        `,
        showCancelButton: true,
        confirmButtonText: 'Reset',
        preConfirm: () => {
          const pass = document.getElementById('new_password').value;
          const confirm = document.getElementById('confirm_password').value;
          if(!pass || !confirm){
            Swal.showValidationMessage("Fill in both fields");
            return false;
          }
          if(pass !== confirm){
            Swal.showValidationMessage("Passwords do not match");
            return false;
          }
          return {pass};
        }
      }).then((pwRes) => {
        if(pwRes.isConfirmed){
          fetch('reset_password.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `role=${encodeURIComponent(role)}&username=${encodeURIComponent(username)}&new_password=${encodeURIComponent(pwRes.value.pass)}`
          })
          .then(res => res.json())
          .then(data => {
            Swal.fire({
              icon: data.status === 'success' ? 'success':'error',
              title: data.status === 'success' ? 'Password Reset' : 'Error',
              text: data.message
            });
          })
          .catch(() => Swal.fire({icon:'error',title:'Error',text:'Something went wrong'}));
        }
      });
    }
  });
});

<?php if($login_success != ''): ?>
Swal.fire({
  icon: 'success',
  title: 'Login Successful',
  text: '<?php echo $login_success; ?>',
  showConfirmButton: false,
  timer: 2000
}).then(() => {
  <?php if(isset($_SESSION['admin_id'])): ?> window.location.href = 'dashboard.php';
  <?php elseif(isset($_SESSION['staff_id'])): ?> window.location.href = 'staff_dashboard.php';
  <?php elseif(isset($_SESSION['doctor_id'])): ?> window.location.href = 'dashboard_doctors.php';
  <?php elseif(isset($_SESSION['patient_id'])): ?> window.location.href = 'patient_dashboard.php';
  <?php endif; ?>
});
<?php endif; ?>
</script>

<?php if($error != ''): ?>
<script>
Swal.fire({
  icon: 'error',
  title: 'Login Failed',
  text: '<?php echo $error; ?>'
});
</script>
<?php endif; ?>
<script>
const roleSelect = document.querySelector('select[name="role"]');
const registerBox = document.getElementById('registerBox');

registerBox.style.display = roleSelect.value === 'patient' ? 'block' : 'none';

roleSelect.addEventListener('change', function(){
  registerBox.style.display = (this.value === 'patient') ? 'block' : 'none';
});
</script>
<script>
const togglePassword = document.getElementById("togglePassword");
const passwordInput = document.getElementById("password");

togglePassword.addEventListener("click", function () {
  const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
  passwordInput.setAttribute("type", type);

  this.classList.toggle("fa-eye");
  this.classList.toggle("fa-eye-slash");
});
</script>
</body>
</html>
