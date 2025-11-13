<?php
include 'connection.php';

// Get filters
$month = isset($_GET['month']) ? intval($_GET['month']) : '';
$year = isset($_GET['year']) ? intval($_GET['year']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=JCLF_Finance_Report_' . date('Y-m-d_H-i-s') . '.csv');

// Open output stream
$output = fopen('php://output', 'w');

// Write header row
fputcsv($output, ['Date', 'Month', 'Type', 'Category', 'Description', 'Amount']);

// ----------------------------
// INCOME DATA
// ----------------------------
$query_income = "
    SELECT 
        s.sunday_date AS date,
        MONTHNAME(s.sunday_date) AS month,
        'INCOME' AS type,
        i.income_type AS category,
        i.description,
        i.amount
    FROM income_entries i
    INNER JOIN sundays s ON i.sunday_id = s.id
    WHERE 1=1
";

// Apply filters
if ($month) $query_income .= " AND MONTH(s.sunday_date) = $month";
if ($year) $query_income .= " AND YEAR(s.sunday_date) = $year";
if (!empty($category)) $query_income .= " AND i.income_type = '" . mysqli_real_escape_string($conn, $category) . "'";

// ----------------------------
// EXPENSE DATA
// ----------------------------
$query_expense = "
    SELECT 
        s.sunday_date AS date,
        MONTHNAME(s.sunday_date) AS month,
        'EXPENSE' AS type,
        c.main_category AS category,
        e.description,
        e.amount
    FROM expense_entries e
    INNER JOIN sundays s ON e.sunday_id = s.id
    INNER JOIN expense_categories c ON e.category_id = c.id
    WHERE 1=1
";

// Apply filters
if ($month) $query_expense .= " AND MONTH(s.sunday_date) = $month";
if ($year) $query_expense .= " AND YEAR(s.sunday_date) = $year";
if (!empty($category)) $query_expense .= " AND c.main_category = '" . mysqli_real_escape_string($conn, $category) . "'";

// Combine both (income + expense)
$final_query = "($query_income) UNION ALL ($query_expense) ORDER BY date DESC";
$result = mysqli_query($conn, $final_query);

// Write each row to CSV
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['date'],
        $row['month'],
        $row['type'],
        $row['category'],
        $row['description'],
        number_format($row['amount'], 2)
    ]);
}

// ----------------------------
// TOTALS SUMMARY
// ----------------------------
$income_total_query = "
    SELECT SUM(i.amount) AS total_income
    FROM income_entries i
    INNER JOIN sundays s ON i.sunday_id = s.id
    WHERE 1=1
    " . ($month ? " AND MONTH(s.sunday_date) = $month" : "") .
      ($year ? " AND YEAR(s.sunday_date) = $year" : "") .
      (!empty($category) ? " AND i.income_type = '" . mysqli_real_escape_string($conn, $category) . "'" : "");

$expense_total_query = "
    SELECT SUM(e.amount) AS total_expense
    FROM expense_entries e
    INNER JOIN sundays s ON e.sunday_id = s.id
    INNER JOIN expense_categories c ON e.category_id = c.id
    WHERE 1=1
    " . ($month ? " AND MONTH(s.sunday_date) = $month" : "") .
      ($year ? " AND YEAR(s.sunday_date) = $year" : "") .
      (!empty($category) ? " AND c.main_category = '" . mysqli_real_escape_string($conn, $category) . "'" : "");

$total_income = $conn->query($income_total_query)->fetch_assoc()['total_income'] ?? 0;
$total_expense = $conn->query($expense_total_query)->fetch_assoc()['total_expense'] ?? 0;
$net_balance = $total_income - $total_expense;

// Blank line + summary
fputcsv($output, []);
fputcsv($output, ['SUMMARY']);
fputcsv($output, ['Total Income', number_format($total_income, 2)]);
fputcsv($output, ['Total Expenses', number_format($total_expense, 2)]);
fputcsv($output, ['Net Balance', number_format($net_balance, 2)]);

fclose($output);
exit();
?>
