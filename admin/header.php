<?php 
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); // Redirect to main login page
    exit();
}

// Check if user has admin roles
$is_admin = false;
if (isset($_SESSION['user_roles'])) {
    if (in_array('admin', $_SESSION['user_roles']) || in_array('super_admin', $_SESSION['user_roles'])) {
        $is_admin = true;
    }
}

// If not an admin, redirect to user dashboard or an access denied page
if (!$is_admin) {
    header("Location: ../user/dashboard.php"); // Redirect to user dashboard
    exit();
}

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smart Gold Trade</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php">Smart Gold <strong>Admin</strong></a>
    </div>
    <ul class="sidebar-menu">
        <li class="<?php if ($current_page === 'dashboard.php') echo 'active'; ?>"><a href="dashboard.php">1. Dashboard Overview</a></li>
        <li class="<?php if ($current_page === 'user_management.php') echo 'active'; ?>"><a href="user_management.php">2. User Management</a></li>
        <li class="<?php if ($current_page === 'kyc_management.php' || $current_page === 'kyc_review.php') echo 'active'; ?>"><a href="kyc_management.php">   - KYC Management</a></li>
        <li class="<?php if ($current_page === 'investment_management.php') echo 'active'; ?>"><a href="investment_management.php">3. Investment Management</a></li>
        <li class="<?php if ($current_page === 'trading_platform.php') echo 'active'; ?>"><a href="trading_platform.php">4. Trading Platform</a></li>
        <li class="<?php if ($current_page === 'ecommerce_management.php') echo 'active'; ?>"><a href="ecommerce_management.php">5. Product & E-commerce</a></li>
        <li class="<?php if ($current_page === 'deposit_management.php') echo 'active'; ?>"><a href="deposit_management.php">6. Deposit Management</a></li>
        <li class="<?php if ($current_page === 'withdrawal_management.php') echo 'active'; ?>"><a href="withdrawal_management.php">7. Withdrawal Management</a></li>
        <li class="<?php if ($current_page === 'referral_management.php') echo 'active'; ?>"><a href="referral_management.php">8. Referral & Commission</a></li>
        <li class="<?php if ($current_page === 'transaction_management.php') echo 'active'; ?>"><a href="transaction_management.php">9. Transaction Management</a></li>
        <li class="<?php if ($current_page === 'gold_conversion_management.php') echo 'active'; ?>"><a href="gold_conversion_management.php">10. Gold Conversion</a></li>
        <li class="<?php if ($current_page === 'p2p_management.php') echo 'active'; ?>"><a href="p2p_management.php">11. P2P Trading</a></li>
        <li class="<?php if ($current_page === 'ticket_management.php') echo 'active'; ?>"><a href="ticket_management.php">12. Support Tickets</a></li>
        <li class="<?php if ($current_page === 'cms_management.php') echo 'active'; ?>"><a href="cms_management.php">13. Content (CMS)</a></li>
        <li class="<?php if ($current_page === 'notification_management.php') echo 'active'; ?>"><a href="notification_management.php">14. Notifications & Communication</a></li>
        <li class="<?php if ($current_page === 'system_settings.php') echo 'active'; ?>"><a href="system_settings.php">15. System & Security</a></li>
        <li class="<?php if ($current_page === 'reports_analytics.php') echo 'active'; ?>"><a href="reports_analytics.php">16. Reports & Analytics</a></li>
        <li class="<?php if ($current_page === 'automation_tools.php') echo 'active'; ?>"><a href="automation_tools.php">17. Automation Tools</a></li>
        <li><a href="../index.php" target="_blank">View Main Site</a></li>
        <li><a href="#">Logout</a></li>
    </ul>
</aside>

<div class="main-content">
    <header class="header">
        <div class="header-title">
            <h2>Welcome, Admin!</h2>
            <p>Here is the real-time overview of your platform.</p>
        </div>
        <div class="user-info">
            <!-- User info can go here -->
        </div>
    </header>

    <main>