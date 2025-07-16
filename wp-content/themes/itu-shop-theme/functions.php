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
    wp_enqueue_style('itu-shop-theme-style', get_stylesheet_uri());
}
add_action('wp_enqueue_scripts', 'itu_shop_theme_styles');

function itu_shop_theme_scripts() {
    wp_enqueue_script('itu-shop-theme-script', get_template_directory_uri() . '/script.js', array(), null, true);
    // Localize script with REST URL and nonce
    wp_localize_script('itu-shop-theme-script', 'ituAjax', array(
        'rest_url' => rest_url('itu/v1/products'),
        'nonce' => wp_create_nonce('wp_rest')
    ));
}
add_action('wp_enqueue_scripts', 'itu_shop_theme_scripts');

// Debug REST registration
function itu_debug_rest_registration() {
    if (WP_DEBUG) {
        error_log('ITU Shop: functions.php loaded');
        error_log('ITU Shop: REST endpoint itu/v1/products registered');
    }
}
add_action('rest_api_init', 'itu_debug_rest_registration');

// Register REST endpoint
add_action('rest_api_init', function() {
    register_rest_route('itu/v1', '/products', array(
        'methods' => 'GET',
        'callback' => 'itu_fetch_products_rest',
        'permission_callback' => '__return_true', // Public access for testing
    ));
});

// REST handler for fetching products
function itu_fetch_products_rest(WP_REST_Request $request) {
    // Verify nonce
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('rest_forbidden', 'Nonce verification failed', array('status' => 403));
    }

    // Check for API credentials
    if (!defined('ITU_API_CLIENT_ID') || !defined('ITU_API_CLIENT_SECRET')) {
        return new WP_Error('rest_config_error', 'API credentials not configured in wp-config.php', array('status' => 500));
    }
    $client_id = ITU_API_CLIENT_ID;
    $client_secret = ITU_API_CLIENT_SECRET;

    $token_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/authorizationserver/oauth/token';
    $base_api_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/occ/v2/itu';
    
    $page = $request->get_param('page') && is_numeric($request->get_param('page')) && $request->get_param('page') >= 0 ? intval($request->get_param('page')) : 0;
    $api_url = $base_api_url . '/products/search?currentPage=' . $page . '&fields=DEFAULT&pageSize=12';
    
    // Debug API URL
    if (WP_DEBUG) {
        error_log('ITU Shop: Fetching products from ' . $api_url);
    }

    // Cache access token
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
            return new WP_Error('rest_token_error', 'Error fetching token: ' . $token_response->get_error_message(), array('status' => 500));
        }
        
        $token_body = json_decode(wp_remote_retrieve_body($token_response), true);
        $access_token = $token_body['access_token'] ?? '';
        if ($access_token) {
            set_transient('itu_access_token', $access_token, $token_body['expires_in'] - 60);
        }
    }
    
    if (empty($access_token)) {
        return new WP_Error('rest_token_error', 'No access token received', array('status' => 500));
    }
    
    $response = wp_remote_get($api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Cache-Control' => 'no-cache'
        ),
    ));
    
    if (is_wp_error($response)) {
        return new WP_Error('rest_api_error', 'Error fetching products: ' . $response->get_error_message(), array('status' => 500));
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $products = $body['products'] ?? [];
    $total_pages = $body['pagination']['totalPages'] ?? 1;
    
    // Debug response
    if (WP_DEBUG) {
        error_log('ITU Shop: API response - Total Pages: ' . $total_pages . ', Products: ' . count($products));
    }

    // Return raw JSON
    return array(
        'products' => $products,
        'total_pages' => $total_pages,
        'product_count' => count($products),
        'links' => array(
            'prev' => $page > 0 ? rest_url('itu/v1/products?page=' . ($page - 1)) : null,
            'next' => $page < $total_pages - 1 ? rest_url('itu/v1/products?page=' . ($page + 1)) : null
        )
    );
}
?>