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
      if (typeof ituAjax === 'undefined' || !ituAjax.ajax_url) {
          console.warn('ituAjax not defined, falling back to server-side pagination');
          const url = new URL(window.location);
          url.searchParams.set('page', page);
          window.location.href = url.toString();
          return;
      }

      const apiUrl = `${ituAjax.ajax_url}?action=fetch_products&page=${page}&nonce=${ituAjax.nonce}`;
      console.log('Fetching products from:', apiUrl);
      
      grid.innerHTML = '<div class="loading">Loading...</div>';
      
      fetch(apiUrl, {
          method: 'GET',
          headers: {
              'X-Requested-With': 'XMLHttpRequest'
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
          if (data.success) {
              grid.innerHTML = data.data.html;
              updatePaginationLinks(page, data.data.total_pages);
              console.log(`Loaded ${data.data.product_count} products for page ${page}`);
          } else {
              console.error('Error fetching products:', data.data.message);
              grid.innerHTML = '<p>Error loading products: ' + data.data.message + '</p>';
          }
      })
      .catch(error => {
          console.error('Fetch error:', error);
          grid.innerHTML = '<p>Error loading products: ' + error.message + '</p>';
          // Fallback to server-side pagination
          console.warn('AJAX failed, falling back to server-side pagination');
          const url = new URL(window.location);
          url.searchParams.set('page', page);
          window.location.href = url.toString();
      });
  }

  function updatePaginationLinks(currentPage, totalPages) {
      const paginationDiv = document.querySelector('.pagination');
      currentPage = parseInt(currentPage);
      totalPages = parseInt(totalPages);
      
      let html = '';
      const baseUrl = removeQueryParam(window.location.href, 'page');
      
      if (currentPage > 0) {
          const prevUrl = addQueryParam(baseUrl, 'page', currentPage - 1);
          html += `<a href="${prevUrl}" class="pagination-link" data-page="${currentPage - 1}">Previous</a>`;
      }
      
      html += `<span class="page-info">Page ${currentPage + 1} of ${totalPages}</span>`;
      
      if (currentPage < totalPages - 1 && totalPages > 1) {
          const nextUrl = addQueryParam(baseUrl, 'page', currentPage + 1);
          html += `<a href="${nextUrl}" class="pagination-link" data-page="${currentPage + 1}">Next</a>`;
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