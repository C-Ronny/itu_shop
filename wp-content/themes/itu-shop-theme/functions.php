<?php
// Start session for cart
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Register Navigation Menu
function itu_shop_theme_setup() {
    register_nav_menus(
        array(
            'primary' => __('Primary Menu', 'itu-shop-theme'),
        )
    );
}
add_action('after_setup_theme', 'itu_shop_theme_setup');

// Enqueue Styles
function itu_shop_theme_styles() {
    wp_enqueue_style('itu-shop-theme-style', get_template_directory_uri() . '/style.css', array(), '1.1.1'); // Updated version
}
add_action('wp_enqueue_scripts', 'itu_shop_theme_styles');

// Enqueue Scripts and Localize
function itu_shop_theme_scripts() {
    if (is_page_template('home.php')) {
        wp_enqueue_script('itu-shop-theme-script', get_template_directory_uri() . '/script.js', array(), '1.1.1', true); // Updated version
        wp_localize_script('itu-shop-theme-script', 'ituAjax', array(
            'rest_url' => rest_url('itu/v1/products'),
            'categories_url' => rest_url('itu/v1/categories'), // Ensure categories URL is localized
            'nonce' => wp_create_nonce('wp_rest'),
            'home_url' => home_url('/')
        ));
    }
    // Enqueue script-product.js only on the product details page
    if (get_query_var('itu_product') && get_query_var('product_code')) {
        wp_enqueue_script('itu-shop-product-script', get_template_directory_uri() . '/script-product.js', array(), '1.0', true);
    }
    // Enqueue script-cart.js on both cart and single product pages
    if (get_query_var('itu_cart') || (get_query_var('itu_product') && get_query_var('product_code'))) {
        wp_enqueue_script('itu-shop-cart-script', get_template_directory_uri() . '/script-cart.js', array(), '1.0.1', true); // Updated version
        wp_localize_script('itu-shop-cart-script', 'ituAjax', array(
            'rest_url_cart_add' => rest_url('itu/v1/cart/add'),
            'rest_url_cart_update' => rest_url('itu/v1/cart/update'),
            'rest_url_cart_remove' => rest_url('itu/v1/cart/remove'),
            'nonce' => wp_create_nonce('wp_rest')
        ));
    }
}
add_action('wp_enqueue_scripts', 'itu_shop_theme_scripts');

// Debug REST registration
function itu_debug_rest_registration() {
    if (WP_DEBUG) {
        error_log('ITU Shop: functions.php loaded');
        error_log('ITU Shop: REST endpoint itu/v1/products registered');
        error_log('ITU Shop: REST endpoint itu/v1/categories registered');
        error_log('ITU Shop: REST endpoint itu/v1/product/(?P<product_code>[a-zA-Z0-9-]+) registered');
        error_log('ITU Shop: REST endpoint itu/v1/cart/add registered');
        error_log('ITU Shop: REST endpoint itu/v1/cart/update registered');
        error_log('ITU Shop: REST endpoint itu/v1/cart/remove registered');
    }
}
add_action('rest_api_init', 'itu_debug_rest_registration');


// Define API credentials
if (!defined('ITU_API_CLIENT_ID')) {
    define('ITU_API_CLIENT_ID', 'ITU_API_CLIENT_ID_placeholder');
}
if (!defined('ITU_API_CLIENT_SECRET')) {
    define('ITU_API_CLIENT_SECRET', 'ITU_API_CLIENT_SECRET_placeholder');
}

// Function to get access token from Commerce API
function itu_get_access_token() {
    $client_id = ITU_API_CLIENT_ID;
    $client_secret = ITU_API_CLIENT_SECRET;
    $token_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/authorizationserver/oauth/token';

    $args = array(
        'body' => 'client_id=' . urlencode($client_id) . '&client_secret=' . urlencode($client_secret) . '&grant_type=client_credentials',
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded',
        ),
        'method' => 'POST',
        'timeout' => 15, // Increase timeout for API calls
    );

    $response = wp_remote_post($token_url, $args);

    if (is_wp_error($response)) {
        error_log('ITU Shop: Token API Error: ' . $response->get_error_message());
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['access_token'] ?? null;
}

// Function to fetch products from Commerce API
function itu_fetch_products_from_api($page = 0, $query = '', $category = '') {
    $access_token = itu_get_access_token();
    if (!$access_token) {
        return new WP_Error('api_error', 'Could not get access token', array('status' => 500));
    }

    $base_api_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/occ/v2/itu';
    $search_url = $base_api_url . '/products/search?pageSize=12&currentPage=' . intval($page); // pageSize=12 as per API integration

    if (!empty($query)) {
        $search_url .= '&query=' . urlencode($query);
    }
    if (!empty($category)) {
        $search_url .= '&query=' . urlencode(':relevance:category:' . $category);
    }

    $response = wp_remote_get($search_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Cache-Control' => 'no-cache' // Bypass cache for product search
        ),
        'timeout' => 15, // Increase timeout for API calls
    ));

    if (is_wp_error($response)) {
        error_log('ITU Shop: Error fetching products: ' . $response->get_error_message());
        return new WP_Error('api_error', 'Could not fetch products', array('status' => 500));
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body;
}

// REST API Endpoint for Products
function itu_products_rest_endpoint(WP_REST_Request $request) {
    $page = $request->get_param('page');
    $query = $request->get_param('query');
    $category = $request->get_param('category');

    $products_data = itu_fetch_products_from_api($page, $query, $category);

    if (is_wp_error($products_data)) {
        return $products_data;
    }

    $products = $products_data['products'] ?? [];
    $pagination = $products_data['pagination'] ?? [];

    $response_data = array(
        'products' => array_map(function($product) {
            // Ensure necessary fields are present, provide defaults if not
            $price = $product['price']['formattedValue'] ?? 'N/A';
            $stock_status = $product['stock']['stockLevelStatus'] ?? 'Unknown';
            $product_code = $product['code'] ?? 'N/A';
            $product_name = $product['name'] ?? 'Untitled Product';
            $image_url = $product['images'][0]['url'] ?? '';

            return [
                'code' => $product_code,
                'name' => $product_name,
                'price' => $price,
                'stock_status' => $stock_status,
                'image_url' => $image_url
            ];
        }, $products),
        'pagination' => [
            'currentPage' => $pagination['currentPage'] ?? 0,
            'totalPages' => $pagination['totalPages'] ?? 1,
            'totalResults' => $pagination['totalResults'] ?? 0,
        ]
    );

    return new WP_REST_Response($response_data, 200);
}

// Function to fetch categories from Commerce API
function itu_fetch_categories_from_api() {
    $categories = get_transient('itu_categories');
    if ($categories === false) { // Only fetch if not in transient
        $access_token = itu_get_access_token();
        if (!$access_token) {
            return new WP_Error('rest_token_error', 'No access token received', array('status' => 500));
        }

        $base_api_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/occ/v2/itu';
        $api_url = $base_api_url . '/categories?fields=DEFAULT';

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Cache-Control' => 'no-cache'
            ),
            'timeout' => 15, // Increase timeout
        ));

        if (is_wp_error($response)) {
            error_log('ITU Shop: Error fetching categories: ' . $response->get_error_message());
            return new WP_Error('rest_api_error', 'Categories Unavailable', array('status' => 500));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $categories = $body['categories'] ?? [];
        set_transient('itu_categories', $categories, DAY_IN_SECONDS); // Cache for a day
    }
    return $categories;
}


// Function to get category counts based on current cart
function itu_get_category_counts() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $cart_items = $_SESSION['itu_cart'] ?? [];
    $counts = [];

    // Fetch all products to get their categories - NOTE: This fetches only the first page.
    // For accurate counts across all products, a more robust API call or caching strategy would be needed.
    // For now, it will base counts on products returned by the initial product search API call.
    $all_products_data = itu_fetch_products_from_api(0, '', '');
    $all_products = $all_products_data['products'] ?? [];

    foreach ($cart_items as $product_code => $quantity) {
        // Find the product in the fetched data to get its categories
        $found_product = null;
        foreach ($all_products as $product) {
            if ($product['code'] === $product_code) {
                $found_product = $product;
                break;
            }
        }

        if ($found_product && !empty($found_product['categories'])) {
            foreach ($found_product['categories'] as $category) {
                if (isset($category['name'])) {
                    $category_name = $category['name'];
                    if (!isset($counts[$category_name])) {
                        $counts[$category_name] = 0;
                    }
                    $counts[$category_name] += $quantity;
                }
            }
        }
    }
    return $counts;
}

// REST API Endpoint for Categories
function itu_categories_rest_endpoint() {
    $categories = itu_fetch_categories_from_api();
    if (is_wp_error($categories)) {
        return $categories;
    }

    $counts = itu_get_category_counts();
    $filtered_categories = array_filter($categories, function($cat) {
        // Exclude specific categories if necessary, e.g., 'Brands' or 'Configurations'
        return $cat['name'] !== 'Brands' && $cat['name'] !== 'Configurations';
    });

    // Ensure response is always an array
    $response = array_values(array_map(function($cat) use ($counts) {
        return array(
            'id' => $cat['id'],
            'name' => $cat['name'],
            'count' => $counts[$cat['name']] ?? 0 // Get count from the calculated counts
        );
    }, $filtered_categories));

    return new WP_REST_Response($response, 200);
}


// Internal function for adding to cart
function itu_add_to_cart_rest_internal($product_code, $quantity) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['itu_cart'])) {
        $_SESSION['itu_cart'] = array();
    }

    $product_code = trim($product_code); // Trim whitespace

    // Add or update product quantity
    if (isset($_SESSION['itu_cart'][$product_code])) {
        $_SESSION['itu_cart'][$product_code] += $quantity;
    } else {
        $_SESSION['itu_cart'][$product_code] = $quantity;
    }

    if (WP_DEBUG) {
        error_log('ITU Shop: Added to cart - Product: ' . $product_code . ', Quantity: ' . $_SESSION['itu_cart'][$product_code]);
    }

    return array(
        'success' => true,
        'message' => 'Product added to cart',
        'cart' => $_SESSION['itu_cart']
    );
}

// REST API Endpoint for adding to cart
function itu_add_to_cart_rest(WP_REST_Request $request) {
    $product_code = sanitize_text_field($request->get_param('product_code'));
    $quantity = absint($request->get_param('quantity')); // Ensure quantity is a positive integer

    if (empty($product_code) || $quantity <= 0) {
        return new WP_Error('rest_invalid_data', 'Invalid product code or quantity', array('status' => 400));
    }

    // Nonce verification
    if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
        return new WP_Error('rest_forbidden', 'Nonce verification failed', array('status' => 403));
    }

    return itu_add_to_cart_rest_internal($product_code, $quantity);
}


// Internal function for removing from cart (can be called by update if quantity is 0)
function itu_remove_from_cart_rest_internal($product_code) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['itu_cart']) || !array_key_exists($product_code, $_SESSION['itu_cart'])) {
        error_log('ITU Shop: Remove from cart failed - Product not in cart: ' . $product_code);
        return new WP_Error('rest_not_found', 'Product not in cart', array('status' => 404));
    }

    unset($_SESSION['itu_cart'][$product_code]);

    if (WP_DEBUG) {
        error_log('ITU Shop: Removed from cart - Product: ' . $product_code);
    }

    return array(
        'success' => true,
        'message' => 'Product removed from cart',
        'cart' => $_SESSION['itu_cart']
    );
}


// REST API Endpoint for updating cart quantity
function itu_update_cart_quantity_rest(WP_REST_Request $request) {
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        error_log('ITU Shop: Update cart nonce verification failed');
        return new WP_Error('rest_forbidden', 'Nonce verification failed', array('status' => 403));
    }

    $product_code = trim(sanitize_text_field($request->get_param('product_code')));
    $quantity = absint($request->get_param('quantity'));

    if ($quantity <= 0) {
        // If quantity is 0 or less, treat it as a remove request
        return itu_remove_from_cart_rest_internal($product_code);
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['itu_cart']) || !array_key_exists($product_code, $_SESSION['itu_cart'])) {
        error_log('ITU Shop: Update cart quantity failed - Product not in cart: ' . $product_code);
        return new WP_Error('rest_not_found', 'Product not in cart', array('status' => 404));
    }

    $_SESSION['itu_cart'][$product_code] = $quantity;

    if (WP_DEBUG) {
        error_log('ITU Shop: Updated cart - Product: ' . $product_code . ', New Quantity: ' . $quantity);
    }

    return array(
        'success' => true,
        'message' => 'Cart quantity updated',
        'cart' => $_SESSION['itu_cart']
    );
}

// REST API Endpoint for removing from cart
function itu_remove_from_cart_rest(WP_REST_Request $request) {
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        error_log('ITU Shop: Remove from cart nonce verification failed');
        return new WP_Error('rest_forbidden', 'Nonce verification failed', array('status' => 403));
    }

    $product_code = trim(sanitize_text_field($request->get_param('product_code')));
    return itu_remove_from_cart_rest_internal($product_code);
}


// Register REST API Endpoints
function itu_shop_register_rest_routes() {
    register_rest_route('itu/v1', '/products', array(
        'methods' => 'GET',
        'callback' => 'itu_products_rest_endpoint',
        'permission_callback' => '__return_true', // No specific permission needed for product listings
    ));

    register_rest_route('itu/v1', '/categories', array(
        'methods' => 'GET',
        'callback' => 'itu_categories_rest_endpoint',
        'permission_callback' => '__return_true', // No specific permission needed for categories
    ));

    register_rest_route('itu/v1', '/product/(?P<product_code>[a-zA-Z0-9-]+)', array(
        'methods' => 'GET',
        'callback' => 'itu_get_single_product_rest_endpoint',
        'args' => array(
            'product_code' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
        'permission_callback' => '__return_true',
    ));

    register_rest_route('itu/v1', '/cart/add', array(
        'methods' => 'POST',
        'callback' => 'itu_add_to_cart_rest',
        'permission_callback' => function(WP_REST_Request $request) {
            return wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest');
        },
    ));

    register_rest_route('itu/v1', '/cart/update', array(
        'methods' => 'POST',
        'callback' => 'itu_update_cart_quantity_rest',
        'permission_callback' => function(WP_REST_Request $request) {
            return wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest');
        },
    ));

    register_rest_route('itu/v1', '/cart/remove', array(
        'methods' => 'POST',
        'callback' => 'itu_remove_from_cart_rest',
        'permission_callback' => function(WP_REST_Request $request) {
            return wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest');
        },
    ));
}
add_action('rest_api_init', 'itu_shop_register_rest_routes');


// REST API Endpoint for Single Product
function itu_get_single_product_rest_endpoint(WP_REST_Request $request) {
    $product_code = sanitize_text_field($request->get_param('product_code'));

    $access_token = itu_get_access_token();
    if (!$access_token) {
        return new WP_Error('api_error', 'Could not get access token', array('status' => 500));
    }

    $base_api_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/occ/v2/itu';
    $api_url = $base_api_url . '/products/' . urlencode($product_code);

    $response = wp_remote_get($api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Cache-Control' => 'no-cache' // Bypass cache for product search
        ),
        'timeout' => 15, // Increase timeout for API calls
    ));

    if (is_wp_error($response)) {
        error_log('ITU Shop: Error fetching single product ' . $product_code . ': ' . $response->get_error_message());
        return new WP_Error('api_error', 'Could not fetch product details', array('status' => 500));
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body)) {
        return new WP_Error('product_not_found', 'Product not found', array('status' => 404));
    }

    return new WP_REST_Response($body, 200);
}


// Add custom rewrite rules for product and cart pages
function itu_shop_rewrite_rules() {
    add_rewrite_rule(
        '^product/([^/]+)/?$',
        'index.php?itu_product=1&product_code=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^cart/?$',
        'index.php?itu_cart=1',
        'top'
    );
}
add_action('init', 'itu_shop_rewrite_rules');

// Add query vars
function itu_shop_query_vars($vars) {
    $vars[] = 'itu_product';
    $vars[] = 'product_code';
    $vars[] = 'itu_cart';
    return $vars;
}
add_filter('query_vars', 'itu_shop_query_vars');

// Template Redirect for custom pages
function itu_shop_template_include($template) {
    if (get_query_var('itu_product') && get_query_var('product_code')) {
        $new_template = locate_template('single-product.php');
        if ($new_template) {
            if (WP_DEBUG) {
                error_log('ITU Shop: Loading single-product.php for product_code: ' . get_query_var('product_code'));
            }
            return $new_template;
        } else {
            if (WP_DEBUG) {
                error_log('ITU Shop: single-product.php template not found');
            }
        }
    }
    if (get_query_var('itu_cart')) {
        $new_template = locate_template('cart.php');
        if ($new_template) {
            if (WP_DEBUG) {
                error_log('ITU Shop: Loading cart.php');
            }
            return $new_template;
        } else {
            if (WP_DEBUG) {
                error_log('ITU Shop: cart.php template not found');
            }
        }
    }
    return $template;
}
add_filter('template_include', 'itu_shop_template_include');

// Flush rewrite rules on theme activation or if not flushed
function itu_shop_flush_rewrite_rules() {
    if (get_transient('itu_shop_rewrite_flushed') !== '1') {
        itu_shop_rewrite_rules();
        flush_rewrite_rules();
        set_transient('itu_shop_rewrite_flushed', '1', WEEK_IN_SECONDS);
        if (WP_DEBUG) {
            error_log('ITU Shop: Rewrite rules flushed.');
        }
    }
}
add_action('init', 'itu_shop_flush_rewrite_rules');

// Clear rewrite rules transient on theme deactivation
function itu_shop_deactivate() {
    delete_transient('itu_shop_rewrite_flushed');
    if (WP_DEBUG) {
        error_log('ITU Shop: Rewrite rules transient deleted on deactivation.');
    }
}
register_deactivation_hook(__FILE__, 'itu_shop_deactivate');