<?php
include 'connection.php';
include 'auth.php';
auth_require_login();
?>
<?php include 'header.php'; ?>

<h3 class="mb-4 text-center">JCLF - Finance Summary</h3>

<?php
// ============================================================
// STEP 1: Read and normalize the request
// ============================================================
$view_type = $_GET['view_type'] ?? 'single';
$date      = $_GET['sunday_date'] ?? date('Y-m-d');
$year      = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// ============================================================
// STEP 2: Helpers — plain-English labels & period bounds
// ============================================================

/**
 * Returns [start_date, end_date, period_label, mode_description]
 * based on the selected view type.
 */
function resolve_period(string $view_type, string $date, int $year): array
{
    if ($view_type === 'yearly') {
        $start = "$year-01-01";
        $end   = "$year-12-31";
        return [
            $start,
            $end,
            "Year $year",
            "Totals for the entire calendar year $year.",
        ];
    }

    if ($view_type === 'monthly') {
        $start = date('Y-m-01', strtotime($date));
        $end   = date('Y-m-t', strtotime($date));
        return [
            $start,
            $end,
            date('F Y', strtotime($date)),
            "Totals for the month of " . date('F Y', strtotime($date)) . ".",
        ];
    }

    if ($view_type === 'weekly') {
        $start = date('Y-01-01', strtotime($date));
        $end   = $date;
        return [
            $start,
            $end,
            "Jan 1 – " . date('M j, Y', strtotime($date)),
            "Running totals from the start of " . date('Y', strtotime($date)) . " up to " . date('M j, Y', strtotime($date)) . ".",
        ];
    }

    // default: single
    return [
        $date,
        $date,
        date('F j, Y', strtotime($date)),
        "Totals for the single Sunday of " . date('F j, Y', strtotime($date)) . ".",
    ];
}

/**
 * Returns the sum of (income - expense) for all sundays strictly
 * BEFORE $start_date. This is the "carry-over" balance.
 */
function carry_over_before($conn, string $start_date): float
{
    $stmt = $conn->prepare("
        SELECT
            COALESCE((SELECT SUM(i.amount)
                      FROM income_entries i
                      JOIN sundays s ON s.id = i.sunday_id
                      WHERE s.sunday_date < ?), 0)
          - COALESCE((SELECT SUM(e.amount)
                      FROM expense_entries e
                      JOIN sundays s ON s.id = e.sunday_id
                      WHERE s.sunday_date < ?), 0) AS cash
    ");
    $stmt->bind_param("ss", $start_date, $start_date);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();
    return (float)($row['cash'] ?? 0);
}

/**
 * Returns [total_income, total_expense, category_breakdown, top_expenses]
 * for the given period [start, end].
 */
function period_totals($conn, string $start, string $end): array
{
    // Income + expense totals
    $stmt = $conn->prepare("
        SELECT
            COALESCE((SELECT SUM(i.amount)
                      FROM income_entries i
                      JOIN sundays s ON s.id = i.sunday_id
                      WHERE s.sunday_date BETWEEN ? AND ?), 0) AS total_income,
            COALESCE((SELECT SUM(e.amount)
                      FROM expense_entries e
                      JOIN sundays s ON s.id = e.sunday_id
                      WHERE s.sunday_date BETWEEN ? AND ?), 0) AS total_expense
    ");
    $stmt->bind_param("ssss", $start, $end, $start, $end);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    // Category breakdown
    $stmt = $conn->prepare("
        SELECT c.main_category, SUM(e.amount) AS total, COUNT(e.id) AS items
        FROM expense_entries e
        JOIN expense_categories c ON c.id = e.category_id
        JOIN sundays s ON s.id = e.sunday_id
        WHERE s.sunday_date BETWEEN ? AND ?
        GROUP BY c.main_category
        ORDER BY total DESC
    ");
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    $cats = db_stmt_fetch_all_assoc($stmt);
    $stmt->close();

    // Top 5 individual expenses
    $stmt = $conn->prepare("
        SELECT s.sunday_date, c.main_category, e.description, e.amount
        FROM expense_entries e
        JOIN expense_categories c ON c.id = e.category_id
        JOIN sundays s ON s.id = e.sunday_id
        WHERE s.sunday_date BETWEEN ? AND ?
        ORDER BY e.amount DESC
        LIMIT 5
    ");
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    $top = db_stmt_fetch_all_assoc($stmt);
    $stmt->close();

    return [
        (float)$row['total_income'],
        (float)$row['total_expense'],
        $cats,
        $top,
    ];
}
?>

<!-- ============================================================ -->
<!-- FILTER FORM                                                   -->
<!-- ============================================================ -->
<form method="GET" class="card shadow-sm mb-4">
  <div class="card-body">
    <div class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label small mb-1 fw-bold">View</label>
        <select name="view_type" class="form-select" id="view_type">
          <option value="single"  <?= $view_type==='single'?'selected':''  ?>>Single Sunday</option>
          <option value="weekly"  <?= $view_type==='weekly'?'selected':''  ?>>Year-to-Date (weekly running total)</option>
          <option value="monthly" <?= $view_type==='monthly'?'selected':'' ?>>Monthly</option>
          <option value="yearly"  <?= $view_type==='yearly'?'selected':''  ?>>Yearly</option>
        </select>
      </div>

      <div class="col-md-3" id="date_field" style="<?= $view_type==='yearly'?'display:none':'' ?>">
        <label class="form-label small mb-1 fw-bold">Pick a date</label>
        <input type="date" name="sunday_date" class="form-control"
               value="<?= htmlspecialchars($date) ?>">
        <div class="form-text">
          <?= $view_type==='monthly' ? 'Any date in the month you want.' : ($view_type==='weekly' ? 'Cut-off Sunday.' : 'The specific Sunday.') ?>
        </div>
      </div>

      <div class="col-md-3" id="year_field" style="<?= $view_type==='yearly'?'':'display:none' ?>">
        <label class="form-label small mb-1 fw-bold">Year</label>
        <select name="year" class="form-select">
          <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 5; $y--): ?>
            <option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="col-md-3">
        <button type="submit" class="btn btn-primary w-100">View Summary</button>
      </div>
    </div>
  </div>
</form>

<script>
document.getElementById('view_type').addEventListener('change', function () {
  const v = this.value;
  document.getElementById('date_field').style.display = (v === 'yearly') ? 'none' : '';
  document.getElementById('year_field').style.display = (v === 'yearly') ? '' : 'none';
});
</script>

<?php
// ============================================================
// STEP 3: Only compute if a view was actually requested
// ============================================================
if (isset($_GET['view_type']) || isset($_GET['sunday_date'])):

    [$start, $end, $period_label, $mode_description] = resolve_period($view_type, $date, $year);
    $carry_over = carry_over_before($conn, $start);

    [$total_income, $total_expense, $cat_breakdown, $top_expenses] = period_totals($conn, $start, $end);
    $net = $total_income - $total_expense;
    $cash_on_hand = $carry_over + $net;

    // Format labels
    $period_display = date('M j, Y', strtotime($start));
    if ($start !== $end) {
        $period_display .= ' – ' . date('M j, Y', strtotime($end));
    }
?>

<!-- ============================================================ -->
<!-- PERIOD CONTEXT BANNER                                         -->
<!-- ============================================================ -->
<div class="alert alert-info d-flex align-items-start gap-3 mb-4">
  <div class="fs-3">ℹ️</div>
  <div>
    <div class="fw-bold"><?= htmlspecialchars($period_label) ?></div>
    <div class="small"><?= htmlspecialchars($mode_description) ?></div>
    <div class="small text-muted mt-1">
      Included dates: <strong><?= htmlspecialchars($period_display) ?></strong>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- 4 SUMMARY CARDS                                              -->
<!-- ============================================================ -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card border-success shadow-sm h-100">
      <div class="card-body text-center">
        <div class="text-success small fw-bold text-uppercase">Income</div>
        <div class="display-6 fw-bold text-success">₱<?= number_format($total_income, 2) ?></div>
        <div class="text-muted small">Money received this period</div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card border-danger shadow-sm h-100">
      <div class="card-body text-center">
        <div class="text-danger small fw-bold text-uppercase">Expenses</div>
        <div class="display-6 fw-bold text-danger">₱<?= number_format($total_expense, 2) ?></div>
        <div class="text-muted small">Money spent this period</div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card border-<?= $net >= 0 ? 'primary' : 'warning' ?> shadow-sm h-100">
      <div class="card-body text-center">
        <div class="small fw-bold text-uppercase text-<?= $net >= 0 ? 'primary' : 'warning' ?>">Net Change</div>
        <div class="display-6 fw-bold text-<?= $net >= 0 ? 'primary' : 'warning' ?>">
          ₱<?= number_format($net, 2) ?>
        </div>
        <div class="text-muted small">Income − Expenses</div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card bg-dark text-white shadow-sm h-100">
      <div class="card-body text-center">
        <div class="small fw-bold text-uppercase opacity-75">Cash on Hand</div>
        <div class="display-6 fw-bold">₱<?= number_format($cash_on_hand, 2) ?></div>
        <div class="small opacity-75 mt-1">
          <?php if ($carry_over != 0): ?>
            ₱<?= number_format($carry_over, 2) ?> carry-over
            + ₱<?= number_format($net, 2) ?> net
          <?php else: ?>
            = Net change (no prior balance)
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- THE MATH — EXPLAINED                                         -->
<!-- ============================================================ -->
<div class="card shadow-sm mb-4">
  <div class="card-header bg-light fw-bold">How "Cash on Hand" is calculated</div>
  <div class="card-body">
    <table class="table table-sm mb-0">
      <tbody>
        <tr>
          <td>Carry-over from before <?= htmlspecialchars($period_display) ?></td>
          <td class="text-end">₱<?= number_format($carry_over, 2) ?></td>
        </tr>
        <tr>
          <td>+ Net change this period (income − expenses)</td>
          <td class="text-end">₱<?= number_format($net, 2) ?></td>
        </tr>
        <tr class="table-dark">
          <th>= Cash on Hand</th>
          <th class="text-end">₱<?= number_format($cash_on_hand, 2) ?></th>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ============================================================ -->
<!-- CATEGORY BREAKDOWN                                           -->
<!-- ============================================================ -->
<div class="card shadow-sm mb-4">
  <div class="card-header bg-light fw-bold d-flex justify-content-between">
    <span>Expenses by Category</span>
    <span class="text-muted small fw-normal"><?= count($cat_breakdown) ?> categories · ₱<?= number_format($total_expense, 2) ?> total</span>
  </div>
  <div class="card-body p-0">
    <?php if (empty($cat_breakdown)): ?>
      <div class="text-center text-muted py-4">No expenses recorded for this period.</div>
    <?php else: ?>
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Category</th>
            <th class="text-center" style="width: 90px;">Items</th>
            <th style="width: 45%;">Share</th>
            <th class="text-end" style="width: 160px;">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cat_breakdown as $c):
            $amt = (float)$c['total'];
            $pct = $total_expense > 0 ? ($amt / $total_expense) * 100 : 0;
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($c['main_category']) ?></strong></td>
            <td class="text-center"><?= (int)$c['items'] ?></td>
            <td>
              <div class="progress" style="height: 20px;">
                <div class="progress-bar bg-danger" style="width: <?= number_format($pct, 1) ?>%;">
                  <?= $pct >= 12 ? number_format($pct, 0) . '%' : '' ?>
                </div>
              </div>
            </td>
            <td class="text-end">₱<?= number_format($amt, 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-secondary">
          <tr>
            <th>TOTAL</th>
            <th class="text-center"><?= array_sum(array_column($cat_breakdown, 'items')) ?></th>
            <th></th>
            <th class="text-end">₱<?= number_format($total_expense, 2) ?></th>
          </tr>
        </tfoot>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- ============================================================ -->
<!-- TOP 5 EXPENSES                                               -->
<!-- ============================================================ -->
<?php if (!empty($top_expenses)): ?>
<div class="card shadow-sm mb-4">
  <div class="card-header bg-light fw-bold">Top 5 Expenses This Period</div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0">
      <thead class="table-light">
        <tr>
          <th>Date</th>
          <th>Category</th>
          <th>Description</th>
          <th class="text-end">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($top_expenses as $t): ?>
        <tr>
          <td><?= htmlspecialchars($t['sunday_date']) ?></td>
          <td><?= htmlspecialchars($t['main_category']) ?></td>
          <td><?= htmlspecialchars($t['description'] ?: '—') ?></td>
          <td class="text-end">₱<?= number_format($t['amount'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php endif; // end if view requested ?>

<?php include 'footer.php'; ?>