<?php
/*
 * Template Name: Product Details
 */
get_header(); ?>
<div class="product-details">
    <div class="back-arrow">
        <a href="<?php echo esc_url(home_url('/')); ?>" title="Back to Home">← Back</a>
    </div>
    <?php
    $product_code = get_query_var('product_code', '');
    if (empty($product_code)) {
        echo '<div class="error-message">Error: No product code specified.</div>';
        if (WP_DEBUG) {
            error_log('ITU Shop: No product code specified for single-product.php');
        }
    } else {
        $client_id = defined('ITU_API_CLIENT_ID') ? ITU_API_CLIENT_ID : '';
        $client_secret = defined('ITU_API_CLIENT_SECRET') ? ITU_API_CLIENT_SECRET : '';
        $token_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/authorizationserver/oauth/token';
        $base_api_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/occ/v2/itu';
        $image_base_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com';

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
                $api_url = $base_api_url . '/products/' . urlencode($product_code) . '?fields=DEFAULT';
                if (WP_DEBUG) {
                    error_log('ITU Shop: Fetching product from ' . $api_url);
                }
                $response = wp_remote_get($api_url, array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $access_token,
                        'Cache-Control' => 'no-cache'
                    ),
                ));

                if (is_wp_error($response)) {
                    echo '<div class="error-message">Error fetching product: ' . esc_html($response->get_error_message()) . '</div>';
                    if (WP_DEBUG) {
                        error_log('ITU Shop: Error fetching product: ' . $response->get_error_message());
                    }
                } else {
                    $product = json_decode(wp_remote_retrieve_body($response), true);
                    if (WP_DEBUG) {
                        error_log('ITU Shop: Product API response: ' . json_encode($product));
                    }
                    if (!isset($product['code'])) {
                        echo '<div class="error-message">Product not found.</div>';
                        if (WP_DEBUG) {
                            error_log('ITU Shop: Product not found for code: ' . $product_code);
                        }
                    } else {
                        $name = $product['name'] ?? 'Unnamed Product';
                        $code = $product['code'] ?? $product_code;
                        $price = $product['price']['formattedValue'] ?? 'CHF 0.00';
                        $price_value = $product['price']['value'] ?? 0.00;
                        $description = $product['description'] ?? 'No description available';
                        $stock_status = $product['stock']['stockLevelStatus'] ?? 'No stock information';
                        $category = !empty($product['categories']) ? ($product['categories'][0]['name'] ?? 'No category') : 'No category';
                        $manufacturer = $product['manufacturer'] ?? 'Unknown manufacturer';
                        $image_url = '';
                        if (!empty($product['images'])) {
                            foreach ($product['images'] as $image) {
                                if ($image['imageType'] === 'PRIMARY' && $image['format'] === 'product') {
                                    $image_url = strpos($image['url'], 'http') === 0 ? $image['url'] : $image_base_url . $image['url'];
                                    break;
                                }
                            }
                        }
                        if (WP_DEBUG) {
                            error_log('ITU Shop: Image URL: ' . ($image_url ?: 'None'));
                            error_log('ITU Shop: Product price value: ' . $price_value);
                        }
                        ?>
                        <div class="product-details-container">
                            <div class="product-details-left">
                                <?php if ($image_url): ?>
                                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($name); ?>" class="product-image">
                                <?php else: ?>
                                    <div class="image-placeholder">No Image</div>
                                <?php endif; ?>
                                <div class="product-description"><?php echo esc_html($description); ?></div>
                            </div>
                            <div class="product-details-right">
                                <h1 class="product-name"><?php echo esc_html($name); ?></h1>
                                <p class="product-id">ID: <?php echo esc_html($code); ?></p>
                                <p class="product-category">Category: <?php echo esc_html($category); ?></p>
                                <p class="product-price"><?php echo esc_html($price); ?></p>
                                <p class="product-stock">Stock: <?php echo esc_html($stock_status); ?></p>
                                <p class="product-manufacturer">Manufacturer: <?php echo esc_html($manufacturer); ?></p>
                                <div class="product-actions">
                                    <div class="quantity-selector">
                                        <label for="quantity">Quantity:</label>
                                        <button type="button" class="quantity-button" id="decrease-quantity" aria-label="Decrease quantity">-</button>
                                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="10" aria-label="Quantity" data-price="<?php echo esc_attr($price_value); ?>">
                                        <button type="button" class="quantity-button" id="increase-quantity" aria-label="Increase quantity">+</button>
                                    </div>                                    
                                    <p class="max-quantity">Maximum Order Quantity: 10</p>
                                    <p class="total-price">Total: <span id="total-price">CHF <?php echo esc_html(number_format($price_value, 2)); ?></span></p>
                                </div>
                            </div>
                        </div>
                        <?php
                        if (WP_DEBUG) {
                            error_log('ITU Shop: Product displayed - Name: ' . $name . ', Code: ' . $code);
                        }
                    }
                }
            }
        }
    }
    ?>
</div>
<?php get_footer(); ?>