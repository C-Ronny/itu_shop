document.addEventListener('DOMContentLoaded', function() {
    const grid = document.getElementById('product-grid');
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

    if (!ituAjax || !ituAjax.categories_url || !ituAjax.nonce) {
        console.error('ituAjax not properly initialized:', { ituAjax });
        categoryFilter.innerHTML = '<li>Categories Unavailable: Script error</li>';
        return;
    }
    console.log('ituAjax:', { rest_url: ituAjax.rest_url, categories_url: ituAjax.categories_url, nonce: ituAjax.nonce });

    const urlParams = new URLSearchParams(window.location.search);
    const initialCategory = urlParams.get('category') || '';
    console.log('Initial category from URL:', initialCategory);

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
        } catch (e) {
            console.error('Categories parse error:', e, 'Response text:', text.substring(0, 200));
            throw new Error('Invalid JSON response');
        }
        if (response.ok && Array.isArray(data)) {
            const filteredCategories = data.filter(category => 
                category.name !== "Brands" && category.name !== "Configurations"
            );
            let html = `<li data-category-name="" class="${initialCategory === '' ? 'active' : ''}">All Products</li>`;
            filteredCategories.forEach(category => {
                html += `<li data-category-id="${category.id}" data-category-name="${escapeHtml(category.name)}" class="${category.id === initialCategory ? 'active' : ''}">${escapeHtml(category.name)}</li>`;
            });
            categoryFilter.innerHTML = html;
            console.log('Rendered categories:', html, 'Filtered out: Brands, Configurations');

            categoryFilter.querySelectorAll('li').forEach(li => {
                li.addEventListener('click', function() {
                    categoryFilter.querySelectorAll('li').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    const categoryName = this.getAttribute('data-category-name');
                    fetchProducts(0, categoryName);
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

    function normalizeCategory(category) {
        category = category.replace(/\s*\(.*?\)/g, '');
        category = category.toLowerCase();
        category = category.replace(/\s+/g, '-');
        category = category.replace(/-$/, '');
        return category;
    }

    let productCache = {};

    async function fetchProducts(page, category = '') {
        if (typeof ituAjax === 'undefined' || !ituAjax.rest_url || !ituAjax.nonce) {
            console.warn('ituAjax not defined or incomplete:', { rest_url: ituAjax?.rest_url, nonce: ituAjax?.nonce }, 'Falling back to server-side pagination');
            const url = new URL(window.location);
            url.searchParams.set('page', page);
            if (category) url.searchParams.set('category', encodeURIComponent(category));
            else url.searchParams.delete('category');
            window.location.href = url.toString();
            return;
        }

        grid.innerHTML = '<div class="loading">Loading...</div>';
        
        let filteredProducts = [];
        let totalPages = 1;
        let totalProducts = 0;
        let currentPage = parseInt(page) || 0;
        let links = {};

        if (!category) {
            const apiUrl = `${ituAjax.rest_url}?page=${currentPage}`;
            console.log('Fetching products from:', apiUrl);
            try {
                const response = await fetch(apiUrl, {
                    method: 'GET',
                    headers: { 'X-WP-Nonce': ituAjax.nonce }
                });
                console.log('Products response:', { status: response.status, ok: response.ok });
                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                    console.log('Parsed products data:', data);
                } catch (e) {
                    console.error('Parse error:', e, 'Response text:', text.substring(0, 200));
                    throw new Error('Invalid JSON response');
                }
                if (response.ok && data.products) {
                    filteredProducts = data.products;
                    totalPages = data.total_pages || 1;
                    totalProducts = data.product_count || data.products.length;
                    links = data.links || {
                        prev: currentPage > 0 ? `${ituAjax.rest_url}?page=${currentPage - 1}` : null,
                        next: currentPage < totalPages - 1 ? `${ituAjax.rest_url}?page=${currentPage + 1}` : null
                    };
                } else {
                    console.error('Error fetching products:', data.message || 'Unknown error', 'Status:', response.status);
                    grid.innerHTML = '<p>Error loading products: ' + (data.message || 'Unknown error') + '</p>';
                    return;
                }
            } catch (error) {
                console.error('Products fetch error:', error.message);
                grid.innerHTML = '<p>Error loading products: ' + error.message + '</p>';
                return;
            }
        } else {
            const normalizedCategory = normalizeCategory(category);
            let collectedProducts = [];
            let pageToFetch = 0;
            totalPages = Infinity;

            while (pageToFetch < 80) {
                const cacheKey = `${pageToFetch}_${category}`;
                let data;

                if (productCache[cacheKey]) {
                    console.log(`Using cached products for page ${pageToFetch}, category: ${category}`);
                    data = productCache[cacheKey];
                } else {
                    const apiUrl = `${ituAjax.rest_url}?page=${pageToFetch}`;
                    console.log('Fetching products from:', apiUrl);
                    try {
                        const response = await fetch(apiUrl, {
                            method: 'GET',
                            headers: { 'X-WP-Nonce': ituAjax.nonce }
                        });
                        console.log('Products response:', { status: response.status, ok: response.ok });
                        const text = await response.text();
                        try {
                            data = JSON.parse(text);
                            console.log('Parsed products data:', data);
                        } catch (e) {
                            console.error('Parse error:', e, 'Response text:', text.substring(0, 200));
                            throw new Error('Invalid JSON response');
                        }
                        if (!response.ok || !data.products) {
                            console.error('Error fetching products:', data.message || 'Unknown error', 'Status:', response.status);
                            break;
                        }
                        productCache[cacheKey] = data;
                    } catch (error) {
                        console.error('Products fetch error:', error.message);
                        break;
                    }
                }

                const matchingProducts = data.products.filter(product => {
                    const urlParts = product.url.split('/');
                    if (urlParts.length < 2) {
                        console.log(`Skipping product: ${product.name}, invalid URL: ${product.url}`);
                        return false;
                    }
                    const categorySegment = decodeURIComponent(urlParts[1]);
                    const normalizedProductCategory = normalizeCategory(categorySegment);
                    console.log(`Product: ${product.name}, Category segment: ${categorySegment} -> Normalized: ${normalizedProductCategory}, Target: ${normalizedCategory}`);
                    return normalizedProductCategory === normalizedCategory;
                });

                collectedProducts.push(...matchingProducts);
                totalPages = Math.min(totalPages, data.total_pages || Infinity);
                pageToFetch++;
                if (pageToFetch >= (data.total_pages || Infinity)) break;
            }

            totalProducts = collectedProducts.length;
            filteredProducts = collectedProducts.slice(currentPage * 12, (currentPage + 1) * 12);
            totalPages = Math.ceil(totalProducts / 12);
            console.log('Pagination debug:', { totalProducts, totalPages, currentPage, filteredProducts: filteredProducts.length });
            links = {
                prev: currentPage > 0 ? `${ituAjax.rest_url}?page=${currentPage - 1}${category ? '&category=' + encodeURIComponent(category) : ''}` : null,
                next: (currentPage + 1) < totalPages ? `${ituAjax.rest_url}?page=${currentPage + 1}${category ? '&category=' + encodeURIComponent(category) : ''}` : null
            };
        }

        const productGrid = document.querySelector('.product-grid');
        let messageDiv = document.querySelector('.category-message');
        if (!messageDiv) {
            messageDiv = document.createElement('div');
            messageDiv.className = 'category-message';
            productGrid.parentNode.insertBefore(messageDiv, productGrid.previousElementSibling);
        }
        messageDiv.textContent = category ? `Showing ${totalProducts} products for ${category}` : `Showing ${totalProducts} products`;

        renderProducts(filteredProducts);
        updatePaginationLinks(currentPage, totalPages, links, category);
        console.log(`Loaded ${filteredProducts.length} products for page ${currentPage}, category: ${category || 'none'}`);
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
        currentPage = parseInt(currentPage) || 0;
        totalPages = parseInt(totalPages) || 1;
        
        let html = '';
        const baseUrl = removeQueryParam(window.location.href, 'page');
        
        if (links.prev) {
            const prevPage = currentPage - 1;
            const prevUrl = addQueryParam(baseUrl, ['page', prevPage, 'category', category]);
            html += `<a href="${prevUrl}" class="pagination-link" data-page="${prevPage}" data-category="${encodeURIComponent(category)}">Previous</a>`;
        }
        
        html += `<span class="page-info">Page ${currentPage + 1} of ${totalPages}</span>`;
        
        if (links.next) {
            const nextPage = currentPage + 1;
            const nextUrl = addQueryParam(baseUrl, ['page', nextPage, 'category', category]);
            html += `<a href="${nextUrl}" class="pagination-link" data-page="${nextPage}" data-category="${encodeURIComponent(category)}">Next</a>`;
        }
        
        paginationDiv.innerHTML = html;
        console.log('Pagination links rendered:', { html, currentPage, totalPages, links, category });

        document.querySelectorAll('.pagination-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = this.getAttribute('data-page');
                const cat = decodeURIComponent(this.getAttribute('data-category'));
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
            url.searchParams.set('page', param[1]);
            if (param[3]) url.searchParams.set('category', encodeURIComponent(param[3]));
            else url.searchParams.delete('category');
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