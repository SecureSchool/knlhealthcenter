<?php
require_once "config.php";

$service_id = intval($_GET['service_id'] ?? 0);
if (!$service_id) exit(json_encode([]));

// Check if you have a linking table between doctors and services
// Assuming tbldoctor_services(doctor_id, service_id)
$sql = "SELECT d.doctor_id, d.fullname
        FROM tbldoctors d
        JOIN tbldoctor_services ds ON d.doctor_id = ds.doctor_id
        WHERE ds.service_id = ? AND d.status = 'Active'
        ORDER BY d.fullname ASC";

$stmt = $link->prepare($sql);
$stmt->bind_param("i", $service_id);
$stmt->execute();
$res = $stmt->get_result();

$doctors = [];
while($row = $res->fetch_assoc()){
    $doctors[] = $row;
}

echo json_encode($doctors);
