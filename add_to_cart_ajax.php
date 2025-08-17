<?php
session_start();
require_once 'db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to add items to your cart.', 'redirect' => 'login.php']);
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'] ?? 0;
$quantity = $_POST['quantity'] ?? 1;

if ($product_id <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product or quantity.']);
    exit();
}

// Check product stock
$stock_stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
$stock_stmt->bind_param("i", $product_id);
$stock_stmt->execute();
$stock_result = $stock_stmt->get_result();
$product_stock = $stock_result->fetch_assoc();
$stock_stmt->close();

if (!$product_stock || $quantity > $product_stock['stock']) {
    echo json_encode(['success' => false, 'message' => 'Not enough stock available.']);
    exit();
}


// Find or create a cart for the user
$cart_id = null;
$cart_stmt = $conn->prepare("SELECT id FROM carts WHERE user_id = ?");
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
if ($cart_row = $cart_result->fetch_assoc()) {
    $cart_id = $cart_row['id'];
} else {
    $create_cart_stmt = $conn->prepare("INSERT INTO carts (user_id) VALUES (?)");
    $create_cart_stmt->bind_param("i", $user_id);
    $create_cart_stmt->execute();
    $cart_id = $conn->insert_id;
    $create_cart_stmt->close();
}
$cart_stmt->close();

// Add or update item in cart_items
$item_stmt = $conn->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
$item_stmt->bind_param("iii", $cart_id, $product_id, $quantity);

// --- পরিবর্তন: সফলতার যাচাই পদ্ধতি পরিবর্তন করা হয়েছে ---
$item_stmt->execute(); // প্রথমে কোয়েরিটি রান করা হচ্ছে

// এখন কোনো এরর আছে কিনা তা সরাসরি যাচাই করা হচ্ছে
if (empty($item_stmt->error)) {
    echo json_encode(['success' => true, 'message' => 'Product added to cart successfully!']);
} else {
    $error_message = 'An error occurred. DB Error: ' . $item_stmt->error;
    echo json_encode(['success' => false, 'message' => $error_message]);
}

$item_stmt->close();
$conn->close();
?>
