<?php
require_once 'header.php';
require_once '../db_connect.php';

// Fetch user's trading account info
$account_info = [];
$account_query = "
    SELECT ta.equity, ta.margin_used, ta.margin_free, w.balance as wallet_balance, c.code as currency_code
    FROM trading_accounts ta
    JOIN wallets w ON ta.wallet_id = w.id
    JOIN currencies c ON w.currency_id = c.id
    WHERE ta.user_id = ?
";
$stmt = $conn->prepare($account_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $account_info = $row;
}
$stmt->close();

// Fetch trading pairs for contract info (assuming XAU/USD for example)
$xauusd_contract_info = [];
$contract_query = "SELECT * FROM trading_pairs WHERE symbol = 'XAUUSD' LIMIT 1";
$result = $conn->query($contract_query);
if ($row = $result->fetch_assoc()) {
    $xauusd_contract_info = $row;
}

// Fetch Open Trades
$open_trades_query = "
    SELECT o.*, tp.symbol, u.username
    FROM trading_orders o
    JOIN trading_accounts ta ON o.account_id = ta.id
    JOIN users u ON ta.user_id = u.id
    JOIN trading_pairs tp ON o.pair_id = tp.id
    WHERE ta.user_id = ? AND o.status = 'open'
    ORDER BY o.opened_at DESC
";
$stmt = $conn->prepare($open_trades_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$open_trades_result = $stmt->get_result();
$stmt->close();

// Fetch Pending Trades
$pending_trades_query = "
    SELECT o.*, tp.symbol, u.username
    FROM trading_orders o
    JOIN trading_accounts ta ON o.account_id = ta.id
    JOIN users u ON ta.user_id = u.id
    JOIN trading_pairs tp ON o.pair_id = tp.id
    WHERE ta.user_id = ? AND o.status = 'pending'
    ORDER BY o.opened_at DESC
";
$stmt = $conn->prepare($pending_trades_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$pending_trades_result = $stmt->get_result();
$stmt->close();

// Fetch Closed Trades
$closed_trades_query = "
    SELECT o.*, tp.symbol, u.username
    FROM trading_orders o
    JOIN trading_accounts ta ON o.account_id = ta.id
    JOIN users u ON ta.user_id = u.id
    JOIN trading_pairs tp ON o.pair_id = tp.id
    WHERE ta.user_id = ? AND o.status = 'closed'
    ORDER BY o.closed_at DESC
";
$stmt = $conn->prepare($closed_trades_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$closed_trades_result = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<style>
    .trading-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
    .trading-panel { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .trading-tabs { display: flex; border-bottom: 1px solid #eee; margin-bottom: 15px; }
    .trading-tab-link { padding: 10px 15px; cursor: pointer; border-bottom: 2px solid transparent; }
    .trading-tab-link.active { border-color: var(--primary-color); font-weight: bold; }
    .trading-tab-content { display: none; }
    .trading-tab-content.active { display: block; }
</style>

<div class="trading-grid">
    <!-- Left Column: Chart and Order Placement -->
    <div>
        <div class="trading-panel">
            <h4>Live TradingView Chart (XAU/USD)</h4>
            <img src="https://via.placeholder.com/800x400?text=Live+TradingView+Chart" alt="Trading Chart" style="width: 100%; border-radius: 5px;">
        </div>

        <div class="trading-panel" style="margin-top: 20px;">
            <h4>Place Order</h4>
            <form>
                <div class="form-group">
                    <label>Symbol</label>
                    <select class="form-control"><option>XAU/USD</option><option>XAU/BTC</option></select>
                </div>
                <div class="form-group">
                    <label>Order Type</label>
                    <select class="form-control"><option>Market</option><option>Limit</option><option>Stop</option></select>
                </div>
                <div class="form-group">
                    <label>Volume (Lots)</label>
                    <input type="number" step="0.01" class="form-control" value="0.01">
                </div>
                <div class="form-group">
                    <label>Take Profit (Optional)</label>
                    <input type="number" step="0.01" class="form-control">
                </div>
                <div class="form-group">
                    <label>Stop Loss (Optional)</label>
                    <input type="number" step="0.01" class="form-control">
                </div>
                <button type="submit" class="btn btn-success">Buy</button>
                <button type="submit" class="btn btn-danger">Sell</button>
            </form>
        </div>
    </div>

    <!-- Right Column: Account Info and Contract Info -->
    <div>
        <div class="trading-panel">
            <h4>Account Information</h4>
            <p><strong>Balance:</strong> $<?php echo number_format($account_info['wallet_balance'] ?? 0, 2); ?> <?php echo htmlspecialchars($account_info['currency_code'] ?? 'USD'); ?></p>
            <p><strong>Equity:</strong> $<?php echo number_format($account_info['equity'] ?? 0, 2); ?></p>
            <p><strong>Used Margin:</strong> $<?php echo number_format($account_info['margin_used'] ?? 0, 2); ?></p>
            <p><strong>Free Margin:</strong> $<?php echo number_format($account_info['margin_free'] ?? 0, 2); ?></p>
        </div>

        <div class="trading-panel" style="margin-top: 20px;">
            <h4>XAU/USD Contract Info</h4>
            <p><strong>Contract Size:</strong> <?php echo number_format($xauusd_contract_info['contract_size'] ?? 0); ?> oz per lot</p>
            <p><strong>Leverage:</strong> 1:<?php echo htmlspecialchars($xauusd_contract_info['leverage'] ?? 0); ?></p>
            <p><strong>Min Lot:</strong> <?php echo number_format($xauusd_contract_info['min_lot'] ?? 0, 2); ?></p>
            <p><strong>Max Lot:</strong> <?php echo number_format($xauusd_contract_info['max_lot'] ?? 0, 2); ?></p>
            <p><strong>Pip Value:</strong> $<?php echo number_format($xauusd_contract_info['pip_value'] ?? 0, 2); ?></p>
        </div>
    </div>
</div>

<div class="trading-panel" style="margin-top: 20px;">
    <h4>Trade History</h4>
    <div class="trading-tabs">
        <div class="trading-tab-link active" onclick="openTradingTab(event, 'open-trades')">Open Trades</div>
        <div class="trading-tab-link" onclick="openTradingTab(event, 'pending-trades')">Pending Trades</div>
        <div class="trading-tab-link" onclick="openTradingTab(event, 'closed-trades')">Closed Trades</div>
    </div>

    <!-- Open Trades Tab -->
    <div id="open-trades" class="trading-tab-content active">
        <table class="table">
            <thead>
                <tr><th>Symbol</th><th>Type</th><th>Volume</th><th>Open Price</th><th>P/L (USD)</th><th>Open Time</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($open_trades_result && $open_trades_result->num_rows > 0): ?>
                    <?php while($trade = $open_trades_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trade['symbol']); ?></td>
                            <td><?php echo htmlspecialchars($trade['side']); ?></td>
                            <td><?php echo number_format($trade['volume'], 8); ?></td>
                            <td><?php echo number_format($trade['open_price'], 8); ?></td>
                            <td>$0.00 <em>(Live P/L)</em></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($trade['opened_at'])); ?></td>
                            <td><button class="btn btn-danger btn-sm">Close</button></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center;">No open trades.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pending Trades Tab -->
    <div id="pending-trades" class="trading-tab-content">
        <table class="table">
            <thead>
                <tr><th>Symbol</th><th>Type</th><th>Volume</th><th>Open Price</th><th>Time</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($pending_trades_result && $pending_trades_result->num_rows > 0): ?>
                    <?php while($trade = $pending_trades_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trade['symbol']); ?></td>
                            <td><?php echo htmlspecialchars($trade['side']); ?></td>
                            <td><?php echo number_format($trade['volume'], 8); ?></td>
                            <td><?php echo number_format($trade['open_price'], 8); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($trade['opened_at'])); ?></td>
                            <td><button class="btn btn-danger btn-sm">Cancel</button></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">No pending trades.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Closed Trades Tab -->
    <div id="closed-trades" class="trading-tab-content">
        <table class="table">
            <thead>
                <tr><th>Symbol</th><th>Type</th><th>Volume</th><th>Open Price</th><th>Close Price</th><th>P/L (USD)</th><th>Open Time</th><th>Close Time</th></tr>
            </thead>
            <tbody>
                <?php if ($closed_trades_result && $closed_trades_result->num_rows > 0): ?>
                    <?php while($trade = $closed_trades_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trade['symbol']); ?></td>
                            <td><?php echo htmlspecialchars($trade['side']); ?></td>
                            <td><?php echo number_format($trade['volume'], 8); ?></td>
                            <td><?php echo number_format($trade['open_price'], 8); ?></td>
                            <td><?php echo number_format($trade['close_price'] ?? 0, 8); ?></td>
                            <td>$<?php echo number_format($trade['pnl'] ?? 0, 2); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($trade['opened_at'])); ?></td>
                            <td><?php echo $trade['closed_at'] ? date('Y-m-d H:i', strtotime($trade['closed_at'])) : 'N/A'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align: center;">No closed trades.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openTradingTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("trading-tab-content");
    for (i = 0; i < tabcontent.length; i++) { tabcontent[i].style.display = "none"; }
    tablinks = document.getElementsByClassName("trading-tab-link");
    for (i = 0; i < tablinks.length; i++) { tablinks[i].className = tablinks[i].className.replace(" active", ""); }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}
</script>

<?php require_once 'footer.php'; ?>
