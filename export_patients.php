<?php
require_once "config.php";
require_once "fpdf/fpdf.php";

// ================= FILTERS =================
$search_name   = $_GET['search_name'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$gender_filter = $_GET['gender_filter'] ?? '';
$min_age       = $_GET['min_age'] ?? '';
$max_age       = $_GET['max_age'] ?? '';
$street_filter = $_GET['street_filter'] ?? '';
$format        = $_GET['format'] ?? 'excel'; // pdf or excel

// Escape inputs
$safe_name   = mysqli_real_escape_string($link, $search_name);
$safe_status = mysqli_real_escape_string($link, $status_filter);
$safe_gender = mysqli_real_escape_string($link, $gender_filter);
$safe_street = mysqli_real_escape_string($link, $street_filter);

// ================= QUERY =================
$sql = "SELECT * FROM tblpatients WHERE 1";
if($safe_name) $sql .= " AND full_name LIKE '%$safe_name%'";
if($safe_status) $sql .= " AND status='$safe_status'";
if($safe_gender) $sql .= " AND gender='$safe_gender'";
if($min_age) $sql .= " AND age >= ".intval($min_age);
if($max_age) $sql .= " AND age <= ".intval($max_age);
if($safe_street) $sql .= " AND address LIKE '%$safe_street%'";
$sql .= " ORDER BY patient_id DESC";

$result = mysqli_query($link, $sql);

// ================= DYNAMIC TITLE & FILENAME =================
$phrases = [];
if($gender_filter) $phrases[] = "who are $gender_filter";
if($status_filter) $phrases[] = "with $status_filter status";
if($min_age && $max_age){
    $phrases[] = "aged between $min_age and $max_age";
} elseif($min_age){
    $phrases[] = "aged $min_age or older";
} elseif($max_age){
    $phrases[] = "aged $max_age or younger";
}
if($search_name) $phrases[] = 'with names containing "' . $search_name . '"';
if($street_filter) $phrases[] = 'living in "' . $street_filter . '"';

$dynamic_title = "All patients";
if(!empty($phrases)){
    $dynamic_title .= " " . implode(", ", $phrases);
}

$filename_parts = ['patients'];
if($search_name) $filename_parts[] = "name-$search_name";
if($status_filter) $filename_parts[] = "status-$status_filter";
if($gender_filter) $filename_parts[] = "gender-$gender_filter";
if($min_age) $filename_parts[] = "minage-$min_age";
if($max_age) $filename_parts[] = "maxage-$max_age";
if($street_filter) $filename_parts[] = "street-$street_filter";
$dynamic_filename = implode('_', $filename_parts);

// ================= EXPORT EXCEL =================
if($format == 'excel'){
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename={$dynamic_filename}.xls");
    echo "$dynamic_title\n\n"; // Professional title at top
    echo "Full Name\tAddress\tStatus\tGender\tAge\tContact\tEmail\n";
    while($row = mysqli_fetch_assoc($result)){
        echo "{$row['full_name']}\t{$row['address']}\t{$row['status']}\t{$row['gender']}\t{$row['age']}\t{$row['contact_number']}\t{$row['email']}\n";
    }
    exit;
}

// ================= EXPORT PDF =================
if($format == 'pdf'){

    class PDF extends FPDF {
        function Header(){
            $logo = 'knlLogo.jpg'; // Update logo path
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
            $this->Cell(0,8,'Patients List',0,1,'C');
            $this->Ln(4);
        }

        function Footer(){
            $this->SetY(-15);
            $this->SetFont('Arial','I',8);
            $this->SetTextColor(120,120,120);
            $this->Cell(0,10,'Generated '.date('Y-m-d H:i').' | Page '.$this->PageNo().'/{nb}',0,0,'R');
        }
    }

    $pdf = new PDF('P','mm','A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Column widths
    $w = [
        'full_name'=>30,
        'address'=>50,
        'status'=>20,
        'gender'=>15,
        'age'=>10,
        'contact'=>25,
        'email'=>40
    ];

    // Table header
    $pdf->SetFont('Arial','B',9);
    $pdf->SetFillColor(0,51,179);
    $pdf->SetTextColor(255,255,255);
    foreach($w as $col=>$width){
        $pdf->Cell($width,7,ucwords(str_replace('_',' ',$col)),1,0,'C',true);
    }
    $pdf->Ln();

    // Table body
    $pdf->SetFont('Arial','',8);
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFillColor(240,240,240);
    $fill = false;

    while($row = mysqli_fetch_assoc($result)){
        $pdf->Cell($w['full_name'],7,$row['full_name'],1,0,'L',$fill);
        $pdf->Cell($w['address'],7,$row['address'],1,0,'L',$fill);
        $pdf->Cell($w['status'],7,$row['status'],1,0,'C',$fill);
        $pdf->Cell($w['gender'],7,$row['gender'],1,0,'C',$fill);
        $pdf->Cell($w['age'],7,$row['age'],1,0,'C',$fill);
        $pdf->Cell($w['contact'],7,$row['contact_number'],1,0,'L',$fill);
        $pdf->Cell($w['email'],7,$row['email'],1,1,'L',$fill);
        $fill = !$fill;
    }

    $pdf->Output('D', $dynamic_filename.'.pdf');
    exit;
}
?>
