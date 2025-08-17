<?php
require_once '../db_connect.php';

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirect back if no valid ID is provided
    header("Location: user_management.php");
    exit();
}

$user_id = (int)$_GET['id'];

// Prepare and execute the update statement
$query = "UPDATE users SET status = 'suspended' WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    // Success, redirect back to user management page
    header("Location: user_management.php?suspend=success");
} else {
    // Error, redirect back with an error message (optional)
    header("Location: user_management.php?suspend=error");
}

$stmt->close();
$conn->close();
exit();
?>
