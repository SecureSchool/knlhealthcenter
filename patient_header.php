<?php
if (!isset($_SESSION)) { session_start(); }
require_once "config.php";

// Fetch patient info
$patient_id = $_SESSION['patient_id'] ?? null;

$patientQuery = mysqli_query($link, 
    "SELECT full_name, profile_picture, residency_type, priority 
     FROM tblpatients 
     WHERE patient_id='$patient_id'"
);

$patient = mysqli_fetch_assoc($patientQuery);

$patient_name    = $patient['full_name'] ?? "Patient";
$patient_pic     = !empty($patient['profile_picture']) ? $patient['profile_picture'] : "default-avatar.png";
$residency_type  = $patient['residency_type'] ?? "Resident";
$priority        = $patient['priority'] ?? 0;

// Determine current page
$current_page = basename($_SERVER['PHP_SELF']);

// ======== PRIORITY LOGIC ========
$priority_label = "";
$priority_icon  = "";
$priority_color = "";

if ($residency_type === "Resident") {
    if ($priority == 1) {
        $priority_label = "PWD / Senior / Pregnant";
        $priority_icon  = "fa-wheelchair";
        $priority_color = "rgba(220,53,69,0.99)"; // yellow
    } elseif ($priority == 2) {
        $priority_label = "Regular Resident";
        $priority_icon  = "fa-user";
        $priority_color = "rgba(40,167,69,0.9)"; // green
    }
} else { // Non-Resident
    if ($priority == 1) {
        $priority_label = "PWD / Senior / Pregnant";
        $priority_icon  = "fa-wheelchair";
        $priority_color = "rgba(220,53,69,0.9)"; // yellow
    } elseif ($priority == 0) {
        $priority_label = "Not Priority";
        $priority_icon  = "fa-user-times";
        $priority_color = "rgba(108,117,125,0.9)"; // red
    }
}
?>

<!-- RESPONSIVE PATIENT HEADER -->
<div class="header-container shadow-sm mb-4 p-4 d-flex justify-content-between align-items-center"
     style="background: linear-gradient(90deg, #001BB7, #0033ff); border-radius: 14px; color: white;">

    <!-- LEFT SIDE -->
    <div class="header-left">
        <h2 class="fw-bold mb-1">Welcome, <?= htmlspecialchars($patient_name) ?> 👋</h2>
        <p class="mb-2" style="opacity:.9;">Hope you're having a healthy day!</p>

        <!-- RESIDENCY + PRIORITY BADGES -->
        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
            <span class="badge"
                  style="background: rgba(255,255,255,0.25); color:#fff; padding:6px 14px; border-radius:20px; font-size:13px; font-weight:600;">
                <i class="fas fa-home me-1"></i>
                <?= htmlspecialchars($residency_type) ?>
            </span>

            <?php if(!empty($priority_label)): ?>
            <span class="badge"
                  style="background: <?= $priority_color ?>; color:#000; padding:6px 14px; border-radius:20px; font-size:13px; font-weight:700;">
                <i class="fas <?= $priority_icon ?> me-1"></i>
                <?= $priority_label ?>
            </span>
            <?php endif; ?>
        </div>

        <!-- REQUEST APPOINTMENT BUTTON -->
        <a href="patient_request.php" class="btn btn-light fw-semibold request-btn"
           style="border-radius:10px; padding:8px 18px; color:#001BB7; box-shadow:0 3px 8px rgba(255,255,255,0.25);">
            <i class="fas fa-calendar-plus me-1"></i> Request Appointment
        </a>
    </div>

    <!-- RIGHT SIDE -->
    <div class="header-right d-flex flex-column align-items-end">

        <!-- CONTROLS -->
        <div class="right-controls d-flex flex-column align-items-end gap-3">

            <!-- NOTIFICATION + DARK MODE + PROFILE -->
            <div class="notif-profile-container d-flex align-items-center gap-3">

                <!-- DARK MODE BUTTON -->
                <button id="darkModeToggle" class="btn btn-light"
                        style="border-radius:50px; padding:6px 14px; box-shadow:0 3px 10px rgba(0,0,0,0.15);">
                    <i class="fas fa-moon"></i>
                </button>

                <?php if($current_page === 'patient_dashboard.php'): ?>
                <div class="notif-container position-relative">
                    <div id="notifBell" style="cursor:pointer;">
                        <i class="fas fa-bell" style="font-size:22px;"></i>
                        <span id="notifCount"
                              style="display:none; position:absolute; top:-6px; right:-10px;
                                     background:red; color:white; padding:2px 6px;
                                     border-radius:50%; font-size:12px;">
                        </span>
                    </div>

                    <div id="notifDropdownMenu" class="notif-dropdown"
                         style="right:0; top:35px; width:320px; border-radius:12px;">
                        <div class="notif-header">Notifications</div>
                        <div id="notifList"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- PROFILE DROPDOWN -->
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2"
                            data-bs-toggle="dropdown"
                            style="border-radius:50px; padding:6px 14px; box-shadow:0 3px 10px rgba(0,0,0,0.15);">
                        <img src="uploads/<?= htmlspecialchars($patient_pic) ?>" class="rounded-circle"
                             style="width:45px; height:45px; object-fit:cover; border:2px solid #001BB7;">
                        <span class="fw-semibold text-dark d-none d-md-inline"><?= htmlspecialchars($patient_name) ?> </span>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text fw-bold"><?= htmlspecialchars($patient_name) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="patient_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="#" id="logoutBtnHeader"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>

            </div>
        </div>

        <!-- CLOCK -->
        <div id="patientHeaderClock"
             style="background: rgba(255,255,255,0.15); padding:12px 18px; border-radius:14px;
                    font-weight:600; color:#fff; min-width:240px; text-align:center;
                    backdrop-filter: blur(5px); box-shadow:0 4px 12px rgba(0,0,0,0.15); margin-top: 10px;">
        </div>
    </div>
</div>

<style>
/* MOBILE/TABLET ADJUSTMENTS ONLY */
@media (max-width: 767.98px) {

    .header-container {
        flex-direction: column;
        align-items: stretch;
    }

    .header-left {
        width: 100%;
        text-align: center;
        margin-bottom: 15px;
    }

    .header-left .d-flex.align-items-center {
    justify-content: center;
    flex-wrap: wrap;
    }
    .request-btn {
        width: 100%;
        justify-content: center;
        margin-top: 10px;
    }

    /* Move dark mode button next to notification bell on mobile */
    .notif-profile-container {
        flex-direction: row !important;
        justify-content: center;
        align-items: center;
        gap: 10px;
    }

    .notif-profile-container > #darkModeToggle {
        order: -1; /* move dark mode button before notification bell */
    }

    #patientHeaderClock {
        margin-top: 10px;
        width: 100%;
        text-align: center;
    }
}

</style>

<script>
/* LOGOUT CONFIRMATION */
document.getElementById('logoutBtnHeader').addEventListener('click', function(e){
    e.preventDefault();
    Swal.fire({
        title: 'Logout?',
        text: "You will be logged out.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#001BB7',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Logout'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
});

/* CLOCK */
function updatePatientClock(){
    const now = new Date();
    const options = {
        weekday:'long', year:'numeric', month:'long', day:'numeric',
        hour:'2-digit', minute:'2-digit', second:'2-digit'
    };
    document.getElementById("patientHeaderClock").textContent =
        now.toLocaleDateString('en-US', options);
}
setInterval(updatePatientClock, 1000);
updatePatientClock();

/* DARK MODE FEATURE */
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

// Load saved mode
const savedMode = localStorage.getItem("darkMode") === "true";
setDarkMode(savedMode);

// Toggle
darkModeToggle.addEventListener("click", () => {
    const isDark = !document.body.classList.contains("dark-mode");
    localStorage.setItem("darkMode", isDark);
    setDarkMode(isDark);
});
</script>
