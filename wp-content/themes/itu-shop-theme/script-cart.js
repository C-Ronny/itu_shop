document.addEventListener('DOMContentLoaded', function() {
const addToCartForm = document.getElementById('add-to-cart-form');
const updateQuantityForms = document.querySelectorAll('.update-quantity-form');
const removeItemForms = document.querySelectorAll('.remove-item-form');
const cartNotification = document.querySelector('.cart-notification');

if (addToCartForm) {
    addToCartForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(addToCartForm);
        const data = {
            product_code: formData.get('product_code'),
            quantity: parseInt(formData.get('quantity'))
        };

        fetch(ituAjax.rest_url_cart_add, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': ituAjax.nonce
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && cartNotification) {
                cartNotification.style.display = 'block';
                cartNotification.classList.add('show');
                setTimeout(() => {
                    cartNotification.classList.remove('show');
                    cartNotification.style.display = 'none';
                }, 2000);
                console.log('ITU Shop: Added to cart:', data);
            } else {
                console.error('ITU Shop: Add to cart failed:', data.message);
                alert('Error adding to cart: ' + data.message);
            }
        })
        .catch(error => {
            console.error('ITU Shop: Add to cart error:', error);
            alert('Error adding to cart.');
        });
    });
}

updateQuantityForms.forEach(form => {
    const decreaseButton = form.querySelector('.update-quantity-decrease');
    const increaseButton = form.querySelector('.update-quantity-increase');
    const quantityInput = form.querySelector('.quantity-input');

    decreaseButton.addEventListener('click', function() {
        let quantity = parseInt(quantityInput.value);
        if (quantity > 1) {
            quantityInput.value = quantity - 1;
            updateCartQuantity(form);
        }
    });

    increaseButton.addEventListener('click', function() {
        let quantity = parseInt(quantityInput.value);
        if (quantity < 10) {
            quantityInput.value = quantity + 1;
            updateCartQuantity(form);
        }
    });

    quantityInput.addEventListener('change', function() {
        let quantity = parseInt(quantityInput.value);
        if (isNaN(quantity) || quantity < 1) {
            quantityInput.value = 1;
        } else if (quantity > 10) {
            quantityInput.value = 10;
        }
        updateCartQuantity(form);
    });
});

function updateCartQuantity(form) {
    const formData = new FormData(form);
    const data = {
        product_code: formData.get('product_code'),
        quantity: parseInt(formData.get('quantity'))
    };

    fetch(ituAjax.rest_url_cart_update, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': ituAjax.nonce
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload(); // Reload to update totals
            console.log('ITU Shop: Updated cart quantity:', data);
        } else {
            console.error('ITU Shop: Update quantity failed:', data.message);
            alert('Error updating quantity: ' + data.message);
        }
    })
    .catch(error => {
        console.error('ITU Shop: Update quantity error:', error);
        alert('Error updating quantity.');
    });
}

removeItemForms.forEach(form => {
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(form);
        const data = {
            product_code: formData.get('product_code')
        };

        fetch(ituAjax.rest_url_cart_remove, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': ituAjax.nonce
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload(); // Reload to update cart
                console.log('ITU Shop: Removed from cart:', data);
            } else {
                console.error('ITU Shop: Remove item failed:', data.message);
                alert('Error removing item: ' . data.message);
            }
        })
        .catch(error => {
            console.error('ITU Shop: Remove item error:', error);
            alert('Error removing item.');
        });
    });
});
});