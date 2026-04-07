<?php
declare(strict_types=1);

include 'connection.php';
include 'auth.php';

auth_require_admin();
$me = auth_user();

$error = '';
$success = '';

function count_admins(mysqli $conn): int
{
    $res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'");
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            $error = 'Invalid user.';
        } elseif ($userId === (int)$me['id']) {
            $error = 'You cannot modify your own admin account here.';
        } else {
            // Fetch target user
            $stmt = $conn->prepare('SELECT id, username, role FROM users WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            $target = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$target) {
                $error = 'User not found.';
            } else {
                $targetName = (string)$target['username'];
                $targetRole = (string)$target['role'];

                if ($action === 'promote_admin') {
                    $stmt = $conn->prepare("UPDATE users SET role='admin' WHERE id = ?");
                    $stmt->bind_param('i', $userId);
                    if ($stmt->execute()) {
                        $success = "Updated: $targetName is now admin.";
                    } else {
                        $error = 'Error: ' . $stmt->error;
                    }
                    $stmt->close();
                } elseif ($action === 'demote_viewer') {
                    if ($targetRole !== 'admin') {
                        $error = 'This user is not an admin.';
                    } elseif (count_admins($conn) <= 1) {
                        $error = 'You cannot demote the last admin.';
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET role='viewer' WHERE id = ?");
                        $stmt->bind_param('i', $userId);
                        if ($stmt->execute()) {
                            $success = "Updated: $targetName is now viewer.";
                        } else {
                            $error = 'Error: ' . $stmt->error;
                        }
                        $stmt->close();
                    }
                } elseif ($action === 'delete_user') {
                    if ($targetRole === 'admin') {
                        $error = 'Admin accounts cannot be deleted here.';
                    } else {
                        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
                        $stmt->bind_param('i', $userId);
                        if ($stmt->execute()) {
                            $success = "Deleted: $targetName.";
                        } else {
                            $error = 'Error: ' . $stmt->error;
                        }
                        $stmt->close();
                    }
                } else {
                    $error = 'Unknown action.';
                }
            }
        }
    }
}

// List users
$users = [];
$res = $conn->query('SELECT id, username, role, created_at FROM users ORDER BY role DESC, username ASC');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
}

include 'header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="mb-0">Manage Accounts</h4>
    <div class="text-muted small">Promote viewers to admin, or delete viewer accounts.</div>
  </div>
  <a class="btn btn-outline-secondary" href="profile.php">Back to Profile</a>
</div>

<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success !== ''): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Username</th>
            <th>Role</th>
            <th>Created</th>
            <th style="width: 260px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <?php
              $uid = (int)$u['id'];
              $role = (string)$u['role'];
              $isMe = $uid === (int)$me['id'];
            ?>
            <tr>
              <td>
                <?= htmlspecialchars((string)$u['username']) ?>
                <?php if ($isMe): ?>
                  <span class="badge bg-info text-dark ms-1">you</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $role === 'admin' ? 'bg-success' : 'bg-secondary' ?>">
                  <?= htmlspecialchars($role) ?>
                </span>
              </td>
              <td class="small text-muted"><?= htmlspecialchars((string)$u['created_at']) ?></td>
              <td>
                <?php if ($isMe): ?>
                  <span class="text-muted small">Use Profile to edit your account</span>
                <?php else: ?>
                  <div class="d-flex flex-wrap gap-2">
                    <?php if ($role === 'viewer'): ?>
                      <form method="POST" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                        <input type="hidden" name="action" value="promote_admin">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <button class="btn btn-sm btn-outline-success">Make Admin</button>
                      </form>
                      <form method="POST" class="m-0" onsubmit="return confirm('Delete this viewer account?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                      </form>
                    <?php else: ?>
                      <form method="POST" class="m-0" onsubmit="return confirm('Demote this admin to viewer?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                        <input type="hidden" name="action" value="demote_viewer">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <button class="btn btn-sm btn-outline-warning">Demote to Viewer</button>
                      </form>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($users) === 0): ?>
            <tr><td colspan="4" class="text-center text-muted">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>

