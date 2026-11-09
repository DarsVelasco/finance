<?php
// ==================== FILE: download_template.php ====================
include 'auth.php';
auth_require_admin();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=jclf_import_template.csv');

$output = fopen('php://output', 'w');

// Headers - exactly matching your Excel format
$headers = [
    'Date', 'Week', 'Beginning Balance', 'Collection', 'Total Available',
    'PASTORAL  STAFF', 'LG Volunteer', 'Children’s Ministry', 'Worship Ministry',
    'Fellowship Ministry', 'Ministry of Ministries', 'Discipleship Ministry',
    'MINISTRY OF EVANGELISM', 'Adult Ministry', 'Tech Support', 'Rental',
    'Water Bill', 'Electric Bill', 'TV', 'Other Expenses', 'Total Expenses', 'Ending Balance'
];
fputcsv($output, $headers);

// Sample row 1
fputcsv($output, [
    '05/05/2024', '1', '430.57', '6070.00', '6500.57',
    '1000.00', '', '350.00', '300.00', '', '300.00', '',
    '', '', '', '126.00', '', '', '', '', '2076.00', '4424.57'
]);

// Sample row 2
fputcsv($output, [
    '05/12/2024', '2', '4424.57', '4710.00', '9134.57',
    '1000.00', '', '416.00', '300.00', '256.60', '436.00', '1435.00',
    '', '1666.00', '500.50', '4000.00', '500.00', '2410.00', '', '136.00', '13056.10', '-3921.53'
]);

fclose($output);
exit();
?>