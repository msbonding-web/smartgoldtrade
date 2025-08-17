<?php
require_once 'header.php';
require_once '../db_connect.php';

$commission_message = '';
$block_message = '';

// Handle Adjust Commission % submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_commission_settings'])) {
    $global_referral_commission_percent = $_POST['global_referral_commission_percent'] ?? 0;

    $settings_to_update = [
        'global_referral_commission_percent' => $global_referral_commission_percent
    ];

    foreach ($settings_to_update as $key => $value) {
        $query = "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sd', $key, $value);
        if (!$stmt->execute()) {
            $commission_message = "<div class=\"alert alert-danger\">Error saving commission: " . $stmt->error . "</div>";
            break;
        }
        $stmt->close();
    }

    if (empty($commission_message)) {
        $commission_message = "<div class=\"alert alert-success\">Commission settings saved successfully!</div>";
    }
}

// Handle Block Referral Earnings submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_referral_earnings'])) {
    $user_id_to_block = $_POST['user_id_to_block'] ?? 0;
    $block_status = $_POST['block_status'] ?? 0; // 1 for block, 0 for unblock

    if (!is_numeric($user_id_to_block) || $user_id_to_block <= 0) {
        $block_message = "<div class=\"alert alert-danger\">Invalid User ID.</div>";
    } else {
        // Check if user exists
        $check_user_query = "SELECT id FROM users WHERE id = ?";
        $check_user_stmt = $conn->prepare($check_user_query);
        $check_user_stmt->bind_param('i', $user_id_to_block);
        $check_user_stmt->execute();
        if ($check_user_stmt->get_result()->num_rows === 0) {
            $block_message = "<div class=\"alert alert-danger\">User not found.</div>";
        } else {
            // Store block status in settings table (or a dedicated table if more complex)
            $setting_key = 'referral_block_user_' . $user_id_to_block;
            $query = "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('si', $setting_key, $block_status);
            if ($stmt->execute()) {
                $action = ($block_status == 1) ? 'blocked' : 'unblocked';
                $block_message = "<div class=\"alert alert-success\">User ID " . $user_id_to_block . " referral earnings " . $action . " successfully!</div>";
            } else {
                $block_message = "<div class=\"alert alert-danger\">Error updating block status: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
        $check_user_stmt->close();
    }
}

// Fetch current commission settings to pre-fill form
$current_commission_settings = [];
$commission_keys = ['global_referral_commission_percent'];
foreach ($commission_keys as $key) {
    $query = "SELECT `value` FROM settings WHERE `key` = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_commission_settings[$key] = $row['value'];
    } else {
        $current_commission_settings[$key] = ''; // Default empty
    }
    $stmt->close();
}

// Fetch Referral Earnings
$earnings_query = "
    SELECT re.*, u.username as earning_user, fu.username as from_user
    FROM referral_earnings re
    JOIN users u ON re.user_id = u.id
    JOIN users fu ON re.from_user_id = fu.id
    ORDER BY re.created_at DESC
";
$earnings_result = $conn->query($earnings_query);

?>

<style>
    .alert-success { background-color: var(--success); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
    .alert-danger { background-color: var(--danger); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
</style>

<div class="card">
    <h3>Referral & Commission Management</h3>

    <div class="grid-container" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <!-- Referral Earnings List -->
        <div>
            <h4>Recent Referral Earnings</h4>
            <table class="table">
                <thead>
                    <tr><th>User</th><th>From User</th><th>Amount</th><th>Level</th><th>Type</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php if ($earnings_result && $earnings_result->num_rows > 0): ?>
                        <?php while($earning = $earnings_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($earning['earning_user']); ?></td>
                                <td><?php echo htmlspecialchars($earning['from_user']); ?></td>
                                <td>$<?php echo number_format($earning['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($earning['level']); ?></td>
                                <td><?php echo htmlspecialchars($earning['type']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($earning['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center;">No referral earnings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Commission & Block Controls -->
        <div>
            <h4>Adjust Global Commission %</h4>
            <?php echo $commission_message; // Display messages ?>
            <form action="referral_management.php" method="POST">
                <div class="form-group">
                    <label>Global Referral Commission Percentage (%)</label>
                    <input type="number" step="0.01" class="form-control" name="global_referral_commission_percent" value="<?php echo htmlspecialchars($current_commission_settings['global_referral_commission_percent'] ?? '5.00'); ?>">
                </div>
                <button type="submit" name="save_commission_settings" class="btn btn-primary">Save Commission</button>
            </form>

            <h4 style="margin-top: 2rem;">Block/Unblock Referral Earnings for User</h4>
            <?php echo $block_message; // Display messages ?>
            <form action="referral_management.php" method="POST">
                <div class="form-group">
                    <label>User ID</label>
                    <input type="number" class="form-control" name="user_id_to_block" required>
                </div>
                <div class="form-group">
                    <label>Action</label>
                    <select name="block_status" class="form-control">
                        <option value="1">Block Earnings</option>
                        <option value="0">Unblock Earnings</option>
                    </select>
                </div>
                <button type="submit" name="block_referral_earnings" class="btn btn-danger">Submit</button>
            </form>

            <h4 style="margin-top: 2rem;">Other Referral Settings</h4>
            <p>Multi-Level Referral Tree View: <em>(Feature coming soon)</em></p>
            <p>Auto Payout / Manual Approval for Referral Bonus: <em>(Feature coming soon)</em></p>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'footer.php';
?>
