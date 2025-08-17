<?php
require_once 'header.php';
require_once '../db_connect.php';

// Fetch user's investment history
$investment_history_query = "
    SELECT 
        i.id, i.amount, i.status, i.started_at, i.ends_at, i.total_profit,
        ip.name as plan_name, ip.profit_value, ip.profit_type, ip.return_period_value, ip.return_period_unit
    FROM investments i
    JOIN investment_plans ip ON i.plan_id = ip.id
    WHERE i.user_id = ?
    ORDER BY i.started_at DESC
";
$stmt = $conn->prepare($investment_history_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$investment_history_result = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<div class="card">
    <h3>Investment History</h3>

    <table class="table">
        <thead>
            <tr>
                <th>SL No.</th>
                <th>Plan Name</th>
                <th>Amount</th>
                <th>Profit %/Fixed</th>
                <th>Status</th>
                <th>Started At</th>
                <th>Ends At</th>
                <th>Total Profit</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($investment_history_result && $investment_history_result->num_rows > 0): ?>
                <?php $sl_no = 1; while($investment = $investment_history_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $sl_no++; ?></td>
                        <td><?php echo htmlspecialchars($investment['plan_name']); ?></td>
                        <td>$<?php echo number_format($investment['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($investment['profit_value']); ?><?php echo ($investment['profit_type'] === 'percent') ? '%' : ' USD'; ?></td>
                        <td><?php echo htmlspecialchars($investment['status']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($investment['started_at'])); ?></td>
                        <td><?php echo $investment['ends_at'] ? date('Y-m-d', strtotime($investment['ends_at'])) : 'N/A'; ?></td>
                        <td>$<?php echo number_format($investment['total_profit'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" style="text-align: center;">No investment history found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>
