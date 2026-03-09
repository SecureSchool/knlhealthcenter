<?php
session_start();
require_once "config.php";

// Only allow admin
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit;
}

// Get filters
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date'] ?? null;
$type       = $_GET['type'] ?? 'excel'; // default excel

$dateFilter = "";
if ($start_date && $end_date) {
    $dateFilter = " AND appointment_date BETWEEN '$start_date' AND '$end_date' ";
}

// Fetch data
$query = "
    SELECT a.appointment_id, a.appointment_date, a.appointment_time,
           p.full_name AS patient_name, s.service_name,
           a.doctor_assigned, a.status
    FROM tblappointments a
    LEFT JOIN tblpatients p ON a.patient_id = p.patient_id
    LEFT JOIN tblservices s ON a.service_id = s.service_id
    WHERE 1=1 $dateFilter
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
";
$result = mysqli_query($link, $query);

// -------------------- EXPORT TO CSV/EXCEL --------------------
if ($type === "excel") {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=admin_report.csv');
    $output = fopen('php://output', 'w');

    // Headers
    fputcsv($output, ['Appointment ID', 'Date', 'Time', 'Patient Name', 'Service', 'Doctor', 'Status']);

    while($row = mysqli_fetch_assoc($result)){
        fputcsv($output, [
            $row['appointment_id'],
            $row['appointment_date'],
            $row['appointment_time'],
            $row['patient_name'],
            $row['service_name'],
            $row['doctor_assigned'],
            $row['status']
        ]);
    }

    fclose($output);
    exit;
}

// -------------------- EXPORT TO PDF --------------------
if ($type === "pdf") {
    require_once("fpdf/fpdf.php");

    class PDF extends FPDF {
        function Header(){
            $this->SetFont('Arial','B',14);
            $this->Cell(0,10,'Admin Report - Appointments',0,1,'C');
            $this->Ln(5);
        }
        function Footer(){
            $this->SetY(-15);
            $this->SetFont('Arial','I',8);
            $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
        }
    }

    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',10);

    // Table header
    $pdf->Cell(20,10,'ID',1);
    $pdf->Cell(25,10,'Date',1);
    $pdf->Cell(20,10,'Time',1);
    $pdf->Cell(40,10,'Patient',1);
    $pdf->Cell(35,10,'Service',1);
    $pdf->Cell(30,10,'Doctor',1);
    $pdf->Cell(20,10,'Status',1);
    $pdf->Ln();

    $pdf->SetFont('Arial','',9);

    while($row = mysqli_fetch_assoc($result)){
        $pdf->Cell(20,8,$row['appointment_id'],1);
        $pdf->Cell(25,8,$row['appointment_date'],1);
        $pdf->Cell(20,8,$row['appointment_time'],1);
        $pdf->Cell(40,8,$row['patient_name'],1);
        $pdf->Cell(35,8,$row['service_name'],1);
        $pdf->Cell(30,8,$row['doctor_assigned'],1);
        $pdf->Cell(20,8,$row['status'],1);
        $pdf->Ln();
    }

    $pdf->Output("D","admin_report.pdf");
    exit;
}
