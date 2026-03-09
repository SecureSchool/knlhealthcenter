<?php
session_start();
require_once "config.php";
require_once "fpdf/fpdf.php";

if(!isset($_SESSION['staff_id'])){
    header("Location: login.php");
    exit;
}

// ================= QUERY =================
$sql = "SELECT 
            a.appointment_id,
            p.full_name AS patient_name,
            s.service_name AS service,
            a.appointment_type,
            a.queue_number,
            a.appointment_date,
            a.appointment_time,
            a.doctor_assigned,
            a.status,
            a.assignedby,
            a.cancel_reason AS notes
        FROM tblappointments a
        LEFT JOIN tblpatients p ON a.patient_id = p.patient_id
        LEFT JOIN tblservices s ON a.service_id = s.service_id
        ORDER BY a.appointment_date DESC, a.appointment_time ASC";

$result = mysqli_query($link, $sql);

// ================= PDF =================
class PDF extends FPDF {
    function Header(){
        // Logo center
        $logo = 'knlLogo.jpg'; // change if needed
        if(file_exists($logo)){
            $this->Image($logo, ($this->GetPageWidth()/2)-12, 8, 24);
        }

        $this->Ln(26);

        // Title
        $this->SetFont('Arial','B',18);
        $this->SetTextColor(0,0,70);
        $this->Cell(0,8,'Krus na Ligas Health Center',0,1,'C');

        $this->SetFont('Arial','',11);
        $this->SetTextColor(0,0,0);
        $this->Cell(0,6,'Lt. J. Francisco St., Quezon City, Philippines, 1101',0,1,'C');

        $this->Ln(3);

        $this->SetFont('Arial','B',14);
        $this->SetTextColor(0,102,204);
        $this->Cell(0,8,'Appointments List Report',0,1,'C');

        $this->Ln(4);
    }

    function Footer(){
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(120,120,120);
        $this->Cell(0,10,'Generated '.date('Y-m-d H:i').' | Page '.$this->PageNo().'/{nb}',0,0,'R');
    }
}

$pdf = new PDF('L','mm','A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// ================= TABLE HEADER =================
$headers = [
    'ID','Patient','Service','Type','Q#',
    'Date','Time','Doctor','Status','Assigned','Reason'
];

// compressed widths (fit landscape)
$widths = [12,35,35,18,12,22,18,30,22,22,44];

$pdf->SetFont('Arial','B',9);
$pdf->SetFillColor(0,51,179);
$pdf->SetTextColor(255,255,255);

foreach($headers as $i => $col){
    $pdf->Cell($widths[$i],7,$col,1,0,'C',true);
}
$pdf->Ln();

// ================= TABLE BODY =================
$pdf->SetFont('Arial','',8);
$pdf->SetTextColor(0,0,0);
$pdf->SetFillColor(240,240,240);

$fill = false;

while($row = mysqli_fetch_assoc($result)){

    // format time
    $time = date("h:i A", strtotime($row['appointment_time']));

    // auto new page
    if($pdf->GetY() > 180){
        $pdf->AddPage();

        $pdf->SetFont('Arial','B',9);
        $pdf->SetFillColor(0,51,179);
        $pdf->SetTextColor(255,255,255);
        foreach($headers as $i => $col){
            $pdf->Cell($widths[$i],7,$col,1,0,'C',true);
        }
        $pdf->Ln();
        $pdf->SetFont('Arial','',8);
        $pdf->SetTextColor(0,0,0);
    }

    // Save X/Y for multiline cell row height sync
    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $pdf->Cell($widths[0],7,$row['appointment_id'],1,0,'C',$fill);
    $pdf->Cell($widths[1],7,$row['patient_name'],1,0,'L',$fill);
    $pdf->Cell($widths[2],7,$row['service'],1,0,'L',$fill);
    $pdf->Cell($widths[3],7,$row['appointment_type'],1,0,'C',$fill);
    $pdf->Cell($widths[4],7,$row['queue_number'],1,0,'C',$fill);
    $pdf->Cell($widths[5],7,$row['appointment_date'],1,0,'C',$fill);
    $pdf->Cell($widths[6],7,$time,1,0,'C',$fill);
    $pdf->Cell($widths[7],7,$row['doctor_assigned'],1,0,'L',$fill);
    $pdf->Cell($widths[8],7,$row['status'],1,0,'C',$fill);
    $pdf->Cell($widths[9],7,$row['assignedby'],1,0,'C',$fill);

    // Multiline reason column
    $pdf->MultiCell($widths[10],7,$row['notes'],1,'L',$fill);

    // move cursor to next line properly
    $pdf->SetXY($x + array_sum(array_slice($widths,0,10)), $y);

    $pdf->Ln();
    $fill = !$fill;
}

$pdf->Output('D','staff_appointments_export_pdf.pdf');
exit;
?>
