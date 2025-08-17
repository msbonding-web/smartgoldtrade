<?php
require_once '../db_connect.php';

// Check if template ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: notification_management.php?tab=templates&error=invalid_id");
    exit();
}

$template_id = (int)$_GET['id'];

// Prepare and execute the delete statement
$query = "DELETE FROM notification_templates WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $template_id);

if ($stmt->execute()) {
    // Success, redirect back to templates management page
    header("Location: notification_management.php?tab=templates&delete=success");
} else {
    // Error, redirect back with an error message
    header("Location: notification_management.php?tab=templates&error=delete_failed");
}

$stmt->close();
$conn->close();
exit();
?>
