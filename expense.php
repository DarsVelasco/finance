<?php
include 'connection.php';
include 'header.php';
?>

<h3 class="mb-4 text-center">JCLF - Expense Tracking</h3>

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

<?php
$last_date = $_GET['last_date'] ?? date('Y-m-d');
?>

<!-- Add Expense Form -->
<form method="POST" action="add_expense.php" class="row g-3 mb-4">
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

<!-- Filter Section -->
<h4>Filter Expenses</h4>
<form method="GET" class="row g-3 mb-4">
  <div class="col-md-3">
    <label>View Type</label>
    <select name="view_type" id="view_type" class="form-control" required>
      <option value="single" <?php echo (!isset($_GET['view_type']) || $_GET['view_type']=='single') ? 'selected' : ''; ?>>Per Day</option>
      <option value="monthly" <?php echo (isset($_GET['view_type']) && $_GET['view_type']=='monthly') ? 'selected' : ''; ?>>Per Month</option>
      <option value="yearly" <?php echo (isset($_GET['view_type']) && $_GET['view_type']=='yearly') ? 'selected' : ''; ?>>Per Year</option>
    </select>
  </div>
  <div class="col-md-3" id="date_field">
    <label>Select Date</label>
    <input type="date" name="date" class="form-control" value="<?php echo isset($_GET['date']) ? htmlspecialchars($_GET['date']) : ''; ?>">
  </div>
  <div class="col-md-3" id="month_field" style="display:none;">
    <label>Select Month</label>
    <input type="month" name="month" class="form-control" value="<?php echo isset($_GET['month']) ? htmlspecialchars($_GET['month']) : ''; ?>">
  </div>
  <div class="col-md-3" id="year_field" style="display:none;">
    <label>Select Year</label>
    <select name="year" class="form-control">
      <?php
      $current_year = date('Y');
      for($y=$current_year; $y>=$current_year-5; $y--){
          $selected = (isset($_GET['year']) && $_GET['year']==$y) ? 'selected' : '';
          echo "<option value='$y' $selected>$y</option>";
      }
      ?>
    </select>
  </div>
  <div class="col-md-3 d-flex align-items-end">
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="expense.php" class="btn btn-secondary ms-2">Clear</a>
  </div>
</form>

<script>
const viewSelect = document.getElementById('view_type');
viewSelect.addEventListener('change', function(){
    document.getElementById('date_field').style.display = this.value=='single'?'block':'none';
    document.getElementById('month_field').style.display = this.value=='monthly'?'block':'none';
    document.getElementById('year_field').style.display = this.value=='yearly'?'block':'none';
});
viewSelect.dispatchEvent(new Event('change'));
</script>

<!-- Expense Table -->
<h4>Expenses</h4>
<table class="table table-bordered mt-3">
<thead>
<tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Action</th></tr>
</thead>
<tbody>
<?php
$where = [];
$params = [];
$types = "";

if(isset($_GET['view_type'])){
    $view = $_GET['view_type'];
    if($view=='single' && !empty($_GET['date'])){
        $where[] = "s.sunday_date=?";
        $params[] = $_GET['date'];
        $types .= "s";
    }elseif($view=='monthly' && !empty($_GET['month'])){
        $month = date('m', strtotime($_GET['month']."-01"));
        $year = date('Y', strtotime($_GET['month']."-01"));
        $where[] = "MONTH(s.sunday_date)=? AND YEAR(s.sunday_date)=?";
        $params[] = $month;
        $params[] = $year;
        $types .= "ii";
    }elseif($view=='yearly' && !empty($_GET['year'])){
        $where[] = "YEAR(s.sunday_date)=?";
        $params[] = $_GET['year'];
        $types .= "i";
    }
}

$where_sql = !empty($where) ? "WHERE ".implode(" AND ", $where) : "";
$sql = "SELECT e.id, s.sunday_date, CONCAT(c.main_category, IF(c.sub_category IS NOT NULL AND c.sub_category != '', CONCAT(' - ', c.sub_category), '')) AS category, e.description, e.amount
        FROM expense_entries e
        JOIN sundays s ON s.id=e.sunday_id
        JOIN expense_categories c ON c.id=e.category_id
        $where_sql
        ORDER BY s.sunday_date DESC
        LIMIT ".(empty($where)?10:100);

if(!empty($params)){
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
}else{
    $res = $conn->query($sql);
}

$total = 0;
$count = 0;
while($row = $res->fetch_assoc()){
    $count++;
    $total += $row['amount'];
    echo "<tr>
        <td>{$row['sunday_date']}</td>
        <td>{$row['category']}</td>
        <td>{$row['description']}</td>
        <td>₱".number_format($row['amount'],2)."</td>
        <td>
            <form method='POST' action='delete_expense.php' onsubmit='return confirm(\"Delete this expense?\");'>
            <input type='hidden' name='expense_id' value='{$row['id']}'>
            <button class='btn btn-danger btn-sm'>Delete</button>
            </form>
        </td>
    </tr>";
}

if($count==0){
    echo "<tr><td colspan='5' class='text-center'>No expenses found.</td></tr>";
}else{
    echo "<tr class='table-info'><td colspan='3'><strong>Total</strong></td><td><strong>₱".number_format($total,2)."</strong></td><td></td></tr>";
}
?>
</tbody>
</table>

<?php include 'footer.php'; ?>
