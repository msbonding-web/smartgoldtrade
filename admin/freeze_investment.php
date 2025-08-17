<?php
require_once '../db_connect.php';

// Check if investment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: investment_management.php?error=invalid_id");
    exit();
}

$investment_id = (int)$_GET['id'];

// Prepare and execute the update statement to set status to 'frozen'
$query = "UPDATE investments SET status = 'frozen' WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $investment_id);

if ($stmt->execute()) {
    // Success, redirect back to investment management page
    header("Location: investment_management.php?freeze=success");
} else {
    // Error, redirect back with an error message
    header("Location: investment_management.php?error=freeze_failed");
}

$stmt->close();
$conn->close();
exit();
?>
