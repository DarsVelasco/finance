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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    if ($username === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in all fields.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = 'Username must be 3-20 characters (letters, numbers, underscore).';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'viewer'; // registrations are viewers by default

        $stmt = $conn->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
        if (!$stmt) {
            $error = 'Unable to create account right now.';
        } else {
            $stmt->bind_param('sss', $username, $hash, $role);
            if ($stmt->execute()) {
                $success = 'Account created. You can now login.';
            } else {
                if (str_contains(strtolower((string)$stmt->error), 'duplicate')) {
                    $error = 'Username already exists.';
                } else {
                    $error = 'Error: ' . $stmt->error;
                }
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - Finance Tracker</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style.css" rel="stylesheet">
</head>
<body class="bg-light">
  <main class="container py-5" style="max-width: 560px;">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2 mb-3">
          <img src="logo.png" alt="Logo" width="40" height="40">
          <div>
            <h4 class="mb-0">Create account</h4>
            <small class="text-muted">New accounts are <strong>viewer</strong> by default</small>
          </div>
        </div>

        <?php if ($error !== ''): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
          <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
            <div class="mt-2"><a href="login.php" class="btn btn-sm btn-outline-primary">Go to Login</a></div>
          </div>
        <?php endif; ?>

        <form method="POST" class="vstack gap-3">
          <div>
            <label class="form-label">Username</label>
            <input name="username" class="form-control" required>
            <div class="form-text">3-20 chars, letters/numbers/underscore only.</div>
          </div>

          <div>
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>

          <div>
            <label class="form-label">Confirm password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>

          <button class="btn btn-primary w-100">Register</button>
          <a class="btn btn-link" href="login.php">Already have an account? Login</a>
        </form>
      </div>
    </div>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

