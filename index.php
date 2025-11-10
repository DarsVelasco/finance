<?php 
include 'connection.php';
include 'header.php';
?>

<div class="container mt-4">
  <h3 class="text-center mb-4">JCLF - Finance Dashboard</h3>

  <?php
  // Total income
  $income_res = $conn->query("SELECT SUM(amount) AS total_income FROM income_entries");
  $income = $income_res->fetch_assoc()['total_income'] ?? 0;

  // Total expenses
  $expense_res = $conn->query("SELECT SUM(amount) AS total_expenses FROM expense_entries");
  $expense = $expense_res->fetch_assoc()['total_expenses'] ?? 0;

  // Net balance
  $balance = $income - $expense;
  ?>

  <!-- Summary Cards -->
  <div class="row text-center mb-4">
    <div class="col-md-4">
      <div class="card border-success shadow-sm">
        <div class="card-body">
          <h5>Total Income</h5>
          <h3 class="text-success">₱<?= number_format($income, 2) ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-danger shadow-sm">
        <div class="card-body">
          <h5>Total Expenses</h5>
          <h3 class="text-danger">₱<?= number_format($expense, 2) ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-primary shadow-sm">
        <div class="card-body">
          <h5>Cash on Hand</h5>
          <h3 class="<?= $balance >= 0 ? 'text-primary' : 'text-danger' ?>">₱<?= number_format($balance, 2) ?></h3>
        </div>
      </div>
    </div>
  </div>

  <?php
  // Expense breakdown by category (for pie chart)
  $cat_data = $conn->query("
    SELECT c.main_category, SUM(e.amount) AS total
    FROM expense_entries e
    JOIN expense_categories c ON c.id = e.category_id
    GROUP BY c.main_category
  ");

  $categories = [];
  $totals = [];

  while ($row = $cat_data->fetch_assoc()) {
    $categories[] = $row['main_category'];
    $totals[] = $row['total'];
  }

  // Weekly income vs expense (for bar chart)
  $weekly_data = $conn->query("
    SELECT s.sunday_date,
           IFNULL(SUM(i.amount),0) AS total_income,
           IFNULL((
             SELECT SUM(e.amount) 
             FROM expense_entries e 
             WHERE e.sunday_id = s.id
           ),0) AS total_expense
    FROM sundays s
    LEFT JOIN income_entries i ON i.sunday_id = s.id
    GROUP BY s.sunday_date
    ORDER BY s.sunday_date ASC
  ");

  $weeks = [];
  $weekly_income = [];
  $weekly_expense = [];

  while ($row = $weekly_data->fetch_assoc()) {
    $weeks[] = $row['sunday_date'];
    $weekly_income[] = $row['total_income'];
    $weekly_expense[] = $row['total_expense'];
  }
  ?>

  <!-- Charts -->
  <div class="row">
    <div class="col-md-6">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h5 class="text-center">Expense Breakdown by Category</h5>
          <canvas id="expensePieChart"></canvas>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h5 class="text-center">Weekly Income vs Expenses</h5>
          <canvas id="weeklyBarChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Pie Chart for Expenses
  const pieCtx = document.getElementById('expensePieChart');
  new Chart(pieCtx, {
    type: 'pie',
    data: {
      labels: <?= json_encode($categories) ?>,
      datasets: [{
        label: 'Total Expenses',
        data: <?= json_encode($totals) ?>,
        borderWidth: 1
      }]
    }
  });

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
          backgroundColor: 'rgba(75, 192, 192, 0.7)'
        },
        {
          label: 'Expenses',
          data: <?= json_encode($weekly_expense) ?>,
          backgroundColor: 'rgba(255, 99, 132, 0.7)'
        }
      ]
    },
    options: {
      scales: {
        y: { beginAtZero: true }
      }
    }
  });
</script>

<?php include 'footer.php'; ?>
