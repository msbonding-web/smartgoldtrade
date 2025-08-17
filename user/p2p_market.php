<?php
session_start();
require_once '../header.php';
require_once '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

$message = '';

// Fetch currencies for asset selection
$currencies = [];
$currency_query = "SELECT id, code, name FROM currencies WHERE is_active = 1 AND code != 'USD' ORDER BY name ASC";
$currency_result = $conn->query($currency_query);
while ($row = $currency_result->fetch_assoc()) {
    $currencies[] = $row;
}

// Fetch payment methods for selection
$payment_methods = [];
$pm_query = "SELECT id, slug, name FROM payment_methods WHERE is_active = 1 ORDER BY name ASC";
$pm_result = $conn->query($pm_query);
while ($row = $pm_result->fetch_assoc()) {
    $payment_methods[] = $row;
}

// Handle new offer creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_offer'])) {
    $side = $_POST['side'] ?? '';
    $asset_currency_id = $_POST['asset_currency_id'] ?? '';
    $price = $_POST['price'] ?? '';
    $min_amount = $_POST['min_amount'] ?? '';
    $max_amount = $_POST['max_amount'] ?? '';
    $selected_payment_methods = $_POST['payment_methods'] ?? [];
    $terms = $_POST['terms'] ?? '';

    if (empty($side) || empty($asset_currency_id) || empty($price) || empty($min_amount) || empty($max_amount) || empty($selected_payment_methods)) {
        $message = '<div class="alert alert-danger">Please fill all required fields.</div>';
    } elseif (!is_numeric($price) || $price <= 0 || !is_numeric($min_amount) || $min_amount <= 0 || !is_numeric($max_amount) || $max_amount <= 0 || $min_amount > $max_amount) {
        $message = '<div class="alert alert-danger">Please enter valid numeric amounts and price. Min amount cannot be greater than max amount.</div>';
    } else {
        $payment_methods_json = json_encode($selected_payment_methods);
        $status = 'active';

        $insert_offer_query = "INSERT INTO p2p_offers (user_id, side, asset_currency_id, price, min_amount, max_amount, payment_methods, terms, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_offer_query);
        if ($stmt) {
            $stmt->bind_param('isddddsss', $user_id, $side, $asset_currency_id, $price, $min_amount, $max_amount, $payment_methods_json, $terms, $status);
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Offer created successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Failed to create offer: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        } else {
            $message = '<div class="alert alert-danger">Database error: ' . $conn->error . '</div>';
        }
    }
}

// Fetch active P2P offers
$offers = [];
$offers_query = "
    SELECT po.id, po.user_id, po.side, po.price, po.min_amount, po.max_amount, po.payment_methods,
           u.username, c.code AS asset_currency_code
    FROM p2p_offers po
    JOIN users u ON po.user_id = u.id
    JOIN currencies c ON po.asset_currency_id = c.id
    WHERE po.status = 'active'
    ORDER BY po.created_at DESC
";
$offers_result = $conn->query($offers_query);
if ($offers_result) {
    while ($row = $offers_result->fetch_assoc()) {
        $row['payment_methods'] = json_decode($row['payment_methods'], true);
        $offers[] = $row;
    }
}

$conn->close();
?>

<!-- Unique CSS for this page only -->
<style>
    .p2p-market-container {
        padding: 20px;
        background-color: #f4f7f6;
    }
    .p2p-tabs {
        display: flex;
        border-bottom: 2px solid #dee2e6;
        margin-bottom: 20px;
    }
    .p2p-tab-item {
        padding: 15px 25px;
        cursor: pointer;
        font-size: 18px;
        font-weight: 600;
        color: #6c757d;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
    }
    .p2p-tab-item.active {
        color: #D4AF37;
        border-bottom-color: #D4AF37;
    }
    .p2p-tab-content {
        display: none;
    }
    .p2p-tab-content.active {
        display: block;
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
        margin-bottom: 25px;
        font-weight: 600;
        color: #333;
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
    }
    .form-group label, .form-check-label {
        color: #333 !important;
        margin-bottom: 8px;
        display: block;
    }
    .btn-gold {
        background-color: #D4AF37 !important;
        border-color: #D4AF37 !important;
        color: #fff !important;
        width: 100%;
        padding: 12px;
        font-weight: bold;
        margin-top: 15px;
    }
    .table thead th {
        background-color: #f8f9fa;
    }
    .badge-success { background-color: #28a745; color: white; padding: 5px 10px; border-radius: 12px; }
    .badge-danger { background-color: #dc3545; color: white; padding: 5px 10px; border-radius: 12px; }
</style>

<div class="p2p-market-container">
    <h3>P2P Trading Market</h3>
    <?php if ($message) echo $message; ?>

    <!-- Tab Navigation -->
    <div class="p2p-tabs">
        <div class="p2p-tab-item active" data-tab="all-offers">All Offers</div>
        <div class="p2p-tab-item" data-tab="create-offer">Create New Offer</div>
    </div>

    <!-- All Offers Tab -->
    <div id="all-offers" class="p2p-tab-content active">
        <div class="p2p-card">
            <h4>Active P2P Offers</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Type</th>
                            <th>Asset</th>
                            <th>Price / Asset</th>
                            <th>Available Limit</th>
                            <th>Payment Methods</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($offers)): ?>
                            <?php foreach ($offers as $offer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($offer['username']); ?></td>
                                    <td><span class="badge <?php echo ($offer['side'] == 'buy' ? 'badge-success' : 'badge-danger'); ?>"><?php echo ucfirst($offer['side']); ?></span></td>
                                    <td><?php echo htmlspecialchars($offer['asset_currency_code']); ?></td>
                                    <td>$<?php echo rtrim(rtrim(number_format($offer['price'], 2), '0'), '.'); ?></td>
                                    <td><?php echo rtrim(rtrim(number_format($offer['min_amount'], 2), '0'), '.'); ?> - <?php echo rtrim(rtrim(number_format($offer['max_amount'], 2), '0'), '.'); ?> <?php echo htmlspecialchars($offer['asset_currency_code']); ?></td>
                                    <td><?php echo implode(', ', array_map('ucwords', array_map('htmlspecialchars', $offer['payment_methods']))); ?></td>
                                    <td>
                                        <?php if ($offer['user_id'] != $user_id): ?>
                                            <a href="p2p_view_offer.php?offer_id=<?php echo $offer['id']; ?>" class="btn btn-sm btn-primary">Trade</a>
                                        <?php else: ?>
                                            <span class="text-muted">Your Offer</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No active P2P offers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Offer Tab -->
    <div id="create-offer" class="p2p-tab-content">
        <div class="p2p-card">
            <h4>Create a New P2P Offer</h4>
            <form action="p2p_market.php" method="POST">
                <div class="form-group">
                    <label for="side">I want to</label>
                    <select id="side" name="side" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="buy">Buy</option>
                        <option value="sell">Sell</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="asset_currency_id">Asset</label>
                    <select id="asset_currency_id" name="asset_currency_id" class="form-control" required>
                        <option value="">Select Asset</option>
                        <?php foreach ($currencies as $currency): ?>
                            <option value="<?php echo $currency['id']; ?>"><?php echo htmlspecialchars($currency['name'] . ' (' . $currency['code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="price">Price (in USD per Asset)</label>
                    <input type="number" step="0.01" id="price" name="price" class="form-control" placeholder="e.g., 75.50" required>
                </div>
                <div class="form-group">
                    <label for="min_amount">Minimum Amount (in Asset)</label>
                    <input type="number" step="0.0001" id="min_amount" name="min_amount" class="form-control" placeholder="e.g., 0.5" required>
                </div>
                <div class="form-group">
                    <label for="max_amount">Maximum Amount (in Asset)</label>
                    <input type="number" step="0.0001" id="max_amount" name="max_amount" class="form-control" placeholder="e.g., 10" required>
                </div>
                <div class="form-group">
                    <label>Supported Payment Methods</label>
                    <?php foreach ($payment_methods as $pm): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="payment_methods[]" value="<?php echo htmlspecialchars($pm['slug']); ?>" id="pm_<?php echo $pm['id']; ?>">
                            <label class="form-check-label" for="pm_<?php echo $pm['id']; ?>">
                                <?php echo htmlspecialchars($pm['name']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-group">
                    <label for="terms">Terms (Optional)</label>
                    <textarea id="terms" name="terms" class="form-control" rows="3" placeholder="e.g., Please make payment within 15 minutes."></textarea>
                </div>
                <button type="submit" name="create_offer" class="btn btn-gold">Create Offer</button>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for Tabs -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.p2p-tab-item');
    const tabContents = document.querySelectorAll('.p2p-tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(item => item.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            const target = tab.getAttribute('data-tab');
            tab.classList.add('active');
            document.getElementById(target).classList.add('active');
        });
    });
});
</script>

<?php require_once '../footer.php'; ?>
