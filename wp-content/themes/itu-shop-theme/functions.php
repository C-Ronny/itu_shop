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
      // Localize script with nonce
      wp_localize_script('itu-shop-theme-script', 'ituAjax', array(
          'ajax_url' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('itu_fetch_products_nonce')
      ));
  }
  add_action('wp_enqueue_scripts', 'itu_shop_theme_scripts');

  // Debug AJAX registration
  function itu_debug_ajax_registration() {
      if (WP_DEBUG) {
          error_log('ITU Shop: functions.php loaded');
          error_log('ITU Shop: AJAX action fetch_products registered');
      }
  }
  add_action('init', 'itu_debug_ajax_registration');

  // AJAX handler for fetching products
  function itu_fetch_products() {
      // Remove nonce verification for testing
      /*
      if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'itu_fetch_products_nonce')) {
          wp_send_json_error(array('message' => 'Nonce verification failed'));
          wp_die();
      }
      */

      $client_id = 'itu_publication';
      $client_secret = '7S0h4$NQK5$6';
      $token_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/authorizationserver/oauth/token';
      $base_api_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/occ/v2/itu';
      
      $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] >= 0 ? intval($_GET['page']) : 0;
      $api_url = $base_api_url . '/products/search?currentPage=' . $page . '&fields=DEFAULT&pageSize=12&cache=' . time();
      
      // Debug API URL
      if (WP_DEBUG) {
          error_log('ITU Shop: Fetching products from ' . $api_url);
      }

      $token_response = wp_remote_post($token_url, array(
          'body' => array(
              'grant_type' => 'client_credentials',
              'client_id' => $client_id,
              'client_secret' => $client_secret,
          ),
      ));
      
      if (is_wp_error($token_response)) {
          wp_send_json_error(array('message' => 'Error fetching token: ' . $token_response->get_error_message()));
          wp_die();
      }
      
      $token_body = json_decode(wp_remote_retrieve_body($token_response), true);
      $access_token = $token_body['access_token'] ?? '';
      
      if (empty($access_token)) {
          wp_send_json_error(array('message' => 'No access token received'));
          wp_die();
      }
      
      $response = wp_remote_get($api_url, array(
          'headers' => array(
              'Authorization' => 'Bearer ' . $access_token,
          ),
      ));
      
      if (is_wp_error($response)) {
          wp_send_json_error(array('message' => 'Error fetching products: ' . $response->get_error_message()));
          wp_die();
      }
      
      $body = json_decode(wp_remote_retrieve_body($response), true);
      $products = $body['products'] ?? [];
      $total_pages = $body['pagination']['totalPages'] ?? 1;
      
      // Debug response
      if (WP_DEBUG) {
          error_log('ITU Shop: API response - Total Pages: ' . $total_pages . ', Products: ' . count($products));
      }

      ob_start();
      if (empty($products)) {
          echo '<p>No products available.</p>';
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
      $html = ob_get_clean();
      
      wp_send_json_success(array(
          'html' => $html,
          'total_pages' => $total_pages,
          'product_count' => count($products)
      ));
      
      wp_die();
  }
  add_action('wp_ajax_fetch_products', 'itu_fetch_products');
  add_action('wp_ajax_nopriv_fetch_products', 'itu_fetch_products');
  ?>