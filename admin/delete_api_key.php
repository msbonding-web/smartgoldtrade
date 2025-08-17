<?php
require_once '../db_connect.php';

// Check if API Key ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: system_settings.php?tab=api-access&error=invalid_id");
    exit();
}

$api_key_id = (int)$_GET['id'];

// Prepare and execute the delete statement
$query = "DELETE FROM api_keys WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $api_key_id);

if ($stmt->execute()) {
    // Success, redirect back to API Access management page
    header("Location: system_settings.php?tab=api-access&delete=success");
} else {
    // Error, redirect back with an error message
    header("Location: system_settings.php?tab=api-access&error=delete_failed");
}

$stmt->close();
$conn->close();
exit();
?>
