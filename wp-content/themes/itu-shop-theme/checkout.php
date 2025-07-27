<?php
/*
 * Template Name: Checkout
 */
get_header(); ?>
<div class="cart-page">
    <div class="back-arrow" style="margin-top:3rem; font-size:0.9rem">
        <a href="<?php echo esc_url(home_url('/cart')); ?>" title="Back to Cart">← Back to Cart</a>
    </div>
    <h1 class="cart-title">Checkout</h1>
    <div class="cart-notification" style="display:none;"></div>
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
        $image_base_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com';

        if (empty($client_id) || empty($client_secret)) {
            echo '<p class="error-message">Error: API credentials not configured in wp-config.php</p>';
        } else {
            // Fetch product details for items in cart
            $access_token = itu_get_access_token();
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
                            $image_url = '';
                            if (!empty($product_data['images'])) {
                                foreach ($product_data['images'] as $image) {
                                    if (isset($image['imageType']) && $image['imageType'] === 'PRIMARY' && $image['format'] === 'product') {
                                        $image_url = strpos($image['url'], 'http') === 0 ? $image['url'] : $image_base_url . $image['url'];
                                        break;
                                    }
                                }
                            }

                            $cart_items_details[] = [
                                'code' => $product_data['code'],
                                'name' => $product_data['name'],
                                'quantity' => $quantity,
                                'price_value' => $price_value,
                                'formatted_price' => $formatted_price,
                                'total' => $item_total,
                                'image_url' => $image_url
                            ];
                        } else {
                            error_log('ITU Shop: Product not found in API for checkout: ' . $product_code);
                        }
                    } else {
                        error_log('ITU Shop: Error fetching product ' . $product_code . ' from API: ' . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_message($response)));
                    }
                }

                if (empty($cart_items_details)) {
                    echo '<div class="empty-cart">Your cart is empty. <a href="' . esc_url(home_url('/')) . '">Continue shopping</a>.</div>';
                } else {
                    ?>
                    <div class="checkout-content">
                        <div class="cart-summary">
                            <h2 class="cart-summary-title">Cart Summary</h2>
                            <table class="cart-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items_details as $item) : ?>
                                        <tr>
                                            <td class="product-info" data-label="Product">
                                                <?php if (!empty($item['image_url'])) : ?>
                                                    <img src="<?php echo esc_url($item['image_url']); ?>" alt="<?php echo esc_attr($item['name']); ?>" class="cart-product-image">
                                                <?php else : ?>
                                                    <div class="product-card-placeholder">No image</div>
                                                <?php endif; ?>
                                                <a href="<?php echo esc_url(home_url('/product/' . $item['code'])); ?>"><?php echo esc_html($item['name']); ?></a>
                                            </td>
                                            <td data-label="Quantity"><?php echo esc_html($item['quantity']); ?></td>
                                            <td data-label="Price"><?php echo esc_html($item['formatted_price']); ?></td>
                                            <td data-label="Total">CHF <?php echo esc_html(number_format($item['total'], 2)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="cart-totals">
                                <p>Total: <span class="cart-total-amount">CHF <?php echo esc_html(number_format($cart_total, 2)); ?></span></p>
                            </div>
                        </div>
                        <div class="user-details">
                            <h2 class="user-details-title">User Details</h2>
                            <form id="checkout-form" method="post" action="">
                                <div class="form-group">
                                    <label for="full-name">Full Name <span class="required">*</span></label>
                                    <input type="text" id="full-name" name="full_name" required aria-required="true">
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address <span class="required">*</span></label>
                                    <input type="email" id="email" name="email" required aria-required="true">
                                </div>
                                <div class="form-group">
                                    <label for="address">Address <span class="required">*</span></label>
                                    <textarea id="address" name="address" required aria-required="true"></textarea>
                                </div>
                                <button type="submit" class="add-to-cart">Submit Order</button>
                            </form>
                            <!-- <p class="checkout-placeholder">Payment processing coming soon.</p> -->
                        </div>
                    </div>
                    <!-- Modal for prototype notice -->
                    <div id="prototype-modal" class="prototype-modal" style="display:none;">
                        <div class="modal-content">
                            <p>This site is a prototype and not an operational store. To purchase ITU publications or merchandise, please visit the official ITU Shop at <a href="https://shop.itu.int" target="_blank" rel="noopener">https://shop.itu.int</a>.</p>
                            <button id="modal-redirect" class="modal-button">Visit Official Store</button>
                            <button id="modal-close" class="modal-button">Close</button>
                        </div>
                    </div>
                    <?php
                    if (WP_DEBUG) {
                        error_log('ITU Shop: Checkout displayed - Items: ' . count($cart_items_details) . ', Total: CHF ' . number_format($cart_total, 2));
                    }
                }
            } else {
                echo '<p class="error-message">Error: Could not retrieve product information for checkout items.</p>';
            }
        }
    }
    ?>
</div>
<script src="<?php echo get_template_directory_uri(); ?>/script-checkout.js"></script>
<?php get_footer(); ?>