<?php
require_once '../db_connect.php';

// Check if FAQ ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: cms_management.php?tab=faq&error=invalid_id");
    exit();
}

$faq_id = (int)$_GET['id'];

// Prepare and execute the delete statement
$query = "DELETE FROM faqs WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $faq_id);

if ($stmt->execute()) {
    // Success, redirect back to FAQ management page
    header("Location: cms_management.php?tab=faq&delete=success");
} else {
    // Error, redirect back with an error message
    header("Location: cms_management.php?tab=faq&error=delete_failed");
}

$stmt->close();
$conn->close();
exit();
?>
