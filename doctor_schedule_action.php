<?php
session_start();
require_once "config.php";

header('Content-Type: application/json');

if(!isset($_SESSION['doctor_id'])){
    echo json_encode(['status'=>'error','msg'=>'Unauthorized']);
    exit;
}

$doctor_id = $_SESSION['doctor_id'];
$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? '';

// --- HELPER FUNCTIONS ---

// Check if datetime is in the past
function is_past_datetime($date, $time){
    $now = new DateTime();
    $dt = new DateTime("$date $time");
    return $dt < $now;
}

// Check overlapping schedules
function check_overlap($link, $doctor_id, $service_id, $date, $start, $end, $exclude_id=0){

    $sql = "SELECT * FROM tbldoctor_schedules 
            WHERE doctor_id=? 
            AND service_id=? 
            AND schedule_date=? 
            AND ((start_time<=? AND end_time>?) OR (start_time<? AND end_time>=?))";

    if($exclude_id>0) $sql .= " AND schedule_id!=?";

    $stmt = $link->prepare($sql);

    if($exclude_id>0){
        $stmt->bind_param("iisssssi", $doctor_id, $service_id, $date, $start, $start, $end, $end, $exclude_id);
    } else {
        $stmt->bind_param("iisssss", $doctor_id, $service_id, $date, $start, $start, $end, $end);
    }

    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// --- VALIDATE SERVICES ---
$validServices = [];
$serviceQuery = mysqli_query($link, "SELECT service_id FROM tblservices WHERE doctor_id='$doctor_id'");
while($row = mysqli_fetch_assoc($serviceQuery)){
    $validServices[] = $row['service_id'];
}

if(empty($validServices)){
    echo json_encode(['status'=>'error','msg'=>'Cannot manage schedule. No service assigned.']);
    exit;
}

// --- ADD SCHEDULE ---
if($action=='add'){
    $d = $data['data'];

    if(empty($d['service_id']) || !in_array(intval($d['service_id']), $validServices)){
        echo json_encode(['status'=>'error','msg'=>'Selected service is invalid']);
        exit;
    }

    $service_id = intval($d['service_id']);
    $date = $d['date'];
    $start = $d['start'];
    $end = $d['end'];

    if(is_past_datetime($date,$start)){
        echo json_encode(['status'=>'error','msg'=>'Cannot set schedule in the past']);
        exit;
    }
    if($end <= $start){
        echo json_encode(['status'=>'error','msg'=>'End time must be after start time']);
        exit;
    }

    // Create 30-min slots
    $slots = [];
    $current = strtotime($start);
    $end_time = strtotime($end);

    while($current + 30*60 <= $end_time){
        $slotStart = date("H:i", $current);
        $slotEnd = date("H:i", $current + 30*60);

        // Skip lunch break
        if(strtotime($slotEnd) > strtotime("12:00") && strtotime($slotStart) < strtotime("13:00")){
            $current += 30*60;
            continue;
        }

        // Skip past slots
        if(is_past_datetime($date, $slotStart)){
            $current += 30*60;
            continue;
        }

        // Skip overlapping slots
        if(check_overlap($link, $doctor_id, $service_id, $date, $slotStart, $slotEnd)){
            $current += 30*60;
            continue;
        }

        $slots[] = ['start'=>$slotStart,'end'=>$slotEnd];
        $current += 30*60;
    }

    if(empty($slots)){
        echo json_encode(['status'=>'error','msg'=>'No slots added (overlap/lunch/past)']);
        exit;
    }

    $stmt = $link->prepare("INSERT INTO tbldoctor_schedules 
        (doctor_id, service_id, schedule_date, start_time, end_time, max_patients, date_created) 
        VALUES (?,?,?,?,?,1,NOW())");
    foreach($slots as $slot){
        $stmt->bind_param("iisss", $doctor_id, $service_id, $date, $slot['start'], $slot['end']);
        $stmt->execute();
    }

    echo json_encode(['status'=>'success','msg'=>'Added '.count($slots).' 30-min slots for selected service']);
    exit;
}

// --- DELETE SCHEDULE ---
if($action=='delete'){
    $id = intval($data['id']);

    // Check if booked
    $checkSql = "SELECT COUNT(*) AS count FROM tblappointments a
                 INNER JOIN tbldoctor_schedules s ON 
                 a.doctor_assigned = s.doctor_id 
                 AND a.appointment_date = s.schedule_date 
                 AND a.appointment_time = s.start_time
                 WHERE s.schedule_id = ? AND a.status IN ('Pending','Completed')";
    $stmtCheck = $link->prepare($checkSql);
    $stmtCheck->bind_param("i", $id);
    $stmtCheck->execute();
    $count = $stmtCheck->get_result()->fetch_assoc()['count'];

    if($count>0){
        echo json_encode(['status'=>'error','msg'=>'Cannot delete. Slot has booked appointments']);
        exit;
    }

    $stmt = $link->prepare("DELETE FROM tbldoctor_schedules WHERE schedule_id=? AND doctor_id=?");
    $stmt->bind_param("ii",$id,$doctor_id);
    $stmt->execute();
    echo json_encode(['status'=>'success','msg'=>'Schedule deleted successfully']);
    exit;
}

// --- EDIT SCHEDULE ---
if($action=='edit'){
    $d = $data['data'];
    $id = intval($data['id']);

    $service_id = intval($d['service_id']); // <-- add this
    if(!in_array($service_id, $validServices)){
        echo json_encode(['status'=>'error','msg'=>'Selected service is invalid']);
        exit;
    }

    if(is_past_datetime($d['date'],$d['start'])){
        echo json_encode(['status'=>'error','msg'=>'Cannot set a schedule in the past']);
        exit;
    }
    if($d['end'] <= $d['start']){
        echo json_encode(['status'=>'error','msg'=>'End time must be after start time']);
        exit;
    }

    if(check_overlap($link, $doctor_id, $service_id, $d['date'], $d['start'], $d['end'], $id)){
        echo json_encode(['status'=>'error','msg'=>'Time overlaps existing schedule']);
        exit;
    }

    $stmt = $link->prepare("UPDATE tbldoctor_schedules 
        SET service_id=?, schedule_date=?, start_time=?, end_time=? 
        WHERE schedule_id=? AND doctor_id=?");
    $stmt->bind_param("isssii", $service_id, $d['date'], $d['start'], $d['end'], $id, $doctor_id);
    $stmt->execute();

    echo json_encode(['status'=>'success','msg'=>'Schedule updated']);
    exit;
}

echo json_encode(['status'=>'error','msg'=>'Invalid action']);