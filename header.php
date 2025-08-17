<?php
// We can add configuration or session start logic here later
require_once 'db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Smart Gold Trade' : 'Smart Gold Trade'; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- ===================================
     Top Bar
    ==================================== -->
    <div class="top-bar">
        <div class="container">
            <div class="top-bar-content">
                <div class="contact-info">
                    <span>📞 +880 123 456 789</span>
                    <span>✉️ support@smartgoldtrade.com</span>
                </div>
                <div class="user-actions">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="/dashboard.php">My Dashboard</a>
                        <a href="/logout.php" class="btn-register">Logout</a>
                    <?php else: ?>
                        <a href="login.php">Login</a>
                        <a href="register.php" class="btn-register">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================
     Main Header & Navigation
    ==================================== -->
    <header class="main-header">
        <div class="container">
            <nav class="main-nav">
                <a href="index.php" class="logo">
                    Smart Gold <span>Trade</span>
                </a>
                
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li class="dropdown">
                        <a href="#">Investment Plans ▾</a>
                        <ul class="dropdown-menu">
                            <li><a href="#">View All Plans</a></li>
                            <li><a href="user/investment_history.php">My Investments</a></li>
                        </ul>
                    </li>
                    <li class="dropdown">
                        <a href="user/trading_platform.php">Trading Platform ▾</a>
                        <ul class="dropdown-menu">
                            <li><a href="#">Live Gold Chart</a></li>
                            <li><a href="#">Open Trades</a></li>
                            <li><a href="user/trading_platform.php">Trade History</a></li>
                        </ul>
                    </li>
                    <li class="dropdown">
                        <a href="#">Gold Shop ▾</a>
                        <ul class="dropdown-menu">
                            <li><a href="gold_shop.php">All Products</a></li>
                            <li><a href="user/ecommerce_orders.php">My Orders</a></li>
                            <li><a href="cart.php">Cart</a></li>
                            <li><a href="checkout.php">Checkout</a></li>
                            <li><a href="#">Wishlist</a></li>
                        </ul>
                    </li>
                    <li><a href="user/p2p_offers.php">P2P Trading</a></li>
                    <li><a href="/user/support_p2p.php">Support</a></li>
                </ul>

                <button class="mobile-nav-toggle">☰</button>
            </nav>
        </div>
    </header>

    <main>
