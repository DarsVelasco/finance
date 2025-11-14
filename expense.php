<?php include 'connection.php'; ?>
<?php include 'header.php'; ?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  Expense added successfully!
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
  Expense deleted successfully!
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  Error: <?php echo htmlspecialchars($_GET['error']); ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<h3 class="mb-4 text-center">JCLF - Expense Tracking</h3>

<?php
// Keep last submitted date
$last_date = $_GET['last_date'] ?? date('Y-m-d');
?>

<form method="POST" action="add_expense.php" class="row g-3">
  <div class="col-md-3">
    <label>Sunday Date</label>
    <input type="date" name="sunday_date" class="form-control" required value="<?= htmlspecialchars($last_date) ?>">
  </div>
  <div class="col-md-4">
    <label>Category</label>
    <select name="category_id" class="form-control" required>
        <?php
        $result = $conn->query("SELECT id, main_category FROM expense_categories ORDER BY main_category ASC");
        while ($row = $result->fetch_assoc()) {
            echo "<option value='{$row['id']}'>" . htmlspecialchars($row['main_category']) . "</option>";
        }
        ?>
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

<h4>Filter Expenses</h4>
<form method="GET" class="row g-3 mb-4">
  <div class="col-md-4">
    <label>Filter by Category</label>
    <select name="filter_category" class="form-control">
      <option value="">All Categories</option>
      <?php
      $cats = $conn->query("SELECT id, main_category FROM expense_categories ORDER BY main_category");
      $selected_category = isset($_GET['filter_category']) ? intval($_GET['filter_category']) : 0;
      while ($row = $cats->fetch_assoc()) {
        $selected = ($selected_category == $row['id']) ? 'selected' : '';
        echo "<option value='{$row['id']}' $selected>" . htmlspecialchars($row['main_category']) . "</option>";
      }
      ?>
    </select>
  </div>
  <div class="col-md-4">
    <label>Filter by Date</label>
    <input type="date" name="filter_date" class="form-control" value="<?php echo isset($_GET['filter_date']) ? htmlspecialchars($_GET['filter_date']) : ''; ?>">
  </div>
  <div class="col-md-4 d-flex align-items-end">
    <button type="submit" class="btn btn-primary me-2">Filter</button>
    <a href="expense.php" class="btn btn-secondary">Clear</a>
  </div>
</form>

<h4>Expenses</h4>
<table class="table table-bordered mt-3">
  <thead>
    <tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Action</th></tr>
  </thead>
  <tbody>
<?php
// Show 10 recent or filtered results
$filter_applied = (isset($_GET['filter_category']) && $_GET['filter_category'] != '') || (isset($_GET['filter_date']) && $_GET['filter_date'] != '');

if ($filter_applied) {
    $where_clauses = [];
    $params = [];
    $types = "";
    if (isset($_GET['filter_category']) && $_GET['filter_category'] != '') {
        $where_clauses[] = "c.id = ?";
        $params[] = intval($_GET['filter_category']);
        $types .= "i";
    }
    if (isset($_GET['filter_date']) && $_GET['filter_date'] != '') {
        $where_clauses[] = "s.sunday_date = ?";
        $params[] = $_GET['filter_date'];
        $types .= "s";
    }
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
    $limit_sql = "LIMIT 100";
} else {
    $where_sql = "";
    $params = [];
    $types = "";
    $limit_sql = "ORDER BY s.sunday_date DESC LIMIT 10";
}

$sql = "SELECT e.id, s.sunday_date, c.main_category AS category, e.description, e.amount
        FROM expense_entries e
        JOIN sundays s ON s.id = e.sunday_id
        JOIN expense_categories c ON c.id = e.category_id
        $where_sql
        $limit_sql";

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
            <td>{$row['category']}</td>
            <td>{$row['description']}</td>
            <td>₱" . number_format($row['amount'],2) . "</td>
            <td>
              <form method='POST' action='delete_expense.php' onsubmit='return confirm(\"Are you sure?\");'>
                <input type='hidden' name='expense_id' value='{$row['id']}'>
                <button type='submit' class='btn btn-danger btn-sm'>Delete</button>
              </form>
            </td>
          </tr>";
}

if ($row_count == 0) {
    echo "<tr><td colspan='5' class='text-center'>No expenses found.</td></tr>";
} else {
    echo "<tr class='table-info'><td colspan='3'><strong>Total</strong></td><td><strong>₱" . number_format($total_amount,2) . "</strong></td><td></td></tr>";
}
?>
  </tbody>
</table>

<script>
// Persist last date
const dateInput = document.querySelector('input[name="sunday_date"]');
if (sessionStorage.getItem('last_expense_date')) {
    dateInput.value = sessionStorage.getItem('last_expense_date');
}
dateInput.addEventListener('change', () => {
    sessionStorage.setItem('last_expense_date', dateInput.value);
});
</script>

<?php include 'footer.php'; ?>
