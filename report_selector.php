<?php
date_default_timezone_set('Asia/Manila');
$currentYear = date('Y');
$currentMonth = date('n');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Finance Report Generator</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f8fafc;
      color: #333;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
    }
    .container {
      background: white;
      padding: 25px 40px;
      border-radius: 12px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      text-align: center;
      width: 420px;
      max-width: 90%;
    }
    h2 {
      margin-bottom: 20px;
    }
    select, button, a {
      padding: 10px;
      margin: 8px 0;
      border-radius: 8px;
      border: 1px solid #ccc;
      font-size: 16px;
      width: 100%;
      box-sizing: border-box;
      text-decoration: none;
    }
    label {
      font-weight: bold;
      display: block;
      margin-top: 10px;
      margin-bottom: 5px;
      text-align: left;
    }
    button {
      background-color: #2563eb;
      color: white;
      border: none;
      cursor: pointer;
      transition: background-color 0.2s ease;
    }
    button:hover {
      background-color: #1d4ed8;
    }
    .csv-btn {
      background-color: #16a34a;
    }
    .csv-btn:hover {
      background-color: #15803d;
    }
    .back-btn {
      display: inline-block;
      background-color: #6b7280;
      color: white;
      border: none;
      cursor: pointer;
      transition: background-color 0.2s ease;
      width: 100%;
      text-align: center;
    }
    .back-btn:hover {
      background-color: #4b5563;
    }
    .btn-group {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-top: 15px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>📊 Finance Report Generator</h2>

    <form method="GET" id="reportForm">
      <!-- Month Selector -->
      <label for="month">Select Month:</label>
      <select name="month" id="month">
        <option value="">All Months (Full Year)</option>
        <?php
        for ($m = 1; $m <= 12; $m++) {
          $monthName = date('F', mktime(0, 0, 0, $m, 1));
          $selected = ($m == $currentMonth) ? 'selected' : '';
          echo "<option value='$m' $selected>$monthName</option>";
        }
        ?>
      </select>

      <!-- Year Selector -->
      <label for="year">Select Year:</label>
      <select name="year" id="year" required>
        <?php
        for ($y = $currentYear - 5; $y <= $currentYear + 1; $y++) {
          $selected = ($y == $currentYear) ? 'selected' : '';
          echo "<option value='$y' $selected>$y</option>";
        }
        ?>
      </select>

      <!-- Category Selector -->
      <label for="category">Select Category:</label>
      <select name="category" id="category">
        <option value="">All Categories</option>
        <option value="MANAGEMENT">MANAGEMENT</option>
        <option value="ADMINISTRATION">ADMINISTRATION</option>
        <option value="DISCIPLESHIP">DISCIPLESHIP</option>
        <option value="WORSHIP">WORSHIP</option>
        <option value="MINISTRY OF MINISTRIES">MINISTRY OF MINISTRIES</option>
        <option value="FELLOWSHIP">FELLOWSHIP</option>
        <option value="EVANGELISM">EVANGELISM</option>
      </select>

      <div class="btn-group">
        <button type="submit" formaction="generate_pdf.php">📄 Download PDF Report</button>
        <button type="submit" formaction="generate_csv.php" class="csv-btn">📥 Download CSV Report</button>
      </div>
    </form>

    <!-- ✅ Back Button -->
    <a href="index.php" class="back-btn">← Return to Dashboard</a>
  </div>
</body>
</html>
