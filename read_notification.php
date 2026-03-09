<?php
session_start();
require_once "config.php";

$patient_id = $_SESSION['patient_id'];
$id = intval($_POST['id']);

mysqli_query($link, "UPDATE tblnotifications SET is_read=1 WHERE notif_id=$id AND patient_id=$patient_id");

echo "ok";
?>
