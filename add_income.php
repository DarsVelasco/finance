<?php
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sunday_date = $_POST['sunday_date'];
    $income_type = $_POST['income_type'];
    $description = $_POST['description'] ?? '';
    $amount = floatval($_POST['amount']);
    
    $stmt = $conn->prepare("INSERT IGNORE INTO sundays (sunday_date) VALUES (?)");
    $stmt->bind_param("s", $sunday_date);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT id FROM sundays WHERE sunday_date = ?");
    $stmt->bind_param("s", $sunday_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $sunday_id = $result->fetch_assoc()['id'];
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO income_entries (sunday_id, income_type, description, amount) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issd", $sunday_id, $income_type, $description, $amount);
    
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: income.php?success=1&last_date=" . urlencode($sunday_date));
        exit();
    } else {
        $error = $stmt->error;
        $stmt->close();
        header("Location: income.php?error=" . urlencode($error) . "&last_date=" . urlencode($sunday_date));
        exit();
    }
}

header("Location: income.php");
