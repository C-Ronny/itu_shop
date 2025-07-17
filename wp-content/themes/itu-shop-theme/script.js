document.addEventListener('DOMContentLoaded', function() {
  const grid = document.getElementById('product-grid');
  const paginationLinks = document.querySelectorAll('.pagination-link');
  const categoryFilter = document.getElementById('category-filter');
  
  if (!grid || !categoryFilter) {
      console.error('Required elements not found:', { grid: !!grid, categoryFilter: !!categoryFilter });
      return;
  }

  const initialCards = Array.from(grid.getElementsByClassName('product-card'));
  console.log('Found ' + initialCards.length + ' product cards on load.');
  
  initialCards.forEach((card, index) => {
      const cardName = card.querySelector('h3')?.textContent || 'Unknown';
      console.log(`Product ${index + 1}: "${cardName}"`);
  });

  // Verify ituAjax
  if (!ituAjax || !ituAjax.categories_url || !ituAjax.nonce) {
      console.error('ituAjax not properly initialized:', { ituAjax });
      categoryFilter.innerHTML = '<li>Categories Unavailable: Script error</li>';
      return;
  }
  console.log('ituAjax:', { rest_url: ituAjax.rest_url, categories_url: ituAjax.categories_url, nonce: ituAjax.nonce });

  const urlParams = new URLSearchParams(window.location.search);
  const initialCategory = urlParams.get('category') || '';
  console.log('Initial category from URL:', initialCategory);

  // Fetch categories
  console.log('Fetching categories from:', ituAjax.categories_url);
  fetch(ituAjax.categories_url, {
      method: 'GET',
      headers: {
          'X-WP-Nonce': ituAjax.nonce
      }
  })
  .then(response => {
      console.log('Categories response:', { status: response.status, ok: response.ok });
      return response.text().then(text => ({ response, text }));
  })
  .then(({ response, text }) => {
      let data;
      try {
          data = JSON.parse(text);
          console.log('Parsed categories data:', data);
      } catch (e) {
          console.error('Categories parse error:', e, 'Response text:', text.substring(0, 200));
          throw new Error('Invalid JSON response');
      }
      if (response.ok && Array.isArray(data)) {
          let html = `<li data-category="" class="${initialCategory === '' ? 'active' : ''}">All Products</li>`;
          data.forEach(category => {
              html += `<li data-category="${category.id}" class="${category.id === initialCategory ? 'active' : ''}">${escapeHtml(category.name)}</li>`;
          });
          categoryFilter.innerHTML = html;
          console.log('Rendered categories:', html);
          
          categoryFilter.querySelectorAll('li').forEach(li => {
              li.addEventListener('click', function() {
                  categoryFilter.querySelectorAll('li').forEach(l => l.classList.remove('active'));
                  this.classList.add('active');
                  const category = this.getAttribute('data-category');
                  console.log('Category clicked:', category);
                  fetchProducts(0, category);
              });
          });
      } else {
          console.error('Error fetching categories:', data.message || 'Unknown error', 'Status:', response.status);
          categoryFilter.innerHTML = '<li>Categories Unavailable: Server error</li>';
      }
  })
  .catch(error => {
      console.error('Categories fetch error:', error.message);
      categoryFilter.innerHTML = '<li>Categories Unavailable: Network error</li>';
  });

  paginationLinks.forEach(link => {
      link.addEventListener('click', function(e) {
          e.preventDefault();
          const page = this.getAttribute('data-page');
          const category = this.getAttribute('data-category') || initialCategory;
          console.log('Navigating to page:', page, 'Category:', category);
          fetchProducts(page, category);
      });
  });

  function fetchProducts(page, category = '') {
      if (typeof ituAjax === 'undefined' || !ituAjax.rest_url || !ituAjax.nonce) {
          console.warn('ituAjax not defined or incomplete:', { rest_url: ituAjax?.rest_url, nonce: ituAjax?.nonce }, 'Falling back to server-side pagination');
          const url = new URL(window.location);
          url.searchParams.set('page', page);
          if (category) url.searchParams.set('category', category);
          else url.searchParams.delete('category');
          window.location.href = url.toString();
          return;
      }

      const apiUrl = `${ituAjax.rest_url}?page=${page}${category ? `&category=${category}` : ''}`;
      console.log('Fetching products from:', apiUrl);
      
      grid.innerHTML = '<div class="loading">Loading...</div>';
      
      fetch(apiUrl, {
          method: 'GET',
          headers: {
              'X-WP-Nonce': ituAjax.nonce
          }
      })
      .then(response => {
          console.log('Products response:', { status: response.status, ok: response.ok });
          return response.text().then(text => ({ response, text }));
      })
      .then(({ response, text }) => {
          let data;
          try {
              data = JSON.parse(text);
              console.log('Parsed products data:', data);
          } catch (e) {
              console.error('Parse error:', e, 'Response text:', text.substring(0, 200));
              throw new Error('Invalid JSON response');
          }
          if (response.ok && data.products) {
              let filteredProducts = data.products;
              if (category) {
                  const categoryEncoded = category.replace(/[\(\)]/g, m => m === '(' ? '%28' : '%29');
                  filteredProducts = data.products.filter(product => 
                      product.url.includes(`/${categoryEncoded}-`)
                  );
                  console.log('Filtered products:', filteredProducts.length, 'Category:', category);
              }
              renderProducts(filteredProducts);
              updatePaginationLinks(page, data.total_pages, data.links, category);
              console.log(`Loaded ${filteredProducts.length} products for page ${page}, category: ${category || 'none'}`);
          } else {
              console.error('Error fetching products:', data.message || 'Unknown error', 'Status:', response.status);
              grid.innerHTML = '<p>Error loading products: ' + (data.message || 'Unknown error') + '</p>';
          }
      })
      .catch(error => {
          console.error('Products fetch error:', error.message);
          grid.innerHTML = '<p>Error loading products: ' + error.message + '</p>';
          console.warn('REST API failed, falling back to server-side pagination');
          const url = new URL(window.location);
          url.searchParams.set('page', page);
          if (category) url.searchParams.set('category', category);
          else url.searchParams.delete('category');
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

  function updatePaginationLinks(currentPage, totalPages, links, category = '') {
      const paginationDiv = document.querySelector('.pagination');
      currentPage = parseInt(currentPage);
      totalPages = parseInt(totalPages);
      
      let html = '';
      const baseUrl = removeQueryParam(window.location.href, 'page');
      
      if (links.prev) {
          const prevPage = currentPage - 1;
          const prevUrl = category ? addQueryParam(baseUrl, ['page', prevPage, 'category', category]) : addQueryParam(baseUrl, 'page', prevPage);
          html += `<a href="${prevUrl}" class="pagination-link" data-page="${prevPage}" data-category="${category}">Previous</a>`;
      }
      
      html += `<span class="page-info">Page ${currentPage + 1} of ${totalPages}</span>`;
      
      if (links.next) {
          const nextPage = currentPage + 1;
          const nextUrl = category ? addQueryParam(baseUrl, ['page', nextPage, 'category', category]) : addQueryParam(baseUrl, 'page', nextPage);
          html += `<a href="${nextUrl}" class="pagination-link" data-page="${nextPage}" data-category="${category}">Next</a>`;
      }
      
      paginationDiv.innerHTML = html;
      
      document.querySelectorAll('.pagination-link').forEach(link => {
          link.addEventListener('click', function(e) {
              e.preventDefault();
              const page = this.getAttribute('data-page');
              const cat = this.getAttribute('data-category');
              console.log('Pagination clicked:', { page, category: cat });
              fetchProducts(page, cat);
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
      if (Array.isArray(param)) {
          param[0] === 'page' ? url.searchParams.set('page', param[1]) : url.searchParams.set('category', param[3]);
          param[0] === 'category' ? url.searchParams.set('category', param[1]) : url.searchParams.set('page', param[3]);
      } else {
          url.searchParams.set(param, value);
      }
      return url.toString();
  }

  if (initialCategory) {
      console.log('Initial fetch with category:', initialCategory);
      fetchProducts(0, initialCategory);
  }
});