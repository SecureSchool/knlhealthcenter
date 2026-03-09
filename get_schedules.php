<?php
require_once "config.php";

$doctor_id = intval($_GET['doctor_id'] ?? 0);
$service_id = intval($_GET['service_id'] ?? 0);
if(!$doctor_id || !$service_id) exit(json_encode([]));

$sql = "SELECT s.schedule_id, s.schedule_date, s.start_time, s.end_time, s.max_patients,
       (SELECT COUNT(*) FROM tblappointments a 
        WHERE a.schedule_id=s.schedule_id AND a.service_id=? ) AS booked
       FROM tbldoctor_schedules s
       WHERE s.doctor_id=? AND s.schedule_date >= CURDATE()
       ORDER BY s.schedule_date, s.start_time";

$stmt = $link->prepare($sql);
$stmt->bind_param("ii", $service_id, $doctor_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($res);
