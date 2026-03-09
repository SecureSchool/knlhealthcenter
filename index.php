<?php
require_once "config.php"; // DB connection
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Brgy Krus na Ligas Health Center</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
body,html{height:100%; scroll-behavior:smooth; background:#fdfdfd; color:#333;}

/* Navbar */
nav{
  width:100%; background:#001BB7; position:fixed; top:0; left:0; z-index:1000;
  display:flex; justify-content:space-between; align-items:center;
  padding:15px 40px; box-shadow:0 4px 10px rgba(0,0,0,0.15);
}
nav .logo{display:flex; align-items:center; gap:10px; color:white; font-size:22px; font-weight:700;}
nav .logo img{width:40px; height:40px; border-radius:50%; background:white; padding:3px;}
nav ul{list-style:none; display:flex; gap:25px; align-items:center;}
nav ul li a{color:white; text-decoration:none; font-weight:500; transition:0.3s;}
nav ul li a:hover{color:#FFD43B;}

/* Dropdown */
nav ul li{position:relative;}
nav ul li ul{
  position:absolute; top:100%; right:0; background:#fff; min-width:220px;
  display:none; flex-direction:column; border-radius:6px;
  box-shadow:0 4px 10px rgba(0,0,0,0.15); animation:fadeIn 0.3s ease-in-out;
}
nav ul li:hover ul{display:flex;}
nav ul li ul li a{color:#001BB7; padding:12px 16px;}
nav ul li ul li a:hover{background:#f0f3ff;}

@keyframes fadeIn{from{opacity:0; transform:translateY(10px);} to{opacity:1; transform:translateY(0);}}

/* Hero */
.hero{
  height:100vh;
  background:linear-gradient(rgba(0,27,183,0.8),rgba(0,27,183,0.8)),url('landing_bg.jpg') center/cover no-repeat;
  display:flex; flex-direction:column; justify-content:center; align-items:center;
  color:white; text-align:center; padding:20px;
}
.hero img.logo-main{width:130px; height:130px; margin-bottom:20px; border-radius:50%; border:5px solid white; box-shadow:0 0 20px rgba(0,0,0,0.3);}
.hero h1{font-size:52px; font-weight:700; margin-bottom:20px; animation:fadeIn 1s ease;}
.hero p{font-size:18px; max-width:650px; margin:0 auto 35px auto; line-height:1.7;}
.hero .buttons{display:flex; gap:20px; flex-wrap:wrap; justify-content:center;}
.hero .btn{
  padding:14px 34px; border-radius:30px; text-decoration:none; font-size:16px; font-weight:600;
  transition:all 0.3s ease; box-shadow:0 4px 12px rgba(0,0,0,0.2);
}
.hero .btn-login{background:white; color:#001BB7; border:2px solid white;}
.hero .btn-login:hover{background:#FFD43B; color:#001BB7; transform:translateY(-3px);}
.hero .btn-register{background:#FFD43B; color:#001BB7; border:2px solid #FFD43B;}
.hero .btn-register:hover{background:white; color:#001BB7; transform:translateY(-3px);}

/* About */
.about{padding:120px 40px; background:#fff; display:flex; flex-wrap:wrap; justify-content:center; align-items:center; gap:40px;}
.about img{width:400px; border-radius:15px; box-shadow:0 6px 16px rgba(0,0,0,0.15);}
.about .text{max-width:650px;}
.about h2{font-size:36px; color:#001BB7; margin-bottom:20px;}
.about p{font-size:17px; line-height:1.9; color:#444; text-align:justify;}

/* Services */
.services-section{padding:100px 20px; background:#f9f9f9; text-align:center;}
.services-section h2{font-size:34px; color:#001BB7; margin-bottom:20px;}
.services-section p{max-width:800px; margin:0 auto 30px auto; font-size:16px; line-height:1.7; color:#555;}
.service-slider {
  display: flex;
  gap: 15px;
  justify-content: center;
  flex-wrap: wrap;
}

.service-slider img {
  width: 400px;       /* fixed width */
  height: 300px;      /* fixed height */
  object-fit: cover;  /* crop without stretching */
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  transition: transform 0.3s;
}

.service-slider img:hover {
  transform: scale(1.03);
}

@media (max-width: 576px) {
  .service-slider img {
    width: 100%;
    height: 200px;   /* taller on mobile for readability */
    margin-bottom: 10px;
  }
}


/* Announcements */
.announcements{padding:100px 20px; background:#fff; text-align:center;}
.announcements h2{font-size:34px; color:#001BB7; margin-bottom:40px;}
.announcement-item{max-width:800px; margin:0 auto 20px auto; padding:25px; border-radius:12px; background:#f9f9f9;
  text-align:left; box-shadow:0 4px 12px rgba(0,0,0,0.1); transition:transform 0.3s;}
.announcement-item:hover{transform:translateY(-5px);}
.announcement-item h3{font-size:22px; color:#001BB7; margin-bottom:10px;}
.announcement-item p{font-size:16px; color:#555; margin-bottom:8px;}
.announcement-item small{font-size:13px; color:#888;}

/* Contact */
#contact{padding:100px 20px; background:#eef2ff; text-align:center;}
#contact h2{color:#001BB7; margin-bottom:20px; font-size:30px;}
#contact form{max-width:600px; margin:20px auto; text-align:left;}
#contact input,#contact textarea{width:100%; padding:14px; margin:10px 0; border-radius:8px; border:1px solid #ccc;}
#contact button{padding:12px 30px; background:#001BB7; color:white; border:none; border-radius:25px; font-weight:600; cursor:pointer; transition:0.3s;}
#contact button:hover{background:#FFD43B; color:#001BB7;}

/* Footer */
footer{background:#001BB7; color:white; padding:20px; text-align:center; font-size:14px; margin-top:40px;}

/* Modal */
#announcementModal{display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6);
  justify-content:center; align-items:center; z-index:5000;}
#announcementModal .modal-content{background:#fff; padding:25px; border-radius:10px; max-width:600px; width:90%; position:relative;}
#announcementModal .close{position:absolute; top:10px; right:15px; cursor:pointer; font-size:20px;}
/* FAQ */
.faq{padding:100px 20px; background:#f9f9f9; text-align:center;}
.faq h2{color:#001BB7; margin-bottom:30px; font-size:34px;}
.faq-item{max-width:800px; margin:10px auto; text-align:left;}
.faq-question{
  background:#eef2ff; padding:15px 20px; cursor:pointer;
  border-radius:8px; font-weight:600; color:#001BB7;
  transition:background 0.3s;
}
.faq-question:hover{background:#dbe2ff;}
.faq-answer{
  display:none; padding:15px 20px; border-left:3px solid #001BB7;
  background:#fff; border-radius:0 0 8px 8px; line-height:1.6;
}
nav ul li.register-btn > a {
  background: #FFD43B;
  color: #001BB7 !important;
  padding: 10px 18px;
  border-radius: 25px;
  font-weight: 600;
  transition: 0.3s;
}
nav ul li.register-btn > a:hover {
  background: white;
  color: #001BB7 !important;
}
/* ----------------- Responsive ----------------- */
@media (max-width: 1024px) {
  .about {flex-direction: column; text-align: center;}
  .about img {width: 90%;}
  .about .text {max-width: 90%;}
}

@media (max-width: 768px) {
  nav {padding: 12px 20px;}
  nav ul {gap: 15px;}
  .hero h1 {font-size: 36px;}
  .hero p {font-size: 16px;}
  .about h2 {font-size: 28px;}
  .services-section h2,
  .announcements h2,
  .faq h2,
  #contact h2,
  #map h2 {font-size: 26px;}
}

@media (max-width: 600px) {
  nav {flex-wrap: wrap; justify-content: space-between;}
  nav .logo {font-size: 18px;}
  nav ul {flex-direction: column; width: 100%; background:#001BB7; margin-top:10px; display:none;}
  nav ul.show {display:flex;}
  nav ul li {text-align: center; width: 100%;}
  nav ul li ul {position: static; box-shadow: none; width: 100%;}
  nav ul li ul li a {padding:10px; text-align:center;}

  /* Make services slider scrollable on small screens */
  .service-slider {overflow-x: auto; gap: 10px;}
  .service-slider img {width: 180px;}

  .hero h1 {font-size: 28px;}
  .hero p {font-size: 15px;}
  .hero .btn {padding:12px 24px; font-size:14px;}

  .about img {width:100%;}
  .about p {font-size: 15px;}

  .announcement-item {padding:15px;}
  .faq-question, .faq-answer {font-size: 15px;}

  #contact input, #contact textarea {font-size:14px;}
  #contact button {width:100%;}
}
.menu-toggle {
  display: none;
  font-size: 26px;
  color: white;
  cursor: pointer;
}
@media (max-width: 600px) {
  .menu-toggle {display: block;}
}
/* User Guide Cards */
.user-guide-container {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 25px;
  max-width: 1000px;
  margin: 0 auto;
}

.user-guide-card {
  background: #fff;
  border-radius: 15px;
  padding: 20px 25px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.1);
  flex: 1 1 400px;
  position: relative;
  transition: transform 0.3s;
}

.user-guide-card:hover {
  transform: translateY(-5px);
}

.user-guide-card h3 {
  color: #001BB7;
  margin-bottom: 12px;
  font-size: 20px;
}

.user-guide-card p {
  color: #444;
  font-size: 15px;
  line-height: 1.6;
}

.user-guide-card::before {
  content: counter(step);
  counter-increment: step;
  position: absolute;
  top: 20px;
  left: -15px;
  background: #FFD43B;
  color: #001BB7;
  font-weight: 700;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

/* Reset counter */
.user-guide-container {
  counter-reset: step;
}

/* Responsive */
@media (max-width: 768px){
  .user-guide-card {flex: 1 1 90%;}
}
/* Appointment Steps Card */
.appointment-steps {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin-top: 10px;
}

.appointment-step {
  display: flex;
  align-items: flex-start;
  gap: 15px;
  background: #fff;
  padding: 15px 20px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  transition: transform 0.3s;
}

.appointment-step:hover {
  transform: translateY(-3px);
}

.step-number {
  min-width: 35px;
  min-height: 35px;
  background: #FFD43B;
  color: #001BB7;
  font-weight: 700;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}

.step-icon {
  width: 25px;
  height: 25px;
  margin-right: 10px;
}

.step-text {
  flex: 1;
  font-size: 14px;
  color: #334155;
  line-height: 1.6;
}

</style>
</head>
<body>

<!-- Navbar -->
<nav>
  <div class="logo">
  <a href="#home" style="display:flex; align-items:center; gap:10px; color:white; text-decoration:none;">
    <img src="logo.png" alt="Logo">
    KNL Health Center
  </a>
</div>

  <ul>
    <li><a href="#home">Home</a></li>
    <li><a href="#about">About Us</a></li>
    <li>
      <a href="#services">Services ▾</a>
      <ul>
        <?php
        $sql_services = "SELECT service_name FROM tblservices";
        $res_services = mysqli_query($link, $sql_services);
        while($srv = mysqli_fetch_assoc($res_services)){
            $id = strtolower(str_replace(' ', '-', $srv['service_name']));
            echo '<li><a href="#'.$id.'">'.htmlspecialchars($srv['service_name']).'</a></li>';
        }
        ?>
      </ul>
    </li>
    <li><a href="#announcements">Announcements</a></li>
    <li><a href="#faq">FAQ</a></li>
    <li><a href="#map">Find Us</a></li>
    <li><a href="#contact">Contact</a></li>
    <li><a href="#user-manual">User Guide</a></li>

    <li class="register-btn">
      <a href="#">Register ▾</a>
      <ul>
        <li><a href="register_resident.php">Resident</a></li>
        <li><a href="register_nonresident.php">Non-Resident</a></li>
      </ul>
    </li>
  </ul>
  <div class="menu-toggle" onclick="document.querySelector('nav ul').classList.toggle('show')">☰</div>
</nav>


<!-- Hero -->
<section class="hero" id="home">
  <img src="logo.png" alt="Health Center Logo" class="logo-main">
  <h1>Welcome to Brgy Krus na Ligas Health Center</h1>
  <p>Your trusted partner in providing quality healthcare services for residents and non-residents alike.</p>
  <div class="buttons">
    <a href="login.php" class="btn btn-login">Login</a>
  </div>
</section>

<!-- About -->
<section class="about" id="about">
  <img src="about.jpg" alt="Health Center">
  <div class="text">
    <h2>About Us</h2>
    <p>
      The Brgy. Krus na Ligas Health Center is dedicated to delivering accessible, affordable, and quality healthcare services for every member of our community. As the primary health facility of the barangay, our mission is to safeguard the well-being of residents through preventive programs, immediate care, and continuous health education.

      Our team is composed of skilled doctors, nurses, midwives, and support staff who serve with compassion, integrity, and professionalism. From maternal and child health to immunizations, consultations, and emergency response, we strive to be a trusted partner in every stage of life.

      Beyond medical services, the Health Center actively engages in health promotion activities such as community seminars, wellness programs, and sustainable initiatives aimed at creating a healthier environment for all.

      We believe that healthcare is not only about treatment but also about building awareness, empowering families, and fostering a supportive community. Together with our residents, we aim to create a safe, healthy, and thriving Barangay Krus na Ligas.
    </p>
  </div>
</section>

<!-- Services -->
<?php
$sql_services_all = "SELECT * FROM tblservices";
$res_services_all = mysqli_query($link, $sql_services_all);
while($service = mysqli_fetch_assoc($res_services_all)){
    $id = strtolower(str_replace(' ', '-', $service['service_name']));
    ?>
    <section class="services-section" id="<?php echo $id; ?>">
        <h2><?php echo htmlspecialchars($service['service_name']); ?></h2>
        <p><?php echo htmlspecialchars($service['description']); ?></p>
        <div class="service-slider">
          <?php if(!empty($service['image1'])): ?>
              <img src="<?php echo htmlspecialchars($service['image1']); ?>" alt="">
          <?php endif; ?>
          <?php if(!empty($service['image2'])): ?>
              <img src="<?php echo htmlspecialchars($service['image2']); ?>" alt="">
          <?php endif; ?>
        </div>
    </section>
<?php } ?>

<!-- Search -->
<div style="text-align:center; margin:30px 0;">
  <input type="text" id="searchBox" placeholder="Search announcements or services..."
         style="padding:12px; width:300px; border:1px solid #ccc; border-radius:25px;">
</div>

<!-- Announcements -->
<section class="announcements" id="announcements">
  <h2>Latest Announcements</h2>
  <div class="row justify-content-center">
    <?php
    $sql_ann = "SELECT * FROM tblannouncements ORDER BY created_at DESC LIMIT 6";
    $result_ann = mysqli_query($link, $sql_ann);

    if ($result_ann && mysqli_num_rows($result_ann) > 0) {
      while ($ann = mysqli_fetch_assoc($result_ann)) {
        ?>
        <div class="announcement-item">
          <h3><?php echo htmlspecialchars($ann['title']); ?></h3>
          <p><?php echo nl2br(htmlspecialchars(substr($ann['content'],0,150))); ?>...</p>
          <small><?php echo date("F j, Y g:i A", strtotime($ann['created_at'])); ?></small><br><br>
          <a href="javascript:void(0);" onclick="openModal(
            '<?php echo htmlspecialchars($ann['title']); ?>',
            `<?php echo nl2br(htmlspecialchars($ann['content'])); ?>`,
            '<?php echo date("F j, Y g:i A", strtotime($ann['created_at'])); ?>'
          )" class="btn btn-sm btn-outline-primary rounded-pill">Read More</a>
        </div>
        <?php
      }
    } else {
      echo "<p>No announcements available at the moment.</p>";
    }
    ?>
  </div>
</section>

<!-- Modal -->
<div id="announcementModal">
  <div class="modal-content">
    <span class="close" onclick="document.getElementById('announcementModal').style.display='none'">&times;</span>
    <h3 id="modalTitle"></h3>
    <p id="modalContent"></p>
    <small id="modalDate"></small>
  </div>
</div>

<!-- Contact -->
<section id="contact">
  <h2>Contact Us</h2>
  <form method="post" action="contact_process.php">
    <input type="text" name="name" placeholder="Your Name" required>
    <input type="email" name="email" placeholder="Your Email" required>
    <textarea name="message" placeholder="Your Message" required></textarea>
    <button type="submit">Send Message</button>
  </form>
</section>


<section id="user-manual" style="padding:100px 20px; background:#eef2ff; text-align:center;">
  <h2 style="color:#001BB7; margin-bottom:30px; font-size:34px;">User Guide</h2>

  <div class="user-guide-container">
    <!-- Existing Cards -->
    <div class="user-guide-card">
      <h3>Login</h3>
      <p>Residents and Non-Residents can log in using their registered accounts. Click the <strong>Login</strong> button on the home page.</p>
      <p><strong>Important:</strong> Select the appropriate <strong>User Role</strong> (Patient) to access the correct dashboard.</p>
    </div>

    <div class="user-guide-card">
      <h3>Registration</h3>
      <p>Click <strong>Register</strong> in the navbar. Choose either <em>Resident</em> or <em>Non-Resident</em> and fill out the form with the required details.</p>
    </div>

    <div class="user-guide-card">
      <h3>Viewing Services</h3>
      <p>Scroll to the <strong>Services</strong> section to see all available health services. Click on any service to view its description and images.</p>
    </div>

    <div class="user-guide-card">
      <h3>Announcements</h3>
      <p>Latest news and updates are in the <strong>Announcements</strong> section. Click <strong>Read More</strong> to view full content.</p>
    </div>

    <div class="user-guide-card">
      <h3>FAQ</h3>
      <p>Find common questions and answers in the <strong>FAQ</strong> section. Click a question to reveal the answer.</p>
    </div>

    <div class="user-guide-card">
      <h3>Contact</h3>
      <p>Fill out the form in the <strong>Contact</strong> section. Enter your name, email, and message, then click <strong>Send Message</strong>.</p>
    </div>

    <div class="user-guide-card">
      <h3>Finding Us</h3>
      <p>Use the <strong>Find Us</strong> section to locate the Health Center on Google Maps.</p>
    </div>

    <div class="user-guide-card">
      <h3>Searching</h3>
      <p>Use the search bar above the Announcements section to quickly find specific services or announcements.</p>
    </div>

    

   <div class="user-guide-card">
  <h3>How to Book an Appointment?</h3>
  <div class="appointment-steps">

    <div class="appointment-step">
      <div class="step-number">1</div>
      <div class="step-text">
        After logging in, go to the <b>"Request Appointment"</b> page from the dashboard or menu.
      </div>
    </div>

    <div class="appointment-step">
      <div class="step-number">2</div>
      <div class="step-text">
        Select the <b>service</b> you want from the dropdown menu.
      </div>
    </div>

    <div class="appointment-step">
      <div class="step-number">3</div>
      <div class="step-text">
        Choose the <b>date</b> for your appointment.
      </div>
    </div>

    <div class="appointment-step">
      <div class="step-number">4</div>
      <div class="step-text">
        Click on an <b>available time slot</b> (green buttons) to select your preferred time.
      </div>
    </div>


    <div class="appointment-step">
      <div class="step-number">6</div>
      <div class="step-text">
        Click <b>"Request Appointment"</b> and confirm your booking in the pop-up window.
      </div>
    </div>

    <div class="appointment-step">
      <div class="step-number">7</div>
      <div class="step-text">
        After successful booking, your <b>queue number</b> will be displayed, and you will receive an email confirmation.
      </div>
    </div>

    <div class="appointment-step">
      <div class="step-number">8</div>
      <div class="step-text">
        Please arrive 10 minutes before your scheduled time.
      </div>
    </div>

  </div>
</div>



  </div>
</section>

<!-- FAQ -->
<section class="faq" id="faq">
  <h2>Frequently Asked Questions</h2>

  <div class="faq-item">
    <div class="faq-question">What are your operating hours?</div>
    <div class="faq-answer">Our health center is open Monday to Saturday, 6:00 AM – 5:00 PM.</div>
  </div>

  <div class="faq-item">
    <div class="faq-question">Do you accept walk-in patients?</div>
    <div class="faq-answer">Yes, we accept walk-ins, but scheduled patients are prioritized.</div>
  </div>

  <div class="faq-item">
    <div class="faq-question">Are services and medicines free?</div>
    <div class="faq-answer">Yes, our services are free for all residents. Medicines are also free if available.</div>
  </div>

  <div class="faq-item">
    <div class="faq-question">How can I schedule an appointment?</div>
    <div class="faq-answer">You can schedule an appointment through our registration system.</div>
  </div>
</section>

<!-- Google Map -->
<section id="map" style="padding:80px 20px; background:#fff; text-align:center;">
  <h2 style="color:#001BB7; margin-bottom:20px; font-size:30px;">Find Us</h2>
  <p style="margin-bottom:30px; color:#555;">Brgy. Krus na Ligas Health Center, Quezon City</p>
  <div style="max-width:1000px; margin:0 auto; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
    <iframe 
      src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3859.9222990272765!2d121.06654837487308!3d14.65176558583756!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b7bbf3e6f0f7%3A0x2dcf1d94d2c7a0d5!2sBarangay%20Krus%20Na%20Ligas!5e0!3m2!1sen!2sph!4v1695281650000!5m2!1sen!2sph" 
      width="100%" 
      height="450" 
      style="border:0;" 
      allowfullscreen="" 
      loading="lazy" 
      referrerpolicy="no-referrer-when-downgrade">
    </iframe>
  </div>
</section>

<!-- Footer -->
<footer>
  &copy; <?php echo date("Y"); ?> Brgy Krus na Ligas Health Center. All Rights Reserved.
</footer>

<script>
// Search filter
document.getElementById("searchBox").addEventListener("keyup", function(){
  let filter = this.value.toLowerCase();
  document.querySelectorAll(".announcement-item, .services-section").forEach(function(item){
    let text = item.textContent.toLowerCase();
    item.style.display = text.includes(filter) ? "block" : "none";
  });
});

// FAQ accordion
document.querySelectorAll(".faq-question").forEach(q=>{
  q.addEventListener("click", ()=>{
    let ans = q.nextElementSibling;
    ans.style.display = (ans.style.display==="block") ? "none" : "block";
  });
});

// Modal
function openModal(title, content, date){
  document.getElementById('modalTitle').innerText = title;
  document.getElementById('modalContent').innerText = content;
  document.getElementById('modalDate').innerText = date;
  document.getElementById('announcementModal').style.display = 'flex';
}
</script>

</body>
</html>
