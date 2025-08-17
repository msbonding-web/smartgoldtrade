<?php
require_once '../db_connect.php';

// Check if plan ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: investment_management.php?error=invalid_id");
    exit();
}

$plan_id = (int)$_GET['id'];

// Prepare and execute the update statement to set is_active to 0
$query = "UPDATE investment_plans SET is_active = 0 WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $plan_id);

if ($stmt->execute()) {
    // Success, redirect back to investment management page
    header("Location: investment_management.php?deactivate=success");
} else {
    // Error, redirect back with an error message
    header("Location: investment_management.php?error=deactivation_failed");
}

$stmt->close();
$conn->close();
exit();
?>
