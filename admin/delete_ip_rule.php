<?php
require_once '../db_connect.php';

// Check if IP Rule ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: system_settings.php?tab=ip-control&error=invalid_id");
    exit();
}

$rule_id = (int)$_GET['id'];

// Prepare and execute the delete statement
$query = "DELETE FROM ip_rules WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $rule_id);

if ($stmt->execute()) {
    // Success, redirect back to IP Control management page
    header("Location: system_settings.php?tab=ip-control&delete=success");
} else {
    // Error, redirect back with an error message
    header("Location: system_settings.php?tab=ip-control&error=delete_failed");
}

$stmt->close();
$conn->close();
exit();
?>
