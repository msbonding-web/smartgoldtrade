<?php
require_once 'header.php';
require_once '../db_connect.php';

$manual_adjustment_message = '';
$reversal_message = '';

// Handle Manual Adjustment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_adjust'])) {
    $user_id = $_POST['user_id'] ?? 0;
    $wallet_type_id = $_POST['wallet_type_id'] ?? 0;
    $currency_id = $_POST['currency_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    $direction = $_POST['direction'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if (!is_numeric($user_id) || !is_numeric($amount) || $amount <= 0 || empty($direction)) {
        $manual_adjustment_message = "<div class=\"alert alert-danger\">Invalid input for manual adjustment.</div>";
    } else {
        $conn->begin_transaction();
        try {
            // Find the correct wallet
            $wallet_query = "SELECT id, balance FROM wallets WHERE user_id = ? AND wallet_type_id = ? AND currency_id = ?";
            $wallet_stmt = $conn->prepare($wallet_query);
            $wallet_stmt->bind_param('iii', $user_id, $wallet_type_id, $currency_id);
            $wallet_stmt->execute();
            $wallet_result = $wallet_stmt->get_result();
            $wallet = $wallet_result->fetch_assoc();
            $wallet_stmt->close();

            if (!$wallet) {
                throw new Exception("Wallet not found for the specified user, type, and currency.");
            }

            $wallet_id = $wallet['id'];
            $balance_before = $wallet['balance'];
            $balance_after = $balance_before;

            // Update wallet balance
            if ($direction === 'credit') {
                $balance_after += $amount;
                $update_wallet_query = "UPDATE wallets SET balance = balance + ?, available = available + ? WHERE id = ?";
            } else {
                $balance_after -= $amount;
                $update_wallet_query = "UPDATE wallets SET balance = balance - ?, available = available - ? WHERE id = ?";
            }
            $update_wallet_stmt = $conn->prepare($update_wallet_query);
            $update_wallet_stmt->bind_param('ddi', $amount, $amount, $wallet_id);
            if (!$update_wallet_stmt->execute()) {
                throw new Exception("Error updating wallet balance.");
            }
            $update_wallet_stmt->close();

            // Record wallet transaction
            $transaction_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, remarks) VALUES (?, ?, ?, ?, ?, 'manual_adjustment', ?)";
            $transaction_stmt = $conn->prepare($transaction_query);
            $transaction_stmt->bind_param('isddds', $wallet_id, $direction, $amount, $balance_before, $balance_after, $remarks);
            if (!$transaction_stmt->execute()) {
                throw new Exception("Error recording wallet transaction.");
            }
            $transaction_stmt->close();

            $conn->commit();
            $manual_adjustment_message = "<div class=\"alert alert-success\">Manual adjustment successful!</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $manual_adjustment_message = "<div class=\"alert alert-danger\">Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch filter values
$user_filter = $_GET['user_id'] ?? '';
$wallet_type_filter = $_GET['wallet_type_id'] ?? '';
$currency_filter = $_GET['currency_id'] ?? '';
$direction_filter = $_GET['direction'] ?? '';
$ref_type_filter = $_GET['ref_type'] ?? '';
$start_date_filter = $_GET['start_date'] ?? '';
$end_date_filter = $_GET['end_date'] ?? '';

// Fetch Transactions
$transactions_query = "
    SELECT wt.*, u.username, w.balance as wallet_current_balance, w.id as wallet_id, wt.remarks, 
           wtp.name as wallet_type_name, c.code as currency_code
    FROM wallet_transactions wt
    JOIN wallets w ON wt.wallet_id = w.id
    JOIN users u ON w.user_id = u.id
    JOIN wallet_types wtp ON w.wallet_type_id = w.id
    JOIN currencies c ON w.currency_id = c.id
";

$where_clauses = [];
$params = [];
$types = '';

if (!empty($user_filter)) {
    $where_clauses[] = "u.id = ?";
    $params[] = & $user_filter;
    $types .= 'i';
}
if (!empty($wallet_type_filter)) {
    $where_clauses[] = "wtp.id = ?";
    $params[] = & $wallet_type_filter;
    $types .= 'i';
}
if (!empty($currency_filter)) {
    $where_clauses[] = "c.id = ?";
    $params[] = & $currency_filter;
    $types .= 'i';
}
if (!empty($direction_filter)) {
    $where_clauses[] = "wt.direction = ?";
    $params[] = & $direction_filter;
    $types .= 's';
}
if (!empty($ref_type_filter)) {
    $where_clauses[] = "wt.ref_type = ?";
    $params[] = & $ref_type_filter;
    $types .= 's';
}
if (!empty($start_date_filter)) {
    $where_clauses[] = "DATE(wt.created_at) >= ?";
    $params[] = & $start_date_filter;
    $types .= 's';
}
if (!empty($end_date_filter)) {
    $where_clauses[] = "DATE(wt.created_at) <= ?";
    $params[] = & $end_date_filter;
    $types .= 's';
}

if (!empty($where_clauses)) {
    $transactions_query .= " WHERE " . implode(' AND ', $where_clauses);
}

$transactions_query .= " ORDER BY wt.created_at DESC";

$stmt = $conn->prepare($transactions_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transactions_result = $stmt->get_result();

// Fetch data for filters (users, wallet types, currencies, ref types)
$users_for_filter = $conn->query("SELECT id, username FROM users ORDER BY username");
$wallet_types_for_filter = $conn->query("SELECT id, name FROM wallet_types ORDER BY name");
$currencies_for_filter = $conn->query("SELECT id, code FROM currencies ORDER BY code");
$ref_types_for_filter = $conn->query("SELECT DISTINCT ref_type FROM wallet_transactions ORDER BY ref_type");

?>

<style>
    .alert-success { background-color: var(--success); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
    .alert-danger { background-color: var(--danger); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
</style>

<div class="card">
    <h3>Transaction Management</h3>

    <div class="grid-container" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <!-- Transaction List -->
        <div>
            <h4>All Wallet Transactions</h4>
            <?php echo $reversal_message; // Display reversal messages ?>
            <div style="margin-bottom: 1rem;">
                <form action="transaction_management.php" method="GET">
                    <div class="grid-container" style="grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                        <select name="user_id" class="form-control">
                            <option value="">All Users</option>
                            <?php while($user = $users_for_filter->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>" <?php if ($user_filter == $user['id']) echo 'selected'; ?>><?php echo htmlspecialchars($user['username']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <select name="wallet_type_id" class="form-control">
                            <option value="">All Wallet Types</option>
                            <?php while($wt = $wallet_types_for_filter->fetch_assoc()): ?>
                                <option value="<?php echo $wt['id']; ?>" <?php if ($wallet_type_filter == $wt['id']) echo 'selected'; ?>><?php echo htmlspecialchars($wt['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <select name="currency_id" class="form-control">
                            <option value="">All Currencies</option>
                            <?php while($curr = $currencies_for_filter->fetch_assoc()): ?>
                                <option value="<?php echo $curr['id']; ?>" <?php if ($currency_filter == $curr['id']) echo 'selected'; ?>><?php echo htmlspecialchars($curr['code']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <select name="direction" class="form-control">
                            <option value="">All Directions</option>
                            <option value="credit" <?php if ($direction_filter === 'credit') echo 'selected'; ?>>Credit</option>
                            <option value="debit" <?php if ($direction_filter === 'debit') echo 'selected'; ?>>Debit</option>
                        </select>
                        <select name="ref_type" class="form-control">
                            <option value="">All Ref Types</option>
                            <?php while($rt = $ref_types_for_filter->fetch_assoc()): ?>
                                <option value="<?php echo $rt['ref_type']; ?>" <?php if ($ref_type_filter === $rt['ref_type']) echo 'selected'; ?>><?php echo htmlspecialchars($rt['ref_type']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date_filter); ?>">
                        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date_filter); ?>">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </form>
            </div>
            <table class="table">
                <thead>
                    <tr><th>User</th><th>Wallet</th><th>Direction</th><th>Amount</th><th>Balance Before</th><th>Balance After</th><th>Ref Type</th><th>Remarks</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($transactions_result && $transactions_result->num_rows > 0): ?>
                        <?php while($transaction = $transactions_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['wallet_type_name']); ?> (<?php echo htmlspecialchars($transaction['currency_code']); ?>)</td>
                                <td><?php echo htmlspecialchars($transaction['direction']); ?></td>
                                <td><?php echo number_format($transaction['amount'], 8); ?></td>
                                <td><?php echo number_format($transaction['balance_before'], 8); ?></td>
                                <td><?php echo number_format($transaction['balance_after'], 8); ?></td>
                                <td><?php echo htmlspecialchars($transaction['ref_type']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['remarks']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?></td>
                                <td>
                                    <a href="reverse_transaction.php?id=<?php echo $transaction['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to reverse this transaction? This action cannot be undone.');">Reverse</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="10" style="text-align: center;">No transactions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Manual Adjustment -->
        <div>
            <h4>Manual Adjustment (Add/Deduct Funds)</h4>
            <?php echo $manual_adjustment_message; // Display messages ?>
            <form action="transaction_management.php" method="POST">
                <div class="form-group">
                    <label>User</label>
                    <select name="user_id" class="form-control" required>
                        <option value="">Select User</option>
                        <?php 
                        $users_for_adjustment = $conn->query("SELECT id, username FROM users ORDER BY username");
                        while($user = $users_for_adjustment->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Wallet Type</label>
                    <select name="wallet_type_id" class="form-control" required>
                        <option value="">Select Wallet Type</option>
                        <?php 
                        $wallet_types_for_adjustment = $conn->query("SELECT id, name FROM wallet_types ORDER BY name");
                        while($wt = $wallet_types_for_adjustment->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $wt['id']; ?>"><?php echo htmlspecialchars($wt['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <select name="currency_id" class="form-control" required>
                        <option value="">Select Currency</option>
                        <?php 
                        $currencies_for_adjustment = $conn->query("SELECT id, code FROM currencies ORDER BY code");
                        while($curr = $currencies_for_adjustment->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $curr['id']; ?>"><?php echo htmlspecialchars($curr['code']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" step="0.00000001" class="form-control" name="amount" required>
                </div>
                <div class="form-group">
                    <label>Direction</label>
                    <select name="direction" class="form-control" required>
                        <option value="">Select Direction</option>
                        <option value="credit">Credit (Add Funds)</option>
                        <option value="debit">Debit (Remove Funds)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Remarks</label>
                    <textarea name="remarks" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" name="manual_adjust" class="btn btn-primary">Adjust Funds</button>
            </form>

            <h4 style="margin-top: 2rem;">Transaction Reversal Option</h4>
            <p><em>(Feature coming soon)</em></p>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'footer.php';
?>