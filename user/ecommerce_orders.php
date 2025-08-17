<?php
require_once 'header.php';
require_once '../db_connect.php';

// Fetch user's purchase orders
$orders_query = "
    SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
";
$stmt = $conn->prepare($orders_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();
$stmt->close();

// Fetch user's wishlist
$wishlist_query = "
    SELECT wi.id, p.name as product_name, p.price, pi.path as image_path
    FROM wishlist_items wi
    JOIN wishlists w ON wi.wishlist_id = w.id
    JOIN products p ON wi.product_id = p.id
    LEFT JOIN product_images pi ON p.id = pi.product_id
    WHERE w.user_id = ?
    GROUP BY wi.id
    ORDER BY wi.id DESC
";
$stmt = $conn->prepare($wishlist_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$wishlist_result = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<div class="card">
    <h3>E-Commerce Orders</h3>

    <h4>Your Purchase Orders</h4>
    <table class="table">
        <thead>
            <tr><th>Order ID</th><th>Date</th><th>Total</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                <?php while($order = $orders_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></td>
                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($order['status']); ?></td>
                        <td><button class="btn btn-primary btn-sm">View Details</button></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align: center;">No purchase orders found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h4 style="margin-top: 2rem;">Your Wishlist</h4>
    <table class="table">
        <thead>
            <tr><th>No.</th><th>Image</th><th>Title</th><th>Price</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php if ($wishlist_result && $wishlist_result->num_rows > 0): ?>
                <?php $sl_no = 1; while($item = $wishlist_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $sl_no++; ?></td>
                        <td>
                            <?php if (!empty($item['image_path'])): ?>
                                <img src="../uploads/products/<?php echo htmlspecialchars($item['image_path']); ?>" alt="Product Image" style="width: 50px; height: 50px; object-fit: cover;">
                            <?php else: ?>
                                No Image
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                        <td><button class="btn btn-danger btn-sm">Remove</button></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align: center;">Your wishlist is empty.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>
