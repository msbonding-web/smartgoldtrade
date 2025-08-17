<?php
require_once '../db_connect.php';

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ecommerce_management.php?error=invalid_id");
    exit();
}

$product_id = (int)$_GET['id'];

// Start transaction
$conn->begin_transaction();

try {
    // 1. Get image paths and delete image files
    $images_query = "SELECT path FROM product_images WHERE product_id = ?";
    $images_stmt = $conn->prepare($images_query);
    $images_stmt->bind_param('i', $product_id);
    $images_stmt->execute();
    $images_result = $images_stmt->get_result();

    $upload_dir = '../uploads/products/';
    while ($image = $images_result->fetch_assoc()) {
        $file_path = $upload_dir . basename($image['path']);
        if (file_exists($file_path)) {
            unlink($file_path); // Delete the actual file
        }
    }
    $images_stmt->close();

    // 2. Delete records from product_images table
    $delete_images_query = "DELETE FROM product_images WHERE product_id = ?";
    $delete_images_stmt = $conn->prepare($delete_images_query);
    $delete_images_stmt->bind_param('i', $product_id);
    if (!$delete_images_stmt->execute()) {
        throw new Exception("Error deleting product images: " . $delete_images_stmt->error);
    }
    $delete_images_stmt->close();

    // 3. Delete record from products table
    $delete_product_query = "DELETE FROM products WHERE id = ?";
    $delete_product_stmt = $conn->prepare($delete_product_query);
    $delete_product_stmt->bind_param('i', $product_id);
    if (!$delete_product_stmt->execute()) {
        throw new Exception("Error deleting product: " . $delete_product_stmt->error);
    }
    $delete_product_stmt->close();

    $conn->commit();
    header("Location: ecommerce_management.php?product_delete=success");

} catch (Exception $e) {
    $conn->rollback();
    header("Location: ecommerce_management.php?product_delete=error&message=" . urlencode($e->getMessage()));
}

$conn->close();
exit();
?>
