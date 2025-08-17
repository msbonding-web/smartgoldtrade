<?php
session_start();
require_once '../header.php';
require_once '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

$offer_id = $_GET['offer_id'] ?? null;
$message = '';
$offer_details = null;

if ($offer_id) {
    $offer_query = "
        SELECT po.id, po.user_id, po.side, po.price, po.min_amount, po.max_amount, po.payment_methods, po.terms,
               u.username, c.code AS asset_currency_code, c.name AS asset_currency_name
        FROM p2p_offers po
        JOIN users u ON po.user_id = u.id
        JOIN currencies c ON po.asset_currency_id = c.id
        WHERE po.id = ? AND po.status = 'active'
    ";
    $stmt = $conn->prepare($offer_query);
    if ($stmt) {
        $stmt->bind_param('i', $offer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $offer_details = $result->fetch_assoc();
            $offer_details['payment_methods'] = json_decode($offer_details['payment_methods'], true);
        } else {
            $message = '<div class="alert alert-danger">Offer not found or not active.</div>';
        }
        $stmt->close();
    } else {
        $message = '<div class="alert alert-danger">Database error: ' . $conn->error . '</div>';
    }
} else {
    $message = '<div class="alert alert-danger">No offer ID specified.</div>';
}

$conn->close();
?>

<style>
    .trade-details-container {
        padding: 20px;
        background-color: #f4f7f6;
    }
    .trade-details-grid {
        display: grid;
        grid-template-columns: 1.5fr 1fr;
        gap: 30px;
        align-items: flex-start;
    }
    .p2p-card {
        background-color: #ffffff;
        border: 1px solid #e7e7e7;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .p2p-card h4 {
        margin-top: 0;
        margin-bottom: 20px;
        font-weight: 600;
        color: #333;
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
    }
    .info-list { list-style: none; padding: 0; margin: 0; }
    .info-list li { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
    .info-list li:last-child { border-bottom: none; }
    .info-list .label { font-weight: 500; color: #555; }
    .info-list .value { color: #333; font-weight: 600; text-align: right; }
    .badge-success { background-color: #28a745; color: white; padding: 5px 10px; border-radius: 12px; font-size: 12px; }
    .badge-danger { background-color: #dc3545; color: white; padding: 5px 10px; border-radius: 12px; font-size: 12px; }
    
    .form-group label {
        color: #333 !important;
        margin-bottom: 8px;
        display: block;
    }
    
    .trade-calculation {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-top: 20px;
        border: 1px solid #e7e7e7;
    }
    .trade-calculation p {
        margin: 8px 0;
        display: flex;
        justify-content: space-between;
        color: #555;
    }
    .btn-gold {
        width: 100%;
        background-color: #D4AF37 !important;
        border-color: #D4AF37 !important;
        color: #fff !important;
        padding: 12px;
        font-weight: bold;
        margin-top: 20px;
    }

    @media (max-width: 992px) {
        .trade-details-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="trade-details-container">
    <h3>P2P Trade Details</h3>
    <a href="p2p_market.php" class="btn btn-secondary mb-3" style="margin-bottom: 20px; display: inline-block;">← Back to Market</a>

    <?php echo $message; ?>

    <?php if ($offer_details): ?>
        <div class="trade-details-grid">
            <!-- Left Column: Offer Summary -->
            <div class="p2p-card">
                <h4>Offer Summary</h4>
                <ul class="info-list">
                    <li><span class="label">Offered by:</span> <span class="value"><?php echo htmlspecialchars($offer_details['username']); ?></span></li>
                    <li><span class="label">Type:</span> <span class="value"><span class="badge <?php echo ($offer_details['side'] == 'buy' ? 'badge-success' : 'badge-danger'); ?>"><?php echo ucfirst($offer_details['side']); ?></span></span></li>
                    <li><span class="label">Asset:</span> <span class="value"><?php echo htmlspecialchars($offer_details['asset_currency_name'] . ' (' . $offer_details['asset_currency_code'] . ')'); ?></span></li>
                    <li><span class="label">Price:</span> <span class="value">$<?php echo rtrim(rtrim(number_format($offer_details['price'], 8), '0'), '.'); ?> / <?php echo htmlspecialchars($offer_details['asset_currency_code']); ?></span></li>
                    <li><span class="label">Available Limit:</span> <span class="value"><?php echo rtrim(rtrim(number_format($offer_details['min_amount'], 8), '0'), '.'); ?> - <?php echo rtrim(rtrim(number_format($offer_details['max_amount'], 8), '0'), '.'); ?> <?php echo htmlspecialchars($offer_details['asset_currency_code']); ?></span></li>
                    <li><span class="label">Payment Methods:</span> <span class="value"><?php echo implode(', ', array_map('ucwords', array_map('htmlspecialchars', $offer_details['payment_methods']))); ?></span></li>
                    <li><span class="label">Terms:</span> <span class="value"><?php echo !empty($offer_details['terms']) ? nl2br(htmlspecialchars($offer_details['terms'])) : 'N/A'; ?></span></li>
                </ul>
            </div>

            <!-- Right Column: Initiate Trade -->
            <div class="p2p-card">
                <h4>Initiate Trade</h4>
                <form action="p2p_start_trade.php" method="POST">
                    <input type="hidden" name="offer_id" value="<?php echo $offer_details['id']; ?>">
                    <div class="form-group">
                        <label for="amount_to_pay">I want to pay (USD):</label>
                        <input type="number" step="0.01" id="amount_to_pay" name="amount_to_pay" class="form-control" placeholder="e.g., 100.00">
                    </div>
                    <div class="form-group">
                        <label for="amount_to_receive">I will receive (<?php echo htmlspecialchars($offer_details['asset_currency_code']); ?>):</label>
                        <input type="number" step="0.00000001" id="amount_to_receive" name="amount_to_receive" class="form-control" placeholder="e.g., 1.50">
                    </div>

                    <div class="trade-calculation">
                        <p><span>Price:</span> <strong id="calc_price">$<?php echo rtrim(rtrim(number_format($offer_details['price'], 8), '0'), '.'); ?></strong></p>
                        <p><span>You will receive:</span> <strong id="calc_receive">0.00 <?php echo htmlspecialchars($offer_details['asset_currency_code']); ?></strong></p>
                    </div>

                    <button type="submit" name="initiate_trade" class="btn btn-gold">Initiate Trade</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const amountToPayInput = document.getElementById('amount_to_pay');
    const amountToReceiveInput = document.getElementById('amount_to_receive');
    const price = <?php echo $offer_details['price'] ?? 0; ?>;
    
    const calcReceive = document.getElementById('calc_receive');

    if(amountToPayInput && amountToReceiveInput && price > 0) {
        amountToPayInput.addEventListener('input', function() {
            const payValue = parseFloat(this.value) || 0;
            const receiveValue = payValue / price;
            amountToReceiveInput.value = receiveValue.toFixed(8);
            calcReceive.textContent = receiveValue.toFixed(8) + ' <?php echo htmlspecialchars($offer_details['asset_currency_code']); ?>';
        });

        amountToReceiveInput.addEventListener('input', function() {
            const receiveValue = parseFloat(this.value) || 0;
            const payValue = receiveValue * price;
            amountToPayInput.value = payValue.toFixed(2);
            calcReceive.textContent = receiveValue.toFixed(8) + ' <?php echo htmlspecialchars($offer_details['asset_currency_code']); ?>';
        });
    }
});
</script>

<?php require_once '../footer.php'; ?>
