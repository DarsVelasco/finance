<?php
ob_start();
require('fpdf/fpdf.php');
include 'connection.php';

date_default_timezone_set('Asia/Manila');
$peso = 'PHP ';

// ✅ Optional: Get month/year from URL or use current
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$selectedYear  = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$currentMonth = date('F', mktime(0, 0, 0, $selectedMonth, 1));
$currentYear = $selectedYear;

// ✅ Fetch totals using correct join (sunday_id → sundays.id)
$income_res = $conn->query("
  SELECT SUM(i.amount) AS total_income
  FROM income_entries i
  JOIN sundays s ON s.id = i.sunday_id
  WHERE MONTH(s.sunday_date) = $selectedMonth
    AND YEAR(s.sunday_date) = $selectedYear
");
$income = $income_res->fetch_assoc()['total_income'] ?? 0;

$expense_res = $conn->query("
  SELECT SUM(e.amount) AS total_expenses
  FROM expense_entries e
  JOIN sundays s ON s.id = e.sunday_id
  WHERE MONTH(s.sunday_date) = $selectedMonth
    AND YEAR(s.sunday_date) = $selectedYear
");
$expense = $expense_res->fetch_assoc()['total_expenses'] ?? 0;

$balance = $income - $expense;

// ✅ Expense breakdown by category
$cat_data = $conn->query("
  SELECT c.main_category, SUM(e.amount) AS total
  FROM expense_entries e
  JOIN expense_categories c ON c.id = e.category_id
  JOIN sundays s ON s.id = e.sunday_id
  WHERE MONTH(s.sunday_date) = $selectedMonth
    AND YEAR(s.sunday_date) = $selectedYear
  GROUP BY c.main_category
");

// ✅ PDF Generation
$pdf = new FPDF();
$pdf->AddPage();

// Title
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, "JCLF - Finance Summary Report for $currentMonth $currentYear", 0, 1, 'C');
$pdf->Ln(5);

// Date generated
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Generated on: ' . date('F j, Y, g:i a') . ' (PH-MNL)', 0, 1, 'R');
$pdf->Ln(5);

// Summary Table
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(80, 10, 'Total Income:', 1);
$pdf->Cell(60, 10, $peso . number_format($income, 2), 1, 1);

$pdf->Cell(80, 10, 'Total Expenses:', 1);
$pdf->Cell(60, 10, $peso . number_format($expense, 2), 1, 1);

$pdf->Cell(80, 10, 'Cash on Hand:', 1);
$pdf->Cell(60, 10, $peso . number_format($balance, 2), 1, 1);
$pdf->Ln(10);

// Expense Breakdown Table
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, "Expense Breakdown by Category ($currentMonth $currentYear)", 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(100, 10, 'Category', 1);
$pdf->Cell(60, 10, 'Total Expense (PHP)', 1, 1);

$pdf->SetFont('Arial', '', 12);
while ($row = $cat_data->fetch_assoc()) {
    $pdf->Cell(100, 10, $row['main_category'], 1);
    $pdf->Cell(60, 10, number_format($row['total'], 2), 1, 1);
}

// ✅ File output with month/year
$filename = "finance_summary_{$currentMonth}_{$currentYear}.pdf";
ob_end_clean();
$pdf->Output('D', $filename);
?>
