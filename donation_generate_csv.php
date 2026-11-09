<?php
include 'connection.php';
include 'auth.php';
auth_require_login();

date_default_timezone_set('Asia/Manila');

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

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="JCLF_Donation_'.preg_replace('/[^A-Za-z0-9_-]+/','_',$periodLabel).'.csv"');

$out = fopen('php://output','w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['JESUS CHRIST LEADS FELLOWSHIP — DONATION REPORT']);
fputcsv($out, ['Period', $periodLabel]);
fputcsv($out, ['Filter', $cat !== '' ? "Category = $cat" : 'None']);
fputcsv($out, ['Generated', date('F j, Y g:i A')]);
fputcsv($out, []);

fputcsv($out, ['=== SUMMARY ===']);
fputcsv($out, ['Total Donation Income',  number_format($totIncome,2), 'Entries', $incomeRow['n']]);
fputcsv($out, ['Total Donation Expenses',number_format($totExpense,2),'Entries', $expenseRow['n']]);
fputcsv($out, ['Net Donation Balance',   number_format($totIncome-$totExpense,2)]);
fputcsv($out, []);

fputcsv($out, ['=== INCOME BY TYPE ===']);
fputcsv($out, ['Type','Entries','Amount']);
$r = $conn->query("
  SELECT d.donation_type, SUM(d.amount) t, COUNT(d.id) n
  FROM donation_income_entries d JOIN sundays s ON s.id=d.sunday_id
  WHERE YEAR(s.sunday_date) = $year $monthCond
  GROUP BY d.donation_type ORDER BY t DESC
");
while ($row = $r->fetch_assoc()) {
    fputcsv($out, [$row['donation_type'], $row['n'], number_format((float)$row['t'],2)]);
}
fputcsv($out, ['TOTAL', $incomeRow['n'], number_format($totIncome,2)]);
fputcsv($out, []);

fputcsv($out, ['=== EXPENSES BY CATEGORY ===']);
fputcsv($out, ['Category','Entries','Amount']);
$r = $conn->query("
  SELECT c.main_category, SUM(e.amount) t, COUNT(e.id) n
  FROM donation_expense_entries e
  JOIN sundays s ON s.id=e.sunday_id
  JOIN donation_categories c ON c.id=e.category_id
  WHERE YEAR(s.sunday_date) = $year $monthCond $catCond
  GROUP BY c.main_category ORDER BY t DESC
");
while ($row = $r->fetch_assoc()) {
    fputcsv($out, [$row['main_category'], $row['n'], number_format((float)$row['t'],2)]);
}
fputcsv($out, ['TOTAL', $expenseRow['n'], number_format($totExpense,2)]);
fputcsv($out, []);

fputcsv($out, ['=== FULL LEDGER ===']);
fputcsv($out, ['Date','Kind','Type/Category','Donor','Description','Amount']);
$r = $conn->query("
  SELECT 'INCOME' kind, s.sunday_date, d.donation_type cat, d.donor, d.description, d.amount
  FROM donation_income_entries d JOIN sundays s ON s.id=d.sunday_id
  WHERE YEAR(s.sunday_date) = $year $monthCond
  UNION ALL
  SELECT 'EXPENSE', s.sunday_date, c.main_category, NULL, e.description, e.amount
  FROM donation_expense_entries e
  JOIN sundays s ON s.id=e.sunday_id
  JOIN donation_categories c ON c.id=e.category_id
  WHERE YEAR(s.sunday_date) = $year $monthCond $catCond
  ORDER BY sunday_date DESC
");
while ($row = $r->fetch_assoc()) {
    fputcsv($out, [
        $row['sunday_date'], $row['kind'], $row['cat'],
        $row['donor'] ?? '', $row['description'] ?? '',
        number_format((float)$row['amount'],2)
    ]);
}

fclose($out);
exit;