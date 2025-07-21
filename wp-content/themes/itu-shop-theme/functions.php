<?php
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
    wp_enqueue_script('itu-shop-theme-script', get_template_directory_uri() . '/script.js', array(), '1.1', true);
    wp_localize_script('itu-shop-theme-script', 'ituAjax', array(
        'rest_url' => rest_url('itu/v1/products'),
        'categories_url' => rest_url('itu/v1/categories'),
        'nonce' => wp_create_nonce('wp_rest')
    ));
}
add_action('wp_enqueue_scripts', 'itu_shop_theme_scripts');

// Debug REST registration
function itu_debug_rest_registration() {
    if (WP_DEBUG) {
        error_log('ITU Shop: functions.php loaded');
        error_log('ITU Shop: REST endpoint itu/v1/products registered');
        error_log('ITU Shop: REST endpoint itu/v1/categories registered');
    }
}
add_action('rest_api_init', 'itu_debug_rest_registration');

// Register REST endpoints
add_action('rest_api_init', function() {
    register_rest_route('itu/v1', '/products', array(
        'methods' => 'GET',
        'callback' => 'itu_fetch_products_rest',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('itu/v1', '/categories', array(
        'methods' => 'GET',
        'callback' => 'itu_fetch_categories_rest',
        'permission_callback' => '__return_true',
    ));
});

// Compute and cache category counts
function itu_get_category_counts() {
    $counts = get_transient('itu_category_counts');
    if (false !== $counts) {
        error_log('ITU Shop: Using cached category counts');
        return $counts;
    }

    $counts = ['' => 0]; // '' for All Products
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
            error_log('ITU Shop: Error fetching token for counts: ' . $token_response->get_error_message());
            return $counts;
        }
        
        $token_body = json_decode(wp_remote_retrieve_body($token_response), true);
        $access_token = $token_body['access_token'] ?? '';
        if ($access_token) {
            set_transient('itu_access_token', $access_token, $token_body['expires_in'] - 60);
            error_log('ITU Shop: Access token cached for counts');
        }
    }
    
    if (empty($access_token)) {
        error_log('ITU Shop: No access token for counts');
        return $counts;
    }

    // Get categories to initialize counts
    $categories = get_transient('itu_categories');
    if (false === $categories) {
        $api_url = $base_api_url . '/catalogs/ituProductCatalog/Online';
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Cache-Control' => 'no-cache'
            ),
        ));
        
        if (is_wp_error($response)) {
            error_log('ITU Shop: Error fetching categories for counts: ' . $response->get_error_message());
            return $counts;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $categories = $body['categories'] ?? [];
        set_transient('itu_categories', $categories, DAY_IN_SECONDS);
    }

    foreach ($categories as $category) {
        if ($category['name'] !== 'Brands' && $category['name'] !== 'Configurations') {
            $counts[$category['name']] = 0;
        }
    }

    // Create a map of normalized category names to original names
    $category_map = [];
    foreach ($categories as $cat) {
        if ($cat['name'] === 'Brands' || $cat['name'] === 'Configurations') {
            continue;
        }
        $name = strtolower($cat['name']);
        $name = preg_replace('/\s*\(.*?\)/', '', $name);
        $name = preg_replace('/\s+/', '-', $name);
        $name = rtrim($name, '-');
        $category_map[$name] = $cat['name'];
    }

    // Fetch all products to count by category
    $page = 0;
    $total_pages = 1;
    while ($page < $total_pages) {
        $api_url = $base_api_url . '/products/search?currentPage=' . $page . '&fields=DEFAULT&pageSize=12';
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Cache-Control' => 'no-cache'
            ),
        ));
        
        if (is_wp_error($response)) {
            error_log('ITU Shop: Error fetching products for counts: ' . $response->get_error_message());
            break;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $products = $body['products'] ?? [];
        $total_pages = $body['pagination']['totalPages'] ?? 1;
        $counts[''] += count($products); // Total for All Products
        
        foreach ($products as $product) {
            $url_parts = explode('/', $product['url']);
            if (count($url_parts) < 2) {
                error_log('ITU Shop: Invalid product URL: ' . $product['url']);
                continue;
            }
            $category_segment = urldecode($url_parts[1]);
            $normalized_category = strtolower($category_segment);
            $normalized_category = preg_replace('/\s*\(.*?\)/', '', $normalized_category);
            $normalized_category = preg_replace('/\s+/', '-', $normalized_category);
            $normalized_category = rtrim($normalized_category, '-');
            
            if (isset($category_map[$normalized_category])) {
                $category_name = $category_map[$normalized_category];
                $counts[$category_name]++;
            } else {
                error_log('ITU Shop: Unmatched category segment: ' . $category_segment . ' (Normalized: ' . $normalized_category . ')');
            }
        }
        $page++;
    }

    set_transient('itu_category_counts', $counts, DAY_IN_SECONDS);
    error_log('ITU Shop: Cached category counts: ' . print_r($counts, true));
    return $counts;
}

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
    
    $page = $request->get_param('page') && is_numeric($request->get_param('page')) && $request->get_param('page') >= 0 ? intval($request->get_param('page')) : 0;
    $api_url = $base_api_url . '/products/search?currentPage=' . $page . '&fields=DEFAULT&pageSize=12';
    
    error_log('ITU Shop: Fetching products from ' . $api_url);

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

// REST handler for fetching categories
function itu_fetch_categories_rest(WP_REST_Request $request) {
    error_log('ITU Shop: Accessing categories endpoint');

    if (!defined('ITU_API_CLIENT_ID') || !defined('ITU_API_CLIENT_SECRET')) {
        error_log('ITU Shop: API credentials not configured in wp-config.php');
        return new WP_Error('rest_config_error', 'API credentials not configured in wp-config.php', array('status' => 500));
    }
    $client_id = ITU_API_CLIENT_ID;
    $client_secret = ITU_API_CLIENT_SECRET;

    $token_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/authorizationserver/oauth/token';
    $api_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/occ/v2/itu/catalogs/ituProductCatalog/Online';
    
    error_log('ITU Shop: Fetching categories from ' . $api_url);

    $categories = get_transient('itu_categories');
    error_log('ITU Shop: Transient itu_categories: ' . (false === $categories ? 'Not cached' : 'Cached'));
    if (false === $categories) {
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
                error_log('ITU Shop: Access token cached for categories');
            }
        }
        
        if (empty($access_token)) {
            error_log('ITU Shop: No access token received for categories');
            return new WP_Error('rest_token_error', 'No access token received', array('status' => 500));
        }
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Cache-Control' => 'no-cache'
            ),
        ));
        
        if (is_wp_error($response)) {
            error_log('ITU Shop: Error fetching categories: ' . $response->get_error_message());
            return new WP_Error('rest_api_error', 'Categories Unavailable', array('status' => 500));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        error_log('ITU Shop: Categories API response: ' . print_r($body, true));
        $categories = $body['categories'] ?? [];
        set_transient('itu_categories', $categories, DAY_IN_SECONDS);
    }
    
    $counts = itu_get_category_counts();
    $filtered_categories = array_filter($categories, function($cat) {
        return $cat['name'] !== 'Brands' && $cat['name'] !== 'Configurations';
    });
    
    // Ensure response is always an array
    $response = array_values(array_map(function($cat) use ($counts) {
        return array(
            'id' => $cat['id'],
            'name' => $cat['name'],
            'count' => $counts[$cat['name']] ?? 0
        );
    }, $filtered_categories));

    error_log('ITU Shop: Categories endpoint response: ' . print_r($response, true));
    return rest_ensure_response($response);
}
?>