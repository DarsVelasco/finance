<?php
include 'connection.php';
include 'auth.php';
auth_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $pw = (string)($_POST['admin_password'] ?? '');

    // Verify password
    if ($pw === '' || !auth_verify_current_password($conn, $pw)) {
        header("Location: donations.php?error=" . urlencode('Incorrect password. Expense NOT deleted.'));
        exit;
    }

    // Delete
    $stmt = $conn->prepare("DELETE FROM donation_expense_entries WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: donations.php?deleted=1');
    exit;
}

header('Location: donations.php');