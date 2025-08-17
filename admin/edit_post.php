<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$post_id = $_GET['id'] ?? 0;

if (!is_numeric($post_id) || $post_id <= 0) {
    die("Invalid Post ID.");
}

// Fetch users for author dropdown
$authors_query = "SELECT id, username FROM users ORDER BY username";
$authors_result = $conn->query($authors_query);

// Handle form submission for updating the post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $excerpt = $_POST['excerpt'] ?? '';
    $content = $_POST['content'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    $author_id = $_POST['author_id'] ?? null;
    $published_at = $_POST['published_at'] ?? null;

    $feature_image_path = $post['feature_image_path']; // Keep existing path by default
    $upload_new_image = false;

    if (isset($_FILES['feature_image']) && $_FILES['feature_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/blog_features/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_ext = strtolower(pathinfo($_FILES['feature_image']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($file_ext, $allowed_ext)) {
            $new_file_name = uniqid('blog_feature_') . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;
            if (move_uploaded_file($_FILES['feature_image']['tmp_name'], $destination)) {
                $feature_image_path = $new_file_name;
                $upload_new_image = true;
            } else {
                $error_message = "Failed to upload new feature image.";
            }
        } else {
            $error_message = "Invalid feature image file type. Only JPG, JPEG, PNG, GIF are allowed.";
        }
    }

    if (empty($title) || empty($content) || empty($author_id)) {
        $error_message = "Title, Content, and Author are required.";
    } else if (!empty($error_message)) { // If file upload failed, stop here
        // Error message already set by file upload logic
    } else {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

        // Check if slug already exists (excluding current post)
        $check_query = "SELECT id FROM blog_posts WHERE slug = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('si', $slug, $post_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "A blog post with this title/slug already exists.";
        } else {
            $query_parts = [
                'slug = ?',
                'title = ?',
                'excerpt = ?',
                'content = ?',
                'status = ?',
                'author_id = ?',
                'published_at = ?'
            ];
            $bind_types = 'sssssis';
            $bind_values = [&$slug, &$title, &$excerpt, &$content, &$status, &$author_id, &$published_at];

            if ($upload_new_image) {
                $query_parts[] = 'feature_image_path = ?';
                $bind_types .= 's';
                $bind_values[] = &$feature_image_path;
            }

            $query = "UPDATE blog_posts SET " . implode(', ', $query_parts) . " WHERE id = ?";
            $bind_types .= 'i';
            $bind_values[] = &$post_id;

            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            // Use call_user_func_array to bind parameters dynamically
            call_user_func_array([$stmt, 'bind_param'], array_merge([$bind_types], $bind_values));

            if ($stmt->execute()) {
                // Delete old image if a new one was uploaded and an old one existed
                if ($upload_new_image && !empty($post['feature_image_path'])) {
                    $old_image_path = $upload_dir . $post['feature_image_path'];
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }
                $success_message = "Blog post '" . htmlspecialchars($title) . "' updated successfully! You will be redirected shortly.";
                echo "<meta http-equiv='refresh' content='3;url=cms_management.php?tab=blog-posts'>";
            } else {
                $error_message = "Error updating blog post: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Fetch current post details to populate the form
$query = "SELECT *, feature_image_path FROM blog_posts WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $post_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $post = $result->fetch_assoc();
} else {
    die("Blog post not found.");
}
$stmt->close();
$conn->close();
?>

<div class="card">
    <h3>Edit Blog Post: <?php echo htmlspecialchars($post['title']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_post.php?id=<?php echo $post_id; ?>" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title">Post Title</label>
            <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($post['title']); ?>" required>
        </div>
        <div class="form-group">
            <label for="feature_image">Feature Image</label>
            <input type="file" id="feature_image" name="feature_image" class="form-control">
            <?php if (!empty($post['feature_image_path'])): ?>
                <p style="margin-top: 5px;">Current Image: <img src="../uploads/blog_features/<?php echo htmlspecialchars($post['feature_image_path']); ?>" alt="Feature Image" style="max-width: 150px; height: auto;"></p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="excerpt">Excerpt (Short Summary)</label>
            <textarea id="excerpt" name="excerpt" class="form-control" rows="3"><?php echo htmlspecialchars($post['excerpt']); ?></textarea>
        </div>
        <div class="form-group">
            <label for="content">Content</label>
            <textarea id="content" name="content" class="form-control" rows="10" required><?php echo htmlspecialchars($post['content']); ?></textarea>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="draft" <?php if($post['status'] === 'draft') echo 'selected'; ?>>Draft</option>
                <option value="published" <?php if($post['status'] === 'published') echo 'selected'; ?>>Published</option>
                <option value="archived" <?php if($post['status'] === 'archived') echo 'selected'; ?>>Archived</option>
            </select>
        </div>
        <div class="form-group">
            <label for="author_id">Author</label>
            <select id="author_id" name="author_id" class="form-control" required>
                <option value="">Select Author</option>
                <?php while($author = $authors_result->fetch_assoc()): ?>
                    <option value="<?php echo $author['id']; ?>" <?php if($post['author_id'] == $author['id']) echo 'selected'; ?>><?php echo htmlspecialchars($author['username']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="published_at">Published At (optional)</label>
            <input type="datetime-local" id="published_at" name="published_at" class="form-control" value="<?php echo htmlspecialchars(str_replace(' ', 'T', $post['published_at'] ?? '')); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Update Post</button>
        <a href="cms_management.php?tab=blog-posts" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>