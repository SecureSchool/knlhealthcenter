<?php
session_start();
require_once "config.php";
require_once "patients.php"; // for logAction if needed

$selected = $_POST['selected'] ?? [];

if(isset($_POST['bulk_delete']) && !empty($selected)){
    $ids = implode(',', array_map('intval', $selected));

    $res = mysqli_query($link, "SELECT full_name FROM tblpatients WHERE patient_id IN ($ids)");
    while($row = mysqli_fetch_assoc($res)){
        logAction($link, "Delete", "Patients", $row['full_name'], $_SESSION['admin_name']);
    }

    if(mysqli_query($link, "DELETE FROM tblpatients WHERE patient_id IN ($ids)")){
        $_SESSION['success_delete'] = "Selected patients deleted successfully!";
    } else {
        $_SESSION['error_delete'] = "Failed to delete selected patients.";
    }

    header("Location: patients.php");
    exit;
}

if(isset($_POST['bulk_export_pdf']) || isset($_POST['bulk_export_excel'])){
    $ids = !empty($selected) ? implode(',', array_map('intval', $selected)) : '';
    $sql = "SELECT * FROM tblpatients";
    if($ids) $sql .= " WHERE patient_id IN ($ids)";
    $res = mysqli_query($link, $sql);

    if(isset($_POST['bulk_export_pdf'])){
        require('fpdf.php');
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(40,10,'Full Name',1);
        $pdf->Cell(60,10,'Address',1);
        $pdf->Cell(30,10,'Status',1);
        $pdf->Cell(30,10,'Gender',1);
        $pdf->Ln();

        while($row = mysqli_fetch_assoc($res)){
            $pdf->Cell(40,10,$row['full_name'],1);
            $pdf->Cell(60,10,$row['address'],1);
            $pdf->Cell(30,10,$row['status'],1);
            $pdf->Cell(30,10,$row['gender'],1);
            $pdf->Ln();
        }

        $pdf->Output('D','Patients.pdf');
        exit;
    }

    if(isset($_POST['bulk_export_excel'])){
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=Patients.xls");
        echo "Full Name\tAddress\tStatus\tGender\n";
        while($row = mysqli_fetch_assoc($res)){
            echo "{$row['full_name']}\t{$row['address']}\t{$row['status']}\t{$row['gender']}\n";
        }
        exit;
    }
}
