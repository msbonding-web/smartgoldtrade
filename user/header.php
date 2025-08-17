<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); // Redirect to main login page
    exit();
}

// Get user info from session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get the current page name for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// --- চূড়ান্ত সমাধান: এটি একটি P2P পেজ কিনা তা চেক করা হচ্ছে ---
$is_p2p_page = in_array($current_page, ['p2p_market.php', 'p2p_view_offer.php', 'p2p_chat.php', 'p2p_start_trade.php']);

// Include the main site header
require_once __DIR__ . '/../header.php';
?>

<!-- কন্ডিশনাল লেআউট: P2P পেজের জন্য সাইডবার বাদ দেওয়া হবে -->
<?php if (!$is_p2p_page): ?>
    <div class="user-dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php">User <strong>Dashboard</strong></a>
            </div>
            <ul class="sidebar-menu">
                <li class="<?php if ($current_page === 'dashboard.php') echo 'active'; ?>"><a href="dashboard.php">Dashboard</a></li>
                <li class="<?php if ($current_page === 'investment_plans.php') echo 'active'; ?>"><a href="investment_plans.php">Investment Plans</a></li>
                <li class="<?php if ($current_page === 'investment_history.php') echo 'active'; ?>"><a href="investment_history.php">Investment History</a></li>
                <li class="<?php if ($current_page === 'trading_platform.php') echo 'active'; ?>"><a href="trading_platform.php">Trading Platform</a></li>
                <li class="<?php if ($current_page === 'ecommerce_orders.php') echo 'active'; ?>"><a href="ecommerce_orders.php">E-commerce Orders</a></li>
                <li class="<?php if ($current_page === 'deposit_withdrawal.php') echo 'active'; ?>"><a href="deposit_withdrawal.php">Deposit & Withdrawal</a></li>
                <li class="<?php if ($current_page === 'referral_system.php') echo 'active'; ?>"><a href="referral_system.php">Referral System</a></li>
                <li class="<?php if ($current_page === 'transactions_transfers.php') echo 'active'; ?>"><a href="transactions_transfers.php">Transactions & Transfers</a></li>
                <li class="<?php if ($current_page === 'gold_conversion.php') echo 'active'; ?>"><a href="gold_conversion.php">Gold Conversion</a></li>
                <li class="<?php if ($is_p2p_page) echo 'active'; ?>"><a href="p2p_market.php">P2P Trading</a></li>
                <li class="<?php if ($current_page === 'support_tickets.php' || $current_page === 'ticket_details.php') echo 'active'; ?>"><a href="support_tickets.php">Support Tickets</a></li>
                <li class="<?php if ($current_page === 'notifications.php') echo 'active'; ?>"><a href="notifications.php">Notifications</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <header class="header">
                <div class="header-title">
                    <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
                    <p>Your personal dashboard.</p>
                </div>
                <div class="user-info">
                    <a href="#">Profile</a>
                </div>
            </header>
            <main>
<?php else: ?>
    <div class="main-content-full-width">
        <main>
<?php endif; ?>
