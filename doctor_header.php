<?php
if(!isset($_SESSION)) { session_start(); }
require_once "config.php";

// Fetch doctor info
$doctor_data = mysqli_fetch_assoc(mysqli_query($link, 
    "SELECT fullname, profile_pic FROM tbldoctors WHERE doctor_id='{$_SESSION['doctor_id']}'"
));

$doctor_pic = !empty($doctor_data['profile_pic']) ? $doctor_data['profile_pic'] : 'default.png';
$doctor_fullname = $doctor_data['fullname'];
?>
<!-- HEADER WRAPPER -->
<div class="header-container shadow-sm mb-4 p-4 d-flex justify-content-between align-items-center"
     style="background: linear-gradient(90deg, #001BB7, #0033ff); border-radius: 14px; color: white;">

    <!-- LEFT SIDE: Welcome + Assigned Services -->
    <div>
        <h2 class="fw-bold mb-2">Welcome, Dr. <?= htmlspecialchars($doctor_fullname) ?> 👋</h2>
        <p class="mb-3" style="opacity: .9;">Have a wonderful day at KNL Health Center</p>

        <!-- Assigned Services -->
        <?php
        $res_services = mysqli_query($link, "SELECT service_name FROM tblservices WHERE doctor_id='{$_SESSION['doctor_id']}'");
        if ($res_services && mysqli_num_rows($res_services) > 0):
        ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php while ($service = mysqli_fetch_assoc($res_services)): ?>
                    <div class="service-pill px-3 py-1 fw-semibold" title="<?= htmlspecialchars($service['service_name']) ?>">
                        <i class="fas fa-stethoscope me-1"></i>
                        <?= htmlspecialchars($service['service_name']) ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-white-50 mb-3" style="font-size:0.85rem;">No services assigned yet.</p>
        <?php endif; ?>

        <a href="doctor_appointments.php" class="btn btn-light fw-semibold"
            style="border-radius: 10px; padding: 8px 18px; color:#001BB7; box-shadow:0 3px 8px rgba(255,255,255,0.25);">
            <i class="fas fa-calendar-check me-1"></i> View Appointments
        </a>
    </div>

    <!-- RIGHT SIDE: Dark Mode + Profile + Clock -->
    <div class="d-flex flex-column align-items-end">

        <!-- DARK MODE + PROFILE ROW -->
        <div class="dark-profile-row mb-3 d-flex gap-2 justify-content-end align-items-center">

            <!-- DARK MODE BUTTON -->
            <button id="darkModeToggle" class="btn btn-light"
                    style="border-radius:50px; padding:6px 14px; box-shadow:0 3px 10px rgba(0,0,0,0.15);">
                <i class="fas fa-moon"></i>
            </button>

            <!-- PROFILE DROPDOWN -->
            <div class="dropdown">
                <button class="btn btn-light d-flex align-items-center gap-2"
                        type="button" id="doctorDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        style="border-radius:50px; padding:6px 14px; box-shadow:0 3px 10px rgba(0,0,0,0.15);">
                    <img src="uploads/doctors/<?= htmlspecialchars($doctor_pic) ?>" alt="Profile" class="rounded-circle"
                         style="width:45px; height:45px; object-fit:cover; border:2px solid #001BB7;">
                    <span class="fw-semibold text-dark"><?= htmlspecialchars($doctor_fullname) ?> ▾</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="doctorDropdown">
                    <li><span class="dropdown-item-text fw-bold"><?= htmlspecialchars($doctor_fullname); ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="doctor_profile.php"><i class="fas fa-user-md"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="#" id="logoutBtnHeader"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>

        </div>

        <!-- CLOCK -->
        <div id="currentDateTime"
             style="background: rgba(255,255,255,0.15); padding: 12px 18px; border-radius: 14px;
                    font-weight: 600; color: #fff; min-width:240px; text-align:center;
                    backdrop-filter: blur(5px); box-shadow:0 4px 12px rgba(0,0,0,0.15);">
        </div>

    </div>
</div>

<style>
/* MOBILE/TABLET ADJUSTMENTS ONLY */
@media (max-width: 767.98px) {

    /* Header container: stack left & right vertically */
    .header-container {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }

    /* Left side full width */
    .header-container > div:first-child {
        width: 100%;
        text-align: center;
    }

    /* Flex wrap for service pills */
    .header-container .d-flex.flex-wrap {
        justify-content: center;
    }

    /* Buttons (view appointments) full width */
    .header-container a.btn {
        width: 100%;
        justify-content: center;
        margin-top: 10px;
    }

    /* Right side full width */
    .header-container > div:last-child {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    /* Profile + dark mode row centered on mobile */
    .dark-profile-row {
        justify-content: center !important;
        width: 100%;
    }

    /* Clock full width below */
    #currentDateTime {
        width: 100%;
        text-align: center;
    }
}

/* Optional: smaller buttons on very small screens */
@media (max-width: 480px) {
    .dark-profile-row .dropdown button,
    .dark-profile-row #darkModeToggle {
        padding: 6px 10px;
        font-size: 0.9rem;
    }
}
</style>

<script>
    // Logout Confirmation
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
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
    });

    // Clock
    function updateDateTime() {
        const now = new Date();
        const options = {
            weekday:'long', year:'numeric', month:'long', day:'numeric',
            hour:'2-digit', minute:'2-digit', second:'2-digit'
        };
        document.getElementById('currentDateTime').textContent =
            now.toLocaleDateString('en-US', options);
    }
    setInterval(updateDateTime, 1000);
    updateDateTime();

    // DARK MODE FEATURE
    const darkModeToggle = document.getElementById("darkModeToggle");

    function setDarkMode(isDark) {
        if (isDark) {
            document.body.classList.add("dark-mode");
            darkModeToggle.innerHTML = '<i class="fas fa-sun"></i> ';
        } else {
            document.body.classList.remove("dark-mode");
            darkModeToggle.innerHTML = '<i class="fas fa-moon"></i> ';
        }
    }

    const savedMode = localStorage.getItem("darkMode") === "true";
    setDarkMode(savedMode);

    darkModeToggle.addEventListener("click", () => {
        const isDark = !document.body.classList.contains("dark-mode");
        localStorage.setItem("darkMode", isDark);
        setDarkMode(isDark);
    });
</script>

<!-- Bootstrap JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>