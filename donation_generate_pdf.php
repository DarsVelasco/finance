<?php
ob_start();
require('fpdf/fpdf.php');
include 'connection.php';
include 'auth.php';
auth_require_login();

date_default_timezone_set('Asia/Manila');
$currency = 'PHP';

$month = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : null;
$year  = (int)($_GET['year'] ?? date('Y'));
$cat   = trim((string)($_GET['category'] ?? ''));

$periodLabel = ($month === null) ? "Full Year $year" : date('F', mktime(0,0,0,$month,1))." $year";
$monthCond   = $month !== null ? "AND MONTH(s.sunday_date) = $month" : "";
$catCond     = '';
if ($cat !== '') {
    $catCond = "AND c.main_category = '".$conn->real_escape_string($cat)."'";
}

$incomeRow  = $conn->query("SELECT COALESCE(SUM(d.amount),0) t, COUNT(d.id) n
    FROM donation_income_entries d JOIN sundays s ON s.id=d.sunday_id
    WHERE YEAR(s.sunday_date) = $year $monthCond")->fetch_assoc();
$expenseRow = $conn->query("SELECT COALESCE(SUM(e.amount),0) t, COUNT(e.id) n
    FROM donation_expense_entries e
    JOIN sundays s ON s.id=e.sunday_id
    JOIN donation_categories c ON c.id=e.category_id
    WHERE YEAR(s.sunday_date) = $year $monthCond $catCond")->fetch_assoc();

$totIncome  = (float)$incomeRow['t'];
$totExpense = (float)$expenseRow['t'];
$net        = $totIncome - $totExpense;

$byType = $conn->query("
  SELECT d.donation_type, SUM(d.amount) t, COUNT(d.id) n
  FROM donation_income_entries d
  JOIN sundays s ON s.id=d.sunday_id
  WHERE YEAR(s.sunday_date) = $year $monthCond
  GROUP BY d.donation_type ORDER BY t DESC
");
$byCat = $conn->query("
  SELECT c.main_category, SUM(e.amount) t, COUNT(e.id) n
  FROM donation_expense_entries e
  JOIN sundays s ON s.id=e.sunday_id
  JOIN donation_categories c ON c.id=e.category_id
  WHERE YEAR(s.sunday_date) = $year $monthCond $catCond
  GROUP BY c.main_category ORDER BY t DESC
");

class DonationPDF extends FPDF {
    public $period = '';
    function Header() {
        if (file_exists(__DIR__.'/logo.png')) $this->Image(__DIR__.'/logo.png', 10, 8, 22);
        $this->SetFont('Arial','B',14);
        $this->SetXY(36,10);
        $this->Cell(0,7,'JESUS CHRIST LEADS FELLOWSHIP',0,2);
        $this->SetFont('Arial','',9);
        $this->SetX(36);
        $this->Cell(0,5,'DONATION REPORT  ·  '.$this->period,0,2);
        $this->SetDrawColor(200);
        $this->Line(10,32,200,32);
        $this->SetY(36);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(120);
        $this->Cell(0,10,'Donation Report · Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
    function SectionTitle($t) {
        $this->Ln(2);
        $this->SetFont('Arial','B',12);
        $this->SetFillColor(22,163,74);
        $this->SetTextColor(255);
        $this->Cell(0,9,'  '.$t,0,1,'L',true);
        $this->SetTextColor(0);
        $this->Ln(2);
    }
    function TableHeader($cols) {
        $this->SetFont('Arial','B',9);
        $this->SetFillColor(225,245,232);
        foreach ($cols as $c) $this->Cell($c[0],7,$c[1],1,0,$c[2]??'L',true);
        $this->Ln();
    }
    function Row($cells, $fill=false) {
        $this->SetFont('Arial','',9);
        $this->SetFillColor($fill?250:255,$fill?250:255,$fill?252:255);
        foreach ($cells as $c) $this->Cell($c[0],6,$c[1],1,0,$c[2]??'L',true);
        $this->Ln();
    }
}

$pdf = new DonationPDF();
$pdf->AliasNbPages();
$pdf->period = $periodLabel;
$pdf->AddPage();

$pdf->SetFont('Arial','B',18);
$pdf->Cell(0,10,'Donation Report',0,1,'C');
$pdf->SetFont('Arial','',11);
$pdf->Cell(0,6,$periodLabel,0,1,'C');
$pdf->Ln(4);

$pdf->SectionTitle('Summary');
$pdf->SetFont('Arial','',10);
$pdf->Cell(110,8,'Total Donation Income',1,0,'L',true);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(60,8,$currency.' '.number_format($totIncome,2),1,1,'R');
$pdf->SetFont('Arial','',10);
$pdf->Cell(110,8,'Total Donation Expenses',1,0,'L',true);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(60,8,$currency.' '.number_format($totExpense,2),1,1,'R');
$pdf->SetFont('Arial','',10);
$pdf->Cell(110,8,'Net Donation Balance',1,0,'L',true);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(60,8,$currency.' '.number_format($net,2),1,1,'R');
$pdf->Ln(4);

$pdf->SectionTitle('Donation Income by Type');
$pdf->TableHeader([[70,'Type'],[30,'Entries','C'],[70,'Amount','R']]);
$i=0;
while ($r = $byType->fetch_assoc()) {
    $pdf->Row([[70,$r['donation_type']],[30,(string)$r['n'],'C'],
               [70,number_format((float)$r['t'],2),'R']], $i++%2===1);
}
$pdf->SetFont('Arial','B',9);
$pdf->SetFillColor(225,245,232);
$pdf->Cell(70,7,'TOTAL',1,0,'L',true);
$pdf->Cell(30,7,(string)$incomeRow['n'],1,0,'C',true);
$pdf->Cell(70,7,number_format($totIncome,2),1,1,'R',true);
$pdf->Ln(3);

$pdf->SectionTitle('Donation Expenses by Category');
$pdf->TableHeader([[70,'Category'],[30,'Entries','C'],[70,'Amount','R']]);
$i=0;
while ($r = $byCat->fetch_assoc()) {
    $pdf->Row([[70,$r['main_category']],[30,(string)$r['n'],'C'],
               [70,number_format((float)$r['t'],2),'R']], $i++%2===1);
}
$pdf->SetFont('Arial','B',9);
$pdf->SetFillColor(255,235,235);
$pdf->Cell(70,7,'TOTAL',1,0,'L',true);
$pdf->Cell(30,7,(string)$expenseRow['n'],1,0,'C',true);
$pdf->Cell(70,7,number_format($totExpense,2),1,1,'R',true);

ob_end_clean();
$pdf->Output('D', 'JCLF_Donation_'.str_replace(' ','_',$periodLabel).'.pdf');
exit;