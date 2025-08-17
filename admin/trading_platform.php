<?php
require_once 'header.php';
require_once '../db_connect.php';

$risk_message = '';
$price_feed_message = '';
$fee_message = '';

// Handle Save Risk Settings submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_risk_settings'])) {
    $max_lot_size = $_POST['max_lot_size'] ?? 0;
    $max_open_trades = $_POST['max_open_trades'] ?? 0;
    $global_stop_loss_limit = $_POST['global_stop_loss_limit'] ?? 0;

    // Update settings table
    $settings_to_update = [
        'max_lot_size_per_user' => $max_lot_size,
        'max_open_trades_per_user' => $max_open_trades,
        'global_stop_loss_limit' => $global_stop_loss_limit
    ];

    foreach ($settings_to_update as $key => $value) {
        $query = "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sd', $key, $value);
        if (!$stmt->execute()) {
            $risk_message = "<div class=\"alert alert-danger\">Error saving settings: " . $stmt->error . "</div>";
            break;
        }
        $stmt->close();
    }

    if (empty($risk_message)) {
        $risk_message = "<div class=\"alert alert-success\">Risk settings saved successfully!</div>";
    }
}

// Handle Save Price Feed Settings submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_price_feed_settings'])) {
    $primary_price_feed_api = $_POST['primary_price_feed_api'] ?? '';
    $manual_price_override_xauusd = $_POST['manual_price_override_xauusd'] ?? '';

    // Update settings table
    $settings_to_update = [
        'primary_price_feed_api' => $primary_price_feed_api,
        'manual_price_override_xauusd' => $manual_price_override_xauusd
    ];

    foreach ($settings_to_update as $key => $value) {
        $query = "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $key, $value);
        if (!$stmt->execute()) {
            $price_feed_message = "<div class=\"alert alert-danger\">Error saving settings: " . $stmt->error . "</div>";
            break;
        }
        $stmt->close();
    }

    if (empty($price_feed_message)) {
        $price_feed_message = "<div class=\"alert alert-success\">Price feed settings saved successfully!</div>";
    }
}

// Handle Save Fee Settings submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_fee_settings'])) {
    $spread_per_lot = $_POST['spread_per_lot'] ?? 0;
    $swap_fee_per_night = $_POST['swap_fee_per_night'] ?? 0;
    $commission_per_trade = $_POST['commission_per_trade'] ?? 0;

    // Update settings table
    $settings_to_update = [
        'spread_per_lot' => $spread_per_lot,
        'swap_fee_per_night' => $swap_fee_per_night,
        'commission_per_trade' => $commission_per_trade
    ];

    foreach ($settings_to_update as $key => $value) {
        $query = "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sd', $key, $value);
        if (!$stmt->execute()) {
            $fee_message = "<div class=\"alert alert-danger\">Error saving settings: " . $stmt->error . "</div>";
            break;
        }
        $stmt->close();
    }

    if (empty($fee_message)) {
        $fee_message = "<div class=\"alert alert-success\">Fee settings saved successfully!</div>";
    }
}

// Fetch current risk settings to pre-fill form
$current_risk_settings = [];
$risk_settings_keys = ['max_lot_size_per_user', 'max_open_trades_per_user', 'global_stop_loss_limit'];
foreach ($risk_settings_keys as $key) {
    $query = "SELECT `value` FROM settings WHERE `key` = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_risk_settings[$key] = $row['value'];
    } else {
        $current_risk_settings[$key] = ''; // Default empty
    }
    $stmt->close();
}

// Fetch current price feed settings to pre-fill form
$current_price_feed_settings = [];
$price_feed_settings_keys = ['primary_price_feed_api', 'manual_price_override_xauusd'];
foreach ($price_feed_settings_keys as $key) {
    $query = "SELECT `value` FROM settings WHERE `key` = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_price_feed_settings[$key] = $row['value'];
    } else {
        $current_price_feed_settings[$key] = ''; // Default empty
    }
    $stmt->close();
}

// Fetch current fee settings to pre-fill form
$current_fee_settings = [];
$fee_settings_keys = ['spread_per_lot', 'swap_fee_per_night', 'commission_per_trade'];
foreach ($fee_settings_keys as $key) {
    $query = "SELECT `value` FROM settings WHERE `key` = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_fee_settings[$key] = $row['value'];
    } else {
        $current_fee_settings[$key] = ''; // Default empty
    }
    $stmt->close();
}

// Fetch all trading orders for the live monitoring table
$trades_query = "
    SELECT 
        o.id, o.side, o.order_type, o.volume, o.open_price, o.status, o.opened_at,
        u.username,
        tp.symbol
    FROM trading_orders o
    JOIN trading_accounts ta ON o.account_id = ta.id
    JOIN users u ON ta.user_id = u.id
    JOIN trading_pairs tp ON o.pair_id = tp.id
    WHERE o.status IN ('open', 'pending')
    ORDER BY o.opened_at DESC
";
$trades_result = $conn->query($trades_query);

?>

<style>
    .tabs { display: flex; border-bottom: 2px solid #ccc; margin-bottom: 2rem; }
    .tab-link { padding: 1rem 1.5rem; cursor: pointer; border-bottom: 2px solid transparent; }
    .tab-link.active { border-color: var(--accent-color); font-weight: bold; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .alert-success { background-color: var(--success); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
    .alert-danger { background-color: var(--danger); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
</style>

<div class="card">
    <h3>Trading Platform Management</h3>

    <div class="tabs">
        <div class="tab-link active" onclick="openTab(event, 'live-trades')">Live Trade Monitoring</div>
        <div class="tab-link" onclick="openTab(event, 'risk-control')">Risk Control</div>
        <div class="tab-link" onclick="openTab(event, 'price-feed')">Price Feed</div>
        <div class="tab-link" onclick="openTab(event, 'fee-setup')">Fee & Commission Setup</div>
    </div>

    <!-- Live Trades Tab -->
    <div id="live-trades" class="tab-content active">
        <h4>Live & Pending Orders</h4>
        <table class="table">
            <thead>
                <tr><th>User</th><th>Symbol</th><th>Side</th><th>Volume</th><th>Open Price</th><th>Status</th><th>Opened At</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($trades_result && $trades_result->num_rows > 0): ?>
                    <?php while($trade = $trades_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trade['username']); ?></td>
                            <td><?php echo htmlspecialchars($trade['symbol']); ?></td>
                            <td><?php echo htmlspecialchars($trade['side']); ?></td>
                            <td><?php echo htmlspecialchars($trade['volume']); ?></td>
                            <td><?php echo htmlspecialchars($trade['open_price']); ?></td>
                            <td><?php echo htmlspecialchars($trade['status']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($trade['opened_at'])); ?></td>
                            <td><a href="force_close_trade.php?id=<?php echo $trade['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to force close this trade?');">Force Close</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align: center;">No live or pending trades found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Risk Control Tab -->
    <div id="risk-control" class="tab-content">
        <h4>Global Trade Risk Control</h4>
        <?php echo $risk_message; // Display risk messages ?>
        <form action="trading_platform.php" method="POST">
            <div class="form-group">
                <label>Max Lot Size Per User</label>
                <input type="number" class="form-control" name="max_lot_size" value="<?php echo htmlspecialchars($current_risk_settings['max_lot_size_per_user'] ?? '100'); ?>">
            </div>
            <div class="form-group">
                <label>Max Open Trades Per User</label>
                <input type="number" class="form-control" name="max_open_trades" value="<?php echo htmlspecialchars($current_risk_settings['max_open_trades_per_user'] ?? '10'); ?>">
            </div>
            <div class="form-group">
                <label>Global Stop Loss Limit (in pips)</label>
                <input type="number" class="form-control" name="global_stop_loss_limit" value="<?php echo htmlspecialchars($current_risk_settings['global_stop_loss_limit'] ?? '500'); ?>">
            </div>
            <button type="submit" name="save_risk_settings" class="btn btn-primary">Save Risk Settings</button>
        </form>
    </div>

    <!-- Price Feed Tab -->
    <div id="price-feed" class="tab-content">
        <h4>Price Feed Management</h4>
        <?php echo $price_feed_message; // Display price feed messages ?>
        <form action="trading_platform.php" method="POST">
            <div class="form-group">
                <label>Primary Price Feed API</label>
                <select class="form-control" name="primary_price_feed_api">
                    <option value="Provider A" <?php if($current_price_feed_settings['primary_price_feed_api'] === 'Provider A') echo 'selected'; ?>>Provider A</option>
                    <option value="Provider B" <?php if($current_price_feed_settings['primary_price_feed_api'] === 'Provider B') echo 'selected'; ?>>Provider B</option>
                </select>
            </div>
            <div class="form-group">
                <label>Manual Price Override for XAU/USD</label>
                <input type="text" class="form-control" name="manual_price_override_xauusd" placeholder="Enter manual price..." value="<?php echo htmlspecialchars($current_price_feed_settings['manual_price_override_xauusd'] ?? ''); ?>">
            </div>
            <button type="submit" name="save_price_feed_settings" class="btn btn-primary">Update Price Feed</button>
        </form>
    </div>

    <!-- Fee Setup Tab -->
    <div id="fee-setup" class="tab-content">
        <h4>Fee & Commission Setup</h4>
        <?php echo $fee_message; // Display fee messages ?>
        <form action="trading_platform.php" method="POST">
            <div class="form-group">
                <label>Spread per Lot (XAU/USD)</label>
                <input type="number" step="0.01" class="form-control" name="spread_per_lot" value="<?php echo htmlspecialchars($current_fee_settings['spread_per_lot'] ?? '0.20'); ?>">
            </div>
            <div class="form-group">
                <label>Swap Fee (per night)</label>
                <input type="number" step="0.01" class="form-control" name="swap_fee_per_night" value="<?php echo htmlspecialchars($current_fee_settings['swap_fee_per_night'] ?? '-2.50'); ?>">
            </div>
            <div class="form-group">
                <label>Commission per Trade (%)</label>
                <input type="number" step="0.01" class="form-control" name="commission_per_trade" value="<?php echo htmlspecialchars($current_fee_settings['commission_per_trade'] ?? '0.05'); ?>">
            </div>
            <button type="submit" name="save_fee_settings" class="btn btn-primary">Save Fee Settings</button>
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
    if (urlParams.has('risk_saved')) {
        openTab(event, 'risk-control');
    } else if (urlParams.has('price_saved')) {
        openTab(event, 'price-feed');
    } else if (urlParams.has('fee_saved')) {
        openTab(event, 'fee-setup');
    }
};
</script>

<?php
$conn->close();
require_once 'footer.php';
?>