document.addEventListener('DOMContentLoaded', function() {
    const addToCartForm = document.getElementById('add-to-cart-form');
    const updateQuantityForms = document.querySelectorAll('.update-quantity-form');
    const removeItemForms = document.querySelectorAll('.remove-item-form');
    const cartNotification = document.querySelector('.cart-notification');

    // Validate ituAjax object
    if (!ituAjax || !ituAjax.rest_url_cart_add || !ituAjax.rest_url_cart_update || !ituAjax.rest_url_cart_remove || !ituAjax.nonce) {
        console.error('ITU Shop: ituAjax not properly initialized:', { ituAjax });
        return;
    }
    console.log('ITU Shop: script-cart.js initialized', {
        addToCart: !!addToCartForm,
        updateForms: updateQuantityForms.length,
        removeForms: removeItemForms.length,
        notification: !!cartNotification
    });

    // Add to cart (unchanged, as it's working)
    if (addToCartForm) {
        addToCartForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(addToCartForm);
            const data = {
                product_code: formData.get('product_code'),
                quantity: parseInt(formData.get('quantity'))
            };
            console.log('ITU Shop: Add to cart request:', data);

            fetch(ituAjax.rest_url_cart_add, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': ituAjax.nonce
                    },
                    body: JSON.stringify(data)
                })
                .then(response => {
                    console.log('ITU Shop: Add to cart response:', { status: response.status, ok: response.ok });
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        if (cartNotification) {
                            cartNotification.textContent = 'Product added to cart!';
                            cartNotification.style.display = 'block';
                            cartNotification.classList.add('show');
                            setTimeout(() => {
                                cartNotification.classList.remove('show');
                                cartNotification.style.display = 'none';
                            }, 2000);
                        }
                        console.log('ITU Shop: Added to cart:', data);
                        // Optionally, update cart display without full reload if needed
                        // For now, reload to ensure cart totals are updated
                        window.location.reload();
                    } else {
                        console.error('ITU Shop: Add to cart failed:', data.message || 'Unknown error');
                        alert('Error adding to cart: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('ITU Shop: Add to cart error:', error);
                    alert('Error adding to cart.');
                });
        });
    }

    // Function to handle item removal (centralized logic)
    const handleRemoveItem = function(productCode) {
        console.log(`ITU Shop: Initiating removal for product: ${productCode}`);
        const data = {
            product_code: productCode
        };

        fetch(ituAjax.rest_url_cart_remove, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': ituAjax.nonce
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                console.log('ITU Shop: Remove item response:', { status: response.status, ok: response.ok });
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (cartNotification) {
                        cartNotification.textContent = 'Product removed from cart!';
                        cartNotification.style.display = 'block';
                        cartNotification.classList.add('show');
                        setTimeout(() => {
                            cartNotification.classList.remove('show');
                            cartNotification.style.display = 'none';
                        }, 2000);
                    }
                    console.log('ITU Shop: Removed from cart:', data);
                    window.location.reload(); // Reload to update cart
                } else {
                    console.error('ITU Shop: Remove item failed:', data.message || 'Unknown error');
                    alert('Error removing item: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('ITU Shop: Remove item error:', error);
                alert('Error removing item.');
            });
    };


    // Update quantity forms
    updateQuantityForms.forEach(form => {
        const decreaseButton = form.querySelector('.update-quantity-decrease');
        const increaseButton = form.querySelector('.update-quantity-increase');
        const quantityInput = form.querySelector('.quantity-input');

        const updateCartQuantity = function() {
            const productCode = form.querySelector('input[name="product_code"]').value.trim(); // Trim whitespace
            let quantity = parseInt(quantityInput.value);

            console.log(`ITU Shop: Attempting to update quantity for ${productCode} to ${quantity}`);

            // If quantity is 0 or less, treat it as a remove request
            if (quantity <= 0) {
                console.log(`ITU Shop: Quantity for ${productCode} is ${quantity}, initiating removal.`);
                handleRemoveItem(productCode); // Call the centralized remove function
                return;
            }

            const data = {
                product_code: productCode,
                quantity: quantity
            };

            fetch(ituAjax.rest_url_cart_update, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': ituAjax.nonce
                    },
                    body: JSON.stringify(data)
                })
                .then(response => {
                    console.log('ITU Shop: Update quantity response:', { status: response.status, ok: response.ok });
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        if (cartNotification) {
                            cartNotification.textContent = 'Cart quantity updated!';
                            cartNotification.style.display = 'block';
                            cartNotification.classList.add('show');
                            setTimeout(() => {
                                cartNotification.classList.remove('show');
                                cartNotification.style.display = 'none';
                            }, 2000);
                        }
                        window.location.reload(); // Reload to reflect changes and update totals
                        console.log('ITU Shop: Updated cart quantity:', data);
                    } else {
                        console.error('ITU Shop: Update quantity failed:', data.message || 'Unknown error');
                        alert('Error updating quantity: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('ITU Shop: Update quantity error:', error);
                    alert('Error updating quantity.');
                });
        };

        if (decreaseButton) {
            decreaseButton.addEventListener('click', function() {
                let currentQuantity = parseInt(quantityInput.value);
                if (currentQuantity > 0) { // Allow decreasing to 0, which will trigger remove
                    quantityInput.value = currentQuantity - 1;
                    updateCartQuantity();
                }
            });
        }

        if (increaseButton) {
            increaseButton.addEventListener('click', function() {
                let currentQuantity = parseInt(quantityInput.value);
                quantityInput.value = currentQuantity + 1;
                updateCartQuantity();
            });
        }

        // Add event listener for direct input changes (e.g., user typing)
        quantityInput.addEventListener('change', updateCartQuantity);
    });

    // Remove item forms
    removeItemForms.forEach(form => {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            const productCode = form.querySelector('input[name="product_code"]').value.trim(); // Trim whitespace
            handleRemoveItem(productCode); // Call the centralized remove function
        });
    });
});