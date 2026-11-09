<?php
include 'connection.php';
include 'auth.php';
auth_require_login();
include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-1 fw-bold">Expense Tracking</h3>
    <p class="text-muted small mb-0">Manage and track all financial expenses.</p>
  </div>
</div>

<!-- ALERTS -->
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show shadow-sm">
  <i class="bi bi-check-circle-fill me-2"></i> Expense added successfully!
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-info alert-dismissible fade show shadow-sm">
  <i class="bi bi-trash-fill me-2"></i> Expense deleted successfully!
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
<div class="alert alert-success alert-dismissible fade show shadow-sm">
  <i class="bi bi-pencil-fill me-2"></i> Expense updated successfully!
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show shadow-sm">
  <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($_GET['error']); ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php
$last_date = $_GET['last_date'] ?? date('Y-m-d');
$isAdmin = auth_is_admin();

// ------------------ SEARCH ------------------
$search = trim((string)($_GET['search'] ?? ''));

// Pagination
$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// ============================================================
// BUILD FILTER
// ============================================================
$view = $_GET['view_type'] ?? 'monthly';

$input_date  = $_GET['date']  ?? date('Y-m-d');
$input_month = $_GET['month'] ?? date('Y-m');
$input_year  = $_GET['year']  ?? date('Y');

$filter_label = 'All Time';
$where = [];
$params = [];
$types = "";

$period_start = null;
$period_end   = null;

if ($view === 'single') {
    $where[] = "s.sunday_date = ?";
    $params[] = $input_date;
    $types .= "s";
    $filter_label = "Date: " . date('F j, Y', strtotime($input_date));
    $period_start = $input_date;
    $period_end   = $input_date;

} elseif ($view === 'monthly') {
    $m = date('m', strtotime($input_month . "-01"));
    $y = date('Y', strtotime($input_month . "-01"));
    $where[] = "MONTH(s.sunday_date) = ? AND YEAR(s.sunday_date) = ?";
    $params[] = $m;
    $params[] = $y;
    $types .= "ii";
    $filter_label = "Month: " . date('F Y', strtotime($input_month . "-01"));
    $period_start = date('Y-m-01', strtotime($input_month . "-01"));
    $period_end   = date('Y-m-t', strtotime($input_month . "-01"));

} elseif ($view === 'yearly') {
    $where[] = "YEAR(s.sunday_date) = ?";
    $params[] = $input_year;
    $types .= "i";
    $filter_label = "Year: " . (int)$input_year;
    $period_start = ((int)$input_year) . "-01-01";
    $period_end   = ((int)$input_year) . "-12-31";
}

// ------------------ SEARCH FILTER ------------------
if ($search !== '') {
    $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($words as $word) {
        $like = db_like_pattern($word);
        $where[] = "(
            e.description COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'
            OR c.main_category COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'
            OR s.sunday_date LIKE ? ESCAPE '\\\\'
        )";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= "sss";
    }
    $filter_label .= " · Search: \"" . $search . "\"";
}

$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// ============================================================
// CASH ON HAND
// ============================================================
$carry_over = 0.0;
$period_income = 0.0;
$period_expense = 0.0;
$cash_on_hand = 0.0;

if ($period_start !== null && $period_end !== null) {
    $stmt = $conn->prepare("
        SELECT
            COALESCE((SELECT SUM(i.amount)
                      FROM income_entries i
                      JOIN sundays s ON s.id = i.sunday_id
                      WHERE s.sunday_date < ?), 0) AS inc_before,
            COALESCE((SELECT SUM(e.amount)
                      FROM expense_entries e
                      JOIN sundays s ON s.id = e.sunday_id
                      WHERE s.sunday_date < ?), 0) AS exp_before,
            COALESCE((SELECT SUM(i.amount)
                      FROM income_entries i
                      JOIN sundays s ON s.id = i.sunday_id
                      WHERE s.sunday_date BETWEEN ? AND ?), 0) AS inc_period,
            COALESCE((SELECT SUM(e.amount)
                      FROM expense_entries e
                      JOIN sundays s ON s.id = e.sunday_id
                      WHERE s.sunday_date BETWEEN ? AND ?), 0) AS exp_period
    ");
    $stmt->bind_param("ssssss",
        $period_start, $period_start,
        $period_start, $period_end,
        $period_start, $period_end
    );
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    $carry_over     = (float)$row['inc_before'] - (float)$row['exp_before'];
    $period_income  = (float)$row['inc_period'];
    $period_expense = (float)$row['exp_period'];
    $cash_on_hand   = $carry_over + $period_income - $period_expense;
}

// ============================================================
// QUERY 1: Category totals
// ============================================================
$cat_sql = "
    SELECT c.id, c.main_category,
           COALESCE(SUM(e.amount), 0) AS total,
           COUNT(e.id) AS item_count
    FROM expense_categories c
    LEFT JOIN expense_entries e ON e.category_id = c.id
    LEFT JOIN sundays s ON s.id = e.sunday_id
    $where_sql
    GROUP BY c.id, c.main_category
    ORDER BY total DESC, c.main_category ASC
";

if (!empty($params)) {
    $stmt = $conn->prepare($cat_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $cat_rows = db_stmt_fetch_all_assoc($stmt);
    $stmt->close();
} else {
    $cat_rows = [];
    $res = $conn->query($cat_sql);
    if ($res) while ($r = $res->fetch_assoc()) $cat_rows[] = $r;
}

$grand_total = 0;
$grand_count = 0;
foreach ($cat_rows as $cr) {
    $grand_total += (float)$cr['total'];
    $grand_count += (int)$cr['item_count'];
}

// ============================================================
// QUERY 2: Total count for pagination
// ============================================================
$count_sql = "
    SELECT COUNT(*) AS c
    FROM expense_entries e
    JOIN sundays s ON s.id = e.sunday_id
    JOIN expense_categories c ON c.id = e.category_id
    $where_sql
";
if (!empty($params)) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $count_row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();
} else {
    $count_row = $conn->query($count_sql)->fetch_assoc();
}
$total_items = (int)($count_row['c'] ?? 0);
$total_pages = max(1, (int)ceil($total_items / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// ============================================================
// QUERY 3: Paginated expense list
// ============================================================
$list_sql = "
    SELECT e.id, s.sunday_date, c.main_category AS category, e.description, e.amount
    FROM expense_entries e
    JOIN sundays s ON s.id = e.sunday_id
    JOIN expense_categories c ON c.id = e.category_id
    $where_sql
    ORDER BY s.sunday_date DESC, e.id DESC
    LIMIT ? OFFSET ?
";
$list_params = array_merge($params, [$per_page, $offset]);
$list_types = $types . "ii";

$stmt = $conn->prepare($list_sql);
$stmt->bind_param($list_types, ...$list_params);
$stmt->execute();
$list_rows = db_stmt_fetch_all_assoc($stmt);
$stmt->close();

$list_total = 0;
foreach ($list_rows as $lr) $list_total += (float)$lr['amount'];

$tab = $_GET['tab'] ?? 'summary';

function page_url($new_page, $tab) {
    $q = $_GET;
    $q['page'] = $new_page;
    $q['tab'] = $tab;
    return '?' . http_build_query($q);
}

$coh_class = $cash_on_hand >= 0 ? 'text-success' : 'text-danger';
?>

<!-- FILTER BAR -->
<div class="card shadow-sm mb-4 border-danger">
  <div class="card-body bg-light rounded">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <input type="hidden" name="page" value="1">

      <div class="col-md-2">
        <label class="form-label small mb-1 fw-bold">Period</label>
        <select name="view_type" id="view_type" class="form-select">
          <option value="single" <?= $view==='single'?'selected':'' ?>>Specific Day</option>
          <option value="monthly" <?= $view==='monthly'?'selected':'' ?>>By Month</option>
          <option value="yearly" <?= $view==='yearly'?'selected':'' ?>>By Year</option>
        </select>
      </div>

      <div class="col-md-2" id="date_field" style="<?= $view==='single'?'':'display:none' ?>">
        <label class="form-label small mb-1 fw-bold">Date</label>
        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($input_date) ?>">
      </div>

      <div class="col-md-2" id="month_field" style="<?= $view==='monthly'?'':'display:none' ?>">
        <label class="form-label small mb-1 fw-bold">Month</label>
        <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($input_month) ?>">
      </div>

      <div class="col-md-2" id="year_field" style="<?= $view==='yearly'?'':'display:none' ?>">
        <label class="form-label small mb-1 fw-bold">Year</label>
        <select name="year" class="form-select">
          <?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
            <option value="<?= $y ?>" <?= ((int)$input_year === $y)?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label small mb-1 fw-bold">Search</label>
        <input type="text" name="search" class="form-control"
               placeholder="Description, category..."
               value="<?= htmlspecialchars($search) ?>">
      </div>

      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-danger flex-fill fw-bold">Apply</button>
        <a href="expense.php?tab=<?= $tab ?>" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('view_type').addEventListener('change', function(){
  document.getElementById('date_field').style.display  = this.value==='single'?'':'none';
  document.getElementById('month_field').style.display = this.value==='monthly'?'':'none';
  document.getElementById('year_field').style.display  = this.value==='yearly'?'':'none';
});
</script>

<!-- SUMMARY CARDS -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card text-white shadow-sm border-0 h-100" style="background: linear-gradient(135deg, #ef4444, #b91c1c);">
      <div class="card-body text-center py-4">
        <div class="text-uppercase small fw-bold opacity-75 mb-1">TOTAL EXPENSES</div>
        <div class="display-5 fw-bold">₱<?= number_format($grand_total, 2) ?></div>
        <div class="small opacity-75 mt-2">📅 <?= htmlspecialchars($filter_label) ?> · <?= $grand_count ?> items</div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card text-white bg-dark shadow-sm border-0 h-100">
      <div class="card-body text-center py-4">
        <div class="text-uppercase small fw-bold opacity-75 mb-1">CASH ON HAND</div>
        <div class="display-5 fw-bold <?= $coh_class ?>">₱<?= number_format($cash_on_hand, 2) ?></div>
        <div class="small opacity-75 mt-2">
          Carry-over ₱<?= number_format($carry_over, 2) ?>
          + Income ₱<?= number_format($period_income, 2) ?>
          − Expenses ₱<?= number_format($period_expense, 2) ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ADD EXPENSE DROPDOWN (ADMIN ONLY) -->
<?php if ($isAdmin): ?>
<div class="accordion shadow-sm mb-4" id="addExpenseAccordion">
  <div class="accordion-item border-danger">
    <h2 class="accordion-header" id="headingAddExpense">
      <button class="accordion-button collapsed fw-bold text-danger bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAddExpense" aria-expanded="false" aria-controls="collapseAddExpense">
        ➕ Click here to Add New Expense
      </button>
    </h2>
    <div id="collapseAddExpense" class="accordion-collapse collapse" aria-labelledby="headingAddExpense" data-bs-parent="#addExpenseAccordion">
      <div class="accordion-body bg-light border-top border-danger">
        <form method="POST" action="add_expense.php">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label fw-bold small">Date</label>
              <input type="date" name="sunday_date" class="form-control" required value="<?= htmlspecialchars($last_date) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small">Category</label>
              <select name="category_id" class="form-select" required>
                <?php
                $cats = $conn->query("SELECT id, main_category FROM expense_categories ORDER BY main_category ASC");
                while ($c = $cats->fetch_assoc()) {
                    echo "<option value='{$c['id']}'>" . htmlspecialchars($c['main_category']) . "</option>";
                }
                ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small">Description (Optional)</label>
              <input type="text" name="description" class="form-control" placeholder="e.g. Utility bill">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small">Amount (₱)</label>
              <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0.00">
            </div>
            <div class="col-12 text-end mt-3">
              <button class="btn btn-danger px-5 fw-bold">Save Expense</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- TABS (REPLACED WITH HIGHLY VISIBLE BUTTONS) -->
<div class="d-flex gap-2 mb-3 bg-white p-2 rounded shadow-sm border">
  <a href="?<?= http_build_query(array_merge($_GET, ['tab'=>'summary','page'=>1])) ?>" 
     class="btn <?= $tab==='summary' ? 'btn-danger' : 'btn-outline-dark' ?> fw-bold flex-fill py-2">
    📊 By Category
  </a>
  <a href="?<?= http_build_query(array_merge($_GET, ['tab'=>'list','page'=>1])) ?>" 
     class="btn <?= $tab==='list' ? 'btn-danger' : 'btn-outline-dark' ?> fw-bold flex-fill py-2">
    📋 All Expenses (<?= $total_items ?>)
  </a>
</div>

<?php if ($tab === 'summary'): ?>
<!-- TAB A: BY CATEGORY -->
<div class="card shadow-sm border-0">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width: 40px;"></th>
          <th>Category</th>
          <th class="text-end" style="width: 30%;">Amount</th>
          <th class="text-end" style="width: 25%;">Cash on Hand</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($cat_rows)): ?>
          <tr><td colspan="4" class="text-center text-muted py-4">No categories found.</td></tr>
        <?php else: ?>
          <?php foreach ($cat_rows as $cr):
              $amt = (float)$cr['total'];
              $cnt = (int)$cr['item_count'];
              $pct = $grand_total > 0 ? ($amt / $grand_total) * 100 : 0;
              $cat_id = (int)$cr['id'];
              $collapse_id = "cat_" . $cat_id;
          ?>
          <!-- MAIN CATEGORY ROW -->
          <tr data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>"
              role="button" aria-expanded="false" aria-controls="<?= $collapse_id ?>"
              style="cursor: pointer;">
            <td class="text-center align-middle">
              <?php if ($cnt > 0): ?>
                <span class="badge bg-danger rounded-pill" id="chev-<?= $cat_id ?>">▶</span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="align-middle">
              <strong class="text-danger"><?= htmlspecialchars($cr['main_category']) ?></strong>
              <span class="text-muted small ms-2">(<?= $cnt ?> item<?= $cnt===1?'':'s' ?>)</span>
            </td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="progress flex-grow-1" style="height: 20px;">
                  <div class="progress-bar <?= $amt > 0 ? 'bg-danger' : 'bg-light' ?>"
                       style="width: <?= number_format($pct,1) ?>%;">
                  </div>
                </div>
                <div class="text-end" style="min-width: 120px;">
                  <strong>₱<?= number_format($amt, 2) ?></strong>
                  <span class="text-muted small">(<?= number_format($pct,1) ?>%)</span>
                </div>
              </div>
            </td>
            <td class="text-end align-middle">
              <span class="fw-bold <?= $coh_class ?>">₱<?= number_format($cash_on_hand, 2) ?></span>
              <div class="text-muted small">period cash position</div>
            </td>
          </tr>

          <!-- EXPANDABLE DETAIL -->
          <?php if ($cnt > 0):
              $detail_page = max(1, (int)($_GET['dpage_' . $cat_id] ?? 1));
              $detail_offset = ($detail_page - 1) * $per_page;

              $dc_sql = "SELECT COUNT(*) AS c
                         FROM expense_entries e
                         JOIN sundays s ON s.id = e.sunday_id
                         JOIN expense_categories c ON c.id = e.category_id
                         WHERE e.category_id = ? " . (!empty($where) ? "AND " . implode(" AND ", $where) : "");
              $dc_stmt = $conn->prepare($dc_sql);
              $dc_types = "i" . $types;
              $dc_params = array_merge([$cat_id], $params);
              $dc_stmt->bind_param($dc_types, ...$dc_params);
              $dc_stmt->execute();
              $dc_row = db_stmt_fetch_one_assoc($dc_stmt);
              $dc_stmt->close();
              $cat_total_items = (int)($dc_row['c'] ?? 0);
              $cat_total_pages = max(1, (int)ceil($cat_total_items / $per_page));
              if ($detail_page > $cat_total_pages) {
                  $detail_page = $cat_total_pages;
                  $detail_offset = ($detail_page - 1) * $per_page;
              }

              $item_sql = "
                  SELECT e.id, s.sunday_date, e.description, e.amount
                  FROM expense_entries e
                  JOIN sundays s ON s.id = e.sunday_id
                  JOIN expense_categories c ON c.id = e.category_id
                  WHERE e.category_id = ?
                  " . (!empty($where) ? "AND " . implode(" AND ", $where) : "") . "
                  ORDER BY s.sunday_date DESC, e.id DESC
                  LIMIT ? OFFSET ?
              ";
              $item_types = "i" . $types . "ii";
              $item_params = array_merge([$cat_id], $params, [$per_page, $detail_offset]);
              $item_stmt = $conn->prepare($item_sql);
              $item_stmt->bind_param($item_types, ...$item_params);
              $item_stmt->execute();
              $items = db_stmt_fetch_all_assoc($item_stmt);
              $item_stmt->close();
          ?>
          <tr class="collapse" id="<?= $collapse_id ?>">
            <td colspan="4" class="p-0 bg-light">
              <div class="p-3">
                <table class="table table-sm table-bordered mb-2 bg-white shadow-sm">
                  <thead class="table-secondary">
                    <tr>
                      <th style="width: 120px;">Date</th>
                      <th>Description</th>
                      <th class="text-end" style="width: 150px;">Amount</th>
                      <?php if ($isAdmin): ?><th class="text-end" style="width: 160px;">Actions</th><?php endif; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($items)): ?>
                      <tr><td colspan="<?= $isAdmin?4:3 ?>" class="text-center text-muted">No items.</td></tr>
                    <?php else: foreach ($items as $item): ?>
                      <tr>
                        <td><?= htmlspecialchars($item['sunday_date']) ?></td>
                        <td><?= htmlspecialchars($item['description'] ?: '—') ?></td>
                        <td class="text-end text-danger fw-bold">₱<?= number_format($item['amount'], 2) ?></td>
                        <?php if ($isAdmin): ?>
                        <td class="text-end text-nowrap">
                          <a href="edit_expense.php?id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline-secondary py-0">Edit</a>
                          <form method="POST" action="delete_expense.php" class="d-inline" onsubmit="return confirm('Delete this expense?');">
                            <input type="hidden" name="expense_id" value="<?= (int)$item['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger py-0">Del</button>
                          </form>
                        </td>
                        <?php endif; ?>
                      </tr>
                    <?php endforeach; endif; ?>
                  </tbody>
                </table>

                <?php if ($cat_total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center small">
                  <span class="text-muted">
                    Page <?= $detail_page ?> of <?= $cat_total_pages ?>
                    (<?= $cat_total_items ?> item<?= $cat_total_items===1?'':'s' ?>)
                  </span>
                  <div class="btn-group btn-group-sm">
                    <?php
                    $q_prev = $_GET;
                    $q_prev['dpage_' . $cat_id] = max(1, $detail_page - 1);
                    $q_prev['tab'] = 'summary';
                    $q_prev['open_cat'] = $cat_id;

                    $q_next = $_GET;
                    $q_next['dpage_' . $cat_id] = min($cat_total_pages, $detail_page + 1);
                    $q_next['tab'] = 'summary';
                    $q_next['open_cat'] = $cat_id;
                    ?>
                    <a class="btn btn-outline-secondary <?= $detail_page <= 1 ? 'disabled' : '' ?>"
                       href="?<?= http_build_query($q_prev) ?>#<?= $collapse_id ?>">
                      ‹ Prev
                    </a>
                    <a class="btn btn-outline-danger <?= $detail_page >= $cat_total_pages ? 'disabled' : '' ?>"
                       href="?<?= http_build_query($q_next) ?>#<?= $collapse_id ?>">
                      Next ›
                    </a>
                  </div>
                </div>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot class="table-danger">
        <tr>
          <th></th>
          <th>TOTAL</th>
          <th class="text-end">₱<?= number_format($grand_total, 2) ?></th>
          <th class="text-end">
            Cash on Hand:
            <span class="<?= $coh_class ?>">₱<?= number_format($cash_on_hand, 2) ?></span>
          </th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('tr[data-bs-toggle="collapse"]').forEach(function(row) {
    var targetId = row.getAttribute('data-bs-target');
    var badge = row.querySelector('span[id^="chev-"]');
    if (!badge) return;
    var collapseEl = document.querySelector(targetId);
    if (!collapseEl) return;
    collapseEl.addEventListener('show.bs.collapse', function() { badge.textContent = '▼'; });
    collapseEl.addEventListener('hide.bs.collapse', function() { badge.textContent = '▶'; });
  });

  var openCat = new URLSearchParams(window.location.search).get('open_cat');
  if (openCat) {
    var el = document.getElementById('cat_' + openCat);
    if (el) {
      var collapse = bootstrap.Collapse.getOrCreateInstance(el);
      collapse.show();
    }
  }
});
</script>

<?php else: ?>
<!-- TAB B: DETAILED EXPENSE LIST -->
<div class="card shadow-sm border-0">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Date</th>
          <th>Category</th>
          <th>Description</th>
          <th class="text-end">Amount</th>
          <?php if ($isAdmin): ?><th class="text-end">Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($list_rows)): ?>
          <tr><td colspan="<?= $isAdmin?5:4 ?>" class="text-center text-muted py-4">No expenses found.</td></tr>
        <?php else: ?>
          <?php foreach ($list_rows as $row): ?>
          <tr>
            <td class="text-nowrap"><?= htmlspecialchars($row['sunday_date']) ?></td>
            <td><span class="badge bg-danger bg-opacity-10 text-danger border border-danger"><?= htmlspecialchars($row['category']) ?></span></td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td class="text-end fw-bold text-danger">₱<?= number_format($row['amount'], 2) ?></td>
            <?php if ($isAdmin): ?>
            <td class="text-end text-nowrap">
              <a href="edit_expense.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
              <form method="POST" action="delete_expense.php" class="d-inline" onsubmit="return confirm('Delete this expense?');">
                <input type="hidden" name="expense_id" value="<?= (int)$row['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Del</button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot class="table-light">
        <tr>
          <th colspan="3" class="text-end">Showing <?= count($list_rows) ?> of <?= $total_items ?> · Page total</th>
          <th class="text-end text-danger fs-6">₱<?= number_format($list_total, 2) ?></th>
          <?php if ($isAdmin): ?><th></th><?php endif; ?>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php if ($total_pages > 1): ?>
<nav class="mt-4">
  <ul class="pagination justify-content-center flex-wrap">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link text-danger" href="<?= $page <= 1 ? '#' : page_url($page - 1, 'list') ?>">‹ Prev</a>
    </li>

    <?php
    $pages_to_show = [1];
    for ($i = max(2, $page - 2); $i <= min($total_pages - 1, $page + 2); $i++) {
        $pages_to_show[] = $i;
    }
    if ($total_pages > 1) $pages_to_show[] = $total_pages;
    $pages_to_show = array_unique($pages_to_show);
    sort($pages_to_show);

    $last_shown = 0;
    foreach ($pages_to_show as $p):
        if ($last_shown && $p > $last_shown + 1): ?>
          <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
        <li class="page-item <?= $p == $page ? 'active bg-danger border-danger' : '' ?>">
          <a class="page-link <?= $p == $page ? 'text-white' : 'text-danger' ?>" href="<?= page_url($p, 'list') ?>"><?= $p ?></a>
        </li>
        <?php $last_shown = $p;
    endforeach; ?>

    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
      <a class="page-link text-danger" href="<?= $page >= $total_pages ? '#' : page_url($page + 1, 'list') ?>">Next ›</a>
    </li>
  </ul>

  <p class="text-center text-muted small">
    Showing <strong><?= ($page - 1) * $per_page + 1 ?>–<?= min($page * $per_page, $total_items) ?></strong>
    of <strong><?= $total_items ?></strong> expenses
  </p>
</nav>
<?php endif; ?>
<?php endif; ?>

<?php include 'footer.php'; ?>