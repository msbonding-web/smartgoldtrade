<?php
require_once '../db_connect.php';

// Check if category ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ecommerce_management.php?tab=categories&error=invalid_id");
    exit();
}

$category_id = (int)$_GET['id'];

// Prepare and execute the delete statement
$query = "DELETE FROM product_categories WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $category_id);

if ($stmt->execute()) {
    // Success, redirect back to category management page
    header("Location: ecommerce_management.php?tab=categories&delete=success");
} else {
    // Error, redirect back with an error message
    header("Location: ecommerce_management.php?tab=categories&error=delete_failed");
}

$stmt->close();
$conn->close();
exit();
?>
