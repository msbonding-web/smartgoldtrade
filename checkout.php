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

// Fetch user's cart contents
$cart_items = [];
$total_cart_amount = 0;
$cart_id = null;

$cart_query = "
    SELECT ci.product_id, ci.quantity, p.name, p.price, p.stock, COALESCE(pi.path, p.image_path) AS image_path, c.id as cart_header_id
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
    $cart_id = $item['cart_header_id']; // Get cart_id from one of the items
}
$stmt->close();

// If cart is empty, redirect to gold shop
if (empty($cart_items)) {
    header("Location: gold_shop.php");
    exit();
}

// Fetch user profile for pre-filling address (assuming user_profiles table exists)
$user_profile = [];
$profile_query = "SELECT first_name, last_name, address_line1, address_line2, city, state, postal_code, country_code FROM user_profiles WHERE user_id = ?";
$profile_stmt = $conn->prepare($profile_query);
$profile_stmt->bind_param('i', $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
if ($row = $profile_result->fetch_assoc()) {
    $user_profile = $row;
}
$profile_stmt->close();

// Fetch payment methods
$payment_methods = [];
$pm_query = "SELECT id, name, slug FROM payment_methods WHERE is_active = 1 ORDER BY name";
$pm_result = $conn->query($pm_query);
while($pm = $pm_result->fetch_assoc()) {
    $payment_methods[] = $pm;
}

// Handle Order Placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $shipping_address_raw = $_POST['shipping_address'] ?? '';
    $billing_address_raw = $_POST['billing_address'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';

    // Convert raw addresses to JSON strings
    $shipping_address = json_encode(['raw_address' => $shipping_address_raw]);
    $billing_address = json_encode(['raw_address' => $billing_address_raw]);

    if (empty($shipping_address_raw) || empty($billing_address_raw) || empty($payment_method)) { // Check raw values for emptiness
        $message = '<div class="alert alert-danger">Please fill in all required address and payment details.</div>';
    } else {
        $conn->begin_transaction();
        try {
            $order_status = 'pending'; // Default status
            $currency_id = 1; // Assuming USD
            $payment_success = true;
            $payment_remarks = '';

            switch ($payment_method) {
                case 'cash_on_delivery':
                    $order_status = 'pending';
                    $payment_remarks = 'Payment via Cash on Delivery.';
                    break;
                case 'main_wallet':
                    // Fetch user's main wallet balance
                    $wallet_balance_stmt = $conn->prepare("SELECT id, balance, available FROM wallets WHERE user_id = ? AND wallet_type_id = (SELECT id FROM wallet_types WHERE slug = 'main') AND currency_id = ? FOR UPDATE");
                    $wallet_balance_stmt->bind_param('ii', $user_id, $currency_id);
                    $wallet_balance_stmt->execute();
                    $user_wallet = $wallet_balance_stmt->get_result()->fetch_assoc();
                    $wallet_balance_stmt->close();

                    if (!$user_wallet || $user_wallet['available'] < $total_cart_amount) {
                        $payment_success = false;
                        $order_status = 'failed';
                        $payment_remarks = 'Insufficient funds in main wallet.';
                        $message = '<div class="alert alert-danger">Insufficient funds in your main wallet.</div>';
                    } else {
                        // Deduct from wallet
                        $new_balance = $user_wallet['balance'] - $total_cart_amount;
                        $new_available = $user_wallet['available'] - $total_cart_amount;
                        $update_wallet_stmt = $conn->prepare("UPDATE wallets SET balance = ?, available = ? WHERE id = ?");
                        $update_wallet_stmt->bind_param('ddi', $new_balance, $new_available, $user_wallet['id']);
                        if (!$update_wallet_stmt->execute()) {
                            throw new Exception("Error deducting from main wallet: " . $update_wallet_stmt->error);
                        }
                        $update_wallet_stmt->close();

                        // Record wallet transaction
                        $trans_remarks = 'Payment for Order ' . $order_number;
                        $trans_stmt = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, fee, ref_type, ref_id, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $direction = 'debit';
                        $balance_before_trans = $user_wallet['balance'];
                        $balance_after_trans = $new_balance;
                        $ref_type = 'order_payment';
                        $ref_id = $order_id; // Order ID will be known after order insert
                        $fee = 0.00; // Default fee for order payment
                        $trans_stmt->bind_param('isddddsis', $user_wallet['id'], $direction, $total_cart_amount, $balance_before_trans, $balance_after_trans, $fee, $ref_type, $ref_id, $trans_remarks);
                        if (!$trans_stmt->execute()) {
                            throw new Exception("Error recording wallet transaction: " . $trans_stmt->error);
                        }
                        $trans_stmt->close();

                        $order_status = 'paid';
                        $payment_remarks = 'Payment successful via Main Wallet.';
                    }
                    break;
                case 'binance':
                case 'cryptos':
                case 'bank_transfer':
                    $order_status = 'pending';
                    $payment_remarks = 'Payment instructions for ' . htmlspecialchars($payment_method) . ' will be sent to your email.';
                    $message = '<div class="alert alert-info">Order placed. Please follow the instructions for ' . htmlspecialchars($payment_method) . ' payment.</div>';
                    break;
                default:
                    $payment_success = false;
                    $order_status = 'failed';
                    $payment_remarks = 'Invalid payment method selected.';
                    $message = '<div class="alert alert-danger">Invalid payment method selected.</div>';
                    break;
            }

            if (!$payment_success) {
                throw new Exception($payment_remarks); // Re-throw if payment failed
            }

            // 1. Create Order Record
            $order_number = 'ORD-' . uniqid();
            // $order_status is determined by payment method
            // $currency_id is 1 (USD)

            $insert_order_stmt = $conn->prepare("INSERT INTO orders (user_id, order_number, total_amount, currency_id, shipping_address, billing_address, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert_order_stmt->bind_param('isdssss', $user_id, $order_number, $total_cart_amount, $currency_id, $shipping_address, $billing_address, $order_status);
            if (!$insert_order_stmt->execute()) {
                throw new Exception("Error creating order: " . $insert_order_stmt->error);
            }
            $order_id = $conn->insert_id;
            $insert_order_stmt->close();

            // Update ref_id for wallet transaction if main_wallet payment was used
            if ($payment_method === 'main_wallet' && $order_status === 'paid') {
                $update_trans_ref_stmt = $conn->prepare("UPDATE wallet_transactions SET ref_id = ? WHERE remarks LIKE 'Payment for Order %' AND wallet_id = ? ORDER BY created_at DESC LIMIT 1");
                $update_trans_ref_stmt->bind_param('ii', $order_id, $user_wallet['id']);
                $update_trans_ref_stmt->execute(); // No need to throw exception if this fails, main order is already placed
                $update_trans_ref_stmt->close();
            }

            // 2. Add Order Items and Update Stock
            foreach ($cart_items as $item) {
                // Insert into order_items
                $insert_item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, qty, price, currency_id) VALUES (?, ?, ?, ?, ?)");
                $insert_item_stmt->bind_param('iiidi', $order_id, $item['product_id'], $item['quantity'], $item['price'], $currency_id);
                if (!$insert_item_stmt->execute()) {
                    throw new Exception("Error adding order item: " . $insert_item_stmt->error);
                }
                $insert_item_stmt->close();

                // Update product stock
                $update_stock_stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                $update_stock_stmt->bind_param('iii', $item['quantity'], $item['product_id'], $item['quantity']);
                if (!$update_stock_stmt->execute() || $update_stock_stmt->affected_rows === 0) {
                    throw new Exception("Failed to update stock for product " . htmlspecialchars($item['name']) . ". Insufficient stock or product not found.");
                }
                $update_stock_stmt->close();
            }

            // 3. Clear Cart
            $clear_cart_items_stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = ?");
            $clear_cart_items_stmt->bind_param('i', $cart_id);
            if (!$clear_cart_items_stmt->execute()) {
                throw new Exception("Error clearing cart items: " . $clear_cart_items_stmt->error);
            }
            $clear_cart_items_stmt->close();

            $clear_cart_stmt = $conn->prepare("DELETE FROM carts WHERE id = ?");
            $clear_cart_stmt->bind_param('i', $cart_id);
            if (!$clear_cart_stmt->execute()) {
                throw new Exception("Error clearing cart: " . $clear_cart_stmt->error);
            }
            $clear_cart_stmt->close();

            $conn->commit();
            $message = '<div class="alert alert-success">Order placed successfully! Your order number is: ' . htmlspecialchars($order_number) . '.</div>';
            // Redirect to a confirmation page or order history
            header("Location: user/ecommerce_orders.php?order_placed=true&order_id=" . $order_id);
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert alert-danger">Order placement failed: ' . $e->getMessage() . '</div>';
        }
    }
}

$conn->close();
?>

<style>
    .checkout-summary-table img { width: 60px; height: 60px; object-fit: contain; border-radius: 4px; }
    .address-form-group { margin-bottom: 1rem; }
    .address-form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
    .address-form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical; color: #000; }
    .payment-method-selection { margin-bottom: 1.5rem; }
    .payment-method-selection label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
    .payment-method-selection select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; color: #000; }
    .checkout-total { text-align: right; font-size: 1.5rem; font-weight: bold; margin-top: 20px; }
</style>

<div class="card">
    <h3>Checkout</h3>

    <?php echo $message; ?>

    <h4>Order Summary</h4>
    <table class="table checkout-summary-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cart_items as $item):
                // Ensure image path is correctly handled, defaulting to no_image.png if empty
                $image_path = !empty($item['image_path']) ? htmlspecialchars('uploads/products/' . $item['image_path']) : 'uploads/no_image.png';
            ?>
                <tr>
                    <td>
                        <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        <?php echo htmlspecialchars($item['name']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                    <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="checkout-total">
        Total: $<?php echo number_format($total_cart_amount, 2); ?>
    </div>

    <hr>

    <h4>Shipping & Billing Information</h4>
    <form action="checkout.php" method="POST">
        <div class="address-form-group">
            <label for="shipping_address">Shipping Address</label>
            <textarea id="shipping_address" name="shipping_address" class="form-control" rows="4" required><?php 
                echo htmlspecialchars(
                    ($user_profile['address_line1'] ?? '') . "\n" .
                    ($user_profile['address_line2'] ?? '') . "\n" .
                    ($user_profile['city'] ?? '') . ", " .
                    ($user_profile['state'] ?? '') . " " .
                    ($user_profile['postal_code'] ?? '') . "\n" .
                    ($user_profile['country_code'] ?? '')
                );
            ?></textarea>
        </div>

        <div class="address-form-group">
            <label for="billing_address">Billing Address (if different, otherwise copy shipping)</label>
            <textarea id="billing_address" name="billing_address" class="form-control" rows="4" required><?php 
                echo htmlspecialchars(
                    ($user_profile['address_line1'] ?? '') . "\n" .
                    ($user_profile['address_line2'] ?? '') . "\n" .
                    ($user_profile['city'] ?? '') . ", " .
                    ($user_profile['state'] ?? '') . " " .
                    ($user_profile['postal_code'] ?? '') . "\n" .
                    ($user_profile['country_code'] ?? '')
                );
            ?></textarea>
        </div>

        <div class="payment-method-selection">
            <label for="payment_method">Payment Method</label>
            <select id="payment_method" name="payment_method" class="form-control" required>
                <option value="">Select Payment Method</option>
                <?php foreach ($payment_methods as $pm): ?>
                    <option value="<?php echo htmlspecialchars($pm['slug']); ?>"><?php echo htmlspecialchars($pm['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" name="place_order" class="btn btn-primary">Place Order</button>
    </form>
</div>

<?php require_once 'footer.php'; ?>
