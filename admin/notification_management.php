<?php
require_once 'header.php';
require_once '../db_connect.php';

$send_notification_message = '';
$template_message = '';
$automated_message = '';

// Fetch users for recipient dropdown
$users_query = "SELECT id, username, email FROM users ORDER BY username";
$users_result = $conn->query($users_query);

// Handle Send Notification submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $recipient_type = $_POST['recipient_type'] ?? '';
    $user_id = $_POST['user_id'] ?? null;
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';

    if (empty($subject) || empty($message)) {
        $send_notification_message = "<div class=\"alert alert-danger\">Subject and Message are required.</div>";
    } else {
        // Simulate sending and log it
        $log_status = 'sent'; // Assume success for simulation
        $log_error_message = null;

        // Determine actual user_id for logging
        $log_user_id = null;
        if ($recipient_type === 'single' && !empty($user_id)) {
            $log_user_id = $user_id;
        } // For 'all' or 'group', user_id would be null in log or handled differently

        $insert_log_query = "INSERT INTO notification_logs (user_id, type, subject, message, status, error_message) VALUES (?, ?, ?, ?, ?, ?)";
        $insert_log_stmt = $conn->prepare($insert_log_query);
        $insert_log_stmt->bind_param('isssss', $log_user_id, $recipient_type, $subject, $message, $log_status, $log_error_message);
        
        if ($insert_log_stmt->execute()) {
            $send_notification_message = "<div class=\"alert alert-success\">Notification sent successfully (simulated) and logged!</div>";
        } else {
            $send_notification_message = "<div class=\"alert alert-danger\">Error logging notification: " . $insert_log_stmt->error . "</div>";
        }
        $insert_log_stmt->close();
    }
}

// Handle Add New Template submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_template'])) {
    $name = $_POST['name'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $body = $_POST['body'] ?? '';
    $type = $_POST['type'] ?? 'email';
    $variables = $_POST['variables'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name) || empty($subject) || empty($body)) {
        $template_message = "<div class=\"alert alert-danger\">Name, Subject, and Body are required for the template.</div>";
    } else {
        $check_query = "SELECT id FROM notification_templates WHERE name = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('s', $name);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $template_message = "<div class=\"alert alert-danger\">Template name already exists.</div>";
        } else {
            $insert_query = "INSERT INTO notification_templates (name, subject, body, type, variables, is_active) VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param('sssssi', $name, $subject, $body, $type, $variables, $is_active);
            if ($insert_stmt->execute()) {
                $template_message = "<div class=\"alert alert-success\">Template '" . htmlspecialchars($name) . "' added successfully!</div>";
            } else {
                $template_message = "<div class=\"alert alert-danger\">Error adding template: " . $insert_stmt->error . "</div>";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle Automated Notifications settings submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_automated_settings'])) {
    $deposit_notification_active = isset($_POST['deposit_notification_active']) ? 1 : 0;
    $withdrawal_notification_active = isset($_POST['withdrawal_notification_active']) ? 1 : 0;
    $trade_notification_active = isset($_POST['trade_notification_active']) ? 1 : 0;

    $settings_to_update = [
        'automated_deposit_notification_active' => $deposit_notification_active,
        'automated_withdrawal_notification_active' => $withdrawal_notification_active,
        'automated_trade_notification_active' => $trade_notification_active
    ];

    foreach ($settings_to_update as $key => $value) {
        $query = "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('si', $key, $value);
        if (!$stmt->execute()) {
            $automated_message = "<div class=\"alert alert-danger\">Error saving automated settings: " . $stmt->error . "</div>";
            break;
        }
        $stmt->close();
    }

    if (empty($automated_message)) {
        $automated_message = "<div class=\"alert alert-success\">Automated notification settings saved successfully!</div>";
    }
}

// Fetch Notification Templates
$templates_query = "SELECT * FROM notification_templates ORDER BY name";
$templates_result = $conn->query($templates_query);

// Fetch current automated notification settings to pre-fill form
$current_automated_settings = [];
$automated_settings_keys = ['automated_deposit_notification_active', 'automated_withdrawal_notification_active', 'automated_trade_notification_active'];
foreach ($automated_settings_keys as $key) {
    $query = "SELECT `value` FROM settings WHERE `key` = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_automated_settings[$key] = $row['value'];
    } else {
        $current_automated_settings[$key] = ''; // Default empty
    }
    $stmt->close();
}

// Fetch Notification History
$history_query = "
    SELECT nl.*, u.username, u.email
    FROM notification_logs nl
    LEFT JOIN users u ON nl.user_id = u.id
    ORDER BY nl.sent_at DESC
";
$history_result = $conn->query($history_query);

$conn->close();
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
    <h3>Notifications & Communication</h3>

    <div class="tabs">
        <div class="tab-link active" onclick="openTab(event, 'send-notification')">Send Notification</div>
        <div class="tab-link" onclick="openTab(event, 'templates')">Templates</div>
        <div class="tab-link" onclick="openTab(event, 'automated')">Automated Notifications</div>
        <div class="tab-link" onclick="openTab(event, 'history')">History</div>
    </div>

    <!-- Send Notification Tab -->
    <div id="send-notification" class="tab-content active">
        <h4>Send New Notification</h4>
        <?php echo $send_notification_message; // Display messages ?>
        <form action="notification_management.php" method="POST">
            <div class="form-group">
                <label for="recipient_type">Send To</label>
                <select id="recipient_type" name="recipient_type" class="form-control">
                    <option value="all">All Users</option>
                    <option value="single">Specific User</option>
                    <option value="group">Specific Group (e.g., VIPs)</option>
                </select>
            </div>
            <div class="form-group" id="user_select_group" style="display: none;">
                <label for="user_id">Select User</label>
                <select id="user_id" name="user_id" class="form-control">
                    <option value="">-- Select User --</option>
                    <?php while($user = $users_result->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
            </div>
            <button type="submit" name="send_notification" class="btn btn-primary">Send Notification</button>
        </form>
    </div>

    <!-- Templates Tab -->
    <div id="templates" class="tab-content">
        <h4>Notification Templates</h4>
        <?php echo $template_message; // Display template messages ?>
        <form action="notification_management.php" method="POST" style="margin-bottom: 2rem;">
            <div class="form-group">
                <label for="template_name">Template Name</label>
                <input type="text" id="template_name" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="template_subject">Subject</label>
                <input type="text" id="template_subject" name="subject" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="template_body">Body</label>
                <textarea id="template_body" name="body" class="form-control" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label for="template_type">Type</label>
                <select id="template_type" name="type" class="form-control">
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                    <option value="push">Push</option>
                </select>
            </div>
            <div class="form-group">
                <label for="template_variables">Variables (comma-separated, e.g., {name}, {amount})</label>
                <input type="text" id="template_variables" name="variables" class="form-control">
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_active" value="1" checked> Is Active</label>
            </div>
            <button type="submit" name="add_template" class="btn btn-primary">Add Template</button>
        </form>

        <h4 style="margin-top: 3rem;">Existing Templates</h4>
        <table class="table">
            <thead>
                <tr><th>Name</th><th>Subject</th><th>Type</th><th>Active</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($templates_result && $templates_result->num_rows > 0): ?>
                    <?php while($template = $templates_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($template['name']); ?></td>
                            <td><?php echo htmlspecialchars($template['subject']); ?></td>
                            <td><?php echo htmlspecialchars($template['type']); ?></td>
                            <td><?php echo $template['is_active'] ? 'Yes' : 'No'; ?></td>
                            <td>
                                <a href="edit_template.php?id=<?php echo $template['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="delete_template.php?id=<?php echo $template['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this template?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center;">No templates found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Automated Notifications Tab -->
    <div id="automated" class="tab-content">
        <h4>Automated Notifications Setup</h4>
        <?php echo $automated_message; // Display automated messages ?>
        <form action="notification_management.php" method="POST">
            <div class="form-group">
                <label><input type="checkbox" name="deposit_notification_active" value="1" <?php if($current_automated_settings['automated_deposit_notification_active'] == 1) echo 'checked'; ?>> Enable Deposit Notifications</label>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="withdrawal_notification_active" value="1" <?php if($current_automated_settings['automated_withdrawal_notification_active'] == 1) echo 'checked'; ?>> Enable Withdrawal Notifications</label>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="trade_notification_active" value="1" <?php if($current_automated_settings['automated_trade_notification_active'] == 1) echo 'checked'; ?>> Enable Trade Notifications</label>
            </div>
            <button type="submit" name="save_automated_settings" class="btn btn-primary">Save Automated Settings</button>
        </form>
    </div>

    <!-- History Tab -->
    <div id="history" class="tab-content">
        <h4>Notification History</h4>
        <table class="table">
            <thead>
                <tr><th>User</th><th>Type</th><th>Subject</th><th>Status</th><th>Sent At</th><th>Error</th></tr>
            </thead>
            <tbody>
                <?php if ($history_result && $history_result->num_rows > 0): ?>
                    <?php while($log = $history_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['username'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log['type']); ?></td>
                            <td><?php echo htmlspecialchars($log['subject']); ?></td>
                            <td><?php echo htmlspecialchars($log['status']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($log['sent_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['error_message'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">No notification history found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
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

document.getElementById('recipient_type').addEventListener('change', function() {
    var userSelectGroup = document.getElementById('user_select_group');
    if (this.value === 'single') {
        userSelectGroup.style.display = 'block';
    } else {
        userSelectGroup.style.display = 'none';
    }
});

// Activate the correct tab on page load if a message is present
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('tab')) {
        openTab(event, urlParams.get('tab'));
    } else if (urlParams.has('template_added') || urlParams.has('template_error') || urlParams.has('template_updated') || urlParams.has('template_deleted')) {
        openTab(event, 'templates');
    } else if (urlParams.has('automated_saved') || urlParams.has('automated_error')) {
        openTab(event, 'automated');
    } else if (urlParams.has('history_view')) {
        openTab(event, 'history');
    }
};
</script>

<?php
$conn->close();
require_once 'footer.php';
?>