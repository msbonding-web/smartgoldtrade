<?php
require_once 'header.php';
require_once '../db_connect.php';

$withdrawal_limit_message = '';
$batch_message = '';

// Handle Withdrawal Limit Control submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_withdrawal_limits'])) {
    $global_daily_limit = $_POST['global_daily_limit'] ?? 0;
    $global_monthly_limit = $_POST['global_monthly_limit'] ?? 0;

    $settings_to_update = [
        'global_withdrawal_daily_limit' => $global_daily_limit,
        'global_withdrawal_monthly_limit' => $global_monthly_limit
    ];

    foreach ($settings_to_update as $key => $value) {
        $query = "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sd', $key, $value);
        if (!$stmt->execute()) {
            $withdrawal_limit_message = "<div class=\"alert alert-danger\">Error saving limits: " . $stmt->error . "</div>";
            break;
        }
        $stmt->close();
    }

    if (empty($withdrawal_limit_message)) {
        $withdrawal_limit_message = "<div class=\"alert alert-success\">Withdrawal limits saved successfully!</div>";
    }
}

// Handle Batch Approval submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_approve'])) {
    $selected_withdrawals = $_POST['selected_withdrawals'] ?? [];
    $approved_count = 0;
    $failed_count = 0;

    if (!empty($selected_withdrawals)) {
        foreach ($selected_withdrawals as $withdrawal_id) {
            $withdrawal_id = (int)$withdrawal_id;
            
            // Start transaction for each withdrawal approval
            $conn->begin_transaction();

            try {
                // Update withdrawal status
                $update_query = "UPDATE withdrawals SET status = 'approved', processed_at = NOW() WHERE id = ? AND (status = 'pending' OR status = 'review')";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param('i', $withdrawal_id);
                if (!$update_stmt->execute()) {
                    throw new Exception("Error updating withdrawal status for ID " . $withdrawal_id . ": " . $update_stmt->error);
                }
                $update_stmt->close();

                // Fetch withdrawal details to update wallet
                $withdrawal_info_query = "SELECT amount, wallet_id FROM withdrawals WHERE id = ?";
                $withdrawal_info_stmt = $conn->prepare($withdrawal_info_query);
                $withdrawal_info_stmt->bind_param('i', $withdrawal_id);
                $withdrawal_info_stmt->execute();
                $withdrawal_info_result = $withdrawal_info_stmt->get_result();
                $withdrawal_info = $withdrawal_info_result->fetch_assoc();
                $withdrawal_info_stmt->close();

                if ($withdrawal_info) {
                    $amount = $withdrawal_info['amount'];
                    $wallet_id = $withdrawal_info['wallet_id'];

                    // Update wallet balance (debit)
                    $wallet_update_query = "UPDATE wallets SET balance = balance - ?, available = available - ? WHERE id = ?";
                    $wallet_update_stmt = $conn->prepare($wallet_update_query);
                    $wallet_update_stmt->bind_param('ddi', $amount, $amount, $wallet_id);
                    if (!$wallet_update_stmt->execute()) {
                        throw new Exception("Error updating wallet balance for ID " . $wallet_id . ": " . $wallet_update_stmt->error);
                    }
                    $wallet_update_stmt->close();

                    // Record wallet transaction
                    $wallet_balance_query = "SELECT balance FROM wallets WHERE id = ?";
                    $wallet_balance_stmt = $conn->prepare($wallet_balance_query);
                    $wallet_balance_stmt->bind_param('i', $wallet_id);
                    $wallet_balance_stmt->execute();
                    $wallet_balance_result = $wallet_balance_stmt->get_result();
                    $current_balance = $wallet_balance_result->fetch_assoc()['balance'];
                    $wallet_balance_stmt->close();

                    $transaction_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, 'debit', ?, ?, ?, 'withdrawal', ?, 'Withdrawal Approved')";
                    $transaction_stmt = $conn->prepare($transaction_query);
                    $balance_before = $current_balance + $amount; 
                    $transaction_stmt->bind_param('idddis', $wallet_id, $amount, $balance_before, $current_balance, $withdrawal_id);
                    if (!$transaction_stmt->execute()) {
                        throw new Exception("Error recording wallet transaction: " . $transaction_stmt->error);
                    }
                    $transaction_stmt->close();
                }

                $conn->commit();
                $approved_count++;
            } catch (Exception $e) {
                $conn->rollback();
                $failed_count++;
                // Log error for debugging: error_log($e->getMessage());
            }
        }
        $batch_message = "<div class=\"alert alert-success\">Successfully approved " . $approved_count . " withdrawals. Failed to approve " . $failed_count . " withdrawals.</div>";
    } else {
        $batch_message = "<div class=\"alert alert-danger\">No withdrawals selected for batch approval.</div>";
    }
}

// Fetch current withdrawal limits to pre-fill form
$current_withdrawal_limits = [];
$limit_keys = ['global_withdrawal_daily_limit', 'global_withdrawal_monthly_limit'];
foreach ($limit_keys as $key) {
    $query = "SELECT `value` FROM settings WHERE `key` = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_withdrawal_limits[$key] = $row['value'];
    } else {
        $current_withdrawal_limits[$key] = ''; // Default empty
    }
    $stmt->close();
}

// Fetch Withdrawals
$status_filter = $_GET['status'] ?? '';

$withdrawals_query = "
    SELECT w.*, u.username, u.email, pm.name as method_name
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    JOIN payment_methods pm ON w.method_id = pm.id
";

$where_clauses = [];
$params = [];
$types = '';

if (!empty($status_filter)) {
    $where_clauses[] = "w.status = ?";
    $params[] = & $status_filter;
    $types .= 's';
}

if (!empty($where_clauses)) {
    $withdrawals_query .= " WHERE " . implode(' AND ', $where_clauses);
}

$withdrawals_query .= " ORDER BY w.created_at DESC";

$stmt = $conn->prepare($withdrawals_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$withdrawals_result = $stmt->get_result();

?>

<style>
    .alert-success { background-color: var(--success); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
    .alert-danger { background-color: var(--danger); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
</style>

<div class="card">
    <h3>Withdrawal Management</h3>

    <div class="grid-container" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <!-- Withdrawal List -->
        <div>
            <h4>All Withdrawals</h4>
            <?php echo $batch_message; // Display batch messages ?>
            <div style="margin-bottom: 1rem;">
                <form action="withdrawal_management.php" method="GET" style="display: flex; gap: 1rem;">
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php if ($status_filter === 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="review" <?php if ($status_filter === 'review') echo 'selected'; ?>>Review</option>
                        <option value="approved" <?php if ($status_filter === 'approved') echo 'selected'; ?>>Approved</option>
                        <option value="paid" <?php if ($status_filter === 'paid') echo 'selected'; ?>>Paid</option>
                        <option value="rejected" <?php if ($status_filter === 'rejected') echo 'selected'; ?>>Rejected</option>
                        <option value="cancelled" <?php if ($status_filter === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
            <form action="withdrawal_management.php" method="POST">
                <table class="table">
                    <thead>
                        <tr><th><input type="checkbox" id="select_all_withdrawals"></th><th>User</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($withdrawals_result && $withdrawals_result->num_rows > 0): ?>
                            <?php while($withdrawal = $withdrawals_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if ($withdrawal['status'] === 'pending' || $withdrawal['status'] === 'review'): ?>
                                            <input type="checkbox" name="selected_withdrawals[]" value="<?php echo $withdrawal['id']; ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($withdrawal['username']); ?></td>
                                    <td>$<?php echo number_format($withdrawal['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($withdrawal['method_name']); ?></td>
                                    <td><?php echo htmlspecialchars($withdrawal['status']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($withdrawal['created_at'])); ?></td>
                                    <td>
                                        <a href="withdrawal_details.php?id=<?php echo $withdrawal['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align: center;">No withdrawals found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <button type="submit" name="batch_approve" class="btn btn-success" style="margin-top: 1rem;">Batch Approve Selected</button>
            </form>
        </div>

        <!-- Withdrawal Limit Control -->
        <div>
            <h4>Withdrawal Limit Control</h4>
            <?php echo $withdrawal_limit_message; // Display messages ?>
            <form action="withdrawal_management.php" method="POST">
                <div class="form-group">
                    <label>Global Daily Limit</label>
                    <input type="number" step="0.01" class="form-control" name="global_daily_limit" value="<?php echo htmlspecialchars($current_withdrawal_limits['global_withdrawal_daily_limit'] ?? '10000'); ?>">
                </div>
                <div class="form-group">
                    <label>Global Monthly Limit</label>
                    <input type="number" step="0.01" class="form-control" name="global_monthly_limit" value="<?php echo htmlspecialchars($current_withdrawal_limits['global_monthly_limit'] ?? '50000'); ?>">
                </div>
                <button type="submit" name="save_withdrawal_limits" class="btn btn-primary">Save Limits</button>
            </form>

            <h4 style="margin-top: 2rem;">Other Withdrawal Settings</h4>
            <p>Multi-Level Approval: <em>(Feature coming soon)</em></p>
            <p>Auto-withdrawal for Trusted Users: <em>(Feature coming soon)</em></p>
            <p>Manual Processing Option for Bank/Crypto: <em>(Feature coming soon)</em></p>
            <p>Fraud Detection Alerts: <em>(Feature coming soon)</em></p>
        </div>
    </div>
</div>

<script>
    document.getElementById('select_all_withdrawals').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('input[name="selected_withdrawals[]"]');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = this.checked;
        }
    });
</script>

<?php
$conn->close();
require_once 'footer.php';
?>