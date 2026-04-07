<?php
declare(strict_types=1);

// Session + authentication helpers.

function auth_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Safer defaults; "secure" depends on https, so leave false for local XAMPP.
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function auth_user(): ?array
{
    auth_start();
    if (!isset($_SESSION['user'])) {
        return null;
    }
    $u = $_SESSION['user'];
    if (!is_array($u) || !isset($u['id'], $u['username'], $u['role'])) {
        return null;
    }
    return $u;
}

function auth_is_logged_in(): bool
{
    return auth_user() !== null;
}

function auth_is_admin(): bool
{
    $u = auth_user();
    return $u !== null && ($u['role'] ?? '') === 'admin';
}

function auth_require_login(): void
{
    if (auth_is_logged_in()) {
        return;
    }
    $next = $_SERVER['REQUEST_URI'] ?? 'index.php';
    header('Location: login.php?next=' . urlencode($next));
    exit();
}

function auth_require_admin(): void
{
    auth_require_login();
    if (auth_is_admin()) {
        return;
    }
    // Viewers get redirected back with a friendly error.
    header('Location: index.php?error=' . urlencode('Viewer accounts cannot add/edit/delete.'));
    exit();
}

function auth_login(array $user): void
{
    auth_start();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'role' => (string)$user['role'],
    ];
}

function auth_logout(): void
{
    auth_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '');
    }
    session_destroy();
}

function auth_csrf_token(): string
{
    auth_start();
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function auth_csrf_validate(?string $token): bool
{
    auth_start();
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        return false;
    }
    if (!is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

