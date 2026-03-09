<?php
if(!isset($_SESSION)) { session_start(); }
require_once "config.php";

// Fetch staff info
$staff_data = mysqli_fetch_assoc(mysqli_query($link, 
    "SELECT full_name, profile_pic FROM tblstaff WHERE staff_id='{$_SESSION['staff_id']}'"
));

$staff_pic = !empty($staff_data['profile_pic']) ? $staff_data['profile_pic'] : 'default.png';
$staff_fullname = $staff_data['full_name'];
$staff_name = $_SESSION['staff_name'];
?>

<!-- HEADER WRAPPER -->
<div class="header-container shadow-sm mb-4 p-4 d-flex justify-content-between align-items-center"
     style="background: linear-gradient(90deg, #001BB7, #0033ff); border-radius: 14px; color: white;">

    <!-- LEFT SIDE: Welcome + Walk-in -->
    <div class="header-left">
        <h2 class="fw-bold mb-1 text-wrap text-center text-md-start">Welcome, <?= htmlspecialchars($staff_name) ?> 👋</h2>
        <p class="mb-3 text-center text-md-start" style="opacity: .9;">Have a productive day at KNL Health Center</p>

        <a href="walkin_register.php" class="btn btn-light fw-semibold"
            style="border-radius: 10px; padding: 8px 18px; color:#001BB7; box-shadow:0 3px 8px rgba(255,255,255,0.25); display:block; text-align:center;">
            <i class="fas fa-user-plus me-1"></i> Add Walk-in Patient
        </a>
    </div>

    <!-- RIGHT SIDE: Dark Mode + Profile + Clock -->
    <div class="header-right d-flex flex-column align-items-end">

        <!-- DARK MODE + PROFILE ROW -->
        <div class="dark-profile-row d-flex gap-2 mb-3">
            <!-- DARK MODE BUTTON -->
<button id="darkModeToggle" class="btn btn-light mb-3"
        style="width:45px; height:45px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 3px 10px rgba(0,0,0,0.15);">
    <i class="fas fa-moon"></i>
</button>

            <!-- PROFILE DROPDOWN -->
            <div class="dropdown">
                <button class="btn btn-light d-flex align-items-center gap-2"
                        type="button" id="staffDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        style="border-radius:50px; padding:6px 14px; box-shadow:0 3px 10px rgba(0,0,0,0.15);">
                    <img src="uploads/staff/<?= htmlspecialchars($staff_pic) ?>" alt="Profile" class="rounded-circle"
                         style="width:45px; height:45px; object-fit:cover; border:2px solid #001BB7;">
                    <span class="fw-semibold text-dark"><?= htmlspecialchars($staff_fullname) ?> ▾</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="staffDropdown">
                    <li><span class="dropdown-item-text fw-bold"><?= htmlspecialchars($staff_fullname); ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="staff_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="#" id="logoutBtnHeader"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>

        <!-- CLOCK -->
        <div id="currentDateTime"
             style="background: rgba(255,255,255,0.15); padding: 12px 18px; border-radius: 14px;
                    font-weight: 600; color: #fff; min-width:200px; text-align:center;
                    backdrop-filter: blur(5px); box-shadow:0 4px 12px rgba(0,0,0,0.15);">
        </div>

    </div>
</div>

<style>
/* =====================
   RESPONSIVE STYLES
   ===================== */
@media (max-width: 767.98px) {

    .header-container {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }

    .header-left {
        width: 100%;
        text-align: center;
    }

    .header-left h2, .header-left p {
        text-align: center !important;
    }

    .header-left a.btn {
        width: 100%;
        justify-content: center;
        margin-top: 10px;
    }

    /* Dark mode + profile row stacked */
    .header-right {
        width: 100%;
        align-items: center;
        gap: 10px;
    }

    .dark-profile-row {
        flex-direction: row;
        justify-content: center;
        width: 100%;
    }

    #currentDateTime {
        width: 100%;
        text-align: center;
    }
}
</style>

<script>
    // Logout
    document.getElementById('logoutBtnHeader').addEventListener('click', function(e){
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
            if(result.isConfirmed){
                window.location.href = 'logout.php';
            }
        });
    });

    // Clock
    function updateDateTime() {
        const now = new Date();
        const options = { weekday:'long', year:'numeric', month:'long', day:'numeric',
                          hour:'2-digit', minute:'2-digit', second:'2-digit' };
        document.getElementById('currentDateTime').textContent =
            now.toLocaleDateString('en-US', options);
    }
    setInterval(updateDateTime, 1000);
    updateDateTime();

    // Dark Mode
    const darkModeToggle = document.getElementById("darkModeToggle");
    function setDarkMode(isDark) {
        if(isDark){
            document.body.classList.add("dark-mode");
            darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        } else {
            document.body.classList.remove("dark-mode");
            darkModeToggle.innerHTML = '<i class="fas fa-moon"></i>';
        }
    }
    const savedMode = localStorage.getItem("darkMode") === "true";
    setDarkMode(savedMode);
    darkModeToggle.addEventListener("click", ()=>{
        const isDark = !document.body.classList.contains("dark-mode");
        localStorage.setItem("darkMode", isDark);
        setDarkMode(isDark);
    });
</script>