<?php
require_once 'header.php';
require_once '../db_connect.php';

$transfer_message = '';

// Handle Money Transfer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_transfer'])) {
    $recipient_identifier = $_POST['recipient_identifier'] ?? '';
    $transfer_amount = $_POST['transfer_amount'] ?? 0;
    $from_wallet_id = $_POST['from_wallet_id'] ?? 0;
    $transfer_remarks = $_POST['transfer_remarks'] ?? '';

    if (empty($recipient_identifier) || !is_numeric($transfer_amount) || $transfer_amount <= 0 || !is_numeric($from_wallet_id)) {
        $transfer_message = "<div class=\"alert alert-danger\">Invalid transfer details.</div>";
    } else {
        $conn->begin_transaction();
        try {
            // Find recipient user ID
            $recipient_user_id = null;
            if (filter_var($recipient_identifier, FILTER_VALIDATE_EMAIL)) {
                $recipient_query = "SELECT id FROM users WHERE email = ?";
            } elseif (is_numeric($recipient_identifier)) {
                $recipient_query = "SELECT id FROM users WHERE id = ?";
            } else {
                $recipient_query = "SELECT id FROM users WHERE username = ?";
            }
            $recipient_stmt = $conn->prepare($recipient_query);
            $recipient_stmt->bind_param('s', $recipient_identifier);
            $recipient_stmt->execute();
            $recipient_result = $recipient_stmt->get_result();
            if ($row = $recipient_result->fetch_assoc()) {
                $recipient_user_id = $row['id'];
            }
            $recipient_stmt->close();

            if (!$recipient_user_id) {
                throw new Exception("Recipient not found.");
            }

            // Fetch sender's wallet details
            $from_wallet_query = "SELECT id, balance, available, currency_id FROM wallets WHERE id = ? AND user_id = ?";
            $from_wallet_stmt = $conn->prepare($from_wallet_query);
            $from_wallet_stmt->bind_param('ii', $from_wallet_id, $user_id);
            $from_wallet_stmt->execute();
            $from_wallet = $from_wallet_stmt->get_result()->fetch_assoc();
            $from_wallet_stmt->close();

            if (!$from_wallet || $from_wallet['available'] < $transfer_amount) {
                throw new Exception("Insufficient funds in your selected wallet.");
            }

            // Find recipient's wallet (main wallet of same currency)
            $to_wallet_query = "SELECT id, balance FROM wallets WHERE user_id = ? AND currency_id = ? AND wallet_type_id = (SELECT id FROM wallet_types WHERE slug = 'main')";
            $to_wallet_stmt = $conn->prepare($to_wallet_query);
            $to_wallet_stmt->bind_param('ii', $recipient_user_id, $from_wallet['currency_id']);
            $to_wallet_stmt->execute();
            $to_wallet = $to_wallet_stmt->get_result()->fetch_assoc();
            $to_wallet_stmt->close();

            if (!$to_wallet) {
                throw new Exception("Recipient does not have a main wallet for this currency.");
            }

            // Debit sender's wallet
            $update_from_wallet_query = "UPDATE wallets SET balance = balance - ?, available = available - ? WHERE id = ?";
            $update_from_wallet_stmt = $conn->prepare($update_from_wallet_query);
            $update_from_wallet_stmt->bind_param('ddi', $transfer_amount, $transfer_amount, $from_wallet['id']);
            if (!$update_from_wallet_stmt->execute()) {
                throw new Exception("Error debiting sender wallet.");
            }
            $update_from_wallet_stmt->close();

            // Credit recipient's wallet
            $update_to_wallet_query = "UPDATE wallets SET balance = balance + ?, available = available + ? WHERE id = ?";
            $update_to_wallet_stmt = $conn->prepare($update_to_wallet_query);
            $update_to_wallet_stmt->bind_param('ddi', $transfer_amount, $transfer_amount, $to_wallet['id']);
            if (!$update_to_wallet_stmt->execute()) {
                throw new Exception("Error crediting recipient wallet.");
            }
            $update_to_wallet_stmt->close();

            // Record transfer transaction
            $insert_transfer_query = "INSERT INTO transfers (from_wallet_id, to_wallet_id, amount, status) VALUES (?, ?, ?, 'completed')";
            $insert_transfer_stmt = $conn->prepare($insert_transfer_query);
            $insert_transfer_stmt->bind_param('iid', $from_wallet['id'], $to_wallet['id'], $transfer_amount);
            if (!$insert_transfer_stmt->execute()) {
                throw new Exception("Error recording transfer.");
            }
            $transfer_id = $conn->insert_id;
            $insert_transfer_stmt->close();

            // Record wallet transactions for both sender and recipient
            $sender_balance_after = $from_wallet['balance'] - $transfer_amount;
            $sender_tx_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, 'debit', ?, ?, ?, 'transfer', ?, ?)";
            $sender_tx_stmt = $conn->prepare($sender_tx_query);
            $sender_tx_stmt->bind_param('idddis', $from_wallet['id'], $transfer_amount, $from_wallet['balance'], $sender_balance_after, $transfer_id, 'Funds Sent');
            if (!$sender_tx_stmt->execute()) throw new Exception("Error recording sender transaction.");
            $sender_tx_stmt->close();

            $recipient_balance_after = $to_wallet['balance'] + $transfer_amount;
            $recipient_tx_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, 'credit', ?, ?, ?, 'transfer', ?, ?)";
            $recipient_tx_stmt = $conn->prepare($recipient_tx_query);
            $recipient_tx_stmt->bind_param('idddis', $to_wallet['id'], $transfer_amount, $to_wallet['balance'], $recipient_balance_after, $transfer_id, 'Funds Received');
            if (!$recipient_tx_stmt->execute()) throw new Exception("Error recording recipient transaction.");
            $recipient_tx_stmt->close();

            $conn->commit();
            $transfer_message = "<div class=\"alert alert-success\">Funds transferred successfully!</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $transfer_message = "<div class=\"alert alert-danger\">Transfer failed: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch user's wallet transactions
$transactions_query = "
    SELECT wt.*, w.balance as wallet_current_balance, wtp.name as wallet_type_name, c.code as currency_code
    FROM wallet_transactions wt
    JOIN wallets w ON wt.wallet_id = w.id
    JOIN wallet_types wtp ON w.wallet_type_id = wtp.id
    JOIN currencies c ON w.currency_id = c.id
    WHERE w.user_id = ?
    ORDER BY wt.created_at DESC
";
$stmt = $conn->prepare($transactions_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$transactions_result = $stmt->get_result();
$stmt->close();

// Fetch user's wallets for transfer form
$user_wallets_for_transfer_query = "
    SELECT w.id, w.available, wt.name as wallet_type_name, c.code as currency_code
    FROM wallets w
    JOIN wallet_types wt ON w.wallet_type_id = wt.id
    JOIN currencies c ON w.currency_id = c.id
    WHERE w.user_id = ? AND w.is_active = 1
";
$stmt = $conn->prepare($user_wallets_for_transfer_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_wallets_for_transfer_result = $stmt->get_result();
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
    <h3>Transactions & Transfers</h3>

    <div class="tabs">
        <div class="tab-link active" onclick="openTab(event, 'history')">Transaction History</div>
        <div class="tab-link" onclick="openTab(event, 'transfer')">Money Transfer</div>
    </div>

    <!-- Transaction History Tab -->
    <div id="history" class="tab-content active">
        <h4>Your Transaction History</h4>
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Wallet</th><th>Direction</th><th>Amount</th><th>Balance Before</th><th>Balance After</th><th>Ref Type</th><th>Remarks</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php if ($transactions_result && $transactions_result->num_rows > 0): ?>
                    <?php while($transaction = $transactions_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($transaction['id']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['wallet_type_name']); ?> (<?php echo htmlspecialchars($transaction['currency_code']); ?>)</td>
                            <td><?php echo htmlspecialchars($transaction['direction']); ?></td>
                            <td><?php echo number_format($transaction['amount'], 8); ?></td>
                            <td><?php echo number_format($transaction['balance_before'], 8); ?></td>
                            <td><?php echo number_format($transaction['balance_after'], 8); ?></td>
                            <td><?php echo htmlspecialchars($transaction['ref_type']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['remarks']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9" style="text-align: center;">No transaction history found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Money Transfer Tab -->
    <div id="transfer" class="tab-content">
        <h4>Send Funds to Another User</h4>
        <?php echo $transfer_message; // Display messages ?>
        <form action="transactions_transfers.php" method="POST">
            <div class="form-group">
                <label for="recipient_identifier">Recipient (Username, Email, or User ID)</label>
                <input type="text" id="recipient_identifier" name="recipient_identifier" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="transfer_amount">Amount</label>
                <input type="number" step="0.01" id="transfer_amount" name="transfer_amount" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="from_wallet_id">From Wallet</label>
                <select id="from_wallet_id" name="from_wallet_id" class="form-control" required>
                    <option value="">Select Wallet</option>
                    <?php if ($user_wallets_for_transfer_result && $user_wallets_for_transfer_result->num_rows > 0): ?>
                        <?php while($wallet = $user_wallets_for_transfer_result->fetch_assoc()): ?>
                            <option value="<?php echo $wallet['id']; ?>"><?php echo htmlspecialchars($wallet['wallet_type_name']); ?> (<?php echo htmlspecialchars($wallet['currency_code']); ?>) - $<?php echo number_format($wallet['available'], 2); ?> Available</option>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <option value="">No wallets found with available balance.</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="transfer_remarks">Remarks (optional)</label>
                <textarea id="transfer_remarks" name="transfer_remarks" class="form-control" rows="3"></textarea>
            </div>
            <button type="submit" name="make_transfer" class="btn btn-primary">Transfer Funds</button>
        </form>
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
