<?php
if (!function_exists('auth_user')) {
    require_once __DIR__ . '/auth.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Finance Tracker | JCLF</title>
  <!-- Bootstrap 5.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="style.css" rel="stylesheet">
  
  <style>
    /* Custom Navbar Styling */
    .navbar-custom {
      background: linear-gradient(135deg, #1e293b, #0f172a);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
      padding: 0.6rem 1rem;
      border-bottom: 3px solid #2563eb;
    }
    
    .navbar-brand {
      font-weight: 800;
      letter-spacing: 0.5px;
      font-size: 1.4rem;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .navbar-brand img {
      border-radius: 50%;
      border: 2px solid rgba(255,255,255,0.2);
      box-shadow: 0 0 10px rgba(255,255,255,0.1);
      background-color: white;
      padding: 2px;
    }
    
    .nav-link {
      font-weight: 500;
      padding: 0.5rem 1rem !important;
      border-radius: 8px;
      transition: all 0.2s ease;
      color: rgba(255,255,255,0.8) !important;
    }
    
    .nav-link:hover, .nav-link.active {
      color: #fff !important;
      background-color: rgba(255, 255, 255, 0.1);
    }

    .dropdown-menu {
      border: none;
      box-shadow: 0 10px 30px rgba(0,0,0,0.15);
      border-radius: 12px;
      padding: 0.5rem;
      margin-top: 10px !important;
      animation: fadeInDown 0.2s ease-out;
    }
    
    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .dropdown-item {
      border-radius: 8px;
      padding: 0.6rem 1rem;
      font-weight: 500;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .dropdown-item:hover {
      background-color: #f1f5f9;
      color: #2563eb;
      transform: translateX(5px);
    }

    .dropdown-item i {
      font-size: 1.1rem;
    }

    .user-profile-btn {
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(255,255,255,0.1);
      border-radius: 50px;
      padding: 4px 15px 4px 4px !important;
      transition: all 0.2s;
    }

    .user-profile-btn:hover {
      background: rgba(255,255,255,0.2);
    }

    .avatar-circle {
      width: 32px;
      height: 32px;
      background: linear-gradient(45deg, #2563eb, #3b82f6);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 0.9rem;
      box-shadow: 0 2px 10px rgba(37, 99, 235, 0.4);
    }

    .role-badge {
      font-size: 0.65rem;
      padding: 0.3em 0.6em;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-radius: 6px;
      font-weight: 700;
    }
  </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
  <div class="container-fluid px-4">
    
    <!-- Logo & Brand -->
    <a class="navbar-brand text-white" href="index.php">
      <img src="logo.png" alt="Logo" width="42" height="42">
      <div class="d-flex flex-column" style="line-height: 1.1;">
        <span>JCLF Finance</span>
        <small class="text-white-50 fw-normal" style="font-size: 0.65rem; letter-spacing: 1px;">JESUS CHRIST LEADS FELLOWSHIP</small>
      </div>
    </a>

    <!-- Mobile Toggle -->
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
            data-bs-target="#mainNavbar" aria-controls="mainNavbar"
            aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Navbar Links -->
    <div class="collapse navbar-collapse" id="mainNavbar">
      <?php
      if (function_exists('auth_user')) {
        $u = auth_user();
      } else {
        $u = null;
      }
      ?>
      
      <?php if ($u): ?>
      <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-1">
        
        <!-- Main Dashboard -->
        <li class="nav-item">
          <a class="nav-link" href="index.php">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
          </a>
        </li>

        <!-- Income & Expenses -->
        <li class="nav-item">
          <a class="nav-link" href="income.php">
            <i class="bi bi-arrow-down-circle me-1"></i> Income
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="expense.php">
            <i class="bi bi-arrow-up-circle me-1"></i> Expenses
          </a>
        </li>

        <!-- Donations Dropdown -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-heart-fill text-danger me-1"></i> Donations/Love Gifts
          </a>
          <ul class="dropdown-menu">
            <li><h6 class="dropdown-header text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Donation Track</h6></li>
            <li><a class="dropdown-item" href="donations.php"><i class="bi bi-grid-1x2 text-primary"></i> Dashboard</a></li>
            <li><a class="dropdown-item" href="add_donation_income.php"><i class="bi bi-plus-circle text-success"></i> Add Income</a></li>
            <li><a class="dropdown-item" href="add_donation_expense.php"><i class="bi bi-dash-circle text-danger"></i> Add Expense</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="donation_report_selector.php"><i class="bi bi-file-earmark-pdf text-secondary"></i> Generate Report</a></li>
          </ul>
        </li>

        <!-- Reports & Tools -->
        <li class="nav-item">
          <a class="nav-link" href="summary.php">
            <i class="bi bi-pie-chart me-1"></i> Summary
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="report_selector.php">
            <i class="bi bi-file-earmark-bar-graph me-1"></i> Reports
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="import.php">
            <i class="bi bi-cloud-arrow-up me-1"></i> Import
          </a>
        </li>
      </ul>

      <!-- Right Side: Profile & Role -->
      <ul class="navbar-nav align-items-lg-center gap-2">
        <li class="nav-item">
          <span class="badge role-badge <?= ($u['role'] === 'admin') ? 'bg-success' : 'bg-secondary' ?>">
            <i class="bi bi-shield-lock-fill me-1"></i><?= htmlspecialchars($u['role']) ?>
          </span>
        </li>
        
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle user-profile-btn" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="avatar-circle">
              <?= strtoupper(substr(htmlspecialchars($u['username']), 0, 1)) ?>
            </div>
            <span class="text-white fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($u['username']) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">Account Settings</h6></li>
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-gear text-secondary"></i> Profile Settings</a></li>
            
            <?php if (($u['role'] ?? '') === 'admin'): ?>
              <li><a class="dropdown-item" href="change_password.php"><i class="bi bi-key text-secondary"></i> Change Password</a></li>
              <li><a class="dropdown-item" href="manage_users.php"><i class="bi bi-people text-secondary"></i> Manage Accounts</a></li>
            <?php endif; ?>
            
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger fw-bold" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
          </ul>
        </li>
      </ul>
      
      <?php else: ?>
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i> Login</a></li>
        <li class="nav-item"><a class="nav-link" href="register.php"><i class="bi bi-person-plus me-1"></i> Register</a></li>
      </ul>
      <?php endif; ?>
      
    </div>
  </div>
</nav>

<main>
<!-- Main content container starts here -->
<div class="container my-4">