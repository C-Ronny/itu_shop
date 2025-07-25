<?php
/*
 * Template Name: Home Page
 */
get_header(); ?>
<main class="home-content">
    <h1 id="welcome">Welcome to the ITU Shop</h1>
    <div class="search-container">
        <input type="text" id="search-input" placeholder="Search by product ID or name" value="<?php echo esc_attr(isset($_GET['query']) ? $_GET['query'] : ''); ?>">
        <button id="search-button">Search</button>
    </div>
    <ul id="category-filter">
        <li data-category="" class="active category-item">All Products</li>
    </ul>
    <?php
    // Check for API credentials
    if (!defined('ITU_API_CLIENT_ID') || !defined('ITU_API_CLIENT_SECRET')) {
        echo '<p class="error-message">Error: API credentials not configured in wp-config.php</p>';
    } else {
        // These PHP variables are for the initial server-side render or if JavaScript fails.
        // The JavaScript (itu-shop.js) will take over dynamic loading and pagination.
        $current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] >= 0 ? intval($_GET['page']) : 0;
        $query = isset($_GET['query']) ? sanitize_text_field($_GET['query']) : '';
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';

        // Initial fetch for PHP fallback display if JS fails or for first load
        $products_data = itu_fetch_products_from_api($current_page, $query, $category);
        $products = $products_data['products'] ?? [];
        $total_pages = $products_data['total_pages'] ?? 1;

        if (empty($products)) {
            echo '<p class="error-message">No products found matching your criteria.</p>';
        } else {
            ?>
            <!-- Assumes API image field is in $product['images']. If different (e.g., 'image_url'), update the image logic in this loop and in itu-shop.js renderProducts function -->
            <div id="product-grid" class="product-grid">
                <?php
                foreach ($products as $product) {
                    $title = $product['name'] ?? 'N/A';
                    $product_code = $product['code'] ?? 'N/A';
                    $price_value = $product['price']['value'] ?? 0;
                    $currency = $product['price']['currencyIso'] ?? 'CHF';
                    $stock_status = ($product['stock']['stockLevelStatus'] ?? '') === 'inStock' ? 'In Stock' : 'Out of Stock';
                    $image_url = '';
                    if (!empty($product['images'])) {
                        foreach ($product['images'] as $image) {
                            if (isset($image['format']) && $image['format'] === 'product') { // Or 'landscape', 'portrait', etc. based on API
                                $image_url = $image_base_url . $image['url'];
                                break;
                            }
                        }
                    }

                    echo '<div class="product-card">';
                    echo '<a href="' . esc_url(home_url('/product/' . $product_code)) . '" class="product-link" data-product-code="' . esc_attr($product_code) . '">';
                    if ($image_url) {
                        echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '" class="product-image">';
                    } else {
                        echo '<div class="product-card-placeholder">No image</div>';
                    }
                    echo '<h2 class="product-name">' . esc_html($title) . '</h2>';
                    echo '<p class="product-price">' . esc_html($currency) . ' ' . esc_html(number_format($price_value, 2)) . '</p>';
                    echo '<p class="stock-status">Stock: ' . esc_html($stock_status) . '</p>';
                    echo '</a>';
                    echo '</div>';
                }
                ?>
            </div>
            <div class="pagination">
                <?php
                $base_url = remove_query_arg('page');
                $query_params = array();
                if ($query) {
                    $query_params['query'] = $query;
                }
                if ($category) {
                    $query_params['category'] = $category;
                }

                if ($current_page > 0) {
                    $prev_params = array_merge($query_params, ['page' => $current_page - 1]);
                    $prev_url = add_query_arg($prev_params, $base_url);
                    echo '<a href="' . esc_url($prev_url) . '" class="pagination-link" data-page="' . ($current_page - 1) . '" data-category="' . esc_attr($category) . '" data-query="' . esc_attr($query) . '">Previous</a>';
                } else {
                    echo '<span class="pagination-link disabled">Previous</span>';
                }
                echo '<span class="page-info">Page ' . ($current_page + 1) . ' of ' . $total_pages . '</span>';
                if ($current_page < $total_pages - 1 && $total_pages > 1) {
                    $next_params = array_merge($query_params, ['page' => $current_page + 1]);
                    $next_url = add_query_arg($next_params, $base_url);
                    echo '<a href="' . esc_url($next_url) . '" class="pagination-link" data-page="' . ($current_page + 1) . '" data-category="' . esc_attr($category) . '" data-query="' . esc_attr($query) . '">Next</a>';
                } else {
                    echo '<span class="pagination-link disabled">Next</span>';
                }
                ?>
            </div>
            <?php
        }
    }
    ?>
</main>
<?php get_footer(); ?>