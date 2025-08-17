<?php
require_once '../db_connect.php';

// Check if banner ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: cms_management.php?tab=banners&error=invalid_id");
    exit();
}

$banner_id = (int)$_GET['id'];

// Start transaction
$conn->begin_transaction();

try {
    // 1. Get image path and delete image file
    $image_query = "SELECT image_path FROM banners WHERE id = ?";
    $image_stmt = $conn->prepare($image_query);
    $image_stmt->bind_param('i', $banner_id);
    $image_stmt->execute();
    $image_path = $image_stmt->get_result()->fetch_assoc()['image_path'] ?? '';
    $image_stmt->close();

    $upload_dir = '../uploads/banners/';
    if (!empty($image_path) && file_exists($upload_dir . $image_path)) {
        unlink($upload_dir . $image_path); // Delete the actual file
    }

    // 2. Delete record from banners table
    $delete_query = "DELETE FROM banners WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param('i', $banner_id);
    if (!$delete_stmt->execute()) {
        throw new Exception("Error deleting banner: " . $delete_stmt->error);
    }
    $delete_stmt->close();

    $conn->commit();
    header("Location: cms_management.php?tab=banners&delete=success");

} catch (Exception $e) {
    $conn->rollback();
    header("Location: cms_management.php?tab=banners&error=delete_failed&message=" . urlencode($e->getMessage()));
}

$conn->close();
exit();
?>
