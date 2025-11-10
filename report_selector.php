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
    }
    .container {
      background: white;
      padding: 25px 40px;
      border-radius: 12px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      text-align: center;
    }
    select, button, a {
      padding: 10px;
      margin: 10px;
      border-radius: 8px;
      border: 1px solid #ccc;
      font-size: 16px;
      text-decoration: none;
    }
    button {
      background-color: #2563eb;
      color: white;
      border: none;
      cursor: pointer;
    }
    button:hover {
      background-color: #1d4ed8;
    }
    .back-btn {
      display: inline-block;
      background-color: #6b7280;
      color: white;
      border: none;
      cursor: pointer;
    }
    .back-btn:hover {
      background-color: #4b5563;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>📊 Generate Finance Summary Report</h2>

    <form action="generate_pdf.php" method="get">
      <label for="month">Select Month:</label>
      <select name="month" id="month" required>
        <?php
        for ($m = 1; $m <= 12; $m++) {
          $monthName = date('F', mktime(0, 0, 0, $m, 1));
          $selected = ($m == $currentMonth) ? 'selected' : '';
          echo "<option value='$m' $selected>$monthName</option>";
        }
        ?>
      </select>

      <label for="year">Select Year:</label>
      <select name="year" id="year" required>
        <?php
        for ($y = $currentYear - 5; $y <= $currentYear + 1; $y++) {
          $selected = ($y == $currentYear) ? 'selected' : '';
          echo "<option value='$y' $selected>$y</option>";
        }
        ?>
      </select>

      <br>
      <button type="submit">📥 Download Report</button>
    </form>

    <!-- ✅ Back Button -->
    <a href="index.php" class="back-btn">← Return to Dashboard</a>
  </div>
</body>
</html>
