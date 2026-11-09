<?php
include 'connection.php';
include 'auth.php';
auth_require_login();

date_default_timezone_set('Asia/Manila');

$selectedMonth = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : null;
$selectedYear  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedCat   = isset($_GET['category']) ? trim((string)$_GET['category']) : '';

$sections = $_GET['sec'] ?? ['summary','cashflow','monthly','income','expense','weekly','top','ledger','notes'];
if (!is_array($sections)) $sections = [];
$has = function($name) use ($sections) { return in_array($name, $sections, true); };

$periodLabel = ($selectedMonth === null)
    ? "Full Year $selectedYear"
    : date('F', mktime(0,0,0,$selectedMonth,1)) . " $selectedYear";

$monthCond = ($selectedMonth !== null) ? "AND MONTH(s.sunday_date) = $selectedMonth" : "";
$yearCond  = "AND YEAR(s.sunday_date) = $selectedYear";
$catCond   = '';
if ($selectedCat !== '') {
    $safeCat = $conn->real_escape_string($selectedCat);
    $catCond = "AND c.main_category = '$safeCat'";
}

// Summary
$incomeRow  = $conn->query("SELECT COALESCE(SUM(i.amount),0) t, COUNT(i.id) n FROM income_entries i JOIN sundays s ON s.id=i.sunday_id WHERE 1=1 $yearCond $monthCond")->fetch_assoc();
$expenseRow = $conn->query("SELECT COALESCE(SUM(e.amount),0) t, COUNT(e.id) n FROM expense_entries e JOIN sundays s ON s.id=e.sunday_id JOIN expense_categories c ON c.id=e.category_id WHERE 1=1 $yearCond $monthCond $catCond")->fetch_assoc();
$totalIncome  = (float)$incomeRow['t'];
$totalExpense = (float)$expenseRow['t'];
$incomeCount  = (int)$incomeRow['n'];
$expenseCount = (int)$expenseRow['n'];

$periodStart = ($selectedMonth === null) ? "$selectedYear-01-01" : sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
$periodEnd   = ($selectedMonth === null) ? "$selectedYear-12-31" : date('Y-m-t', strtotime($periodStart));

$openRow = $conn->query("
  SELECT
    COALESCE((SELECT SUM(i.amount) FROM income_entries i JOIN sundays s ON s.id=i.sunday_id WHERE s.sunday_date < '$periodStart'),0) inc,
    COALESCE((SELECT SUM(e.amount) FROM expense_entries e JOIN sundays s ON s.id=e.sunday_id WHERE s.sunday_date < '$periodStart'),0) exp
")->fetch_assoc();
$openingBalance = (float)$openRow['inc'] - (float)$openRow['exp'];
$net            = $totalIncome - $totalExpense;
$closingBalance = $openingBalance + $net;

// Output
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="JCLF_Report_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $periodLabel) . '_' . date('Y-m-d_Hi') . '.csv"');

$out = fopen('php://output', 'w');

// Excel-friendly: BOM
fwrite($out, "\xEF\xBB\xBF");

// ---------- HEADER ----------
fputcsv($out, ['JESUS CHRIST LEADS FELLOWSHIP']);
fputcsv($out, ['Finance Report']);
fputcsv($out, ['Period', $periodLabel, 'From', $periodStart, 'To', $periodEnd]);
fputcsv($out, ['Filter', $selectedCat !== '' ? "Category = $selectedCat" : 'None']);
fputcsv($out, ['Generated', date('F j, Y g:i A')]);
fputcsv($out, []);

// ---------- SUMMARY ----------
if ($has('summary')) {
    fputcsv($out, ['=== EXECUTIVE SUMMARY ===']);
    fputcsv($out, ['Total Income',  number_format($totalIncome, 2),  'Entries', $incomeCount]);
    fputcsv($out, ['Total Expenses', number_format($totalExpense, 2), 'Entries', $expenseCount]);
    fputcsv($out, ['Net Change',    number_format($net, 2)]);
    fputcsv($out, ['Opening Balance', number_format($openingBalance, 2)]);
    fputcsv($out, ['Closing Balance (Cash on Hand)', number_format($closingBalance, 2)]);
    fputcsv($out, []);
}

// ---------- CASH FLOW ----------
if ($has('cashflow')) {
    fputcsv($out, ['=== CASH FLOW RECONCILIATION ===']);
    fputcsv($out, ['Description', 'Amount']);
    fputcsv($out, ['Opening balance at start of period', number_format($openingBalance, 2)]);
    fputcsv($out, ['(+) Total income',                   number_format($totalIncome, 2)]);
    fputcsv($out, ['(-) Total expenses',                 number_format($totalExpense, 2)]);
    fputcsv($out, ['(=) Closing balance (Cash on Hand)', number_format($closingBalance, 2)]);
    fputcsv($out, []);
}

// ---------- MONTHLY ----------
if ($has('monthly') && $selectedMonth === null) {
    fputcsv($out, ['=== MONTHLY BREAKDOWN ===']);
    fputcsv($out, ['Month', 'Income', 'Expenses', 'Net', 'Closing Balance']);
    $r = $conn->query("
      SELECT MONTH(s.sunday_date) m, MONTHNAME(s.sunday_date) name,
             COALESCE(SUM(i.amount),0) inc, COALESCE(SUM(e.amount),0) exp
      FROM sundays s
      LEFT JOIN income_entries i ON i.sunday_id = s.id
      LEFT JOIN expense_entries e ON e.sunday_id = s.id
      WHERE YEAR(s.sunday_date) = $selectedYear
      GROUP BY m ORDER BY m
    ");
    $running = $openingBalance;
    while ($row = $r->fetch_assoc()) {
        $running += (float)$row['inc'] - (float)$row['exp'];
        fputcsv($out, [
            $row['name'],
            number_format((float)$row['inc'], 2),
            number_format((float)$row['exp'], 2),
            number_format((float)$row['inc'] - (float)$row['exp'], 2),
            number_format($running, 2),
        ]);
    }
    fputcsv($out, ['TOTAL', number_format($totalIncome, 2), number_format($totalExpense, 2), number_format($net, 2), number_format($closingBalance, 2)]);
    fputcsv($out, []);
}

// ---------- INCOME BY TYPE ----------
if ($has('income')) {
    fputcsv($out, ['=== INCOME BY TYPE ===']);
    fputcsv($out, ['Income Type', 'Entries', 'Amount', '% of Total']);
    $r = $conn->query("
      SELECT i.income_type, SUM(i.amount) total, COUNT(i.id) n
      FROM income_entries i JOIN sundays s ON s.id=i.sunday_id
      WHERE 1=1 $yearCond $monthCond
      GROUP BY i.income_type ORDER BY total DESC
    ");
    while ($row = $r->fetch_assoc()) {
        $amt = (float)$row['total'];
        $pct = $totalIncome > 0 ? ($amt / $totalIncome) * 100 : 0;
        fputcsv($out, [$row['income_type'], $row['n'], number_format($amt, 2), number_format($pct, 1) . '%']);
    }
    fputcsv($out, ['TOTAL', $incomeCount, number_format($totalIncome, 2), '100.0%']);
    fputcsv($out, []);
}

// ---------- EXPENSE BY CATEGORY ----------
if ($has('expense')) {
    fputcsv($out, ['=== EXPENSE BY CATEGORY ===']);
    fputcsv($out, ['Category', 'Entries', 'Amount', '% of Total']);
    $r = $conn->query("
      SELECT c.main_category, SUM(e.amount) total, COUNT(e.id) n
      FROM expense_entries e
      JOIN sundays s ON s.id=e.sunday_id
      JOIN expense_categories c ON c.id=e.category_id
      WHERE 1=1 $yearCond $monthCond $catCond
      GROUP BY c.main_category ORDER BY total DESC
    ");
    while ($row = $r->fetch_assoc()) {
        $amt = (float)$row['total'];
        $pct = $totalExpense > 0 ? ($amt / $totalExpense) * 100 : 0;
        fputcsv($out, [$row['main_category'], $row['n'], number_format($amt, 2), number_format($pct, 1) . '%']);
    }
    fputcsv($out, ['TOTAL', $expenseCount, number_format($totalExpense, 2), '100.0%']);
    fputcsv($out, []);
}

// ---------- WEEKLY ----------
if ($has('weekly')) {
    fputcsv($out, ['=== WEEKLY BREAKDOWN ===']);
    fputcsv($out, ['Sunday', 'Income', 'Expenses', 'Net', 'Running Balance']);
    $r = $conn->query("
      SELECT s.sunday_date,
             (SELECT COALESCE(SUM(amount),0) FROM income_entries WHERE sunday_id=s.id) inc,
             (SELECT COALESCE(SUM(amount),0) FROM expense_entries WHERE sunday_id=s.id) exp
      FROM sundays s
      WHERE 1=1 $yearCond $monthCond
      ORDER BY s.sunday_date
    ");
    $running = $openingBalance;
    while ($row = $r->fetch_assoc()) {
        $running += (float)$row['inc'] - (float)$row['exp'];
        fputcsv($out, [
            $row['sunday_date'],
            number_format((float)$row['inc'], 2),
            number_format((float)$row['exp'], 2),
            number_format((float)$row['inc'] - (float)$row['exp'], 2),
            number_format($running, 2),
        ]);
    }
    fputcsv($out, []);
}

// ---------- TOP EXPENSES ----------
if ($has('top')) {
    fputcsv($out, ['=== TOP 10 EXPENSES ===']);
    fputcsv($out, ['Date', 'Category', 'Description', 'Amount']);
    $r = $conn->query("
      SELECT s.sunday_date, c.main_category, e.description, e.amount
      FROM expense_entries e
      JOIN sundays s ON s.id=e.sunday_id
      JOIN expense_categories c ON c.id=e.category_id
      WHERE 1=1 $yearCond $monthCond $catCond
      ORDER BY e.amount DESC LIMIT 10
    ");
    while ($row = $r->fetch_assoc()) {
        fputcsv($out, [$row['sunday_date'], $row['main_category'], $row['description'], number_format((float)$row['amount'], 2)]);
    }
    fputcsv($out, []);
}

// ---------- FULL LEDGER ----------
if ($has('ledger')) {
    fputcsv($out, ['=== FULL TRANSACTION LEDGER ===']);
    fputcsv($out, ['Date', 'Kind', 'Category/Type', 'Description', 'Amount']);
    $r = $conn->query("
      SELECT 'INCOME' kind, s.sunday_date, i.income_type cat, i.description, i.amount
      FROM income_entries i JOIN sundays s ON s.id=i.sunday_id
      WHERE 1=1 $yearCond $monthCond
      UNION ALL
      SELECT 'EXPENSE', s.sunday_date, c.main_category, e.description, e.amount
      FROM expense_entries e
      JOIN sundays s ON s.id=e.sunday_id
      JOIN expense_categories c ON c.id=e.category_id
      WHERE 1=1 $yearCond $monthCond $catCond
      ORDER BY sunday_date DESC
    ");
    while ($row = $r->fetch_assoc()) {
        fputcsv($out, [$row['sunday_date'], $row['kind'], $row['cat'], $row['description'], number_format((float)$row['amount'], 2)]);
    }
    fputcsv($out, []);
}

// ---------- NOTES ----------
if ($has('notes')) {
    fputcsv($out, ['=== NOTES & FORMULAS ===']);
    fputcsv($out, ['Period', $periodLabel]);
    fputcsv($out, ['From', $periodStart, 'To', $periodEnd]);
    fputcsv($out, ['Opening Balance = cumulative (Income - Expenses) before ' . $periodStart]);
    fputcsv($out, ['Net Change = Total Income - Total Expenses in the period']);
    fputcsv($out, ['Closing Balance (Cash on Hand) = Opening + Net Change']);
    fputcsv($out, ['Currency', 'PHP']);
    fputcsv($out, ['Prepared', date('F j, Y g:i A')]);
}

fclose($out);
exit();