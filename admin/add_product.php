<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';

// Fetch categories for the dropdown
$categories_query = "SELECT id, name FROM product_categories ORDER BY name";
$categories_result = $conn->query($categories_query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data
    $name = $_POST['name'] ?? '';
    $sku = $_POST['sku'] ?? '';
    $category_id = $_POST['category_id'] ?? null;
    $price = $_POST['price'] ?? 0;
    $stock = $_POST['stock'] ?? 0;
    $description = $_POST['description'] ?? '';

    // File upload variables
    $file_name = $_FILES['product_image']['name'] ?? '';
    $file_tmp_name = $_FILES['product_image']['tmp_name'] ?? '';
    $file_error = $_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE;
    $file_size = $_FILES['product_image']['size'] ?? 0;

    // Simple validation
    if (empty($name) || empty($sku) || empty($price) || empty($stock) || empty($category_id)) {
        $error_message = "Please fill in all required fields: Name, SKU, Category, Price, Stock.";
    } else {
        // Generate slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

        // Assuming currency_id 1 is USD for now
        $currency_id = 1; 

        // Start transaction
        $conn->begin_transaction();

        try {
            $query = "INSERT INTO products (name, slug, sku, category_id, price, currency_id, stock, description, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                'sssiddis',
                $name, $slug, $sku, $category_id, $price, $currency_id, $stock, $description
            );

            if (!$stmt->execute()) {
                throw new Exception("Error creating product: " . $stmt->error);
            }
            $product_id = $conn->insert_id;
            $stmt->close();

            // Handle image upload
            if ($file_error === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/products/'; // IMPORTANT: Create this directory and ensure it's writable
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

                if (!in_array($file_ext, $allowed_ext)) {
                    throw new Exception("Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.");
                }
                if ($file_size > 5 * 1024 * 1024) { // 5MB max size
                    throw new Exception("File size exceeds limit (5MB).");
                }

                $new_file_name = uniqid('prod_') . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;

                if (!move_uploaded_file($file_tmp_name, $destination)) {
                    throw new Exception("Failed to upload image.");
                }

                // Insert image path into product_images table
                $image_query = "INSERT INTO product_images (product_id, path, display_order) VALUES (?, ?, 0)";
                $image_stmt = $conn->prepare($image_query);
                $image_stmt->bind_param('is', $product_id, $new_file_name);
                if (!$image_stmt->execute()) {
                    throw new Exception("Error saving image path: " . $image_stmt->error);
                }
                $image_stmt->close();
            } else if ($file_error !== UPLOAD_ERR_NO_FILE) {
                throw new Exception("File upload error: " . $file_error);
            }

            $conn->commit();
            $success_message = "New product '" . htmlspecialchars($name) . "' created successfully! You will be redirected shortly.";
            echo "<meta http-equiv='refresh' content='3;url=ecommerce_management.php'>";

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

$conn->close();
?>

<div class="card">
    <h3>Add New Product</h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="add_product.php" method="POST" enctype="multipart/form-data">
        <div class="grid-container" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label for="name">Product Name</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="sku">SKU</label>
                <input type="text" id="sku" name="sku" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <option value="">Select Category</option>
                    <?php while($category = $categories_result->fetch_assoc()): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="price">Price</label>
                <input type="number" step="0.01" id="price" name="price" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="stock">Stock</label>
                <input type="number" id="stock" name="stock" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="product_image">Product Image</label>
                <input type="file" id="product_image" name="product_image" class="form-control">
            </div>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="form-control" rows="5"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Save Product</button>
        <a href="ecommerce_management.php" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>