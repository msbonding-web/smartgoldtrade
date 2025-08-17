<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$rule_id = $_GET['id'] ?? 0;

if (!is_numeric($rule_id) || $rule_id <= 0) {
    die("Invalid IP Rule ID.");
}

// Handle form submission for updating the IP rule
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip_address = $_POST['ip_address'] ?? '';
    $type = $_POST['type'] ?? 'blacklist';
    $remarks = $_POST['remarks'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($ip_address) || !filter_var($ip_address, FILTER_VALIDATE_IP)) {
        $error_message = "Invalid IP Address.";
    } else {
        // Check if IP Address already exists (excluding current rule)
        $check_query = "SELECT id FROM ip_rules WHERE ip_address = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('si', $ip_address, $rule_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "IP Address already exists.";
        } else {
            $query = "UPDATE ip_rules SET ip_address = ?, type = ?, remarks = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('sssii', $ip_address, $type, $remarks, $is_active, $rule_id);

            if ($stmt->execute()) {
                $success_message = "IP Rule '" . htmlspecialchars($ip_address) . "' updated successfully! You will be redirected shortly.";
                echo "<meta http-equiv='refresh' content='3;url=system_settings.php?tab=ip-control'>";
            } else {
                $error_message = "Error updating IP rule: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Fetch current IP rule details to populate the form
$query = "SELECT * FROM ip_rules WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $rule_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $rule = $result->fetch_assoc();
} else {
    die("IP Rule not found.");
}
$stmt->close();
$conn->close();
?>

<div class="card">
    <h3>Edit IP Rule: <?php echo htmlspecialchars($rule['ip_address']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_ip_rule.php?id=<?php echo $rule_id; ?>" method="POST">
        <div class="form-group">
            <label for="ip_address">IP Address</label>
            <input type="text" id="ip_address" name="ip_address" class="form-control" value="<?php echo htmlspecialchars($rule['ip_address']); ?>" required>
        </div>
        <div class="form-group">
            <label for="ip_type">Rule Type</label>
            <select id="ip_type" name="type" class="form-control">
                <option value="whitelist" <?php if($rule['type'] === 'whitelist') echo 'selected'; ?>>Whitelist</option>
                <option value="blacklist" <?php if($rule['type'] === 'blacklist') echo 'selected'; ?>>Blacklist</option>
            </select>
        </div>
        <div class="form-group">
            <label for="remarks">Remarks (optional)</label>
            <textarea id="remarks" name="remarks" class="form-control" rows="3"><?php echo htmlspecialchars($rule['remarks']); ?></textarea>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_active" value="1" <?php if($rule['is_active']) echo 'checked'; ?>> Is Active</label>
        </div>
        <button type="submit" class="btn btn-primary">Update IP Rule</button>
        <a href="system_settings.php?tab=ip-control" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
