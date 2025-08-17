<?php
session_start();
require_once 'header.php';
require_once 'db_connect.php';

$message = '';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// Handle cart item updates or removals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        // Get user's cart_id
        $cart_id = null;
        $cart_stmt = $conn->prepare("SELECT id FROM carts WHERE user_id = ?");
        $cart_stmt->bind_param('i', $user_id);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();
        if ($row = $cart_result->fetch_assoc()) {
            $cart_id = $row['id'];
        } else {
            throw new Exception("Cart not found."); // Should not happen if add to cart works
        }
        $cart_stmt->close();

        if (isset($_POST['update_quantity'])) {
            $product_id = $_POST['product_id'];
            $quantity = $_POST['quantity'];

            if (!is_numeric($quantity) || $quantity <= 0) {
                throw new Exception("Invalid quantity.");
            }

            $update_stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
            $update_stmt->bind_param('iii', $quantity, $cart_id, $product_id);
            if (!$update_stmt->execute()) {
                throw new Exception("Error updating quantity: " . $update_stmt->error);
            }
            $update_stmt->close();
            $message = '<div class="alert alert-success">Cart updated successfully.</div>';

        } elseif (isset($_POST['remove_item'])) {
            $product_id = $_POST['product_id'];

            $delete_stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?");
            $delete_stmt->bind_param('ii', $cart_id, $product_id);
            if (!$delete_stmt->execute()) {
                throw new Exception("Error removing item: " . $delete_stmt->error);
            }
            $delete_stmt->close();
            $message = '<div class="alert alert-success">Item removed from cart.</div>';
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// Fetch cart contents
$cart_items = [];
$total_cart_amount = 0;

$cart_query = "
    SELECT ci.product_id, ci.quantity, p.name, p.price, p.stock, COALESCE(pi.path, p.image_path) AS image_path
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.display_order = 0
    JOIN carts c ON ci.cart_id = c.id
    WHERE c.user_id = ?
    ORDER BY p.name ASC
";

$stmt = $conn->prepare($cart_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($item = $result->fetch_assoc()) {
    $item['subtotal'] = $item['quantity'] * $item['price'];
    $total_cart_amount += $item['subtotal'];
    $cart_items[] = $item;
}
$stmt->close();

$conn->close();
?>

<style>
    .cart-table img { width: 80px; height: 80px; object-fit: contain; border-radius: 4px; }
    .cart-table input[type="number"] { width: 60px; padding: 5px; border: 1px solid #ddd; border-radius: 4px; color: #000; }
    .cart-summary { text-align: right; margin-top: 20px; font-size: 1.2rem; }
    .cart-actions { display: flex; justify-content: space-between; margin-top: 30px; }
</style>

<div class="card">
    <h3>Your Shopping Cart</h3>

    <?php echo $message; ?>

    <?php if (empty($cart_items)): ?>
        <div class="alert alert-info">Your cart is empty. <a href="gold_shop.php">Start shopping!</a></div>
    <?php else: ?>
        <table class="table cart-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart_items as $item): ?>
                    <tr>
                        <td>
                            <img src="<?php echo !empty($item['image_path']) ? htmlspecialchars('uploads/products/' . $item['image_path']) : 'uploads/no_image.png'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </td>
                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                        <td>
                            <form action="cart.php" method="POST" style="display: flex; align-items: center; gap: 5px;">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo htmlspecialchars($item['stock']); ?>">
                                <button type="submit" name="update_quantity" class="btn btn-sm">Update</button>
                            </form>
                        </td>
                        <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                        <td>
                            <form action="cart.php" method="POST">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <button type="submit" name="remove_item" class="btn btn-danger btn-sm">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="cart-summary">
            <strong>Total: $<?php echo number_format($total_cart_amount, 2); ?></strong>
        </div>

        <div class="cart-actions">
            <a href="gold_shop.php" class="btn" style="background-color: var(--gray);">Continue Shopping</a>
            <a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
