<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$permission_id = $_GET['id'] ?? 0;

if (!is_numeric($permission_id) || $permission_id <= 0) {
    die("Invalid Permission ID.");
}

// Handle form submission for updating the permission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $slug = $_POST['slug'] ?? '';

    if (empty($name) || empty($slug)) {
        $error_message = "Name and Slug are required.";
    } else {
        // Check if slug already exists (excluding current permission)
        $check_query = "SELECT id FROM permissions WHERE slug = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('si', $slug, $permission_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "A permission with this slug already exists.";
        } else {
            $query = "UPDATE permissions SET name = ?, slug = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssi', $name, $slug, $permission_id);

            if ($stmt->execute()) {
                $success_message = "Permission '" . htmlspecialchars($name) . "' updated successfully! You will be redirected shortly.";
                echo "<meta http-equiv='refresh' content='3;url=system_settings.php?tab=roles-permissions'>";
            } else {
                $error_message = "Error updating permission: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Fetch current permission details to populate the form
$query = "SELECT id, name, slug FROM permissions WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $permission_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $permission = $result->fetch_assoc();
} else {
    die("Permission not found.");
}
$stmt->close();
$conn->close();
?>

<div class="card">
    <h3>Edit Permission: <?php echo htmlspecialchars($permission['name']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_permission.php?id=<?php echo $permission_id; ?>" method="POST">
        <div class="form-group">
            <label for="name">Permission Name</label>
            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($permission['name']); ?>" required>
        </div>
        <div class="form-group">
            <label for="slug">Permission Slug</label>
            <input type="text" id="slug" name="slug" class="form-control" value="<?php echo htmlspecialchars($permission['slug']); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Permission</button>
        <a href="system_settings.php?tab=roles-permissions" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
