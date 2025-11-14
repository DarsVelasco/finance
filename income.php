<?php include 'connection.php'; ?>
<?php include 'header.php'; ?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  Income added successfully!
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
  Income deleted successfully!
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  Error: <?php echo htmlspecialchars($_GET['error']); ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<h3 class="mb-4 text-center">JCLF - Income Tracking</h3>

<?php
// Keep last selected date (from URL) or default to today
$last_date = $_GET['last_date'] ?? date('Y-m-d');
?>

<form method="POST" action="add_income.php" class="row g-3">
  <?php if (isset($_GET['filter_date']) && $_GET['filter_date'] != ''): ?>
    <input type="hidden" name="filter_date" value="<?php echo htmlspecialchars($_GET['filter_date']); ?>">
  <?php endif; ?>
  <div class="col-md-3">
    <label>Sunday Date</label>
    <input type="date" name="sunday_date" class="form-control" required value="<?= htmlspecialchars($last_date) ?>">
  </div>
  <div class="col-md-3">
    <label>Type</label>
    <select name="income_type" class="form-control">
      <option value="Tithes">Tithes</option>
      <option value="Donations">Donations</option>
    </select>
  </div>
  <div class="col-md-3">
    <label>Description</label>
    <input type="text" name="description" class="form-control">
  </div>
  <div class="col-md-2">
    <label>Amount</label>
    <input type="number" step="0.01" name="amount" class="form-control" required>
  </div>
  <div class="col-md-1 d-flex align-items-end">
    <button class="btn btn-success w-100">Add</button>
  </div>
</form>

<hr>

<h4>Filter Income</h4>
<form method="GET" class="row g-3 mb-4">
  <div class="col-md-4">
    <label>Filter by Date</label>
    <input type="date" name="filter_date" class="form-control" value="<?php echo isset($_GET['filter_date']) ? htmlspecialchars($_GET['filter_date']) : ''; ?>">
  </div>
  <div class="col-md-4 d-flex align-items-end">
    <button type="submit" class="btn btn-primary me-2">Filter</button>
    <a href="income.php" class="btn btn-secondary">Clear</a>
  </div>
</form>

<h4>Income <?php 
  if (isset($_GET['filter_date']) && $_GET['filter_date'] != '') {
    echo " on " . htmlspecialchars($_GET['filter_date']);
  }
?></h4>
<table class="table table-bordered mt-3">
  <thead>
    <tr><th>Date</th><th>Type</th><th>Description</th><th>Amount</th><th>Action</th></tr>
  </thead>
  <tbody>
    <?php
    $where_clauses = [];
    $params = [];
    $types = "";

    if (isset($_GET['filter_date']) && $_GET['filter_date'] != '') {
      $where_clauses[] = "s.sunday_date = ?";
      $params[] = $_GET['filter_date'];
      $types .= "s";
    }

    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

    $sql = "SELECT i.id, s.sunday_date, i.income_type, i.description, i.amount
            FROM income_entries i
            JOIN sundays s ON s.id = i.sunday_id
            $where_sql
            ORDER BY s.sunday_date DESC
            LIMIT 100";

    if (!empty($params)) {
      $stmt = $conn->prepare($sql);
      $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $res = $stmt->get_result();
    } else {
      $res = $conn->query($sql);
    }

    $total_amount = 0;
    $row_count = 0;
    while ($row = $res->fetch_assoc()) {
      $row_count++;
      $total_amount += $row['amount'];
      echo "<tr>
              <td>{$row['sunday_date']}</td>
              <td>{$row['income_type']}</td>
              <td>{$row['description']}</td>
              <td>₱" . number_format($row['amount'],2) . "</td>
              <td>
                <form method='POST' action='delete_income.php' style='display: inline;' onsubmit='return confirm(\"Are you sure you want to delete this income?\");'>
                  <input type='hidden' name='income_id' value='{$row['id']}'>";

      if (isset($_GET['filter_date']) && $_GET['filter_date'] != '') {
        echo "<input type='hidden' name='filter_date' value='" . htmlspecialchars($_GET['filter_date']) . "'>";
      }

      echo "                  <button type='submit' class='btn btn-danger btn-sm'>Delete</button>
                </form>
              </td>
            </tr>";
    }

    if ($row_count == 0) {
      echo "<tr><td colspan='5' class='text-center'>No income found.</td></tr>";
    } else {
      echo "<tr class='table-info'><td colspan='3'><strong>Total</strong></td><td><strong>₱" . number_format($total_amount, 2) . "</strong></td><td></td></tr>";
    }
    ?>
  </tbody>
</table>

<!-- Optional JS for persistent last date across page reloads -->
<script>
const dateInput = document.querySelector('input[name="sunday_date"]');
if (sessionStorage.getItem('last_income_date')) {
    dateInput.value = sessionStorage.getItem('last_income_date');
}
dateInput.addEventListener('change', () => {
    sessionStorage.setItem('last_income_date', dateInput.value);
});
</script>

<?php include 'footer.php'; ?>
