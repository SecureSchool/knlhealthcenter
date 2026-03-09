<?php
$conn = mysqli_connect("localhost", "root", "", "cs310-cs3b-2025");

// Check DB connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

/*
    STATUS REFERENCE:
    pending   = waiting
    called    = currently being served
    completed = done
*/

// -----------------------------------
// GET CURRENT CALLED / PRIORITY
// -----------------------------------
$called = mysqli_query($conn, 
    "SELECT a.*, 
            p.full_name AS patient_name, 
            s.service_name
     FROM tblappointments a
     LEFT JOIN tblpatients p ON a.patient_id = p.patient_id
     LEFT JOIN tblservices s ON a.service_id = s.service_id
     WHERE a.status IN ('called')
     ORDER BY a.appointment_time ASC
     LIMIT 1"
);

// If no “called” appointment → fallback to next pending
if (mysqli_num_rows($called) == 0) {
    $called = mysqli_query($conn, 
        "SELECT a.*, 
                p.full_name AS patient_name, 
                s.service_name
         FROM tblappointments a
         LEFT JOIN tblpatients p ON a.patient_id = p.patient_id
         LEFT JOIN tblservices s ON a.service_id = s.service_id
         WHERE a.status='pending'
         ORDER BY a.priority DESC, a.queue_number ASC
         LIMIT 1"
    );
}

$current = mysqli_fetch_assoc($called);

// -----------------------------------
// GET LAST 5 COMPLETED
// -----------------------------------
$served = mysqli_query($conn,
    "SELECT a.queue_number, a.priority,
            p.full_name AS patient_name,
            s.service_name
     FROM tblappointments a
     LEFT JOIN tblpatients p ON a.patient_id = p.patient_id
     LEFT JOIN tblservices s ON a.service_id = s.service_id
     WHERE a.status='completed'
     ORDER BY a.appointment_time DESC
     LIMIT 5"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Health Center Queue Display</title>
<meta http-equiv="refresh" content="5">
<style>
body {
    background: #ecf0f1;
    font-family: Arial, sans-serif;
    text-align: center;
    padding-top: 20px;
}
.now-serving {
    animation: fadeIn 1.5s;
    background: #2c3e50;
    color: #fff;
    padding: 30px;
    border-radius: 20px;
    width: 80%;
    margin: auto;
    margin-bottom: 40px;
}
.now-serving h1 {
    font-size: 56px;
    margin: 0;
}
.now-serving h2 {
    font-size: 120px;
    margin: 10px 0;
}
.priority { 
    color: #e74c3c; 
    font-weight: bold; 
    font-size: 40px;
}
.normal { 
    color: #3498db; 
    font-weight: bold; 
    font-size: 40px;
}
.table-box {
    width: 80%;
    margin: 0 auto;
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 0 10px #ccc;
}
table {
    width: 100%;
    font-size: 30px;
    border-collapse: collapse;
}
th, td {
    padding: 12px;
    border-bottom: 2px solid #ddd;
}
th {
    background: #34495e;
    color: white;
    font-size: 32px;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-30px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>

<?php if($current): ?>
<div class="now-serving">
    <h1>NOW SERVING</h1>

    <h2 id="ticket">
        <?php echo "Q" . str_pad($current['queue_number'], 3, "0", STR_PAD_LEFT); ?>
    </h2>

    <h3 class="<?php echo ($current['priority'] == 1 ? 'priority' : 'normal'); ?>">
        <?php echo ($current['priority'] == 1 ? "PRIORITY PATIENT" : "REGULAR PATIENT"); ?>
    </h3>

    <p style="font-size:36px; margin-top:15px;">
        Service: <b><?php echo htmlspecialchars($current['service_name']); ?></b>
    </p>

    <p style="font-size:36px; margin-top:5px;">
        Patient: <b><?php echo htmlspecialchars($current['patient_name']); ?></b>
    </p>
</div>
<?php endif; ?>

<!-- SPEECH ANNOUNCEMENT -->
<?php if($current): ?>
<script>
let lastTicket = localStorage.getItem("lastTicket");

let ticket = document.getElementById("ticket").innerText.trim();

if(ticket !== lastTicket){
    speak("Now serving " + ticket);
    localStorage.setItem("lastTicket", ticket);
}

function speak(text){
    let msg = new SpeechSynthesisUtterance();
    msg.text = text;
    msg.lang = "en-US";
    msg.rate = 0.9;
    speechSynthesis.speak(msg);
}
</script>
<?php endif; ?>

</body>
</html>
