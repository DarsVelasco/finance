<?php
date_default_timezone_set('Asia/Manila');
include 'connection.php';
include 'auth.php';
auth_require_login();

$currentYear  = (int)date('Y');
$currentMonth = (int)date('n');

$totIncome  = (float)($conn->query("SELECT COALESCE(SUM(amount),0) t FROM donation_income_entries")->fetch_assoc()['t'] ?? 0);
$totExpense = (float)($conn->query("SELECT COALESCE(SUM(amount),0) t FROM donation_expense_entries")->fetch_assoc()['t'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Donation Report Generator — JCLF</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f5f7fb; }
    .hero { background: linear-gradient(135deg, #14532d, #16a34a); color: white;
            padding: 2rem 1rem; border-radius: 0 0 24px 24px; }
    .card-report { border: none; border-radius: 18px; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
  </style>
</head>
<body>
<div class="hero">
  <div class="container d-flex align-items-center gap-3">
    <img src="logo.png" width="56" height="56" style="border-radius:50%;background:white;padding:4px;">
    <div>
      <h2 class="mb-0">Donation Report Generator</h2>
      <div class="opacity-75 small">Separate from main Finance — donations only</div>
    </div>
  </div>
</div>

<div class="container my-4">
  <div class="card card-report p-4">
    <form method="GET">
      <h5 class="mb-3">1 · Period</h5>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">Month</label>
          <select name="month" class="form-select">
            <option value="">All Months (Full Year)</option>
            <?php for ($m=1; $m<=12; $m++):
              $name = date('F', mktime(0,0,0,$m,1));
              $sel = ($m == $currentMonth) ? 'selected' : '';
              echo "<option value='$m' $sel>$name</option>";
            endfor; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-bold">Year</label>
          <select name="year" class="form-select" required>
            <?php for ($y=$currentYear-5; $y<=$currentYear+1; $y++):
              $sel = ($y == $currentYear) ? 'selected' : '';
              echo "<option value='$y' $sel>$y</option>";
            endfor; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-bold">Category filter</label>
          <select name="category" class="form-select">
            <option value="">All Categories</option>
            <?php $r = $conn->query("SELECT main_category FROM donation_categories ORDER BY main_category");
            while ($c = $r->fetch_assoc()) echo '<option>'.htmlspecialchars($c['main_category']).'</option>'; ?>
          </select>
        </div>
      </div>

      <hr class="my-4">
      <h5 class="mb-3">2 · Generate</h5>
      <div class="d-flex flex-wrap gap-2">
        <button type="submit" formaction="donation_generate_pdf.php" class="btn btn-danger">📄 PDF</button>
        <button type="submit" formaction="donation_generate_csv.php" class="btn btn-success">📥 CSV</button>
        <a href="donations.php" class="btn btn-outline-secondary ms-auto">← Back to Donations</a>
      </div>
    </form>
  </div>

  <p class="text-center text-muted small mt-3">
    Current all-time donation totals —
    Income <strong>₱<?= number_format($totIncome, 2) ?></strong> ·
    Expenses <strong>₱<?= number_format($totExpense, 2) ?></strong>
  </p>
</div>
</body>
</html>