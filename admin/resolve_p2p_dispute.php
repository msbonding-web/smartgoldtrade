<?php
require_once 'header.php';
require_once '../db_connect.php';

$dispute_id = $_GET['id'] ?? 0;

if (!is_numeric($dispute_id) || $dispute_id <= 0) {
    die("Invalid Dispute ID.");
}

$process_message = '';

// Handle dispute resolution submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_action'])) {
    $action = $_POST['resolve_action'] ?? ''; // 'release_buyer', 'release_seller', 'reject'
    $admin_notes = $_POST['admin_notes'] ?? '';
    $admin_id = 1; // Placeholder for logged-in admin ID

    $conn->begin_transaction();
    try {
        // Fetch dispute and trade details
        $dispute_query = "SELECT pd.*, pt.amount, pt.buyer_id, pt.seller_id, pt.currency_id FROM p2p_disputes pd JOIN p2p_trades pt ON pd.trade_id = pt.id WHERE pd.id = ? AND pd.status = 'open'";
        $dispute_stmt = $conn->prepare($dispute_query);
        $dispute_stmt->bind_param('i', $dispute_id);
        $dispute_stmt->execute();
        $dispute_result = $dispute_stmt->get_result();
        $dispute = $dispute_result->fetch_assoc();
        $dispute_stmt->close();

        if (!$dispute) {
            throw new Exception("Dispute not found or already resolved.");
        }

        $trade_id = $dispute['trade_id'];
        $trade_amount = $dispute['amount'];
        $buyer_id = $dispute['buyer_id'];
        $seller_id = $dispute['seller_id'];
        $currency_id = $dispute['currency_id'];

        // Record admin action
        $action_slug = '';
        $new_dispute_status = '';
        $new_trade_status = '';

        if ($action === 'release_buyer') {
            $action_slug = 'release';
            $new_dispute_status = 'resolved_refunded'; // Funds go back to buyer
            $new_trade_status = 'refunded';
            // Logic to release funds from escrow to buyer
            // Assuming escrow wallet is linked to the trade or a central P2P wallet
            // For simplicity, we'll just credit buyer's main wallet
            $buyer_wallet_query = "SELECT id FROM wallets WHERE user_id = ? AND currency_id = ? AND wallet_type_id = (SELECT id FROM wallet_types WHERE slug = 'main')";
            $buyer_wallet_stmt = $conn->prepare($buyer_wallet_query);
            $buyer_wallet_stmt->bind_param('ii', $buyer_id, $currency_id);
            $buyer_wallet_stmt->execute();
            $buyer_wallet_id = $buyer_wallet_stmt->get_result()->fetch_assoc()['id'];
            $buyer_wallet_stmt->close();

            if (!$buyer_wallet_id) throw new Exception("Buyer main wallet not found.");

            $update_buyer_wallet_query = "UPDATE wallets SET balance = balance + ?, available = available + ? WHERE id = ?";
            $update_buyer_wallet_stmt = $conn->prepare($update_buyer_wallet_query);
            $update_buyer_wallet_stmt->bind_param('ddi', $trade_amount, $trade_amount, $buyer_wallet_id);
            if (!$update_buyer_wallet_stmt->execute()) throw new Exception("Error crediting buyer wallet.");
            $update_buyer_wallet_stmt->close();

            // Record wallet transaction for buyer
            $buyer_balance_after_query = "SELECT balance FROM wallets WHERE id = ?";
            $buyer_balance_after_stmt = $conn->prepare($buyer_balance_after_query);
            $buyer_balance_after_stmt->bind_param('i', $buyer_wallet_id);
            $buyer_balance_after_stmt->execute();
            $buyer_balance_after = $buyer_balance_after_stmt->get_result()->fetch_assoc()['balance'];
            $buyer_balance_after_stmt->close();

            $buyer_balance_before = $buyer_balance_after - $trade_amount;
            $buyer_tx_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, 'credit', ?, ?, ?, 'p2p_dispute_refund', ?, ?)";
            $buyer_tx_stmt = $conn->prepare($buyer_tx_query);
            $buyer_tx_stmt->bind_param('idddis', $buyer_wallet_id, $trade_amount, $buyer_balance_before, $buyer_balance_after, $dispute_id, 'P2P Dispute Refund');
            if (!$buyer_tx_stmt->execute()) throw new Exception("Error recording buyer transaction.");
            $buyer_tx_stmt->close();

        } elseif ($action === 'release_seller') {
            $action_slug = 'release';
            $new_dispute_status = 'resolved_released'; // Funds go to seller
            $new_trade_status = 'released';
            // Logic to release funds from escrow to seller
            $seller_wallet_query = "SELECT id FROM wallets WHERE user_id = ? AND currency_id = ? AND wallet_type_id = (SELECT id FROM wallet_types WHERE slug = 'main')";
            $seller_wallet_stmt = $conn->prepare($seller_wallet_query);
            $seller_wallet_stmt->bind_param('ii', $seller_id, $currency_id);
            $seller_wallet_stmt->execute();
            $seller_wallet_id = $seller_wallet_stmt->get_result()->fetch_assoc()['id'];
            $seller_wallet_stmt->close();

            if (!$seller_wallet_id) throw new Exception("Seller main wallet not found.");

            $update_seller_wallet_query = "UPDATE wallets SET balance = balance + ?, available = available + ? WHERE id = ?";
            $update_seller_wallet_stmt = $conn->prepare($update_seller_wallet_query);
            $update_seller_wallet_stmt->bind_param('ddi', $trade_amount, $trade_amount, $seller_wallet_id);
            if (!$update_seller_wallet_stmt->execute()) throw new Exception("Error crediting seller wallet.");
            $update_seller_wallet_stmt->close();

            // Record wallet transaction for seller
            $seller_balance_after_query = "SELECT balance FROM wallets WHERE id = ?";
            $seller_balance_after_stmt = $conn->prepare($seller_balance_after_query);
            $seller_balance_after_stmt->bind_param('i', $seller_wallet_id);
            $seller_balance_after_stmt->execute();
            $seller_balance_after = $seller_balance_after_stmt->get_result()->fetch_assoc()['balance'];
            $seller_balance_after_stmt->close();

            $seller_balance_before = $seller_balance_after - $trade_amount;
            $seller_tx_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, 'credit', ?, ?, ?, 'p2p_dispute_release', ?, ?)";
            $seller_tx_stmt = $conn->prepare($seller_tx_query);
            $seller_tx_stmt->bind_param('idddis', $seller_wallet_id, $trade_amount, $seller_balance_before, $seller_balance_after, $dispute_id, 'P2P Dispute Release');
            if (!$seller_tx_stmt->execute()) throw new Exception("Error recording seller transaction.");
            $seller_tx_stmt->close();

        } elseif ($action === 'reject') {
            $action_slug = 'reject';
            $new_dispute_status = 'rejected';
            $new_trade_status = 'cancelled'; // Or original status if funds were never held
            // No fund transfer needed if rejected (assuming funds were never moved from buyer or returned to buyer)
        } else {
            throw new Exception("Invalid resolution action.");
        }

        // Update dispute status
        $update_dispute_query = "UPDATE p2p_disputes SET status = ?, remarks = ?, processed_by = ?, processed_at = NOW() WHERE id = ?";
        $update_dispute_stmt = $conn->prepare($update_dispute_query);
        $update_dispute_stmt->bind_param('ssii', $new_dispute_status, $admin_notes, $admin_id, $dispute_id);
        if (!$update_dispute_stmt->execute()) throw new Exception("Error updating dispute status.");
        $update_dispute_stmt->close();

        // Update trade status
        $update_trade_query = "UPDATE p2p_trades SET status = ? WHERE id = ?";
        $update_trade_stmt = $conn->prepare($update_trade_query);
        $update_trade_stmt->bind_param('si', $new_trade_status, $trade_id);
        if (!$update_trade_stmt->execute()) throw new Exception("Error updating trade status.");
        $update_trade_stmt->close();

        // Record action in p2p_dispute_actions
        $record_action_query = "INSERT INTO p2p_dispute_actions (dispute_id, admin_id, action, notes) VALUES (?, ?, ?, ?)";
        $record_action_stmt = $conn->prepare($record_action_query);
        $record_action_stmt->bind_param('iiss', $dispute_id, $admin_id, $action_slug, $admin_notes);
        if (!$record_action_stmt->execute()) throw new Exception("Error recording dispute action.");
        $record_action_stmt->close();

        $conn->commit();
        $process_message = "<div class=\"alert alert-success\">Dispute resolved successfully!</div>";
        header("Location: p2p_management.php?tab=disputes&resolved=success");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $process_message = "<div class=\"alert alert-danger\">Error: " . $e->getMessage() . "</div>";
    }
}

// Fetch dispute details
$dispute_query = "
    SELECT 
        pd.*, 
        pt.amount, pt.status as trade_status, pt.created_at as trade_created_at,
        b.username as buyer_username, b.email as buyer_email,
        s.username as seller_username, s.email as seller_email,
        op.username as opened_by_username,
        c.code as currency_code
    FROM p2p_disputes pd
    JOIN p2p_trades pt ON pd.trade_id = pt.id
    JOIN users b ON pt.buyer_id = b.id
    JOIN users s ON pt.seller_id = s.id
    JOIN users op ON pd.opened_by = op.id
    JOIN currencies c ON pt.currency_id = c.id
    WHERE pd.id = ?
";
$dispute_stmt = $conn->prepare($dispute_query);
$dispute_stmt->bind_param('i', $dispute_id);
$dispute_stmt->execute();
$dispute_result = $dispute_stmt->get_result();
if ($dispute_result->num_rows === 1) {
    $dispute = $dispute_result->fetch_assoc();
} else {
    die("Dispute not found.");
}
$dispute_stmt->close();

// Fetch dispute messages
$messages_query = "
    SELECT pm.*, u.username as sender_username
    FROM p2p_messages pm
    JOIN users u ON pm.sender_id = u.id
    WHERE pm.trade_id = ?
    ORDER BY pm.created_at ASC
";
$messages_stmt = $conn->prepare($messages_query);
$messages_stmt->bind_param('i', $dispute['trade_id']);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();
$messages_stmt->close();

$conn->close();
?>

<a href="p2p_management.php?tab=disputes" class="btn" style="margin-bottom: 1rem; background-color: var(--gray);">← Back to Disputes</a>

<div class="card">
    <h3>Resolve P2P Dispute #<?php echo htmlspecialchars($dispute['id']); ?></h3>

    <?php echo $process_message; // Display messages ?>

    <div class="grid-container" style="grid-template-columns: 1fr 1fr; align-items: flex-start;">
        <div>
            <h4>Dispute Information</h4>
            <p><strong>Trade ID:</strong> <?php echo htmlspecialchars($dispute['trade_id']); ?></p>
            <p><strong>Opened By:</strong> <?php echo htmlspecialchars($dispute['opened_by_username']); ?></p>
            <p><strong>Reason:</strong> <?php echo htmlspecialchars($dispute['reason']); ?></p>
            <p><strong>Status:</strong> <span style="font-weight: bold; text-transform: capitalize;"><?php echo htmlspecialchars($dispute['status']); ?></span></p>
            <p><strong>Requested At:</strong> <?php echo date('Y-m-d H:i', strtotime($dispute['created_at'])); ?></p>
            <p><strong>Trade Amount:</strong> <?php echo number_format($dispute['amount'], 2); ?> <?php echo htmlspecialchars($dispute['currency_code']); ?></p>
            <p><strong>Buyer:</strong> <?php echo htmlspecialchars($dispute['buyer_username']); ?> (<?php echo htmlspecialchars($dispute['buyer_email']); ?>)</p>
            <p><strong>Seller:</strong> <?php echo htmlspecialchars($dispute['seller_username']); ?> (<?php echo htmlspecialchars($dispute['seller_email']); ?>)</p>

            <h4 style="margin-top: 2rem;">Dispute Messages</h4>
            <div style="max-height: 300px; overflow-y: scroll; border: 1px solid #eee; padding: 1rem; border-radius: 5px;">
                <?php if ($messages_result->num_rows > 0): ?>
                    <?php while($message = $messages_result->fetch_assoc()): ?>
                        <p><strong><?php echo htmlspecialchars($message['sender_username']); ?>:</strong> <?php echo htmlspecialchars($message['message']); ?><br><small><?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?></small></p>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No messages for this dispute.</p>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <?php if ($dispute['status'] === 'open'): ?>
                <h4>Resolve Dispute</h4>
                <form action="resolve_p2p_dispute.php?id=<?php echo $dispute_id; ?>" method="POST">
                    <div class="form-group">
                        <label for="admin_notes">Admin Notes (Optional)</label>
                        <textarea name="admin_notes" id="admin_notes" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="resolve_action" value="release_buyer" class="btn btn-success" style="width: 100%; margin-bottom: 0.5rem;">Release Funds to Buyer</button>
                        <button type="submit" name="resolve_action" value="release_seller" class="btn btn-success" style="width: 100%; margin-bottom: 0.5rem;">Release Funds to Seller</button>
                        <button type="submit" name="resolve_action" value="reject" class="btn btn-danger" style="width: 100%;">Reject Dispute</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-info">This dispute has already been <?php echo htmlspecialchars($dispute['status']); ?>.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
