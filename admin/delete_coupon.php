<?php
require_once '../db_connect.php';

// Check if coupon ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ecommerce_management.php?tab=discounts&error=invalid_id");
    exit();
}

$coupon_id = (int)$_GET['id'];

// Prepare and execute the delete statement
$query = "DELETE FROM coupons WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $coupon_id);

if ($stmt->execute()) {
    // Success, redirect back to discounts management page
    header("Location: ecommerce_management.php?tab=discounts&delete=success");
} else {
    // Error, redirect back with an error message
    header("Location: ecommerce_management.php?tab=discounts&error=delete_failed");
}

$stmt->close();
$conn->close();
exit();
?>
