<?php
session_start();
require_once "config.php";

$patient_id = $_SESSION['patient_id'];

// Get unread count
$count = mysqli_fetch_assoc(mysqli_query(
    $link,
    "SELECT COUNT(*) AS total FROM tblnotifications WHERE patient_id=$patient_id AND is_read=0"
))['total'];

// Get latest 10 notifications
$list = mysqli_query(
    $link,
    "SELECT * FROM tblnotifications WHERE patient_id=$patient_id ORDER BY created_at DESC LIMIT 10"
);

$notifications = [];
while($row = mysqli_fetch_assoc($list)) {
    $notifications[] = $row;
}

echo json_encode([
    'count' => $count,
    'notifications' => $notifications
]);
?>
