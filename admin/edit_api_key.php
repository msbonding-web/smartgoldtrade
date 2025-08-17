<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$api_key_id = $_GET['id'] ?? 0;

if (!is_numeric($api_key_id) || $api_key_id <= 0) {
    die("Invalid API Key ID.");
}

// Fetch users for API key assignment
$users_for_api_key = $conn->query("SELECT id, username FROM users ORDER BY username");

// Handle form submission for updating the API key
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = $_POST['description'] ?? '';
    $user_id = $_POST['user_id'] ?? null;
    $permissions = $_POST['permissions'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($description)) {
        $error_message = "Description is required.";
    } else {
        $query = "UPDATE api_keys SET description = ?, user_id = ?, permissions = ?, is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssiii', $description, $user_id, $permissions, $is_active, $api_key_id);

        if ($stmt->execute()) {
            $success_message = "API Key updated successfully! You will be redirected shortly.";
            echo "<meta http-equiv='refresh' content='3;url=system_settings.php?tab=api-access'>";
        } else {
            $error_message = "Error updating API Key: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch current API key details to populate the form
$query = "SELECT * FROM api_keys WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $api_key_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $api_key = $result->fetch_assoc();
} else {
    die("API Key not found.");
}
$stmt->close();
$conn->close();
?>

<div class="card">
    <h3>Edit API Key: <?php echo htmlspecialchars($api_key['key']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_api_key.php?id=<?php echo $api_key_id; ?>" method="POST">
        <div class="form-group">
            <label for="description">Description</label>
            <input type="text" id="description" name="description" class="form-control" value="<?php echo htmlspecialchars($api_key['description']); ?>" required>
        </div>
        <div class="form-group">
            <label for="user_id">Assign to User (optional)</label>
            <select id="user_id" name="user_id" class="form-control">
                <option value="">-- Select User --</option>
                <?php while($user = $users_for_api_key->fetch_assoc()): ?>
                    <option value="<?php echo $user['id']; ?>" <?php if($api_key['user_id'] == $user['id']) echo 'selected'; ?>><?php echo htmlspecialchars($user['username']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="permissions">Permissions (comma-separated, e.g., read_users, write_products)</label>
            <input type="text" id="permissions" name="permissions" class="form-control" value="<?php echo htmlspecialchars($api_key['permissions']); ?>">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_active" value="1" <?php if($api_key['is_active']) echo 'checked'; ?>> Is Active</label>
        </div>
        <button type="submit" class="btn btn-primary">Update API Key</button>
        <a href="system_settings.php?tab=api-access" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
