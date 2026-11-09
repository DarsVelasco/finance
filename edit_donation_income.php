<?php
include 'connection.php';
include 'auth.php';
auth_require_admin();

$error = '';
$id = (int)($_GET['id'] ?? 0);
$row = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (!auth_csrf_validate($_POST['csrf_token'] ?? null)) {
        header('Location: donations.php?error=' . urlencode('Invalid request.')); exit;
    }
    
    $pw = (string)($_POST['admin_password'] ?? '');
    $sunday_date   = $_POST['sunday_date'] ?? '';
    $donation_type = trim((string)($_POST['donation_type'] ?? ''));
    $donor         = trim((string)($_POST['donor'] ?? ''));
    $description   = trim((string)($_POST['description'] ?? ''));
    $amount        = floatval($_POST['amount'] ?? 0);

    if ($id <= 0 || $sunday_date === '' || $donation_type === '' || $amount <= 0) {
        $error = 'Missing fields.';
    } elseif ($pw === '' || !auth_verify_current_password($conn, $pw)) {
        $error = 'Incorrect password. You must enter your account password to save changes.';
    } else {
        $stmt = $conn->prepare("INSERT IGNORE INTO sundays (sunday_date) VALUES (?)");
        $stmt->bind_param("s", $sunday_date);
        $stmt->execute(); $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM sundays WHERE sunday_date = ?");
        $stmt->bind_param("s", $sunday_date);
        $stmt->execute();
        $sunday = db_stmt_fetch_one_assoc($stmt);
        $stmt->close();
        $sunday_id = (int)($sunday['id'] ?? 0);

        $stmt = $conn->prepare("
            UPDATE donation_income_entries
            SET sunday_id=?, donation_type=?, donor=?, description=?, amount=?
            WHERE id=?
        ");
        $stmt->bind_param("isssdi", $sunday_id, $donation_type, $donor, $description, $amount, $id);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: donations.php?success=1'); exit;
        }
        $error = $stmt->error; $stmt->close();
    }

    if ($error !== '') {
        $row = [
            'id' => $id,
            'sunday_date' => $sunday_date,
            'donation_type' => $donation_type,
            'donor' => $donor,
            'description' => $description,
            'amount' => $amount,
        ];
    }
}

if ($row === null && $id > 0) {
    $stmt = $conn->prepare("
        SELECT d.id, d.donation_type, d.donor, d.description, d.amount, s.sunday_date
        FROM donation_income_entries d
        JOIN sundays s ON s.id = d.sunday_id
        WHERE d.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();
}

if (!$row) {
    header('Location: donations.php?error=' . urlencode('Donation income not found.'));
    exit;
}

include 'header.php';
?>
<h3 class="mb-4 text-center">Edit Donation Income</h3>
<p class="text-center text-muted small mb-3">Enter your account password to confirm changes.</p>

<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">
    <?php if ($error !== ''): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm p-4">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
      
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-bold small">Sunday Date</label>
          <input type="date" name="sunday_date" class="form-control" required value="<?= htmlspecialchars((string)$row['sunday_date']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold small">Amount (₱)</label>
          <input type="number" step="0.01" name="amount" class="form-control" required value="<?= htmlspecialchars((string)$row['amount']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-bold small">Donation Type</label>
          <input type="text" name="donation_type" class="form-control" required value="<?= htmlspecialchars((string)$row['donation_type']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold small">Donor Name (Optional)</label>
          <input type="text" name="donor" class="form-control" value="<?= htmlspecialchars((string)($row['donor'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold small">Description (Optional)</label>
          <input type="text" name="description" class="form-control" value="<?= htmlspecialchars((string)($row['description'] ?? '')) ?>">
        </div>
        
        <div class="col-12 mt-4">
          <div class="alert alert-warning py-2">
            <label class="form-label fw-bold small mb-1">Confirm Password</label>
            <input type="password" name="admin_password" class="form-control" required autocomplete="current-password" placeholder="Enter your login password">
          </div>
        </div>
      </div>
      
      <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary w-50 fw-bold">Save Changes</button>
        <a href="donations.php" class="btn btn-secondary w-50">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>