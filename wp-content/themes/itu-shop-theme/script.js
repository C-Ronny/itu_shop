document.addEventListener('DOMContentLoaded', function() {
    // Exit early if not on the homepage
    if (!document.querySelector('.home-content')) {
        console.log('ITU Shop: script.js skipped on non-homepage');
        return;
    }

    const grid = document.getElementById('product-grid');
    const searchInput = document.getElementById('search-input');
    const searchButton = document.getElementById('search-button');
    const categoryFilter = document.getElementById('category-filter'); // Ensure this element is selected

    if (!grid || !searchInput || !searchButton || !categoryFilter) {
        console.error('Required elements not found:', { grid: !!grid, searchInput: !!searchInput, searchButton: !!searchButton, categoryFilter: !!categoryFilter });
        grid.innerHTML = '<p>Error: Page elements not found</p>';
        return;
    }

    // Corrected ituAjax check to ensure all necessary properties are present
    if (!ituAjax || !ituAjax.rest_url || !ituAjax.categories_url || !ituAjax.nonce || !ituAjax.home_url) {
        console.error('ituAjax not properly initialized:', { ituAjax });
        grid.innerHTML = '<p>Error: Script initialization failed</p>';
        return;
    }
    console.log('ituAjax:', { rest_url: ituAjax.rest_url, categories_url: ituAjax.categories_url, nonce: ituAjax.nonce, home_url: ituAjax.home_url });

    const urlParams = new URLSearchParams(window.location.search);
    const initialQuery = urlParams.get('query') || '';
    const initialCategory = urlParams.get('category') || ''; // Get initial category from URL
    const initialPage = parseInt(urlParams.get('page')) || 0;
    console.log('Initial params:', { query: initialQuery, category: initialCategory, page: initialPage });

    let productCache = {}; // Cache for products (though not fully implemented for caching in this snippet)

    // Debounce function to limit API calls
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    // Function to fetch and display products
    function fetchProducts(page = 0, category = '', query = '') {
        console.log('ITU Shop: Fetching products with:', { page, category, query });

        let apiUrl = ituAjax.rest_url;
        const params = new URLSearchParams();
        params.append('page', page);
        if (query) {
            params.append('query', query);
        }
        if (category) {
            // If a category is selected, the API query parameter changes
            params.append('category', category);
        }
        apiUrl += '?' + params.toString();

        // Update URL in browser history
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('page', page);
        if (query) {
            newUrl.searchParams.set('query', query);
        } else {
            newUrl.searchParams.delete('query');
        }
        if (category) {
            newUrl.searchParams.set('category', category);
        } else {
            newUrl.searchParams.delete('category');
        }
        window.history.pushState({ page, category, query }, '', newUrl.toString());

        fetch(apiUrl, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': ituAjax.nonce
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('ITU Shop: Products fetched:', data);
                displayProducts(data.products);
                renderPagination(data.pagination.currentPage, data.pagination.totalPages, category, query); // Pass category and query
            })
            .catch(error => {
                console.error('ITU Shop: Error fetching products:', error);
                grid.innerHTML = '<p class="error-message">Failed to load products. Please try again later.</p>';
            });
    }

    // Function to display products
    function displayProducts(products) {
        if (!grid) return; // defensive check
        grid.innerHTML = ''; // Clear existing products
        if (products.length === 0) {
            grid.innerHTML = '<p class="no-results">No products found.</p>';
            return;
        }
        products.forEach(product => {
            const productCard = document.createElement('div');
            productCard.className = 'product-card';
            productCard.innerHTML = `
                <a href="${ituAjax.home_url}product/${product.code}" class="product-link">
                    <img src="${product.image_url}" alt="${product.name}" class="product-image">
                    <h3 class="product-name">${product.name}</h3>
                </a>
                <p class="product-price">${product.price}</p>
                <p class="stock-status">Stock: ${product.stock_status}</p>
            `;
            grid.appendChild(productCard);
        });
    }

    // Function to render pagination
    function renderPagination(currentPage, totalPages, currentCategory, currentQuery) {
        const paginationDiv = document.querySelector('.pagination');
        if (!paginationDiv) return;

        let html = '';
        const prevPage = currentPage - 1;
        const nextPage = currentPage + 1;

        if (currentPage > 0) {
            // Ensure addQueryParam properly handles all parameters
            const prevUrl = addQueryParam(window.location.href, { page: prevPage, category: currentCategory, query: currentQuery });
            html += `<a href="${prevUrl}" class="pagination-link" data-page="${prevPage}" data-category="${encodeURIComponent(currentCategory)}" data-query="${encodeURIComponent(currentQuery)}">Previous</a>`;
        } else {
            html += `<span class="pagination-link disabled">Previous</span>`;
        }

        html += `<span class="page-info">Page ${currentPage + 1} of ${totalPages}</span>`;

        if (currentPage < totalPages - 1 && totalPages > 1) {
            // Ensure addQueryParam properly handles all parameters
            const nextUrl = addQueryParam(window.location.href, { page: nextPage, category: currentCategory, query: currentQuery });
            html += `<a href="${nextUrl}" class="pagination-link" data-page="${nextPage}" data-category="${encodeURIComponent(currentCategory)}" data-query="${encodeURIComponent(currentQuery)}">Next</a>`;
        } else {
            html += `<span class="pagination-link disabled">Next</span>`;
        }

        paginationDiv.innerHTML = html; // Replace existing pagination with new
        console.log('Pagination links rendered:', { html, currentPage, totalPages, currentCategory, currentQuery });

        document.querySelectorAll('.pagination-link:not(.disabled)').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = parseInt(this.getAttribute('data-page'));
                const cat = decodeURIComponent(this.getAttribute('data-category') || '');
                const q = decodeURIComponent(this.getAttribute('data-query') || '');
                console.log('Pagination clicked:', { page, category: cat, query: q });
                fetchProducts(page, cat, q); // Pass all three parameters
            });
        });
    }

    // Fixed addQueryParam function to handle object of parameters
    function addQueryParam(baseUrl, paramsObj) {
        const url = new URL(baseUrl);
        for (const key in paramsObj) {
            if (paramsObj[key]) {
                url.searchParams.set(key, encodeURIComponent(paramsObj[key]));
            } else {
                url.searchParams.delete(key);
            }
        }
        return url.toString();
    }

    // Function to normalize category names
    function normalizeCategory(category) {
        category = category.replace(/\s*\(\d+\)/g, ''); // Remove (count)
        category = category.trim();
        category = category.toLowerCase();
        category = category.replace(/\s+/g, '-');
        category = category.replace(/-$/, ''); // Remove trailing hyphen
        return category;
    }

    function normalizeCategoryName(name) {
        return name.replace(/\s*\(\d+\)/g, '').trim(); // Remove count and trim
    }

    // Initial product fetch based on URL parameters
    fetchProducts(initialPage, initialCategory, initialQuery);

    // Category filter logic
    console.log('Fetching categories from:', ituAjax.categories_url);
    fetch(ituAjax.categories_url, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': ituAjax.nonce
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('ITU Shop: Categories fetched:', data);
            // Assuming categoriesData is used if needed elsewhere
            renderCategoryFilter(data, initialCategory); // Render initial filter with counts
        })
        .catch(error => {
            console.error('ITU Shop: Error fetching categories:', error);
            categoryFilter.innerHTML = '<li>Categories Unavailable: Failed to load.</li>';
        });

    function renderCategoryFilter(categories, activeCategoryCode) {
        categoryFilter.innerHTML = ''; // Clear existing filters
        const allProductsLi = document.createElement('li');
        allProductsLi.className = 'category-item' + (activeCategoryCode === '' ? ' active' : '');
        allProductsLi.dataset.category = '';
        allProductsLi.innerHTML = `All Products`;
        categoryFilter.appendChild(allProductsLi);

        categories.forEach(cat => {
            const li = document.createElement('li');
            li.className = 'category-item' + (normalizeCategory(cat.name) === activeCategoryCode ? ' active' : '');
            li.dataset.category = normalizeCategory(cat.name);
            li.innerHTML = `${normalizeCategoryName(cat.name)} <span>(${cat.count})</span>`;
            categoryFilter.appendChild(li);
        });

        document.querySelectorAll('.category-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.category-item').forEach(li => li.classList.remove('active'));
                this.classList.add('active');
                const selectedCategory = this.dataset.category;
                console.log('Category selected:', selectedCategory);
                // Fetch products for selected category, reset page to 0, keep current search query
                fetchProducts(0, selectedCategory, initialQuery);
            });
        });
    }

    // Search functionality
    const debouncedSearch = debounce(() => {
        const query = searchInput.value.trim();
        console.log('ITU Shop: Performing search:', query);
        // Search should reset page to 0, keep current category
        fetchProducts(0, initialCategory, query);
    }, 500);

    searchInput.addEventListener('input', debouncedSearch);
    searchButton.addEventListener('click', function() {
        const query = searchInput.value.trim();
        console.log('ITU Shop: Search button clicked, performing search:', query);
        // Search should reset page to 0, keep current category
        fetchProducts(0, initialCategory, query);
    });
});