<?php
require_once 'header.php';
require_once '../db_connect.php';

$ip_rule_message = '';
$twofa_message = '';
$backup_restore_message = '';
$api_key_message = '';

// Handle Add New IP Rule submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ip_rule'])) {
    $ip_address = $_POST['ip_address'] ?? '';
    $type = $_POST['type'] ?? 'blacklist';
    $remarks = $_POST['remarks'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($ip_address) || !filter_var($ip_address, FILTER_VALIDATE_IP)) {
        $ip_rule_message = "<div class=\"alert alert-danger\">Invalid IP Address.</div>";
    } else {
        $check_query = "SELECT id FROM ip_rules WHERE ip_address = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('s', $ip_address);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $ip_rule_message = "<div class=\"alert alert-danger\">IP Address already exists.</div>";
        } else {
            $insert_query = "INSERT INTO ip_rules (ip_address, type, remarks, is_active) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param('sssi', $ip_address, $type, $remarks, $is_active);
            if ($insert_stmt->execute()) {
                $ip_rule_message = "<div class=\"alert alert-success\">IP Rule for '" . htmlspecialchars($ip_address) . "' added successfully!</div>";
            } else {
                $ip_rule_message = "<div class=\"alert alert-danger\">Error adding IP rule: " . $insert_stmt->error . "</div>";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle 2FA settings submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_2fa_settings'])) {
    $admin_2fa_enabled = isset($_POST['admin_2fa_enabled']) ? 1 : 0;
    $enforce_user_2fa = isset($_POST['enforce_user_2fa']) ? 1 : 0;

    $settings_to_update = [
        'admin_2fa_enabled' => $admin_2fa_enabled,
        'enforce_user_2fa' => $enforce_user_2fa
    ];

    foreach ($settings_to_update as $key => $value) {
        $query = "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('si', $key, $value);
        if (!$stmt->execute()) {
            $twofa_message = "<div class=\"alert alert-danger\">Error saving 2FA settings: " . $stmt->error . "</div>";
            break;
        }
        $stmt->close();
    }

    if (empty($twofa_message)) {
        $twofa_message = "<div class=\"alert alert-success\">2FA settings saved successfully!</div>";
    }
}

// Handle Backup/Restore submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['backup_db']) || isset($_POST['restore_db']))) {
    $db_host = DB_HOST;
    $db_user = DB_USERNAME;
    $db_pass = DB_PASSWORD;
    $db_name = DB_NAME;
    $backup_dir = '../backups/'; // IMPORTANT: Create this directory and ensure it's writable

    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }

    if (isset($_POST['backup_db'])) {
        $backup_file = $backup_dir . $db_name . '_' . date('Y-m-d_H-i-s') . '.sql';
        $command = sprintf('mysqldump -h%s -u%s -p%s %s > %s', $db_host, $db_user, $db_pass, $db_name, $backup_file);
        
        exec($command, $output, $return_var);

        if ($return_var === 0) {
            $backup_restore_message = "<div class=\"alert alert-success\">Database backup successful! File: " . basename($backup_file) . "</div>";
        } else {
            $backup_restore_message = "<div class=\"alert alert-danger\">Database backup failed. Error: " . implode(' ', $output) . "</div>";
        }
    } elseif (isset($_POST['restore_db'])) {
        $restore_file = $_POST['restore_file'] ?? '';
        $full_restore_path = $backup_dir . basename($restore_file); // Sanitize input

        if (file_exists($full_restore_path) && pathinfo($full_restore_path, PATHINFO_EXTENSION) === 'sql') {
            $command = sprintf('mysql -h%s -u%s -p%s %s < %s', $db_host, $db_user, $db_pass, $db_name, $full_restore_path);
            exec($command, $output, $return_var);

            if ($return_var === 0) {
                $backup_restore_message = "<div class=\"alert alert-success\">Database restore successful from file: " . basename($full_restore_path) . "</div>";
            } else {
                $backup_restore_message = "<div class=\"alert alert-danger\">Database restore failed. Error: " . implode(' ', $output) . "</div>";
            }
        } else {
            $backup_restore_message = "<div class=\"alert alert-danger\">Invalid or missing restore file.</div>";
        }
    }
}

// Handle Generate New API Key submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_api_key'])) {
    $description = $_POST['description'] ?? '';
    $user_id = $_POST['user_id'] ?? null;
    $permissions = $_POST['permissions'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Generate a random API key (e.g., 32 characters long)
    $api_key = bin2hex(random_bytes(16)); 

    $insert_query = "INSERT INTO api_keys (`key`, description, user_id, permissions, is_active) VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param('ssisi', $api_key, $description, $user_id, $permissions, $is_active);
    if ($insert_stmt->execute()) {
        $api_key_message = "<div class=\"alert alert-success\">New API Key generated: <strong>" . htmlspecialchars($api_key) . "</strong></div>";
    } else {
        $api_key_message = "<div class=\"alert alert-danger\">Error generating API Key: " . $insert_stmt->error . "</div>";
    }
    $insert_stmt->close();
}

// Fetch Roles and Permissions
$roles_query = "SELECT * FROM roles ORDER BY name";
$roles_result = $conn->query($roles_query);

$permissions_query = "SELECT * FROM permissions ORDER BY name";
$permissions_result = $conn->query($permissions_query);

// Fetch Activity Log
$activity_log_query = "SELECT al.*, u.username FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 100";
$activity_log_result = $conn->query($activity_log_query);

// Fetch IP Rules
$ip_rules_query = "SELECT * FROM ip_rules ORDER BY created_at DESC";
$ip_rules_result = $conn->query($ip_rules_query);

// Fetch current 2FA settings to pre-fill form
$current_2fa_settings = [];
$twofa_settings_keys = ['admin_2fa_enabled', 'enforce_user_2fa'];
foreach ($twofa_settings_keys as $key) {
    $query = "SELECT `value` FROM settings WHERE `key` = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_2fa_settings[$key] = $row['value'];
    } else {
        $current_2fa_settings[$key] = ''; // Default empty
    }
    $stmt->close();
}

// Fetch API Keys
$api_keys_query = "SELECT ak.*, u.username FROM api_keys ak LEFT JOIN users u ON ak.user_id = u.id ORDER BY ak.created_at DESC";
$api_keys_result = $conn->query($api_keys_query);

// Fetch users for API key assignment
$users_for_api_key = $conn->query("SELECT id, username FROM users ORDER BY username");

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
    <h3>System & Security Settings</h3>

    <div class="tabs">
        <div class="tab-link active" onclick="openTab(event, 'general')">General Settings</div>
        <div class="tab-link" onclick="openTab(event, 'roles-permissions')">Admin Roles & Permissions</div>
        <div class="tab-link" onclick="openTab(event, 'ip-control')">IP Control</div>
        <div class="tab-link" onclick="openTab(event, '2fa')">Two-Factor Authentication</div>
        <div class="tab-link" onclick="openTab(event, 'activity-log')">Activity Log</div>
        <div class="tab-link" onclick="openTab(event, 'backup-restore')">Backup & Restore</div>
        <div class="tab-link" onclick="openTab(event, 'api-access')">API Access Control</div>
    </div>

    <!-- General Settings Tab -->
    <div id="general" class="tab-content active">
        <h4>General System Settings</h4>
        <p><em>(Feature coming soon)</em></p>
    </div>

    <!-- Admin Roles & Permissions Tab -->
    <div id="roles-permissions" class="tab-content">
        <h4>Admin Roles</h4>
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Slug</th><th>Name</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($roles_result && $roles_result->num_rows > 0): ?>
                    <?php while($role = $roles_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($role['id']); ?></td>
                            <td><?php echo htmlspecialchars($role['slug']); ?></td>
                            <td><?php echo htmlspecialchars($role['name']); ?></td>
                            <td><a href="edit_role.php?id=<?php echo $role['id']; ?>" class="btn btn-primary btn-sm">Edit</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center;">No roles found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h4 style="margin-top: 2rem;">Permissions</h4>
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Slug</th><th>Name</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($permissions_result && $permissions_result->num_rows > 0): ?>
                    <?php while($permission = $permissions_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($permission['id']); ?></td>
                            <td><?php echo htmlspecialchars($permission['slug']); ?></td>
                            <td><?php echo htmlspecialchars($permission['name']); ?></td>
                            <td><a href="edit_permission.php?id=<?php echo $permission['id']; ?>" class="btn btn-primary btn-sm">Edit</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center;">No permissions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- IP Control Tab -->
    <div id="ip-control" class="tab-content">
        <h4>IP Whitelist &amp; Blacklist</h4>
        <?php echo $ip_rule_message; // Display IP rule messages ?>
        <form action="system_settings.php" method="POST" style="margin-bottom: 2rem;">
            <div class="form-group">
                <label for="ip_address">IP Address</label>
                <input type="text" id="ip_address" name="ip_address" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="ip_type">Rule Type</label>
                <select id="ip_type" name="type" class="form-control">
                    <option value="whitelist">Whitelist</option>
                    <option value="blacklist">Blacklist</option>
                </select>
            </div>
            <div class="form-group">
                <label for="ip_remarks">Remarks (optional)</label>
                <textarea id="ip_remarks" name="remarks" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_active" value="1" checked> Is Active</label>
            </div>
            <button type="submit" name="add_ip_rule" class="btn btn-primary">Add IP Rule</button>
        </form>

        <h4 style="margin-top: 3rem;">Existing IP Rules</h4>
        <table class="table">
            <thead>
                <tr><th>IP Address</th><th>Type</th><th>Remarks</th><th>Active</th><th>Created At</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($ip_rules_result && $ip_rules_result->num_rows > 0): ?>
                    <?php while($rule = $ip_rules_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($rule['ip_address']); ?></td>
                            <td><?php echo htmlspecialchars($rule['type']); ?></td>
                            <td><?php echo htmlspecialchars($rule['remarks']); ?></td>
                            <td><?php echo $rule['is_active'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($rule['created_at'])); ?></td>
                            <td>
                                <a href="edit_ip_rule.php?id=<?php echo $rule['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="delete_ip_rule.php?id=<?php echo $rule['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this IP rule?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">No IP rules found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 2FA Tab -->
    <div id="2fa" class="tab-content">
        <h4>Two-Factor Authentication Settings</h4>
        <?php echo $twofa_message; // Display 2FA messages ?>
        <form action="system_settings.php" method="POST">
            <div class="form-group">
                <label><input type="checkbox" name="admin_2fa_enabled" value="1" <?php if($current_2fa_settings['admin_2fa_enabled'] == 1) echo 'checked'; ?>> Enable 2FA for Admin Login</label>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="enforce_user_2fa" value="1" <?php if($current_2fa_settings['enforce_user_2fa'] == 1) echo 'checked'; ?>> Enforce 2FA for All Users</label>
            </div>
            <button type="submit" name="save_2fa_settings" class="btn btn-primary">Save 2FA Settings</button>
        </form>
    </div>

    <!-- Activity Log Tab -->
    <div id="activity-log" class="tab-content">
        <h4>Admin Activity Log</h4>
        <table class="table">
            <thead>
                <tr><th>User</th><th>Action</th><th>Ref Type</th><th>Ref ID</th><th>IP</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php if ($activity_log_result && $activity_log_result->num_rows > 0): ?>
                    <?php while($log = $activity_log_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['username'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['ref_type'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log['ref_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(inet_ntop($log['ip']) ?? 'N/A'); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">No activity logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Backup & Restore Tab -->
    <div id="backup-restore" class="tab-content">
        <h4>Database Backup &amp; Restore</h4>
        <?php echo $backup_restore_message; // Display messages ?>
        <form action="system_settings.php" method="POST" style="margin-bottom: 2rem;">
            <div class="form-group">
                <label for="backup_file_name">Backup File Name (optional)</label>
                <input type="text" id="backup_file_name" name="backup_file_name" class="form-control" placeholder="e.g., my_backup_2025-08-16">
            </div>
            <button type="submit" name="backup_db" class="btn btn-primary">Create Backup</button>
        </form>

        <h4 style="margin-top: 3rem;">Restore Database</h4>
        <form action="system_settings.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="restore_file">Upload SQL Backup File</label>
                <input type="file" id="restore_file" name="restore_file" class="form-control" accept=".sql">
            </div>
            <button type="submit" name="restore_db" class="btn btn-danger">Restore from File</button>
        </form>

        <h4 style="margin-top: 3rem;">Existing Backup Files</h4>
        <table class="table">
            <thead>
                <tr><th>File Name</th><th>Size</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php
                $backup_files = glob('../backups/*.sql');
                if (!empty($backup_files)) {
                    foreach ($backup_files as $file) {
                        $file_name = basename($file);
                        $file_size = round(filesize($file) / 1024 / 1024, 2); // MB
                        $file_date = date('Y-m-d H:i:s', filemtime($file));
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($file_name) . '</td>';
                        echo '<td>' . htmlspecialchars($file_size) . ' MB</td>';
                        echo '<td>' . htmlspecialchars($file_date) . '</td>';
                        echo '<td><a href="delete_backup.php?file=' . urlencode($file_name) . '" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete this backup file?\');">Delete</a></td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="4" style="text-align: center;">No backup files found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- API Access Control Tab -->
    <div id="api-access" class="tab-content">
        <h4>API Access Control</h4>
        <?php echo $api_key_message; // Display API key messages ?>
        <form action="system_settings.php" method="POST" style="margin-bottom: 2rem;">
            <div class="form-group">
                <label for="api_key_description">Description</label>
                <input type="text" id="api_key_description" name="description" class="form-control">
            </div>
            <div class="form-group">
                <label for="api_key_user_id">Assign to User (optional)</label>
                <select id="api_key_user_id" name="user_id" class="form-control">
                    <option value="">-- Select User --</option>
                    <?php while($user = $users_for_api_key->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="api_key_permissions">Permissions (comma-separated, e.g., read_users, write_products)</label>
                <input type="text" id="api_key_permissions" name="permissions" class="form-control">
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_active" value="1" checked> Is Active</label>
            </div>
            <button type="submit" name="generate_api_key" class="btn btn-primary">Generate New API Key</button>
        </form>

        <h4 style="margin-top: 3rem;">Existing API Keys</h4>
        <table class="table">
            <thead>
                <tr><th>Key</th><th>Description</th><th>User</th><th>Permissions</th><th>Active</th><th>Last Used</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($api_keys_result && $api_keys_result->num_rows > 0): ?>
                    <?php while($key = $api_keys_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($key['key']); ?></td>
                            <td><?php echo htmlspecialchars($key['description']); ?></td>
                            <td><?php echo htmlspecialchars($key['username'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($key['permissions']); ?></td>
                            <td><?php echo $key['is_active'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo $key['last_used_at'] ? date('Y-m-d H:i', strtotime($key['last_used_at'])) : 'N/A'; ?></td>
                            <td>
                                <a href="edit_api_key.php?id=<?php echo $key['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="delete_api_key.php?id=<?php echo $key['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this API key?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center;">No API keys found.</td></tr>
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

// Activate the correct tab on page load if a message is present
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('tab')) {
        openTab(event, urlParams.get('tab'));
    } else if (urlParams.has('ip_added') || urlParams.has('ip_error')) {
        openTab(event, 'ip-control');
    } else if (urlParams.has('2fa_saved') || urlParams.has('2fa_error')) {
        openTab(event, '2fa');
    } else if (urlParams.has('backup_success') || urlParams.has('backup_error') || urlParams.has('restore_success') || urlParams.has('restore_error')) {
        openTab(event, 'backup-restore');
    } else if (urlParams.has('api_key_added') || urlParams.has('api_key_error')) {
        openTab(event, 'api-access');
    }
};
</script>

<?php
$conn->close();
require_once 'footer.php';
?>
