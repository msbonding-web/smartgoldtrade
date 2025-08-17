<?php
require_once 'header.php';
require_once '../db_connect.php';

$withdrawal_id = $_GET['id'] ?? 0;

if (!is_numeric($withdrawal_id) || $withdrawal_id <= 0) {
    die("Invalid Withdrawal ID.");
}

// Handle withdrawal status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['new_status'] ?? '';
    $remarks = $_POST['remarks'] ?? ''; // Optional remarks

    // Basic validation for status
    if (!in_array($new_status, ['pending', 'review', 'approved', 'paid', 'rejected', 'cancelled'])) {
        die("Invalid status provided.");
    }

    // Start transaction for potential wallet updates
    $conn->begin_transaction();

    try {
        $update_query = "UPDATE withdrawals SET status = ?, processed_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('si', $new_status, $withdrawal_id);
        if (!$update_stmt->execute()) {
            throw new Exception("Error updating withdrawal status: " . $update_stmt->error);
        }
        $update_stmt->close();

        // If withdrawal is approved or paid, update user's wallet balance (debit)
        if ($new_status === 'approved' || $new_status === 'paid') {
            // Fetch withdrawal details again to get amount and wallet_id
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
                    throw new Exception("Error updating wallet balance: " . $wallet_update_stmt->error);
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

                $transaction_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, 'debit', ?, ?, ?, 'withdrawal', ?, ?)";
                $transaction_stmt = $conn->prepare($transaction_query);
                $balance_before = $current_balance + $amount; // Calculate balance before transaction
                $transaction_stmt->bind_param('idddis', $wallet_id, $amount, $balance_before, $current_balance, $withdrawal_id, $remarks);
                if (!$transaction_stmt->execute()) {
                    throw new Exception("Error recording wallet transaction: " . $transaction_stmt->error);
                }
                $transaction_stmt->close();
            }
        }

        $conn->commit();
        header("Location: withdrawal_details.php?id=" . $withdrawal_id . "&update=success");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// Fetch withdrawal details
$withdrawal_query = "
    SELECT w.*, u.username, u.email, pm.name as method_name, c.code as currency_code
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    JOIN payment_methods pm ON w.method_id = pm.id
    JOIN currencies c ON w.currency_id = c.id
    WHERE w.id = ?
";
$withdrawal_stmt = $conn->prepare($withdrawal_query);
$withdrawal_stmt->bind_param('i', $withdrawal_id);
$withdrawal_stmt->execute();
$withdrawal_result = $withdrawal_stmt->get_result();
if ($withdrawal_result->num_rows === 1) {
    $withdrawal = $withdrawal_result->fetch_assoc();
} else {
    die("Withdrawal not found.");
}
$withdrawal_stmt->close();

$conn->close();
?>

<a href="withdrawal_management.php" class="btn" style="margin-bottom: 1rem; background-color: var(--gray);">← Back to Withdrawals</a>

<div class="card">
    <h3>Withdrawal Details: #<?php echo htmlspecialchars($withdrawal['id']); ?></h3>

    <?php if (isset($_GET['update']) && $_GET['update'] === 'success'): ?>
        <div class="alert alert-success">Withdrawal status updated successfully!</div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="grid-container" style="grid-template-columns: 1fr 1fr; align-items: flex-start;">
        <div>
            <h4>Withdrawal Information</h4>
            <p><strong>Withdrawal ID:</strong> <?php echo htmlspecialchars($withdrawal['id']); ?></p>
            <p><strong>User:</strong> <?php echo htmlspecialchars($withdrawal['username']); ?> (<?php echo htmlspecialchars($withdrawal['email']); ?>)</p>
            <p><strong>Amount:</strong> <?php echo number_format($withdrawal['amount'], 2); ?> <?php echo htmlspecialchars($withdrawal['currency_code']); ?></p>
            <p><strong>Method:</strong> <?php echo htmlspecialchars($withdrawal['method_name']); ?></p>
            <p><strong>Charge:</strong> <?php echo number_format($withdrawal['charge'], 2); ?> <?php echo htmlspecialchars($withdrawal['currency_code']); ?></p>
            <p><strong>Net Amount:</strong> <?php echo number_format($withdrawal['amount'] - $withdrawal['charge'], 2); ?> <?php echo htmlspecialchars($withdrawal['currency_code']); ?></p>
            <p><strong>Status:</strong> <span style="font-weight: bold; text-transform: capitalize;"><?php echo htmlspecialchars($withdrawal['status']); ?></span></p>
            <p><strong>Requested At:</strong> <?php echo date('Y-m-d H:i', strtotime($withdrawal['created_at'])); ?></p>
            <p><strong>Processed At:</strong> <?php echo $withdrawal['processed_at'] ? date('Y-m-d H:i', strtotime($withdrawal['processed_at'])) : 'N/A'; ?></p>
            <p><strong>Payout Tx Ref:</strong> <?php echo htmlspecialchars($withdrawal['payout_tx_ref'] ?? 'N/A'); ?></p>
        </div>

        <div>
            <h4>Update Status</h4>
            <form action="withdrawal_details.php?id=<?php echo $withdrawal_id; ?>" method="POST">
                <div class="form-group">
                    <label for="new_status">Change Status to:</label>
                    <select name="new_status" id="new_status" class="form-control">
                        <option value="pending" <?php if($withdrawal['status'] === 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="review" <?php if($withdrawal['status'] === 'review') echo 'selected'; ?>>Review</option>
                        <option value="approved" <?php if($withdrawal['status'] === 'approved') echo 'selected'; ?>>Approved</option>
                        <option value="paid" <?php if($withdrawal['status'] === 'paid') echo 'selected'; ?>>Paid</option>
                        <option value="rejected" <?php if($withdrawal['status'] === 'rejected') echo 'selected'; ?>>Rejected</option>
                        <option value="cancelled" <?php if($withdrawal['status'] === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="remarks">Remarks (for rejection/internal notes)</label>
                    <textarea name="remarks" id="remarks" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="payout_tx_ref">Payout Transaction Reference (for Paid status)</label>
                    <input type="text" name="payout_tx_ref" id="payout_tx_ref" class="form-control" value="<?php echo htmlspecialchars($withdrawal['payout_tx_ref'] ?? ''); ?>">
                </div>
                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
