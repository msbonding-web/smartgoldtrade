<?php
require_once 'header.php';
require_once '../db_connect.php';

$category_message = '';
$product_message = '';
$coupon_message = '';

// Handle Add New Category submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = $_POST['category_name'] ?? '';
    if (!empty($category_name)) {
        $category_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $category_name)));
        
        $check_query = "SELECT id FROM product_categories WHERE slug = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('s', $category_slug);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $category_message = "<div class=\"alert alert-danger\">Category with this slug already exists.</div>";
        } else {
            $insert_query = "INSERT INTO product_categories (name, slug, is_active) VALUES (?, ?, 1)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param('ss', $category_name, $category_slug);
            if ($insert_stmt->execute()) {
                $category_message = "<div class=\"alert alert-success\">Category '" . htmlspecialchars($category_name) . "' added successfully!</div>";
            } else {
                $category_message = "<div class=\"alert alert-danger\">Error adding category: " . $insert_stmt->error . "</div>";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    } else {
        $category_message = "<div class=\"alert alert-danger\">Category name cannot be empty.</div>";
    }
}

// Handle Add New Coupon submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code = $_POST['code'] ?? '';
    $type = $_POST['type'] ?? '';
    $value = $_POST['value'] ?? 0;
    $min_order_amount = $_POST['min_order_amount'] ?? 0;
    $usage_limit = $_POST['usage_limit'] ?? null;
    $expiry_date = $_POST['expiry_date'] ?? null;

    if (empty($code) || empty($type) || empty($value)) {
        $coupon_message = "<div class=\"alert alert-danger\">Code, Type, and Value are required for the coupon.</div>";
    } else {
        $check_query = "SELECT id FROM coupons WHERE code = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('s', $code);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $coupon_message = "<div class=\"alert alert-danger\">Coupon code already exists.</div>";
        } else {
            $insert_query = "INSERT INTO coupons (code, type, value, min_order_amount, usage_limit, expiry_date) VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param('ssddis', $code, $type, $value, $min_order_amount, $usage_limit, $expiry_date);
            if ($insert_stmt->execute()) {
                $coupon_message = "<div class=\"alert alert-success\">Coupon '" . htmlspecialchars($code) . "' added successfully!</div>";
            } else {
                $coupon_message = "<div class=\"alert alert-danger\">Error adding coupon: " . $insert_stmt->error . "</div>";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Fetch Products
$products_query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN product_categories c ON p.category_id = c.id ORDER BY p.created_at DESC";
$products_result = $conn->query($products_query);

// Fetch Categories
$categories_query = "SELECT * FROM product_categories ORDER BY name";
$categories_result = $conn->query($categories_query);

// Fetch Orders
$orders_query = "SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC";
$orders_result = $conn->query($orders_query);

// Fetch Coupons
$coupons_query = "SELECT * FROM coupons ORDER BY created_at DESC";
$coupons_result = $conn->query($coupons_query);

?>

<style>
    .tabs { display: flex; border-bottom: 2px solid #ccc; margin-bottom: 2rem; }
    .tab-link { padding: 1rem 1.5rem; cursor: pointer; border-bottom: 2px solid transparent; }
    .tab-link.active { border-color: var(--accent-color); font-weight: bold; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .alert-success { background-color: var(--success); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
    .alert-danger { background-color: var(--danger); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
</style>

<div class="card">
    <h3>Product & E-commerce Management</h3>

    <div class="tabs">
        <div class="tab-link active" onclick="openTab(event, 'products')">Products</div>
        <div class="tab-link" onclick="openTab(event, 'categories')">Categories</div>
        <div class="tab-link" onclick="openTab(event, 'orders')">Orders</div>
        <div class="tab-link" onclick="openTab(event, 'discounts')">Discounts</div>
    </div>

    <!-- Products Tab -->
    <div id="products" class="tab-content active">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;"><h4>All Products</h4><a href="add_product.php" class="btn btn-primary">Add New Product</a></div>
        <table class="table"><thead><tr><th>SKU</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($products_result && $products_result->num_rows > 0): ?>
                <?php while($product = $products_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['sku']); ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                        <td><?php echo htmlspecialchars($product['stock']); ?></td>
                        <td>
                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="delete_product.php?id=<?php echo $product['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?> 
            <?php else:
                echo "<tr><td colspan=\"6\" style=\"text-align: center;\">No products found.</td></tr>";
            endif; ?>
            </tbody></table>
    </div>

    <!-- Categories Tab -->
    <div id="categories" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;"><h4>Product Categories</h4></div>
        <?php echo $category_message; // Display category messages ?>
        <form action="ecommerce_management.php" method="POST" style="margin-bottom: 2rem;">
            <div class="form-group" style="display: flex; gap: 1rem;">
                <input type="text" name="category_name" class="form-control" placeholder="New Category Name" required>
                <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
            </div>
        </form>
        <table class="table"><thead><tr><th>Name</th><th>Slug</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                <?php while($category = $categories_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td><?php echo htmlspecialchars($category['slug']); ?></td>
                        <td>
                            <a href="edit_category.php?id=<?php echo $category['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="delete_category.php?id=<?php echo $category['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this category?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else:
                echo "<tr><td colspan=\"3\" style=\"text-align: center;\">No categories found.</td></tr>";
            endif; ?>
            </tbody></table>
    </div>

    <!-- Orders Tab -->
    <div id="orders" class="tab-content">
        <h4>Recent Orders</h4>
        <table class="table"><thead><tr><th>Order #</th><th>User</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                    <?php while($order = $orders_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                            <td><?php echo htmlspecialchars($order['username']); ?></td>
                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($order['status']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></td>
                            <td><a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">View Details</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else:
                    echo "<tr><td colspan=\"6\" style=\"text-align: center;\">No orders found.</td></tr>";
                endif; ?>
            </tbody></table>
    </div>

    <!-- Discounts Tab -->
    <div id="discounts" class="tab-content">
        <h4>Create New Coupon</h4>
        <?php echo $coupon_message; // Display coupon messages ?>
        <form action="ecommerce_management.php" method="POST" style="margin-bottom: 2rem;">
            <div class="grid-container" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="code">Coupon Code</label>
                    <input type="text" id="code" name="code" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="type">Discount Type</label>
                    <select id="type" name="type" class="form-control" required>
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed_amount">Fixed Amount ($)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="value">Discount Value</label>
                    <input type="number" step="0.01" id="value" name="value" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="min_order_amount">Minimum Order Amount</label>
                    <input type="number" step="0.01" id="min_order_amount" name="min_order_amount" class="form-control" value="0">
                </div>
                <div class="form-group">
                    <label for="usage_limit">Usage Limit (optional)</label>
                    <input type="number" id="usage_limit" name="usage_limit" class="form-control">
                </div>
                <div class="form-group">
                    <label for="expiry_date">Expiry Date (optional)</label>
                    <input type="date" id="expiry_date" name="expiry_date" class="form-control">
                </div>
            </div>
            <button type="submit" name="add_coupon" class="btn btn-primary">Add Coupon</button>
        </form>

        <h4 style="margin-top: 3rem;">Existing Coupons</h4>
        <table class="table"><thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Min Order</th><th>Usage Limit</th><th>Used</th><th>Expiry</th><th>Active</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($coupons_result && $coupons_result->num_rows > 0): ?>
                <?php while($coupon = $coupons_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($coupon['code']); ?></td>
                        <td><?php echo htmlspecialchars($coupon['type']); ?></td>
                        <td><?php echo htmlspecialchars($coupon['value']); ?></td>
                        <td>$<?php echo number_format($coupon['min_order_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($coupon['usage_limit'] ?? 'Unlimited'); ?></td>
                        <td><?php echo htmlspecialchars($coupon['used_count']); ?></td>
                        <td><?php echo $coupon['expiry_date'] ? date('Y-m-d', strtotime($coupon['expiry_date'])) : 'N/A'; ?></td>
                        <td><?php echo $coupon['is_active'] ? 'Yes' : 'No'; ?></td>
                        <td>
                            <a href="edit_coupon.php?id=<?php echo $coupon['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="delete_coupon.php?id=<?php echo $coupon['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this coupon?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else:
                echo "<tr><td colspan=\"9\" style=\"text-align: center;\">No coupons found.</td></tr>";
            endif; ?>
            </tbody></table>
    </div>

</div>

<script>
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) { tabcontent[i].style.display = "none"; }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) { tablinks[i].className = tablinks[i].className.replace(" active", ""); }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}

// Activate the correct tab on page load if a message is present
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('category_added') || urlParams.has('category_error')) {
        openTab(event, 'categories');
    } else if (urlParams.has('coupon_added') || urlParams.has('coupon_error')) {
        openTab(event, 'discounts');
    }
};
</script>

<?php
$conn->close();
require_once 'footer.php';
?>
