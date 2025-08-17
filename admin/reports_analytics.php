<?php
require_once 'header.php';
require_once '../db_connect.php';

// Fetch data for Overview/Dashboard tab
$total_users_query = "SELECT COUNT(*) as total_users FROM users";
$total_users = $conn->query($total_users_query)->fetch_assoc()['total_users'];

$total_deposits_query = "SELECT SUM(amount) as total_deposits FROM deposits WHERE status = 'completed'";
$total_deposits = $conn->query($total_deposits_query)->fetch_assoc()['total_deposits'] ?? 0;

$total_withdrawals_query = "SELECT SUM(amount) as total_withdrawals FROM withdrawals WHERE status = 'paid'";
$total_withdrawals = $conn->query($total_withdrawals_query)->fetch_assoc()['total_withdrawals'] ?? 0;

$total_investments_query = "SELECT SUM(amount) as total_investments FROM investments WHERE status = 'active' OR status = 'completed'";
$total_investments = $conn->query($total_investments_query)->fetch_assoc()['total_investments'] ?? 0;

$total_p2p_trades_query = "SELECT COUNT(*) as total_p2p_trades FROM p2p_trades WHERE status = 'released'";
$total_p2p_trades = $conn->query($total_p2p_trades_query)->fetch_assoc()['total_p2p_trades'];

$total_p2p_volume_query = "SELECT SUM(amount) as total_p2p_volume FROM p2p_trades WHERE status = 'released'";
$total_p2p_volume = $conn->query($total_p2p_volume_query)->fetch_assoc()['total_p2p_volume'] ?? 0;

// Fetch data for P2P Report tab
$p2p_start_date = $_GET['p2p_start_date'] ?? '';
$p2p_end_date = $_GET['p2p_end_date'] ?? '';
$p2p_user_id = $_GET['p2p_user_id'] ?? '';

$p2p_report_query = "
    SELECT pt.*, b.username as buyer_username, s.username as seller_username, c.code as currency_code
    FROM p2p_trades pt
    JOIN users b ON pt.buyer_id = b.id
    JOIN users s ON pt.seller_id = s.id
    JOIN currencies c ON pt.currency_id = c.id
";

$p2p_where_clauses = [];
$p2p_params = [];
$p2p_types = '';

if (!empty($p2p_start_date)) {
    $p2p_where_clauses[] = "DATE(pt.created_at) >= ?";
    $p2p_params[] = &$p2p_start_date;
    $p2p_types .= 's';
}
if (!empty($p2p_end_date)) {
    $p2p_where_clauses[] = "DATE(pt.created_at) <= ?";
    $p2p_params[] = &$p2p_end_date;
    $p2p_types .= 's';
}
if (!empty($p2p_user_id)) {
    $p2p_where_clauses[] = "(pt.buyer_id = ? OR pt.seller_id = ?)";
    $p2p_params[] = &$p2p_user_id;
    $p2p_params[] = &$p2p_user_id;
    $p2p_types .= 'ii';
}

if (!empty($p2p_where_clauses)) {
    $p2p_report_query .= " WHERE " . implode(' AND ', $p2p_where_clauses);
}

$p2p_report_query .= " ORDER BY pt.created_at DESC";

$p2p_stmt = $conn->prepare($p2p_report_query);
if (!empty($p2p_params)) {
    $p2p_stmt->bind_param($p2p_types, ...$p2p_params);
}
$p2p_stmt->execute();
$p2p_report_result = $p2p_stmt->get_result();

// Fetch users for P2P filter
$users_for_p2p_filter = $conn->query("SELECT id, username FROM users ORDER BY username");

// Fetch data for Trading Report tab
$trading_start_date = $_GET['trading_start_date'] ?? '';
$trading_end_date = $_GET['trading_end_date'] ?? '';
$trading_user_id = $_GET['trading_user_id'] ?? '';
$trading_pair_id = $_GET['trading_pair_id'] ?? '';

$trading_report_query = "
    SELECT o.*, u.username, tp.symbol
    FROM trading_orders o
    JOIN trading_accounts ta ON o.account_id = ta.id
    JOIN users u ON ta.user_id = u.id
    JOIN trading_pairs tp ON o.pair_id = tp.id
";

$trading_where_clauses = [];
$trading_params = [];
$trading_types = '';

if (!empty($trading_start_date)) {
    $trading_where_clauses[] = "DATE(o.opened_at) >= ?";
    $trading_params[] = &$trading_start_date;
    $trading_types .= 's';
}
if (!empty($trading_end_date)) {
    $trading_where_clauses[] = "DATE(o.opened_at) <= ?";
    $trading_params[] = &$trading_end_date;
    $trading_types .= 's';
}
if (!empty($trading_user_id)) {
    $trading_where_clauses[] = "u.id = ?";
    $trading_params[] = &$trading_user_id;
    $trading_types .= 'i';
}
if (!empty($trading_pair_id)) {
    $trading_where_clauses[] = "tp.id = ?";
    $trading_params[] = &$trading_pair_id;
    $trading_types .= 'i';
}

if (!empty($trading_where_clauses)) {
    $trading_report_query .= " WHERE " . implode(' AND ', $trading_where_clauses);
}

$trading_report_query .= " ORDER BY o.opened_at DESC";

$trading_stmt = $conn->prepare($trading_report_query);
if (!empty($trading_params)) {
    $trading_stmt->bind_param($trading_types, ...$trading_params);
}
$trading_stmt->execute();
$trading_report_result = $trading_stmt->get_result();

// Fetch users for Trading filter
$users_for_trading_filter = $conn->query("SELECT id, username FROM users ORDER BY username");
// Fetch trading pairs for Trading filter
$trading_pairs_for_filter = $conn->query("SELECT id, symbol FROM trading_pairs ORDER BY symbol");

// Fetch data for Investment Report tab
$investment_start_date = $_GET['investment_start_date'] ?? '';
$investment_end_date = $_GET['investment_end_date'] ?? '';
$investment_user_id = $_GET['investment_user_id'] ?? '';
$investment_plan_id = $_GET['investment_plan_id'] ?? '';

$investment_report_query = "
    SELECT i.*, u.username, ip.name as plan_name
    FROM investments i
    JOIN users u ON i.user_id = u.id
    JOIN investment_plans ip ON i.plan_id = ip.id
";

$investment_where_clauses = [];
$investment_params = [];
$investment_types = '';

if (!empty($investment_start_date)) {
    $investment_where_clauses[] = "DATE(i.started_at) >= ?";
    $investment_params[] = &$investment_start_date;
    $investment_types .= 's';
}
if (!empty($investment_end_date)) {
    $investment_where_clauses[] = "DATE(i.started_at) <= ?";
    $investment_params[] = &$investment_end_date;
    $investment_types .= 's';
}
if (!empty($investment_user_id)) {
    $investment_where_clauses[] = "u.id = ?";
    $investment_params[] = &$investment_user_id;
    $investment_types .= 'i';
}
if (!empty($investment_plan_id)) {
    $investment_where_clauses[] = "ip.id = ?";
    $investment_params[] = &$investment_plan_id;
    $investment_types .= 'i';
}

if (!empty($investment_where_clauses)) {
    $investment_report_query .= " WHERE " . implode(' AND ', $investment_where_clauses);
}

$investment_report_query .= " ORDER BY i.started_at DESC";

$investment_stmt = $conn->prepare($investment_report_query);
if (!empty($investment_params)) {
    $investment_stmt->bind_param($investment_types, ...$investment_params);
}
$investment_stmt->execute();
$investment_report_result = $investment_stmt->get_result();

// Fetch users for Investment filter
$users_for_investment_filter = $conn->query("SELECT id, username FROM users ORDER BY username");
// Fetch investment plans for Investment filter
$plans_for_investment_filter = $conn->query("SELECT id, name FROM investment_plans ORDER BY name");

// Fetch data for Deposit & Withdrawal Report tab
$dw_start_date = $_GET['dw_start_date'] ?? '';
$dw_end_date = $_GET['dw_end_date'] ?? '';
$dw_user_id = $_GET['dw_user_id'] ?? '';
$dw_status = $_GET['dw_status'] ?? '';
$dw_method_id = $_GET['dw_method_id'] ?? '';

$dw_report_query = "
    SELECT 
        d.id, d.amount, d.status, d.created_at, u.username, pm.name as method_name, 'Deposit' as type
    FROM deposits d
    JOIN users u ON d.user_id = u.id
    JOIN payment_methods pm ON d.method_id = pm.id
    UNION ALL
    SELECT 
        w.id, w.amount, w.status, w.created_at, u.username, pm.name as method_name, 'Withdrawal' as type
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    JOIN payment_methods pm ON w.method_id = pm.id
";

$dw_where_clauses = [];
$dw_params = [];
$dw_types = '';

if (!empty($dw_start_date)) {
    $dw_where_clauses[] = "DATE(created_at) >= ?";
    $dw_params[] = &$dw_start_date;
    $dw_types .= 's';
}
if (!empty($dw_end_date)) {
    $dw_where_clauses[] = "DATE(created_at) <= ?";
    $dw_params[] = &$dw_end_date;
    $dw_types .= 's';
}
if (!empty($dw_user_id)) {
    $dw_where_clauses[] = "user_id = ?";
    $dw_params[] = &$dw_user_id;
    $dw_types .= 'i';
}
if (!empty($dw_status)) {
    $dw_where_clauses[] = "status = ?";
    $dw_params[] = &$dw_status;
    $dw_types .= 's';
}
if (!empty($dw_method_id)) {
    $dw_where_clauses[] = "method_id = ?";
    $dw_params[] = &$dw_method_id;
    $dw_types .= 'i';
}

if (!empty($dw_where_clauses)) {
    $dw_report_query = "SELECT * FROM (" . $dw_report_query . ") as combined_data WHERE " . implode(' AND ', $dw_where_clauses);
}

$dw_report_query .= " ORDER BY created_at DESC";

$dw_stmt = $conn->prepare($dw_report_query);
if (!empty($dw_params)) {
    $dw_stmt->bind_param($dw_types, ...$dw_params);
}
$dw_stmt->execute();
$dw_report_result = $dw_stmt->get_result();

// Fetch users for D&W filter
$users_for_dw_filter = $conn->query("SELECT id, username FROM users ORDER BY username");
// Fetch payment methods for D&W filter
$payment_methods_for_dw_filter = $conn->query("SELECT id, name FROM payment_methods ORDER BY name");

// Fetch data for Sales Report tab
$sales_start_date = $_GET['sales_start_date'] ?? '';
$sales_end_date = $_GET['sales_end_date'] ?? '';
$sales_user_id = $_GET['sales_user_id'] ?? '';
$sales_product_id = $_GET['sales_product_id'] ?? '';

$sales_report_query = "
    SELECT o.id as order_id, o.order_number, o.total_amount, o.status, o.created_at, u.username, 
           oi.qty, p.name as product_name, p.sku
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
";

$sales_where_clauses = [];
$sales_params = [];
$sales_types = '';

if (!empty($sales_start_date)) {
    $sales_where_clauses[] = "DATE(o.created_at) >= ?";
    $sales_params[] = &$sales_start_date;
    $sales_types .= 's';
}
if (!empty($sales_end_date)) {
    $sales_where_clauses[] = "DATE(o.created_at) <= ?";
    $sales_params[] = &$sales_end_date;
    $sales_types .= 's';
}
if (!empty($sales_user_id)) {
    $sales_where_clauses[] = "u.id = ?";
    $sales_params[] = &$sales_user_id;
    $sales_types .= 'i';
}
if (!empty($sales_product_id)) {
    $sales_where_clauses[] = "p.id = ?";
    $sales_params[] = &$sales_product_id;
    $sales_types .= 'i';
}

if (!empty($sales_where_clauses)) {
    $sales_report_query .= " WHERE " . implode(' AND ', $sales_where_clauses);
}

$sales_report_query .= " ORDER BY o.created_at DESC";

$sales_stmt = $conn->prepare($sales_report_query);
if (!empty($sales_params)) {
    $sales_stmt->bind_param($sales_types, ...$sales_params);
}
$sales_stmt->execute();
$sales_report_result = $sales_stmt->get_result();

// Fetch users for Sales filter
$users_for_sales_filter = $conn->query("SELECT id, username FROM users ORDER BY username");
// Fetch products for Sales filter
$products_for_sales_filter = $conn->query("SELECT id, name FROM products ORDER BY name");

$conn->close();
?>

<style>
    .tabs { display: flex; border-bottom: 2px solid #ccc; margin-bottom: 2rem; }
    .tab-link { padding: 1rem 1.5rem; cursor: pointer; border-bottom: 2px solid transparent; }
    .tab-link.active { border-color: var(--accent-color); font-weight: bold; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .alert-success { background-color: var(--success); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
    .alert-danger { background-color: var(--danger); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
</style>

<div class="card">
    <h3>Advanced Reports & Analytics</h3>

    <div class="tabs">
        <div class="tab-link active" data-tab="overview">Overview</div>
        <div class="tab-link" data-tab="p2p-report">P2P Report</div>
        <div class="tab-link" data-tab="trading-report">Trading Report</div>
        <div class="tab-link" data-tab="investment-report">Investment Report</div>
        <div class="tab-link" data-tab="deposit-withdrawal-report">Deposit & Withdrawal Report</div>
        <div class="tab-link" data-tab="sales-report">Sales Report</div>
        <div class="tab-link" data-tab="fraud-report">Fraud Report</div>
        <div class="tab-link" data-tab="tax-compliance-report">Tax & Compliance Report</div>
    </div>

    <!-- Overview Tab -->
    <div id="overview" class="tab-content active">
        <h4>Platform Overview</h4>
        <div class="grid-container">
            <div class="card stat-card" style="background-color: #2980b9; color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                <div class="info">
                    <h4>Total Users</h4>
                    <p><?php echo number_format($total_users); ?></p>
                </div>
            </div>
            <div class="card stat-card" style="background-color: #27ae60; color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                <div class="info">
                    <h4>Total Deposits</h4>
                    <p>$<?php echo number_format($total_deposits, 2); ?></p>
                </div>
            </div>
            <div class="card stat-card" style="background-color: #c0392b; color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                <div class="info">
                    <h4>Total Withdrawals</h4>
                    <p>$<?php echo number_format($total_withdrawals, 2); ?></p>
                </div>
            </div>
            <div class="card stat-card" style="background-color: #f39c12; color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                <div class="info">
                    <h4>Total Investments</h4>
                    <p>$<?php echo number_format($total_investments, 2); ?></p>
                </div>
            </div>
            <div class="card stat-card" style="background-color: #8e44ad; color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                <div class="info">
                    <h4>Total P2P Trades</h4>
                    <p><?php echo number_format($total_p2p_trades); ?></p>
                </div>
            </div>
            <div class="card stat-card" style="background-color: #16a085; color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                <div class="info">
                    <h4>Total P2P Volume</h4>
                    <p>$<?php echo number_format($total_p2p_volume, 2); ?></p>
                </p>
            </div>
        </div>
    </div>

    <!-- P2P Report Tab -->
    <div id="p2p-report" class="tab-content">
        <h4>P2P Trading Report</h4>
        <form action="reports_analytics.php" method="GET">
            <input type="hidden" name="tab" value="p2p-report">
            <div class="grid-container" style="grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="p2p_start_date" class="form-control" value="<?php echo htmlspecialchars($p2p_start_date); ?>">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="p2p_end_date" class="form-control" value="<?php echo htmlspecialchars($p2p_end_date); ?>">
                </div>
                <div class="form-group">
                    <label>User</label>
                    <select name="p2p_user_id" class="form-control">
                        <option value="">All Users</option>
                        <?php while($user = $users_for_p2p_filter->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>" <?php if ($p2p_user_id == $user['id']) echo 'selected'; ?>><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter Report</button>
            </div>
        </form>
        <table class="table">
            <thead>
                <tr><th>Trade ID</th><th>Buyer</th><th>Seller</th><th>Amount</th><th>Price</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php if ($p2p_report_result && $p2p_report_result->num_rows > 0): ?>
                    <?php while($trade = $p2p_report_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trade['id']); ?></td>
                            <td><?php echo htmlspecialchars($trade['buyer_username']); ?></td>
                            <td><?php echo htmlspecialchars($trade['seller_username']); ?></td>
                            <td><?php echo number_format($trade['amount'], 2); ?> <?php echo htmlspecialchars($trade['currency_code']); ?></td>
                            <td><?php echo number_format($trade['price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($trade['status']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($trade['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center;">No P2P trades found for the selected criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Trading Report Tab -->
    <div id="trading-report" class="tab-content">
        <h4>Trading Report</h4>
        <form action="reports_analytics.php" method="GET">
            <input type="hidden" name="tab" value="trading-report">
            <div class="grid-container" style="grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="trading_start_date" class="form-control" value="<?php echo htmlspecialchars($trading_start_date); ?>">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="trading_end_date" class="form-control" value="<?php echo htmlspecialchars($trading_end_date); ?>">
                </div>
                <div class="form-group">
                    <label>User</label>
                    <select name="trading_user_id" class="form-control">
                        <option value="">All Users</option>
                        <?php while($user = $users_for_trading_filter->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>" <?php if ($trading_user_id == $user['id']) echo 'selected'; ?>><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trading Pair</label>
                    <select name="trading_pair_id" class="form-control">
                        <option value="">All Pairs</option>
                        <?php while($pair = $trading_pairs_for_filter->fetch_assoc()): ?>
                            <option value="<?php echo $pair['id']; ?>" <?php if ($trading_pair_id == $pair['id']) echo 'selected'; ?>><?php echo htmlspecialchars($pair['symbol']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter Report</button>
            </div>
        </form>
        <table class="table">
            <thead>
                <tr><th>Order ID</th><th>User</th><th>Pair</th><th>Side</th><th>Volume</th><th>Open Price</th><th>Status</th><th>Opened At</th></tr>
            </thead>
            <tbody>
                <?php if ($trading_report_result && $trading_report_result->num_rows > 0): ?>
                    <?php while($order = $trading_report_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['id']); ?></td>
                            <td><?php echo htmlspecialchars($order['username']); ?></td>
                            <td><?php echo htmlspecialchars($order['symbol']); ?></td>
                            <td><?php echo htmlspecialchars($order['side']); ?></td>
                            <td><?php echo number_format($order['volume'], 8); ?></td>
                            <td><?php echo number_format($order['open_price'], 8); ?></td>
                            <td><?php echo htmlspecialchars($order['status']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($order['opened_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align: center;">No trading orders found for the selected criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Investment Report Tab -->
    <div id="investment-report" class="tab-content">
        <h4>Investment Report</h4>
        <form action="reports_analytics.php" method="GET">
            <input type="hidden" name="tab" value="investment-report">
            <div class="grid-container" style="grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="investment_start_date" class="form-control" value="<?php echo htmlspecialchars($investment_start_date); ?>">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="investment_end_date" class="form-control" value="<?php echo htmlspecialchars($investment_end_date); ?>">
                </div>
                <div class="form-group">
                    <label>User</label>
                    <select name="investment_user_id" class="form-control">
                        <option value="">All Users</option>
                        <?php while($user = $users_for_investment_filter->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>" <?php if ($investment_user_id == $user['id']) echo 'selected'; ?>><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Investment Plan</label>
                    <select name="investment_plan_id" class="form-control">
                        <option value="">All Plans</option>
                        <?php while($plan = $plans_for_investment_filter->fetch_assoc()): ?>
                            <option value="<?php echo $plan['id']; ?>" <?php if ($investment_plan_id == $plan['id']) echo 'selected'; ?>><?php echo htmlspecialchars($plan['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter Report</button>
            </div>
        </form>
        <table class="table">
            <thead>
                <tr><th>Investment ID</th><th>User</th><th>Plan</th><th>Amount</th><th>Status</th><th>Started At</th><th>Ends At</th><th>Total Profit</th></tr>
            </thead>
            <tbody>
                <?php if ($investment_report_result && $investment_report_result->num_rows > 0): ?>
                    <?php while($investment = $investment_report_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($investment['id']); ?></td>
                            <td><?php echo htmlspecialchars($investment['username']); ?></td>
                            <td><?php echo htmlspecialchars($investment['plan_name']); ?></td>
                            <td>$<?php echo number_format($investment['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($investment['status']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($investment['started_at'])); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($investment['ends_at'])); ?></td>
                            <td>$<?php echo number_format($investment['total_profit'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align: center;">No investments found for the selected criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Deposit & Withdrawal Report Tab -->
    <div id="deposit-withdrawal-report" class="tab-content">
        <h4>Deposit & Withdrawal Report</h4>
        <form action="reports_analytics.php" method="GET">
            <input type="hidden" name="tab" value="deposit-withdrawal-report">
            <div class="grid-container" style="grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="dw_start_date" class="form-control" value="<?php echo htmlspecialchars($dw_start_date); ?>">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="dw_end_date" class="form-control" value="<?php echo htmlspecialchars($dw_end_date); ?>">
                </div>
                <div class="form-group">
                    <label>User</label>
                    <select name="dw_user_id" class="form-control">
                        <option value="">All Users</option>
                        <?php 
                        // Re-fetch users for this filter as the previous result set might be exhausted
                        $users_for_dw_filter = $conn->query("SELECT id, username FROM users ORDER BY username");
                        while($user = $users_for_dw_filter->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $user['id']; ?>" <?php if ($dw_user_id == $user['id']) echo 'selected'; ?>><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="dw_status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php if ($dw_status === 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="processing" <?php if ($dw_status === 'processing') echo 'selected'; ?>>Processing</option>
                        <option value="completed" <?php if ($dw_status === 'completed') echo 'selected'; ?>>Completed</option>
                        <option value="rejected" <?php if ($dw_status === 'rejected') echo 'selected'; ?>>Rejected</option>
                        <option value="review" <?php if ($dw_status === 'review') echo 'selected'; ?>>Review (Withdrawal)</option>
                        <option value="paid" <?php if ($dw_status === 'paid') echo 'selected'; ?>>Paid (Withdrawal)</option>
                        <option value="cancelled" <?php if ($dw_status === 'cancelled') echo 'selected'; ?>>Cancelled (Withdrawal)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Method</label>
                    <select name="dw_method_id" class="form-control">
                        <option value="">All Methods</option>
                        <?php 
                        // Re-fetch payment methods for this filter
                        $payment_methods_for_dw_filter = $conn->query("SELECT id, name FROM payment_methods ORDER BY name");
                        while($method = $payment_methods_for_dw_filter->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $method['id']; ?>" <?php if ($dw_method_id == $method['id']) echo 'selected'; ?>><?php echo htmlspecialchars($method['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter Report</button>
            </div>
        </form>
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Type</th><th>User</th><th>Amount</th><th>Status</th><th>Method</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php if ($dw_report_result && $dw_report_result->num_rows > 0): ?>
                    <?php while($item = $dw_report_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['id']); ?></td>
                            <td><?php echo htmlspecialchars($item['type']); ?></td>
                            <td><?php echo htmlspecialchars($item['username']); ?></td>
                            <td>$<?php echo number_format($item['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($item['status']); ?></td>
                            <td><?php echo htmlspecialchars($item['method_name']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($item['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center;">No deposits or withdrawals found for the selected criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Sales Report Tab -->
    <div id="sales-report" class="tab-content">
        <h4>Sales Report (Physical Gold)</h4>
        <form action="reports_analytics.php" method="GET">
            <input type="hidden" name="tab" value="sales-report">
            <div class="grid-container" style="grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="sales_start_date" class="form-control" value="<?php echo htmlspecialchars($sales_start_date); ?>">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="sales_end_date" class="form-control" value="<?php echo htmlspecialchars($sales_end_date); ?>">
                </div>
                <div class="form-group">
                    <label>User</label>
                    <select name="sales_user_id" class="form-control">
                        <option value="">All Users</option>
                        <?php 
                        // Re-fetch users for Sales filter
                        $users_for_sales_filter = $conn->query("SELECT id, username FROM users ORDER BY username");
                        while($user = $users_for_sales_filter->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $user['id']; ?>" <?php if ($sales_user_id == $user['id']) echo 'selected'; ?>><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Product</label>
                    <select name="sales_product_id" class="form-control">
                        <option value="">All Products</option>
                        <?php 
                        // Re-fetch products for Sales filter
                        $products_for_sales_filter = $conn->query("SELECT id, name FROM products ORDER BY name");
                        while($product = $products_for_sales_filter->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $product['id']; ?>" <?php if ($sales_product_id == $product['id']) echo 'selected'; ?>><?php echo htmlspecialchars($product['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter Report</button>
            </div>
        </form>
        <table class="table">
            <thead>
                <tr><th>Order ID</th><th>User</th><th>Product</th><th>Quantity</th><th>Price</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php if ($sales_report_result && $sales_report_result->num_rows > 0): ?>
                    <?php while($item = $sales_report_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['order_id']); ?></td>
                            <td><?php echo htmlspecialchars($item['username']); ?></td>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['qty']); ?></td>
                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($item['status']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($item['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center;">No sales found for the selected criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Fraud Report Tab -->
    <div id="fraud-report" class="tab-content">
        <h4>Fraud Detection Report</h4>
        <p><em>This report is under development. Data will appear here soon.</em></p>
        <table class="table">
            <thead>
                <tr><th>User</th><th>Activity</th><th>Risk Score</th><th>Date</th></tr>
            </thead>
            <tbody>
                <tr><td colspan="4" style="text-align: center;">No fraud data available yet.</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Tax & Compliance Report Tab -->
    <div id="tax-compliance-report" class="tab-content">
        <h4>Tax & Compliance Report</h4>
        <p><em>This report is under development. Data will appear here soon.</em></p>
        <table class="table">
            <thead>
                <tr><th>Report Type</th><th>Period</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <tr><td colspan="4" style="text-align: center;">No tax/compliance data available yet.</td></tr>
            </tbody>
        </table>
    </div>

</div>

