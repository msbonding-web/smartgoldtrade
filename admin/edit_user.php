<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$user_id = $_GET['id'] ?? 0;

if (!is_numeric($user_id) || $user_id <= 0) {
    die("Invalid User ID.");
}

// Fetch all available roles
$all_roles = [];
$query_all_roles = "SELECT id, name FROM roles ORDER BY name ASC";
$stmt_all_roles = $conn->prepare($query_all_roles);
$stmt_all_roles->execute();
$result_all_roles = $stmt_all_roles->get_result();
while ($row = $result_all_roles->fetch_assoc()) {
    $all_roles[] = $row;
}
$stmt_all_roles->close();

// Fetch current roles for the user
$current_user_roles = [];
$query_current_roles = "SELECT r.id, r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?";
$stmt_current_roles = $conn->prepare($query_current_roles);
$stmt_current_roles->bind_param('i', $user_id);
$stmt_current_roles->execute();
$result_current_roles = $stmt_current_roles->get_result();
while ($row = $result_current_roles->fetch_assoc()) {
    $current_user_roles[$row['id']] = $row['name'];
}
$stmt_current_roles->close();

// Handle form submission for updating the user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $status = $_POST['status'] ?? '';
    $password = $_POST['password'] ?? '';
    $selected_roles = $_POST['roles'] ?? []; // Array of selected role IDs

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update users table
        if (empty($password)) {
            $query_user = "UPDATE users SET email = ?, phone = ?, status = ? WHERE id = ?";
            $stmt_user = $conn->prepare($query_user);
            $stmt_user->bind_param('sssi', $email, $phone, $status, $user_id);
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $query_user = "UPDATE users SET email = ?, phone = ?, status = ?, password_hash = ? WHERE id = ?";
            $stmt_user = $conn->prepare($query_user);
            $stmt_user->bind_param('ssssi', $email, $phone, $status, $password_hash, $user_id);
        }
        $stmt_user->execute();
        $stmt_user->close();

        // Update user_profiles table
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $address_line1 = $_POST['address_line1'] ?? '';
        $city = $_POST['city'] ?? '';
        $country_code = $_POST['country_code'] ?? '';

        $query_profile = "INSERT INTO user_profiles (user_id, first_name, last_name, address_line1, city, country_code) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), address_line1 = VALUES(address_line1), city = VALUES(city), country_code = VALUES(country_code)";
        $stmt_profile = $conn->prepare($query_profile);
        $stmt_profile->bind_param('isssss', $user_id, $first_name, $last_name, $address_line1, $city, $country_code);
        $stmt_profile->execute();
        $stmt_profile->close();

        // Update user_roles table
        // 1. Delete existing roles for the user
        $delete_roles_query = "DELETE FROM user_roles WHERE user_id = ?";
        $stmt_delete_roles = $conn->prepare($delete_roles_query);
        $stmt_delete_roles->bind_param('i', $user_id);
        $stmt_delete_roles->execute();
        $stmt_delete_roles->close();

        // 2. Insert new roles
        if (!empty($selected_roles)) {
            $insert_roles_query = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
            $stmt_insert_roles = $conn->prepare($insert_roles_query);
            foreach ($selected_roles as $role_id) {
                $stmt_insert_roles->bind_param('ii', $user_id, $role_id);
                $stmt_insert_roles->execute();
            }
            $stmt_insert_roles->close();
        }

        $conn->commit();
        $success_message = "User updated successfully!";

        // Re-fetch current user roles after update to display correctly
        $current_user_roles = [];
        $stmt_current_roles = $conn->prepare($query_current_roles); // Re-use the query
        $stmt_current_roles->bind_param('i', $user_id);
        $stmt_current_roles->execute();
        $result_current_roles = $stmt_current_roles->get_result();
        while ($row = $result_current_roles->fetch_assoc()) {
            $current_user_roles[$row['id']] = $row['name'];
        }
        $stmt_current_roles->close();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Failed to update user: " . $e->getMessage();
    }
}

// Fetch current user details (re-fetch in case of POST update)
$query = "SELECT u.id, u.username, u.email, u.phone, u.status, up.first_name, up.last_name, up.address_line1, up.city, up.country_code FROM users u LEFT JOIN user_profiles up ON u.id = up.user_id WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
} else {
    die("User not found.");
}
$stmt->close();
$conn->close(); // Close connection after all operations
?>

<div class="card">
    <h3>Edit User: <?php echo htmlspecialchars($user['username']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success" style="background-color: var(--success); color: white; padding: 1rem; border-radius: 5px;"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" style="background-color: var(--danger); color: white; padding: 1rem; border-radius: 5px;"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_user.php?id=<?php echo $user_id; ?>" method="POST">
        <h4>Account Information</h4>
        <div class="grid-container" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="active" <?php if($user['status'] === 'active') echo 'selected'; ?>>Active</option>
                    <option value="suspended" <?php if($user['status'] === 'suspended') echo 'selected'; ?>>Suspended</option>
                    <option value="pending" <?php if($user['status'] === 'pending') echo 'selected'; ?>>Pending</option>
                </select>
            </div>
            <div class="form-group">
                <label for="password">New Password (leave blank to keep current)</label>
                <input type="password" id="password" name="password" class="form-control">
            </div>
        </div>

        <hr style="margin: 2rem 0;">

        <h4>User Roles</h4>
        <div class="form-group">
            <label for="roles">Assign Roles</label>
            <select id="roles" name="roles[]" class="form-control" multiple size="3">
                <?php foreach ($all_roles as $role): ?>
                    <option value="<?php echo $role['id']; ?>" <?php if (isset($current_user_roles[$role['id']])) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($role['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">Hold Ctrl (Windows) or Command (Mac) to select multiple roles.</small>
        </div>

        <hr style="margin: 2rem 0;">

        <h4>User Profile</h4>
        <div class="grid-container" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="address_line1">Address</label>
                <input type="text" id="address_line1" name="address_line1" class="form-control" value="<?php echo htmlspecialchars($user['address_line1'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="city">City</label>
                <input type="text" id="city" name="city" class="form-control" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="country_code">Country Code</label>
                <input type="text" id="country_code" name="country_code" class="form-control" value="<?php echo htmlspecialchars($user['country_code'] ?? ''); ?>">
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Update User</button>
        <a href="user_management.php" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>