<?php
require_once "config.php";
require_once "fpdf/fpdf.php";

// ================= GET FILTER =================
$search_name = $_GET['search_name'] ?? '';

// ================= BUILD SQL =================
$sql = "SELECT doctor_id, fullname, username, specialization, contact_number, email, date_created, status 
        FROM tbldoctors WHERE 1";
if($search_name){
    $safe = mysqli_real_escape_string($link, $search_name);
    $sql .= " AND fullname LIKE '%$safe%'";
}
$sql .= " ORDER BY fullname ASC";
$result = mysqli_query($link, $sql);

// ================= PDF CLASS =================
class PDF extends FPDF {
    function Header(){
        $logo = 'knlLogo.jpg'; // Update your logo path
        if(file_exists($logo)){
            $this->Image($logo, ($this->GetPageWidth()/2)-12, 8, 24);
        }
        $this->Ln(20);
        $this->SetFont('Arial','B',16);
        $this->SetTextColor(0,0,70);
        $this->Cell(0,6,'Krus na Ligas Health Center',0,1,'C');

        $this->SetFont('Arial','',10);
        $this->SetTextColor(0,0,0);
        $this->Cell(0,5,'Lt. J. Francisco St., Quezon City, Philippines, 1101',0,1,'C');
        $this->Ln(2);

        $this->SetFont('Arial','B',12);
        $this->SetTextColor(0,102,204);
        $this->Cell(0,6,'Doctors List',0,1,'C');
        $this->Ln(2);
    }

    function Footer(){
        $this->SetY(-12);
        $this->SetFont('Arial','I',7);
        $this->SetTextColor(120,120,120);
        $this->Cell(0,10,'Generated '.date('Y-m-d H:i').' | Page '.$this->PageNo().'/{nb}',0,0,'R');
    }
}

// ================= CREATE PDF =================
$pdf = new PDF('L','mm','A4'); // Landscape
$pdf->AliasNbPages();
$pdf->AddPage();

// Compressed column widths
$w = [
    'ID'=>12,
    'Full Name'=>40,
    'Username'=>30,
    'Specialization'=>40,
    'Contact'=>30,
    'Email'=>50,
    'Status'=>20,
    'Date Created'=>25
];

// Table header
$pdf->SetFont('Arial','B',8);
$pdf->SetFillColor(0,51,179);
$pdf->SetTextColor(255,255,255);

foreach($w as $col=>$width){
    $pdf->Cell($width,6,$col,1,0,'C',true);
}
$pdf->Ln();

// Table body
$pdf->SetFont('Arial','',7);
$pdf->SetTextColor(0,0,0);
$pdf->SetFillColor(240,240,240);
$fill = false;

while($row = mysqli_fetch_assoc($result)){
    $pdf->Cell($w['ID'],6,$row['doctor_id'],1,0,'C',$fill);
    $pdf->Cell($w['Full Name'],6,$row['fullname'],1,0,'L',$fill);
    $pdf->Cell($w['Username'],6,$row['username'],1,0,'L',$fill);
    $pdf->Cell($w['Specialization'],6,$row['specialization'],1,0,'L',$fill);
    $pdf->Cell($w['Contact'],6,$row['contact_number'],1,0,'L',$fill);
    $pdf->Cell($w['Email'],6,$row['email'],1,0,'L',$fill);
    $pdf->Cell($w['Status'],6,$row['status'],1,0,'C',$fill);
    $pdf->Cell($w['Date Created'],6,$row['date_created'],1,0,'C',$fill);
    $pdf->Ln();
    $fill = !$fill;
}

$pdf->Output('D','doctors_list.pdf');
exit;
?>
