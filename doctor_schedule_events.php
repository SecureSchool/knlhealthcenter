<?php
session_start();
require_once "config.php";

header('Content-Type: application/json');

if(!isset($_SESSION['doctor_id'])){
    echo json_encode([]);
    exit;
}

$doctor_id = $_SESSION['doctor_id'];

// Fetch services to assign colors
$serviceColors = [
    // Assign colors per service_id manually or dynamically
];
$serviceQuery = mysqli_query($link, "SELECT service_id, service_name FROM tblservices WHERE doctor_id='$doctor_id'");
$colorPalette = ['#1BB700','#0033FF','#FF8800','#6A0DAD','#00CED1','#FFD700','#FF1493','#008080','#800000','#00FF7F'];
$i = 0;
while($row = mysqli_fetch_assoc($serviceQuery)){
    $serviceColors[$row['service_id']] = $colorPalette[$i % count($colorPalette)];
    $i++;
}

$sql = "SELECT s.schedule_id, s.schedule_date, s.start_time, s.end_time, 
               s.service_id,
               sv.service_name,
       (SELECT COUNT(*) FROM tblappointments a 
        WHERE a.doctor_assigned = s.doctor_id 
        AND a.service_id = s.service_id
        AND a.appointment_date = s.schedule_date 
        AND a.appointment_time = s.start_time
        AND a.status IN ('Pending','Completed')) AS booked
FROM tbldoctor_schedules s
INNER JOIN tblservices sv ON s.service_id = sv.service_id
WHERE s.doctor_id = ?
ORDER BY s.schedule_date, s.start_time";

$stmt = $link->prepare($sql);
$stmt->bind_param("i",$doctor_id);
$stmt->execute();
$res = $stmt->get_result();

$events = [];
$now = new DateTime();
$lunchStart = "12:00";
$lunchEnd = "13:00";
$lunchDatesAdded = [];

while($row = $res->fetch_assoc()){
    if(empty($row['service_name'])) continue; // Skip invalid

    $scheduleDate = $row['schedule_date'];
    $startDateTime = new DateTime($scheduleDate.' '.$row['start_time']);
    $isBooked = $row['booked'] > 0;
    $isPast = $startDateTime < $now;

    // Red if booked, else service color
    $color = $isBooked ? '#FF6B6B' : ($serviceColors[$row['service_id']] ?? '#1BB700');

    $disabled = $isBooked || $isPast;

    $events[] = [
        'id'=>$row['schedule_id'],
        'title'=>($isBooked ? "Booked" : "Available") . " [" . $row['service_name'] . "]",
        'start'=>$scheduleDate.'T'.$row['start_time'],
        'end'=>$scheduleDate.'T'.$row['end_time'],
        'color'=>$color,
        'editable'=>!$disabled,
        'startEditable'=>!$disabled,
        'durationEditable'=>!$disabled,
        'selectable'=>!$disabled
    ];

    // Add lunch break once per day
    if(!in_array($scheduleDate,$lunchDatesAdded)){
        $lunchDatesAdded[] = $scheduleDate;
        $lunchDateTime = new DateTime("$scheduleDate $lunchEnd");
        if($lunchDateTime > $now){
            $events[] = [
                'id'=>'lunch_break_'.$scheduleDate,
                'title'=>'Lunch Break',
                'start'=>$scheduleDate.'T'.$lunchStart,
                'end'=>$scheduleDate.'T'.$lunchEnd,
                'color'=>'#FFA500',
                'editable'=>false,
                'startEditable'=>false,
                'durationEditable'=>false,
                'selectable'=>false
            ];
        }
    }
}

echo json_encode($events);