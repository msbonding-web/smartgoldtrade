<?php
require_once 'header.php';
require_once '../db_connect.php';

$trade_id = $_GET['id'] ?? 0;

if (!is_numeric($trade_id) || $trade_id <= 0) {
    die("Invalid Trade ID.");
}

$process_message = '';

// Handle trade status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['new_status'] ?? '';
    $payout_tx_ref = $_POST['payout_tx_ref'] ?? ''; // For 'paid' status

    if (!in_array($new_status, ['initiated', 'payment_pending', 'paid', 'released', 'cancelled', 'disputed', 'refunded'])) {
        $process_message = "<div class=\"alert alert-danger\">Invalid status provided.</div>";
    } else {
        $conn->begin_transaction();
        try {
            $update_query = "UPDATE p2p_trades SET status = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param('si', $new_status, $trade_id);
            if (!$update_stmt->execute()) {
                throw new Exception("Error updating trade status: " . $update_stmt->error);
            }
            $update_stmt->close();

            // If status is 'paid' or 'released', update escrow and wallets
            if ($new_status === 'paid' || $new_status === 'released') {
                // Fetch trade details again to get amount, buyer, seller, currency
                $trade_info_query = "SELECT amount, buyer_id, seller_id, currency_id FROM p2p_trades WHERE id = ?";
                $trade_info_stmt = $conn->prepare($trade_info_query);
                $trade_info_stmt->bind_param('i', $trade_id);
                $trade_info_stmt->execute();
                $trade_info = $trade_info_stmt->get_result()->fetch_assoc();
                $trade_info_stmt->close();

                if ($trade_info) {
                    $amount = $trade_info['amount'];
                    $buyer_id = $trade_info['buyer_id'];
                    $seller_id = $trade_info['seller_id'];
                    $currency_id = $trade_info['currency_id'];

                    // Update escrow status (assuming escrow is created when trade is initiated)
                    $update_escrow_query = "UPDATE p2p_escrows SET status = ?, released_at = NOW() WHERE trade_id = ?";
                    $escrow_status = ($new_status === 'paid') ? 'released' : 'held'; // Adjust based on actual P2P flow
                    $update_escrow_stmt = $conn->prepare($update_escrow_query);
                    $update_escrow_stmt->bind_param('si', $escrow_status, $trade_id);
                    if (!$update_escrow_stmt->execute()) {
                        throw new Exception("Error updating escrow status: " . $update_escrow_stmt->error);
                    }
                    $update_escrow_stmt->close();

                    // Credit seller's wallet (assuming funds are released to seller upon 'paid' or 'released')
                    $seller_wallet_query = "SELECT id, balance FROM wallets WHERE user_id = ? AND currency_id = ? AND wallet_type_id = (SELECT id FROM wallet_types WHERE slug = 'main')";
                    $seller_wallet_stmt = $conn->prepare($seller_wallet_query);
                    $seller_wallet_stmt->bind_param('ii', $seller_id, $currency_id);
                    $seller_wallet_stmt->execute();
                    $seller_wallet = $seller_wallet_stmt->get_result()->fetch_assoc();
                    $seller_wallet_stmt->close();

                    if (!$seller_wallet) throw new Exception("Seller main wallet not found.");

                    $update_seller_wallet_query = "UPDATE wallets SET balance = balance + ?, available = available + ? WHERE id = ?";
                    $update_seller_wallet_stmt = $conn->prepare($update_seller_wallet_query);
                    $update_seller_wallet_stmt->bind_param('ddi', $amount, $amount, $seller_wallet['id']);
                    if (!$update_seller_wallet_stmt->execute()) throw new Exception("Error crediting seller wallet.");
                    $update_seller_wallet_stmt->close();

                    // Record wallet transaction for seller
                    $seller_balance_after_query = "SELECT balance FROM wallets WHERE id = ?";
                    $seller_balance_after_stmt = $conn->prepare($seller_balance_after_query);
                    $seller_balance_after_stmt->bind_param('i', $seller_wallet['id']);
                    $seller_balance_after_stmt->execute();
                    $seller_balance_after = $seller_balance_after_stmt->get_result()->fetch_assoc()['balance'];
                    $seller_balance_after_stmt->close();

                    $seller_balance_before = $seller_balance_after - $amount;
                    $seller_tx_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, 'credit', ?, ?, ?, 'p2p_trade_release', ?, ?)";
                    $seller_tx_stmt = $conn->prepare($seller_tx_query);
                    $seller_tx_stmt->bind_param('idddis', $seller_wallet['id'], $amount, $seller_balance_before, $seller_balance_after, $trade_id, 'P2P Trade Release');
                    if (!$seller_tx_stmt->execute()) throw new Exception("Error recording seller transaction.");
                    $seller_tx_stmt->close();
                }
            }

            $conn->commit();
            header("Location: p2p_trade_details.php?id=" . $trade_id . "&update=success");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $process_message = "<div class=\"alert alert-danger\">Error processing trade: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch trade details
$trade_query = "
    SELECT 
        pt.*, 
        po.side, po.price as offer_price, po.asset_currency_id,
        b.username as buyer_username, b.email as buyer_email,
        s.username as seller_username, s.email as seller_email,
        c.code as currency_code, ac.code as asset_currency_code
    FROM p2p_trades pt
    JOIN p2p_offers po ON pt.offer_id = po.id
    JOIN users b ON pt.buyer_id = b.id
    JOIN users s ON pt.seller_id = s.id
    JOIN currencies c ON pt.currency_id = c.id
    JOIN currencies ac ON po.asset_currency_id = ac.id
    WHERE pt.id = ?
";
$trade_stmt = $conn->prepare($trade_query);
$trade_stmt->bind_param('i', $trade_id);
$trade_stmt->execute();
$trade_result = $trade_stmt->get_result();
if ($trade_result->num_rows === 1) {
    $trade = $trade_result->fetch_assoc();
} else {
    die("Trade not found.");
}
$trade_stmt->close();

// Fetch trade messages
$messages_query = "
    SELECT pm.*, u.username as sender_username
    FROM p2p_messages pm
    JOIN users u ON pm.sender_id = u.id
    WHERE pm.trade_id = ?
    ORDER BY pm.created_at ASC
";
$messages_stmt = $conn->prepare($messages_query);
$messages_stmt->bind_param('i', $trade_id);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();
$messages_stmt->close();

$conn->close();
?>

<a href="p2p_management.php?tab=trades" class="btn" style="margin-bottom: 1rem; background-color: var(--gray);">← Back to Trades</a>

<div class="card">
    <h3>P2P Trade Details: #<?php echo htmlspecialchars($trade['id']); ?></h3>

    <?php echo $process_message; // Display messages ?>

    <div class="grid-container" style="grid-template-columns: 1fr 1fr; align-items: flex-start;">
        <div>
            <h4>Trade Information</h4>
            <p><strong>Trade ID:</strong> <?php echo htmlspecialchars($trade['id']); ?></p>
            <p><strong>Offer ID:</strong> <?php echo htmlspecialchars($trade['offer_id']); ?></p>
            <p><strong>Buyer:</strong> <?php echo htmlspecialchars($trade['buyer_username']); ?> (<?php echo htmlspecialchars($trade['buyer_email']); ?>)</p>
            <p><strong>Seller:</strong> <?php echo htmlspecialchars($trade['seller_username']); ?> (<?php echo htmlspecialchars($trade['seller_email']); ?>)</p>
            <p><strong>Amount:</strong> <?php echo number_format($trade['amount'], 2); ?> <?php echo htmlspecialchars($trade['currency_code']); ?></p>
            <p><strong>Price:</strong> <?php echo number_format($trade['price'], 2); ?></p>
            <p><strong>Status:</strong> <span style="font-weight: bold; text-transform: capitalize;"><?php echo htmlspecialchars($trade['status']); ?></span></p>
            <p><strong>Created At:</strong> <?php echo date('Y-m-d H:i', strtotime($trade['created_at'])); ?></p>
            <p><strong>Updated At:</strong> <?php echo date('Y-m-d H:i', strtotime($trade['updated_at'])); ?></p>
        </div>

        <div>
            <h4>Trade Actions</h4>
            <?php if ($trade['status'] === 'payment_pending'): ?>
                <form action="p2p_trade_details.php?id=<?php echo $trade_id; ?>" method="POST">
                    <div class="form-group">
                        <button type="submit" name="update_status" value="paid" class="btn btn-success" style="width: 100%; margin-bottom: 0.5rem;">Mark as Paid</button>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="update_status" value="cancelled" class="btn btn-danger" style="width: 100%;">Cancel Trade</button>
                    </div>
                </form>
            <?php elseif ($trade['status'] === 'paid'): ?>
                <form action="p2p_trade_details.php?id=<?php echo $trade_id; ?>" method="POST">
                    <div class="form-group">
                        <button type="submit" name="update_status" value="released" class="btn btn-success" style="width: 100%;">Release Funds</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-info">No actions available for this trade status.</div>
            <?php endif; ?>
        </div>
    </div>

    <h4 style="margin-top: 2rem;">Trade Messages</h4>
    <div style="max-height: 300px; overflow-y: scroll; border: 1px solid #eee; padding: 1rem; border-radius: 5px;">
        <?php if ($messages_result->num_rows > 0): ?>
            <?php while($message = $messages_result->fetch_assoc()): ?>
                <p><strong><?php echo htmlspecialchars($message['sender_username']); ?>:</strong> <?php echo htmlspecialchars($message['message']); ?><br><small><?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?></small></p>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No messages for this trade.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>
