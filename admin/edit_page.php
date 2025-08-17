<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$page_id = $_GET['id'] ?? 0;

if (!is_numeric($page_id) || $page_id <= 0) {
    die("Invalid Page ID.");
}

// Handle form submission for updating the page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $content = $_POST['content'] ?? '';
    $status = $_POST['status'] ?? 'draft';

    if (empty($title) || empty($slug) || empty($content)) {
        $error_message = "Title, Slug, and Content are required.";
    } else {
        // Check if slug already exists (excluding current page)
        $check_query = "SELECT id FROM cms_pages WHERE slug = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('si', $slug, $page_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "A page with this slug already exists.";
        } else {
            $query = "UPDATE cms_pages SET slug = ?, title = ?, content = ?, status = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssssi', $slug, $title, $content, $status, $page_id);

            if ($stmt->execute()) {
                $success_message = "Page '" . htmlspecialchars($title) . "' updated successfully! You will be redirected shortly.";
                echo "<meta http-equiv='refresh' content='3;url=cms_management.php?tab=pages'>";
            } else {
                $error_message = "Error updating page: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Fetch current page details to populate the form
$query = "SELECT * FROM cms_pages WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $page_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $page = $result->fetch_assoc();
} else {
    die("Page not found.");
}
$stmt->close();
$conn->close();
?>

<div class="card">
    <h3>Edit CMS Page: <?php echo htmlspecialchars($page['title']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_page.php?id=<?php echo $page_id; ?>" method="POST">
        <div class="form-group">
            <label for="title">Page Title</label>
            <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($page['title']); ?>" required>
        </div>
        <div class="form-group">
            <label for="slug">Page Slug (e.g., about-us)</label>
            <input type="text" id="slug" name="slug" class="form-control" value="<?php echo htmlspecialchars($page['slug']); ?>" required>
        </div>
        <div class="form-group">
            <label for="content">Content</label>
            <textarea id="content" name="content" class="form-control" rows="10"><?php echo htmlspecialchars($page['content']); ?></textarea>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="draft" <?php if($page['status'] === 'draft') echo 'selected'; ?>>Draft</option>
                <option value="published" <?php if($page['status'] === 'published') echo 'selected'; ?>>Published</option>
                <option value="archived" <?php if($page['status'] === 'archived') echo 'selected'; ?>>Archived</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Update Page</button>
        <a href="cms_management.php?tab=pages" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
