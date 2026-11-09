<?php
include 'connection.php';
include 'auth.php';
auth_require_admin();

$error = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$row = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['income_id'] ?? 0);
    if (!auth_csrf_validate($_POST['csrf_token'] ?? null)) {
        header('Location: income.php?error=' . urlencode('Invalid request.'));
        exit;
    }

    $admin_password = (string)($_POST['admin_password'] ?? '');
    $sunday_date = $_POST['sunday_date'] ?? '';
    $income_type = $_POST['income_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);

    if ($id <= 0 || $sunday_date === '' || $income_type === '') {
        $error = 'Missing fields.';
    } elseif ($admin_password === '' || !auth_verify_current_password($conn, $admin_password)) {
        $error = 'Incorrect password. Enter your account password to save changes.';
    } else {
        $stmt = $conn->prepare('INSERT IGNORE INTO sundays (sunday_date) VALUES (?)');
        $stmt->bind_param('s', $sunday_date);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('SELECT id FROM sundays WHERE sunday_date = ?');
        $stmt->bind_param('s', $sunday_date);
        $stmt->execute();
        $sunday = db_stmt_fetch_one_assoc($stmt);
        $stmt->close();
        $sunday_id = (int)$sunday['id'];

        $stmt = $conn->prepare('UPDATE income_entries SET sunday_id=?, income_type=?, description=?, amount=? WHERE id=?');
        $stmt->bind_param('issdi', $sunday_id, $income_type, $description, $amount, $id);

        if ($stmt->execute()) {
            $stmt->close();
            header('Location: income.php?updated=1&last_date=' . urlencode($sunday_date));
            exit;
        }
        $error = $stmt->error;
        $stmt->close();
    }

    if ($error !== '' && $id > 0) {
        $row = [
            'id' => $id,
            'income_type' => (string)($_POST['income_type'] ?? ''),
            'description' => (string)($_POST['description'] ?? ''),
            'amount' => $_POST['amount'] ?? 0,
            'sunday_date' => (string)($_POST['sunday_date'] ?? ''),
        ];
    }
}

if ($row === null && $id > 0) {
    $stmt = $conn->prepare('SELECT i.id, i.income_type, i.description, i.amount, s.sunday_date
        FROM income_entries i JOIN sundays s ON s.id = i.sunday_id WHERE i.id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();
}

if (!$row || $id <= 0) {
    header('Location: income.php?error=' . urlencode('Income not found.'));
    exit;
}

include 'header.php';
?>
<h3 class="mb-4 text-center">Edit Income</h3>

<?php if ($error !== ''): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<p class="text-muted small mb-3">Enter your <strong>account password</strong> to confirm this edit. Only admins can change passwords from Profile → Change Password.</p>

<form method="POST" action="edit_income.php" class="row g-3 mb-4">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
  <input type="hidden" name="income_id" value="<?= (int)$row['id'] ?>">
  <div class="col-md-3">
    <label>Sunday Date</label>
    <input type="date" name="sunday_date" class="form-control" required value="<?= htmlspecialchars((string)$row['sunday_date']) ?>">
  </div>
  <div class="col-md-3">
    <label>Type</label>
    <select name="income_type" class="form-control">
      <?php
      foreach (['Tithes', 'Donations'] as $t) {
          $sel = ($row['income_type'] === $t) ? ' selected' : '';
          echo '<option value="' . htmlspecialchars($t) . '"' . $sel . '>' . htmlspecialchars($t) . '</option>';
      }
      ?>
    </select>
  </div>
  <div class="col-md-3">
    <label>Description</label>
    <input type="text" name="description" class="form-control" value="<?= htmlspecialchars((string)$row['description']) ?>">
  </div>
  <div class="col-md-2">
    <label>Amount</label>
    <input type="number" step="0.01" name="amount" class="form-control" required value="<?= htmlspecialchars((string)$row['amount']) ?>">
  </div>
  <div class="col-12 col-md-6">
    <label>Confirm password</label>
    <input type="password" name="admin_password" class="form-control" required autocomplete="current-password" placeholder="Your login password">
  </div>
  <div class="col-12">
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="income.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>

<?php include 'footer.php'; ?>
