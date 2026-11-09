<?php
declare(strict_types=1);

include 'connection.php';
include 'auth.php';

// Admin-only password change page
auth_require_admin();

$user = auth_user();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        $error = 'Please fill in all fields.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        if (!$stmt) {
            $error = 'Unable to validate current password.';
        } else {
            $uid = (int)$user['id'];
            $stmt->bind_param('i', $uid);
                $stmt->execute();
                $row = db_stmt_fetch_one_assoc($stmt);
                $stmt->close();

            $hash = $row['password_hash'] ?? null;
            if (!$hash || !password_verify($current, (string)$hash)) {
                $error = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                if (!$stmt) {
                    $error = 'Unable to update password.';
                } else {
                    $stmt->bind_param('si', $newHash, $uid);
                    if ($stmt->execute()) {
                        $success = 'Password updated successfully.';
                    } else {
                        $error = 'Error: ' . $stmt->error;
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
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-1">Change Password</h4>
        <div class="text-muted small mb-3">Admin account: <strong><?= htmlspecialchars($user['username']) ?></strong></div>

        <?php if ($error !== ''): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" class="vstack gap-3">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
          <div>
            <label class="form-label">Current password</label>
            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
          </div>
          <div>
            <label class="form-label">New password</label>
            <input type="password" name="new_password" class="form-control" required autocomplete="new-password">
          </div>
          <div>
            <label class="form-label">Confirm new password</label>
            <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password">
          </div>
          <button class="btn btn-primary">Update Password</button>
          <a href="index.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>

