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

<form method="POST" action="add_expense.php" class="row g-3">
  <?php if (isset($_GET['filter_category']) && $_GET['filter_category'] != ''): ?>
    <input type="hidden" name="filter_category" value="<?php echo htmlspecialchars($_GET['filter_category']); ?>">
  <?php endif; ?>
  <?php if (isset($_GET['filter_date']) && $_GET['filter_date'] != ''): ?>
    <input type="hidden" name="filter_date" value="<?php echo htmlspecialchars($_GET['filter_date']); ?>">
  <?php endif; ?>
  <div class="col-md-3">
    <label>Sunday Date</label>
    <input type="date" name="sunday_date" class="form-control" required>
  </div>
  <div class="col-md-4">
    <label>Category</label>
    <select name="category_id" class="form-control" required>
        <?php
        // Fetch all categories from the database
        $result = $conn->query("SELECT id, main_category FROM expense_categories ORDER BY main_category ASC");
        while ($row = $result->fetch_assoc()) {
            // Preserve previously selected value after form submission
            $selected = (isset($_POST['category_id']) && $_POST['category_id'] == $row['id']) ? "selected" : "";
            echo "<option value='{$row['id']}' $selected>" . htmlspecialchars($row['main_category']) . "</option>";
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
      $cats = $conn->query("SELECT id, main_category, sub_category FROM expense_categories ORDER BY main_category");
      $selected_category = isset($_GET['filter_category']) ? intval($_GET['filter_category']) : 0;
      while ($row = $cats->fetch_assoc()) {
        $label = $row['main_category'] . ($row['sub_category'] ? " - " . $row['sub_category'] : "");
        $selected = ($selected_category == $row['id']) ? 'selected' : '';
        echo "<option value='{$row['id']}' $selected>$label</option>";
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

<h4>Expenses <?php 
  if (isset($_GET['filter_category']) && $_GET['filter_category'] != '') {
    $cat_id = intval($_GET['filter_category']);
    $stmt = $conn->prepare("SELECT main_category FROM expense_categories WHERE id = ?");
    $stmt->bind_param("i", $cat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
      $cat_name = $result->fetch_assoc()['main_category'];
      echo " - " . htmlspecialchars($cat_name);
    }
    $stmt->close();
  }
  if (isset($_GET['filter_date']) && $_GET['filter_date'] != '') {
    echo " on " . htmlspecialchars($_GET['filter_date']);
  }
?></h4>
<table class="table table-bordered mt-3">
  <thead>
    <tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Action</th></tr>
  </thead>
  <tbody>
    <?php
    // Build the query with filters
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
    
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    $sql = "SELECT e.id,
                   s.sunday_date, 
                   CONCAT(c.main_category, IF(c.sub_category IS NOT NULL AND c.sub_category != '', CONCAT(' - ', c.sub_category), '')) AS category, 
                   e.description, 
                   e.amount
            FROM expense_entries e
            JOIN sundays s ON s.id = e.sunday_id
            JOIN expense_categories c ON c.id = e.category_id
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
              <td>{$row['category']}</td>
              <td>{$row['description']}</td>
              <td>₱" . number_format($row['amount'],2) . "</td>
              <td>
                <form method='POST' action='delete_expense.php' style='display: inline;' onsubmit='return confirm(\"Are you sure you want to delete this expense?\");'>
                  <input type='hidden' name='expense_id' value='{$row['id']}'>";
      
      if (isset($_GET['filter_category']) && $_GET['filter_category'] != '') {
        echo "<input type='hidden' name='filter_category' value='" . htmlspecialchars($_GET['filter_category']) . "'>";
      }
      if (isset($_GET['filter_date']) && $_GET['filter_date'] != '') {
        echo "<input type='hidden' name='filter_date' value='" . htmlspecialchars($_GET['filter_date']) . "'>";
      }
      
      echo "                  <button type='submit' class='btn btn-danger btn-sm'>Delete</button>
                </form>
              </td>
            </tr>";
    }
    
    if ($row_count == 0) {
      echo "<tr><td colspan='5' class='text-center'>No expenses found.</td></tr>";
    } else {
      echo "<tr class='table-info'><td colspan='3'><strong>Total</strong></td><td><strong>₱" . number_format($total_amount, 2) . "</strong></td><td></td></tr>";
    }
    ?>
  </tbody>
</table>

<?php include 'footer.php'; ?>
