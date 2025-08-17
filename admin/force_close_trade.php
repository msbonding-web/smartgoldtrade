<?php
require_once '../db_connect.php';

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: trading_platform.php?error=invalid_id");
    exit();
}

$order_id = (int)$_GET['id'];

// Prepare and execute the update statement to set status to 'closed'
// In a real application, this would involve complex PnL calculation, wallet updates, etc.
$query = "UPDATE trading_orders SET status = 'closed', closed_at = NOW() WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $order_id);

if ($stmt->execute()) {
    // Success, redirect back to trading platform page
    header("Location: trading_platform.php?force_close=success");
} else {
    // Error, redirect back with an error message
    header("Location: trading_platform.php?error=force_close_failed");
}

$stmt->close();
$conn->close();
exit();
?>
