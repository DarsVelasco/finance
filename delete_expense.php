<?php
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['expense_id'])) {
    $expense_id = intval($_POST['expense_id']);
    
    // Delete the expense entry
    $stmt = $conn->prepare("DELETE FROM expense_entries WHERE id = ?");
    $stmt->bind_param("i", $expense_id);
    
    if ($stmt->execute()) {
        // Preserve filter parameters
        $params = [];
        if (isset($_POST['filter_category']) && $_POST['filter_category'] != '') {
            $params[] = 'filter_category=' . urlencode($_POST['filter_category']);
        }
        if (isset($_POST['filter_date']) && $_POST['filter_date'] != '') {
            $params[] = 'filter_date=' . urlencode($_POST['filter_date']);
        }
        $query_string = !empty($params) ? '?' . implode('&', $params) . '&deleted=1' : '?deleted=1';
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

