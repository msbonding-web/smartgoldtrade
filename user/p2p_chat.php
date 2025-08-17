<?php
session_start();
require_once '../header.php';
require_once '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

$trade_id = $_GET['trade_id'] ?? 0;
$message = '';

if ($trade_id <= 0) {
    die("Invalid Trade ID.");
}

// Fetch trade details
$trade_query = "
    SELECT pt.*, b.username as buyer_username, s.username as seller_username, 
           c.code as asset_currency_code, po.payment_methods
    FROM p2p_trades pt
    JOIN users b ON pt.buyer_id = b.id
    JOIN users s ON pt.seller_id = s.id
    JOIN p2p_offers po ON pt.offer_id = po.id
    JOIN currencies c ON po.asset_currency_id = c.id
    WHERE pt.id = ?
";
$trade_stmt = $conn->prepare($trade_query);
$trade_stmt->bind_param('i', $trade_id);
$trade_stmt->execute();
$trade_result = $trade_stmt->get_result();
if ($trade_result->num_rows === 0) {
    die("Trade not found.");
}
$trade = $trade_result->fetch_assoc();
$trade_stmt->close();

// Security check: ensure the current user is part of this trade
if ($current_user_id != $trade['buyer_id'] && $current_user_id != $trade['seller_id']) {
    die("You are not authorized to view this trade.");
}

// Determine user's role in this trade
$user_role = ($current_user_id == $trade['buyer_id']) ? 'buyer' : 'seller';

// Handle form submissions (sending message, updating status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle sending a new message
    if (isset($_POST['send_message'])) {
        $new_message = trim($_POST['message'] ?? '');
        if (!empty($new_message)) {
            $insert_msg_stmt = $conn->prepare("INSERT INTO p2p_messages (trade_id, sender_id, message) VALUES (?, ?, ?)");
            $insert_msg_stmt->bind_param('iis', $trade_id, $current_user_id, $new_message);
            $insert_msg_stmt->execute();
            $insert_msg_stmt->close();
            header("Location: p2p_chat.php?trade_id=" . $trade_id); // Refresh to show new message
            exit();
        }
    }

    // Handle trade status updates
    if (isset($_POST['update_status'])) {
        $new_status = $_POST['status'] ?? '';
        
        // Logic for Buyer marking as 'paid'
        if ($new_status === 'paid' && $user_role === 'buyer' && $trade['status'] === 'initiated') {
            $update_stmt = $conn->prepare("UPDATE p2p_trades SET status = 'paid' WHERE id = ?");
            $update_stmt->bind_param('i', $trade_id);
            $update_stmt->execute();
            $update_stmt->close();
            header("Location: p2p_chat.php?trade_id=" . $trade_id);
            exit();
        }
        
        // Add logic for 'release' by seller, etc. in the next steps
    }
}


// Fetch trade messages
$messages = [];
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
while ($row = $messages_result->fetch_assoc()) {
    $messages[] = $row;
}
$messages_stmt->close();

$conn->close();
?>

<style>
    .trade-page-container { padding: 20px; background-color: #f4f7f6; }
    .trade-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; align-items: flex-start; }
    .p2p-card { background-color: #ffffff; border: 1px solid #e7e7e7; border-radius: 8px; padding: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
    .p2p-card h4 { margin-top: 0; margin-bottom: 20px; font-weight: 600; color: #333; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .info-list { list-style: none; padding: 0; margin: 0; }
    .info-list li { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
    .info-list .label { font-weight: 500; color: #555; }
    .info-list .value { color: #333; font-weight: 600; text-align: right; }
    .status-badge { padding: 5px 12px; border-radius: 15px; font-weight: bold; text-transform: capitalize; color: #fff; }
    .status-initiated { background-color: #ffc107; color: #333; }
    .status-paid { background-color: #17a2b8; }
    .status-released { background-color: #28a745; }
    .status-cancelled, .status-disputed { background-color: #dc3545; }

    /* Chat Box Styles */
    .chat-box { display: flex; flex-direction: column; height: 500px; }
    .chat-messages { flex-grow: 1; overflow-y: auto; padding: 15px; border: 1px solid #ddd; border-radius: 5px 5px 0 0; }
    .message { margin-bottom: 15px; }
    .message .sender { font-weight: bold; color: #333; }
    .message .timestamp { font-size: 0.75rem; color: #999; margin-left: 10px; }
    .message p { margin: 5px 0 0 0; background: #f1f1f1; padding: 10px; border-radius: 10px; display: inline-block; max-width: 80%; }
    .message.sent p { background: #D4AF37; color: #fff; }
    .message.sent { text-align: right; }
    .chat-form { display: flex; border: 1px solid #ddd; border-top: none; border-radius: 0 0 5px 5px; }
    .chat-form input { flex-grow: 1; border: none; padding: 15px; outline: none; }
    .chat-form button { background: #D4AF37; color: #fff; border: none; padding: 0 20px; cursor: pointer; font-weight: bold; }

    @media (max-width: 992px) { .trade-grid { grid-template-columns: 1fr; } }
</style>

<div class="trade-page-container">
    <h3>Active P2P Trade #<?php echo htmlspecialchars($trade_id); ?></h3>
    
    <div class="trade-grid">
        <!-- Left Column: Trade Info & Actions -->
        <div>
            <div class="p2p-card">
                <h4>Trade Details</h4>
                <ul class="info-list">
                    <li><span class="label">Status:</span> <span class="value"><span class="status-badge status-<?php echo strtolower($trade['status']); ?>"><?php echo htmlspecialchars($trade['status']); ?></span></span></li>
                    <li><span class="label">Amount:</span> <span class="value">$<?php echo number_format($trade['amount'], 2); ?></span></li>
                    <li><span class="label">Asset to Receive:</span> <span class="value"><?php echo rtrim(rtrim(number_format($trade['amount'] / $trade['price'], 8), '0'), '.'); ?> <?php echo htmlspecialchars($trade['asset_currency_code']); ?></span></li>
                    <li><span class="label">Buyer:</span> <span class="value"><?php echo htmlspecialchars($trade['buyer_username']); ?></span></li>
                    <li><span class="label">Seller:</span> <span class="value"><?php echo htmlspecialchars($trade['seller_username']); ?></span></li>
                    <li><span class="label">Payment Methods:</span> <span class="value"><?php echo implode(', ', array_map('ucwords', json_decode($trade['payment_methods'], true))); ?></span></li>
                </ul>
            </div>

            <div class="p2p-card" style="margin-top: 20px;">
                <h4>Trade Actions</h4>
                <?php if ($user_role === 'buyer' && $trade['status'] === 'initiated'): ?>
                    <p>Please pay <strong>$<?php echo number_format($trade['amount'], 2); ?></strong> to the seller using one of the agreed payment methods. After payment, click the button below.</p>
                    <form action="p2p_chat.php?trade_id=<?php echo $trade_id; ?>" method="POST">
                        <button type="submit" name="update_status" value="paid" class="btn btn-success w-100">I Have Paid</button>
                    </form>
                <?php elseif ($user_role === 'seller' && $trade['status'] === 'paid'): ?>
                    <p>The buyer has marked this trade as paid. Please verify you have received the payment, then release the assets.</p>
                    <form action="p2p_chat.php?trade_id=<?php echo $trade_id; ?>" method="POST">
                        <button type="submit" name="update_status" value="released" class="btn btn-success w-100">Release Asset</button>
                    </form>
                <?php elseif ($trade['status'] === 'released'): ?>
                    <div class="alert alert-success">This trade has been completed successfully.</div>
                <?php else: ?>
                    <p>Waiting for the other party to take action.</p>
                <?php endif; ?>
                
                <hr>
                <p>If you have a problem with this trade, you can open a dispute.</p>
                <a href="p2p_dispute.php?trade_id=<?php echo $trade_id; ?>" class="btn btn-danger w-100">Open Dispute</a>
            </div>
        </div>

        <!-- Right Column: Chat Box -->
        <div class="p2p-card">
            <h4>Trade Chat</h4>
            <div class="chat-box">
                <div class="chat-messages" id="chat-messages">
                    <?php if (empty($messages)): ?>
                        <p class="text-center" style="color: #888;">No messages yet. Start the conversation!</p>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?php echo ($msg['sender_id'] == $current_user_id) ? 'sent' : 'received'; ?>">
                                <span class="sender"><?php echo htmlspecialchars($msg['sender_username']); ?></span>
                                <span class="timestamp"><?php echo date('h:i A', strtotime($msg['created_at'])); ?></span>
                                <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <form class="chat-form" action="p2p_chat.php?trade_id=<?php echo $trade_id; ?>" method="POST">
                    <input type="text" name="message" placeholder="Type your message..." required autocomplete="off">
                    <button type="submit" name="send_message">Send</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-scroll chat to the bottom
    const chatMessages = document.getElementById('chat-messages');
    chatMessages.scrollTop = chatMessages.scrollHeight;
</script>

<?php require_once '../footer.php'; ?>
