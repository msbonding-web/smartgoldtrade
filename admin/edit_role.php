<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$role_id = $_GET['id'] ?? 0;

if (!is_numeric($role_id) || $role_id <= 0) {
    die("Invalid Role ID.");
}

// Handle form submission for updating the role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $permissions = $_POST['permissions'] ?? [];

    if (empty($name)) {
        $error_message = "Role name cannot be empty.";
    } else {
        $conn->begin_transaction();
        try {
            // Update role name
            $update_role_query = "UPDATE roles SET name = ? WHERE id = ?";
            $update_role_stmt = $conn->prepare($update_role_query);
            $update_role_stmt->bind_param('si', $name, $role_id);
            if (!$update_role_stmt->execute()) {
                throw new Exception("Error updating role name: " . $update_role_stmt->error);
            }
            $update_role_stmt->close();

            // Update role permissions
            // First, delete all existing permissions for this role
            $delete_permissions_query = "DELETE FROM role_permissions WHERE role_id = ?";
            $delete_permissions_stmt = $conn->prepare($delete_permissions_query);
            $delete_permissions_stmt->bind_param('i', $role_id);
            if (!$delete_permissions_stmt->execute()) {
                throw new Exception("Error deleting old permissions: " . $delete_permissions_stmt->error);
            }
            $delete_permissions_stmt->close();

            // Then, insert new permissions
            if (!empty($permissions)) {
                $insert_permission_query = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
                $insert_permission_stmt = $conn->prepare($insert_permission_query);
                foreach ($permissions as $permission_id) {
                    $insert_permission_stmt->bind_param('ii', $role_id, $permission_id);
                    if (!$insert_permission_stmt->execute()) {
                        throw new Exception("Error inserting new permission: " . $insert_permission_stmt->error);
                    }
                }
                $insert_permission_stmt->close();
            }

            $conn->commit();
            $success_message = "Role '" . htmlspecialchars($name) . "' updated successfully! You will be redirected shortly.";
            echo "<meta http-equiv='refresh' content='3;url=system_settings.php?tab=roles-permissions'>";

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Fetch current role details to populate the form
$query = "SELECT id, name, slug FROM roles WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $role_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $role = $result->fetch_assoc();
} else {
    die("Role not found.");
}
$stmt->close();

// Fetch all available permissions
$all_permissions_query = "SELECT id, name FROM permissions ORDER BY name";
$all_permissions_result = $conn->query($all_permissions_query);

// Fetch permissions currently assigned to this role
$assigned_permissions = [];
$assigned_permissions_query = "SELECT permission_id FROM role_permissions WHERE role_id = ?";
$assigned_permissions_stmt = $conn->prepare($assigned_permissions_query);
$assigned_permissions_stmt->bind_param('i', $role_id);
$assigned_permissions_stmt->execute();
$assigned_permissions_result = $assigned_permissions_stmt->get_result();
while ($row = $assigned_permissions_result->fetch_assoc()) {
    $assigned_permissions[] = $row['permission_id'];
}
$assigned_permissions_stmt->close();

$conn->close();
?>

<div class="card">
    <h3>Edit Role: <?php echo htmlspecialchars($role['name']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_role.php?id=<?php echo $role_id; ?>" method="POST">
        <div class="form-group">
            <label for="name">Role Name</label>
            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($role['name']); ?>" required>
        </div>

        <h4 style="margin-top: 2rem;">Assign Permissions</h4>
        <div class="grid-container" style="grid-template-columns: repeat(3, 1fr); gap: 1rem;">
            <?php while($perm = $all_permissions_result->fetch_assoc()): ?>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="permissions[]" value="<?php echo $perm['id']; ?>" <?php if(in_array($perm['id'], $assigned_permissions)) echo 'checked'; ?>>
                        <?php echo htmlspecialchars($perm['name']); ?>
                    </label>
                </div>
            <?php endwhile; ?>
        </div>

        <button type="submit" class="btn btn-primary">Update Role</button>
        <a href="system_settings.php?tab=roles-permissions" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
