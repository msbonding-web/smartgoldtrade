<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$coupon_id = $_GET['id'] ?? 0;

if (!is_numeric($coupon_id) || $coupon_id <= 0) {
    die("Invalid Coupon ID.");
}

// Handle form submission for updating the coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $type = $_POST['type'] ?? '';
    $value = $_POST['value'] ?? 0;
    $min_order_amount = $_POST['min_order_amount'] ?? 0;
    $usage_limit = $_POST['usage_limit'] ?? null;
    $expiry_date = $_POST['expiry_date'] ?? null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($code) || empty($type) || empty($value)) {
        $error_message = "Code, Type, and Value are required for the coupon.";
    } else {
        // Check for duplicate code (excluding current coupon)
        $check_query = "SELECT id FROM coupons WHERE code = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('si', $code, $coupon_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "Coupon code already exists.";
        } else {
            $query = "UPDATE coupons SET code = ?, type = ?, value = ?, min_order_amount = ?, usage_limit = ?, expiry_date = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssddisii', $code, $type, $value, $min_order_amount, $usage_limit, $expiry_date, $is_active, $coupon_id);

            if ($stmt->execute()) {
                $success_message = "Coupon updated successfully! You will be redirected shortly.";
                echo "<meta http-equiv='refresh' content='3;url=ecommerce_management.php?tab=discounts'>";
            } else {
                $error_message = "Error updating coupon: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Fetch current coupon details to populate the form
$query = "SELECT * FROM coupons WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $coupon_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $coupon = $result->fetch_assoc();
} else {
    die("Coupon not found.");
}
$stmt->close();
$conn->close();
?>

<div class="card">
    <h3>Edit Coupon: <?php echo htmlspecialchars($coupon['code']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_coupon.php?id=<?php echo $coupon_id; ?>" method="POST">
        <div class="grid-container" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label for="code">Coupon Code</label>
                <input type="text" id="code" name="code" class="form-control" value="<?php echo htmlspecialchars($coupon['code']); ?>" required>
            </div>
            <div class="form-group">
                <label for="type">Discount Type</label>
                <select id="type" name="type" class="form-control" required>
                    <option value="percentage" <?php if($coupon['type'] === 'percentage') echo 'selected'; ?>>Percentage (%)</option>
                    <option value="fixed_amount" <?php if($coupon['type'] === 'fixed_amount') echo 'selected'; ?>>Fixed Amount ($)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="value">Discount Value</label>
                <input type="number" step="0.01" id="value" name="value" class="form-control" value="<?php echo htmlspecialchars($coupon['value']); ?>" required>
            </div>
            <div class="form-group">
                <label for="min_order_amount">Minimum Order Amount</label>
                <input type="number" step="0.01" id="min_order_amount" name="min_order_amount" class="form-control" value="<?php echo htmlspecialchars($coupon['min_order_amount']); ?>">
            </div>
            <div class="form-group">
                <label for="usage_limit">Usage Limit (optional)</label>
                <input type="number" id="usage_limit" name="usage_limit" class="form-control" value="<?php echo htmlspecialchars($coupon['usage_limit'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="expiry_date">Expiry Date (optional)</label>
                <input type="date" id="expiry_date" name="expiry_date" class="form-control" value="<?php echo htmlspecialchars(substr($coupon['expiry_date'], 0, 10) ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_active" value="1" <?php if($coupon['is_active']) echo 'checked'; ?>> Is Active</label>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Update Coupon</button>
        <a href="ecommerce_management.php?tab=discounts" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
