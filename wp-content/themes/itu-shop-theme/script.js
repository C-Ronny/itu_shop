document.addEventListener('DOMContentLoaded', function() {
    // Exit early if not on the homepage
    if (!document.querySelector('.home-content')) {
        console.log('ITU Shop: script.js skipped on non-homepage');
        return;
    }

    const grid = document.getElementById('product-grid');
    const searchInput = document.getElementById('search-input');
    const searchButton = document.getElementById('search-button');

    if (!grid || !searchInput || !searchButton) {
        console.error('Required elements not found:', { grid: !!grid, searchInput: !!searchInput, searchButton: !!searchButton });
        grid.innerHTML = '<p>Error: Page elements not found</p>';
        return;
    }

    if (!ituAjax || !ituAjax.rest_url || !ituAjax.nonce || !ituAjax.home_url) {
        console.error('ituAjax not properly initialized:', { ituAjax });
        grid.innerHTML = '<p>Error: Script initialization failed</p>';
        return;
    }
    console.log('ituAjax:', { rest_url: ituAjax.rest_url, nonce: ituAjax.nonce, home_url: ituAjax.home_url });

    const urlParams = new URLSearchParams(window.location.search);
    const initialQuery = urlParams.get('query') || '';
    const initialPage = parseInt(urlParams.get('page')) || 0;
    console.log('Initial params:', { query: initialQuery, page: initialPage });

    let productCache = {};

    // Debounce function to limit API calls
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Handle search
    searchButton.addEventListener('click', function() {
        const query = searchInput.value.trim();
        fetchProducts(initialPage, query);
    });

    searchInput.addEventListener('keyup', function(event) {
        if (event.key === 'Enter') {
            const query = searchInput.value.trim();
            fetchProducts(initialPage, query);
        }
    });

    // Real-time search on input
    const debouncedFetchProducts = debounce((query) => {
        fetchProducts(initialPage, query);
    }, 300);

    searchInput.addEventListener('input', function() {
        const query = searchInput.value.trim();
        console.log('ITU Shop: Real-time search triggered:', { query });
        debouncedFetchProducts(query);
    });

    // Initial fetch
    fetchProducts(initialPage, initialQuery);

    async function fetchProducts(page, query = '') {
        grid.innerHTML = '<div class="loading">Loading...</div>';

        let filteredProducts = [];
        let totalPages = 1;
        let totalProducts = 0;
        let currentPage = parseInt(page) || 0;
        let links = {};

        const cacheKey = `${currentPage}_${query || 'all'}`;
        let data;

        if (productCache[cacheKey]) {
            console.log(`Using cached products for page ${currentPage}, query: ${query || 'none'}`);
            data = productCache[cacheKey];
        } else {
            try {
                // Handle product ID search (numeric query)
                if (query && /^\d+$/.test(query)) {
                    const apiUrl = `${ituAjax.rest_url}/${encodeURIComponent(query)}`;
                    console.log('Fetching product by ID from:', apiUrl);
                    const response = await fetch(apiUrl, {
                        method: 'GET',
                        headers: { 'X-WP-Nonce': ituAjax.nonce }
                    });
                    console.log('Product response:', { status: response.status, ok: response.ok });
                    const text = await response.text();
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('Parse error:', e, 'Response text:', text.substring(0, 200));
                        throw new Error('Invalid JSON response');
                    }
                    if (!response.ok || !data.code) {
                        console.error('Error fetching product:', data.message || 'Product not found', 'Status:', response.status);
                        grid.innerHTML = `<p>No products found for '${escapeHtml(query)}'</p>`;
                        updateCategoryMessage(query, 0);
                        updatePaginationLinks(currentPage, 1, {}, query);
                        return;
                    }
                    // Wrap single product in array for consistency
                    data = { products: [data], total_pages: 1, total_results: 1, links: {} };
                } else {
                    // Handle name search or all products
                    const apiUrl = `${ituAjax.rest_url}?page=${currentPage}&pageSize=12`;
                    console.log('Fetching products from:', apiUrl);
                    const response = await fetch(apiUrl, {
                        method: 'GET',
                        headers: { 'X-WP-Nonce': ituAjax.nonce }
                    });
                    console.log('Products response:', { status: response.status, ok: response.ok });
                    const text = await response.text();
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('Parse error:', e, 'Response text:', text.substring(0, 200));
                        throw new Error('Invalid JSON response');
                    }
                    if (!response.ok || !data.products) {
                        console.error('Error fetching products:', data.message || 'Unknown error', 'Status:', response.status);
                        grid.innerHTML = `<p>No products found${query ? ` for '${escapeHtml(query)}'` : ''}</p>`;
                        updateCategoryMessage(query, 0);
                        updatePaginationLinks(currentPage, 1, {}, query);
                        return;
                    }
                }
                productCache[cacheKey] = data;
            } catch (error) {
                console.error('Products fetch error:', error.message);
                grid.innerHTML = `<p>Error loading products: ${error.message}</p>`;
                updateCategoryMessage(query, 0);
                updatePaginationLinks(currentPage, 1, {}, query);
                return;
            }
        }

        // Filter products for name search
        filteredProducts = query && !/^\d+$/.test(query)
            ? data.products.filter(product => product.name.toLowerCase().includes(query.toLowerCase())).slice(0, 12)
            : data.products;

        totalPages = query && !/^\d+$/.test(query)
            ? Math.ceil(data.total_results / 12)
            : data.total_pages || 1;
        totalProducts = query && !/^\d+$/.test(query)
            ? Math.min(data.total_results, filteredProducts.length)
            : (data.total_results || filteredProducts.length);
        links = data.links || {
            prev: currentPage > 0 ? `${ituAjax.rest_url}?page=${currentPage - 1}${query ? '&query=' + encodeURIComponent(query) : ''}` : null,
            next: currentPage < totalPages - 1 ? `${ituAjax.rest_url}?page=${currentPage + 1}${query ? '&query=' + encodeURIComponent(query) : ''}` : null
        };

        console.log('Pagination debug:', { totalProducts, totalPages, currentPage, filteredProducts: filteredProducts.length });

        updateCategoryMessage(query, totalProducts);
        renderProducts(filteredProducts);
        updatePaginationLinks(currentPage, totalPages, links, query);
        console.log(`Loaded ${filteredProducts.length} products for page ${currentPage}, query: ${query || 'none'}`);
    }

    function updateCategoryMessage(query, totalProducts) {
        const productGrid = document.querySelector('.product-grid');
        let messageDiv = document.querySelector('.category-message');
        if (!messageDiv) {
            messageDiv = document.createElement('div');
            messageDiv.className = 'category-message';
            productGrid.parentNode.insertBefore(messageDiv, productGrid);
        }
        messageDiv.textContent = query ? `Showing ${totalProducts} products for '${escapeHtml(query)}'` : `Showing ${totalProducts} products`;
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
            const productUrl = `${ituAjax.home_url}product/${encodeURIComponent(productCode)}`;

            html += `
                <div class="product-card">
                    <div class="image-placeholder"></div>
                    <h3>${escapeHtml(title)}</h3>
                    <p class="price">${escapeHtml(numberFormat(price, 2))} ${escapeHtml(currency)}</p>
                    <p class="stock-status">Stock: ${escapeHtml(stockStatus)}</p>
                    <a href="${escapeHtml(productUrl)}" class="product-link" data-product-code="${escapeHtml(productCode)}">${escapeHtml(title)}</a>
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

    function updatePaginationLinks(currentPage, totalPages, links, query = '') {
        const paginationDiv = document.querySelector('.pagination');
        currentPage = parseInt(currentPage) || 0;
        totalPages = parseInt(totalPages) || 1;

        let html = '';
        const baseUrl = removeQueryParam(window.location.href, 'page');

        if (links.prev) {
            const prevPage = currentPage - 1;
            const prevUrl = addQueryParam(baseUrl, ['page', prevPage, 'query', query]);
            html += `<a href="${prevUrl}" class="pagination-link" data-page="${prevPage}" data-query="${encodeURIComponent(query)}">Previous</a>`;
        } else {
            html += `<span class="pagination-link disabled">Previous</span>`;
        }

        html += `<span class="page-info">Page ${currentPage + 1} of ${totalPages}</span>`;

        if (links.next) {
            const nextPage = currentPage + 1;
            const nextUrl = addQueryParam(baseUrl, ['page', nextPage, 'query', query]);
            html += `<a href="${nextUrl}" class="pagination-link" data-page="${nextPage}" data-query="${encodeURIComponent(query)}">Next</a>`;
        } else {
            html += `<span class="pagination-link disabled">Next</span>`;
        }

        paginationDiv.innerHTML = html;
        console.log('Pagination links rendered:', { html, currentPage, totalPages, links, query });

        document.querySelectorAll('.pagination-link:not(.disabled)').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = this.getAttribute('data-page');
                const q = decodeURIComponent(this.getAttribute('data-query'));
                console.log('Pagination clicked:', { page, query: q });
                fetchProducts(page, q);
            });
        });
    }

    function removeQueryParam(url, param) {
        const urlObj = new URL(url);
        urlObj.searchParams.delete(param);
        return urlObj.toString();
    }

    function addQueryParam(baseUrl, param) {
        const url = new URL(baseUrl);
        if (Array.isArray(param)) {
            url.searchParams.set('page', param[1]);
            if (param[3]) url.searchParams.set('query', encodeURIComponent(param[3]));
            else url.searchParams.delete('query');
        } else {
            url.searchParams.set(param, encodeURIComponent(param));
        }
        return url.toString;
    }
});