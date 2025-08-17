<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $content = $_POST['content'] ?? '';
    $status = $_POST['status'] ?? 'draft';

    if (empty($title) || empty($slug) || empty($content)) {
        $error_message = "Title, Slug, and Content are required.";
    } else {
        // Check if slug already exists
        $check_query = "SELECT id FROM cms_pages WHERE slug = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('s', $slug);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "A page with this slug already exists.";
        } else {
            $insert_query = "INSERT INTO cms_pages (slug, title, content, status) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param('ssss', $slug, $title, $content, $status);

            if ($insert_stmt->execute()) {
                $success_message = "Page '" . htmlspecialchars($title) . "' created successfully! You will be redirected shortly.";
                echo "<meta http-equiv='refresh' content='3;url=cms_management.php?tab=pages'>";
            } else {
                $error_message = "Error creating page: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

$conn->close();
?>

<div class="card">
    <h3>Add New CMS Page</h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="add_page.php" method="POST">
        <div class="form-group">
            <label for="title">Page Title</label>
            <input type="text" id="title" name="title" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="slug">Page Slug (e.g., about-us)</label>
            <input type="text" id="slug" name="slug" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="content">Content</label>
            <textarea id="content" name="content" class="form-control" rows="10"></textarea>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="archived">Archived</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Save Page</button>
        <a href="cms_management.php?tab=pages" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
