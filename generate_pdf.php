<?php
ob_start();
require('fpdf/fpdf.php');
include 'connection.php';
include 'auth.php';
auth_require_login();

date_default_timezone_set('Asia/Manila');
$currency = 'PHP';

// ------------------ PARAMETERS ------------------
$selectedMonth = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : null;
$selectedYear  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedCat   = isset($_GET['category']) ? trim((string)$_GET['category']) : '';

$sections = $_GET['sec'] ?? ['summary','cashflow','monthly','income','expense','weekly','top','ledger','notes'];
if (!is_array($sections)) $sections = [];
$has = function($name) use ($sections) { return in_array($name, $sections, true); };

// ------------------ BUILD PERIOD CONDITION ------------------
$periodLabel = ($selectedMonth === null)
    ? "Full Year $selectedYear"
    : date('F', mktime(0,0,0,$selectedMonth,1)) . " $selectedYear";

$monthCond = ($selectedMonth !== null) ? "AND MONTH(s.sunday_date) = $selectedMonth" : "";
$yearCond  = "AND YEAR(s.sunday_date) = $selectedYear";

// Category filter only affects expense queries
$catCond = '';
if ($selectedCat !== '') {
    $safeCat = $conn->real_escape_string($selectedCat);
    $catCond = "AND c.main_category = '$safeCat'";
}

// ------------------ SUMMARY TOTALS ------------------
$incomeQ = $conn->query("
  SELECT COALESCE(SUM(i.amount),0) AS t, COUNT(i.id) AS n
  FROM income_entries i
  JOIN sundays s ON s.id = i.sunday_id
  WHERE 1=1 $yearCond $monthCond
");
$incomeRow = $incomeQ->fetch_assoc();
$totalIncome = (float)$incomeRow['t'];
$incomeCount = (int)$incomeRow['n'];

$expenseQ = $conn->query("
  SELECT COALESCE(SUM(e.amount),0) AS t, COUNT(e.id) AS n
  FROM expense_entries e
  JOIN sundays s ON s.id = e.sunday_id
  JOIN expense_categories c ON c.id = e.category_id
  WHERE 1=1 $yearCond $monthCond $catCond
");
$expenseRow = $expenseQ->fetch_assoc();
$totalExpense = (float)$expenseRow['t'];
$expenseCount = (int)$expenseRow['n'];

// Opening balance = cash BEFORE the report period starts
$periodStart = ($selectedMonth === null)
    ? "$selectedYear-01-01"
    : sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
$periodEnd = ($selectedMonth === null)
    ? "$selectedYear-12-31"
    : date('Y-m-t', strtotime($periodStart));

$openBalQ = $conn->query("
  SELECT
    COALESCE((SELECT SUM(i.amount) FROM income_entries i
              JOIN sundays s ON s.id = i.sunday_id
              WHERE s.sunday_date < '$periodStart'), 0) AS inc,
    COALESCE((SELECT SUM(e.amount) FROM expense_entries e
              JOIN sundays s ON s.id = e.sunday_id
              WHERE s.sunday_date < '$periodStart'), 0) AS exp
");
$openRow = $openBalQ->fetch_assoc();
$openingBalance = (float)$openRow['inc'] - (float)$openRow['exp'];
$closingBalance = $openingBalance + $totalIncome - $totalExpense;
$net = $totalIncome - $totalExpense;

// ------------------ DETAIL QUERIES ------------------
// Income by type
$incomeByType = $conn->query("
  SELECT i.income_type, SUM(i.amount) AS total, COUNT(i.id) AS n
  FROM income_entries i
  JOIN sundays s ON s.id = i.sunday_id
  WHERE 1=1 $yearCond $monthCond
  GROUP BY i.income_type
  ORDER BY total DESC
");

// Expenses by category
$expenseByCat = $conn->query("
  SELECT c.main_category, SUM(e.amount) AS total, COUNT(e.id) AS n
  FROM expense_entries e
  JOIN sundays s ON s.id = e.sunday_id
  JOIN expense_categories c ON c.id = e.category_id
  WHERE 1=1 $yearCond $monthCond $catCond
  GROUP BY c.main_category
  ORDER BY total DESC
");

// Monthly breakdown (full-year only)
$monthlyData = [];
if ($selectedMonth === null && $has('monthly')) {
  $r = $conn->query("
    SELECT
      MONTH(s.sunday_date) AS m,
      MONTHNAME(s.sunday_date) AS name,
      COALESCE(SUM(i.amount),0) AS inc,
      COALESCE(SUM(e.amount),0) AS exp
    FROM sundays s
    LEFT JOIN income_entries i ON i.sunday_id = s.id
    LEFT JOIN expense_entries e ON e.sunday_id = s.id
    WHERE YEAR(s.sunday_date) = $selectedYear
    GROUP BY MONTH(s.sunday_date)
    ORDER BY m
  ");
  while ($row = $r->fetch_assoc()) $monthlyData[] = $row;
}

// Weekly breakdown
$weeklyData = [];
if ($has('weekly')) {
  $r = $conn->query("
    SELECT s.sunday_date,
           COALESCE(SUM(DISTINCT i.amount),0) AS inc,
           COALESCE(SUM(DISTINCT e.amount),0) AS exp
    FROM sundays s
    LEFT JOIN income_entries i ON i.sunday_id = s.id
    LEFT JOIN expense_entries e ON e.sunday_id = s.id
    WHERE 1=1 $yearCond $monthCond
    GROUP BY s.sunday_date
    ORDER BY s.sunday_date
  ");
  // Simpler: recompute per-sunday without DISTINCT issues
  $r = $conn->query("
    SELECT s.sunday_date,
           (SELECT COALESCE(SUM(amount),0) FROM income_entries WHERE sunday_id = s.id) AS inc,
           (SELECT COALESCE(SUM(amount),0) FROM expense_entries WHERE sunday_id = s.id) AS exp
    FROM sundays s
    WHERE 1=1 $yearCond $monthCond
    ORDER BY s.sunday_date
  ");
  while ($row = $r->fetch_assoc()) $weeklyData[] = $row;
}

// Top 10 expenses
$topExpenses = [];
if ($has('top')) {
  $r = $conn->query("
    SELECT s.sunday_date, c.main_category, e.description, e.amount
    FROM expense_entries e
    JOIN sundays s ON s.id = e.sunday_id
    JOIN expense_categories c ON c.id = e.category_id
    WHERE 1=1 $yearCond $monthCond $catCond
    ORDER BY e.amount DESC
    LIMIT 10
  ");
  while ($row = $r->fetch_assoc()) $topExpenses[] = $row;
}

// Full ledger
$ledger = [];
if ($has('ledger')) {
  $r = $conn->query("
    SELECT 'INCOME' AS kind, s.sunday_date, i.income_type AS cat, i.description, i.amount
    FROM income_entries i
    JOIN sundays s ON s.id = i.sunday_id
    WHERE 1=1 $yearCond $monthCond
    UNION ALL
    SELECT 'EXPENSE' AS kind, s.sunday_date, c.main_category AS cat, e.description, e.amount
    FROM expense_entries e
    JOIN sundays s ON s.id = e.sunday_id
    JOIN expense_categories c ON c.id = e.category_id
    WHERE 1=1 $yearCond $monthCond $catCond
    ORDER BY sunday_date DESC, kind
  ");
  while ($row = $r->fetch_assoc()) $ledger[] = $row;
}

// ------------------ PDF CLASS ------------------
class JCLFReport extends FPDF
{
    public $period = '';
    public $filters = '';

    function Header()
    {
        $logo = __DIR__ . '/logo.png';
        if (file_exists($logo)) $this->Image($logo, 10, 8, 22);

        $this->SetFont('Arial', 'B', 14);
        $this->SetXY(36, 10);
        $this->Cell(0, 7, 'JESUS CHRIST LEADS FELLOWSHIP', 0, 2);

        $this->SetFont('Arial', '', 9);
        $this->SetX(36);
        $this->Cell(0, 5, 'Finance Report  ·  ' . $this->period, 0, 2);

        $this->SetX(36);
        $this->SetTextColor(120);
        $this->Cell(0, 5, $this->filters, 0, 2);
        $this->SetTextColor(0);

        $this->SetDrawColor(200);
        $this->Line(10, 32, 200, 32);
        $this->SetY(36);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120);
        $this->Cell(0, 10, 'JCLF Finance Report  ·  Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function SectionTitle($title)
    {
        $this->Ln(2);
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(37, 99, 235);
        $this->SetTextColor(255);
        $this->Cell(0, 9, '  ' . $title, 0, 1, 'L', true);
        $this->SetTextColor(0);
        $this->Ln(2);
    }

    function SubText($text)
    {
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(110);
        $this->MultiCell(0, 4, $text);
        $this->SetTextColor(0);
    }

    function KeyValueBox($label, $value, $color = [0,0,0])
    {
        $this->SetFillColor(245, 247, 251);
        $this->SetFont('Arial', '', 10);
        $this->Cell(70, 9, '  ' . $label, 1, 0, 'L', true);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($color[0], $color[1], $color[2]);
        $this->Cell(50, 9, $value, 1, 1, 'R');
        $this->SetTextColor(0);
    }

    function TableHeader($cols)
    {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(225, 232, 245);
        foreach ($cols as $c) {
            $this->Cell($c[0], 7, $c[1], 1, 0, $c[2] ?? 'L', true);
        }
        $this->Ln();
    }

    function Row($cells, $fill = false)
    {
        $this->SetFont('Arial', '', 9);
        $fillColor = $fill ? [250, 250, 252] : [255, 255, 255];
        $this->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
        foreach ($cells as $c) {
            $this->Cell($c[0], 6, $c[1], 1, 0, $c[2] ?? 'L', true);
        }
        $this->Ln();
    }
}

// ------------------ BUILD PDF ------------------
$pdf = new JCLFReport();
$pdf->AliasNbPages();
$pdf->period  = $periodLabel;
$pdf->filters = 'Filter: ' . ($selectedCat !== '' ? "Category = $selectedCat · " : '') .
                'Generated: ' . date('M j, Y g:i a');
$pdf->AddPage();

// ============ COVER / TITLE ============
$pdf->SetFont('Arial', 'B', 18);
$pdf->Cell(0, 10, 'Finance Report', 0, 1, 'C');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 6, $periodLabel, 0, 1, 'C');
$pdf->Ln(6);

// ============ 1 · EXECUTIVE SUMMARY ============
if ($has('summary')) {
    $pdf->SectionTitle('1 · Executive Summary');
    $pdf->SubText('High-level totals for the reporting period.');
    $pdf->Ln(1);

    $pdf->KeyValueBox('Total Income',           $currency . ' ' . number_format($totalIncome, 2), [22, 163, 74]);
    $pdf->KeyValueBox('Total Expenses',         $currency . ' ' . number_format($totalExpense, 2), [220, 38, 38]);
    $pdf->KeyValueBox('Net Change (Income − Expenses)', $currency . ' ' . number_format($net, 2), $net >= 0 ? [37, 99, 235] : [220, 38, 38]);
    $pdf->KeyValueBox('Opening Balance (before ' . $periodStart . ')', $currency . ' ' . number_format($openingBalance, 2));
    $pdf->KeyValueBox('Closing Balance (Cash on Hand)', $currency . ' ' . number_format($closingBalance, 2), $closingBalance >= 0 ? [22, 163, 74] : [220, 38, 38]);
    $pdf->Ln(3);

    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, "Income entries: $incomeCount    ·    Expense entries: $expenseCount", 0, 1);
    $pdf->Ln(2);
}

// ============ 2 · CASH FLOW RECONCILIATION ============
if ($has('cashflow')) {
    $pdf->SectionTitle('2 · Cash Flow Reconciliation');
    $pdf->SubText('Step-by-step: how the opening balance becomes the closing balance.');
    $pdf->Ln(1);

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(110, 7, 'Opening balance at start of period', 1, 0, 'L', true);
    $pdf->Cell(60, 7, $currency . ' ' . number_format($openingBalance, 2), 1, 1, 'R', true);

    $pdf->Cell(110, 7, '(+) Total income received', 1, 0, 'L');
    $pdf->Cell(60, 7, $currency . ' ' . number_format($totalIncome, 2), 1, 1, 'R');

    $pdf->Cell(110, 7, '(−) Total expenses paid', 1, 0, 'L');
    $pdf->Cell(60, 7, $currency . ' ' . number_format($totalExpense, 2), 1, 1, 'R');

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(230, 242, 255);
    $pdf->Cell(110, 8, '(=) Closing balance (Cash on Hand)', 1, 0, 'L', true);
    $pdf->Cell(60, 8, $currency . ' ' . number_format($closingBalance, 2), 1, 1, 'R', true);
    $pdf->Ln(3);
}

// ============ 3 · MONTHLY BREAKDOWN ============
if ($has('monthly') && $selectedMonth === null && !empty($monthlyData)) {
    $pdf->AddPage();
    $pdf->SectionTitle('3 · Monthly Breakdown');
    $pdf->SubText('Income, expenses and running closing balance for each month of ' . $selectedYear . '.');

    $pdf->TableHeader([
        [30, 'Month'],
        [38, 'Income', 'R'],
        [38, 'Expenses', 'R'],
        [36, 'Net', 'R'],
        [48, 'Closing Balance', 'R'],
    ]);

    $running = $openingBalance;
    $i = 0;
    foreach ($monthlyData as $m) {
        $running += ($m['inc'] - $m['exp']);
        $pdf->Row([
            [30, $m['name']],
            [38, number_format((float)$m['inc'], 2), 'R'],
            [38, number_format((float)$m['exp'], 2), 'R'],
            [36, number_format((float)$m['inc'] - (float)$m['exp'], 2), 'R'],
            [48, number_format($running, 2), 'R'],
        ], $i++ % 2 === 1);
    }

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(230, 242, 255);
    $pdf->Cell(30, 7, 'TOTAL', 1, 0, 'L', true);
    $pdf->Cell(38, 7, number_format($totalIncome, 2), 1, 0, 'R', true);
    $pdf->Cell(38, 7, number_format($totalExpense, 2), 1, 0, 'R', true);
    $pdf->Cell(36, 7, number_format($net, 2), 1, 0, 'R', true);
    $pdf->Cell(48, 7, number_format($closingBalance, 2), 1, 1, 'R', true);
    $pdf->Ln(3);
}

// ============ 4 · INCOME BY TYPE ============
if ($has('income') && $incomeByType && $incomeByType->num_rows > 0) {
    $pdf->SectionTitle('4 · Income by Type');
    $pdf->SubText('Where income came from during the period.');

    $pdf->TableHeader([
        [70, 'Income Type'],
        [30, 'Entries', 'C'],
        [40, 'Amount', 'R'],
        [40, '% of Total', 'R'],
    ]);

    $i = 0;
    while ($row = $incomeByType->fetch_assoc()) {
        $amt = (float)$row['total'];
        $pct = $totalIncome > 0 ? ($amt / $totalIncome) * 100 : 0;
        $pdf->Row([
            [70, $row['income_type']],
            [30, (string)$row['n'], 'C'],
            [40, number_format($amt, 2), 'R'],
            [40, number_format($pct, 1) . '%', 'R'],
        ], $i++ % 2 === 1);
    }
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(230, 242, 255);
    $pdf->Cell(70, 7, 'TOTAL', 1, 0, 'L', true);
    $pdf->Cell(30, 7, (string)$incomeCount, 1, 0, 'C', true);
    $pdf->Cell(40, 7, number_format($totalIncome, 2), 1, 0, 'R', true);
    $pdf->Cell(40, 7, '100.0%', 1, 1, 'R', true);
    $pdf->Ln(3);
}

// ============ 5 · EXPENSE BY CATEGORY ============
if ($has('expense') && $expenseByCat && $expenseByCat->num_rows > 0) {
    if ($pdf->GetY() > 200) $pdf->AddPage();
    $pdf->SectionTitle('5 · Expense by Category');
    $pdf->SubText('Where money was spent during the period.');

    $pdf->TableHeader([
        [70, 'Category'],
        [30, 'Entries', 'C'],
        [40, 'Amount', 'R'],
        [40, '% of Total', 'R'],
    ]);

    $i = 0;
    while ($row = $expenseByCat->fetch_assoc()) {
        $amt = (float)$row['total'];
        $pct = $totalExpense > 0 ? ($amt / $totalExpense) * 100 : 0;
        $pdf->Row([
            [70, $row['main_category']],
            [30, (string)$row['n'], 'C'],
            [40, number_format($amt, 2), 'R'],
            [40, number_format($pct, 1) . '%', 'R'],
        ], $i++ % 2 === 1);
    }
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(255, 235, 235);
    $pdf->Cell(70, 7, 'TOTAL', 1, 0, 'L', true);
    $pdf->Cell(30, 7, (string)$expenseCount, 1, 0, 'C', true);
    $pdf->Cell(40, 7, number_format($totalExpense, 2), 1, 0, 'R', true);
    $pdf->Cell(40, 7, '100.0%', 1, 1, 'R', true);
    $pdf->Ln(3);
}

// ============ 6 · WEEKLY BREAKDOWN ============
if ($has('weekly') && !empty($weeklyData)) {
    $pdf->AddPage();
    $pdf->SectionTitle('6 · Weekly (Sunday) Breakdown');
    $pdf->SubText('Income vs expenses on each Sunday, with running balance.');

    $pdf->TableHeader([
        [40, 'Sunday'],
        [35, 'Income', 'R'],
        [35, 'Expenses', 'R'],
        [30, 'Net', 'R'],
        [50, 'Running Balance', 'R'],
    ]);

    $running = $openingBalance;
    $i = 0;
    foreach ($weeklyData as $w) {
        $running += ((float)$w['inc'] - (float)$w['exp']);
        $pdf->Row([
            [40, $w['sunday_date']],
            [35, number_format((float)$w['inc'], 2), 'R'],
            [35, number_format((float)$w['exp'], 2), 'R'],
            [30, number_format((float)$w['inc'] - (float)$w['exp'], 2), 'R'],
            [50, number_format($running, 2), 'R'],
        ], $i++ % 2 === 1);
    }
    $pdf->Ln(3);
}

// ============ 7 · TOP 10 EXPENSES ============
if ($has('top') && !empty($topExpenses)) {
    $pdf->AddPage();
    $pdf->SectionTitle('7 · Top 10 Expenses');
    $pdf->SubText('Largest individual expense items during the period.');

    $pdf->TableHeader([
        [30, 'Date'],
        [55, 'Category'],
        [65, 'Description'],
        [30, 'Amount', 'R'],
    ]);

    $i = 0;
    foreach ($topExpenses as $t) {
        $pdf->Row([
            [30, $t['sunday_date']],
            [55, $t['main_category']],
            [65, mb_substr($t['description'] ?? '—', 0, 30)],
            [30, number_format((float)$t['amount'], 2), 'R'],
        ], $i++ % 2 === 1);
    }
    $pdf->Ln(3);
}

// ============ 8 · FULL LEDGER ============
if ($has('ledger') && !empty($ledger)) {
    $pdf->AddPage();
    $pdf->SectionTitle('8 · Full Transaction Ledger');
    $pdf->SubText('All income and expense entries in the period, newest first.');

    $pdf->TableHeader([
        [22, 'Date'],
        [24, 'Type'],
        [40, 'Category'],
        [55, 'Description'],
        [24, 'Amount', 'R'],
    ]);

    $i = 0;
    foreach ($ledger as $row) {
        if ($pdf->GetY() > 255) {
            $pdf->AddPage();
            $pdf->TableHeader([
                [22, 'Date'],
                [24, 'Type'],
                [40, 'Category'],
                [55, 'Description'],
                [24, 'Amount', 'R'],
            ]);
        }
        $pdf->Row([
            [22, $row['sunday_date']],
            [24, $row['kind']],
            [40, mb_substr((string)$row['cat'], 0, 20)],
            [55, mb_substr($row['description'] ?? '—', 0, 30)],
            [24, number_format((float)$row['amount'], 2), 'R'],
        ], $i++ % 2 === 1);
    }
    $pdf->Ln(3);
}

// ============ 9 · NOTES & FORMULAS ============
if ($has('notes')) {
    if ($pdf->GetY() > 220) $pdf->AddPage();
    $pdf->SectionTitle('9 · Notes & Formulas');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'Definitions used in this report:', 0, 1);
    $pdf->SetFont('Arial', '', 9);
    $notes = [
        '• Period: ' . $periodLabel . ' (' . $periodStart . ' to ' . $periodEnd . ')',
        '• Opening Balance = cumulative (Income − Expenses) for all dates BEFORE ' . $periodStart . '.',
        '• Net Change = Total Income − Total Expenses within the period.',
        '• Closing Balance (Cash on Hand) = Opening Balance + Net Change.',
        '• Percentages are relative to the section total for the same period.',
        '• All figures are in ' . $currency . '.',
    ];
    foreach ($notes as $n) {
        $pdf->MultiCell(0, 5, $n);
    }
    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->MultiCell(0, 5, 'Prepared on ' . date('F j, Y \\a\\t g:i A') . ' by the JCLF Finance Tracker.');
}

// ------------------ OUTPUT ------------------
$filename = 'JCLF_Report_' . str_replace(' ', '_', $periodLabel) . '.pdf';
ob_end_clean();
$pdf->Output('D', $filename);
exit();