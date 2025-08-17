document.addEventListener('DOMContentLoaded', function() {
    const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');

    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const quantityInput = this.closest('.product-actions').querySelector('.quantity-input');
            const quantity = quantityInput ? quantityInput.value : 1; // Default to 1 if input not found

            // Basic validation
            if (quantity < 1) {
                alert('Quantity must be at least 1.');
                return;
            }

            // Send AJAX request
            fetch('cart.php', { // Assuming cart.php handles adding to cart via POST
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add&product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json()) // Assuming cart.php returns JSON response
            .then(data => {
                if (data.success) {
                    alert('Product added to cart successfully!');
                    // Optionally update a cart count display
                    // const cartCountElement = document.getElementById('cart-count');
                    // if (cartCountElement) {
                    //     cartCountElement.textContent = data.cartCount;
                    // }
                } else {
                    alert('Failed to add product to cart: ' + (data.message || 'Unknown error.'));
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                alert('An error occurred while adding to cart. Please try again.');
            });
        });
    });
});