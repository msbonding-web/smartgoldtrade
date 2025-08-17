<?php
require_once 'header.php';
require_once '../db_connect.php';

// Generate unique referral link (example: using user ID)
$referral_link = "http://yourdomain.com/register.php?ref=" . $user_id; // Replace with your actual domain

// Fetch referred users
$referred_users_query = "
    SELECT r.created_at, u.username, u.email, u.status
    FROM referrals r
    JOIN users u ON r.referred_user_id = u.id
    WHERE r.referrer_id = ?
    ORDER BY r.created_at DESC
";
$stmt = $conn->prepare($referred_users_query);
if ($stmt === false) {
    die('Prepare failed for referred users query: ' . htmlspecialchars($conn->error));
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$referred_users_result = $stmt->get_result();
$stmt->close();

// Fetch referral earnings
$referral_earnings_query = "
    SELECT re.amount, re.type, re.level, re.created_at, fu.username as from_user_username
    FROM referral_earnings re
    JOIN users fu ON re.from_user_id = fu.id
    WHERE re.user_id = ?
    ORDER BY re.created_at DESC
";
$stmt = $conn->prepare($referral_earnings_query);
if ($stmt === false) {
    die('Prepare failed for referral earnings query: ' . htmlspecialchars($conn->error));
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$referral_earnings_result = $stmt->get_result();
$stmt->close();

// We DO NOT close the connection here. It will be closed automatically by PHP.
?>

<div class="card">
    <h3>Referral System</h3>

    <h4>Your Referral Link</h4>
    <div class="form-group">
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($referral_link); ?>" readonly>
        <button class="btn btn-primary" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($referral_link); ?>'); alert('Link copied!');">Copy Link</button>
    </div>

    <h4 style="margin-top: 2rem;">Share on Social Media</h4>
        <div style="margin-top: 1rem;">
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_link); ?>" target="_blank" class="btn btn-primary btn-sm" style="background-color: #3b5998;">Share on Facebook</a>
        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($referral_link); ?>&text=Join Smart Gold Trade and start investing in gold!" target="_blank" class="btn btn-primary btn-sm" style="background-color: #1da1f2;">Share on Twitter</a>
        <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($referral_link); ?>&title=Invest in Gold with Smart Gold Trade" target="_blank" class="btn btn-primary btn-sm" style="background-color: #0077b5;">Share on LinkedIn</a>
        <a href="https://api.whatsapp.com/send?text=Invest in Gold with Smart Gold Trade! Join here: <?php echo urlencode($referral_link); ?>" target="_blank" class="btn btn-primary btn-sm" style="background-color: #25d366;">Share on WhatsApp</a>
    </div>

    <h4 style="margin-top: 2rem;">Referred Users</h4>
    <table class="table">
        <thead>
            <tr><th>Username</th><th>Email</th><th>Status</th><th>Join Date</th></tr>
        </thead>
        <tbody>
            <?php if ($referred_users_result && $referred_users_result->num_rows > 0): ?>
                <?php while($referred_user = $referred_users_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($referred_user['username']); ?></td>
                        <td><?php echo htmlspecialchars($referred_user['email']); ?></td>
                        <td><?php echo htmlspecialchars($referred_user['status']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($referred_user['created_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align: center;">No users referred yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h4 style="margin-top: 2rem;">Referral Bonus History</h4>
    <table class="table">
        <thead>
            <tr><th>Bonus From</th><th>Amount</th><th>Type</th><th>Level</th><th>Date</th></tr>
        </thead>
        <tbody>
            <?php if ($referral_earnings_result && $referral_earnings_result->num_rows > 0): ?>
                <?php while($earning = $referral_earnings_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($earning['from_user_username']); ?></td>
                        <td>$<?php echo number_format($earning['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($earning['type']); ?></td>
                        <td><?php echo htmlspecialchars($earning['level']); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($earning['created_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align: center;">No referral bonuses earned yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>
