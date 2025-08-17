<?php
require_once 'header.php';
require_once '../db_connect.php';

$deposit_id = $_GET['id'] ?? 0;

if (!is_numeric($deposit_id) || $deposit_id <= 0) {
    die("Invalid Deposit ID.");
}

// Handle deposit status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['new_status'] ?? '';
    $remarks = $_POST['remarks'] ?? ''; // Optional remarks for rejection

    // Basic validation for status
    if (!in_array($new_status, ['pending', 'processing', 'completed', 'rejected'])) {
        die("Invalid status provided.");
    }

    // Start transaction for potential wallet updates
    $conn->begin_transaction();

    try {
        $update_query = "UPDATE deposits SET status = ?, processed_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('si', $new_status, $deposit_id);
        if (!$update_stmt->execute()) {
            throw new Exception("Error updating deposit status: " . $update_stmt->error);
        }
        $update_stmt->close();

        // If deposit is completed, update user's wallet balance
        if ($new_status === 'completed') {
            // Fetch deposit details again to get amount and wallet_id
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

                $transaction_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, 'credit', ?, ?, ?, 'deposit', ?, ?)";
                $transaction_stmt = $conn->prepare($transaction_query);
                $balance_before = $current_balance - $amount; // Calculate balance before transaction
                $transaction_stmt->bind_param('idddis', $wallet_id, $amount, $balance_before, $current_balance, $deposit_id, $remarks);
                if (!$transaction_stmt->execute()) {
                    throw new Exception("Error recording wallet transaction: " . $transaction_stmt->error);
                }
                $transaction_stmt->close();
            }
        }

        $conn->commit();
        header("Location: deposit_details.php?id=" . $deposit_id . "&update=success");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// Fetch deposit details
$deposit_query = "
    SELECT d.*, u.username, u.email, pm.name as method_name, c.code as currency_code
    FROM deposits d
    JOIN users u ON d.user_id = u.id
    JOIN payment_methods pm ON d.method_id = pm.id
    JOIN currencies c ON d.currency_id = c.id
    WHERE d.id = ?
";
$deposit_stmt = $conn->prepare($deposit_query);
$deposit_stmt->bind_param('i', $deposit_id);
$deposit_stmt->execute();
$deposit_result = $deposit_stmt->get_result();
if ($deposit_result->num_rows === 1) {
    $deposit = $deposit_result->fetch_assoc();
} else {
    die("Deposit not found.");
}
$deposit_stmt->close();

$conn->close();
?>

<a href="deposit_management.php" class="btn" style="margin-bottom: 1rem; background-color: var(--gray);">← Back to Deposits</a>

<div class="card">
    <h3>Deposit Details: #<?php echo htmlspecialchars($deposit['id']); ?></h3>

    <?php if (isset($_GET['update']) && $_GET['update'] === 'success'): ?>
        <div class="alert alert-success">Deposit status updated successfully!</div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="grid-container" style="grid-template-columns: 1fr 1fr; align-items: flex-start;">
        <div>
            <h4>Deposit Information</h4>
            <p><strong>Deposit ID:</strong> <?php echo htmlspecialchars($deposit['id']); ?></p>
            <p><strong>User:</strong> <?php echo htmlspecialchars($deposit['username']); ?> (<?php echo htmlspecialchars($deposit['email']); ?>)</p>
            <p><strong>Amount:</strong> <?php echo number_format($deposit['amount'], 2); ?> <?php echo htmlspecialchars($deposit['currency_code']); ?></p>
            <p><strong>Method:</strong> <?php echo htmlspecialchars($deposit['method_name']); ?></p>
            <p><strong>Transaction Ref:</strong> <?php echo htmlspecialchars($deposit['tx_ref'] ?? 'N/A'); ?></p>
            <p><strong>Status:</strong> <span style="font-weight: bold; text-transform: capitalize;"><?php echo htmlspecialchars($deposit['status']); ?></span></p>
            <p><strong>Created At:</strong> <?php echo date('Y-m-d H:i', strtotime($deposit['created_at'])); ?></p>
            <p><strong>Processed At:</strong> <?php echo $deposit['processed_at'] ? date('Y-m-d H:i', strtotime($deposit['processed_at'])) : 'N/A'; ?></p>
        </div>

        <div>
            <h4>Update Status</h4>
            <form action="deposit_details.php?id=<?php echo $deposit_id; ?>" method="POST">
                <div class="form-group">
                    <label for="new_status">Change Status to:</label>
                    <select name="new_status" id="new_status" class="form-control">
                        <option value="pending" <?php if($deposit['status'] === 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="processing" <?php if($deposit['status'] === 'processing') echo 'selected'; ?>>Processing</option>
                        <option value="completed" <?php if($deposit['status'] === 'completed') echo 'selected'; ?>>Completed</option>
                        <option value="rejected" <?php if($deposit['status'] === 'rejected') echo 'selected'; ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="remarks">Remarks (for rejection/internal notes)</label>
                    <textarea name="remarks" id="remarks" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
