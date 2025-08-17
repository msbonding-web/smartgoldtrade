<?php
$page_title = "Client Dashboard";
require_once 'header.php';
require_once 'db_connect.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch user's wallets information
$wallets_query = "
    SELECT 
        w.balance, 
        w.available, 
        w.locked, 
        c.name as currency_name, 
        c.code as currency_code, 
        wt.name as wallet_type_name
    FROM wallets w
    JOIN currencies c ON w.currency_id = c.id
    JOIN wallet_types wt ON w.wallet_type_id = wt.id
    WHERE w.user_id = ?
    ORDER BY wt.id, c.id
";

$stmt = $conn->prepare($wallets_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wallets_result = $stmt->get_result();

?>

<div class="card">
    <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
    <p>This is your client dashboard. Here you can see an overview of your account.</p>
</div>

<div class="card" style="margin-top: 2rem;">
    <h3>Your Wallets</h3>
    <?php if ($wallets_result->num_rows > 0): ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="text-align: left;">
                <tr>
                    <th style="padding: 8px; border-bottom: 1px solid #ddd;">Wallet Type</th>
                    <th style="padding: 8px; border-bottom: 1px solid #ddd;">Currency</th>
                    <th style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;">Balance</th>
                    <th style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;">Available</th>
                </tr>
            </thead>
            <tbody>
            <?php while($wallet = $wallets_result->fetch_assoc()): ?>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?php echo htmlspecialchars($wallet['wallet_type_name']); ?></td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?php echo htmlspecialchars($wallet['currency_name']); ?> (<?php echo htmlspecialchars($wallet['currency_code']); ?>)</td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;"><?php echo number_format($wallet['balance'], 8); ?></td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;"><?php echo number_format($wallet['available'], 8); ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>You don't have any wallets set up yet.</p>
    <?php endif; ?>
</div>

<?php
$stmt->close();
$conn->close();
require_once 'footer.php';
?>
