<?php
// Note: This is a placeholder for an admin login.
// For a real application, you would have a separate admin authentication system.

// For now, we will just redirect to the dashboard.
// In a real scenario, you would check for a POST request with admin credentials.

header("Location: dashboard.php");
exit();

/*
// --- Example of a full login form (to be implemented later) ---

session_start();
require_once '../db_connect.php'; // Assuming db_connect is in the parent directory

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // ... (Database query to fetch admin user and verify password) ...

    // If successful:
    // $_SESSION['admin_id'] = $admin['id'];
    // header("Location: dashboard.php");
    // exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="display: flex; justify-content: center; align-items: center; height: 100vh;">
    <div class="card" style="width: 400px;">
        <h2 style="text-align: center;">Admin Login</h2>
        <form action="index.php" method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
        </form>
    </div>
</body>
</html>
*/
