<?php
require_once '../db_connect.php';

// Check if popup ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: cms_management.php?tab=popups&error=invalid_id");
    exit();
}

$popup_id = (int)$_GET['id'];

// Prepare and execute the delete statement
$query = "DELETE FROM popups WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $popup_id);

if ($stmt->execute()) {
    // Success, redirect back to popups management page
    header("Location: cms_management.php?tab=popups&delete=success");
} else {
    // Error, redirect back with an error message
    header("Location: cms_management.php?tab=popups&error=delete_failed");
}

$stmt->close();
$conn->close();
exit();
?>
