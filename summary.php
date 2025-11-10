<?php include 'connection.php'; ?>
<?php include 'header.php'; ?>

<h3 class="mb-4 text-center">JCLF - Finance Summary</h3>

<form method="GET" class="row g-3 mb-4">
  <div class="col-md-3">
    <label>View Type</label>
    <select name="view_type" class="form-control" id="view_type" required>
      <option value="single" <?php echo (!isset($_GET['view_type']) || $_GET['view_type'] == 'single') ? 'selected' : ''; ?>>Single Sunday</option>
      <option value="weekly" <?php echo (isset($_GET['view_type']) && $_GET['view_type'] == 'weekly') ? 'selected' : ''; ?>>Weekly Summary (Up to Date)</option>
      <option value="monthly" <?php echo (isset($_GET['view_type']) && $_GET['view_type'] == 'monthly') ? 'selected' : ''; ?>>Monthly Summary</option>
      <option value="yearly" <?php echo (isset($_GET['view_type']) && $_GET['view_type'] == 'yearly') ? 'selected' : ''; ?>>Yearly Summary</option>
    </select>
  </div>
  <div class="col-md-3" id="date_field">
    <label>Select Sunday Date</label>
    <input type="date" name="sunday_date" class="form-control" value="<?php echo isset($_GET['sunday_date']) ? htmlspecialchars($_GET['sunday_date']) : ''; ?>" required>
  </div>
  <div class="col-md-3" id="year_field" style="display: none;">
    <label>Select Year</label>
    <select name="year" class="form-control">
      <?php
      $current_year = date('Y');
      for ($y = $current_year; $y >= $current_year - 5; $y--) {
        $selected = (isset($_GET['year']) && $_GET['year'] == $y) ? 'selected' : '';
        echo "<option value='$y' $selected>$y</option>";
      }
      ?>
    </select>
  </div>
  <div class="col-md-3 d-flex align-items-end">
    <button type="submit" class="btn btn-primary">View Summary</button>
  </div>
</form>

<script>
document.getElementById('view_type').addEventListener('change', function() {
  const viewType = this.value;
  const dateField = document.getElementById('date_field');
  const yearField = document.getElementById('year_field');
  
  if (viewType === 'yearly') {
    dateField.style.display = 'none';
    dateField.querySelector('input').removeAttribute('required');
    yearField.style.display = 'block';
  } else {
    dateField.style.display = 'block';
    dateField.querySelector('input').setAttribute('required', 'required');
    yearField.style.display = 'none';
  }
});
// Trigger on page load
document.getElementById('view_type').dispatchEvent(new Event('change'));
</script>

<?php
if (isset($_GET['sunday_date']) || (isset($_GET['view_type']) && $_GET['view_type'] == 'yearly' && isset($_GET['year']))) {
    $view_type = isset($_GET['view_type']) ? $_GET['view_type'] : 'single';

    $previous_cash_on_hand = 0;
    $previous_year_balance = 0;
    $is_previous_year = false;

    if ($view_type == 'yearly') {
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $year_start = "$year-01-01";
        $year_end = "$year-12-31";

        // Previous year's ending balance
        $prev_year_end = ($year - 1) . "-12-31";
        $stmt = $conn->prepare("
            SELECT 
                COALESCE((SELECT SUM(amount) FROM income_entries i 
                          INNER JOIN sundays s ON s.id = i.sunday_id 
                          WHERE s.sunday_date <= ?), 0) -
                COALESCE((SELECT SUM(amount) FROM expense_entries e 
                          INNER JOIN sundays s ON s.id = e.sunday_id 
                          WHERE s.sunday_date <= ?), 0) AS cash_on_hand
        ");
        $stmt->bind_param("ss", $prev_year_end, $prev_year_end);
        $stmt->execute();
        $result = $stmt->get_result();
        $prev_row = $result->fetch_assoc();
        $previous_year_balance = $prev_row['cash_on_hand'];
        $stmt->close();

        // Current year totals
        $sql = "
            SELECT 
                COALESCE((SELECT SUM(amount) FROM income_entries i 
                          INNER JOIN sundays s ON s.id = i.sunday_id 
                          WHERE s.sunday_date >= ? AND s.sunday_date <= ?), 0) AS total_income,
                COALESCE((SELECT SUM(amount) FROM expense_entries e 
                          INNER JOIN sundays s ON s.id = e.sunday_id 
                          WHERE s.sunday_date >= ? AND s.sunday_date <= ?), 0) AS total_expense
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $year_start, $year_end, $year_start, $year_end);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $period_label = "Yearly Summary ($year)";

        $current_period_cash = $row['total_income'] - $row['total_expense'];
        $row['cash_on_hand'] = $current_period_cash; // ONLY current year
        $row['previous_cash'] = $previous_year_balance; // previous year separate

    } else {
        // Other views: single, weekly, monthly
        $date = $_GET['sunday_date'];
        $current_year = date('Y', strtotime($date));
        $year_start = "$current_year-01-01";

        if ($view_type == 'weekly') {
            // Weekly up to date
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE((SELECT SUM(amount) FROM income_entries i 
                              INNER JOIN sundays s ON s.id = i.sunday_id 
                              WHERE s.sunday_date < ? AND s.sunday_date >= ?), 0) -
                    COALESCE((SELECT SUM(amount) FROM expense_entries e 
                              INNER JOIN sundays s ON s.id = e.sunday_id 
                              WHERE s.sunday_date < ? AND s.sunday_date >= ?), 0) AS cash_on_hand
            ");
            $stmt->bind_param("ssss", $date, $year_start, $date, $year_start);
            $stmt->execute();
            $result = $stmt->get_result();
            $prev_row = $result->fetch_assoc();
            $previous_cash_on_hand = $prev_row['cash_on_hand'];
            $stmt->close();

            // Previous year ending balance
            $prev_year_end = ($current_year - 1) . "-12-31";
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE((SELECT SUM(amount) FROM income_entries i 
                              INNER JOIN sundays s ON s.id = i.sunday_id 
                              WHERE s.sunday_date <= ?), 0) -
                    COALESCE((SELECT SUM(amount) FROM expense_entries e 
                              INNER JOIN sundays s ON s.id = e.sunday_id 
                              WHERE s.sunday_date <= ?), 0) AS cash_on_hand
            ");
            $stmt->bind_param("ss", $prev_year_end, $prev_year_end);
            $stmt->execute();
            $result = $stmt->get_result();
            $prev_year_row = $result->fetch_assoc();
            $previous_year_balance = $prev_year_row['cash_on_hand'];
            $stmt->close();

            if ($previous_year_balance > 0) {
                $previous_cash_on_hand += $previous_year_balance;
                $is_previous_year = true;
            }

            $sql = "
                SELECT 
                    COALESCE((SELECT SUM(amount) FROM income_entries i 
                              INNER JOIN sundays s ON s.id = i.sunday_id 
                              WHERE s.sunday_date <= ? AND s.sunday_date >= ?), 0) AS total_income,
                    COALESCE((SELECT SUM(amount) FROM expense_entries e 
                              INNER JOIN sundays s ON s.id = e.sunday_id 
                              WHERE s.sunday_date <= ? AND s.sunday_date >= ?), 0) AS total_expense
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $date, $year_start, $date, $year_start);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            $period_label = "Weekly Summary (Up to " . date('F d, Y', strtotime($date)) . ")";

        } elseif ($view_type == 'monthly') {
            $month_start = date('Y-m-01', strtotime($date));
            $month_end = date('Y-m-t', strtotime($date));
            $month_year = date('Y', strtotime($date));

            // Previous month cash
            $prev_month_end = date('Y-m-t', strtotime($month_start . ' -1 month'));
            $prev_month_year = date('Y', strtotime($prev_month_end));
            if ($prev_month_year == $month_year) {
                $stmt = $conn->prepare("
                    SELECT 
                        COALESCE((SELECT SUM(amount) FROM income_entries i 
                                  INNER JOIN sundays s ON s.id = i.sunday_id 
                                  WHERE s.sunday_date <= ? AND s.sunday_date >= ?), 0) -
                        COALESCE((SELECT SUM(amount) FROM expense_entries e 
                                  INNER JOIN sundays s ON s.id = e.sunday_id 
                                  WHERE s.sunday_date <= ? AND s.sunday_date >= ?), 0) AS cash_on_hand
                ");
                $year_start_month = "$month_year-01-01";
                $stmt->bind_param("ssss", $prev_month_end, $year_start_month, $prev_month_end, $year_start_month);
                $stmt->execute();
                $result = $stmt->get_result();
                $prev_row = $result->fetch_assoc();
                $previous_cash_on_hand = $prev_row['cash_on_hand'];
                $stmt->close();
            }

            // Previous year balance if January
            if ($month_year > date('Y', strtotime($prev_month_end))) {
                $prev_year_end = ($month_year - 1) . "-12-31";
                $stmt = $conn->prepare("
                    SELECT 
                        COALESCE((SELECT SUM(amount) FROM income_entries i 
                                  INNER JOIN sundays s ON s.id = i.sunday_id 
                                  WHERE s.sunday_date <= ?), 0) -
                        COALESCE((SELECT SUM(amount) FROM expense_entries e 
                                  INNER JOIN sundays s ON s.id = e.sunday_id 
                                  WHERE s.sunday_date <= ?), 0) AS cash_on_hand
                ");
                $stmt->bind_param("ss", $prev_year_end, $prev_year_end);
                $stmt->execute();
                $result = $stmt->get_result();
                $prev_year_row = $result->fetch_assoc();
                $previous_year_balance = $prev_year_row['cash_on_hand'];
                $stmt->close();

                if ($previous_year_balance > 0) {
                    $previous_cash_on_hand += $previous_year_balance;
                    $is_previous_year = true;
                }
            }

            $sql = "
                SELECT 
                    COALESCE((SELECT SUM(amount) FROM income_entries i 
                              INNER JOIN sundays s ON s.id = i.sunday_id 
                              WHERE s.sunday_date >= ? AND s.sunday_date <= ?), 0) AS total_income,
                    COALESCE((SELECT SUM(amount) FROM expense_entries e 
                              INNER JOIN sundays s ON s.id = e.sunday_id 
                              WHERE s.sunday_date >= ? AND s.sunday_date <= ?), 0) AS total_expense
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $month_start, $month_end, $month_start, $month_end);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            $period_label = "Monthly Summary (" . date('F Y', strtotime($date)) . ")";

        } else {
            // Single Sunday
            $sql = "
                SELECT 
                    COALESCE((SELECT SUM(amount) FROM income_entries i 
                              INNER JOIN sundays s ON s.id = i.sunday_id 
                              WHERE s.sunday_date = ?), 0) AS total_income,
                    COALESCE((SELECT SUM(amount) FROM expense_entries e 
                              INNER JOIN sundays s ON s.id = e.sunday_id 
                              WHERE s.sunday_date = ?), 0) AS total_expense
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $date, $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            // Previous Sunday's cash
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE((SELECT SUM(amount) FROM income_entries i 
                              INNER JOIN sundays s ON s.id = i.sunday_id 
                              WHERE s.sunday_date < ? AND s.sunday_date >= ?), 0) -
                    COALESCE((SELECT SUM(amount) FROM expense_entries e 
                              INNER JOIN sundays s ON s.id = e.sunday_id 
                              WHERE s.sunday_date < ? AND s.sunday_date >= ?), 0) AS cash_on_hand
            ");
            $stmt->bind_param("ssss", $date, $year_start, $date, $year_start);
            $stmt->execute();
            $result = $stmt->get_result();
            $prev_row = $result->fetch_assoc();
            $previous_cash_on_hand = $prev_row['cash_on_hand'];
            $stmt->close();

            // Previous year balance
            $prev_year_end = ($current_year - 1) . "-12-31";
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE((SELECT SUM(amount) FROM income_entries i 
                              INNER JOIN sundays s ON s.id = i.sunday_id 
                              WHERE s.sunday_date <= ?), 0) -
                    COALESCE((SELECT SUM(amount) FROM expense_entries e 
                              INNER JOIN sundays s ON s.id = e.sunday_id 
                              WHERE s.sunday_date <= ?), 0) AS cash_on_hand
            ");
            $stmt->bind_param("ss", $prev_year_end, $prev_year_end);
            $stmt->execute();
            $result = $stmt->get_result();
            $prev_year_row = $result->fetch_assoc();
            $previous_year_balance = $prev_year_row['cash_on_hand'];
            $stmt->close();

            if ($previous_year_balance > 0) {
                $previous_cash_on_hand += $previous_year_balance;
                $is_previous_year = true;
            }

            $period_label = "Summary for " . date('F d, Y', strtotime($date));
        }

        $current_period_cash = $row['total_income'] - $row['total_expense'];
        $row['cash_on_hand'] = $previous_cash_on_hand + $current_period_cash;
        $row['previous_cash'] = $previous_cash_on_hand;
    }
?>

<div class="mb-3">
    <h4 class="text-center"><?php echo $period_label; ?></h4>
    <?php if ($view_type == 'yearly' && $previous_year_balance > 0): ?>
        <p class="text-center text-warning"><strong>Previous Year Balance: ₱<?php echo number_format($previous_year_balance, 2); ?></strong></p>
    <?php elseif ($previous_cash_on_hand > 0): ?>
        <p class="text-center text-muted">Carry-over from previous period: ₱<?php echo number_format($previous_cash_on_hand, 2); ?></p>
    <?php endif; ?>
</div>

<div class="row text-center">
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5>Total Income</h5>
                <h3>₱<?php echo number_format($row['total_income'], 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h5>Total Expenses</h5>
                <h3>₱<?php echo number_format($row['total_expense'], 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5>Cash on Hand</h5>
                <h3>₱<?php echo number_format($row['cash_on_hand'], 2); ?></h3>
                <?php if ($view_type != 'yearly' && $previous_cash_on_hand > 0): ?>
                    <small>(₱<?php echo number_format($previous_cash_on_hand, 2); ?> carry-over + ₱<?php echo number_format($current_period_cash, 2); ?> current)</small>
                <?php elseif ($view_type == 'yearly' && $previous_year_balance > 0): ?>
                    <small>(Current Year: ₱<?php echo number_format($current_period_cash, 2); ?>)</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php } ?>

<?php include 'footer.php'; ?>
