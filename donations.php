<?php
include 'connection.php';
include 'auth.php';
auth_require_login();
include 'header.php';

$isAdmin = auth_is_admin();

// --- PERIOD FILTER LOGIC ---
$view = $_GET['view_type'] ?? 'monthly';
$input_date  = $_GET['date']  ?? date('Y-m-d');
$input_month = $_GET['month'] ?? date('Y-m');
$input_year  = $_GET['year']  ?? date('Y');

$where = [];
$params = [];
$types = "";
$period_label = "";

if ($view === 'single') {
    $where[] = "s.sunday_date = ?";
    $params[] = $input_date;
    $types .= "s";
    $period_label = "Date: " . date('F j, Y', strtotime($input_date));
} elseif ($view === 'monthly') {
    $m = date('m', strtotime($input_month . "-01"));
    $y = date('Y', strtotime($input_month . "-01"));
    $where[] = "MONTH(s.sunday_date) = ? AND YEAR(s.sunday_date) = ?";
    $params[] = $m;
    $params[] = $y;
    $types .= "ii";
    $period_label = "Month: " . date('F Y', strtotime($input_month . "-01"));
} elseif ($view === 'yearly') {
    $where[] = "YEAR(s.sunday_date) = ?";
    $params[] = $input_year;
    $types .= "i";
    $period_label = "Year: " . (int)$input_year;
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// --- GET TOTALS ---
$stmt = $conn->prepare("
  SELECT
    COALESCE((SELECT SUM(d.amount) FROM donation_income_entries d
              JOIN sundays s ON s.id = d.sunday_id $where_sql), 0) AS income,
    COALESCE((SELECT SUM(e.amount) FROM donation_expense_entries e
              JOIN sundays s ON s.id = e.sunday_id $where_sql), 0) AS expense
");
if ($params) $stmt->bind_param($types . $types, ...array_merge($params, $params));
$stmt->execute();
$totals = db_stmt_fetch_one_assoc($stmt);
$stmt->close();

$donIncome  = (float)($totals['income'] ?? 0);
$donExpense = (float)($totals['expense'] ?? 0);
$donNet     = $donIncome - $donExpense;

// --- GET RECENT LEDGER ---
$stmt = $conn->prepare("
  SELECT 'INCOME' kind, d.id, s.sunday_date, d.donation_type cat,
         d.donor, d.description, d.amount
  FROM donation_income_entries d JOIN sundays s ON s.id = d.sunday_id
  $where_sql
  UNION ALL
  SELECT 'EXPENSE', e.id, s.sunday_date, c.main_category,
         NULL, e.description, e.amount
  FROM donation_expense_entries e
  JOIN sundays s ON s.id = e.sunday_id
  JOIN donation_categories c ON c.id = e.category_id
  $where_sql
  ORDER BY sunday_date DESC, kind
  LIMIT 50
");
if ($params) $stmt->bind_param($types . $types, ...array_merge($params, $params));
$stmt->execute();
$ledger = db_stmt_fetch_all_assoc($stmt);
$stmt->close();

// Get donation categories for the expense form
$donCats = [];
$res = $conn->query("SELECT id, main_category FROM donation_categories ORDER BY main_category");
if ($res) while ($c = $res->fetch_assoc()) $donCats[] = $c;
?>

<!-- TITLE & HEADER -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-1">Donations Track</h3>
    <p class="text-muted small mb-0">This is completely separate from your main Finance data.</p>
  </div>
  <a href="donation_report_selector.php" class="btn btn-outline-primary">📄 Donation Report</a>
</div>

<!-- ALERTS -->
<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show">
  ⚠️ <?= htmlspecialchars($_GET['error']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
  ✅ Record saved successfully!
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-info alert-dismissible fade show">
  🗑️ Record deleted successfully!
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- SEARCH & FILTER BAR -->
<div class="card shadow-sm mb-4 border-primary">
  <div class="card-body bg-light rounded">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small mb-1 fw-bold">Search By</label>
        <select name="view_type" id="view_type" class="form-select">
          <option value="single"  <?= $view==='single'?'selected':'' ?>>Specific Day</option>
          <option value="monthly" <?= $view==='monthly'?'selected':'' ?>>By Month</option>
          <option value="yearly"  <?= $view==='yearly'?'selected':'' ?>>By Year</option>
        </select>
      </div>
      
      <div class="col-md-3" id="date_field" style="<?= $view==='single'?'':'display:none' ?>">
        <label class="form-label small mb-1 fw-bold">Date</label>
        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($input_date) ?>">
      </div>
      
      <div class="col-md-3" id="month_field" style="<?= $view==='monthly'?'':'display:none' ?>">
        <label class="form-label small mb-1 fw-bold">Month</label>
        <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($input_month) ?>">
      </div>
      
      <div class="col-md-3" id="year_field" style="<?= $view==='yearly'?'':'display:none' ?>">
        <label class="form-label small mb-1 fw-bold">Year</label>
        <select name="year" class="form-select">
          <?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
            <option value="<?= $y ?>" <?= ((int)$input_year === $y)?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      
      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary flex-fill fw-bold">Apply Filter</button>
        <a href="donations.php" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- SUMMARY CARDS -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card bg-success text-white shadow-sm border-0">
      <div class="card-body">
        <div class="text-uppercase small fw-bold opacity-75 mb-1">Total Income</div>
        <div class="display-5 fw-bold mb-0">₱<?= number_format($donIncome, 2) ?></div>
        <div class="small opacity-75 mt-2">📅 <?= htmlspecialchars($period_label) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card bg-danger text-white shadow-sm border-0">
      <div class="card-body">
        <div class="text-uppercase small fw-bold opacity-75 mb-1">Total Expenses</div>
        <div class="display-5 fw-bold mb-0">₱<?= number_format($donExpense, 2) ?></div>
        <div class="small opacity-75 mt-2">📅 <?= htmlspecialchars($period_label) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card bg-primary text-white shadow-sm border-0">
      <div class="card-body">
        <div class="text-uppercase small fw-bold opacity-75 mb-1">Net Donation</div>
        <div class="display-5 fw-bold mb-0">₱<?= number_format($donNet, 2) ?></div>
        <div class="small opacity-75 mt-2">Income − Expenses</div>
      </div>
    </div>
  </div>
</div>

<!-- ADD FORMS (NO PASSWORD REQUIRED) -->
<?php if ($isAdmin): ?>
<div class="accordion shadow-sm mb-4" id="addDataAccordion">
  
  <!-- ADD INCOME DROPDOWN -->
  <div class="accordion-item border-success">
    <h2 class="accordion-header" id="headingIncome">
      <button class="accordion-button collapsed fw-bold text-success bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIncome" aria-expanded="false" aria-controls="collapseIncome">
        ➕ Click here to Add Donation Income
      </button>
    </h2>
    <div id="collapseIncome" class="accordion-collapse collapse" aria-labelledby="headingIncome" data-bs-parent="#addDataAccordion">
      <div class="accordion-body bg-light border-top border-success">
        <form method="POST" action="add_donation_income.php">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-bold small">Date</label>
              <input type="date" name="sunday_date" class="form-control" required value="<?= htmlspecialchars($input_date) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold small">Amount (₱)</label>
              <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-bold small">Type of Donation</label>
              <input type="text" name="donation_type" class="form-control" placeholder="e.g. Tithes, Missions, Building Fund" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold small">Donor Name (Optional)</label>
              <input type="text" name="donor" class="form-control" placeholder="e.g. John Doe or Anonymous">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold small">Description (Optional)</label>
              <input type="text" name="description" class="form-control" placeholder="e.g. Monthly support">
            </div>
            <div class="col-12 text-end">
              <button class="btn btn-success px-4 fw-bold">Save Income</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ADD EXPENSE DROPDOWN -->
  <div class="accordion-item border-danger">
    <h2 class="accordion-header" id="headingExpense">
      <button class="accordion-button collapsed fw-bold text-danger bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExpense" aria-expanded="false" aria-controls="collapseExpense">
        ➖ Click here to Add Donation Expense
      </button>
    </h2>
    <div id="collapseExpense" class="accordion-collapse collapse" aria-labelledby="headingExpense" data-bs-parent="#addDataAccordion">
      <div class="accordion-body bg-light border-top border-danger">
        <form method="POST" action="add_donation_expense.php">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-bold small">Date</label>
              <input type="date" name="sunday_date" class="form-control" required value="<?= htmlspecialchars($input_date) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold small">Amount (₱)</label>
              <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-bold small">Expense Category</label>
              <select name="category_id" class="form-select" required>
                <option value="" disabled selected>-- Select a Category --</option>
                <?php foreach ($donCats as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['main_category']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-bold small">Description (Optional)</label>
              <input type="text" name="description" class="form-control" placeholder="e.g. Groceries for outreach">
            </div>
            <div class="col-12 text-end">
              <button class="btn btn-danger px-4 fw-bold">Save Expense</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

</div>
<?php endif; ?>

<!-- ACTIVITY LEDGER -->
<div class="card shadow-sm">
  <div class="card-header bg-white py-3">
    <h5 class="mb-0 fw-bold">Recent Activity (Filtered)</h5>
  </div>

  <div class="card-body p-0">
    <?php if (empty($ledger)): ?>
      <div class="text-center text-muted py-5">
        <h1 class="display-4 text-secondary opacity-50">📭</h1>
        <p class="mb-0">No donation activity found for this period.</p>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Category / Type</th>
            <th>Details</th>
            <th class="text-end">Amount</th>
            <?php if ($isAdmin): ?><th class="text-end">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($ledger as $r): 
          $isInc = $r['kind'] === 'INCOME';
          $badge = $isInc ? 'bg-success' : 'bg-danger';
          $sign  = $isInc ? '+' : '−';
        ?>
          <tr>
            <td class="text-nowrap"><?= htmlspecialchars($r['sunday_date']) ?></td>
            <td>
              <span class="badge <?= $badge ?>"><?= $r['kind'] ?></span>
            </td>
            <td class="fw-bold"><?= htmlspecialchars($r['cat']) ?></td>
            <td>
              <?php if ($r['donor']): ?>
                <span class="text-muted small">Donor: <?= htmlspecialchars($r['donor']) ?></span><br>
              <?php endif; ?>
              <?= htmlspecialchars($r['description'] ?: '—') ?>
            </td>
            <td class="text-end fw-bold <?= $isInc ? 'text-success' : 'text-danger' ?>">
              <?= $sign ?> ₱<?= number_format((float)$r['amount'], 2) ?>
            </td>
            <?php if ($isAdmin): ?>
            <td class="text-end text-nowrap">
              
              <?php if ($isInc): ?>
                <a href="edit_donation_income.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                
                <!-- Trigger Delete Modal for INCOME -->
                <button type="button" class="btn btn-sm btn-outline-danger" 
                        onclick="openDeleteModal(<?= (int)$r['id'] ?>, 'income')">
                  Del
                </button>

              <?php else: ?>
                <a href="edit_donation_expense.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                
                <!-- Trigger Delete Modal for EXPENSE -->
                <button type="button" class="btn btn-sm btn-outline-danger" 
                        onclick="openDeleteModal(<?= (int)$r['id'] ?>, 'expense')">
                  Del
                </button>
              <?php endif; ?>
              
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ========================================== -->
<!-- SECURE DELETE MODAL                        -->
<!-- ========================================== -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <!-- The form action will be set dynamically by JS -->
      <form method="POST" id="deleteForm">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Confirm Deletion</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete this record? This action cannot be undone.</p>
          
          <input type="hidden" name="id" id="modal_delete_id">
          
          <div class="mb-3">
            <label class="form-label fw-bold small text-danger">Enter your Admin Password to confirm</label>
            <!-- Hidden password input -->
            <input type="password" name="admin_password" class="form-control" required placeholder="••••••••" autocomplete="current-password">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger fw-bold">Yes, Delete It</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('view_type').addEventListener('change', function(){
  document.getElementById('date_field').style.display  = this.value==='single'?'':'none';
  document.getElementById('month_field').style.display = this.value==='monthly'?'':'none';
  document.getElementById('year_field').style.display  = this.value==='yearly'?'':'none';
});

// Function to populate the Modal and set the correct form action
function openDeleteModal(id, type) {
  document.getElementById('modal_delete_id').value = id;
  
  const form = document.getElementById('deleteForm');
  if (type === 'income') {
    form.action = 'delete_donation_income.php';
  } else {
    form.action = 'delete_donation_expense.php';
  }
  
  // Clear the password field each time it opens
  form.querySelector('input[name="admin_password"]').value = '';
  
  var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
  deleteModal.show();
}
</script>

<?php include 'footer.php'; ?>