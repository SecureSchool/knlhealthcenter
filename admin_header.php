<?php
if(!isset($_SESSION)) { session_start(); }
require_once "config.php";

// Fetch admin info with correct column name
$admin_data = mysqli_fetch_assoc(mysqli_query($link, 
    "SELECT full_name, profile_picture FROM tbladmin WHERE admin_id='{$_SESSION['admin_id']}'"
));

$admin_fullname = $admin_data['full_name'];

// If no profile picture, use default admin avatar
$admin_pic = !empty($admin_data['profile_picture']) 
            ? $admin_data['profile_picture'] 
            : 'default_admin.png';

$admin_name = $_SESSION['admin_name'];
?>

<!-- ADMIN HEADER -->
<div class="header-container shadow-sm mb-4 p-3 d-flex flex-column flex-md-row justify-content-between align-items-center"
     style="background: linear-gradient(90deg, #001BB7, #0033ff); border-radius: 14px; color: white;">

    <!-- LEFT SECTION -->
    <div class="text-center text-md-start mb-3 mb-md-0">
        <h3 class="fw-bold mb-1" style="font-size: 33px;">Welcome, <?= htmlspecialchars($admin_name) ?> 👋</h3>
        <p class="mb-2 mb-md-3 fs-7 fs-md-6" style="opacity:0.9; font-size: 15px; color: white;">Managing KNL Health Center System</p>

        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center justify-content-md-start">
            <a href="patients.php" class="btn btn-light btn-sm fw-semibold"
               style="border-radius: 8px; padding: 6px 14px; color:#001BB7; box-shadow:0 3px 6px rgba(255,255,255,0.25);">
                <i class="fas fa-users me-1"></i> Review Patients
            </a>

            <button id="sendReminderBtn" class="btn btn-warning btn-sm fw-semibold"
                    style="border-radius: 50px; padding: 6px 14px; color: white; display:flex; align-items:center; justify-content-center;">
                <i class="fas fa-envelope me-1"></i> Send Reminders
            </button>
        </div>
    </div>

    <!-- RIGHT SECTION -->
    <div class="d-flex flex-row flex-md-column align-items-center align-items-md-end gap-2">
        <!-- DARK MODE BUTTON -->
        <button id="darkModeToggle" class="btn btn-light btn-sm"
        style="border-radius:50px; padding:5px 10px; box-shadow:0 3px 6px rgba(0,0,0,0.15);">
    <i class="fas fa-moon"></i>
</button>

        <!-- PROFILE DROPDOWN -->
        <div class="dropdown">
            <button class="btn btn-light d-flex align-items-center gap-2 btn-sm"
                    type="button" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                    style="border-radius:50px; padding:5px 10px; box-shadow:0 3px 6px rgba(0,0,0,0.15);">

                <img src="/CS310-CS3B-DELACONCEPCION/database1/uploads/<?= htmlspecialchars($admin_pic) ?>"
                     alt="Profile"
                     class="rounded-circle"
                     style="width:35px; height:35px; object-fit:cover; border:2px solid #001BB7;">

                <span class="fw-semibold text-dark d-none d-md-inline">
                    <?= htmlspecialchars($admin_fullname) ?> ▾
                </span>
            </button>

            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                <li><span class="dropdown-item-text fw-bold"><?= htmlspecialchars($admin_fullname); ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a class="dropdown-item" href="#" id="logoutBtnAdmin"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <!-- CLOCK -->
        <div id="currentDateTimeHeader"
             class="mt-2 px-2 py-1 text-center fw-semibold fs-7"
             style="background: rgba(255,255,255,0.15); border-radius: 12px; color: #fff; min-width:160px; backdrop-filter: blur(5px); box-shadow:0 3px 6px rgba(0,0,0,0.15);">
        </div>
    </div>
</div>

<script>
    // Logout Confirmation
    document.getElementById('logoutBtnAdmin').addEventListener('click', function(e){
        e.preventDefault();
        Swal.fire({
            title: 'Are you sure?',
            text: "You will be logged out.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#001BB7',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
    });

    // Clock
    function updateDateTimeHeader() {
        const now = new Date();
        const options = {
            weekday:'long', year:'numeric', month:'long', day:'numeric',
            hour:'2-digit', minute:'2-digit', second:'2-digit'
        };
        document.getElementById('currentDateTimeHeader').textContent =
            now.toLocaleDateString('en-US', options);
    }
    setInterval(updateDateTimeHeader, 1000);
    updateDateTimeHeader();


    // DARK MODE FEATURE
    const darkModeToggle = document.getElementById("darkModeToggle");

    function setDarkMode(isDark) {
        if (isDark) {
            document.body.classList.add("dark-mode");
            darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        } else {
            document.body.classList.remove("dark-mode");
            darkModeToggle.innerHTML = '<i class="fas fa-moon"></i>';
        }
    }

    // Load saved mode from localStorage
    const savedMode = localStorage.getItem("darkMode") === "true";
    setDarkMode(savedMode);

    // Toggle button click
    darkModeToggle.addEventListener("click", () => {
        const isDark = !document.body.classList.contains("dark-mode");
        localStorage.setItem("darkMode", isDark);
        setDarkMode(isDark);
    });

    document.getElementById('sendReminderBtn').addEventListener('click', function(e){
    e.preventDefault();
    Swal.fire({
        title: 'Send Reminders?',
        text: "Are you sure you want to send appointment reminders for tomorrow?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#001BB7',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, send them!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            // Redirect to send_reminders.php
            window.location.href = 'sent_reminder.php';
        }
    });
});

</script>