<?php
  /*
   * Template Name: Home Page
   */
  get_header(); ?>
  <div class="home-content">
      <h1 id="welcome">Welcome to ITU Shop</h1>
      <?php
      $client_id = 'itu_publication';
      $client_secret = '7S0h4$NQK5$6';
      $token_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/authorizationserver/oauth/token';
      $base_api_url = 'https://api.cisz6lfhs9-ituintern1-s1-public.model-t.cc.commerce.ondemand.com/occ/v2/itu';
      
      // Validate $_GET parameters
      $current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] >= 0 ? intval($_GET['page']) : 0;
      
      // Build API URL
      $api_url = $base_api_url . '/products/search?currentPage=' . $current_page . '&fields=DEFAULT&pageSize=12&cache=' . time();
      
      // Get access token
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
          
          if (empty($access_token)) {
              echo '<p>Error: No access token received.</p>';
          } else {
              // Fetch products
              $response = wp_remote_get($api_url, array(
                  'headers' => array(
                      'Authorization' => 'Bearer ' . $access_token,
                  ),
              ));
              
              if (is_wp_error($response)) {
                  echo '<p>Error fetching products: ' . esc_html($response->get_error_message()) . '</p>';
              } else {
                  $body = json_decode(wp_remote_retrieve_body($response), true);
                  $products = $body['products'] ?? [];
                  $total_pages = $body['pagination']['totalPages'] ?? 1;
                  $total_results = $body['pagination']['totalResults'] ?? 0;
                  
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
                          $prev_url = add_query_arg('page', $current_page - 1, $base_url);
                          echo '<a href="' . esc_url($prev_url) . '" class="pagination-link" data-page="' . ($current_page - 1) . '">Previous</a>';
                      }
                      echo '<span class="page-info">Page ' . ($current_page + 1) . ' of ' . $total_pages . '</span>';
                      if ($current_page < $total_pages - 1 && $total_pages > 1) {
                          $next_url = add_query_arg('page', $current_page + 1, $base_url);
                          echo '<a href="' . esc_url($next_url) . '" class="pagination-link" data-page="' . ($current_page + 1) . '">Next</a>';
                      }
                      ?>
                  </div>
                  <?php
              }
          }
      }
      ?>
  </div>
  <?php get_footer(); ?>