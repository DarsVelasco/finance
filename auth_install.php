<?php
declare(strict_types=1);

/**
 * Ensures `users` table exists and seeds the JCLF admin if missing.
 * Default password: JCLF2026 (change it after first login via Change Password).
 * Include from login.php / register.php once.
 */
function auth_install_if_needed($conn): void
{
    if (!$conn instanceof mysqli) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(190) DEFAULT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','viewer') NOT NULL DEFAULT 'viewer',
            is_email_verified TINYINT(1) NOT NULL DEFAULT 0,
            email_verification_token_hash VARCHAR(64) DEFAULT NULL,
            email_verification_expires_at DATETIME DEFAULT NULL,
            password_reset_token_hash VARCHAR(64) DEFAULT NULL,
            password_reset_expires_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $cols = [];
    $resCols = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='users'");
    if ($resCols) {
        while ($r = $resCols->fetch_assoc()) {
            $cols[(string)$r['COLUMN_NAME']] = true;
        }
    }
    $add = function (string $sql) use ($conn): void { $conn->query($sql); };
    if (!isset($cols['email'])) {
        $add("ALTER TABLE users ADD COLUMN email VARCHAR(190) DEFAULT NULL UNIQUE");
    }
    if (!isset($cols['is_email_verified'])) {
        $add("ALTER TABLE users ADD COLUMN is_email_verified TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!isset($cols['email_verification_token_hash'])) {
        $add("ALTER TABLE users ADD COLUMN email_verification_token_hash VARCHAR(64) DEFAULT NULL");
    }
    if (!isset($cols['email_verification_expires_at'])) {
        $add("ALTER TABLE users ADD COLUMN email_verification_expires_at DATETIME DEFAULT NULL");
    }
    if (!isset($cols['password_reset_token_hash'])) {
        $add("ALTER TABLE users ADD COLUMN password_reset_token_hash VARCHAR(64) DEFAULT NULL");
    }
    if (!isset($cols['password_reset_expires_at'])) {
        $add("ALTER TABLE users ADD COLUMN password_reset_expires_at DATETIME DEFAULT NULL");
    }

    $adminUsername = 'JCLF';
    $adminPassword = 'JCLF2026';

    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $adminUsername);
    $stmt->execute();
    $existing = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    if ($existing) {
        return;
    }

    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $role = 'admin';
    $stmt = $conn->prepare('INSERT INTO users (username, password_hash, role, is_email_verified) VALUES (?, ?, ?, 1)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('sss', $adminUsername, $hash, $role);
    $stmt->execute();
    $stmt->close();
}
