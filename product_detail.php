<?php
session_start();
require_once 'header.php';
require_once 'db_connect.php';

$message = ''; // Initialize message variable

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    header("Location: gold_shop.php");
    exit();
}

// Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $add_quantity = $_POST['quantity'] ?? 1;
    $add_product_id = $_POST['product_id'] ?? 0;

    // Basic validation
    if (!is_numeric($add_quantity) || $add_quantity <= 0) {
        $message = '<div class="alert alert-danger">Invalid quantity.</div>';
    } else {
        $conn->begin_transaction();
        try {
            // 1. Get or create user's cart
            $cart_id = null;
            $cart_stmt = $conn->prepare("SELECT id FROM carts WHERE user_id = ?");
            $cart_stmt->bind_param('i', $user_id);
            $cart_stmt->execute();
            $cart_result = $cart_stmt->get_result();
            if ($row = $cart_result->fetch_assoc()) {
                $cart_id = $row['id'];
            } else {
                // Create new cart
                $insert_cart_stmt = $conn->prepare("INSERT INTO carts (user_id) VALUES (?)");
                $insert_cart_stmt->bind_param('i', $user_id);
                if (!$insert_cart_stmt->execute()) {
                    throw new Exception("Error creating cart: " . $insert_cart_stmt->error);
                }
                $cart_id = $conn->insert_id;
                $insert_cart_stmt->close();
            }
            $cart_stmt->close();

            // 2. Check if product already in cart_items
            $cart_item_stmt = $conn->prepare("SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
            $cart_item_stmt->bind_param('ii', $cart_id, $add_product_id);
            $cart_item_stmt->execute();
            $cart_item_result = $cart_item_stmt->get_result();

            if ($existing_item = $cart_item_result->fetch_assoc()) {
                // Product exists, update quantity
                $new_quantity = $existing_item['quantity'] + $add_quantity;
                $update_item_stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
                $update_item_stmt->bind_param('iii', $new_quantity, $cart_id, $add_product_id);
                if (!$update_item_stmt->execute()) {
                    throw new Exception("Error updating cart item quantity: " . $update_item_stmt->error);
                }
                $update_item_stmt->close();
            } else {
                // Product does not exist, insert new item
                $insert_item_stmt = $conn->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
                $insert_item_stmt->bind_param('iii', $cart_id, $add_product_id, $add_quantity);
                if (!$insert_item_stmt->execute()) {
                    throw new Exception("Error adding product to cart: " . $insert_item_stmt->error);
                }
                $insert_item_stmt->close();
            }
            $cart_item_stmt->close();

            $conn->commit();
            $message = '<div class="alert alert-success">Product added to cart successfully!</div>';

        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert alert-danger">Error adding product to cart: ' . $e->getMessage() . '</div>';
        }
    }
}

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    header("Location: gold_shop.php");
    exit();
}

// Fetch product details
$product_query = "SELECT p.id, p.name, p.description, p.price, p.stock, p.currency_id, p.weight_gram, p.attributes, p.is_active, p.created_at, p.updated_at, COALESCE(pi.path, p.image_path) AS image_path, c.name AS category_name FROM products p JOIN product_categories c ON p.category_id = c.id LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.display_order = 0 WHERE p.id = ?";
$stmt = $conn->prepare($product_query);
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product_result = $stmt->get_result();
$product = $product_result->fetch_assoc();
$stmt->close();

if (!$product) {
    echo "<div class=\"card\"><p>Product not found.</p><a href=\"gold_shop.php\" class=\"btn\">Back to Shop</a></div>";
    require_once 'footer.php';
    exit();
}

$conn->close();
?>


<style>
    .product-detail-container { display: flex; flex-wrap: wrap; gap: 30px; align-items: flex-start; }
    .product-detail-image { flex: 1; min-width: 300px; max-width: 500px; }
    .product-detail-image img { max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    .product-detail-info { flex: 2; min-width: 300px; color: #fff; } /* Added color: #fff; */
    .product-detail-info h3 { font-size: 2rem; margin-bottom: 10px; color: #fff; } /* Added color: #fff; */
    .product-detail-info .category { font-size: 0.9rem; color: #777; margin-bottom: 15px; color: #ccc; } /* Adjusted color */
    .product-detail-info .price { font-size: 2.5rem; font-weight: bold; color: var(--primary-color); margin-bottom: 20px; }
    .product-detail-info .description { font-size: 1.1rem; line-height: 1.6; margin-bottom: 20px; color: #eee; } /* Added color: #eee; */
    .product-detail-info .stock { font-size: 1rem; color: #333; margin-bottom: 20px; color: #ddd; } /* Adjusted color */
    .add-to-cart-form { display: flex; gap: 10px; align-items: center; }
    .add-to-cart-form input[type="number"] { width: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 5px; color: #000; } /* Added color: #000; */
    .add-to-cart-form button { padding: 10px 20px; background-color: var(--accent-color); color: white; border: none; border-radius: 5px; cursor: pointer; }
</style>

<div class="card">
    <?php echo $message; ?>
    <?php echo $message; ?>
    <div class="product-detail-container">
        <div class="product-detail-image">
            <img src="<?php echo !empty($product['image_path']) ? htmlspecialchars('uploads/products/' . $product['image_path']) : 'uploads/no_image.png'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
        </div>
        <div class="product-detail-info">
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
            <p class="category">Category: <?php echo htmlspecialchars($product['category_name']); ?></p>
            <p class="price">$<?php echo number_format($product['price'], 2); ?></p>
            <p class="description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            <p class="stock">Stock: <?php echo htmlspecialchars($product['stock']); ?> units available</p>

            <form class="add-to-cart-form" action="product_detail.php?id=<?php echo $product_id; ?>" method="POST">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                <input type="number" name="quantity" value="1" min="1" max="<?php echo htmlspecialchars($product['stock']); ?>">
                <button type="submit" name="add_to_cart">Add to Cart</button>
            </form>

            <a href="gold_shop.php" class="btn" style="margin-top: 20px; display: inline-block; background-color: var(--gray);">Back to Shop</a>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
