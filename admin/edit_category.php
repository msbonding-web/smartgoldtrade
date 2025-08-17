<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$category_id = $_GET['id'] ?? 0;

if (!is_numeric($category_id) || $category_id <= 0) {
    die("Invalid Category ID.");
}

// Handle form submission for updating the category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

    if (empty($name)) {
        $error_message = "Category name cannot be empty.";
    } else {
        // Check for duplicate slug (excluding current category)
        $check_query = "SELECT id FROM product_categories WHERE slug = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('si', $slug, $category_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "Category with this slug already exists.";
        } else {
            $query = "UPDATE product_categories SET name = ?, slug = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssi', $name, $slug, $category_id);

            if ($stmt->execute()) {
                $success_message = "Category updated successfully! You will be redirected shortly.";
                echo "<meta http-equiv='refresh' content='3;url=ecommerce_management.php?tab=categories'>";
            } else {
                $error_message = "Error updating category: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Fetch current category details to populate the form
$query = "SELECT id, name, slug FROM product_categories WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $category_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $category = $result->fetch_assoc();
} else {
    die("Category not found.");
}
$stmt->close();
$conn->close();
?>

<div class="card">
    <h3>Edit Category: <?php echo htmlspecialchars($category['name']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_category.php?id=<?php echo $category_id; ?>" method="POST">
        <div class="form-group">
            <label for="name">Category Name</label>
            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($category['name']); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Category</button>
        <a href="ecommerce_management.php?tab=categories" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
