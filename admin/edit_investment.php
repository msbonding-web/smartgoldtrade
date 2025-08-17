<?php
require_once 'header.php';
require_once '../db_connect.php';

$message = '';
$investment_id = $_GET['id'] ?? null;

if (!$investment_id) {
    header("Location: investment_management.php");
    exit();
}

// Fetch investment details
$stmt = $conn->prepare("SELECT i.*, u.username, p.name as plan_name FROM investments i JOIN users u ON i.user_id = u.id JOIN investment_plans p ON i.plan_id = p.id WHERE i.id = ?");
$stmt->bind_param('i', $investment_id);
$stmt->execute();
$result = $stmt->get_result();
$investment = $result->fetch_assoc();
$stmt->close();

if (!$investment) {
    echo "Investment not found.";
    require_once 'footer.php';
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_investment'])) {
    $amount = $_POST['amount'];
    $status = $_POST['status'];
    $started_at = $_POST['started_at'];
    $ends_at = $_POST['ends_at'];

    // Basic validation
    if (!is_numeric($amount) || empty($status) || empty($started_at) || empty($ends_at)) {
        $message = '<div class="alert alert-danger">Please fill in all fields correctly.</div>';
    } else {
        $update_stmt = $conn->prepare("UPDATE investments SET amount = ?, status = ?, started_at = ?, ends_at = ? WHERE id = ?");
        $update_stmt->bind_param('dsssi', $amount, $status, $started_at, $ends_at, $investment_id);
        
        if ($update_stmt->execute()) {
            $message = '<div class="alert alert-success">Investment updated successfully. <a href="investment_management.php">Return to list.</a></div>';
            // Re-fetch data to display updated values
            $stmt = $conn->prepare("SELECT i.*, u.username, p.name as plan_name FROM investments i JOIN users u ON i.user_id = u.id JOIN investment_plans p ON i.plan_id = p.id WHERE i.id = ?");
            $stmt->bind_param('i', $investment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $investment = $result->fetch_assoc();
            $stmt->close();
        } else {
            $message = '<div class="alert alert-danger">Error updating investment: ' . $update_stmt->error . '</div>';
        }
        $update_stmt->close();
    }
}

?>

<div class="card">
    <h3>Edit Investment #<?php echo htmlspecialchars($investment_id); ?></h3>
    <p><strong>User:</strong> <?php echo htmlspecialchars($investment['username']); ?></p>
    <p><strong>Plan:</strong> <?php echo htmlspecialchars($investment['plan_name']); ?></p>
    
    <?php echo $message; ?>

    <form action="edit_investment.php?id=<?php echo htmlspecialchars($investment_id); ?>" method="POST">
        <div class="form-group">
            <label for="amount">Amount</label>
            <input type="number" step="0.01" id="amount" name="amount" class="form-control" value="<?php echo htmlspecialchars($investment['amount']); ?>" required>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control" required>
                <option value="pending" <?php if ($investment['status'] === 'pending') echo 'selected'; ?>>Pending</option>
                <option value="active" <?php if ($investment['status'] === 'active') echo 'selected'; ?>>Active</option>
                <option value="completed" <?php if ($investment['status'] === 'completed') echo 'selected'; ?>>Completed</option>
                <option value="frozen" <?php if ($investment['status'] === 'frozen') echo 'selected'; ?>>Frozen</option>
                <option value="cancelled" <?php if ($investment['status'] === 'cancelled') echo 'selected'; ?>>Cancelled</option>
            </select>
        </div>
        <div class="form-group">
            <label for="started_at">Start Date</label>
            <input type="datetime-local" id="started_at" name="started_at" class="form-control" value="<?php echo $investment['started_at'] ? date('Y-m-d\TH:i:s', strtotime($investment['started_at'])) : ''; ?>">
        </div>
        <div class="form-group">
            <label for="ends_at">End Date</label>
            <input type="datetime-local" id="ends_at" name="ends_at" class="form-control" value="<?php echo $investment['ends_at'] ? date('Y-m-d\TH:i:s', strtotime($investment['ends_at'])) : ''; ?>">
        </div>
        <button type="submit" name="update_investment" class="btn btn-primary">Update Investment</button>
        <a href="investment_management.php" class="btn">Cancel</a>
    </form>
</div>

<?php 
$conn->close();
require_once 'footer.php'; 
?>
