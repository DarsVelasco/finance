<?php
include 'connection.php';
include 'auth.php';
auth_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sunday_date = $_POST['sunday_date'] ?? '';
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim((string)($_POST['description'] ?? ''));
    $amount      = floatval($_POST['amount'] ?? 0);

    if ($sunday_date === '' || $category_id <= 0 || $amount <= 0) {
        header("Location: donations.php?error=" . urlencode('Missing fields.'));
        exit;
    }

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
        header("Location: donations.php?error=" . urlencode('Could not resolve Sunday date.'));
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO donation_expense_entries (sunday_id, category_id, description, amount)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iisd", $sunday_id, $category_id, $description, $amount);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: donations.php?success=1");
        exit;
    }
    $err = $stmt->error;
    $stmt->close();
    header("Location: donations.php?error=" . urlencode($err));
    exit;
}

header("Location: donations.php");