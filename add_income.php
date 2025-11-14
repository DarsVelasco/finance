<?php
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sunday_date = $_POST['sunday_date'];
    $income_type = $_POST['income_type'];
    $description = $_POST['description'] ?? '';
    $amount = floatval($_POST['amount']);
    
    // Ensure the sunday exists
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
    
    // Insert income entry
    $stmt = $conn->prepare("INSERT INTO income_entries (sunday_id, income_type, description, amount) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issd", $sunday_id, $income_type, $description, $amount);
    
    if ($stmt->execute()) {
        $stmt->close();
        // Pass last_date to keep it after submission
        $params = ['last_date=' . urlencode($sunday_date), 'success=1'];
        if (isset($_POST['filter_date']) && $_POST['filter_date'] != '') {
            $params[] = 'filter_date=' . urlencode($_POST['filter_date']);
        }
        header("Location: income.php?" . implode('&', $params));
        exit();
    } else {
        $error = $stmt->error;
        $stmt->close();
        $params = ['last_date=' . urlencode($sunday_date), 'error=' . urlencode($error)];
        if (isset($_POST['filter_date']) && $_POST['filter_date'] != '') {
            $params[] = 'filter_date=' . urlencode($_POST['filter_date']);
        }
        header("Location: income.php?" . implode('&', $params));
        exit();
    }
}

header("Location: income.php");
?>
