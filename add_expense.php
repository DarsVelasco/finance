<?php
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sunday_date = $_POST['sunday_date'];
    $category_id = intval($_POST['category_id']);
    $description = $_POST['description'] ?? '';
    $amount = floatval($_POST['amount']);
    
    // First, ensure the sunday exists
    $stmt = $conn->prepare("INSERT IGNORE INTO sundays (sunday_date) VALUES (?)");
    $stmt->bind_param("s", $sunday_date);
    $stmt->execute();
    $stmt->close();
    
    // Get the sunday_id
    $stmt = $conn->prepare("SELECT id FROM sundays WHERE sunday_date = ?");
    $stmt->bind_param("s", $sunday_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $sunday = $result->fetch_assoc();
    $sunday_id = $sunday['id'];
    $stmt->close();
    
    // Insert expense entry
    $stmt = $conn->prepare("INSERT INTO expense_entries (sunday_id, category_id, description, amount) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisd", $sunday_id, $category_id, $description, $amount);
    
    if ($stmt->execute()) {
        // Preserve filter parameters
        $params = [];
        if (isset($_POST['filter_category']) && $_POST['filter_category'] != '') {
            $params[] = 'filter_category=' . urlencode($_POST['filter_category']);
        }
        if (isset($_POST['filter_date']) && $_POST['filter_date'] != '') {
            $params[] = 'filter_date=' . urlencode($_POST['filter_date']);
        }
        $query_string = !empty($params) ? '?' . implode('&', $params) . '&success=1' : '?success=1';
        header("Location: expense.php" . $query_string);
    } else {
        // Preserve filter parameters on error too
        $params = [];
        if (isset($_POST['filter_category']) && $_POST['filter_category'] != '') {
            $params[] = 'filter_category=' . urlencode($_POST['filter_category']);
        }
        if (isset($_POST['filter_date']) && $_POST['filter_date'] != '') {
            $params[] = 'filter_date=' . urlencode($_POST['filter_date']);
        }
        $query_string = !empty($params) ? '?' . implode('&', $params) . '&error=' . urlencode($stmt->error) : '?error=' . urlencode($stmt->error);
        header("Location: expense.php" . $query_string);
    }
    $stmt->close();
    exit();
}

header("Location: expense.php");
?>

