<?php
declare(strict_types=1);

// One-time (safe) installer to ensure `users` table exists and has ONLY the admin account.
// Viewer accounts are created via register.php (no built-in viewer).
// This runs from login/register; it is idempotent.

function auth_install_if_needed(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','viewer') NOT NULL DEFAULT 'viewer',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Ensure admin exists. Do not create any viewer accounts here.
    $adminUsername = 'JCLF';
    $adminPassword = 'JCLF2026.';

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $adminUsername);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($existing) {
        return;
    }

    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $role = 'admin';
    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('sss', $adminUsername, $hash, $role);
    $stmt->execute();
    $stmt->close();
}

