<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $status = $_POST['status'] ?? 'pending';

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error_message = "Username, Email, and Password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        // Check if username or email already exists
        $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('ss', $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "Username or Email already exists.";
        } else {
            // Hash the password and insert new user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $uuid = uniqid(); // Simple UUID

            $insert_query = "INSERT INTO users (uuid, username, email, password_hash, status) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param('sssss', $uuid, $username, $email, $password_hash, $status);

            if ($insert_stmt->execute()) {
                $success_message = "New user created successfully!";
            } else {
                $error_message = "Error creating user: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

$conn->close();
?>

<div class="card">
    <h3>Add New User</h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success" style="background-color: var(--success); color: white; padding: 1rem; border-radius: 5px;"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" style="background-color: var(--danger); color: white; padding: 1rem; border-radius: 5px;"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="add_user.php" method="POST">
        <div class="grid-container" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="pending" selected>Pending</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Add User</button>
        <a href="user_management.php" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
