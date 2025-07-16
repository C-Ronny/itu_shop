document.addEventListener('DOMContentLoaded', function() {
  const grid = document.getElementById('product-grid');
  const paginationLinks = document.querySelectorAll('.pagination-link');
  
  if (!grid) {
      console.error('Required element not found:', { grid });
      return;
  }

  const initialCards = Array.from(grid.getElementsByClassName('product-card'));
  console.log('Found ' + initialCards.length + ' product cards on load.');
  
  // Debug: Log all products
  initialCards.forEach((card, index) => {
      const cardName = card.querySelector('h3')?.textContent || 'Unknown';
      console.log(`Product ${index + 1}: "${cardName}"`);
  });

  // Handle pagination clicks
  paginationLinks.forEach(link => {
      link.addEventListener('click', function(e) {
          e.preventDefault();
          const page = this.getAttribute('data-page');
          console.log('Navigating to page:', page);
          fetchProducts(page);
      });
  });

  function fetchProducts(page) {
      // Fallback to server-side if ituAjax is undefined
      if (typeof ituAjax === 'undefined' || !ituAjax.rest_url) {
          console.warn('ituAjax not defined, falling back to server-side pagination');
          const url = new URL(window.location);
          url.searchParams.set('page', page);
          window.location.href = url.toString();
          return;
      }

      const apiUrl = `${ituAjax.rest_url}?page=${page}`;
      console.log('Fetching products from:', apiUrl);
      
      grid.innerHTML = '<div class="loading">Loading...</div>';
      
      fetch(apiUrl, {
          method: 'GET',
          headers: {
              'X-WP-Nonce': ituAjax.nonce
          }
      })
      .then(response => {
          console.log('Response status:', response.status, 'OK:', response.ok);
          return response.text().then(text => ({ response, text }));
      })
      .then(({ response, text }) => {
          let data;
          try {
              data = JSON.parse(text);
          } catch (e) {
              console.error('Parse error:', e, 'Response text:', text.substring(0, 200));
              throw new Error('Invalid JSON response');
          }
          if (response.ok && data.products) {
              renderProducts(data.products);
              updatePaginationLinks(page, data.total_pages, data.links);
              console.log(`Loaded ${data.product_count} products for page ${page}`);
          } else {
              console.error('Error fetching products:', data.message || 'Unknown error');
              grid.innerHTML = '<p>Error loading products: ' + (data.message || 'Unknown error') + '</p>';
          }
      })
      .catch(error => {
          console.error('Fetch error:', error);
          grid.innerHTML = '<p>Error loading products: ' + error.message + '</p>';
          // Fallback to server-side pagination
          console.warn('REST API failed, falling back to server-side pagination');
          const url = new URL(window.location);
          url.searchParams.set('page', page);
          window.location.href = url.toString();
      });
  }

  function renderProducts(products) {
      if (!products || products.length === 0) {
          grid.innerHTML = '<p>No products available.</p>';
          return;
      }

      let html = '';
      products.forEach(product => {
          const title = product.name || 'Unnamed Product';
          const price = product.price?.value || '0.00';
          const currency = product.price?.currencyIso || 'CHF';
          const stockStatus = product.stock?.stockLevelStatus || 'unknown';
          const productCode = product.code || '';

          html += `
              <div class="product-card">
                  <div class="image-placeholder"></div>
                  <h3>${escapeHtml(title)}</h3>
                  <p class="price">${escapeHtml(numberFormat(price, 2))} ${escapeHtml(currency)}</p>
                  <p class="stock-status">Stock: ${escapeHtml(stockStatus)}</p>
                  <a href="#" class="product-link" data-product-code="${escapeHtml(productCode)}">${escapeHtml(title)}</a>
              </div>
          `;
      });
      grid.innerHTML = html;
  }

  function escapeHtml(str) {
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
  }

  function numberFormat(value, decimals) {
      return Number(value).toFixed(decimals);
  }

  function updatePaginationLinks(currentPage, totalPages, links) {
      const paginationDiv = document.querySelector('.pagination');
      currentPage = parseInt(currentPage);
      totalPages = parseInt(totalPages);
      
      let html = '';
      const baseUrl = removeQueryParam(window.location.href, 'page');
      
      if (links.prev) {
          const prevPage = currentPage - 1;
          const prevUrl = addQueryParam(baseUrl, 'page', prevPage);
          html += `<a href="${prevUrl}" class="pagination-link" data-page="${prevPage}">Previous</a>`;
      }
      
      html += `<span class="page-info">Page ${currentPage + 1} of ${totalPages}</span>`;
      
      if (links.next) {
          const nextPage = currentPage + 1;
          const nextUrl = addQueryParam(baseUrl, 'page', nextPage);
          html += `<a href="${nextUrl}" class="pagination-link" data-page="${nextPage}">Next</a>`;
      }
      
      paginationDiv.innerHTML = html;
      
      // Reattach event listeners
      document.querySelectorAll('.pagination-link').forEach(link => {
          link.addEventListener('click', function(e) {
              e.preventDefault();
              const page = this.getAttribute('data-page');
              fetchProducts(page);
          });
      });
  }

  function removeQueryParam(url, param) {
      const urlObj = new URL(url);
      urlObj.searchParams.delete(param);
      return urlObj.toString();
  }

  function addQueryParam(baseUrl, param, value) {
      const url = new URL(baseUrl);
      url.searchParams.set(param, value);
      return url.toString();
  }
});