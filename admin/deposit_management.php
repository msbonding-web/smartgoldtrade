<?php
require_once 'header.php';
require_once '../db_connect.php';

$deposit_limit_message = '';
$batch_message = '';

// Handle Deposit Limit Control submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_deposit_limits'])) {
    $global_daily_limit = $_POST['global_daily_limit'] ?? 0;
    $global_monthly_limit = $_POST['global_monthly_limit'] ?? 0;

    $settings_to_update = [
        'global_deposit_daily_limit' => $global_daily_limit,
        'global_deposit_monthly_limit' => $global_monthly_limit
    ];

    foreach ($settings_to_update as $key => $value) {
        $query = "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sd', $key, $value);
        if (!$stmt->execute()) {
            $deposit_limit_message = "<div class=\"alert alert-danger\">Error saving limits: " . $stmt->error . "</div>";
            break;
        }
        $stmt->close();
    }

    if (empty($deposit_limit_message)) {
        $deposit_limit_message = "<div class=\"alert alert-success\">Deposit limits saved successfully!</div>";
    }
}

// Handle Batch Approval submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_approve'])) {
    $selected_deposits = $_POST['selected_deposits'] ?? [];
    $approved_count = 0;
    $failed_count = 0;

    if (!empty($selected_deposits)) {
        foreach ($selected_deposits as $deposit_id) {
            $deposit_id = (int)$deposit_id;
            
            // Start transaction for each deposit approval
            $conn->begin_transaction();

            try {
                // Update deposit status
                $update_query = "UPDATE deposits SET status = 'completed', processed_at = NOW() WHERE id = ? AND (status = 'pending' OR status = 'processing')";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param('i', $deposit_id);
                if (!$update_stmt->execute()) {
                    throw new Exception("Error updating deposit status for ID " . $deposit_id . ": " . $update_stmt->error);
                }
                $update_stmt->close();

                // Fetch deposit details to update wallet
                $deposit_info_query = "SELECT amount, wallet_id FROM deposits WHERE id = ?";
                $deposit_info_stmt = $conn->prepare($deposit_info_query);
                $deposit_info_stmt->bind_param('i', $deposit_id);
                $deposit_info_stmt->execute();
                $deposit_info_result = $deposit_info_stmt->get_result();
                $deposit_info = $deposit_info_result->fetch_assoc();
                $deposit_info_stmt->close();

                if ($deposit_info) {
                    $amount = $deposit_info['amount'];
                    $wallet_id = $deposit_info['wallet_id'];

                    // Update wallet balance
                    $wallet_update_query = "UPDATE wallets SET balance = balance + ?, available = available + ? WHERE id = ?";
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

                    $transaction_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, 'credit', ?, ?, ?, 'deposit', ?, 'Deposit Approved')";
                    $transaction_stmt = $conn->prepare($transaction_query);
                    $balance_before = $current_balance - $amount; 
                    $transaction_stmt->bind_param('idddis', $wallet_id, $amount, $balance_before, $current_balance, $deposit_id);
                    if (!$transaction_stmt->execute()) {
                        throw new Exception("Error recording wallet transaction for ID " . $deposit_id . ": " . $transaction_stmt->error);
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
        $batch_message = "<div class=\"alert alert-success\">Successfully approved " . $approved_count . " deposits. Failed to approve " . $failed_count . " deposits.</div>";
    } else {
        $batch_message = "<div class=\"alert alert-danger\">No deposits selected for batch approval.</div>";
    }
}

// Fetch current deposit limits to pre-fill form
$current_deposit_limits = [];
$limit_keys = ['global_deposit_daily_limit', 'global_deposit_monthly_limit'];
foreach ($limit_keys as $key) {
    $query = "SELECT `value` FROM settings WHERE `key` = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_deposit_limits[$key] = $row['value'];
    } else {
        $current_deposit_limits[$key] = ''; // Default empty
    }
    $stmt->close();
}

// Fetch Deposits
$status_filter = $_GET['status'] ?? '';

$deposits_query = "
    SELECT d.*, u.username, u.email, pm.name as method_name
    FROM deposits d
    JOIN users u ON d.user_id = u.id
    JOIN payment_methods pm ON d.method_id = pm.id
";

$where_clauses = [];
$params = [];
$types = '';

if (!empty($status_filter)) {
    $where_clauses[] = "d.status = ?";
    $params[] = & $status_filter;
    $types .= 's';
}

if (!empty($where_clauses)) {
    $deposits_query .= " WHERE " . implode(' AND ', $where_clauses);
}

$deposits_query .= " ORDER BY d.created_at DESC";

$stmt = $conn->prepare($deposits_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$deposits_result = $stmt->get_result();

?>

<style>
    .alert-success { background-color: var(--success); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
    .alert-danger { background-color: var(--danger); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
</style>

<div class="card">
    <h3>Deposit Management</h3>

    <div class="grid-container" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <!-- Deposit List -->
        <div>
            <h4>All Deposits</h4>
            <?php echo $batch_message; // Display batch messages ?>
            <div style="margin-bottom: 1rem;">
                <form action="deposit_management.php" method="GET" style="display: flex; gap: 1rem;">
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php if ($status_filter === 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="processing" <?php if ($status_filter === 'processing') echo 'selected'; ?>>Processing</option>
                        <option value="completed" <?php if ($status_filter === 'completed') echo 'selected'; ?>>Completed</option>
                        <option value="rejected" <?php if ($status_filter === 'rejected') echo 'selected'; ?>>Rejected</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
            <form action="deposit_management.php" method="POST">
                <table class="table">
                    <thead>
                        <tr><th><input type="checkbox" id="select_all_deposits"></th><th>User</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($deposits_result && $deposits_result->num_rows > 0): ?>
                            <?php while($deposit = $deposits_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if ($deposit['status'] === 'pending' || $deposit['status'] === 'processing'): ?>
                                            <input type="checkbox" name="selected_deposits[]" value="<?php echo $deposit['id']; ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($deposit['username']); ?></td>
                                    <td>$<?php echo number_format($deposit['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($deposit['method_name']); ?></td>
                                    <td><?php echo htmlspecialchars($deposit['status']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($deposit['created_at'])); ?></td>
                                    <td>
                                        <a href="deposit_details.php?id=<?php echo $deposit['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align: center;">No deposits found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <button type="submit" name="batch_approve" class="btn btn-success" style="margin-top: 1rem;">Batch Approve Selected</button>
            </form>
        </div>

        <!-- Deposit Limit Control -->
        <div>
            <h4>Deposit Limit Control</h4>
            <?php echo $deposit_limit_message; // Display messages ?>
            <form action="deposit_management.php" method="POST">
                <div class="form-group">
                    <label>Global Daily Limit</label>
                    <input type="number" step="0.01" class="form-control" name="global_daily_limit" value="<?php echo htmlspecialchars($current_deposit_limits['global_deposit_daily_limit'] ?? '10000'); ?>">
                </div>
                <div class="form-group">
                    <label>Global Monthly Limit</label>
                    <input type="number" step="0.01" class="form-control" name="global_monthly_limit" value="<?php echo htmlspecialchars($current_deposit_limits['global_monthly_limit'] ?? '50000'); ?>">
                </div>
                <button type="submit" name="save_deposit_limits" class="btn btn-primary">Save Limits</button>
            </form>

            <h4 style="margin-top: 2rem;">Other Deposit Settings</h4>
            <div class="card">
                <h5>Real-Time Payment Gateway Logs</h5>
                <p>This section would display a live feed of payment gateway transactions. Integration requires specific API keys and webhooks from your chosen payment providers (e.g., Stripe, PayPal, etc.).</p>
                <p><em>(Requires external API integration)</em></p>
            </div>
            <div class="card" style="margin-top: 1rem;">
                <h5>Fraud Detection Alerts</h5>
                <p>This section would highlight suspicious deposit patterns or flag transactions from high-risk accounts. This could involve:</p>
                <ul>
                    <li>Monitoring multiple deposits from the same IP in a short period.</li>
                    <li>Alerts for deposits from blacklisted countries/IPs.</li>
                    <li>Integration with fraud detection services.</li>
                </ul>
                <p><em>(Requires advanced logic and potentially external services)</em></p>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('select_all_deposits').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('input[name="selected_deposits[]"]');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = this.checked;
        }
    });
</script>

<?php
$conn->close();
require_once 'footer.php';
?>
