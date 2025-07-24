<?php
/*
 * Template Name: Cart
 */
get_header(); ?>
<div class="cart-page">
    <div class="back-arrow" style="margin-top:3rem; font-size:0.9rem">
        <a href="<?php echo esc_url(home_url('/')); ?>" title="Back to Home">← Back</a>
    </div>
    <h1 class="cart-title">Your Cart</h1>
    <?php
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $cart = isset($_SESSION['itu_cart']) ? $_SESSION['itu_cart'] : array();
    if (empty($cart)) {
        echo '<div class="empty-cart">Your cart is empty. <a href="' . esc_url(home_url('/')) . '">Continue shopping</a>.</div>';
    } else {
        $client_id = defined('ITU_API_CLIENT_ID') ? ITU_API_CLIENT_ID : '';
        $client_secret = defined('ITU_API_CLIENT_SECRET') ? ITU_API_CLIENT_SECRET : '';
        $token_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/authorizationserver/oauth/token';
        $base_api_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/occ/v2/itu';

        if (empty($client_id) || empty($client_secret)) {
            echo '<p>Error: API credentials not configured in wp-config.php</p>';
        } else {
            // Fetch product details for items in cart
            $access_token = itu_get_access_token(); // Re-use the existing token function
            $cart_items_details = [];
            $cart_total = 0;

            if ($access_token) {
                foreach ($cart as $product_code => $quantity) {
                    $api_url = $base_api_url . '/products/' . urlencode($product_code);
                    $response = wp_remote_get($api_url, [
                        'headers' => ['Authorization' => 'Bearer ' . $access_token],
                        'timeout' => 15,
                    ]);

                    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                        $product_data = json_decode(wp_remote_retrieve_body($response), true);
                        if ($product_data) {
                            $price_value = $product_data['price']['value'] ?? 0;
                            $formatted_price = $product_data['price']['formattedValue'] ?? 'N/A';
                            $item_total = $price_value * $quantity;
                            $cart_total += $item_total;

                            $cart_items_details[] = [
                                'code' => $product_data['code'],
                                'name' => $product_data['name'],
                                'quantity' => $quantity,
                                'price_value' => $price_value,
                                'formatted_price' => $formatted_price,
                                'total' => $item_total,
                                'image_url' => $product_data['images'][0]['url'] ?? ''
                            ];
                        } else {
                            // Log missing product or error, consider removing from cart if product not found
                            error_log('ITU Shop: Product not found in API for cart: ' . $product_code);
                            // Optionally, remove the invalid product from session here
                            // unset($_SESSION['itu_cart'][$product_code]);
                        }
                    } else {
                        error_log('ITU Shop: Error fetching product ' . $product_code . ' from API: ' . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_message($response)));
                    }
                }

                if (empty($cart_items_details)) {
                    echo '<div class="empty-cart">Your cart is empty. <a href="' . esc_url(home_url('/')) . '">Continue shopping</a>.</div>';
                    // Clear the session cart if no items were successfully fetched (optional, depends on desired behavior)
                    // unset($_SESSION['itu_cart']);
                } else {
                    ?>
                    <div class="cart-notification" style="display:none;"></div>
                    <table class="cart-table"> <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items_details as $item) : ?>
                                <tr>
                                    <td class="product-info" data-label="Product">
                                        <?php if (!empty($item['image_url'])) : ?>
                                            <img src="<?php echo esc_url($item['image_url']); ?>" alt="<?php echo esc_attr($item['name']); ?>" class="cart-product-image">
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url(home_url('/product/' . $item['code'])); ?>"><?php echo esc_html($item['name']); ?></a>
                                    </td>
                                    <td data-label="Quantity">
                                        <form class="update-quantity-form" method="post" action="">
                                            <input type="hidden" name="product_code" value="<?php echo esc_attr($item['code']); ?>">
                                            <div class="quantity-selector">
                                                <button type="button" class="update-quantity-decrease">-</button>
                                                <input type="number" name="quantity" class="quantity-input" value="<?php echo esc_attr($item['quantity']); ?>" min="0" data-product-code="<?php echo esc_attr($item['code']); ?>">
                                                <button type="button" class="update-quantity-increase">+</button>
                                            </div>
                                        </form>
                                    </td>
                                    <td data-label="Price"><?php echo esc_html($item['formatted_price']); ?></td>
                                    <td data-label="Total">CHF <?php echo esc_html(number_format($item['total'], 2)); ?></td>
                                    <td data-label="Actions">
                                        <form class="remove-item-form" method="post" action="">
                                            <input type="hidden" name="product_code" value="<?php echo esc_attr($item['code']); ?>">
                                            <button type="submit" class="remove-item" aria-label="Remove item">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="cart-totals">
                        <p>Total: <span class="cart-total-amount">CHF <?php echo esc_html(number_format($cart_total, 2)); ?></span></p>
                        <p class="checkout-placeholder">Proceed to Checkout coming soon.</p>
                    </div>
                    <?php
                    if (WP_DEBUG) {
                        error_log('ITU Shop: Cart displayed - Items: ' . count($cart_items_details) . ', Total: CHF ' . number_format($cart_total, 2));
                    }
                }
            } else {
                echo '<p>Error: Could not retrieve product information for cart items.</p>';
            }
        }
    }
    ?>
</div>
<?php get_footer(); ?>