<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$banner_id = $_GET['id'] ?? 0;

if (!is_numeric($banner_id) || $banner_id <= 0) {
    die("Invalid Banner ID.");
}

// Handle form submission for updating the banner
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $link_url = $_POST['link_url'] ?? '';
    $display_order = $_POST['display_order'] ?? 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $file_name = $_FILES['banner_image']['name'] ?? '';
    $file_tmp_name = $_FILES['banner_image']['tmp_name'] ?? '';
    $file_error = $_FILES['banner_image']['error'] ?? UPLOAD_ERR_NO_FILE;

    // Start transaction
    $conn->begin_transaction();

    try {
        $image_path_update = '';
        $image_path_param = '';
        $types = 'ssii'; // Default types for title, link_url, display_order, is_active
        $params = [&$title, &$link_url, &$display_order, &$is_active];

        // Handle image upload if a new one is provided
        if ($file_error === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/banners/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($file_ext, $allowed_ext)) {
                throw new Exception("Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.");
            }
            if ($_FILES['banner_image']['size'] > 5 * 1024 * 1024) { // 5MB max size
                throw new Exception("File size exceeds limit (5MB).");
            }

            $new_file_name = uniqid('banner_') . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;

            if (!move_uploaded_file($file_tmp_name, $destination)) {
                throw new Exception("Failed to upload new image.");
            }

            // Delete old image if exists
            $old_image_query = "SELECT image_path FROM banners WHERE id = ?";
            $old_image_stmt = $conn->prepare($old_image_query);
            $old_image_stmt->bind_param('i', $banner_id);
            $old_image_stmt->execute();
            $old_image_path = $old_image_stmt->get_result()->fetch_assoc()['image_path'] ?? '';
            $old_image_stmt->close();

            if (!empty($old_image_path) && file_exists($upload_dir . $old_image_path)) {
                unlink($upload_dir . $old_image_path);
            }

            $image_path_update = ', image_path = ?';
            $image_path_param = $new_file_name;
            $types = 'ssssii'; // Add 's' for image_path
            array_splice($params, 1, 0, [&$image_path_param]); // Insert image_path_param after title

        } else if ($file_error !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("File upload error: " . $file_error);
        }

        $query = "UPDATE banners SET title = ?, link_url = ?, display_order = ?, is_active = ?" . $image_path_update . " WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        // Dynamically bind parameters based on whether image_path is updated
        if (!empty($image_path_update)) {
            $stmt->bind_param('ssiisi', $title, $image_path_param, $link_url, $display_order, $is_active, $banner_id);
        } else {
            $stmt->bind_param('ssiii', $title, $link_url, $display_order, $is_active, $banner_id);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error updating banner: " . $stmt->error);
        }
        $stmt->close();

        $conn->commit();
        $success_message = "Banner updated successfully! You will be redirected shortly.";
        echo "<meta http-equiv='refresh' content='3;url=cms_management.php?tab=banners'>";

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// Fetch current banner details to populate the form
$query = "SELECT * FROM banners WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $banner_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $banner = $result->fetch_assoc();
} else {
    die("Banner not found.");
}
$stmt->close();
$conn->close();
?>

<div class="card">
    <h3>Edit Banner: <?php echo htmlspecialchars($banner['title']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_banner.php?id=<?php echo $banner_id; ?>" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title">Title (optional)</label>
            <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($banner['title']); ?>">
        </div>
        <div class="form-group">
            <label for="banner_image">Banner Image (Leave blank to keep current)</label>
            <input type="file" id="banner_image" name="banner_image" class="form-control">
            <?php if (!empty($banner['image_path'])): ?>
                <p style="margin-top: 0.5rem;">Current Image:</p>
                <img src="../uploads/banners/<?php echo htmlspecialchars($banner['image_path']); ?>" alt="Current Banner" style="max-width: 200px; height: auto; border: 1px solid #ddd;">
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="link_url">Link URL (optional)</label>
            <input type="url" id="link_url" name="link_url" class="form-control" value="<?php echo htmlspecialchars($banner['link_url']); ?>">
        </div>
        <div class="form-group">
            <label for="display_order">Display Order</label>
            <input type="number" id="display_order" name="display_order" class="form-control" value="<?php echo htmlspecialchars($banner['display_order']); ?>">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_active" value="1" <?php if($banner['is_active']) echo 'checked'; ?>> Is Active</label>
        </div>
        <button type="submit" class="btn btn-primary">Update Banner</button>
        <a href="cms_management.php?tab=banners" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
