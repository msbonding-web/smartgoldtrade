<?php
require_once 'header.php';
require_once '../db_connect.php';

$message = '';
$investment_id = $_GET['id'] ?? null;

if (!$investment_id) {
    header("Location: investment_management.php");
    exit();
}

?>

<div class="card">
    <h3>Manual Investment Payout</h3>

<?php
// Only process if the request is confirmed
if (isset($_POST['confirm_payout'])) {
    // Start transaction
    $conn->begin_transaction();

    try {
        // 1. Fetch investment details and lock the row for update
        $inv_stmt = $conn->prepare("SELECT user_id, amount, plan_id, status FROM investments WHERE id = ? FOR UPDATE");
        $inv_stmt->bind_param('i', $investment_id);
        $inv_stmt->execute();
        $investment = $inv_stmt->get_result()->fetch_assoc();
        $inv_stmt->close();

        if (!$investment) {
            throw new Exception("Investment not found.");
        }

        // 2. Check status
        if ($investment['status'] !== 'active') {
            throw new Exception("This investment is not active and cannot be paid out. Current Status: " . htmlspecialchars($investment['status']));
        }

        $user_id = $investment['user_id'];
        $amount = $investment['amount'];
        $plan_id = $investment['plan_id'];

        // 3. Fetch plan details
        $plan_stmt = $conn->prepare("SELECT profit_value FROM investment_plans WHERE id = ?");
        $plan_stmt->bind_param('i', $plan_id);
        $plan_stmt->execute();
        $plan = $plan_stmt->get_result()->fetch_assoc();
        $plan_stmt->close();

        if (!$plan) {
            throw new Exception("Investment plan associated with this investment not found.");
        }

        // 4. Calculate profit and payout
        $profit = $amount * ($plan['profit_value'] / 100);
        $payout = $amount + $profit;

        // 5. Find user's main USD wallet and lock it
        $wallet_stmt = $conn->prepare("SELECT w.id, w.balance FROM wallets w JOIN wallet_types wt ON w.wallet_type_id = wt.id JOIN currencies c ON w.currency_id = c.id WHERE w.user_id = ? AND wt.slug = 'main' AND c.code = 'USD' FOR UPDATE");
        $wallet_stmt->bind_param('i', $user_id);
        $wallet_stmt->execute();
        $wallet = $wallet_stmt->get_result()->fetch_assoc();
        $wallet_stmt->close();

        if (!$wallet) {
            throw new Exception("User's main USD wallet not found. Cannot process payout.");
        }
        $wallet_id = $wallet['id'];
        $balance_before = $wallet['balance'];

        // 6. Update user's wallet
        $update_wallet_stmt = $conn->prepare("UPDATE wallets SET balance = balance + ?, available = available + ? WHERE id = ?");
        $update_wallet_stmt->bind_param('ddi', $payout, $payout, $wallet_id);
        if (!$update_wallet_stmt->execute()) {
            throw new Exception("Failed to update user wallet balance.");
        }
        $update_wallet_stmt->close();

        // 7. Update investment status
        $update_inv_stmt = $conn->prepare("UPDATE investments SET status = 'completed', total_profit = ?, payout_at = NOW() WHERE id = ?");
        $update_inv_stmt->bind_param('di', $profit, $investment_id);
        if (!$update_inv_stmt->execute()) {
            throw new Exception("Failed to update investment status.");
        }
        $update_inv_stmt->close();

        // 8. Record wallet transaction
        $balance_after = $balance_before + $payout;
        $remarks = "Manual payout for investment #" . $investment_id;
        $direction = 'credit';
        $ref_type = 'investment_payout';
        $trans_stmt = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($trans_stmt === false) {
            throw new Exception("Failed to prepare wallet transaction statement: " . $conn->error);
        }
        // Corrected type string: isdddsis
        $trans_stmt->bind_param('isdddsis', $wallet_id, $direction, $payout, $balance_before, $balance_after, $ref_type, $investment_id, $remarks);
        if (!$trans_stmt->execute()) {
            throw new Exception("Failed to record wallet transaction: " . $trans_stmt->error);
        }
        $trans_stmt->close();

        // If all good, commit
        $conn->commit();
        $message = '<div class="alert alert-success">Manual payout of $' . number_format($payout, 2) . ' processed successfully! The investment is now marked as completed.</div>';

    } catch (Exception $e) {
        $conn->rollback();
        $message = '<div class="alert alert-danger">Error processing payout: ' . $e->getMessage() . '</div>';
    }

    echo $message;
    echo '<a href="investment_management.php" class="btn">Return to Investment Management</a>';

} else {
    // Display confirmation form
    echo '<p>Are you sure you want to process a manual payout for this investment? This action cannot be undone.</p>';
    echo '<form action="manual_payout.php?id=' . htmlspecialchars($investment_id) . '" method="POST">
';
    echo '<button type="submit" name="confirm_payout" class="btn btn-success">Yes, Process Payout</button>';
    echo ' <a href="investment_management.php" class="btn">No, Cancel</a>';
    echo '</form>';
}

$conn->close();
?>
</div>

<?php require_once 'footer.php'; ?>
