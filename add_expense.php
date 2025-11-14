<?php
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sunday_date = $_POST['sunday_date'];
    $category_id = intval($_POST['category_id']);
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
    
    // Insert expense entry
    $stmt = $conn->prepare("INSERT INTO expense_entries (sunday_id, category_id, description, amount) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisd", $sunday_id, $category_id, $description, $amount);
    
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: expense.php?success=1&last_date=" . urlencode($sunday_date));
        exit();
    } else {
        $error = $stmt->error;
        $stmt->close();
        header("Location: expense.php?error=" . urlencode($error) . "&last_date=" . urlencode($sunday_date));
        exit();
    }
}

header("Location: expense.php");
