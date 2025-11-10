<?php
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['income_id'])) {
    $income_id = intval($_POST['income_id']);
    
    // Delete the income entry
    $stmt = $conn->prepare("DELETE FROM income_entries WHERE id = ?");
    $stmt->bind_param("i", $income_id);
    
    if ($stmt->execute()) {
        // Preserve filter parameters
        $params = [];
        if (isset($_POST['filter_date']) && $_POST['filter_date'] != '') {
            $params[] = 'filter_date=' . urlencode($_POST['filter_date']);
        }
        $query_string = !empty($params) ? '?' . implode('&', $params) . '&deleted=1' : '?deleted=1';
        header("Location: income.php" . $query_string);
    } else {
        // Preserve filter parameters on error too
        $params = [];
        if (isset($_POST['filter_date']) && $_POST['filter_date'] != '') {
            $params[] = 'filter_date=' . urlencode($_POST['filter_date']);
        }
        $query_string = !empty($params) ? '?' . implode('&', $params) . '&error=' . urlencode($stmt->error) : '?error=' . urlencode($stmt->error);
        header("Location: income.php" . $query_string);
    }
    $stmt->close();
    exit();
}

header("Location: income.php");
?>

