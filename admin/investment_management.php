<?php 
require_once 'header.php'; 
require_once '../db_connect.php';

$message = '';

// Handle Delete Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plan'])) {
    $plan_id = $_POST['plan_id'];

    // Safety Check: Ensure no investments are using this plan
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM investments WHERE plan_id = ?");
    $check_stmt->bind_param('i', $plan_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($result['count'] > 0) {
        $message = '<div class="alert alert-danger">Cannot delete plan. There are ' . $result['count'] . ' investment(s) currently using this plan.</div>';
    } else {
        // Proceed with deletion
        $delete_stmt = $conn->prepare("DELETE FROM investment_plans WHERE id = ?");
        $delete_stmt->bind_param('i', $plan_id);
        if ($delete_stmt->execute()) {
            $message = '<div class="alert alert-success">Investment plan deleted successfully.</div>';
        } else {
            $message = '<div class="alert alert-danger">Error deleting investment plan.</div>';
        }
        $delete_stmt->close();
    }
}

// Handle Approve Investment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_investment'])) {
    $investment_id = $_POST['investment_id'];
    $update_stmt = $conn->prepare("UPDATE investments SET status = 'active', started_at = NOW() WHERE id = ? AND status = 'pending'");
    $update_stmt->bind_param('i', $investment_id);
    if ($update_stmt->execute()) {
        $message = '<div class="alert alert-success">Investment approved and set to active.</div>';
    } else {
        $message = '<div class="alert alert-danger">Error approving investment.</div>';
    }
    $update_stmt->close();
}

// Handle Delete Investment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_investment'])) {
    $investment_id = $_POST['investment_id'];
    $delete_stmt = $conn->prepare("DELETE FROM investments WHERE id = ? AND status NOT IN ('active', 'completed')");
    $delete_stmt->bind_param('i', $investment_id);
    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            $message = '<div class="alert alert-success">Investment deleted successfully.</div>';
        } else {
            $message = '<div class="alert alert-warning">Could not delete investment. It might be active, completed, or already deleted.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Error deleting investment.</div>';
    }
    $delete_stmt->close();
}


// Fetch all investment plans
$plans_query = "SELECT * FROM investment_plans ORDER BY display_order";
$plans_result = $conn->query($plans_query);

// Fetch all investments (including pending, active, etc.)
$investments_query = "
    SELECT 
        i.id, i.amount, i.status, i.started_at, i.ends_at,
        u.username,
        p.name as plan_name
    FROM investments i
    JOIN users u ON i.user_id = u.id
    JOIN investment_plans p ON i.plan_id = p.id
    ORDER BY i.started_at DESC
";
$investments_result = $conn->query($investments_query);

?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3>Investment Plans</h3>
        <a href="create_plan.php" class="btn btn-primary">Create New Plan</a>
    </div>
    <?php if(!empty($message)) echo $message; // Display feedback messages related to plans at the top of this card ?>
    <div class="grid-container">
        <?php if ($plans_result && $plans_result->num_rows > 0): ?>
            <?php while($plan = $plans_result->fetch_assoc()): ?>
                <div class="card" style="background-color: #f9f9f9; border-left: 4px solid var(--accent-color);">
                    <h4><?php echo htmlspecialchars($plan['name']); ?></h4>
                    <p><strong>Profit:</strong> <?php echo htmlspecialchars($plan['profit_value']); ?>%</p>
                    <p><strong>Duration:</strong> <?php echo htmlspecialchars($plan['duration_value']); ?> <?php echo htmlspecialchars($plan['duration_unit']); ?>(s)</p>
                    <p><strong>Min/Max Amount:</strong> $<?php echo number_format($plan['min_amount']); ?> - $<?php echo number_format($plan['max_amount']); ?></p>
                    <div>
                        <a href="edit_plan.php?id=<?php echo $plan['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                        <form action="investment_management.php" method="POST" style="display: inline-block;">
                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                            <button type="submit" name="delete_plan" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to permanently delete this plan? This cannot be undone.');">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No investment plans found.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom: 1rem;">All Investments</h3>
    <?php if(!empty($message)) echo $message; // Display feedback messages ?>
    <table class="table">
        <thead>
            <tr>
                <th>User</th>
                <th>Plan</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th style="width: 280px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($investments_result && $investments_result->num_rows > 0): ?>
                <?php while($investment = $investments_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($investment['username']); ?></td>
                        <td><?php echo htmlspecialchars($investment['plan_name']); ?></td>
                        <td>$<?php echo number_format($investment['amount'], 2); ?></td>
                        <td>
                             <?php 
                            $status = htmlspecialchars($investment['status']);
                            $color = 'var(--gray)';
                            if ($status === 'active') $color = 'var(--success)';
                            if ($status === 'completed') $color = 'var(--primary-color)';
                            if ($status === 'frozen') $color = 'var(--danger)';
                            if ($status === 'pending') $color = 'var(--warning)';
                            ?>
                            <span style="color: <?php echo $color; ?>; text-transform: capitalize;"><?php echo $status; ?></span>
                        </td>
                        <td><?php echo $investment['started_at'] ? date('Y-m-d', strtotime($investment['started_at'])) : 'N/A'; ?></td>
                        <td><?php echo $investment['ends_at'] ? date('Y-m-d', strtotime($investment['ends_at'])) : 'N/A'; ?></td>
                        <td>
                            <a href="investment_details.php?id=<?php echo $investment['id']; ?>" class="btn btn-sm">Details</a>
                            <a href="edit_investment.php?id=<?php echo $investment['id']; ?>" class="btn btn-info btn-sm">Edit</a>

                            <?php if ($investment['status'] === 'pending'): ?>
                                <form action="investment_management.php" method="POST" style="display: inline-block;">
                                    <input type="hidden" name="investment_id" value="<?php echo $investment['id']; ?>">
                                    <button type="submit" name="approve_investment" class="btn btn-success btn-sm">Approve</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($investment['status'] === 'active'): ?>
                                <a href="manual_payout.php?id=<?php echo $investment['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to process a manual payout? This will complete the investment.');">Payout</a>
                            <?php endif; ?>
                            
                            <?php if ($investment['status'] !== 'active' && $investment['status'] !== 'completed'): ?>
                                <form action="investment_management.php" method="POST" style="display: inline-block;">
                                    <input type="hidden" name="investment_id" value="<?php echo $investment['id']; ?>">
                                    <button type="submit" name="delete_investment" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to permanently delete this investment?');">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No investments found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
$conn->close();
require_once 'footer.php'; 
?>
