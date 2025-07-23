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
    wp_enqueue_style('itu-shop-theme-style', get_template_directory_uri() . '/style.css', array(), '1.1');
}
add_action('wp_enqueue_scripts', 'itu_shop_theme_styles');

// Enqueue Scripts and Localize
function itu_shop_theme_scripts() {
    if (is_page_template('home.php')) {
        wp_enqueue_script('itu-shop-theme-script', get_template_directory_uri() . '/script.js', array(), '1.1', true);
        wp_localize_script('itu-shop-theme-script', 'ituAjax', array(
            'rest_url' => rest_url('itu/v1/products'),
            'nonce' => wp_create_nonce('wp_rest'),
            'home_url' => home_url('/')
        ));
    }
    if (get_query_var('itu_product') && get_query_var('product_code')) {
        wp_enqueue_script('itu-shop-product-script', get_template_directory_uri() . '/script-product.js', array(), '1.1', true);
        wp_enqueue_script('itu-shop-cart-script', get_template_directory_uri() . '/script-cart.js', array(), '1.1', true);
        wp_localize_script('itu-shop-cart-script', 'ituAjax', array(
            'rest_url_cart_add' => rest_url('itu/v1/cart/add'),
            'rest_url_cart_update' => rest_url('itu/v1/cart/update'),
            'rest_url_cart_remove' => rest_url('itu/v1/cart/remove'),
            'nonce' => wp_create_nonce('wp_rest')
        ));
        if (WP_DEBUG) {
            error_log('ITU Shop: Enqueued script-product.js and script-cart.js for product page');
        }
    }
    if (is_page_template('cart.php')) {
        wp_enqueue_script('itu-shop-cart-script', get_template_directory_uri() . '/script-cart.js', array(), '1.1', true);
        wp_localize_script('itu-shop-cart-script', 'ituAjax', array(
            'rest_url_cart_add' => rest_url('itu/v1/cart/add'),
            'rest_url_cart_update' => rest_url('itu/v1/cart/update'),
            'rest_url_cart_remove' => rest_url('itu/v1/cart/remove'),
            'nonce' => wp_create_nonce('wp_rest')
        ));
        if (WP_DEBUG) {
            error_log('ITU Shop: Enqueued script-cart.js for cart page');
        }
    }
}
add_action('wp_enqueue_scripts', 'itu_shop_theme_scripts');

// Debug REST registration
function itu_debug_rest_registration() {
    if (WP_DEBUG) {
        error_log('ITU Shop: functions.php loaded');
        error_log('ITU Shop: REST endpoints registered: products, cart/add, cart/update, cart/remove');
    }
}
add_action('rest_api_init', 'itu_debug_rest_registration');

// Register REST endpoints
add_action('rest_api_init', function() {
    // Products endpoint
    register_rest_route('itu/v1', '/products(?:/(?P<productCode>[^/]+))?', array(
        'methods' => 'GET',
        'callback' => 'itu_fetch_products_rest',
        'permission_callback' => '__return_true',
        'args' => array(
            'productCode' => array(
                'validate_callback' => function($param) {
                    return is_string($param) && !empty($param);
                }
            ),
        ),
    ));

    // Add to cart endpoint
    register_rest_route('itu/v1', '/cart/add', array(
        'methods' => 'POST',
        'callback' => 'itu_add_to_cart_rest',
        'permission_callback' => '__return_true',
        'args' => array(
            'product_code' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_string($param) && !empty($param);
                }
            ),
            'quantity' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param >= 1 && $param <= 10;
                }
            ),
        ),
    ));

    // Update cart quantity endpoint
    register_rest_route('itu/v1', '/cart/update', array(
        'methods' => 'POST',
        'callback' => 'itu_update_cart_quantity_rest',
        'permission_callback' => '__return_true',
        'args' => array(
            'product_code' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_string($param) && !empty($param);
                }
            ),
            'quantity' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param >= 1 && $param <= 10;
                }
            ),
        ),
    ));

    // Remove from cart endpoint
    register_rest_route('itu/v1', '/cart/remove', array(
        'methods' => 'POST',
        'callback' => 'itu_remove_from_cart_rest',
        'permission_callback' => '__return_true',
        'args' => array(
            'product_code' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_string($param) && !empty($param);
                }
            ),
        ),
    ));
});

// REST handler for fetching products
function itu_fetch_products_rest(WP_REST_Request $request) {
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        error_log('ITU Shop: Products nonce verification failed');
        return new WP_Error('rest_forbidden', 'Nonce verification failed', array('status' => 403));
    }

    if (!defined('ITU_API_CLIENT_ID') || !defined('ITU_API_CLIENT_SECRET')) {
        error_log('ITU Shop: API credentials not configured in wp-config.php');
        return new WP_Error('rest_config_error', 'API credentials not configured in wp-config.php', array('status' => 500));
    }
    $client_id = ITU_API_CLIENT_ID;
    $client_secret = ITU_API_CLIENT_SECRET;

    $token_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/authorizationserver/oauth/token';
    $base_api_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/occ/v2/itu';

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
            error_log('ITU Shop: Error fetching token: ' . $token_response->get_error_message());
            return new WP_Error('rest_token_error', 'Error fetching token: ' . $token_response->get_error_message(), array('status' => 500));
        }

        $token_body = json_decode(wp_remote_retrieve_body($token_response), true);
        $access_token = $token_body['access_token'] ?? '';
        if ($access_token) {
            set_transient('itu_access_token', $access_token, $token_body['expires_in'] - 60);
            error_log('ITU Shop: Access token cached');
        }
    }

    if (empty($access_token)) {
        error_log('ITU Shop: No access token received');
        return new WP_Error('rest_token_error', 'No access token received', array('status' => 500));
    }

    $productCode = $request->get_param('productCode');
    $page = $request->get_param('page') && is_numeric($request->get_param('page')) && $request->get_param('page') >= 0 ? intval($request->get_param('page')) : 0;

    if ($productCode) {
        // Fetch single product by ID
        $api_url = $base_api_url . '/products/' . urlencode($productCode) . '?fields=DEFAULT';
        error_log('ITU Shop: Fetching product by ID from ' . $api_url);
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Cache-Control' => 'no-cache'
            ),
        ));

        if (is_wp_error($response)) {
            error_log('ITU Shop: Error fetching product: ' . $response->get_error_message());
            return new WP_Error('rest_api_error', 'Error fetching product: ' . $response->get_error_message(), array('status' => 500));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['code'])) {
            error_log('ITU Shop: Product not found for code: ' . $productCode);
            return new WP_Error('rest_not_found', 'Product not found', array('status' => 404));
        }

        error_log('ITU Shop: Product fetched: ' . $body['name']);
        return rest_ensure_response($body);
    } else {
        // Fetch all products
        $api_url = $base_api_url . '/products/search?currentPage=' . $page . '&fields=DEFAULT&pageSize=12';
        error_log('ITU Shop: Fetching products from ' . $api_url);

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Cache-Control' => 'no-cache'
            ),
        ));

        if (is_wp_error($response)) {
            error_log('ITU Shop: Error fetching products: ' . $response->get_error_message());
            return new WP_Error('rest_api_error', 'Error fetching products: ' . $response->get_error_message(), array('status' => 500));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $products = $body['products'] ?? [];
        $total_pages = $body['pagination']['totalPages'] ?? 1;
        $total_results = $body['pagination']['totalResults'] ?? count($products);

        error_log('ITU Shop: API response - Total Pages: ' . $total_pages . ', Total Results: ' . $total_results . ', Products: ' . count($products));

        return array(
            'products' => $products,
            'total_pages' => $total_pages,
            'total_results' => $total_results,
            'links' => array(
                'prev' => $page > 0 ? rest_url('itu/v1/products?page=' . ($page - 1)) : null,
                'next' => $page < $total_pages - 1 ? rest_url('itu/v1/products?page=' . ($page + 1)) : null
            )
        );
    }
}

// Add to cart REST handler
function itu_add_to_cart_rest(WP_REST_Request $request) {
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        error_log('ITU Shop: Add to cart nonce verification failed');
        return new WP_Error('rest_forbidden', 'Nonce verification failed', array('status' => 403));
    }

    $product_code = sanitize_text_field($request->get_param('product_code'));
    $quantity = intval($request->get_param('quantity'));

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['itu_cart'])) {
        $_SESSION['itu_cart'] = array();
    }

    if (isset($_SESSION['itu_cart'][$product_code])) {
        $_SESSION['itu_cart'][$product_code] += $quantity;
        if ($_SESSION['itu_cart'][$product_code] > 10) {
            $_SESSION['itu_cart'][$product_code] = 10;
        }
    } else {
        $_SESSION['itu_cart'][$product_code] = $quantity;
    }

    if (WP_DEBUG) {
        error_log('ITU Shop: Added to cart - Product: ' . $product_code . ', Quantity: ' . $quantity);
    }

    return array(
        'success' => true,
        'message' => 'Product added to cart',
        'cart' => $_SESSION['itu_cart']
    );
}

// Update cart quantity REST handler
function itu_update_cart_quantity_rest(WP_REST_Request $request) {
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        error_log('ITU Shop: Update cart quantity nonce verification failed');
        return new WP_Error('rest_forbidden', 'Nonce verification failed', array('status' => 403));
    }

    $product_code = sanitize_text_field($request->get_param('product_code'));
    $quantity = intval($request->get_param('quantity'));

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['itu_cart']) || !isset($_SESSION['itu_cart'][$product_code])) {
        error_log('ITU Shop: Product not in cart: ' . $product_code);
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

// Remove from cart REST handler
function itu_remove_from_cart_rest(WP_REST_Request $request) {
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        error_log('ITU Shop: Remove from cart nonce verification failed');
        return new WP_Error('rest_forbidden', 'Nonce verification failed', array('status' => 403));
    }

    $product_code = sanitize_text_field($request->get_param('product_code'));

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['itu_cart']) || !isset($_SESSION['itu_cart'][$product_code])) {
        error_log('ITU Shop: Product not in cart: ' . $product_code);
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

// Add rewrite rules for product and cart pages
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
    if (WP_DEBUG) {
        error_log('ITU Shop: Rewrite rules added for product pages: product/([^/]+)/? -> index.php?itu_product=1&product_code=$matches[1]');
        error_log('ITU Shop: Rewrite rule added for cart page: cart/? -> index.php?itu_cart=1');
    }
}
add_action('init', 'itu_shop_rewrite_rules');

// Register query vars
function itu_shop_query_vars($query_vars) {
    $query_vars[] = 'itu_product';
    $query_vars[] = 'product_code';
    $query_vars[] = 'itu_cart';
    return $query_vars;
}
add_filter('query_vars', 'itu_shop_query_vars');

// Load templates
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
            error_log('ITU Shop: Rewrite rules flushed');
        }
    }
}
add_action('after_switch_theme', 'itu_shop_flush_rewrite_rules');
add_action('init', 'itu_shop_flush_rewrite_rules');
?>