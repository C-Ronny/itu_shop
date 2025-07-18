<?php
/*
 * Template Name: Home Page
 */
get_header(); ?>
<div class="home-content">
    <h1 id="welcome">Welcome to ITU Shop</h1>
    <ul id="category-filter">
        <li data-category="" class="active">All Products</li>        
    </ul>
    <?php
    // Check for API credentials
    if (!defined('ITU_API_CLIENT_ID') || !defined('ITU_API_CLIENT_SECRET')) {
        echo '<p>Error: API credentials not configured in wp-config.php</p>';
    } else {
        $client_id = ITU_API_CLIENT_ID;
        $client_secret = ITU_API_CLIENT_SECRET;
        $token_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/authorizationserver/oauth/token';
        $base_api_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/occ/v2/itu';
        
        // Validate $_GET parameters
        $current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] >= 0 ? intval($_GET['page']) : 0;
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        
        // Normalize category for filtering
        $normalized_category = $category;
        if ($category) {
            $normalized_category = strtolower($category);
            $normalized_category = preg_replace('/\s*\(.*?\)/', '', $normalized_category);
            $normalized_category = preg_replace('/\s+/', '-', $normalized_category);
            $normalized_category = rtrim($normalized_category, '-');
        }

        // Fetch products for the current page
        $products = [];
        $total_pages = 1;
        $total_results = 0;

        $api_url = $base_api_url . '/products/search?currentPage=' . $current_page . '&fields=DEFAULT&pageSize=12';
        if (WP_DEBUG) {
            error_log('ITU Shop: Home page fetching products from ' . $api_url);
        }

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
                echo '<p>Error fetching token: ' . esc_html($token_response->get_error_message()) . '</p>';
            } else {
                $token_body = json_decode(wp_remote_retrieve_body($token_response), true);
                $access_token = $token_body['access_token'] ?? '';
                if ($access_token) {
                    set_transient('itu_access_token', $access_token, $token_body['expires_in'] - 60);
                }
            }
        }
        
        if (empty($access_token)) {
            echo '<p>Error: No access token received.</p>';
        } else {
            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Cache-Control' => 'no-cache'
                ),
            ));
            
            if (is_wp_error($response)) {
                echo '<p>Error fetching products: ' . esc_html($response->get_error_message()) . '</p>';
            } else {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $products = $body['products'] ?? [];
                $total_pages = $body['pagination']['totalPages'] ?? 1;
                $total_results = $body['pagination']['totalResults'] ?? count($products);

                if ($category) {
                    $products = array_filter($products, function($product) use ($normalized_category) {
                        $url_parts = explode('/', $product['url']);
                        if (count($url_parts) < 2) return false;
                        $category_segment = urldecode($url_parts[1]);
                        $normalized_product_category = strtolower($category_segment);
                        $normalized_product_category = preg_replace('/\s*\(.*?\)/', '', $normalized_product_category);
                        $normalized_product_category = preg_replace('/\s+/', '-', $normalized_product_category);
                        $normalized_product_category = rtrim($normalized_product_category, '-');
                        return $normalized_product_category === $normalized_category;
                    });
                    $products = array_values($products);
                    $total_results = count($products); // Approximate for current page
                }
            }
        }

        // Display total products message
        echo '<div class="category-message">' . esc_html($category ? "Showing " . count($products) . " products for $category" : "Showing $total_results products") . '</div>';

        // Debug output
        if (WP_DEBUG) {
            echo '<div style="display:none;" class="debug">';
            echo '<h3>API Debug Info:</h3>';
            echo '<p>Total Pages: ' . esc_html($total_pages) . '</p>';
            echo '<p>Current Page: ' . esc_html($current_page) . '</p>';
            echo '<p>Total Results: ' . esc_html($total_results) . '</p>';
            echo '<p>API URL: ' . esc_html($api_url) . '</p>';
            echo '</div>';
        }
        ?>
        <h2>Our Products</h2>
        <div class="product-grid" id="product-grid">
            <?php
            if (empty($products)) {
                echo '<p>No products available.</p>';
                if (WP_DEBUG) {
                    echo '<pre class="debug">' . esc_html(print_r($body, true)) . '</pre>';
                }
            } else {
                foreach ($products as $product) {
                    $title = $product['name'] ?? 'Unnamed Product';
                    $price = $product['price']['value'] ?? '0.00';
                    $currency = $product['price']['currencyIso'] ?? 'CHF';
                    $stock_status = $product['stock']['stockLevelStatus'] ?? 'unknown';
                    echo '<div class="product-card">';
                    echo '<div class="image-placeholder"></div>';
                    echo '<h3>' . esc_html($title) . '</h3>';
                    echo '<p class="price">' . esc_html(number_format($price, 2)) . ' ' . esc_html($currency) . '</p>';
                    echo '<p class="stock-status">Stock: ' . esc_html($stock_status) . '</p>';
                    echo '<a href="#" class="product-link" data-product-code="' . esc_attr($product['code']) . '">' . esc_html($title) . '</a>';
                    echo '</div>';
                }
            }
            ?>
        </div>
        <div class="pagination">
            <?php
            $base_url = remove_query_arg('page');
            if ($current_page > 0) {
                $prev_url = $category ? add_query_arg(['page' => $current_page - 1, 'category' => $category], $base_url) : add_query_arg('page', $current_page - 1, $base_url);
                echo '<a href="' . esc_url($prev_url) . '" class="pagination-link" data-page="' . ($current_page - 1) . '" data-category="' . esc_attr($category) . '">Previous</a>';
            }
            echo '<span class="page-info">Page ' . ($current_page + 1) . ' of ' . $total_pages . '</span>';
            if ($current_page < $total_pages - 1 && $total_pages > 1) {
                $next_url = $category ? add_query_arg(['page' => $current_page + 1, 'category' => $category], $base_url) : add_query_arg('page', $current_page + 1, $base_url);
                echo '<a href="' . esc_url($next_url) . '" class="pagination-link" data-page="' . ($current_page + 1) . '" data-category="' . esc_attr($category) . '">Next</a>';
            }
            ?>
        </div>
        <?php
    }
    ?>
</div>
<?php get_footer(); ?>