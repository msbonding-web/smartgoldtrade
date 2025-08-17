<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once 'vendor/autoload.php';
require_once 'db_connect.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_email'])) {
    $email = $_POST['email'] ?? '';

    if (empty($email)) {
        $message = '<div class="alert alert-danger">Please enter your email address.</div>';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            $user_id = $user['id'];
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, type, expires_at) VALUES (?, ?, 'password_reset', ?)");
            $stmt->bind_param('iss', $user_id, $token, $expires_at);
            
            if ($stmt->execute()) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smartgoldtrade.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'noreply@smartgoldtrade.com';
                    $mail->Password   = 'Zarra-852882';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = 465;

                    $mail->setFrom('noreply@smartgoldtrade.com', 'Smart Gold Trade');
                    $mail->addAddress($email);

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request';
                    $reset_link = "https://smartgoldtrade.com/reset_password.php?token=" . $token;
                    $mail->Body    = "Hello,<br><br>You requested a password reset. Please click the link below to reset your password:<br><br><a href='" . htmlspecialchars($reset_link) . "'>Reset Password</a><br><br>If you did not request this, please ignore this email.";

                    $mail->send();
                    $message = '<div class="alert alert-success">If your email address is registered, you will receive a password reset link.</div>';

                } catch (Exception $e) {
                    $message = '<div class="alert alert-danger">Could not send reset email. Error: ' . $mail->ErrorInfo . '</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">Error generating reset token. Please try again.</div>';
            }
            $stmt->close();
        } else {
            $message = '<div class="alert alert-info">If your email address is registered, you will receive a password reset link.</div>';
        }
    }
}
$conn->close();
$page_title = "Forgot Password - Smart Gold Trade";
require_once 'header.php';
?>

<!-- নতুন ডিজাইন করা কনটেন্ট শুরু -->
<style>
    .forgot-password-container { 
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
        margin-bottom: 20px;
    }
    .form-group label { 
        color: #333 !important; 
        display: block;
        margin-bottom: 8px;
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
        color: #ffff; 
        /* পরিবর্তন: বাটনের উচ্চতা বাড়ানো হয়েছে */
        padding: 14px;
        margin-top: 10px;
    }
    .btn-primary:hover { 
        background-color: #c5a22d; 
        border-color: #c5a22d; 
    }
    .login-link { 
        text-align: center; 
        margin-top: 15px; 
    }
    .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
    .alert-info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }
    .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
</style>

<div class="forgot-password-container">
    <div class="card">
        <h3>Forgot Password</h3>
        <?php echo $message; ?>
        <form action="forgot_password.php" method="POST">
            <div class="form-group">
                <label for="email">Enter your email address</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            <button type="submit" name="submit_email" class="btn btn-primary">Send Reset Link</button>
        </form>
        <p class="login-link"><a href="login.php">Back to Login</a></p>
    </div>
</div>
<!-- নতুন ডিজাইন করা কনটেন্ট শেষ -->

<?php require_once 'footer.php'; ?>
