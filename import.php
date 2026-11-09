<?php
// ==================== FILE: import.php (BASED ON COLUMN POSITIONS) ====================
include 'connection.php';
include 'auth.php';
auth_require_admin();

$error = '';
$success = '';
$preview_data = [];
$show_preview = false;

// Helper function to parse numbers from CSV
function parse_number($value) {
    $clean = trim($value);
    $clean = trim($clean, '"');
    $clean = str_replace(',', '', $clean);
    
    if ($clean === '' || $clean === '-' || $clean === '--') {
        return 0.00;
    }
    return floatval($clean);
}

// Handle file upload and preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    if (!auth_csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request.';
    } else {
        $file = $_FILES['import_file'];
        $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'File upload failed.';
        } elseif ($file_type !== 'csv') {
            $error = 'Please save your Excel file as CSV first.';
        } else {
            $upload_dir = __DIR__ . '/uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $temp_file = $upload_dir . 'import_' . time() . '.csv';
            
            if (move_uploaded_file($file['tmp_name'], $temp_file)) {
                $data = parse_csv_file($temp_file);
                
                if (!empty($data)) {
                    $preview_data = $data;
                    $show_preview = true;
                    $_SESSION['import_file_path'] = $temp_file;
                } else {
                    $error = 'No valid data found.';
                    @unlink($temp_file);
                }
            } else {
                $error = 'Failed to save file.';
            }
        }
    }
}

// Handle confirm import
if (isset($_POST['confirm_import']) && isset($_SESSION['import_file_path'])) {
    if (!auth_csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request.';
    } else {
        $file_path = $_SESSION['import_file_path'];
        
        if (file_exists($file_path)) {
            $data = parse_csv_file($file_path);
            
            if (!empty($data)) {
                $result = import_to_database($data, $conn);
                if ($result['success']) {
                    $success = $result['message'];
                    $show_preview = false;
                    @unlink($file_path);
                    unset($_SESSION['import_file_path']);
                } else {
                    $error = $result['message'];
                }
            } else {
                $error = 'No data to import.';
            }
        } else {
            $error = 'File not found.';
            unset($_SESSION['import_file_path']);
        }
    }
}

function parse_csv_file($filepath) {
    $rows = [];
    $line_num = 0;
    $found_headers = false;
    $header_indices = [];
    
    // Define column indices based on your CSV structure
    // Based on: Date,Week,Beginning Balance,Collection,Total Available,PASTORAL STAFF,...
    $column_mapping = [
        'Date' => 0,
        'Week' => 1,
        'Beginning Balance' => 2,
        'Collection' => 3,
        'Total Available' => 4,
        'PASTORAL STAFF' => 5,
        'LG Volunteer' => 6,
        'Childrens Ministry' => 7,
        'Worship Ministry' => 8,
        'Fellowship Ministry' => 9,
        'Ministry of Ministries' => 10,
        'Discipleship Ministry' => 11,
        'MINISTRY OF EVANGELISM' => 12,
        'Adult Ministry' => 13,
        'Tech Support' => 14,
        'Rental' => 15,
        'Water Bill' => 16,
        'Electric Bill' => 17,
        'TV' => 18,
        'Other Expenses' => 19,
        'Total Expenses' => 20,
        'Ending Balance' => 21
    ];
    
    if (($handle = fopen($filepath, 'r')) !== false) {
        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $line_num++;
            
            // Clean each cell
            $clean_row = [];
            foreach ($data as $cell) {
                $clean = trim($cell);
                $clean = str_replace("\xEF\xBB\xBF", '', $clean);
                $clean = trim($clean, '"');
                $clean_row[] = $clean;
            }
            
            // Skip the title row
            if ($line_num == 1 && isset($clean_row[0]) && (empty($clean_row[0]) || strpos($clean_row[0], 'JESUS CHRIST') !== false)) {
                continue;
            }
            
            // Find header row (where column 0 is 'Date')
            if (!$found_headers && isset($clean_row[0]) && $clean_row[0] === 'Date') {
                // Map header names to indices
                foreach ($clean_row as $idx => $header) {
                    $header_indices[$header] = $idx;
                }
                $found_headers = true;
                continue;
            }
            
            // Process data rows
            if ($found_headers && isset($clean_row[0]) && !empty($clean_row[0])) {
                $first_cell = $clean_row[0];
                
                // Check if it's a date
                if (!preg_match('/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/', $first_cell)) {
                    continue;
                }
                
                // Create row using indices
                $row = [];
                foreach ($column_mapping as $field_name => $index) {
                    // Find actual column index based on header
                    $actual_index = isset($header_indices[$field_name]) ? $header_indices[$field_name] : $index;
                    $value = isset($clean_row[$actual_index]) ? $clean_row[$actual_index] : '0';
                    $row[$field_name] = $value;
                }
                
                $rows[] = $row;
            }
        }
        fclose($handle);
    }
    
    return $rows;
}

function import_to_database($data, $conn) {
    $imported = 0;
    $errors = [];
    $sample_log = [];
    
    $conn->begin_transaction();
    
    try {
        // Get category IDs
        $category_ids = [];
        $cat_result = $conn->query("SELECT id, main_category FROM expense_categories");
        while ($cat = $cat_result->fetch_assoc()) {
            $category_ids[$cat['main_category']] = $cat['id'];
        }
        
        foreach ($data as $index => $row) {
            // Parse date
            $date_str = trim($row['Date'] ?? '');
            if (empty($date_str)) {
                $errors[] = "Row " . ($index + 1) . ": Empty date";
                continue;
            }
            
            // Convert date (m/d/Y format)
            $date_parts = explode('/', $date_str);
            if (count($date_parts) == 3) {
                $month = str_pad($date_parts[0], 2, '0', STR_PAD_LEFT);
                $day = str_pad($date_parts[1], 2, '0', STR_PAD_LEFT);
                $year = $date_parts[2];
                $sunday_date = "$year-$month-$day";
            } else {
                $timestamp = strtotime($date_str);
                if (!$timestamp) {
                    $errors[] = "Row " . ($index + 1) . ": Invalid date - $date_str";
                    continue;
                }
                $sunday_date = date('Y-m-d', $timestamp);
            }
            
            // Get Collection amount
            $collection = parse_number($row['Collection'] ?? '0');
            
            // Debug first 3 rows
            if ($imported < 3) {
                $sample_log[] = "Row " . ($index + 1) . ": Date=$sunday_date, Collection=" . number_format($collection, 2);
            }
            
            // Get or create Sunday record
            $stmt = $conn->prepare("INSERT IGNORE INTO sundays (sunday_date) VALUES (?)");
            $stmt->bind_param("s", $sunday_date);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("SELECT id FROM sundays WHERE sunday_date = ?");
            $stmt->bind_param("s", $sunday_date);
            $stmt->execute();
            $sunday = db_stmt_fetch_one_assoc($stmt);
            $stmt->close();
            $sunday_id = (int)($sunday['id'] ?? 0);
            
            if ($sunday_id <= 0) {
                $errors[] = "Row " . ($index + 1) . ": Could not save date";
                continue;
            }
            
            // Insert Income
            if ($collection > 0) {
                $income_desc = 'Sunday Collection - ' . date('F j, Y', strtotime($sunday_date));
                $stmt = $conn->prepare("INSERT INTO income_entries (sunday_id, income_type, description, amount) VALUES (?, 'Sunday Collection', ?, ?)");
                $stmt->bind_param("isd", $sunday_id, $income_desc, $collection);
                $stmt->execute();
                $stmt->close();
            }
            
            // Map expense columns to categories
            $expense_mapping = [
                'PASTORAL STAFF' => 'MANAGEMENT',
                'LG Volunteer' => 'ADMINISTRATION',
                'Childrens Ministry' => 'DISCIPLESHIP',
                'Worship Ministry' => 'WORSHIP',
                'Fellowship Ministry' => 'FELLOWSHIP',
                'Ministry of Ministries' => 'MINISTRY OF MINISTRIES',
                'Discipleship Ministry' => 'DISCIPLESHIP',
                'MINISTRY OF EVANGELISM' => 'EVANGELISM',
                'Adult Ministry' => 'ADMINISTRATION',
                'Tech Support' => 'ADMINISTRATION',
                'Rental' => 'ADMINISTRATION',
                'Water Bill' => 'ADMINISTRATION',
                'Electric Bill' => 'ADMINISTRATION',
                'TV' => 'ADMINISTRATION',
                'Other Expenses' => 'ADMINISTRATION'
            ];
            
            // Insert Expenses
            foreach ($expense_mapping as $column_name => $category) {
                $raw_value = $row[$column_name] ?? '0';
                $amount = parse_number($raw_value);
                
                if ($amount > 0) {
                    $category_id = $category_ids[$category] ?? 0;
                    if ($category_id > 0) {
                        $desc = $column_name;
                        $stmt = $conn->prepare("INSERT INTO expense_entries (sunday_id, category_id, description, amount) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("iisd", $sunday_id, $category_id, $desc, $amount);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            
            $imported++;
        }
        
        $conn->commit();
        
        $message = "✅ Successfully imported $imported weeks of financial data!";
        if (!empty($sample_log)) {
            $message .= "<br><small class='text-muted'>Sample: " . implode(' | ', $sample_log) . "</small>";
        }
        if (!empty($errors)) {
            $message .= "<br>⚠️ " . implode(', ', array_slice($errors, 0, 3));
        }
        return ['success' => true, 'message' => $message];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="mb-3">📥 Import Financial Data (JCLF 2024-2025)</h4>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <?php if (!$show_preview): ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Upload CSV File:</label>
                        <input type="file" name="import_file" class="form-control" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Preview & Import</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </form>
                
                <?php else: ?>
                
                <div class="alert alert-success">
                    <strong><?= count($preview_data) ?> records found!</strong>
                </div>
                
                <?php if (!empty($preview_data)): ?>
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-striped">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Collection (₱)</th>
                                <th>Pastoral Staff (₱)</th>
                                <th>Childrens Ministry (₱)</th>
                                <th>Worship (₱)</th>
                                <th>Fellowship (₱)</th>
                                <th>Ministry of Ministries (₱)</th>
                                <th>Rental (₱)</th>
                                <th>Other (₱)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $display_limit = min(15, count($preview_data));
                            for ($idx = 0; $idx < $display_limit; $idx++): 
                                $row = $preview_data[$idx];
                            ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><?= htmlspecialchars($row['Date'] ?? '') ?></td>
                                <td class="text-end"><?= number_format(parse_number($row['Collection'] ?? 0), 2) ?></td>
                                <td class="text-end"><?= number_format(parse_number($row['PASTORAL STAFF'] ?? 0), 2) ?></td>
                                <td class="text-end"><?= number_format(parse_number($row['Childrens Ministry'] ?? 0), 2) ?></td>
                                <td class="text-end"><?= number_format(parse_number($row['Worship Ministry'] ?? 0), 2) ?></td>
                                <td class="text-end"><?= number_format(parse_number($row['Fellowship Ministry'] ?? 0), 2) ?></td>
                                <td class="text-end"><?= number_format(parse_number($row['Ministry of Ministries'] ?? 0), 2) ?></td>
                                <td class="text-end"><?= number_format(parse_number($row['Rental'] ?? 0), 2) ?></td>
                                <td class="text-end"><?= number_format(parse_number($row['Other Expenses'] ?? 0), 2) ?></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="mt-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                    <input type="hidden" name="confirm_import" value="1">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Import all <?= count($preview_data) ?> records?');">
                        ✅ Confirm Import
                    </button>
                    <a href="import.php" class="btn btn-secondary">Cancel</a>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>