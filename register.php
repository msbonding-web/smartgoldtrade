<?php
session_start();
require_once 'db_connect.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'Please fill in all fields.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email format.';
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param('ss', $username, $email);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = 'Username or email already taken.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $uuid = uniqid('user_', true); // Generate a unique ID
            $status = 'active'; // Default status

            $insert_stmt = $conn->prepare("INSERT INTO users (uuid, username, email, password_hash, status) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->bind_param('sssss', $uuid, $username, $email, $password_hash, $status);

            if ($insert_stmt->execute()) {
                $success_message = "Registration successful! You can now log in.";
                header("refresh:3;url=login.php");
            } else {
                $error_message = 'An error occurred. Please try again.';
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}
$conn->close();
$page_title = "Register - Smart Gold Trade";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="card" style="max-width: 500px; margin: 4rem auto;">
            <h1 style="text-align: center; margin-bottom: 2rem;">Create Your Account</h1>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" style="background-color: #ffebee; color: #b71c1c; padding: 1rem; border-radius: 5px; border-left: 5px solid #f44336;">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" style="background-color: #e6ffed; color: #2d6a4f; padding: 1rem; border-radius: 5px; border-left: 5px solid #4caf50;">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
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
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-gold" style="width: 100%;">Register</button>
            </form>
            <p style="text-align: center; margin-top: 1.5rem; color: #ccc;">
                Already have an account? <a href="login.php" style="color: var(--gold);">Login here</a>
            </p>
        </div>
    </div>
</body>
</html>
