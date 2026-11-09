<?php
declare(strict_types=1);

include 'connection.php';
include 'auth.php';

auth_require_login();
$user = auth_user();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $newUsername = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($newUsername === '' || $password === '') {
            $error = 'Please fill in all fields.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $newUsername)) {
            $error = 'Username must be 3-20 characters (letters, numbers, underscore).';
        } else {
            // Verify password first
            $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
            if (!$stmt) {
                $error = 'Unable to validate password.';
            } else {
                $uid = (int)$user['id'];
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $row = db_stmt_fetch_one_assoc($stmt);
                $stmt->close();

                $hash = $row['password_hash'] ?? null;
                if (!$hash || !password_verify($password, (string)$hash)) {
                    $error = 'Password is incorrect.';
                } else {
                    $stmt = $conn->prepare('UPDATE users SET username = ? WHERE id = ?');
                    if (!$stmt) {
                        $error = 'Unable to update username.';
                    } else {
                        $stmt->bind_param('si', $newUsername, $uid);
                        if ($stmt->execute()) {
                            $success = 'Name updated successfully.';
                            // Update session so navbar shows the new name
                            $_SESSION['user']['username'] = $newUsername;
                            $user = auth_user();
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
        }
    }
}

include 'header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-7 col-lg-6">
    <div class="card shadow-sm mb-4">
      <div class="card-body p-4">
        <h4 class="mb-1">Profile</h4>
        <div class="text-muted small mb-3">
          Role: <strong><?= htmlspecialchars($user['role']) ?></strong>
        </div>

        <?php if ($error !== ''): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" class="vstack gap-3">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
          <div>
            <label class="form-label">Change username</label>
            <input name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
            <div class="form-text">3-20 chars, letters/numbers/underscore only.</div>
          </div>
          <div>
            <label class="form-label">Confirm your password</label>
            <input type="password" name="password" class="form-control" required autocomplete="current-password">
          </div>
          <button class="btn btn-primary">Save</button>
        </form>

        <?php if (($user['role'] ?? '') === 'admin'): ?>
          <hr>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="change_password.php">Change Password</a>
            <a class="btn btn-outline-primary" href="manage_users.php">Manage Viewer Accounts</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>

