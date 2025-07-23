<?php
/*
 * Template Name: Cart
 */
get_header(); ?>
<div class="cart-page">
    <div class="back-arrow" style="margin-top:2rem">
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
            echo '<div class="error-message">Error: API credentials not configured in wp-config.php</div>';
            if (WP_DEBUG) {
                error_log('ITU Shop: API credentials not configured in wp-config.php');
            }
        } else {
            $access_token = get_transient('itu_access_token');
            if (false === $access_token) {
                $token_response = wp_remote_post($token_url, array(
                    'body' => array(
                        'grant_type' => 'client_credentials',
                        'client_id' => $client_id,
                        'client_secret' => $client_secret,
                    ),
                ));

                if (is_wp_error($token_response)) {
                    echo '<div class="error-message">Error fetching token: ' . esc_html($token_response->get_error_message()) . '</div>';
                    if (WP_DEBUG) {
                        error_log('ITU Shop: Error fetching token: ' . $token_response->get_error_message());
                    }
                } else {
                    $token_body = json_decode(wp_remote_retrieve_body($token_response), true);
                    $access_token = $token_body['access_token'] ?? '';
                    if ($access_token) {
                        set_transient('itu_access_token', $access_token, $token_body['expires_in'] - 60);
                        if (WP_DEBUG) {
                            error_log('ITU Shop: Access token cached');
                        }
                    }
                }
            }

            if (empty($access_token)) {
                echo '<div class="error-message">Error: No access token received.</div>';
                if (WP_DEBUG) {
                    error_log('ITU Shop: No access token received');
                }
            } else {
                $cart_items = array();
                $cart_total = 0;
                foreach ($cart as $product_code => $quantity) {
                    $api_url = $base_api_url . '/products/' . urlencode($product_code) . '?fields=DEFAULT';
                    if (WP_DEBUG) {
                        error_log('ITU Shop: Fetching cart product from ' . $api_url);
                    }
                    $response = wp_remote_get($api_url, array(
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $access_token,
                            'Cache-Control' => 'no-cache'
                        ),
                    ));

                    if (is_wp_error($response)) {
                        if (WP_DEBUG) {
                            error_log('ITU Shop: Error fetching cart product: ' . $response->get_error_message());
                        }
                        continue;
                    }

                    $product = json_decode(wp_remote_retrieve_body($response), true);
                    if (!isset($product['code'])) {
                        if (WP_DEBUG) {
                            error_log('ITU Shop: Cart product not found for code: ' . $product_code);
                        }
                        continue;
                    }

                    $cart_items[] = array(
                        'code' => $product['code'],
                        'name' => $product['name'] ?? 'Unnamed Product',
                        'price' => $product['price']['value'] ?? 0.00,
                        'formatted_price' => $product['price']['formattedValue'] ?? 'CHF 0.00',
                        'quantity' => $quantity,
                        'total' => ($product['price']['value'] ?? 0.00) * $quantity
                    );
                    $cart_total += ($product['price']['value'] ?? 0.00) * $quantity;
                }

                if (empty($cart_items)) {
                    echo '<div class="empty-cart">Your cart is empty. <a href="' . esc_url(home_url('/')) . '">Continue shopping</a>.</div>';
                } else {
                    ?>
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Total</th>
                                <th>Remove</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item): ?>
                                <tr data-product-code="<?php echo esc_attr($item['code']); ?>">
                                    <td><?php echo esc_html($item['name']); ?></td>
                                    <td>
                                        <form class="update-quantity-form" method="post" action="">
                                            <input type="hidden" name="product_code" value="<?php echo esc_attr($item['code']); ?>">
                                            <div class="quantity-selector">
                                                <button type="button" class="quantity-button update-quantity-decrease" aria-label="Decrease quantity">-</button>
                                                <input type="number" class="quantity-input" name="quantity" value="<?php echo esc_attr($item['quantity']); ?>" min="1" max="10" aria-label="Quantity">
                                                <button type="button" class="quantity-button update-quantity-increase" aria-label="Increase quantity">+</button>
                                            </div>
                                        </form>
                                    </td>
                                    <td><?php echo esc_html($item['formatted_price']); ?></td>
                                    <td>CHF <?php echo esc_html(number_format($item['total'], 2)); ?></td>
                                    <td>
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
                        error_log('ITU Shop: Cart displayed - Items: ' . count($cart_items) . ', Total: CHF ' . number_format($cart_total, 2));
                    }
                }
            }
        }
    }
    ?>
</div>
<?php get_footer(); ?>