<?php
require_once "config.php";

$role = $_GET['role'] ?? '';
$username = $_GET['username'] ?? '';

if (!$role || !$username) {
    die("Missing role or username. Please go back to login.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f0f2f5;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}
.card {
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    width: 380px;
    text-align: center;
}
.card h2 {
    color: #2575fc;
    margin-bottom: 20px;
}
.card input {
    width: 100%;
    padding: 12px;
    margin: 8px 0;
    border: 1px solid #ccc;
    border-radius: 8px;
}
.card button {
    width: 100%;
    padding: 12px;
    margin-top: 10px;
    background: #2575fc;
    border: none;
    border-radius: 8px;
    color: #fff;
    font-size: 15px;
    cursor: pointer;
}
.card button:hover {
    background: #1a5dc9;
}
</style>
</head>
<body>
<div class="card">
    <h2>Reset Password</h2>
    <form method="POST" action="reset_password.php">
        <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
        <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
        <input type="password" name="new_password" placeholder="Enter new password" required>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
        <button type="submit">Reset Password</button>
    </form>
</div>
</body>
</html>
