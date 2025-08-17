        </main>
    </div><!-- .main-content or .main-content-full-width ends -->
</div><!-- .user-dashboard-container ends (if it exists) -->

<!-- ===================================
    Footer Section
==================================== -->
<footer class="main-footer">
    <div class="container">
        <?php
        // --- চূড়ান্ত সমাধান: P2P পেজগুলোতে ফুটারের অপ্রয়োজনীয় অংশ হাইড করা ---
        $current_page_for_footer = basename($_SERVER['PHP_SELF']);
        $is_p2p_page_for_footer = in_array($current_page_for_footer, ['p2p_market.php', 'p2p_view_offer.php', 'p2p_chat.php', 'p2p_start_trade.php']);

        if (!$is_p2p_page_for_footer):
        ?>
            <div class="footer-content">
                <div class="footer-widget">
                    <h4>Smart Gold <span>Trade</span></h4>
                    <p>Your trusted partner in gold investment, trading, and e-commerce. Secure your future with the timeless value of gold.</p>
                </div>
                <div class="footer-widget">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Investment Plans</a></li>
                        <li><a href="#">Trading Platform</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>
                <div class="footer-widget">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Risk Disclosure</a></li>
                    </ul>
                </div>
                <div class="footer-widget">
                    <h4>Contact Us</h4>
                    <p>123 Golden Street, Dhaka, Bangladesh</p>
                    <p>support@smartgoldtrade.com</p>
                    <p>+880 123 456 789</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> Smart Gold Trade. All Rights Reserved.</p>
        </div>
    </div>
</footer>

<!-- JavaScript for Mobile Menu -->
<script>
    const mobileNavToggle = document.querySelector('.mobile-nav-toggle');
    const navLinks = document.querySelector('.nav-links');

    if (mobileNavToggle) {
        mobileNavToggle.addEventListener('click', () => {
            navLinks.classList.toggle('active');
        });
    }
</script>
<script src="/script.js"></script> <!-- Assuming script.js is in the root -->
</body>
</html>
