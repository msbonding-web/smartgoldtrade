<?php
require_once 'header.php';
require_once '../db_connect.php';

$investment_id = $_GET['id'] ?? 0;

if (!is_numeric($investment_id) || $investment_id <= 0) {
    die("Invalid Investment ID.");
}

// Fetch main investment details
$query = "
    SELECT 
        i.*, 
        u.username, u.email,
        up.first_name, up.last_name,
        p.name as plan_name, p.profit_value, p.profit_type, p.duration_value, p.duration_unit
    FROM investments i
    JOIN users u ON i.user_id = u.id
    JOIN investment_plans p ON i.plan_id = p.id
    LEFT JOIN user_profiles up ON i.user_id = up.user_id
    WHERE i.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $investment_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $investment = $result->fetch_assoc();
} else {
    die("Investment not found.");
}
$stmt->close();

// Fetch payout history
$payout_query = "SELECT * FROM investment_payouts WHERE investment_id = ? ORDER BY scheduled_at DESC";
$payout_stmt = $conn->prepare($payout_query);
$payout_stmt->bind_param('i', $investment_id);
$payout_stmt->execute();
$payout_result = $payout_stmt->get_result();

$conn->close();
?>

<a href="investment_management.php" class="btn" style="margin-bottom: 1rem; background-color: var(--gray)">← Back to All Investments</a>

<div class="grid-container" style="grid-template-columns: 1fr 1fr; align-items: flex-start;">
    <!-- Left Column -->
    <div>
        <div class="card">
            <h4>Investment Summary</h4>
            <p><strong>ID:</strong> #<?php echo $investment['id']; ?></p>
            <p><strong>Amount:</strong> $<?php echo number_format($investment['amount'], 2); ?></p>
            <p><strong>Status:</strong> <span style="text-transform: capitalize; font-weight: bold; color: var(--success);"><?php echo htmlspecialchars($investment['status']); ?></span></p>
            <p><strong>Started:</strong> <?php echo date('F j, Y, g:i a', strtotime($investment['started_at'])); ?></p>
            <p><strong>Ends:</strong> <?php echo date('F j, Y, g:i a', strtotime($investment['ends_at'])); ?></p>
            <p><strong>Next Payout:</strong> <?php echo date('F j, Y, g:i a', strtotime($investment['next_payout_at'])); ?></p>
            <p><strong>Total Profit Paid:</strong> $<?php echo number_format($investment['total_profit'], 2); ?></p>
        </div>
        <div class="card">
            <h4>Investor Details</h4>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($investment['username']); ?></p>
            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($investment['first_name'] . ' ' . $investment['last_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($investment['email']); ?></p>
        </div>
    </div>

    <!-- Right Column -->
    <div>
        <div class="card">
            <h4>Plan Details</h4>
            <p><strong>Plan:</strong> <?php echo htmlspecialchars($investment['plan_name']); ?></p>
            <p><strong>Profit:</strong> <?php echo htmlspecialchars($investment['profit_value']); ?> <?php echo ($investment['profit_type'] === 'percent' ? '%' : 'USD'); ?></p>
            <p><strong>Duration:</strong> <?php echo htmlspecialchars($investment['duration_value']); ?> <?php echo htmlspecialchars($investment['duration_unit']); ?>(s)</p>
        </div>
    </div>
</div>

<div class="card">
    <h4>Payout History</h4>
    <table class="table">
        <thead>
            <tr>
                <th>Scheduled At</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Paid At</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($payout_result->num_rows > 0): ?>
                <?php while($payout = $payout_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i', strtotime($payout['scheduled_at'])); ?></td>
                        <td>$<?php echo number_format($payout['amount'], 2); ?></td>
                        <td style="text-transform: capitalize;"><?php echo htmlspecialchars($payout['status']); ?></td>
                        <td><?php echo $payout['paid_at'] ? date('Y-m-d H:i', strtotime($payout['paid_at'])) : 'N/A'; ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align: center;">No payout history found for this investment.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
$payout_stmt->close();
require_once 'footer.php'; 
?>
