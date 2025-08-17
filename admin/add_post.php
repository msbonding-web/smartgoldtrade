<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';

// Fetch users for author dropdown (assuming admin users can be authors)
$authors_query = "SELECT id, username FROM users ORDER BY username";
$authors_result = $conn->query($authors_query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $excerpt = $_POST['excerpt'] ?? '';
    $content = $_POST['content'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    $author_id = $_POST['author_id'] ?? null;
    $published_at = $_POST['published_at'] ?? null;

    $feature_image_path = null;
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
            } else {
                $error_message = "Failed to upload feature image.";
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

        // Check if slug already exists
        $check_query = "SELECT id FROM blog_posts WHERE slug = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('s', $slug);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "A blog post with this title/slug already exists.";
        } else {
            $insert_query = "INSERT INTO blog_posts (slug, title, feature_image_path, excerpt, content, status, author_id, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            if ($insert_stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $insert_stmt->bind_param('ssssssis', $slug, $title, $feature_image_path, $excerpt, $content, $status, $author_id, $published_at);

            if ($insert_stmt->execute()) {
                $success_message = "Blog post '" . htmlspecialchars($title) . "' created successfully! You will be redirected shortly.";
                echo "<meta http-equiv='refresh' content='3;url=cms_management.php?tab=blog-posts'>";
            } else {
                $error_message = "Error creating blog post: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

$conn->close();
?>

<div class="card">
    <h3>Add New Blog Post</h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="add_post.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title">Post Title</label>
            <input type="text" id="title" name="title" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="feature_image">Feature Image</label>
            <input type="file" id="feature_image" name="feature_image" class="form-control">
        </div>
        <div class="form-group">
            <label for="excerpt">Excerpt (Short Summary)</label>
            <textarea id="excerpt" name="excerpt" class="form-control" rows="3"></textarea>
        </div>
        <div class="form-group">
            <label for="content">Content</label>
            <textarea id="content" name="content" class="form-control" rows="10" required></textarea>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="archived">Archived</option>
            </select>
        </div>
        <div class="form-group">
            <label for="author_id">Author</label>
            <select id="author_id" name="author_id" class="form-control" required>
                <option value="">Select Author</option>
                <?php while($author = $authors_result->fetch_assoc()): ?>
                    <option value="<?php echo $author['id']; ?>"><?php echo htmlspecialchars($author['username']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="published_at">Published At (optional)</label>
            <input type="datetime-local" id="published_at" name="published_at" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary">Save Post</button>
        <a href="cms_management.php?tab=blog-posts" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
