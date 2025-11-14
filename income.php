<?php
include 'connection.php';
include 'header.php';
?>

<h3 class="mb-4 text-center">JCLF - Income Tracking</h3>

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

<?php
$last_date = $_GET['last_date'] ?? date('Y-m-d');
?>

<form method="POST" action="add_income.php" class="row g-3 mb-4">
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
    <a href="income.php" class="btn btn-secondary ms-2">Clear</a>
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

<h4>Income Records</h4>
<table class="table table-bordered mt-3">
<thead>
<tr><th>Date</th><th>Type</th><th>Description</th><th>Amount</th><th>Action</th></tr>
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
$sql = "SELECT i.id, s.sunday_date, i.income_type, i.description, i.amount
        FROM income_entries i
        JOIN sundays s ON s.id=i.sunday_id
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
        <td>{$row['income_type']}</td>
        <td>{$row['description']}</td>
        <td>₱".number_format($row['amount'],2)."</td>
        <td>
            <form method='POST' action='delete_income.php' onsubmit='return confirm(\"Delete this income?\");'>
            <input type='hidden' name='income_id' value='{$row['id']}'>
            <button class='btn btn-danger btn-sm'>Delete</button>
            </form>
        </td>
    </tr>";
}
if($count==0){
    echo "<tr><td colspan='5' class='text-center'>No income found.</td></tr>";
}else{
    echo "<tr class='table-info'><td colspan='3'><strong>Total</strong></td><td><strong>₱".number_format($total,2)."</strong></td><td></td></tr>";
}
?>
</tbody>
</table>

<?php include 'footer.php'; ?>
