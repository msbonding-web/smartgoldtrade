<?php 
$page_title = "Invest, Trade & Own Real Gold";
// Corrected path for header.php
require_once 'header.php'; 
require_once 'db_connect.php'; // Ensure db_connect.php is included

// Fetch products for Gold Shop section
$home_products = [];
$home_products_query = "SELECT p.id, p.name, p.price, COALESCE(pi.path, p.image_path) AS image_path FROM products p LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.display_order = 0 WHERE p.is_active = 1 ORDER BY p.created_at DESC LIMIT 3";
$home_products_result = $conn->query($home_products_query);
if ($home_products_result) {
    while ($product = $home_products_result->fetch_assoc()) {
        $home_products[] = $product;
    }
}

// Fetch investment plans for Featured Investment Plans section
$home_plans = [];
$home_plans_query = "SELECT id, name, min_amount, max_amount, profit_value, duration_value, duration_unit, capital_back FROM investment_plans WHERE is_active = 1 ORDER BY display_order ASC LIMIT 3";
$home_plans_result = $conn->query($home_plans_query);
if ($home_plans_result) {
    while ($plan = $home_plans_result->fetch_assoc()) {
        $home_plans[] = $plan;
    }
}

// Fetch latest gold rates for ticker
$gold_rates = [];
$gold_rates_query = "SELECT gram_price_usd FROM gold_rates ORDER BY updated_at DESC LIMIT 1";
$gold_rates_result = $conn->query($gold_rates_query);
if ($gold_rates_result && $gold_rates_result->num_rows > 0) {
    $latest_gold_price_usd_per_gram = $gold_rates_result->fetch_assoc()['gram_price_usd'];
    // Assuming 1 oz = 31.1035 grams
    $latest_gold_price_usd_per_oz = $latest_gold_price_usd_per_gram * 31.1035;

    // For simplicity, hardcoding BTC and EUR conversion for now, or fetching from a more comprehensive rates table if available
    // In a real scenario, you'd fetch these from a live API or a more robust currency conversion table.
    $xau_usd = number_format($latest_gold_price_usd_per_oz, 2);
    $xau_btc = number_format($latest_gold_price_usd_per_oz / 60000, 5); // Assuming 1 BTC = $60,000 for example
    $xau_eur = number_format($latest_gold_price_usd_per_oz * 0.92, 2); // Assuming 1 USD = 0.92 EUR for example
    $gold_1g_usd = number_format($latest_gold_price_usd_per_gram, 2);

    $gold_rates[] = "XAU/USD: $" . $xau_usd;
    $gold_rates[] = "XAU/BTC: " . $xau_btc . " BTC";
    $gold_rates[] = "XAU/EUR: €" . $xau_eur;
    $gold_rates[] = "GOLD (1g): $" . $gold_1g_usd;
} else {
    // Fallback to static or default values if no rates found
    $gold_rates[] = "XAU/USD: $2,350.50";
    $gold_rates[] = "XAU/BTC: 0.035 BTC";
    $gold_rates[] = "XAU/EUR: €2,180.75";
    $gold_rates[] = "GOLD (1g): $75.50";
}

// Fetch latest blog posts
$latest_blog_posts = [];
$blog_posts_query = "SELECT id, title, slug, excerpt, created_at FROM blog_posts WHERE status = 'published' ORDER BY created_at DESC LIMIT 3";
$blog_posts_result = $conn->query($blog_posts_query);
if ($blog_posts_result) {
    while ($post = $blog_posts_result->fetch_assoc()) {
        $latest_blog_posts[] = $post;
    }
}

?>

<!-- ===================================
    Hero Section
==================================== -->
<section class="hero">
    <div class="hero-overlay"></div>
    <div class="container">
        <div class="hero-content">
            <h1>Invest, Trade & Own Real Gold with <span>Confidence</span></h1>
            <p>Smart Gold Trade – Your Gateway to Premium Gold Investment & Trading</p>
            <div class="hero-buttons">
                <a href="#" class="btn btn-gold">Start Investing</a>
                <a href="#" class="btn btn-dark">Start Trading</a>
            </div>
        </div>
    </div>
</section>

<!-- ===================================
    Section 1: Why Smart Gold Trade?
==================================== -->
<section class="why-us section-padding">
    <div class="container">
        <div class="section-title">
            <h2>Why Smart Gold Trade?</h2>
        </div>
        <div class="grid-4">
            <div class="feature-box">
                <h3>💹 Flexible Investment Plans</h3>
                <p>Choose from a variety of plans tailored to your financial goals. Grow your wealth with the stability of gold.</p>
            </div>
            <div class="feature-box">
                <h3>📊 Live Gold Trading</h3>
                <p>Experience the thrill of the market with our advanced trading platform. Trade gold pairs with high leverage.</p>
            </div>
            <div class="feature-box">
                <h3>🛒 Premium Gold E-Commerce</h3>
                <p>Buy and own physical gold coins and bars from our secure online shop, delivered insured to your doorstep.</p>
            </div>
            <div class="feature-box">
                <h3>🔒 Secure & Transparent</h3>
                <p>Your security is our priority. We use state-of-the-art encryption and offer fully transparent transactions.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===================================
    Section 2: Live Gold Price Ticker
==================================== -->
<section class="price-ticker">
    <div class="ticker-track">
        <?php foreach ($gold_rates as $rate): ?>
            <span><?php echo htmlspecialchars($rate); ?></span>
        <?php endforeach; ?>
        <?php foreach ($gold_rates as $rate): // Duplicate for continuous scroll ?>
            <span><?php echo htmlspecialchars($rate); ?></span>
        <?php endforeach; ?>
    </div>
</section>

<!-- ===================================
    Section 3: Featured Investment Plans
==================================== -->
<section class="investment-plans section-padding">
    <div class="container">
        <div class="section-title">
            <h2>Featured Investment Plans</h2>
        </div>
        <div class="grid-3">
            <?php if (!empty($home_plans)): ?>
                <?php foreach ($home_plans as $plan): ?>
                    <div class="plan-card">
                        <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
                        <p class="plan-profit"><?php echo number_format($plan['profit_value'], 2); ?>%</p>
                        <p><strong>Min Amount:</strong> $<?php echo number_format($plan['min_amount'], 2); ?></p>
                        <p><strong>Duration:</strong> <?php echo htmlspecialchars($plan['duration_value'] . ' ' . ucfirst($plan['duration_unit'])); ?></p>
                        <p><strong>Maturity:</strong> <?php echo $plan['capital_back'] ? 'Capital Back' : 'No Capital Back'; ?></p>
                        <a href="user/investment_plans.php" class="btn btn-gold">Get Started</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="grid-column: 1 / -1; text-align: center;">No investment plans found.</p>
            <?php endif; ?>
        </div>
        <div style="text-align: center; margin-top: 30px;">
            <a href="user/investment_plans.php" class="btn btn-dark">View All Plans</a>
        </div>
    </div>
</section>

<!-- ===================================
    Section 4: Trading Platform Preview
==================================== -->
<section class="trading-preview section-padding">
    <div class="container">
        <div class="trading-content">
            <div class="trading-text">
                <h2>Advanced Trading Platform</h2>
                <ul>
                    <li>✓ Instant Buy/Sell Orders</li>
                    <li>✓ Set Stop Loss & Take Profit</li>
                    <li>✓ Up to 200x Leverage</li>
                    <li>✓ Real-time TradingView Charts</li>
                </ul>
                <a href="#" class="btn btn-dark">Start Trading Now</a>
            </div>
            <div class="trading-image">
                <img src="https://placehold.co/600x400/1A1A1A/FFD700?text=TradingView+Chart" alt="Trading Platform Preview">
            </div>
        </div>
    </div>
</section>

<!-- ===================================
    Section 5: Gold Shop
==================================== -->
<section class="gold-shop section-padding">
    <div class="container">
        <div class="section-title">
            <h2>Our Gold Shop</h2>
        </div>
        <div class="grid-3">
            <?php if (!empty($home_products)): ?>
                <?php foreach ($home_products as $product): ?>
                    <div class="product-card">
                        <img src="<?php echo !empty($product['image_path']) ? htmlspecialchars('uploads/products/' . $product['image_path']) : 'uploads/no_image.png'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                        <p class="price">$<?php echo number_format($product['price'], 2); ?></p>
                        <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn btn-gold">View Details</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="grid-column: 1 / -1; text-align: center;">No products found.</p>
            <?php endif; ?>
        </div>
        <div style="text-align: center; margin-top: 30px;">
            <a href="gold_shop.php" class="btn btn-dark">View All Products</a>
        </div>
    </div>
</section>

<!-- ===================================
    Section 10: Call To Action
==================================== -->
<section class="cta">
    <div class="container">
        <h2>Start Your Gold Journey Today</h2>
        <p>Secure, Profitable & Simple. Join thousands of investors who trust us.</p>
        <a href="#" class="btn btn-dark">Join Now</a>
    </div>
</section>

<?php 
// Corrected path for footer.php
require_once 'footer.php'; 
?>
