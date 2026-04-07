<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Finance Tracker</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="logo.png" alt="Logo" width="40" height="40" class="me-2"> <!-- logo before text -->
      Finance Tracker
    </a>
    <ul class="navbar-nav ms-auto">
      <?php
      if (function_exists('auth_user')) {
        $u = auth_user();
      } else {
        $u = null;
      }
      ?>
      <?php if ($u): ?>
        <li class="nav-item"><a class="nav-link" href="income.php">Income</a></li>
        <li class="nav-item"><a class="nav-link" href="expense.php">Expenses</a></li>
        <li class="nav-item"><a class="nav-link" href="summary.php">Summary</a></li>
        <li class="nav-item"><a class="nav-link" href="report_selector.php">Reports</a></li>
        <li class="nav-item d-flex align-items-center px-2">
          <span class="badge <?= ($u['role'] === 'admin') ? 'bg-success' : 'bg-secondary' ?>">
            <?= htmlspecialchars($u['role']) ?>
          </span>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle text-white-50" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <?= htmlspecialchars($u['username']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">Profile</h6></li>
            <li><a class="dropdown-item" href="profile.php">Profile Settings</a></li>
            <?php if (($u['role'] ?? '') === 'admin'): ?>
              <li><a class="dropdown-item" href="change_password.php">Change Password</a></li>
              <li><a class="dropdown-item" href="manage_users.php">Manage Accounts</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
          </ul>
        </li>
      <?php else: ?>
        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
        <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
      <?php endif; ?>
    </ul>
  </div>
</nav>
<main>
<div class="container my-4">
