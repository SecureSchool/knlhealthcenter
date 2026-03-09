<?php
session_start();
require_once "config.php";
require_once "fpdf/fpdf.php";

if(!isset($_SESSION['doctor_id'])){
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['doctor_id'];


// =============================
// FETCH DOCTOR INFO (FIXED)
// =============================
$doctor_result = mysqli_query($link,
    "SELECT fullname, specialization 
     FROM tbldoctors 
     WHERE doctor_id = '$doctor_id'"
);

$doctor_row = mysqli_fetch_assoc($doctor_result);
$doctor_name = $doctor_row['fullname'] ?? 'Doctor';
$doctor_spec = $doctor_row['specialization'] ?? '';


// =============================
// FILTERS
// =============================
$search = $_GET['search'] ?? '';
$gender_filter = $_GET['gender'] ?? '';

$safe_search = mysqli_real_escape_string($link, $search);
$safe_gender = mysqli_real_escape_string($link, $gender_filter);


// =============================
// PATIENT QUERY
// =============================
$sql = "SELECT DISTINCT p.*
        FROM tblappointments a
        INNER JOIN tblpatients p ON a.patient_id = p.patient_id
        WHERE a.doctor_assigned = '$doctor_id'";

if($safe_search){
    $sql .= " AND (p.full_name LIKE '%$safe_search%' 
              OR p.username LIKE '%$safe_search%')";
}

if($safe_gender){
    $sql .= " AND p.gender = '$safe_gender'";
}

$sql .= " ORDER BY p.full_name ASC";

$result = mysqli_query($link, $sql);


// =============================
// PDF SETUP
// =============================
$pdf = new FPDF('L','mm','A4');
$pdf->AddPage();


// =============================
// LOGO CENTERED
// =============================
$logoPath = 'knlLogo.jpg';
$logoSize = 25;
$pageWidth = $pdf->GetPageWidth();
$logoX = ($pageWidth / 2) - ($logoSize / 2);

if(file_exists($logoPath)){
    $pdf->Image($logoPath, $logoX, 10, $logoSize, $logoSize);
}

$pdf->Ln($logoSize + 3);


// =============================
// PROFESSIONAL HEADER
// =============================
$pdf->SetFont('Arial','B',20);
$pdf->SetTextColor(0,0,60);
$pdf->Cell(0,10,'Krus na Ligas Health Center',0,1,'C');

$pdf->SetFont('Arial','',12);
$pdf->SetTextColor(0,0,0);
$pdf->Cell(0,6,'Lt. J. Francisco St., Quezon City, Philippines, 1101',0,1,'C');

$pdf->Ln(4);

$pdf->SetFont('Arial','B',16);
$pdf->SetTextColor(0,102,204);
$pdf->Cell(0,8,'My Patients List',0,1,'C');

$pdf->SetFont('Arial','',12);
$pdf->SetTextColor(0,0,0);
$pdf->Cell(0,8,
    'Doctor Assigned: '.$doctor_name.' ('.$doctor_spec.')',
    0,1,'C'
);

$pdf->Ln(5);


// =============================
// TABLE HEADER
// =============================
$header = [
    'ID','Full Name','Username','Gender','Birthday',
    'Age','Contact','Email','Address','Status','Date'
];

$widths = [10,40,25,15,20,10,25,35,45,15,25];

$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(0,51,179);
$pdf->SetTextColor(255,255,255);

foreach($header as $i => $col){
    $pdf->Cell($widths[$i],8,$col,1,0,'C',true);
}
$pdf->Ln();


// =============================
// TABLE BODY
// =============================
$pdf->SetFont('Arial','',9);
$pdf->SetTextColor(0,0,0);
$pdf->SetFillColor(235,235,235);

$fill = false;

while($row = mysqli_fetch_assoc($result)){

    $line_counts = [
        ceil($pdf->GetStringWidth($row['full_name']) / ($widths[1]-2)),
        ceil($pdf->GetStringWidth($row['username']) / ($widths[2]-2)),
        ceil($pdf->GetStringWidth($row['email']) / ($widths[7]-2)),
        ceil($pdf->GetStringWidth($row['address']) / ($widths[8]-2))
    ];

    $rowHeight = max($line_counts) * 5;

    $pdf->Cell($widths[0],$rowHeight,$row['patient_id'],1,0,'C',$fill);
    $pdf->Cell($widths[1],$rowHeight,$row['full_name'],1,0,'L',$fill);
    $pdf->Cell($widths[2],$rowHeight,$row['username'],1,0,'L',$fill);
    $pdf->Cell($widths[3],$rowHeight,$row['gender'],1,0,'C',$fill);
    $pdf->Cell($widths[4],$rowHeight,$row['birthday'],1,0,'C',$fill);
    $pdf->Cell($widths[5],$rowHeight,$row['age'],1,0,'C',$fill);
    $pdf->Cell($widths[6],$rowHeight,$row['contact_number'],1,0,'L',$fill);
    $pdf->Cell($widths[7],$rowHeight,$row['email'],1,0,'L',$fill);
    $pdf->Cell($widths[8],$rowHeight,$row['address'],1,0,'L',$fill);
    $pdf->Cell($widths[9],$rowHeight,$row['status'],1,0,'C',$fill);
    $pdf->Cell($widths[10],$rowHeight,$row['date_registered'],1,0,'C',$fill);

    $pdf->Ln();
    $fill = !$fill;
}


// =============================
// FOOTER
// =============================
$pdf->SetY(-15);
$pdf->SetFont('Arial','I',8);
$pdf->SetTextColor(120,120,120);
$pdf->Cell(0,10,'Generated on '.date('Y-m-d H:i:s'),0,0,'R');


// =============================
// OUTPUT
// =============================
$pdf->Output('D','doctor_patients_list.pdf');
?>
