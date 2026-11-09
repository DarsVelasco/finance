<?php 
include 'connection.php';
include 'auth.php';
auth_require_login();
include 'header.php';

// Get selected year from query parameter, default to current year
$selected_year = isset($_GET['year']) && $_GET['year'] !== 'all' ? (int)$_GET['year'] : 'all';

// Get available years from sundays table
$years_result = $conn->query("
    SELECT DISTINCT YEAR(sunday_date) as year 
    FROM sundays 
    ORDER BY year DESC
");
$available_years = [];
if ($years_result) {
    while ($row = $years_result->fetch_assoc()) {
        $available_years[] = $row['year'];
    }
}
?>

<div class="container mt-4">
  <h3 class="text-center mb-4">JCLF - Finance Dashboard</h3>

  <!-- Year Filter -->
  <div class="row mb-4">
    <div class="col-md-6 offset-md-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-8">
              <label for="year" class="form-label fw-bold">Filter by Year:</label>
              <select name="year" id="year" class="form-select">
                <option value="all" <?= $selected_year === 'all' ? 'selected' : '' ?>>All Years (Full Summary)</option>
                <?php foreach ($available_years as $year): ?>
                  <option value="<?= $year ?>" <?= $selected_year == $year ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-funnel"></i> Apply Filter
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php
  // Build sunday_id filter based on selected year
  $sunday_filter = "";
  if ($selected_year !== 'all') {
    $sunday_filter = "AND sunday_id IN (SELECT id FROM sundays WHERE YEAR(sunday_date) = $selected_year)";
  }
  
  // Total income with year filter
  $income_query = "SELECT SUM(amount) AS total_income FROM income_entries WHERE 1=1 $sunday_filter";
  $income_res = $conn->query($income_query);
  $income = ($income_res && ($ti = $income_res->fetch_assoc())) ? ($ti['total_income'] ?? 0) : 0;

  // Total expenses with year filter
  $expense_query = "SELECT SUM(amount) AS total_expenses FROM expense_entries WHERE 1=1 $sunday_filter";
  $expense_res = $conn->query($expense_query);
  $expense = ($expense_res && ($te = $expense_res->fetch_assoc())) ? ($te['total_expenses'] ?? 0) : 0;

  // Net balance
  $balance = $income - $expense;

  // Display current filter info
  $filter_text = ($selected_year === 'all') ? 'All Years' : "Year: $selected_year";
  ?>

  <!-- Summary Cards -->
  <div class="row text-center mb-4">
    <div class="col-md-4">
      <div class="card border-success shadow-sm">
        <div class="card-body">
          <h5>Total Income <small class="text-muted">(<?= $filter_text ?>)</small></h5>
          <h3 class="text-success">₱<?= number_format($income, 2) ?></h3>
        </div>
      </div>
    </div>
  
    <div class="col-md-4">
      <div class="card border-danger shadow-sm">
        <div class="card-body">
          <h5>Total Expenses <small class="text-muted">(<?= $filter_text ?>)</small></h5>
          <h3 class="text-danger">₱<?= number_format($expense, 2) ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-primary shadow-sm">
        <div class="card-body">
          <h5>Cash on Hand <small class="text-muted">(<?= $filter_text ?>)</small></h5>
          <h3 class="<?= $balance >= 0 ? 'text-primary' : 'text-danger' ?>">₱<?= number_format($balance, 2) ?></h3>
        </div>
      </div>
    </div>
  </div>

  <div class="text-center mb-4">
    <a href="report_selector.php<?= $selected_year !== 'all' ? '?year=' . $selected_year : '' ?>" class="btn btn-outline-primary">
      <i class="bi bi-file-earmark-pdf"></i> Download Report
    </a>
  </div>

  <?php
  // Expense breakdown by category (with year filter)
  if ($selected_year !== 'all') {
    $cat_query = "
      SELECT c.main_category, SUM(e.amount) AS total
      FROM expense_entries e
      JOIN expense_categories c ON c.id = e.category_id
      WHERE e.sunday_id IN (SELECT id FROM sundays WHERE YEAR(sunday_date) = $selected_year)
      GROUP BY c.main_category
    ";
  } else {
    $cat_query = "
      SELECT c.main_category, SUM(e.amount) AS total
      FROM expense_entries e
      JOIN expense_categories c ON c.id = e.category_id
      GROUP BY c.main_category
    ";
  }
  $cat_data = $conn->query($cat_query);

  $categories = [];
  $totals = [];

  if ($cat_data) {
    while ($row = $cat_data->fetch_assoc()) {
      $categories[] = $row['main_category'];
      $totals[] = $row['total'];
    }
  }

  // Weekly income vs expense (with year filter)
  if ($selected_year !== 'all') {
    $weekly_query = "
      SELECT s.sunday_date,
             IFNULL(SUM(i.amount),0) AS total_income,
             IFNULL(SUM(e.amount),0) AS total_expense
      FROM sundays s
      LEFT JOIN income_entries i ON i.sunday_id = s.id
      LEFT JOIN expense_entries e ON e.sunday_id = s.id
      WHERE YEAR(s.sunday_date) = $selected_year
      GROUP BY s.sunday_date, s.id
      ORDER BY s.sunday_date ASC
    ";
  } else {
    $weekly_query = "
      SELECT s.sunday_date,
             IFNULL(SUM(i.amount),0) AS total_income,
             IFNULL(SUM(e.amount),0) AS total_expense
      FROM sundays s
      LEFT JOIN income_entries i ON i.sunday_id = s.id
      LEFT JOIN expense_entries e ON e.sunday_id = s.id
      GROUP BY s.sunday_date, s.id
      ORDER BY s.sunday_date ASC
    ";
  }
  
  $weekly_data = $conn->query($weekly_query);

  $weeks = [];
  $weekly_income = [];
  $weekly_expense = [];

  if ($weekly_data) {
    while ($row = $weekly_data->fetch_assoc()) {
      $weeks[] = $row['sunday_date'];
      $weekly_income[] = (float)$row['total_income'];
      $weekly_expense[] = (float)$row['total_expense'];
    }
  }
  ?>

  <!-- Charts -->
  <div class="row">
    <div class="col-md-6">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h5 class="text-center">Expense Breakdown by Category <small class="text-muted">(<?= $filter_text ?>)</small></h5>
          <?php if (empty($categories)): ?>
            <p class="text-center text-muted">No expense data available for this period.</p>
          <?php else: ?>
            <canvas id="expensePieChart"></canvas>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h5 class="text-center">Weekly Income vs Expenses <small class="text-muted">(<?= $filter_text ?>)</small></h5>
          <?php if (empty($weeks)): ?>
            <p class="text-center text-muted">No weekly data available for this period.</p>
          <?php else: ?>
            <canvas id="weeklyBarChart"></canvas>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  <?php if (!empty($categories)): ?>
  // Pie Chart for Expenses
  const pieCtx = document.getElementById('expensePieChart');
  new Chart(pieCtx, {
    type: 'pie',
    data: {
      labels: <?= json_encode($categories) ?>,
      datasets: [{
        label: 'Total Expenses',
        data: <?= json_encode($totals) ?>,
        backgroundColor: [
          '#FF6384',
          '#36A2EB',
          '#FFCE56',
          '#4BC0C0',
          '#9966FF',
          '#FF9F40',
          '#C9CBCF'
        ],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          position: 'bottom'
        }
      }
    }
  });
  <?php endif; ?>

  <?php if (!empty($weeks)): ?>
  // Bar Chart for Weekly Comparison
  const barCtx = document.getElementById('weeklyBarChart');
  new Chart(barCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($weeks) ?>,
      datasets: [
        {
          label: 'Income',
          data: <?= json_encode($weekly_income) ?>,
          backgroundColor: 'rgba(75, 192, 192, 0.7)',
          borderRadius: 5
        },
        {
          label: 'Expenses',
          data: <?= json_encode($weekly_expense) ?>,
          backgroundColor: 'rgba(255, 99, 132, 0.7)',
          borderRadius: 5
        }
      ]
    },
    options: {
      responsive: true,
      scales: {
        y: { 
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return '₱' + value.toLocaleString();
            }
          }
        }
      },
      plugins: {
        tooltip: {
          callbacks: {
            label: function(context) {
              let label = context.dataset.label || '';
              if (label) {
                label += ': ';
              }
              label += '₱' + context.raw.toLocaleString();
              return label;
            }
          }
        }
      }
    }
  });
  <?php endif; ?>
</script>

<?php include 'footer.php'; ?>