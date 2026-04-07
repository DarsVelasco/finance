<?php
declare(strict_types=1);

include 'connection.php';
include 'auth.php';
include 'auth_install.php';

auth_start();
auth_install_if_needed($conn);

if (auth_is_logged_in()) {
    header('Location: index.php');
    exit();
}

$error = '';
$next = $_GET['next'] ?? ($_POST['next'] ?? 'index.php');
if (!is_string($next) || $next === '') {
    $next = 'index.php';
}
// Avoid open redirects: only allow relative paths.
if (str_starts_with($next, 'http://') || str_starts_with($next, 'https://') || str_starts_with($next, '//')) {
    $next = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $stmt = $conn->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if ($user && password_verify($password, (string)$user['password_hash'])) {
                auth_login($user);
                header('Location: ' . $next);
                exit();
            }
        }
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Finance Tracker</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style.css" rel="stylesheet">
</head>
<body class="bg-light">
  <main class="container py-5" style="max-width: 520px;">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2 mb-3">
          <img src="logo.png" alt="Logo" width="40" height="40">
          <div>
            <h4 class="mb-0">Finance Tracker</h4>
            <small class="text-muted">Sign in to continue</small>
          </div>
        </div>

        <?php if ($error !== ''): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="vstack gap-3">
          <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">

          <div>
            <label class="form-label">Username</label>
            <input name="username" class="form-control" autocomplete="username" required>
          </div>

          <div>
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" autocomplete="current-password" required>
          </div>

          <button class="btn btn-primary w-100">Login</button>
        </form>

        <hr>
        <div class="d-grid gap-2">
          <a class="btn btn-outline-secondary" href="register.php">Register (viewer)</a>
        </div>
      </div>
    </div>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

