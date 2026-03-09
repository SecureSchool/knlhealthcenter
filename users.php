<?php
session_start();
require_once "config.php";

if(!isset($_SESSION["admin_id"])){
    header("location: login.php");
    exit;
}

// Filters
$where = [];
if(!empty($_GET['search'])) {
    $search = mysqli_real_escape_string($link, $_GET['search']);
    $where[] = "(full_name LIKE '%$search%' OR username LIKE '%$search%')";
}
if(!empty($_GET['date'])) {
    $date = mysqli_real_escape_string($link, $_GET['date']);
    $where[] = "DATE(date_created)='$date'";
}
if(!empty($_GET['role'])) {
    $role = mysqli_real_escape_string($link, $_GET['role']);
    $where[] = "role='$role'";
}
if(!empty($_GET['status'])) {
    $status = mysqli_real_escape_string($link, $_GET['status']);
    $where[] = "status='$status'";
}

$where_sql = count($where) > 0 ? "WHERE ".implode(" AND ", $where) : "";

// Fetch users
$sql = "SELECT * FROM tblusers $where_sql ORDER BY date_created DESC";
$result = mysqli_query($link, $sql);

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Users - BKNL Health Center</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f7f9;margin:0;}
.sidebar{width:220px;background:#2c3e50;height:100vh;position:fixed;color:white;padding-top:20px;}
.sidebar .logo{text-align:center;margin-bottom:20px;}
.sidebar .logo img{width:80px;}
.sidebar a{display:block;color:white;text-decoration:none;padding:12px 20px;margin:5px 0;border-radius:6px;}
.sidebar a:hover{background:#34495e;}
.main{margin-left:240px;padding:20px;}
h1{color:#2c3e50;margin-bottom:20px;}
form.filters{margin-bottom:20px; display:flex; flex-wrap:wrap; gap:10px;}
form.filters input, form.filters select, form.filters button{padding:8px; border-radius:6px; border:1px solid #ccc;}
table{width:100%; border-collapse:collapse; background:white; border-radius:8px; overflow:hidden; box-shadow:0 0 10px rgba(0,0,0,0.1);}
th, td{border:1px solid #ccc; padding:8px; text-align:left;}
th{background:#27ae60;color:white;}
a.btn{text-decoration:none;padding:6px 12px;border-radius:6px;color:white;margin-right:5px;}
a.view{background:#2980b9;}
a.approve{background:#27ae60;}
a.delete{background:#e74c3c;}
a.add{background:#27ae60;color:white;padding:10px 15px;border-radius:6px;text-decoration:none;margin-bottom:15px;display:inline-block;}
a.add:hover{background:#2ecc71;}
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
<h1>Users</h1>

<a href="add_user.php" class="add">+ Add User</a>

<form method="GET" class="filters">
    <input type="text" name="search" placeholder="Search by name or username" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
    <input type="date" name="date" value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>">
    <select name="role">
        <option value="">All Roles</option>
        <option value="Admin" <?php if(isset($_GET['role']) && $_GET['role']=='Admin') echo 'selected'; ?>>Admin</option>
        <option value="Staff" <?php if(isset($_GET['role']) && $_GET['role']=='Staff') echo 'selected'; ?>>Staff</option>
        <option value="Doctor" <?php if(isset($_GET['role']) && $_GET['role']=='Doctor') echo 'selected'; ?>>Doctor</option>
    </select>
    <select name="status">
        <option value="">All Status</option>
        <option value="Active" <?php if(isset($_GET['status']) && $_GET['status']=='Active') echo 'selected'; ?>>Active</option>
        <option value="Inactive" <?php if(isset($_GET['status']) && $_GET['status']=='Inactive') echo 'selected'; ?>>Inactive</option>
    </select>
    <button type="submit">Filter</button>
</form>

<table>
<tr>
<th>Name</th>
<th>Role</th>
<th>Email</th>
<th>Contact Number</th>
<th>Date Created</th>
<th>Time Created</th>
<th>Status</th>
<th>Actions</th>
</tr>
<?php while($row = mysqli_fetch_assoc($result)): 
    $date = date('Y-m-d', strtotime($row['date_created']));
    $time = date('H:i:s', strtotime($row['date_created']));
?>
<tr>
<td><?php echo htmlspecialchars($row['full_name']); ?></td>
<td><?php echo htmlspecialchars($row['role']); ?></td>
<td><?php echo htmlspecialchars($row['email']); ?></td>
<td><?php echo htmlspecialchars($row['contact_number']); ?></td>
<td><?php echo $date; ?></td>
<td><?php echo $time; ?></td>
<td><?php echo htmlspecialchars($row['status']); ?></td>
<td>
<a href="view_user.php?id=<?php echo $row['user_id']; ?>" class="btn view">View</a>
<a href="users_action.php?action=approve&id=<?php echo $row['user_id']; ?>" class="btn approve">Approve</a>
<a href="users.php?delete_id=<?php echo $row['user_id']; ?>" class="btn delete" onclick="return confirm('Are you sure?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>

</div>
</body>
</html>
