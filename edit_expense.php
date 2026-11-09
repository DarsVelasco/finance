<?php
include 'connection.php';
include 'auth.php';
auth_require_admin();

$error = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$row = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['expense_id'] ?? 0);
    if (!auth_csrf_validate($_POST['csrf_token'] ?? null)) {
        header('Location: expense.php?error=' . urlencode('Invalid request.'));
        exit;
    }

    $admin_password = (string)($_POST['admin_password'] ?? '');
    $sunday_date = $_POST['sunday_date'] ?? '';
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = $_POST['description'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);

    if ($id <= 0 || $category_id <= 0 || $sunday_date === '') {
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

        $stmt = $conn->prepare('UPDATE expense_entries SET sunday_id=?, category_id=?, description=?, amount=? WHERE id=?');
        $stmt->bind_param('iisdi', $sunday_id, $category_id, $description, $amount, $id);

        if ($stmt->execute()) {
            $stmt->close();
            header('Location: expense.php?updated=1&last_date=' . urlencode($sunday_date));
            exit;
        }
        $error = $stmt->error;
        $stmt->close();
    }

    if ($error !== '' && $id > 0) {
        $row = [
            'id' => $id,
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'description' => (string)($_POST['description'] ?? ''),
            'amount' => $_POST['amount'] ?? 0,
            'sunday_date' => (string)($_POST['sunday_date'] ?? ''),
        ];
    }
}

if ($row === null && $id > 0) {
    $stmt = $conn->prepare('SELECT e.id, e.category_id, e.description, e.amount, s.sunday_date
        FROM expense_entries e JOIN sundays s ON s.id = e.sunday_id WHERE e.id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();
}

if (!$row || $id <= 0) {
    header('Location: expense.php?error=' . urlencode('Expense not found.'));
    exit;
}

include 'header.php';
$cats = $conn->query('SELECT id, main_category FROM expense_categories ORDER BY main_category ASC');
?>
<h3 class="mb-4 text-center">Edit Expense</h3>

<?php if ($error !== ''): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<p class="text-muted small mb-3">Enter your <strong>account password</strong> to confirm this edit. Only admins can change passwords from Profile → Change Password.</p>

<form method="POST" action="edit_expense.php" class="row g-3 mb-4">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
  <input type="hidden" name="expense_id" value="<?= (int)$row['id'] ?>">
  <div class="col-md-3">
    <label>Sunday Date</label>
    <input type="date" name="sunday_date" class="form-control" required value="<?= htmlspecialchars((string)$row['sunday_date']) ?>">
  </div>
  <div class="col-md-4">
    <label>Category</label>
    <select name="category_id" class="form-control" required>
      <?php
      while ($c = $cats->fetch_assoc()) {
          $sel = ((int)$c['id'] === (int)$row['category_id']) ? ' selected' : '';
          echo '<option value="' . (int)$c['id'] . '"' . $sel . '>' . htmlspecialchars($c['main_category']) . '</option>';
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
    <a href="expense.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>

<?php include 'footer.php'; ?>
