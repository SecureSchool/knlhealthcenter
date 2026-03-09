<?php
require_once "config.php";
header('Content-Type: application/json');

$role = $_POST['role'] ?? '';
$username = $_POST['username'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if(!$role || !$username || !$new_password){
    echo json_encode(["status"=>"error","message"=>"Missing fields."]);
    exit;
}

// decide which table to check
switch($role){
    case 'admin':   
        $table = 'tbladmin';   
        $col   = 'username'; 
        $checkStatus = false; // admin has no status
        break;
    case 'staff':   
        $table = 'tblstaff';   
        $col   = 'username'; 
        $checkStatus = true;
        break;
    case 'doctor':  
        $table = 'tbldoctors'; 
        $col   = 'username'; 
        $checkStatus = true;
        break;
    case 'patient': 
        $table = 'tblpatients';
        $col   = 'username'; 
        $checkStatus = false; // patients have status but usually always allowed
        break;
    default: 
        echo json_encode(["status"=>"error","message"=>"Invalid role."]);
        exit;
}

// check user exists
$stmt = $link->prepare("SELECT * FROM $table WHERE $col=? LIMIT 1");
$stmt->bind_param("s",$username);
$stmt->execute();
$res = $stmt->get_result();

if($res->num_rows===0){
    echo json_encode(["status"=>"error","message"=>"Username not found."]);
    exit;
}

$row = $res->fetch_assoc();

// block inactive staff/doctor
if($checkStatus && strtolower($row['status']) !== 'active'){
    echo json_encode(["status"=>"error","message"=>"Your account is inactive. Contact the admin."]);
    exit;
}

// update password (plain text, no hash as per your requirement)
$stmt2 = $link->prepare("UPDATE $table SET password=? WHERE $col=?");
$stmt2->bind_param("ss",$new_password,$username);

if($stmt2->execute()){
    echo json_encode(["status"=>"success","message"=>"Password updated successfully."]);
}else{
    echo json_encode(["status"=>"error","message"=>"Failed to update password."]);
}
