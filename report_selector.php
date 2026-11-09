<?php
date_default_timezone_set('Asia/Manila');
include 'connection.php';
include 'auth.php';
auth_require_login();

$currentYear  = (int)date('Y');
$currentMonth = (int)date('n');

// Pull counts for a live preview
$totalIncome  = (float)($conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM income_entries")->fetch_assoc()['t'] ?? 0);
$totalExpense = (float)($conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expense_entries")->fetch_assoc()['t'] ?? 0);
$totalEntries = (int)($conn->query("SELECT (SELECT COUNT(*) FROM income_entries) + (SELECT COUNT(*) FROM expense_entries) AS t")->fetch_assoc()['t'] ?? 0);
$minDate = $conn->query("SELECT MIN(sunday_date) AS d FROM sundays")->fetch_assoc()['d'] ?? null;
$maxDate = $conn->query("SELECT MAX(sunday_date) AS d FROM sundays")->fetch_assoc()['d'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Finance Report Generator — JCLF</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f5f7fb; }
    .hero {
      background: linear-gradient(135deg, #1e3a8a, #2563eb);
      color: white;
      padding: 2rem 1rem;
      border-radius: 0 0 24px 24px;
    }
    .card-report {
      border: none;
      border-radius: 18px;
      box-shadow: 0 6px 24px rgba(0,0,0,0.06);
    }
    .stat {
      background: #fff;
      border-radius: 14px;
      padding: 1rem 1.25rem;
      border-left: 5px solid #2563eb;
      height: 100%;
    }
    .stat .label { font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
    .stat .value { font-size: 1.4rem; font-weight: 700; }
    .stat.income  { border-left-color: #16a34a; }
    .stat.expense { border-left-color: #dc2626; }
    .stat.net     { border-left-color: #2563eb; }
    .stat.entries { border-left-color: #9333ea; }
    .preset-btn { font-size: .8rem; }
    .section-hint { font-size: .8rem; color: #64748b; }
    .form-check-label { font-size: .9rem; }
  </style>
</head>
<body>

<div class="hero">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <img src="logo.png" alt="JCLF Logo" width="56" height="56" style="border-radius: 50%; background: white; padding: 4px;">
      <div>
        <h2 class="mb-0">Finance Report Generator</h2>
        <div class="opacity-75 small">Jesus Christ Leads Fellowship · Reports & Exports</div>
      </div>
    </div>
  </div>
</div>

<div class="container my-4">

  <!-- Live snapshot -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat income">
        <div class="label">Total Income</div>
        <div class="value text-success">₱<?= number_format($totalIncome, 2) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat expense">
        <div class="label">Total Expenses</div>
        <div class="value text-danger">₱<?= number_format($totalExpense, 2) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat net">
        <div class="label">All-Time Net</div>
        <div class="value <?= ($totalIncome-$totalExpense) >= 0 ? 'text-primary' : 'text-danger' ?>">
          ₱<?= number_format($totalIncome - $totalExpense, 2) ?>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat entries">
        <div class="label">Entries Recorded</div>
        <div class="value"><?= number_format($totalEntries) ?></div>
        <div class="small text-muted">
          <?= $minDate ? htmlspecialchars($minDate) : '—' ?> → <?= $maxDate ? htmlspecialchars($maxDate) : '—' ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Report builder -->
  <form method="GET" class="card card-report p-4">
    <h5 class="mb-3">1 · Choose period</h5>

    <div class="mb-3 d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-preset="this-month">This Month</button>
      <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-preset="last-month">Last Month</button>
      <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-preset="ytd">Year to Date</button>
      <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-preset="this-year">This Year</button>
      <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-preset="last-year">Last Year</button>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label fw-bold">Month</label>
        <select name="month" id="month" class="form-select">
          <option value="">All Months (Full Year)</option>
          <?php for ($m = 1; $m <= 12; $m++):
              $monthName = date('F', mktime(0, 0, 0, $m, 1));
              $sel = ($m == $currentMonth) ? 'selected' : '';
              echo "<option value='$m' $sel>$monthName</option>";
          endfor; ?>
        </select>
        <div class="section-hint">Pick a specific month, or leave as "All Months" for a full-year report.</div>
      </div>

      <div class="col-md-4">
        <label class="form-label fw-bold">Year</label>
        <select name="year" id="year" class="form-select" required>
          <?php for ($y = $currentYear - 5; $y <= $currentYear + 1; $y++):
              $sel = ($y == $currentYear) ? 'selected' : '';
              echo "<option value='$y' $sel>$y</option>";
          endfor; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label fw-bold">Category filter (optional)</label>
        <select name="category" id="category" class="form-select">
          <option value="">All Categories</option>
          <?php
          $cats = $conn->query("SELECT DISTINCT main_category FROM expense_categories ORDER BY main_category");
          while ($c = $cats->fetch_assoc()) {
              echo "<option value='" . htmlspecialchars($c['main_category']) . "'>"
                 . htmlspecialchars($c['main_category']) . "</option>";
          }
          ?>
        </select>
      </div>
    </div>

    <hr class="my-4">

    <h5 class="mb-3">2 · Include sections</h5>
    <div class="row g-2">
      <div class="col-md-4"><div class="form-check">
        <input class="form-check-input" type="checkbox" name="sec[]" value="summary" id="sec1" checked>
        <label class="form-check-label" for="sec1">Executive Summary</label>
      </div></div>
      <div class="col-md-4"><div class="form-check">
        <input class="form-check-input" type="checkbox" name="sec[]" value="cashflow" id="sec2" checked>
        <label class="form-check-label" for="sec2">Cash Flow Reconciliation</label>
      </div></div>
      <div class="col-md-4"><div class="form-check">
        <input class="form-check-input" type="checkbox" name="sec[]" value="monthly" id="sec3" checked>
        <label class="form-check-label" for="sec3">Monthly Breakdown</label>
      </div></div>
      <div class="col-md-4"><div class="form-check">
        <input class="form-check-input" type="checkbox" name="sec[]" value="income" id="sec4" checked>
        <label class="form-check-label" for="sec4">Income by Type</label>
      </div></div>
      <div class="col-md-4"><div class="form-check">
        <input class="form-check-input" type="checkbox" name="sec[]" value="expense" id="sec5" checked>
        <label class="form-check-label" for="sec5">Expense by Category</label>
      </div></div>
      <div class="col-md-4"><div class="form-check">
        <input class="form-check-input" type="checkbox" name="sec[]" value="weekly" id="sec6" checked>
        <label class="form-check-label" for="sec6">Weekly Breakdown</label>
      </div></div>
      <div class="col-md-4"><div class="form-check">
        <input class="form-check-input" type="checkbox" name="sec[]" value="top" id="sec7" checked>
        <label class="form-check-label" for="sec7">Top 10 Expenses</label>
      </div></div>
      <div class="col-md-4"><div class="form-check">
        <input class="form-check-input" type="checkbox" name="sec[]" value="ledger" id="sec8" checked>
        <label class="form-check-label" for="sec8">Full Transaction Ledger</label>
      </div></div>
      <div class="col-md-4"><div class="form-check">
        <input class="form-check-input" type="checkbox" name="sec[]" value="notes" id="sec9" checked>
        <label class="form-check-label" for="sec9">Notes & Formulas</label>
      </div></div>
    </div>

    <hr class="my-4">

    <h5 class="mb-3">3 · Generate</h5>
    <div class="d-flex flex-wrap gap-2">
      <button type="submit" formaction="generate_pdf.php" class="btn btn-danger">
        📄 Download PDF Report
      </button>
      <button type="submit" formaction="generate_csv.php" class="btn btn-success">
        📥 Download CSV Report
      </button>
      <a href="index.php" class="btn btn-outline-secondary ms-auto">← Back to Dashboard</a>
    </div>
  </form>

  <p class="text-center text-muted small mt-3 mb-0">
    Both exports apply the same filters. The PDF is designed for printing/sharing, the CSV for spreadsheet analysis.
  </p>
</div>

<script>
  // Preset buttons
  document.querySelectorAll('.preset-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      const preset = this.dataset.preset;
      const monthSel = document.getElementById('month');
      const yearSel  = document.getElementById('year');
      const now = new Date();

      const setVal = (el, v) => { el.value = v; };

      if (preset === 'this-month') {
        setVal(monthSel, String(now.getMonth() + 1));
        setVal(yearSel, String(now.getFullYear()));
      } else if (preset === 'last-month') {
        const d = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        setVal(monthSel, String(d.getMonth() + 1));
        setVal(yearSel, String(d.getFullYear()));
      } else if (preset === 'ytd') {
        setVal(monthSel, '');
        setVal(yearSel, String(now.getFullYear()));
      } else if (preset === 'this-year') {
        setVal(monthSel, '');
        setVal(yearSel, String(now.getFullYear()));
      } else if (preset === 'last-year') {
        setVal(monthSel, '');
        setVal(yearSel, String(now.getFullYear() - 1));
      }
    });
  });
</script>

</body>
</html>