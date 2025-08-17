<?php
session_start();
require_once 'header.php';
require_once 'db_connect.php';

$search_query = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

// Fetch categories for filter sidebar
$categories_query = "SELECT id, name FROM product_categories ORDER BY name ASC";
$categories_result = $conn->query($categories_query);

// Fetch products
$products_query = "SELECT p.id, p.name, p.description, p.price, p.stock, COALESCE(pi.path, p.image_path) AS image_path, c.name AS category_name, c.id AS category_id 
                   FROM products p 
                   JOIN product_categories c ON p.category_id = c.id 
                   LEFT JOIN (SELECT product_id, path FROM product_images WHERE display_order = 0) pi ON p.id = pi.product_id 
                   WHERE p.is_active = 1";

$params = [];
$types = '';

if (!empty($search_query)) {
    $products_query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_param = '%' . $search_query . '%';
    array_push($params, $search_param, $search_param);
    $types .= 'ss';
}

if (!empty($category_filter)) {
    $products_query .= " AND p.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

$products_query .= " ORDER BY p.name ASC";

$stmt = $conn->prepare($products_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products_result = $stmt->get_result();

?>

<style>
    /* Gold Shop Specific Styles */
    .shop-container { display: flex; padding: 20px; gap: 30px; }
    .sidebar { width: 250px; flex-shrink: 0; }
    .main-content { flex-grow: 1; }
    .category-list { list-style: none; padding: 0; margin: 0; background: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .category-list h4 { margin-top: 0; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
    .category-list li a { display: block; padding: 10px 15px; text-decoration: none; color: #333; border-radius: 5px; transition: background-color 0.2s; }
    .category-list li a:hover { background-color: #f4f4f4; }
    .category-list li a.active { background-color: #D4AF37; color: #fff; font-weight: bold; }
    .filter-section { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; gap: 15px; align-items: center; }
    .filter-section .form-group { margin-bottom: 0; flex-grow: 1; }
    .filter-section .form-control { height: 45px; }
    .filter-section .btn { height: 45px; }
    
    .product-grid { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr);
        gap: 20px; 
    }
    @media (max-width: 992px) { .product-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 768px) { .product-grid { grid-template-columns: 1fr; } .shop-container { flex-direction: column; } .sidebar { width: 100%; } }

    .product-card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.08); display: flex; flex-direction: column; transition: transform 0.2s; }
    .product-card:hover { transform: translateY(-5px); }
    .product-card img { width: 100%; height: 200px; object-fit: cover; }
    .product-card-content { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
    
    /* --- পরিবর্তন: টাইটেলের রঙ এবং ফন্ট সাইজ --- */
    .product-card h4 { 
        margin-top: 0; 
        margin-bottom: 10px; 
        font-size: 1.2rem; /* একটু বড় করা হয়েছে */
        color: #333; /* রঙ কালো করা হয়েছে */
        font-weight: 600;
    }
    
    /* --- পরিবর্তন: ক্যাটাগরির রঙ এবং ফন্ট স্টাইল --- */
    .product-card .category { 
        font-size: 0.9rem; 
        color: #555; /* রঙ গাঢ় করা হয়েছে */
        margin-bottom: 15px; 
        font-weight: 600; /* বোল্ড করা হয়েছে */
    }

    .product-card .price { font-size: 1.25rem; font-weight: bold; color: #D4AF37; margin-top: auto; margin-bottom: 15px; }
    .product-actions { display: flex; gap: 10px; align-items: center; }
    .quantity-input { width: 70px !important; text-align: center; height: 38px; }
    .product-actions .btn { flex-grow: 1; padding: 8px 12px; font-size: 14px; }
    .btn-gold { background-color: #D4AF37; color: #fff; border: none; }
    .btn-dark { background-color: #343a40; color: #fff; border: none; }
    .toast-message { position: fixed; bottom: 20px; right: 20px; background-color: #333; color: #fff; padding: 15px 25px; border-radius: 5px; z-index: 1000; opacity: 0; transition: opacity 0.5s; }
    .toast-message.show { opacity: 1; }
</style>

<div class="shop-container">
    <aside class="sidebar">
        <ul class="category-list">
            <h4>Categories</h4>
            <li><a href="gold_shop.php" class="<?php echo empty($category_filter) ? 'active' : ''; ?>">All Categories</a></li>
            <?php while($category = $categories_result->fetch_assoc()): ?>
                <li>
                    <a href="gold_shop.php?category=<?php echo $category['id']; ?>" class="<?php echo ($category_filter == $category['id']) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </a>
                </li>
            <?php endwhile; ?>
        </ul>
    </aside>

    <main class="main-content">
        <h3>Gold Shop</h3>
        <div class="filter-section">
            <form action="gold_shop.php" method="GET" style="display: flex; width: 100%; gap: 15px;">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                <div class="form-group">
                    <input type="text" id="search" name="search" class="form-control" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search by name or description">
                </div>
                <button type="submit" class="btn btn-gold">Search</button>
                <a href="gold_shop.php" class="btn btn-dark">Clear</a>
            </form>
        </div>

        <div class="product-grid">
            <?php if ($products_result && $products_result->num_rows > 0): ?>
                <?php while($product = $products_result->fetch_assoc()): ?>
                    <div class="product-card">
                        <img src="<?php echo !empty($product['image_path']) ? htmlspecialchars('uploads/products/' . $product['image_path']) : 'uploads/no_image.png'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="product-card-content">
                            <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                            <p class="category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                            <p class="price">$<?php echo number_format($product['price'], 2); ?></p>
                            <div class="product-actions">
                                <input type="number" class="form-control quantity-input" value="1" min="1" id="quantity-<?php echo $product['id']; ?>">
                                <button class="btn btn-gold ajax-add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>">Add to Cart</button>
                                <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn btn-dark">View</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="grid-column: 1 / -1; text-align: center;">No products found matching your criteria.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Toast message container -->
<div id="toast-message" class="toast-message"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addToCartButtons = document.querySelectorAll('.ajax-add-to-cart-btn');

    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();

            const productId = this.getAttribute('data-product-id');
            const quantityInput = document.getElementById('quantity-' + productId);
            const quantity = quantityInput.value;

            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', quantity);

            fetch('add_to_cart_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.message);
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An unexpected error occurred.');
            });
        });
    });
});

function showToast(message) {
    const toast = document.getElementById('toast-message');
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}
</script>

<?php require_once 'footer.php'; ?>
