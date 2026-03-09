<?php
session_start();
require_once "config.php";
require_once "fpdf/fpdf.php";

// Redirect if not logged in as staff
if(!isset($_SESSION['staff_id'])){
    header("Location: login.php");
    exit;
}

// ================= FILTERS =================
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$registration_filter = $_GET['registration'] ?? '';

$search = mysqli_real_escape_string($link, $search);
$status_filter = mysqli_real_escape_string($link, $status_filter);
$registration_filter = mysqli_real_escape_string($link, $registration_filter);

// ================= FETCH =================
$sql = "SELECT * FROM tblpatients WHERE 1";
if($search) $sql .= " AND (full_name LIKE '%$search%' OR username LIKE '%$search%')";
if($status_filter) $sql .= " AND status='$status_filter'";
if($registration_filter) $sql .= " AND registration_source='$registration_filter'";
$sql .= " ORDER BY full_name ASC";

$result = mysqli_query($link, $sql);

// ================= PDF =================
$pdf = new FPDF('L','mm','A4');
$pdf->AddPage();

// ===== Logo Center =====
$logoPath = 'knlLogo.jpg'; // change if needed
$logoSize = 25;
$pageWidth = $pdf->GetPageWidth();
$logoX = ($pageWidth / 2) - ($logoSize / 2);

if(file_exists($logoPath)){
    $pdf->Image($logoPath, $logoX, 8, $logoSize, $logoSize);
}

$pdf->Ln(28);

// ===== Header Text =====
$pdf->SetFont('Arial','B',20);
$pdf->SetTextColor(0,0,70);
$pdf->Cell(0,10,'Krus na Ligas Health Center',0,1,'C');

$pdf->SetFont('Arial','',12);
$pdf->SetTextColor(0,0,0);
$pdf->Cell(0,6,'Lt. J. Francisco St., Quezon City, Philippines, 1101',0,1,'C');

$pdf->Ln(3);

$pdf->SetFont('Arial','B',16);
$pdf->SetTextColor(0,102,204);
$pdf->Cell(0,8,'Patients List Report',0,1,'C');

$pdf->Ln(6);

// ================= TABLE HEADER =================
$pdf->SetFont('Arial','B',9);
$pdf->SetFillColor(0,51,179);
$pdf->SetTextColor(255,255,255);

// Compressed widths (fits landscape)
$widths = [15,45,35,28,55,22,30,30];

$headers = [
    'ID',
    'Full Name',
    'Username',
    'Contact',
    'Email',
    'Status',
    'Registration',
    'Date'
];

foreach($headers as $i => $h){
    $pdf->Cell($widths[$i],7,$h,1,0,'C',true);
}
$pdf->Ln();

// ================= TABLE BODY =================
$pdf->SetFont('Arial','',8);
$pdf->SetTextColor(0,0,0);
$pdf->SetFillColor(240,240,240);

$fill = false;

while($row = mysqli_fetch_assoc($result)){

    // Auto new page if near bottom
    if($pdf->GetY() > 180){
        $pdf->AddPage();

        // repeat header
        $pdf->SetFont('Arial','B',9);
        $pdf->SetFillColor(0,51,179);
        $pdf->SetTextColor(255,255,255);
        foreach($headers as $i => $h){
            $pdf->Cell($widths[$i],7,$h,1,0,'C',true);
        }
        $pdf->Ln();

        $pdf->SetFont('Arial','',8);
        $pdf->SetTextColor(0,0,0);
    }

    $pdf->Cell($widths[0],7,$row['patient_id'],1,0,'C',$fill);
    $pdf->Cell($widths[1],7,$row['full_name'],1,0,'L',$fill);
    $pdf->Cell($widths[2],7,$row['username'],1,0,'L',$fill);
    $pdf->Cell($widths[3],7,$row['contact_number'],1,0,'L',$fill);
    $pdf->Cell($widths[4],7,$row['email'],1,0,'L',$fill);
    $pdf->Cell($widths[5],7,$row['status'],1,0,'C',$fill);
    $pdf->Cell($widths[6],7,$row['registration_source'],1,0,'C',$fill);
    $pdf->Cell($widths[7],7,$row['date_registered'],1,0,'C',$fill);

    $pdf->Ln();
    $fill = !$fill;
}

// ================= FOOTER =================
$pdf->SetY(-15);
$pdf->SetFont('Arial','I',8);
$pdf->SetTextColor(120,120,120);
$pdf->Cell(0,10,'Generated on '.date('Y-m-d H:i:s'),0,0,'R');

// ================= OUTPUT =================
$pdf->Output('D','patients_list.pdf');
exit;
?>
