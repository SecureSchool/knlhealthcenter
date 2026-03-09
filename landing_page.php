<?php
session_start();
require_once "config.php";

// Fetch all services
$services_result = mysqli_query($link, "SELECT * FROM tblservices ORDER BY service_name ASC");
$services = [];
if(mysqli_num_rows($services_result) > 0){
    while($row = mysqli_fetch_assoc($services_result)){
        $services[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brgy Health Center</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Reset & base */
* {margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', sans-serif;}
body {line-height:1.6; color:#333; scroll-behavior: smooth; background:#f9f9f9;}

/* Navbar */
.navbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:15px 50px;
    background:#2575fc;
    color:white;
    position:sticky;
    top:0;
    z-index:100;
}
.navbar a {color:white; text-decoration:none; margin:0 15px; transition:0.3s; position: relative;}
.navbar a:hover {text-decoration:underline;}
.navbar .logo {font-weight:bold; font-size:24px;}

/* Dropdown */
.nav-links {display:flex; align-items:center; position: relative;}
.dropdown {position:relative;}
.dropdown-content {
    display:none;
    position:absolute;
    top:100%;
    left:0;
    background:#fff;
    min-width:200px;
    box-shadow:0 4px 12px rgba(0,0,0,0.2);
    border-radius:5px;
    z-index:200;
}
.dropdown-content a {
    color:#2575fc;
    padding:10px 15px;
    display:block;
    text-decoration:none;
}
.dropdown-content a:hover {background:#f1f1f1;}
.dropdown:hover .dropdown-content {display:block;}

/* Hero Section */
.hero {
    height:80vh;
    background:linear-gradient(rgba(37,117,252,0.7), rgba(37,117,252,0.7)), url('https://images.unsplash.com/photo-1588776814546-3a68c4db38c0?auto=format&fit=crop&w=1950&q=80') no-repeat center center/cover;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    color:white;
    text-align:center;
}
.hero h1 {font-size:48px; margin-bottom:20px;}
.hero p {font-size:20px; margin-bottom:30px; max-width:700px;}
.hero .btn {padding:12px 25px; background:#fff; color:#2575fc; border:none; border-radius:5px; font-weight:bold; cursor:pointer; transition:0.3s;}
.hero .btn:hover {background:#e1e1e1;}

/* Sections */
section {padding:80px 50px;}
section h2 {text-align:center; margin-bottom:50px; font-size:36px; color:#2575fc;}
section p {max-width:800px; margin:0 auto 20px; text-align:center; line-height:1.7;}

/* Services Grid Professional */
.services-container {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
    gap:30px;
}
.service-card {
    background:white;
    padding:40px 30px;
    border-radius:15px;
    text-align:center;
    box-shadow:0 10px 20px rgba(0,0,0,0.1);
    transition:transform 0.3s, box-shadow 0.3s;
    opacity:1;
}
.service-card:hover {
    transform: translateY(-10px);
    box-shadow:0 15px 25px rgba(0,0,0,0.15);
}
.service-card i {
    font-size:50px;
    color:#2575fc;
    margin-bottom:20px;
}
.service-card h3 {margin-bottom:15px; font-size:24px; color:#2575fc;}
.service-card p {font-size:16px; color:#555;}
.service-card.hidden {display:none;}

/* Login Buttons */
.login-buttons {text-align:center; margin-top:30px;}
.login-buttons a {display:inline-block; margin:10px; padding:12px 25px; background:#2575fc; color:white; text-decoration:none; border-radius:5px; transition:0.3s;}
.login-buttons a:hover {background:#1a5dc9;}

/* Footer */
footer {background:#2c3e50; color:white; text-align:center; padding:20px;}

/* Responsive */
@media(max-width:768px){
    .navbar {flex-direction:column; padding:15px 20px;}
    .hero h1 {font-size:36px;}
    .hero p {font-size:18px;}
    section {padding:60px 20px;}
    .services-container {grid-template-columns:1fr;}
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="logo">Brgy Health Center</div>
    <div class="nav-links">
        <a href="#home">Home</a>
        <div class="dropdown">
            <a href="#">Services <i class="fas fa-caret-down"></i></a>
            <div class="dropdown-content">
                <a href="#" onclick="showAllServices()">Show All Services</a>
                <?php foreach($services as $srv): ?>
                    <a href="#" onclick="showService(<?php echo $srv['service_id']; ?>)"><?php echo htmlspecialchars($srv['service_name']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="#about">About Us</a>
        <a href="#contact">Contact Us</a>
        <a href="#login">Login</a>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero" id="home">
    <h1>Welcome to Our Health Center</h1>
    <p>Your health and well-being are our top priority. Schedule appointments easily and stay informed.</p>
    <button class="btn" onclick="document.getElementById('services').scrollIntoView({behavior:'smooth'})">View Services</button>
</section>

<!-- Services -->
<section id="services">
    <h2>Our Services</h2>
    <div class="services-container">
        <?php if(!empty($services)): ?>
            <?php foreach($services as $service): ?>
                <div class="service-card" id="service-<?php echo $service['service_id']; ?>">
                    <i class="fas fa-stethoscope"></i>
                    <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
                    <p><?php echo htmlspecialchars($service['description']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center;">No services available.</p>
        <?php endif; ?>
    </div>
</section>

<!-- About Us -->
<section id="about">
    <h2>About Us</h2>
    <p>We are a community health center dedicated to providing quality healthcare services. Our mission is to promote health awareness, offer preventive care, and ensure the well-being of our residents through compassionate and professional medical services.</p>
</section>

<!-- Contact Us -->
<section id="contact">
    <h2>Contact Us</h2>
    <p>Reach out to us for inquiries or appointment scheduling. We are here to assist you!</p>
    <p style="text-align:center;">
        <strong>Address:</strong> 123 Barangay Health St., Your City<br>
        <strong>Email:</strong> info@brgyhealthcenter.com<br>
        <strong>Phone:</strong> +63 912 345 6789
    </p>
</section>

<!-- Login Section -->
<section id="login">
    <h2>Login</h2>
    <p>Select your account type to login:</p>
    <div class="login-buttons">
        <a href="login.php?type=patient"><i class="fas fa-user"></i> Patient Login</a>
        <a href="login.php?type=staff"><i class="fas fa-user-tie"></i> Staff Login</a>
        <a href="login.php?type=admin"><i class="fas fa-user-shield"></i> Admin Login</a>
    </div>
</section>

<!-- Footer -->
<footer>
    &copy; 2025 Brgy Health Center. All rights reserved.
</footer>

<script>
// Show only the selected service
function showService(id){
    const allServices = document.querySelectorAll('.service-card');
    allServices.forEach(s => s.classList.add('hidden'));
    const selected = document.getElementById('service-' + id);
    if(selected) selected.classList.remove('hidden');
    document.getElementById('services').scrollIntoView({behavior:'smooth'});
}

// Show all services
function showAllServices(){
    const allServices = document.querySelectorAll('.service-card');
    allServices.forEach(s => s.classList.remove('hidden'));
    document.getElementById('services').scrollIntoView({behavior:'smooth'});
}
</script>

</body>
</html>
