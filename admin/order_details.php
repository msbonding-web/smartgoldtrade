<?php
require_once 'header.php';
require_once '../db_connect.php';

$order_id = $_GET['id'] ?? 0;

if (!is_numeric($order_id) || $order_id <= 0) {
    die("Invalid Order ID.");
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['new_status'] ?? '';
    $update_query = "UPDATE orders SET status = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('si', $new_status, $order_id);
    if ($update_stmt->execute()) {
        // Success, refresh page to show updated status
        header("Location: order_details.php?id=" . $order_id . "&update=success");
        exit();
    } else {
        $error_message = "Error updating status: " . $update_stmt->error;
    }
    $update_stmt->close();
}

// Fetch order details
$order_query = "
    SELECT o.*, u.username, u.email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
";
$order_stmt = $conn->prepare($order_query);
$order_stmt->bind_param('i', $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
if ($order_result->num_rows === 1) {
    $order = $order_result->fetch_assoc();
} else {
    die("Order not found.");
}
$order_stmt->close();

// Fetch order items
$items_query = "
    SELECT oi.*, p.name as product_name, p.sku
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param('i', $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items_stmt->close();

// Fetch shipment details (if any)
$shipment_query = "SELECT * FROM shipments WHERE order_id = ?";
$shipment_stmt = $conn->prepare($shipment_query);
$shipment_stmt->bind_param('i', $order_id);
$shipment_stmt->execute();
$shipment_result = $shipment_stmt->get_result();
$shipment = $shipment_result->fetch_assoc();
$shipment_stmt->close();

$conn->close();
?>

<a href="ecommerce_management.php?tab=orders" class="btn" style="margin-bottom: 1rem; background-color: var(--gray);">← Back to Orders</a>

<div class="card">
    <h3>Order Details: #<?php echo htmlspecialchars($order['order_number']); ?></h3>

    <?php if (isset($_GET['update']) && $_GET['update'] === 'success'): ?>
        <div class="alert alert-success">Order status updated successfully!</div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="grid-container" style="grid-template-columns: 1fr 1fr; align-items: flex-start;">
        <div>
            <h4>Order Information</h4>
            <p><strong>Order Date:</strong> <?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></p>
            <p><strong>Status:</strong> <span style="font-weight: bold; text-transform: capitalize;"><?php echo htmlspecialchars($order['status']); ?></span></p>
            <p><strong>Total Amount:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
            <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['username']); ?> (<?php echo htmlspecialchars($order['email']); ?>)</p>

            <h4 style="margin-top: 2rem;">Update Order Status</h4>
            <form action="order_details.php?id=<?php echo $order_id; ?>" method="POST">
                <div class="form-group">
                    <select name="new_status" class="form-control">
                        <option value="pending" <?php if($order['status'] === 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="paid" <?php if($order['status'] === 'paid') echo 'selected'; ?>>Paid</option>
                        <option value="processing" <?php if($order['status'] === 'processing') echo 'selected'; ?>>Processing</option>
                        <option value="shipped" <?php if($order['status'] === 'shipped') echo 'selected'; ?>>Shipped</option>
                        <option value="delivered" <?php if($order['status'] === 'delivered') echo 'selected'; ?>>Delivered</option>
                        <option value="cancelled" <?php if($order['status'] === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                        <option value="refunded" <?php if($order['status'] === 'refunded') echo 'selected'; ?>>Refunded</option>
                    </select>
                </div>
                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
            </form>
        </div>

        <div>
            <h4>Shipping Information</h4>
            <?php if ($order['shipping_address']): 
                $shipping_address = json_decode($order['shipping_address'], true);
            ?>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($shipping_address['address_line1'] ?? ''); ?></p>
                <p><strong>City:</strong> <?php echo htmlspecialchars($shipping_address['city'] ?? ''); ?></p>
                <p><strong>Postal Code:</strong> <?php echo htmlspecialchars($shipping_address['postal_code'] ?? ''); ?></p>
                <p><strong>Country:</strong> <?php echo htmlspecialchars($shipping_address['country_code'] ?? ''); ?></p>
            <?php else: ?>
                <p>No shipping address provided.</p>
            <?php endif; ?>

            <?php if ($shipment): ?>
                <h4 style="margin-top: 2rem;">Shipment Details</h4>
                <p><strong>Carrier:</strong> <?php echo htmlspecialchars($shipment['carrier']); ?></p>
                <p><strong>Tracking:</strong> <?php echo htmlspecialchars($shipment['tracking_number']); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($shipment['status']); ?></p>
                <p><strong>Shipped At:</strong> <?php echo $shipment['shipped_at'] ? date('Y-m-d', strtotime($shipment['shipped_at'])) : 'N/A'; ?></p>
            <?php else: ?>
                <p style="margin-top: 2rem;">No shipment details available.</p>
            <?php endif; ?>
        </div>
    </div>

    <h4 style="margin-top: 2rem;">Order Items</h4>
    <table class="table">
        <thead>
            <tr><th>Product</th><th>SKU</th><th>Quantity</th><th>Price</th><th>Total</th></tr>
        </thead>
        <tbody>
            <?php if ($items_result && $items_result->num_rows > 0): ?>
                <?php while($item = $items_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['sku']); ?></td>
                        <td><?php echo htmlspecialchars($item['qty']); ?></td>
                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                        <td>$<?php echo number_format($item['qty'] * $item['price'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align: center;">No items found for this order.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>
