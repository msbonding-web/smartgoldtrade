<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$plan_id = $_GET['id'] ?? 0;

if (!is_numeric($plan_id) || $plan_id <= 0) {
    die("Invalid Plan ID.");
}

// Handle form submission for updating the plan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data
    $name = $_POST['name'] ?? '';
    $min_amount = $_POST['min_amount'] ?? 0;
    $max_amount = $_POST['max_amount'] ?? 0;
    $duration_value = $_POST['duration_value'] ?? 0;
    $duration_unit = $_POST['duration_unit'] ?? 'day';
    $return_period_value = $_POST['return_period_value'] ?? 0;
    $return_period_unit = $_POST['return_period_unit'] ?? 'day';
    $number_of_returns = $_POST['number_of_returns'] ?? 0;
    $profit_type = $_POST['profit_type'] ?? 'percent';
    $profit_value = $_POST['profit_value'] ?? 0;
    $capital_back = isset($_POST['capital_back']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $query = "UPDATE investment_plans SET name = ?, min_amount = ?, max_amount = ?, duration_value = ?, duration_unit = ?, return_period_value = ?, return_period_unit = ?, number_of_returns = ?, profit_type = ?, profit_value = ?, capital_back = ?, is_active = ? WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        'sddisisiisiis',
        $name, $min_amount, $max_amount, $duration_value, $duration_unit, 
        $return_period_value, $return_period_unit, $number_of_returns, 
        $profit_type, $profit_value, $capital_back, $is_active, $plan_id
    );

    if ($stmt->execute()) {
        $success_message = "Plan updated successfully! You will be redirected shortly.";
        echo "<meta http-equiv='refresh' content='3;url=investment_management.php'>";
    } else {
        $error_message = "Error updating plan: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch the current plan details to populate the form
$query = "SELECT * FROM investment_plans WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $plan_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $plan = $result->fetch_assoc();
} else {
    die("Plan not found.");
}
$stmt->close();
$conn->close();
?>

<div class="card">
    <h3>Edit Investment Plan: <?php echo htmlspecialchars($plan['name']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_plan.php?id=<?php echo $plan_id; ?>" method="POST">
        <div class="grid-container" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label for="name">Plan Name</label>
                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($plan['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="profit_value">Profit Value</label>
                <input type="number" step="0.01" id="profit_value" name="profit_value" class="form-control" value="<?php echo htmlspecialchars($plan['profit_value']); ?>" required>
            </div>
            <div class="form-group">
                <label for="profit_type">Profit Type</label>
                <select id="profit_type" name="profit_type" class="form-control">
                    <option value="percent" <?php if($plan['profit_type'] === 'percent') echo 'selected'; ?>>Percent (%)</option>
                    <option value="fixed" <?php if($plan['profit_type'] === 'fixed') echo 'selected'; ?>>Fixed</option>
                </select>
            </div>
            <div class="form-group">
                <label for="min_amount">Minimum Amount</label>
                <input type="number" step="0.01" id="min_amount" name="min_amount" class="form-control" value="<?php echo htmlspecialchars($plan['min_amount']); ?>">
            </div>
            <div class="form-group">
                <label for="max_amount">Maximum Amount</label>
                <input type="number" step="0.01" id="max_amount" name="max_amount" class="form-control" value="<?php echo htmlspecialchars($plan['max_amount']); ?>">
            </div>
            <div class="form-group">
                <label for="duration_value">Duration Value</label>
                <input type="number" id="duration_value" name="duration_value" class="form-control" value="<?php echo htmlspecialchars($plan['duration_value']); ?>" required>
            </div>
            <div class="form-group">
                <label for="duration_unit">Duration Unit</label>
                <select id="duration_unit" name="duration_unit" class="form-control">
                    <option value="hour" <?php if($plan['duration_unit'] === 'hour') echo 'selected'; ?>>Hour(s)</option>
                    <option value="day" <?php if($plan['duration_unit'] === 'day') echo 'selected'; ?>>Day(s)</option>
                    <option value="month" <?php if($plan['duration_unit'] === 'month') echo 'selected'; ?>>Month(s)</option>
                    <option value="year" <?php if($plan['duration_unit'] === 'year') echo 'selected'; ?>>Year(s)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="number_of_returns">Number of Returns</label>
                <input type="number" id="number_of_returns" name="number_of_returns" class="form-control" value="<?php echo htmlspecialchars($plan['number_of_returns']); ?>">
            </div>
            <div class="form-group">
                <label for="return_period_value">Return Period Value</label>
                <input type="number" id="return_period_value" name="return_period_value" class="form-control" value="<?php echo htmlspecialchars($plan['return_period_value']); ?>">
            </div>
            <div class="form-group">
                <label for="return_period_unit">Return Period Unit</label>
                <select id="return_period_unit" name="return_period_unit" class="form-control">
                    <option value="hour" <?php if($plan['return_period_unit'] === 'hour') echo 'selected'; ?>>Hour(s)</option>
                    <option value="day" <?php if($plan['return_period_unit'] === 'day') echo 'selected'; ?>>Day(s)</option>
                    <option value="month" <?php if($plan['return_period_unit'] === 'month') echo 'selected'; ?>>Month(s)</option>
                </select>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="capital_back" value="1" <?php if($plan['capital_back']) echo 'checked'; ?>> Capital Back</label>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_active" value="1" <?php if($plan['is_active']) echo 'checked'; ?>> Is Active</label>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Update Plan</button>
        <a href="investment_management.php" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
