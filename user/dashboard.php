<?php
require_once 'header.php';
require_once '../db_connect.php';

// Fetch user's main wallet balance (assuming main wallet type and USD currency for simplicity)
$main_wallet_balance = 0;
$wallet_query = "
    SELECT w.balance
    FROM wallets w
    JOIN wallet_types wt ON w.wallet_type_id = wt.id
    JOIN currencies c ON w.currency_id = c.id
    WHERE w.user_id = ? AND wt.slug = 'main' AND c.code = 'USD'
";
$stmt = $conn->prepare($wallet_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $main_wallet_balance = $row['balance'];
}
$stmt->close();

// Fetch total profit from investments
$total_profit = 0;
$profit_query = "SELECT SUM(total_profit) as total_profit FROM investments WHERE user_id = ? AND status = 'completed'";
$stmt = $conn->prepare($profit_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $total_profit = $row['total_profit'] ?? 0;
}
$stmt->close();

// Fetch investment statistics
$total_investments = 0;
$active_investments = 0;
$investment_stats_query = "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active FROM investments WHERE user_id = ?";
$stmt = $conn->prepare($investment_stats_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $total_investments = $row['total'];
    $active_investments = $row['active'];
}
$stmt->close();

// Fetch withdrawal statistics
$total_withdrawals = 0;
$withdrawal_stats_query = "SELECT SUM(amount) as total FROM withdrawals WHERE user_id = ? AND status IN ('approved', 'paid')";
$stmt = $conn->prepare($withdrawal_stats_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $total_withdrawals = $row['total'] ?? 0;
}
$stmt->close();

// Fetch KYC status
$kyc_status = 'Not Verified';
$kyc_status_class = 'status-rejected';
$kyc_stmt = $conn->prepare("SELECT status FROM kyc_documents WHERE user_id = ?");
$kyc_stmt->bind_param('i', $user_id);
$kyc_stmt->execute();
$kyc_result = $kyc_stmt->get_result();
$docs = $kyc_result->fetch_all(MYSQLI_ASSOC);
$kyc_stmt->close();

if (!empty($docs)) {
    $has_approved = true;
    $has_pending = false;
    $has_rejected = false;
    foreach ($docs as $doc) {
        if ($doc['status'] !== 'approved') $has_approved = false;
        if ($doc['status'] === 'pending') $has_pending = true;
        if ($doc['status'] === 'rejected') $has_rejected = true;
    }
    if ($has_approved) {
        $kyc_status = 'Approved';
        $kyc_status_class = 'status-approved';
    } elseif ($has_pending) {
        $kyc_status = 'Pending';
        $kyc_status_class = 'status-pending';
    } elseif ($has_rejected) {
        $kyc_status = 'Rejected';
        $kyc_status_class = 'status-rejected';
    }
}


// Fetch recent activity (last 7 days wallet transactions)
$recent_activity = [];
$activity_query = "
    SELECT wt.amount, wt.direction, wt.remarks, wt.created_at, c.code as currency_code
    FROM wallet_transactions wt
    JOIN wallets w ON wt.wallet_id = w.id
    JOIN currencies c ON w.currency_id = c.id
    WHERE w.user_id = ? AND wt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY wt.created_at DESC
    LIMIT 5
";
$stmt = $conn->prepare($activity_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$recent_activity_result = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<style>
    /* Dashboard Specific Styles */
    .dashboard-container {
        padding: 20px;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    .stat-card {
        background-color: #fff;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.07);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .stat-card .icon {
        font-size: 2.5rem;
        padding: 15px;
        border-radius: 50%;
        color: #fff;
    }
    .stat-card .info h4 {
        margin: 0 0 5px 0;
        font-size: 1rem;
        color: #888;
    }
    .stat-card .info p {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
        color: #333;
    }
    .icon.bg-blue { background-color: #007bff; }
    .icon.bg-green { background-color: #28a745; }
    .icon.bg-yellow { background-color: #ffc107; }
    .icon.bg-red { background-color: #dc3545; }
    .icon.bg-cyan { background-color: #17a2b8; }

    .content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-top: 20px;
    }
    .card {
        background-color: #fff;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.07);
    }
    .card h4 {
        margin-top: 0;
        margin-bottom: 20px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    .table .dir-credit { color: #28a745; font-weight: bold; }
    .table .dir-debit { color: #dc3545; font-weight: bold; }

    .quick-actions .btn {
        display: block;
        width: 100%;
        margin-bottom: 10px;
        text-align: center;
        padding: 12px;
        background-color: #D4AF37;
        color: #fff;
        border: none;
        border-radius: 5px;
        text-decoration: none;
        font-weight: 600;
        transition: background-color 0.2s;
    }
    .quick-actions .btn:hover {
        background-color: #c5a22d;
    }
    .kyc-status-link { color: #007bff; text-decoration: underline; font-size: 0.9rem; }
    .status-approved { color: #28a745; }
    .status-pending { color: #ffc107; }
    .status-rejected { color: #dc3545; }

    @media (max-width: 992px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dashboard-container">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon bg-blue">💰</div>
            <div class="info">
                <h4>Main Balance</h4>
                <p>$<?php echo number_format($main_wallet_balance, 2); ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon bg-green">📈</div>
            <div class="info">
                <h4>Total Profit</h4>
                <p>$<?php echo number_format($total_profit, 2); ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon bg-yellow">📦</div>
            <div class="info">
                <h4>Active Investments</h4>
                <p><?php echo number_format($active_investments); ?> / <?php echo number_format($total_investments); ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon bg-red">💸</div>
            <div class="info">
                <h4>Total Withdrawals</h4>
                <p>$<?php echo number_format($total_withdrawals, 2); ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon bg-cyan">🛡️</div>
            <div class="info">
                <h4>KYC Status</h4>
                <p class="<?php echo $kyc_status_class; ?>"><?php echo htmlspecialchars($kyc_status); ?></p>
                <?php if ($kyc_status !== 'Approved'): ?>
                    <a href="kyc.php" class="kyc-status-link">Verify Now</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content-grid">
        <div class="card">
            <h4>Recent Activity (Last 7 Days)</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>Amount</th><th>Type</th><th>Remarks</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_activity_result->num_rows > 0): ?>
                            <?php while($activity = $recent_activity_result->fetch_assoc()): ?>
                                <tr>
                                    <td class="dir-<?php echo htmlspecialchars($activity['direction']); ?>">
                                        <?php echo ($activity['direction'] == 'credit' ? '+' : '-') . number_format($activity['amount'], 2); ?> <?php echo htmlspecialchars($activity['currency_code']); ?>
                                    </td>
                                    <td style="text-transform: capitalize;"><?php echo htmlspecialchars($activity['direction']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['remarks']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($activity['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align: center;">No recent activity.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h4>Quick Actions</h4>
            <div class="quick-actions">
                <a href="deposit_withdrawal.php?tab=deposit" class="btn">Deposit Funds</a>
                <a href="deposit_withdrawal.php?tab=withdrawal" class="btn">Withdraw Funds</a>
                <a href="investment_plans.php" class="btn">Invest Now</a>
                <a href="support_tickets.php" class="btn">Get Support</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
