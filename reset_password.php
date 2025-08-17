<?php
session_start();
require_once 'db_connect.php';

$token = $_GET['token'] ?? '';
$message = '';
$show_form = false;
$user_id = null;

if (empty($token)) {
    $message = '<div class="alert alert-danger">Invalid or missing reset token.</div>';
} else {
    $stmt = $conn->prepare("SELECT user_id, expires_at FROM user_tokens WHERE token = ? AND type = 'password_reset'");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $token_data = $result->fetch_assoc();
    $stmt->close();

    if ($token_data && strtotime($token_data['expires_at']) > time()) {
        $show_form = true;
        $user_id = $token_data['user_id'];
    } else {
        $message = '<div class="alert alert-danger">Your password reset link is invalid or has expired.</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_id_from_post = $_POST['user_id'] ?? null;
    $token_from_post = $_POST['token'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $message = '<div class="alert alert-danger">Please fill both password fields.</div>';
        $show_form = true;
    } elseif ($new_password !== $confirm_password) {
        $message = '<div class="alert alert-danger">Passwords do not match.</div>';
        $show_form = true;
    } else {
        $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $update_stmt->bind_param('si', $password_hash, $user_id_from_post);
        
        if ($update_stmt->execute()) {
            $delete_stmt = $conn->prepare("DELETE FROM user_tokens WHERE token = ?");
            $delete_stmt->bind_param('s', $token_from_post);
            $delete_stmt->execute();
            $delete_stmt->close();

            $message = '<div class="alert alert-success">Your password has been reset successfully! You can now <a href="login.php" style="color: #155724; font-weight: bold;">login</a> with your new password.</div>';
            $show_form = false;
        } else {
            $message = '<div class="alert alert-danger">Failed to update password. Please try again.</div>';
            $show_form = true;
        }
        $update_stmt->close();
    }
}

$conn->close();
$page_title = "Reset Password - Smart Gold Trade";
require_once 'header.php';
?>

<!-- নতুন ডিজাইন করা কনটেন্ট শুরু -->
<style>
    .reset-password-container { 
        display: flex; 
        justify-content: center; 
        align-items: center; 
        padding: 50px 20px; 
        background-color: #f4f7f6; 
    }
    .card { 
        max-width: 500px; 
        width: 100%; 
        /* পরিবর্তন: কার্ডের প্যাডিং বাড়ানো হয়েছে */
        padding: 40px; 
        border: 1px solid #e0e0e0; 
        border-radius: 8px; 
        box-shadow: 0 4px 15px rgba(0,0,0,0.08); 
        background-color: #ffffff; 
    }
    .card h3 { 
        text-align: center; 
        /* পরিবর্তন: শিরোনামের নিচে মার্জিন বাড়ানো হয়েছে */
        margin-bottom: 30px; 
        color: #333; 
        font-size: 24px;
    }
    .form-group {
        margin-bottom: 20px; /* ফিল্ডগুলোর মধ্যে স্পেস */
    }
    .form-group label { 
        color: #333 !important; 
        display: block;
        margin-bottom: 8px; /* লেবেল এবং বক্সের মধ্যে স্পেস */
    }
    .form-control { 
        border-radius: 5px; 
        border: 1px solid #ccc;
        /* পরিবর্তন: ইনপুট বক্সের উচ্চতা বাড়ানো হয়েছে */
        padding: 14px; 
        width: 100%;
        box-sizing: border-box;
    }
    .btn-primary { 
        width: 100%; 
        background-color: #D4AF37; 
        border-color: #D4AF37; 
        font-weight: bold; 
        color: #fff; 
        /* পরিবর্তন: বাটনের উচ্চতা বাড়ানো হয়েছে */
        padding: 14px;
        margin-top: 10px; /* বাটনের উপরে স্পেস */
    }
    .btn-primary:hover { 
        background-color: #c5a22d; 
        border-color: #c5a22d; 
    }
    .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; border: 1px solid transparent; }
    .alert-danger { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    .alert-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
</style>

<div class="reset-password-container">
    <div class="card">
        <h3>Reset Your Password</h3>
        <?php echo $message; ?>

        <?php if ($show_form): ?>
        <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<!-- নতুন ডিজাইন করা কনটেন্ট শেষ -->

<?php require_once 'footer.php'; ?>
