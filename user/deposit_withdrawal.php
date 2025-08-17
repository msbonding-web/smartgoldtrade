<?php
require_once 'header.php';
require_once '../db_connect.php';

$deposit_message = '';
$withdrawal_message = '';

// Handle Deposit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_deposit'])) {
    $method_id = $_POST['method_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;

    if (!is_numeric($method_id) || !is_numeric($amount) || $amount <= 0) {
        $deposit_message = "<div class=\"alert alert-danger\">Invalid deposit details.</div>";
    } else {
        $conn->begin_transaction();
        try {
            // Assuming USD currency_id = 1 and main wallet type for deposit
            $currency_id = 1;
            $main_wallet_type_query = "SELECT id FROM wallet_types WHERE slug = 'main' LIMIT 1";
            $main_wallet_type_result = $conn->query($main_wallet_type_query);
            $main_wallet_type_id = $main_wallet_type_result->fetch_assoc()['id'] ?? null;
            if (!$main_wallet_type_id) throw new Exception("Main wallet type not found.");

            // Get or create user's main USD wallet
            $wallet_query = "SELECT id FROM wallets WHERE user_id = ? AND wallet_type_id = ? AND currency_id = ?";
            $wallet_stmt = $conn->prepare($wallet_query);
            $wallet_stmt->bind_param('iii', $user_id, $main_wallet_type_id, $currency_id);
            $wallet_stmt->execute();
            $wallet_result = $wallet_stmt->get_result();
            $wallet_id = null;
            if ($row = $wallet_result->fetch_assoc()) {
                $wallet_id = $row['id'];
            } else {
                // Create wallet if it doesn't exist
                $insert_wallet_query = "INSERT INTO wallets (user_id, wallet_type_id, currency_id, balance, available) VALUES (?, ?, ?, 0, 0)";
                $insert_wallet_stmt = $conn->prepare($insert_wallet_query);
                $insert_wallet_stmt->bind_param('iii', $user_id, $main_wallet_type_id, $currency_id);
                if (!$insert_wallet_stmt->execute()) throw new Exception("Error creating user wallet.");
                $wallet_id = $conn->insert_id;
                $insert_wallet_stmt->close();
            }
            $wallet_stmt->close();

            // Insert deposit record
            $insert_deposit_query = "INSERT INTO deposits (user_id, wallet_id, method_id, amount, currency_id, status) VALUES (?, ?, ?, ?, ?, 'pending')";
            $insert_deposit_stmt = $conn->prepare($insert_deposit_query);
            $insert_deposit_stmt->bind_param('iiidi', $user_id, $wallet_id, $method_id, $amount, $currency_id);
            if (!$insert_deposit_stmt->execute()) {
                throw new Exception("Error recording deposit: " . $insert_deposit_stmt->error);
            }
            $insert_deposit_stmt->close();

            $conn->commit();
            $deposit_message = "<div class=\"alert alert-success\">Deposit request submitted successfully! It will be reviewed by admin.</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $deposit_message = "<div class=\"alert alert-danger\">Deposit failed: " . $e->getMessage() . "</div>";
        }
    }
}

// Handle Withdrawal submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_withdrawal'])) {
    $method_id = $_POST['method_id'] ?? 0;
    $wallet_id = $_POST['wallet_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;

    if (!is_numeric($method_id) || !is_numeric($wallet_id) || !is_numeric($amount) || $amount <= 0) {
        $withdrawal_message = "<div class=\"alert alert-danger\">Invalid withdrawal details.</div>";
    } else {
        $conn->begin_transaction();
        try {
            // Fetch wallet details to check balance and currency
            $wallet_query = "SELECT w.id as wallet_id_val, w.balance, w.available, w.currency_id, c.code FROM wallets w JOIN currencies c ON w.currency_id = c.id WHERE w.id = ? AND w.user_id = ?";
            $wallet_stmt = $conn->prepare($wallet_query);
            $wallet_stmt->bind_param('ii', $wallet_id, $user_id);
            $wallet_stmt->execute();
            $wallet = $wallet_stmt->get_result()->fetch_assoc();
            $wallet_stmt->close();

            if (!$wallet || $wallet['available'] < $amount) {
                throw new Exception("Insufficient funds in selected wallet or wallet not found.");
            }

            // Calculate charge (example: 1% charge)
            $charge = $amount * 0.01;
            $net_amount = $amount - $charge;

            // Insert withdrawal record
            $insert_withdrawal_query = "INSERT INTO withdrawals (user_id, wallet_id, method_id, amount, charge, currency_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_withdrawal_stmt = $conn->prepare($insert_withdrawal_query);
            $currency_id = $wallet['currency_id'];
            $status = 'pending';
            $insert_withdrawal_stmt->bind_param('iiiddis', $user_id, $wallet_id, $method_id, $amount, $charge, $currency_id, $status);
            if (!$insert_withdrawal_stmt->execute()) {
                throw new Exception("Error recording withdrawal: " . $insert_withdrawal_stmt->error);
            }
            $withdrawal_id = $conn->insert_id;
            $insert_withdrawal_stmt->close();

            // Debit wallet immediately (lock funds)
            $update_wallet_query = "UPDATE wallets SET balance = balance - ?, available = available - ? WHERE id = ?";
            $update_wallet_stmt = $conn->prepare($update_wallet_query);
            $wallet_id_val = $wallet['wallet_id_val'];
            $update_wallet_stmt->bind_param('ddi', $amount, $amount, $wallet_id_val);
            if (!$update_wallet_stmt->execute()) {
                throw new Exception("Error debiting wallet for withdrawal.");
            }
            $update_wallet_stmt->close();

            // Record wallet transaction
            $wallet_balance_after_debit = $wallet['balance'] - $amount;
            $transaction_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $transaction_stmt = $conn->prepare($transaction_query);
            $direction = 'debit';
            $balance_before = $wallet['balance'];
            $ref_type = 'withdrawal_request';
            $remarks = 'Withdrawal Request';
            $transaction_stmt->bind_param('isddisis', $wallet_id_val, $direction, $amount, $balance_before, $wallet_balance_after_debit, $ref_type, $withdrawal_id, $remarks);
            if (!$transaction_stmt->execute()) {
                throw new Exception("Error recording withdrawal transaction: " . $transaction_stmt->error);
            }
            $transaction_stmt->close();

            $conn->commit();
            $withdrawal_message = "<div class=\"alert alert-success\">Withdrawal request submitted successfully! It will be reviewed by admin.</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $withdrawal_message = "<div class=\"alert alert-danger\">Withdrawal failed: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch payment methods for deposit/withdrawal forms
$payment_methods_query = "SELECT id, name FROM payment_methods WHERE is_active = 1 ORDER BY name";
$payment_methods_result = $conn->query($payment_methods_query);

// Fetch user's wallets for withdrawal form
$user_wallets_query = "
    SELECT w.id, w.balance, w.available, wt.name as wallet_type_name, c.code as currency_code
    FROM wallets w
    JOIN wallet_types wt ON w.wallet_type_id = wt.id
    JOIN currencies c ON w.currency_id = c.id
    WHERE w.user_id = ?
";
$stmt = $conn->prepare($user_wallets_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_wallets_result = $stmt->get_result();
$stmt->close();

// Fetch Deposit History
$deposit_history_query = "
    SELECT d.id, d.amount, d.status, d.created_at, pm.name as method_name, d.charge, c.code as currency_code
    FROM deposits d
    JOIN payment_methods pm ON d.method_id = pm.id
    JOIN currencies c ON d.currency_id = c.id
    WHERE d.user_id = ?
    ORDER BY d.created_at DESC
";
$stmt = $conn->prepare($deposit_history_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$deposit_history_result = $stmt->get_result();
$stmt->close();

// Fetch Withdrawal History
$withdrawal_history_query = "
    SELECT w.id, w.amount, w.status, w.created_at, pm.name as method_name, w.charge, c.code as currency_code
    FROM withdrawals w
    JOIN payment_methods pm ON w.method_id = pm.id
    JOIN currencies c ON w.currency_id = c.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
";
$stmt = $conn->prepare($withdrawal_history_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$withdrawal_history_result = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<style>
    .tabs { display: flex; border-bottom: 2px solid #ccc; margin-bottom: 2rem; }
    .tab-link { padding: 1rem 1.5rem; cursor: pointer; border-bottom: 2px solid transparent; }
    .tab-link.active { border-color: var(--primary-color); font-weight: bold; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .alert-success { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    .alert-danger { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
</style>

<div class="card">
    <h3>Deposit & Withdrawal</h3>

    <div class="tabs">
        <div class="tab-link active" onclick="openTab(event, 'deposit')">Deposit</div>
        <div class="tab-link" onclick="openTab(event, 'deposit-history')">Deposit History</div>
        <div class="tab-link" onclick="openTab(event, 'withdrawal')">Withdrawal</div>
        <div class="tab-link" onclick="openTab(event, 'withdrawal-history')">Withdrawal History</div>
    </div>

    <!-- Deposit Tab -->
    <div id="deposit" class="tab-content active">
        <h4>Make a Deposit</h4>
        <?php echo $deposit_message; // Display messages ?>
        <form action="deposit_withdrawal.php" method="POST">
            <div class="form-group">
                <label for="deposit_method">Choose Method</label>
                <select id="deposit_method" name="method_id" class="form-control" required>
                    <option value="">Select Method</option>
                    <?php 
                    // Reset pointer for payment methods result
                    if ($payment_methods_result) $payment_methods_result->data_seek(0);
                    while($method = $payment_methods_result->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $method['id']; ?>"><?php echo htmlspecialchars($method['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="deposit_amount">Amount (USD)</label>
                <input type="number" step="0.01" id="deposit_amount" name="amount" class="form-control" required>
            </div>
            <button type="submit" name="make_deposit" class="btn btn-primary">Deposit Now</button>
        </form>
    </div>

    <!-- Deposit History Tab -->
    <div id="deposit-history" class="tab-content">
        <h4>Deposit History</h4>
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Method</th><th>Amount</th><th>Charge</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php if ($deposit_history_result && $deposit_history_result->num_rows > 0): ?>
                    <?php while($deposit = $deposit_history_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($deposit['id']); ?></td>
                            <td><?php echo htmlspecialchars($deposit['method_name']); ?></td>
                            <td>$<?php echo number_format($deposit['amount'], 2); ?> <?php echo htmlspecialchars($deposit['currency_code']); ?></td>
                            <td>$<?php echo number_format($deposit['charge'], 2); ?></td>
                            <td><?php echo htmlspecialchars($deposit['status']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($deposit['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">No deposit history found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Withdrawal Tab -->
    <div id="withdrawal" class="tab-content">
        <h4>Make a Withdrawal</h4>
        <?php echo $withdrawal_message; // Display messages ?>
        <form action="deposit_withdrawal.php" method="POST">
            <div class="form-group">
                <label for="withdrawal_method">Choose Method</label>
                <select id="withdrawal_method" name="method_id" class="form-control" required>
                    <option value="">Select Method</option>
                    <?php 
                    // Reset pointer for payment methods result
                    if ($payment_methods_result) $payment_methods_result->data_seek(0);
                    while($method = $payment_methods_result->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $method['id']; ?>"><?php echo htmlspecialchars($method['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="withdrawal_wallet">Select Wallet</label>
                <select id="withdrawal_wallet" name="wallet_id" class="form-control" required>
                    <option value="">Select Wallet</option>
                    <?php if ($user_wallets_result && $user_wallets_result->num_rows > 0): ?>
                        <?php while($wallet = $user_wallets_result->fetch_assoc()): ?>
                            <option value="<?php echo $wallet['id']; ?>"><?php echo htmlspecialchars($wallet['wallet_type_name']); ?> (<?php echo htmlspecialchars($wallet['currency_code']); ?>) - $<?php echo number_format($wallet['available'], 2); ?> Available</option>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <option value="">No wallets found.</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="withdrawal_amount">Amount (USD)</label>
                <input type="number" step="0.01" id="withdrawal_amount" name="amount" class="form-control" required>
            </div>
            <button type="submit" name="make_withdrawal" class="btn btn-primary">Withdraw Now</button>
        </form>
    </div>

    <!-- Withdrawal History Tab -->
    <div id="withdrawal-history" class="tab-content">
        <h4>Withdrawal History</h4>
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Method</th><th>Amount</th><th>Charge</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php if ($withdrawal_history_result && $withdrawal_history_result->num_rows > 0): ?>
                    <?php while($withdrawal = $withdrawal_history_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($withdrawal['id']); ?></td>
                            <td><?php echo htmlspecialchars($withdrawal['method_name']); ?></td>
                            <td>$<?php echo number_format($withdrawal['amount'], 2); ?> <?php echo htmlspecialchars($withdrawal['currency_code']); ?></td>
                            <td>$<?php echo number_format($withdrawal['charge'], 2); ?></td>
                            <td><?php echo htmlspecialchars($withdrawal['status']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($withdrawal['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">No withdrawal history found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) { tabcontent[i].style.display = "none"; }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) { tablinks[i].className = tablinks[i].className.replace(" active", ""); }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}

// Activate the correct tab on page load if a message is present
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('tab')) {
        openTab(event, urlParams.get('tab'));
    }
};
</script>

<?php require_once 'footer.php'; ?>