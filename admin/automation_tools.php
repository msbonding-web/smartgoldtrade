<?php
require_once 'header.php';
require_once '../db_connect.php';

$automation_message = '';

// Handle Automation Settings submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_automation_settings'])) {
    $scheduled_payouts_active = isset($_POST['scheduled_payouts_active']) ? 1 : 0;
    $auto_close_p2p_disputes_active = isset($_POST['auto_close_p2p_disputes_active']) ? 1 : 0;
    $auto_close_p2p_disputes_hours = $_POST['auto_close_p2p_disputes_hours'] ?? 0;
    $auto_ban_failed_kyc_active = isset($_POST['auto_ban_failed_kyc_active']) ? 1 : 0;
    $auto_ban_failed_kyc_attempts = $_POST['auto_ban_failed_kyc_attempts'] ?? 0;
    $auto_low_stock_notification_active = isset($_POST['auto_low_stock_notification_active']) ? 1 : 0;
    $auto_low_stock_threshold = $_POST['auto_low_stock_threshold'] ?? 0;
    $auto_margin_call_email_active = isset($_POST['auto_margin_call_email_active']) ? 1 : 0;

    $settings_to_update = [
        'automation_scheduled_payouts_active' => $scheduled_payouts_active,
        'automation_auto_close_p2p_disputes_active' => $auto_close_p2p_disputes_active,
        'automation_auto_close_p2p_disputes_hours' => $auto_close_p2p_disputes_hours,
        'automation_auto_ban_failed_kyc_active' => $auto_ban_failed_kyc_active,
        'automation_auto_ban_failed_kyc_attempts' => $auto_ban_failed_kyc_attempts,
        'automation_auto_low_stock_notification_active' => $auto_low_stock_notification_active,
        'automation_auto_low_stock_threshold' => $auto_low_stock_threshold,
        'automation_auto_margin_call_email_active' => $auto_margin_call_email_active
    ];

    foreach ($settings_to_update as $key => $value) {
        $query = "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('si', $key, $value);
        if (!$stmt->execute()) {
            $automation_message = "<div class=\"alert alert-danger\">Error saving settings: " . $stmt->error . "</div>";
            break;
        }
        $stmt->close();
    }

    if (empty($automation_message)) {
        $automation_message = "<div class=\"alert alert-success\">Automation settings saved successfully!</div>";
    }
}

// Fetch current Automation settings to pre-fill form
$current_automation_settings = [];
$automation_settings_keys = [
    'automation_scheduled_payouts_active',
    'automation_auto_close_p2p_disputes_active',
    'automation_auto_close_p2p_disputes_active',
    'automation_auto_close_p2p_disputes_hours',
    'automation_auto_ban_failed_kyc_active',
    'automation_auto_ban_failed_kyc_attempts',
    'automation_auto_low_stock_notification_active',
    'automation_auto_low_stock_threshold',
    'automation_auto_margin_call_email_active'
];
foreach ($automation_settings_keys as $key) {
    $query = "SELECT `value` FROM settings WHERE `key` = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_automation_settings[$key] = $row['value'];
    } else {
        $current_automation_settings[$key] = ''; // Default empty
    }
    $stmt->close();
}

$conn->close();
?>

<style>
    .alert-success { background-color: var(--success); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
    .alert-danger { background-color: var(--danger); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
</style>

<div class="card">
    <h3>Automation Tools</h3>

    <?php echo $automation_message; // Display messages ?>

    <form action="automation_tools.php" method="POST">
        <h4>Investment Automation</h4>
        <div class="form-group">
            <label><input type="checkbox" name="scheduled_payouts_active" value="1" <?php if($current_automation_settings['automation_scheduled_payouts_active'] == 1) echo 'checked'; ?>> Enable Scheduled Payouts for Investments</label>
        </div>

        <h4 style="margin-top: 2rem;">P2P Trading Automation</h4>
        <div class="form-group">
            <label><input type="checkbox" name="auto_close_p2p_disputes_active" value="1" <?php if($current_automation_settings['automation_auto_close_p2p_disputes_active'] == 1) echo 'checked'; ?>> Auto-close P2P Disputes if no response</label>
        </div>
        <div class="form-group">
            <label for="auto_close_p2p_disputes_hours">Auto-close P2P Disputes after (hours)</label>
            <input type="number" id="auto_close_p2p_disputes_hours" name="auto_close_p2p_disputes_hours" class="form-control" value="<?php echo htmlspecialchars($current_automation_settings['automation_auto_close_p2p_disputes_hours'] ?? '72'); ?>">
        </div>

        <h4 style="margin-top: 2rem;">User Management Automation</h4>
        <div class="form-group">
            <label><input type="checkbox" name="auto_ban_failed_kyc_active" value="1" <?php if($current_automation_settings['automation_auto_ban_failed_kyc_active'] == 1) echo 'checked'; ?>> Auto-ban users after multiple failed KYC attempts</label>
        </div>
        <div class="form-group">
            <label for="auto_ban_failed_kyc_attempts">Auto-ban after (attempts)</label>
            <input type="number" id="auto_ban_failed_kyc_attempts" name="auto_ban_failed_kyc_attempts" class="form-control" value="<?php echo htmlspecialchars($current_automation_settings['automation_auto_ban_failed_kyc_attempts'] ?? '3'); ?>">
        </div>

        <h4 style="margin-top: 2rem;">Product Management Automation</h4>
        <div class="form-group">
            <label><input type="checkbox" name="auto_low_stock_notification_active" value="1" <?php if($current_automation_settings['automation_auto_low_stock_notification_active'] == 1) echo 'checked'; ?>> Auto-notification for Low Stock Products</label>
        </div>
        <div class="form-group">
            <label for="auto_low_stock_threshold">Low Stock Threshold</label>
            <input type="number" id="auto_low_stock_threshold" name="auto_low_stock_threshold" class="form-control" value="<?php echo htmlspecialchars($current_automation_settings['automation_auto_low_stock_threshold'] ?? '10'); ?>">
        </div>

        <h4 style="margin-top: 2rem;">Trading Automation</h4>
        <div class="form-group">
            <label><input type="checkbox" name="auto_margin_call_email_active" value="1" <?php if($current_automation_settings['automation_auto_margin_call_email_active'] == 1) echo 'checked'; ?>> Auto-email for Trade Margin Calls</label>
        </div>

        <button type="submit" name="save_automation_settings" class="btn btn-primary">Save Automation Settings</button>
    </form>

</div>

<?php
$conn->close();
require_once 'footer.php';
?>
