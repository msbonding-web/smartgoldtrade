<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: user/dashboard.php");
    exit();
}

require_once 'db_connect.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (আপনার আগের PHP কোড এখানে অপরিবর্তিত থাকবে) ...
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password_hash FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                $user_roles = [];
                $roles_stmt = $conn->prepare("SELECT r.slug FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?");
                $roles_stmt->bind_param("i", $user['id']);
                $roles_stmt->execute();
                $roles_result = $roles_stmt->get_result();
                while ($role_row = $roles_result->fetch_assoc()) {
                    $user_roles[] = $role_row['slug'];
                }
                $_SESSION['user_roles'] = $user_roles;
                $roles_stmt->close();

                $is_admin = in_array('admin', $user_roles) || in_array('super_admin', $user_roles);

                if ($is_admin) {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: user/dashboard.php");
                }
                exit();
            } else {
                $error_message = "Invalid email or password.";
            }
        } else {
            $error_message = "Invalid email or password.";
        }
        $stmt->close();
    }
}


$conn->close();
$page_title = "Login - Smart Gold Trade";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        /* ... (আগের সব CSS কোড এখানে অপরিবর্তিত থাকবে) ... */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #1a1a1a;
            color: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container { width: 100%; max-width: 400px; padding: 20px; }
        .login-card { background-color: #2c2c2c; padding: 40px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); border: 1px solid #444; }
        .login-card h1 { text-align: center; margin-bottom: 25px; color: #D4AF37; font-weight: 600; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #ccc; }
        .form-control { width: 100%; padding: 12px; background-color: #1a1a1a; border: 1px solid #555; border-radius: 5px; color: #fff; font-size: 16px; box-sizing: border-box; }
        .form-control:focus { outline: none; border-color: #D4AF37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2); }
        .btn-submit { width: 100%; padding: 12px; background-color: #D4AF37; border: none; border-radius: 5px; color: #1a1a1a; font-size: 16px; font-weight: 600; cursor: pointer; transition: background-color 0.3s; }
        .btn-submit:hover { background-color: #c5a22d; }
        .login-links { text-align: center; margin-top: 20px; }
        .login-links a { color: #D4AF37; text-decoration: none; font-size: 14px; }
        .login-links a:hover { text-decoration: underline; }
        .alert-danger { background-color: #5c2a2a; color: #ffc4c4; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; border: 1px solid #8e3f3f; }
        
        /* --- নতুন CSS কোড শুরু --- */
        .separator {
            display: flex;
            align-items: center;
            text-align: center;
            color: #888;
            margin: 25px 0;
        }
        .separator::before, .separator::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #444;
        }
        .separator:not(:empty)::before {
            margin-right: .25em;
        }
        .separator:not(:empty)::after {
            margin-left: .25em;
        }

        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 12px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #333;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        .btn-google:hover {
            background-color: #f5f5f5;
        }
        .btn-google svg {
            margin-right: 10px;
            height: 20px;
            width: 20px;
        }
        /* --- নতুন CSS কোড শেষ --- */
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h1>Client Login</h1>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn-submit">Login</button>
            </form>
            
            <div class="separator">OR</div>
            <a href="google_auth.php" class="btn-google">
                <svg viewBox="0 0 48 48"><path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"></path><path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"></path><path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"></path><path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.574l6.19,5.238C39.999,35.596,44,30.165,44,24C44,22.659,43.862,21.35,43.611,20.083z"></path></svg>
                Sign in with Google
            </a>

            <div class="login-links">
                <p><a href="forgot_password.php">Forgot Password?</a></p>
                <p>Don't have an account? <a href="register.php">Sign Up</a></p>
            </div>
        </div>
    </div>
</body>
</html>