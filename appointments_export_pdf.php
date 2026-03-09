<?php
session_start();
require_once "config.php";
require_once "fpdf/fpdf.php";

// ================= CHECK ADMIN LOGIN =================
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

// ================= GET FILTERS =================
$search_name   = $_GET['search_name'] ?? '';
$status_filter = $_GET['status'] ?? '';
$service_filter = $_GET['service'] ?? '';
$start_date    = $_GET['start_date'] ?? '';
$end_date      = $_GET['end_date'] ?? '';

// ================= BUILD SQL =================
$sql = "SELECT 
            a.appointment_id,
            p.full_name AS patient_name,
            d.fullname AS doctor_name,
            s.service_name,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.appointment_type,
            a.queue_number
        FROM tblappointments a
        JOIN tblpatients p ON a.patient_id = p.patient_id
        LEFT JOIN tbldoctors d ON a.doctor_assigned = d.doctor_id
        LEFT JOIN tblservices s ON a.service_id = s.service_id
        WHERE 1";

if($search_name){
    $safe = mysqli_real_escape_string($link, $search_name);
    $sql .= " AND p.full_name LIKE '%$safe%'";
}
if($status_filter){
    $safe = mysqli_real_escape_string($link, $status_filter);
    $sql .= " AND a.status='$safe'";
}
if($service_filter){
    $safe = mysqli_real_escape_string($link, $service_filter);
    $sql .= " AND a.service_id='$safe'";
}
if($start_date && $end_date){
    $sql .= " AND a.appointment_date BETWEEN '$start_date' AND '$end_date'";
} elseif($start_date){
    $sql .= " AND a.appointment_date >= '$start_date'";
} elseif($end_date){
    $sql .= " AND a.appointment_date <= '$end_date'";
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC";
$result = mysqli_query($link, $sql);

// ================= BUILD DYNAMIC TITLE =================
$phrases = [];
if($search_name) $phrases[] = 'Patient: '.$search_name;
if($status_filter) $phrases[] = 'Status: '.$status_filter;
if($service_filter) $phrases[] = 'Service: '.$service_filter;
if($start_date && $end_date) $phrases[] = "From $start_date to $end_date";
elseif($start_date) $phrases[] = "From $start_date";
elseif($end_date) $phrases[] = "Up to $end_date";

$dynamic_title = "Appointments";
if(!empty($phrases)){
    $dynamic_title .= " (".implode(", ", $phrases).")";
}

// ================= PDF CLASS =================
class PDF extends FPDF {
    function Header(){
        $logo = 'knlLogo.jpg'; // Update your logo path
        if(file_exists($logo)){
            $this->Image($logo, ($this->GetPageWidth()/2)-12, 8, 24);
        }
        $this->Ln(26);
        $this->SetFont('Arial','B',18);
        $this->SetTextColor(0,0,70);
        $this->Cell(0,8,'Krus na Ligas Health Center',0,1,'C');

        $this->SetFont('Arial','',11);
        $this->SetTextColor(0,0,0);
        $this->Cell(0,6,'Lt. J. Francisco St., Quezon City, Philippines, 1101',0,1,'C');
        $this->Ln(3);

        $this->SetFont('Arial','B',14);
        $this->SetTextColor(0,102,204);
        $this->Cell(0,8,'Appointments List',0,1,'C');
        $this->Ln(2);
    }

    function Footer(){
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(120,120,120);
        $this->Cell(0,10,'Generated '.date('Y-m-d H:i').' | Page '.$this->PageNo().'/{nb}',0,0,'R');
    }
}

// ================= CREATE PDF =================
$pdf = new PDF('L','mm','A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// Column widths (compressed to fit landscape)
$w = [
    'ID'=>15,
    'Patient'=>40,
    'Doctor'=>40,
    'Service'=>35,
    'Date'=>25,
    'Time'=>20,
    'Status'=>25,
    'Type'=>30,
    'Queue'=>15
];

// Table header
$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(0,51,179);
$pdf->SetTextColor(255,255,255);

foreach($w as $col=>$width){
    $pdf->Cell($width,7,$col,1,0,'C',true);
}
$pdf->Ln();

// Table body
$pdf->SetFont('Arial','',9);
$pdf->SetTextColor(0,0,0);
$pdf->SetFillColor(240,240,240);
$fill = false;

while($row = mysqli_fetch_assoc($result)){
    $pdf->Cell($w['ID'],7,$row['appointment_id'],1,0,'C',$fill);
    $pdf->Cell($w['Patient'],7,$row['patient_name'],1,0,'L',$fill);
    $pdf->Cell($w['Doctor'],7,$row['doctor_name'] ?? 'Not assigned',1,0,'L',$fill);
    $pdf->Cell($w['Service'],7,$row['service_name'] ?? 'N/A',1,0,'L',$fill);
    $pdf->Cell($w['Date'],7,$row['appointment_date'],1,0,'C',$fill);
    $pdf->Cell($w['Time'],7,$row['appointment_time'],1,0,'C',$fill);
    $pdf->Cell($w['Status'],7,$row['status'],1,0,'C',$fill);
    $pdf->Cell($w['Type'],7,$row['appointment_type'] ?? 'N/A',1,0,'C',$fill);
    $pdf->Cell($w['Queue'],7,$row['queue_number'] ?? '—',1,0,'C',$fill);
    $pdf->Ln();
    $fill = !$fill;
}

$pdf->Output('D','appointments_list.pdf');
exit;
?>
