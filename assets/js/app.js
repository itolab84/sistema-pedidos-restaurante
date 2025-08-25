// ===== FLAVORFINDER ENHANCED JAVASCRIPT =====

// Global Variables
let cart = [];
let products = [];
let additionals = [];
let currentProduct = null;
let currentCustomer = null;
let map = null;
let marker = null;
let searchTimeout = null;
let currentPage = 1;
let isLoading = false;
let hasMoreProducts = true;
let filteredProducts = [];
let currentFilters = {
    category: 'all',
    search: '',
    minPrice: null,
    maxPrice: null
};

// Theme Management
let currentTheme = localStorage.getItem('flavorfinderTheme') || 'light';

// Intersection Observer for Lazy Loading
let imageObserver = null;
let loadTriggerObserver = null;

// Initialize Application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// Main Initialization Function
async function initializeApp() {
    showLoadingOverlay();
    
    try {
        // Initialize theme
        initializeTheme();
        
        // Load data
        await Promise.all([
            loadProducts(),
            loadAdditionals()
        ]);
        
        // Initialize components
        initializeOrderTypeToggle();
        initializeSearch();
        initializeLazyLoading();
        initializeIntersectionObserver();
        
        // Setup event listeners
        setupEventListeners();
        
        hideLoadingOverlay();
        
        // Add entrance animations
        setTimeout(() => {
            document.querySelectorAll('.fade-in, .slide-in-right').forEach(el => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0) translateX(0)';
            });
        }, 100);
        
    } catch (error) {
        console.error('Error initializing app:', error);
        hideLoadingOverlay();
        showToast('Error al cargar la aplicación', 'error');
    }
}

// ===== THEME MANAGEMENT =====

function initializeTheme() {
    if (currentTheme === 'dark') {
        document.body.classList.add('dark-mode');
        document.getElementById('themeIcon').className = 'fas fa-sun';
    } else {
        document.body.classList.remove('dark-mode');
        document.getElementById('themeIcon').className = 'fas fa-moon';
    }
}

function toggleTheme() {
    currentTheme = currentTheme === 'light' ? 'dark' : 'light';
    localStorage.setItem('flavorfinderTheme', currentTheme);
    
    document.body.classList.toggle('dark-mode');
    
    const themeIcon = document.getElementById('themeIcon');
    if (currentTheme === 'dark') {
        themeIcon.className = 'fas fa-sun';
        showToast('Modo oscuro activado', 'info');
    } else {
        themeIcon.className = 'fas fa-moon';
        showToast('Modo claro activado', 'info');
    }
    
    // Animate theme transition
    document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
    setTimeout(() => {
        document.body.style.transition = '';
    }, 300);
}

// ===== LOADING MANAGEMENT =====

function showLoadingOverlay() {
    document.getElementById('loadingOverlay').classList.remove('d-none');
}

function hideLoadingOverlay() {
    document.getElementById('loadingOverlay').classList.add('d-none');
}

function showSkeletonCards() {
    const container = document.getElementById('productsContainer');
    container.innerHTML = '';
    
    for (let i = 0; i < 6; i++) {
        const skeletonCard = createSkeletonCard();
        container.appendChild(skeletonCard);
    }
}

function createSkeletonCard() {
    const card = document.createElement('div');
    card.className = 'product-card skeleton-card';
    card.innerHTML = `
        <div class="skeleton skeleton-image"></div>
        <div class="product-card-body">
            <div class="skeleton skeleton-text title"></div>
            <div class="skeleton skeleton-text"></div>
            <div class="skeleton skeleton-text"></div>
            <div class="skeleton skeleton-text price"></div>
        </div>
    `;
    return card;
}

// ===== SEARCH FUNCTIONALITY =====

function initializeSearch() {
    const searchInput = document.getElementById('productSearch');
    const minPriceInput = document.getElementById('minPrice');
    const maxPriceInput = document.getElementById('maxPrice');
    
    // Real-time search with debouncing
    searchInput.addEventListener('input', debounce(handleSearchInput, 300));
    minPriceInput.addEventListener('input', debounce(handlePriceFilter, 500));
    maxPriceInput.addEventListener('input', debounce(handlePriceFilter, 500));
    
    // Search suggestions
    searchInput.addEventListener('focus', showSearchSuggestions);
    searchInput.addEventListener('blur', hideSearchSuggestions);
}

function debounce(func, wait) {
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(searchTimeout);
            func(...args);
        };
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(later, wait);
    };
}

function handleSearchInput(event) {
    const query = event.target.value.trim();
    currentFilters.search = query;
    
    if (query.length >= 2) {
        performSearch();
        updateSearchSuggestions(query);
    } else if (query.length === 0) {
        clearSearch();
    }
}

function handlePriceFilter() {
    const minPrice = parseFloat(document.getElementById('minPrice').value) || null;
    const maxPrice = parseFloat(document.getElementById('maxPrice').value) || null;
    
    currentFilters.minPrice = minPrice;
    currentFilters.maxPrice = maxPrice;
    
    performSearch();
}

function performSearch() {
    const { search, category, minPrice, maxPrice } = currentFilters;
    
    filteredProducts = products.filter(product => {
        // Text search
        if (search && !product.name.toLowerCase().includes(search.toLowerCase()) && 
            !product.description.toLowerCase().includes(search.toLowerCase())) {
            return false;
        }
        
        // Category filter
        if (category !== 'all' && product.category !== category) {
            return false;
        }
        
        // Price filter
        const productPrice = getProductMinPrice(product);
        if (minPrice && productPrice < minPrice) return false;
        if (maxPrice && productPrice > maxPrice) return false;
        
        return true;
    });
    
    displayProducts(filteredProducts);
    updateActiveFilters();
    
    // Analytics
    if (search) {
        trackSearchEvent(search, filteredProducts.length);
    }
}

function clearSearch() {
    document.getElementById('productSearch').value = '';
    document.getElementById('minPrice').value = '';
    document.getElementById('maxPrice').value = '';
    
    currentFilters = {
        category: currentFilters.category,
        search: '',
        minPrice: null,
        maxPrice: null
    };
    
    filteredProducts = products;
    displayProducts(filteredProducts);
    updateActiveFilters();
    hideSearchSuggestions();
}

function updateActiveFilters() {
    const container = document.getElementById('activeFilters');
    container.innerHTML = '';
    
    const { search, category, minPrice, maxPrice } = currentFilters;
    
    if (search) {
        addFilterTag(container, 'Búsqueda', search, () => {
            document.getElementById('productSearch').value = '';
            currentFilters.search = '';
            performSearch();
        });
    }
    
    if (category !== 'all') {
        addFilterTag(container, 'Categoría', category, () => {
            filterProducts('all');
        });
    }
    
    if (minPrice || maxPrice) {
        const priceText = minPrice && maxPrice ? `$${minPrice} - $${maxPrice}` :
                         minPrice ? `Desde $${minPrice}` : `Hasta $${maxPrice}`;
        addFilterTag(container, 'Precio', priceText, () => {
            document.getElementById('minPrice').value = '';
            document.getElementById('maxPrice').value = '';
            currentFilters.minPrice = null;
            currentFilters.maxPrice = null;
            performSearch();
        });
    }
}

function addFilterTag(container, label, value, removeCallback) {
    const tag = document.createElement('div');
    tag.className = 'filter-tag';
    tag.innerHTML = `
        <span>${label}: ${value}</span>
        <button class="filter-tag-remove" onclick="this.parentElement.remove(); (${removeCallback.toString()})()">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(tag);
}

function showSearchSuggestions() {
    // Implementation for search suggestions
    const suggestions = document.getElementById('searchSuggestions');
    // Add logic to show popular searches or recent searches
}

function hideSearchSuggestions() {
    setTimeout(() => {
        document.getElementById('searchSuggestions').classList.add('d-none');
    }, 200);
}

function updateSearchSuggestions(query) {
    // Implementation for dynamic search suggestions
    const suggestions = getSearchSuggestions(query);
    // Display suggestions
}

function getSearchSuggestions(query) {
    // Return relevant suggestions based on query
    return products
        .filter(p => p.name.toLowerCase().includes(query.toLowerCase()))
        .slice(0, 5)
        .map(p => p.name);
}

function trackSearchEvent(query, resultsCount) {
    // Analytics tracking
    console.log(`Search: "${query}" - ${resultsCount} results`);
}

// ===== LAZY LOADING & PERFORMANCE =====

function initializeLazyLoading() {
    // Image lazy loading
    imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.classList.add('loaded');
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                }
            }
        });
    }, {
        rootMargin: '50px'
    });
}

function initializeIntersectionObserver() {
    // Load more products trigger
    loadTriggerObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !isLoading && hasMoreProducts) {
                loadMoreProducts();
            }
        });
    });
    
    const trigger = document.getElementById('lazyLoadTrigger');
    if (trigger) {
        loadTriggerObserver.observe(trigger);
    }
}

function loadMoreProducts() {
    if (isLoading || !hasMoreProducts) return;
    
    isLoading = true;
    document.getElementById('lazyLoadTrigger').classList.remove('d-none');
    
    // Simulate API call for pagination
    setTimeout(() => {
        currentPage++;
        // In real implementation, load more products from API
        // For now, we'll just hide the trigger after a few loads
        if (currentPage > 3) {
            hasMoreProducts = false;
            document.getElementById('lazyLoadTrigger').classList.add('d-none');
        }
        isLoading = false;
    }, 1000);
}

function getProductMinPrice(product) {
    if (product.sizes && product.sizes.length > 0) {
        return Math.min(...product.sizes.map(s => parseFloat(s.price)));
    }
    return parseFloat(product.price || product.base_price || 0);
}

// Load products from API
async function loadProducts() {
    try {
        const response = await fetch('api/products.php');
        const data = await response.json();
        
        if (data.success) {
            products = data.products;
            displayProducts(products);
            loadCategories();
        }
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

// Load additionals from API
async function loadAdditionals() {
    try {
        const response = await fetch('api/additionals.php');
        const data = await response.json();
        
        if (data.success) {
            additionals = data.additionals;
        }
    } catch (error) {
        console.error('Error loading additionals:', error);
    }
}

// Load categories for filter
function loadCategories() {
    const categories = [...new Set(products.map(p => p.category))];
    const filterContainer = document.getElementById('categoryFilter');
    
    categories.forEach(category => {
        if (category) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-primary';
            button.textContent = category.charAt(0).toUpperCase() + category.slice(1);
            button.onclick = () => filterProducts(category);
            filterContainer.appendChild(button);
        }
    });
}

// Display products
function displayProducts(products) {
    const container = document.getElementById('productsContainer');
    
    // Remove skeleton cards first
    container.querySelectorAll('.skeleton-card').forEach(card => card.remove());
    
    if (products.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4>No se encontraron productos</h4>
                <p class="text-muted">Intenta con otros términos de búsqueda o filtros</p>
                <button class="btn btn-primary" onclick="clearSearch()">
                    <i class="fas fa-times"></i> Limpiar filtros
                </button>
            </div>
        `;
        return;
    }
    
    // Clear all existing products
    container.innerHTML = '';
    
    console.log('Displaying products:', products.length); // Debug log
    
    products.forEach((product, index) => {
        console.log('Creating card for product:', product.name); // Debug log
        
        // Use main_image if available, fallback to image, then placeholder
        const imageUrl = product.main_image || product.image || 'https://via.placeholder.com/300x200?text=' + encodeURIComponent(product.name);
        
        // Show base price or price range
        let priceDisplay = '';
        if (product.sizes && product.sizes.length > 0) {
            const minPrice = Math.min(...product.sizes.map(s => parseFloat(s.price)));
            const maxPrice = Math.max(...product.sizes.map(s => parseFloat(s.price)));
            if (minPrice === maxPrice) {
                priceDisplay = `$${minPrice.toFixed(2)}`;
            } else {
                priceDisplay = `$${minPrice.toFixed(2)} - $${maxPrice.toFixed(2)}`;
            }
        } else {
            priceDisplay = `$${parseFloat(product.price || product.base_price || 0).toFixed(2)}`;
        }
        
        // Category badge
        const categoryBadge = product.category_name ? 
            `<div class="category-badge" style="background-color: ${product.category_color || 'var(--primary-orange)'}">${product.category_name}</div>` : '';
        
        const productCard = document.createElement('div');
        productCard.className = 'product-card';
        // Remove initial opacity and transform to ensure visibility
        productCard.style.opacity = '1';
        productCard.style.transform = 'translateY(0)';
        productCard.style.transition = 'all 0.3s ease';
        
        productCard.innerHTML = `
            <div class="product-image-container">
                <img src="${imageUrl}" 
                     class="product-image" 
                     alt="${product.name}"
                     style="height: 200px; object-fit: cover; width: 100%;"
                     onerror="this.src='https://via.placeholder.com/300x200?text=' + encodeURIComponent('${product.name}')">
                ${categoryBadge}
            </div>
            <div class="product-card-body">
                <h5 class="product-title">${product.name}</h5>
                <p class="product-description">${product.description || 'Sin descripción'}</p>
                <div class="product-price">${priceDisplay}</div>
                ${product.sizes && product.sizes.length > 1 ? '<small class="text-muted">Múltiples tamaños disponibles</small>' : ''}
                <button class="btn-view-details w-100 mt-2" onclick="openProductModal(${product.id})">
                    <i class="fas fa-eye"></i> Ver Detalles
                </button>
            </div>
        `;
        
        // Add click handler for the entire card
        productCard.addEventListener('click', (e) => {
            if (!e.target.closest('button')) {
                openProductModal(product.id);
            }
        });
        
        container.appendChild(productCard);
        console.log('Product card appended for:', product.name); // Debug log
        
        // Observe images for lazy loading
        const img = productCard.querySelector('.product-image');
        if (img && imageObserver) {
            imageObserver.observe(img);
        }
    });
    
    console.log('Total cards in container:', container.children.length); // Debug log
    
    // Add gentle fade-in animation after a short delay
    setTimeout(() => {
        container.querySelectorAll('.product-card').forEach((card, index) => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
            // Add a subtle hover effect
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-5px)';
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });

function createProductCard(product, index) {
    const card = document.createElement('div');
    card.className = 'product-card';
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'all 0.3s ease';
    
    // Use main_image if available, fallback to image, then placeholder
    const imageUrl = product.main_image || product.image || 'https://via.placeholder.com/300x200?text=' + encodeURIComponent(product.name);
    
    // Show base price or price range
    let priceDisplay = '';
    if (product.sizes && product.sizes.length > 0) {
        const minPrice = Math.min(...product.sizes.map(s => parseFloat(s.price)));
        const maxPrice = Math.max(...product.sizes.map(s => parseFloat(s.price)));
        if (minPrice === maxPrice) {
            priceDisplay = `$${minPrice.toFixed(2)}`;
        } else {
            priceDisplay = `$${minPrice.toFixed(2)} - $${maxPrice.toFixed(2)}`;
        }
    } else {
        priceDisplay = `$${parseFloat(product.price || product.base_price || 0).toFixed(2)}`;
    }
    
    // Category badge
    const categoryBadge = product.category_name ? 
        `<div class="category-badge" style="background-color: ${product.category_color || 'var(--primary-orange)'}">${product.category_name}</div>` : '';
    
    card.innerHTML = `
        <div class="product-image-container">
            <img data-src="${imageUrl}" 
                 class="product-image lazy" 
                 alt="${product.name}"
                 style="height: 200px; object-fit: cover; width: 100%;"
                 onerror="this.src='https://via.placeholder.com/300x200?text=' + encodeURIComponent('${product.name}')">
            ${categoryBadge}
        </div>
        <div class="product-card-body">
            <h5 class="product-title">${product.name}</h5>
            <p class="product-description">${product.description || 'Sin descripción'}</p>
            <div class="product-price">${priceDisplay}</div>
            ${product.sizes && product.sizes.length > 1 ? '<small class="text-muted">Múltiples tamaños disponibles</small>' : ''}
            <button class="btn-view-details w-100 mt-2" onclick="openProductModal(${product.id})">
                <i class="fas fa-eye"></i> Ver Detalles
            </button>
        </div>
    `;
    
    // Add click handler for the entire card
    card.addEventListener('click', (e) => {
        if (!e.target.closest('button')) {
            openProductModal(product.id);
        }
    });
    
    return card;
}

// Open product detail modal with enhanced features
async function openProductModal(productId) {
    try {
        productId = parseInt(productId);
        
        // Get detailed product data from API
        const response = await fetch(`api/products.php?id=${productId}`);
        const data = await response.json();
        
        if (!data.success || !data.product) {
            console.error('Product not found:', productId);
            showToast('Producto no encontrado', 'error');
            return;
        }
        
        currentProduct = data.product;
        
        // Check if modal elements exist with better error handling
        const modalTitle = document.getElementById('productModalTitle');
        const modalName = document.getElementById('productModalName');
        const modalDescription = document.getElementById('productModalDescription');
        const modalPrice = document.getElementById('productModalPrice');
        const modalMainImage = document.getElementById('productModalMainImage');
        const modalQuantity = document.getElementById('productQuantity');
        const modalNotes = document.getElementById('productNotes');
        const productModal = document.getElementById('productModal');
        
        // Validate all required elements exist
        const requiredElements = {
            modalTitle,
            modalName,
            modalDescription,
            modalPrice,
            modalMainImage,
            productModal
        };
        
        const missingElements = Object.entries(requiredElements)
            .filter(([name, element]) => !element)
            .map(([name]) => name);
        
        if (missingElements.length > 0) {
            console.error('Missing modal elements:', missingElements);
            showToast('Error al cargar el modal del producto', 'error');
            return;
        }
        
        // Populate modal with product data safely
        if (modalTitle) modalTitle.textContent = currentProduct.name || 'Producto';
        if (modalName) modalName.textContent = currentProduct.name || 'Producto';
        if (modalDescription) modalDescription.textContent = currentProduct.description || 'Sin descripción';
        
        // Load main image with error handling
        if (modalMainImage) {
            const imageUrl = currentProduct.main_image || currentProduct.image || 'https://via.placeholder.com/400x300?text=' + encodeURIComponent(currentProduct.name || 'Producto');
            modalMainImage.src = imageUrl;
            
            // Add error handler for image loading
            modalMainImage.onerror = function() {
                this.src = 'https://via.placeholder.com/400x300?text=' + encodeURIComponent(currentProduct.name || 'Producto');
            };
        }
        
        // Load image gallery from API data
        loadProductImageGallery(currentProduct.images);
        
        // Load product sizes from API data
        loadProductSizes(currentProduct.sizes);
        
        // Load additionals from API data
        loadProductAdditionalsFromData(currentProduct.additionals);
        
        // Reset form values safely
        if (modalQuantity) modalQuantity.value = 1;
        if (modalNotes) modalNotes.value = '';
        
        // Set initial price and update total
        setInitialPrice();
        updateProductTotalPrice();
        
        // Show modal
        const modal = new bootstrap.Modal(productModal);
        modal.show();
        
    } catch (error) {
        console.error('Error opening product modal:', error);
        showToast('Error al abrir los detalles del producto', 'error');
    }
}

// Load product images gallery
async function loadProductImages(productId) {
    try {
        const response = await fetch(`api/product_images.php?product_id=${productId}`);
        const data = await response.json();
        
        const thumbnailsContainer = document.getElementById('productImageThumbnails');
        const mainImage = document.getElementById('productModalMainImage');
        
        if (thumbnailsContainer) {
            thumbnailsContainer.innerHTML = '';
        }
        
        if (data.success && data.images.length > 0) {
            // Set the first image as the main image
            if (mainImage && data.images[0]) {
                mainImage.src = data.images[0].image_path;
            }
            
            // Create thumbnails for all images
            data.images.forEach((image, index) => {
                if (thumbnailsContainer) {
                    const thumbnail = document.createElement('img');
                    thumbnail.src = image.image_path;
                    thumbnail.className = 'img-thumbnail me-2 mb-2';
                    thumbnail.style.width = '80px';
                    thumbnail.style.height = '80px';
                    thumbnail.style.objectFit = 'cover';
                    thumbnail.style.cursor = 'pointer';
                    thumbnail.onclick = () => {
                        if (mainImage) {
                            mainImage.src = image.image_path;
                        }
                    };
                    thumbnailsContainer.appendChild(thumbnail);
                }
            });
        } else {
            // If no images found in product_images table, use the product's main image or placeholder
            if (mainImage && currentProduct) {
                mainImage.src = currentProduct.image || 'https://via.placeholder.com/400x300?text=' + encodeURIComponent(currentProduct.name);
            }
        }
    } catch (error) {
        console.error('Error loading product images:', error);
        // Fallback to product's main image on error
        const mainImage = document.getElementById('productModalMainImage');
        if (mainImage && currentProduct) {
            mainImage.src = currentProduct.image || 'https://via.placeholder.com/400x300?text=' + encodeURIComponent(currentProduct.name);
        }
    }
}

// Load additionals for product
function loadProductAdditionals(productId) {
    const container = document.getElementById('productAdditionalsContainer');
    container.innerHTML = '';
    
    // Filter additionals for this product (you can implement category-based filtering)
    const productAdditionals = additionals.filter(additional => 
        additional.status === 'active' && 
        (additional.category === currentProduct.category || additional.category === 'general')
    );
    
    if (productAdditionals.length > 0) {
        document.getElementById('productAdditionals').style.display = 'block';
        
        productAdditionals.forEach(additional => {
            const additionalItem = document.createElement('div');
            additionalItem.className = 'form-check mb-2';
            additionalItem.innerHTML = `
                <input class="form-check-input additional-checkbox" type="checkbox" 
                       value="${additional.id}" id="additional_${additional.id}"
                       data-price="${additional.price}" onchange="updateProductTotalPrice()">
                <label class="form-check-label d-flex justify-content-between w-100" for="additional_${additional.id}">
                    <span>${additional.name}</span>
                    <span class="text-success">+$${parseFloat(additional.price).toFixed(2)}</span>
                </label>
            `;
            container.appendChild(additionalItem);
        });
    } else {
        document.getElementById('productAdditionals').style.display = 'none';
    }
}

// Change quantity in product modal
function changeQuantity(change) {
    const quantityInput = document.getElementById('productQuantity');
    let currentQuantity = parseInt(quantityInput.value);
    let newQuantity = currentQuantity + change;
    
    if (newQuantity < 1) newQuantity = 1;
    if (newQuantity > 10) newQuantity = 10;
    
    quantityInput.value = newQuantity;
    updateProductTotalPrice();
}

// Update total price in product modal including additionals
function updateProductTotalPrice() {
    if (!currentProduct) return;
    
    const quantity = parseInt(document.getElementById('productQuantity').value);
    let totalPrice = parseFloat(currentProduct.price) * quantity;
    
    // Add selected additionals
    const selectedAdditionals = document.querySelectorAll('.additional-checkbox:checked');
    selectedAdditionals.forEach(checkbox => {
        totalPrice += parseFloat(checkbox.dataset.price) * quantity;
    });
    
    document.getElementById('productTotalPrice').textContent = totalPrice.toFixed(2);
}

// Add product to cart from modal with additionals
function addProductToCart() {
    if (!currentProduct) return;
    
    const quantity = parseInt(document.getElementById('productQuantity').value);
    const notes = document.getElementById('productNotes').value.trim();
    
    // Get selected additionals
    const selectedAdditionals = [];
    const additionalCheckboxes = document.querySelectorAll('.additional-checkbox:checked');
    additionalCheckboxes.forEach(checkbox => {
        const additional = additionals.find(a => a.id == checkbox.value);
        if (additional) {
            selectedAdditionals.push({
                id: additional.id,
                name: additional.name,
                price: parseFloat(additional.price)
            });
        }
    });
    
    // Calculate total price including additionals
    let itemPrice = parseFloat(currentProduct.price);
    selectedAdditionals.forEach(additional => {
        itemPrice += additional.price;
    });
    
    // Create cart item with additionals and notes
    const cartItem = {
        id: parseInt(currentProduct.id),
        name: currentProduct.name,
        price: itemPrice,
        basePrice: parseFloat(currentProduct.price),
        quantity: quantity,
        notes: notes,
        additionals: selectedAdditionals
    };
    
    // Create unique key for cart item (including additionals and notes)
    const itemKey = `${cartItem.id}_${JSON.stringify(selectedAdditionals)}_${notes}`;
    
    // Check if identical item already exists
    const existingItemIndex = cart.findIndex(item => {
        const existingKey = `${item.id}_${JSON.stringify(item.additionals)}_${item.notes}`;
        return existingKey === itemKey;
    });
    
    if (existingItemIndex !== -1) {
        cart[existingItemIndex].quantity += quantity;
    } else {
        cart.push(cartItem);
    }
    
    updateCartDisplay();
    showToast(`${quantity} x ${currentProduct.name} agregado al carrito`);
    
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
}

// Update cart display with additionals
function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    const modalCartItems = document.getElementById('modalCartItems');
    const cartCount = document.getElementById('cartCount');
    const cartTotal = document.getElementById('cartTotal');
    const modalCartTotal = document.getElementById('modalCartTotal');
    
    let total = 0;
    let itemsHTML = '';
    
    if (cart.length === 0) {
        itemsHTML = '<p class="text-muted text-center">Tu carrito está vacío</p>';
    } else {
        cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            
            let additionalsText = '';
            if (item.additionals && item.additionals.length > 0) {
                additionalsText = '<br><small class="text-info">+ ' + 
                    item.additionals.map(a => a.name).join(', ') + '</small>';
            }
            
            itemsHTML += `
                <div class="cart-item mb-3 p-2 border rounded">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <strong>${item.name}</strong>
                            ${additionalsText}
                            <br>
                            <small class="text-muted">$${item.price.toFixed(2)} x ${item.quantity} = $${itemTotal.toFixed(2)}</small>
                            ${item.notes ? `<br><small class="text-warning"><i class="fas fa-sticky-note"></i> ${item.notes}</small>` : ''}
                        </div>
                        <div class="quantity-controls d-flex align-items-center">
                            <button class="btn btn-sm btn-outline-secondary" onclick="updateCartQuantity(${index}, -1)">-</button>
                            <span class="mx-2">${item.quantity}</span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="updateCartQuantity(${index}, 1)">+</button>
                            <button class="btn btn-sm btn-danger ms-2" onclick="removeFromCartByIndex(${index})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    cartItems.innerHTML = itemsHTML;
    modalCartItems.innerHTML = itemsHTML;
    cartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
    cartTotal.textContent = total.toFixed(2);
    modalCartTotal.textContent = total.toFixed(2);
}

// Update item quantity by index
function updateCartQuantity(index, change) {
    if (index < 0 || index >= cart.length) return;
    
    cart[index].quantity += change;
    
    if (cart[index].quantity <= 0) {
        removeFromCartByIndex(index);
    } else {
        updateCartDisplay();
    }
}

// Remove item from cart by index
function removeFromCartByIndex(index) {
    if (index < 0 || index >= cart.length) return;
    
    const removedItem = cart.splice(index, 1)[0];
    updateCartDisplay();
    showToast(`${removedItem.name} eliminado del carrito`);
}

// Initialize order type toggle
function initializeOrderTypeToggle() {
    const deliveryRadio = document.getElementById('delivery');
    const pickupRadio = document.getElementById('pickup');
    
    if (deliveryRadio && pickupRadio) {
        deliveryRadio.addEventListener('change', toggleOrderType);
        pickupRadio.addEventListener('change', toggleOrderType);
    }
}

// Toggle between delivery and pickup sections
function toggleOrderType() {
    const deliverySection = document.getElementById('deliverySection');
    const pickupSection = document.getElementById('pickupSection');
    const deliveryFeeRow = document.getElementById('deliveryFeeRow');
    const isDelivery = document.getElementById('delivery').checked;
    
    // Get delivery required fields
    const deliveryRequiredFields = document.querySelectorAll('.delivery-required');
    
    if (isDelivery) {
        deliverySection.style.display = 'block';
        pickupSection.style.display = 'none';
        deliveryFeeRow.style.display = 'flex';
        
        // Make delivery fields required
        deliveryRequiredFields.forEach(field => {
            field.setAttribute('required', 'required');
        });
    } else {
        deliverySection.style.display = 'none';
        pickupSection.style.display = 'block';
        deliveryFeeRow.style.display = 'none';
        loadStoreLocations();
        
        // Remove required attribute from delivery fields
        deliveryRequiredFields.forEach(field => {
            field.removeAttribute('required');
        });
    }
    
    updateCheckoutTotal();
}

// Load store locations for pickup
function loadStoreLocations() {
    const container = document.getElementById('storeLocations');
    
    // Mock store locations - replace with actual API call
    const stores = [
        {
            id: 1,
            name: 'Sucursal Centro',
            address: 'Av. Principal #123, Centro',
            phone: '555-0001',
            hours: 'Lun-Dom: 8:00 AM - 10:00 PM'
        },
        {
            id: 2,
            name: 'Sucursal Norte',
            address: 'Calle Norte #456, Zona Norte',
            phone: '555-0002',
            hours: 'Lun-Dom: 9:00 AM - 9:00 PM'
        }
    ];
    
    container.innerHTML = '';
    stores.forEach(store => {
        const storeCard = document.createElement('div');
        storeCard.className = 'card mb-3';
        storeCard.innerHTML = `
            <div class="card-body">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="pickup_store" value="${store.id}" id="store_${store.id}">
                    <label class="form-check-label w-100" for="store_${store.id}">
                        <h6>${store.name}</h6>
                        <p class="mb-1"><i class="fas fa-map-marker-alt"></i> ${store.address}</p>
                        <p class="mb-1"><i class="fas fa-phone"></i> ${store.phone}</p>
                        <small class="text-muted"><i class="fas fa-clock"></i> ${store.hours}</small>
                    </label>
                </div>
            </div>
        `;
        container.appendChild(storeCard);
    });
}

// Proceed to checkout with enhanced form
function proceedToCheckout() {
    if (cart.length === 0) {
        showToast('Tu carrito está vacío', 'error');
        return;
    }
    
    // Initialize map when modal opens
    setTimeout(() => {
        initializeMap();
    }, 500);
    
    updateCheckoutSummary();
    
    const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
    modal.show();
}

// Update checkout summary
function updateCheckoutSummary() {
    const container = document.getElementById('checkoutCartItems');
    const subtotalElement = document.getElementById('checkoutSubtotal');
    const totalElement = document.getElementById('checkoutTotal');
    
    let subtotal = 0;
    let itemsHTML = '';
    
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        
        let additionalsText = '';
        if (item.additionals && item.additionals.length > 0) {
            additionalsText = ' + ' + item.additionals.map(a => a.name).join(', ');
        }
        
        itemsHTML += `
            <div class="d-flex justify-content-between mb-2">
                <div>
                    <strong>${item.name}</strong>${additionalsText}
                    <br><small class="text-muted">Cantidad: ${item.quantity}</small>
                    ${item.notes ? `<br><small class="text-info">${item.notes}</small>` : ''}
                </div>
                <div class="text-end">
                    <strong>$${itemTotal.toFixed(2)}</strong>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = itemsHTML;
    subtotalElement.textContent = subtotal.toFixed(2);
    
    updateCheckoutTotal();
}

// Update checkout total with delivery fee
function updateCheckoutTotal() {
    const subtotal = parseFloat(document.getElementById('checkoutSubtotal').textContent);
    const isDelivery = document.getElementById('delivery') && document.getElementById('delivery').checked;
    const deliveryFee = isDelivery ? 3.00 : 0;
    const total = subtotal + deliveryFee;
    
    document.getElementById('checkoutTotal').textContent = total.toFixed(2);
}

// Lookup existing customer
async function lookupCustomer() {
    const lookup = document.getElementById('customerLookup').value.trim();
    
    if (!lookup) {
        showToast('Ingrese un email o teléfono para buscar', 'error');
        return;
    }
    
    try {
        const response = await fetch('api/customer_lookup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ lookup: lookup })
        });
        
        const result = await response.json();
        
        if (result.success && result.customer) {
            currentCustomer = result.customer;
            populateCustomerData(result.customer);
            loadCustomerAddresses(result.customer.id);
            showToast('Cliente encontrado y datos cargados', 'success');
        } else {
            showToast('Cliente no encontrado. Puede registrarse como nuevo cliente.', 'info');
            currentCustomer = null;
        }
    } catch (error) {
        console.error('Error looking up customer:', error);
        showToast('Error al buscar cliente', 'error');
    }
}

// Populate customer data in form
function populateCustomerData(customer) {
    document.getElementById('firstName').value = customer.first_name || '';
    document.getElementById('lastName').value = customer.last_name || '';
    document.getElementById('customerEmail').value = customer.email || '';
    
    // Load phone numbers
    if (customer.phones && customer.phones.length > 0) {
        const primaryPhone = customer.phones.find(p => p.is_primary) || customer.phones[0];
        const whatsappPhone = customer.phones.find(p => p.is_whatsapp);
        
        document.getElementById('phonePrimary').value = primaryPhone ? primaryPhone.phone_number : '';
        document.getElementById('phoneWhatsapp').value = whatsappPhone ? whatsappPhone.phone_number : '';
    }
}

// Load customer addresses
async function loadCustomerAddresses(customerId) {
    try {
        const response = await fetch(`api/customer_addresses.php?customer_id=${customerId}`);
        const result = await response.json();
        
        if (result.success && result.addresses.length > 0) {
            const container = document.getElementById('addressesList');
            const existingAddressesDiv = document.getElementById('existingAddresses');
            
            container.innerHTML = '';
            result.addresses.forEach(address => {
                const addressCard = document.createElement('div');
                addressCard.className = 'card mb-2';
                addressCard.innerHTML = `
                    <div class="card-body p-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="selected_address" 
                                   value="${address.id}" id="address_${address.id}">
                            <label class="form-check-label w-100" for="address_${address.id}">
                                <strong>${address.address_type}</strong>
                                ${address.is_primary ? '<span class="badge bg-primary ms-1">Principal</span>' : ''}
                                <br>
                                <small>${address.street_address}, ${address.city}</small>
                                ${address.delivery_instructions ? `<br><small class="text-muted">${address.delivery_instructions}</small>` : ''}
                            </label>
                        </div>
                    </div>
                `;
                container.appendChild(addressCard);
            });
            
            existingAddressesDiv.style.display = 'block';
            document.getElementById('newAddressForm').style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading customer addresses:', error);
    }
}

// Show new address form
function showNewAddressForm() {
    document.getElementById('newAddressForm').style.display = 'block';
    // Clear selected addresses
    const addressRadios = document.querySelectorAll('input[name="selected_address"]');
    addressRadios.forEach(radio => radio.checked = false);
}

// Initialize map
function initializeMap() {
    if (map) return; // Map already initialized
    
    const mapContainer = document.getElementById('map');
    if (!mapContainer) return;
    
    // Initialize map centered on a default location
    map = L.map('map').setView([10.4806, -66.9036], 13); // Caracas, Venezuela
    
    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Add click event to map
    map.on('click', function(e) {
        setMapMarker(e.latlng.lat, e.latlng.lng);
    });
}

// Get current location
function getCurrentLocation() {
    if (!navigator.geolocation) {
        showToast('Geolocalización no soportada por este navegador', 'error');
        return;
    }
    
    navigator.geolocation.getCurrentPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            if (map) {
                map.setView([lat, lng], 16);
                setMapMarker(lat, lng);
                
                // Reverse geocoding to get address
                reverseGeocode(lat, lng);
            }
        },
        function(error) {
            showToast('Error al obtener ubicación: ' + error.message, 'error');
        }
    );
}

// Set marker on map
function setMapMarker(lat, lng) {
    if (marker) {
        map.removeLayer(marker);
    }
    
    marker = L.marker([lat, lng]).addTo(map);
    
    // Update hidden inputs
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
}

// Reverse geocoding
async function reverseGeocode(lat, lng) {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
        const data = await response.json();
        
        if (data && data.display_name) {
            const addressParts = data.display_name.split(',');
            document.getElementById('streetAddress').value = addressParts.slice(0, 2).join(',').trim();
            document.getElementById('city').value = addressParts[addressParts.length - 3]?.trim() || '';
        }
    } catch (error) {
        console.error('Error in reverse geocoding:', error);
    }
}

// Search address
async function searchAddress() {
    const address = document.getElementById('streetAddress').value.trim();
    
    if (!address) {
        showToast('Ingrese una dirección para buscar', 'error');
        return;
    }
    
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`);
        const data = await response.json();
        
        if (data && data.length > 0) {
            const result = data[0];
            const lat = parseFloat(result.lat);
            const lng = parseFloat(result.lon);
            
            if (map) {
                map.setView([lat, lng], 16);
                setMapMarker(lat, lng);
            }
        } else {
            showToast('Dirección no encontrada', 'error');
        }
    } catch (error) {
        console.error('Error searching address:', error);
        showToast('Error al buscar dirección', 'error');
    }
}

// Complete order with enhanced data
async function completeOrder() {
    const form = document.getElementById('checkoutForm');
    const formData = new FormData(form);
    
    // Validate required fields
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const isDelivery = document.getElementById('delivery').checked;
    
    // Prepare order data
    const orderData = {
        customer: {
            first_name: formData.get('first_name'),
            last_name: formData.get('last_name'),
            email: formData.get('email'),
            phones: [
                {
                    phone_number: formData.get('phone_primary'),
                    phone_type: 'mobile',
                    is_primary: true,
                    is_whatsapp: false
                }
            ]
        },
        order_type: isDelivery ? 'delivery' : 'pickup',
        payment_method: formData.get('payment_method'),
        items: cart,
        delivery_fee: isDelivery ? 3.00 : 0
    };
    
    // Add WhatsApp phone if provided
    const whatsappPhone = formData.get('phone_whatsapp');
    if (whatsappPhone && whatsappPhone.trim()) {
        orderData.customer.phones.push({
            phone_number: whatsappPhone.trim(),
            phone_type: 'mobile',
            is_primary: false,
            is_whatsapp: true
        });
    }
    
    // Add address data for delivery
    if (isDelivery) {
        const selectedAddressId = document.querySelector('input[name="selected_address"]:checked')?.value;
        
        if (selectedAddressId) {
            orderData.address_id = selectedAddressId;
        } else {
            // New address
            orderData.address = {
                street_address: formData.get('street_address'),
                city: formData.get('city'),
                postal_code: formData.get('postal_code'),
                delivery_instructions: formData.get('delivery_instructions'),
                latitude: formData.get('latitude'),
                longitude: formData.get('longitude'),
                address_type: 'delivery',
                is_primary: false
            };
        }
    } else {
        // Pickup store
        const selectedStore = document.querySelector('input[name="pickup_store"]:checked')?.value;
        if (selectedStore) {
            orderData.pickup_store_id = selectedStore;
        }
    }
    
    try {
        const response = await fetch('api/orders_new.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(orderData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('¡Pedido realizado exitosamente!', 'success');
            cart = [];
            updateCartDisplay();
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('checkoutModal')).hide();
            
            // Reset form
            form.reset();
            currentCustomer = null;
            
            // Show order confirmation
            showOrderConfirmation(result.order_id, result.total);
        } else {
            showToast(result.message || 'Error al procesar el pedido', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error al procesar el pedido', 'error');
    }
}

// Show order confirmation
function showOrderConfirmation(orderId, total) {
    const confirmationHTML = `
        <div class="alert alert-success alert-dismissible fade show order-success" role="alert">
            <h4 class="alert-heading"><i class="fas fa-check-circle"></i> ¡Pedido Confirmado!</h4>
            <p>Tu pedido #${orderId} ha sido realizado exitosamente.</p>
            <p><strong>Total: $${parseFloat(total).toFixed(2)}</strong></p>
            <hr>
            <p class="mb-0">Recibirás una confirmación por email y te contactaremos pronto.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('.container');
    container.insertAdjacentHTML('afterbegin', confirmationHTML);
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Show toast notification
function showToast(message, type = 'success') {
    const toastHTML = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger')} border-0" 
             role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    const toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
    toastContainer.innerHTML = toastHTML;
    document.body.appendChild(toastContainer);
    
    const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
    toast.show();
    
    setTimeout(() => {
        toastContainer.remove();
    }, 3000);
}

// Filter products by category
function filterProducts(category) {
    // Update active button
    const buttons = document.querySelectorAll('#categoryFilter button');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    if (category === 'all') {
        displayProducts(products);
    } else {
        const filtered = products.filter(product => product.category === category);
        displayProducts(filtered);
    }
}

// Load product sizes
function loadProductSizes(sizes) {
    const sizesContainer = document.getElementById('productSizesContainer');
    const sizesSection = document.getElementById('productSizes');
    
    if (!sizesContainer || !sizesSection) return;
    
    sizesContainer.innerHTML = '';
    
    if (sizes && sizes.length > 0) {
        sizesSection.style.display = 'block';
        
        sizes.forEach((size, index) => {
            const sizeItem = document.createElement('div');
            sizeItem.className = 'form-check mb-2';
            sizeItem.innerHTML = `
                <input class="form-check-input size-radio" type="radio" 
                       name="product_size" value="${size.id}" id="size_${size.id}"
                       data-price="${size.price}" data-name="${size.name}"
                       ${index === 0 ? 'checked' : ''} onchange="updateProductTotalPrice()">
                <label class="form-check-label d-flex justify-content-between w-100" for="size_${size.id}">
                    <div>
                        <strong>${size.name}</strong>
                        ${size.description ? `<br><small class="text-muted">${size.description}</small>` : ''}
                    </div>
                    <span class="text-success">$${parseFloat(size.price).toFixed(2)}</span>
                </label>
            `;
            sizesContainer.appendChild(sizeItem);
        });
    } else {
        sizesSection.style.display = 'none';
    }
}

// Load product additionals from data
function loadProductAdditionalsFromData(additionals) {
    const container = document.getElementById('productAdditionalsContainer');
    const additionalsSection = document.getElementById('productAdditionals');
    
    if (!container || !additionalsSection) return;
    
    container.innerHTML = '';
    
    if (additionals && additionals.length > 0) {
        additionalsSection.style.display = 'block';
        
        additionals.forEach(additional => {
            const additionalItem = document.createElement('div');
            additionalItem.className = 'form-check mb-2';
            additionalItem.innerHTML = `
                <input class="form-check-input additional-checkbox" type="checkbox" 
                       value="${additional.id}" id="additional_${additional.id}"
                       data-price="${additional.price}" data-name="${additional.name}"
                       ${additional.is_default ? 'checked' : ''} onchange="updateProductTotalPrice()">
                <label class="form-check-label d-flex justify-content-between w-100" for="additional_${additional.id}">
                    <div>
                        <strong>${additional.name}</strong>
                        ${additional.description ? `<br><small class="text-muted">${additional.description}</small>` : ''}
                        ${additional.category_name ? `<br><small class="badge bg-secondary">${additional.category_name}</small>` : ''}
                    </div>
                    <span class="text-success">+$${parseFloat(additional.price).toFixed(2)}</span>
                </label>
            `;
            container.appendChild(additionalItem);
        });
    } else {
        additionalsSection.style.display = 'none';
    }
}

// Load product image gallery
function loadProductImageGallery(images) {
    const thumbnailsContainer = document.getElementById('productImageThumbnails');
    const mainImage = document.getElementById('productModalMainImage');
    
    if (!thumbnailsContainer) return;
    
    thumbnailsContainer.innerHTML = '';
    
    if (images && images.length > 1) {
        images.forEach((image, index) => {
            const thumbnail = document.createElement('img');
            thumbnail.src = image.image_path;
            thumbnail.className = 'img-thumbnail me-2 mb-2';
            thumbnail.style.width = '80px';
            thumbnail.style.height = '80px';
            thumbnail.style.objectFit = 'cover';
            thumbnail.style.cursor = 'pointer';
            thumbnail.onclick = () => {
                if (mainImage) {
                    mainImage.src = image.image_path;
                }
            };
            thumbnailsContainer.appendChild(thumbnail);
        });
    }
}

// Set initial price based on selected size or base price
function setInitialPrice() {
    const modalPrice = document.getElementById('productModalPrice');
    if (!modalPrice || !currentProduct) return;
    
    // Check if there are sizes available
    const selectedSize = document.querySelector('input[name="product_size"]:checked');
    if (selectedSize) {
        modalPrice.textContent = parseFloat(selectedSize.dataset.price).toFixed(2);
    } else {
        modalPrice.textContent = parseFloat(currentProduct.price || currentProduct.base_price || 0).toFixed(2);
    }
}

// Enhanced update total price function
function updateProductTotalPrice() {
    if (!currentProduct) return;
    
    const quantity = parseInt(document.getElementById('productQuantity').value);
    let basePrice = 0;
    
    // Get price from selected size or base price
    const selectedSize = document.querySelector('input[name="product_size"]:checked');
    if (selectedSize) {
        basePrice = parseFloat(selectedSize.dataset.price);
        // Update the displayed price
        const modalPrice = document.getElementById('productModalPrice');
        if (modalPrice) {
            modalPrice.textContent = basePrice.toFixed(2);
        }
    } else {
        basePrice = parseFloat(currentProduct.price || currentProduct.base_price || 0);
    }
    
    let totalPrice = basePrice * quantity;
    
    // Add selected additionals
    const selectedAdditionals = document.querySelectorAll('.additional-checkbox:checked');
    selectedAdditionals.forEach(checkbox => {
        totalPrice += parseFloat(checkbox.dataset.price) * quantity;
    });
    
    const totalPriceElement = document.getElementById('productTotalPrice');
    if (totalPriceElement) {
        totalPriceElement.textContent = totalPrice.toFixed(2);
    }
}

// Enhanced add to cart function
function addProductToCart() {
    if (!currentProduct) return;
    
    const quantity = parseInt(document.getElementById('productQuantity').value);
    const notes = document.getElementById('productNotes').value.trim();
    
    // Get selected size
    let selectedSize = null;
    const sizeRadio = document.querySelector('input[name="product_size"]:checked');
    if (sizeRadio) {
        selectedSize = {
            id: sizeRadio.value,
            name: sizeRadio.dataset.name,
            price: parseFloat(sizeRadio.dataset.price)
        };
    }
    
    // Get selected additionals
    const selectedAdditionals = [];
    const additionalCheckboxes = document.querySelectorAll('.additional-checkbox:checked');
    additionalCheckboxes.forEach(checkbox => {
        selectedAdditionals.push({
            id: checkbox.value,
            name: checkbox.dataset.name,
            price: parseFloat(checkbox.dataset.price)
        });
    });
    
    // Calculate total price
    let basePrice = selectedSize ? selectedSize.price : parseFloat(currentProduct.price || currentProduct.base_price || 0);
    let itemPrice = basePrice;
    selectedAdditionals.forEach(additional => {
        itemPrice += additional.price;
    });
    
    // Create cart item
    const cartItem = {
        id: parseInt(currentProduct.id),
        name: currentProduct.name,
        price: itemPrice,
        basePrice: basePrice,
        quantity: quantity,
        notes: notes,
        size: selectedSize,
        additionals: selectedAdditionals
    };
    
    // Create unique key for cart item
    const itemKey = `${cartItem.id}_${selectedSize ? selectedSize.id : 'no_size'}_${JSON.stringify(selectedAdditionals)}_${notes}`;
    
    // Check if identical item already exists
    const existingItemIndex = cart.findIndex(item => {
        const existingKey = `${item.id}_${item.size ? item.size.id : 'no_size'}_${JSON.stringify(item.additionals)}_${item.notes}`;
        return existingKey === itemKey;
    });
    
    if (existingItemIndex !== -1) {
        cart[existingItemIndex].quantity += quantity;
    } else {
        cart.push(cartItem);
    }
    
    updateCartDisplay();
    
    // Create display name for toast
    let displayName = currentProduct.name;
    if (selectedSize) {
        displayName += ` (${selectedSize.name})`;
    }
    
    showToast(`${quantity} x ${displayName} agregado al carrito`);
    
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
}

// Enhanced cart display function
function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    const modalCartItems = document.getElementById('modalCartItems');
    const cartCount = document.getElementById('cartCount');
    const cartTotal = document.getElementById('cartTotal');
    const modalCartTotal = document.getElementById('modalCartTotal');
    
    let total = 0;
    let itemsHTML = '';
    
    if (cart.length === 0) {
        itemsHTML = '<p class="text-muted text-center">Tu carrito está vacío</p>';
    } else {
        cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            
            let sizeText = '';
            if (item.size) {
                sizeText = `<br><small class="text-primary"><i class="fas fa-expand-arrows-alt"></i> ${item.size.name}</small>`;
            }
            
            let additionalsText = '';
            if (item.additionals && item.additionals.length > 0) {
                additionalsText = '<br><small class="text-info"><i class="fas fa-plus"></i> ' + 
                    item.additionals.map(a => a.name).join(', ') + '</small>';
            }
            
            itemsHTML += `
                <div class="cart-item mb-3 p-2 border rounded">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <strong>${item.name}</strong>
                            ${sizeText}
                            ${additionalsText}
                            <br>
                            <small class="text-muted">$${item.price.toFixed(2)} x ${item.quantity} = $${itemTotal.toFixed(2)}</small>
                            ${item.notes ? `<br><small class="text-warning"><i class="fas fa-sticky-note"></i> ${item.notes}</small>` : ''}
                        </div>
                        <div class="quantity-controls d-flex align-items-center">
                            <button class="btn btn-sm btn-outline-secondary" onclick="updateCartQuantity(${index}, -1)">-</button>
                            <span class="mx-2">${item.quantity}</span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="updateCartQuantity(${index}, 1)">+</button>
                            <button class="btn btn-sm btn-danger ms-2" onclick="removeFromCartByIndex(${index})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    if (cartItems) cartItems.innerHTML = itemsHTML;
    if (modalCartItems) modalCartItems.innerHTML = itemsHTML;
    if (cartCount) cartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
    if (cartTotal) cartTotal.textContent = total.toFixed(2);
    if (modalCartTotal) modalCartTotal.textContent = total.toFixed(2);
}

// ===== ORDER TRACKING SYSTEM =====

function showOrderTracking() {
    const modal = new bootstrap.Modal(document.getElementById('orderTrackingModal'));
    modal.show();
}

async function trackOrder() {
    const orderNumber = document.getElementById('orderTrackingNumber').value.trim();
    
    if (!orderNumber) {
        showToast('Ingrese un número de pedido', 'error');
        return;
    }
    
    try {
        showLoadingOverlay();
        
        const response = await fetch(`api/order_tracking.php?order_id=${encodeURIComponent(orderNumber)}`);
        const result = await response.json();
        
        hideLoadingOverlay();
        
        if (result.success) {
            displayOrderTracking(result.order);
        } else {
            showToast(result.message || 'Pedido no encontrado', 'error');
            document.getElementById('orderTrackingResult').classList.add('d-none');
        }
    } catch (error) {
        hideLoadingOverlay();
        console.error('Error tracking order:', error);
        showToast('Error al buscar el pedido', 'error');
    }
}

function displayOrderTracking(order) {
    const container = document.getElementById('orderTrackingResult');
    
    const statusClass = getOrderStatusClass(order.status);
    const timeline = generateOrderTimeline(order);
    
    container.innerHTML = `
        <div class="order-tracking">
            <div class="tracking-header">
                <div class="order-number">Pedido #${order.id}</div>
                <div class="order-status ${statusClass}">${getOrderStatusText(order.status)}</div>
                <p class="text-muted mt-2">Realizado el ${formatDate(order.created_at)}</p>
            </div>
            
            <div class="tracking-timeline">
                ${timeline}
            </div>
            
            <div class="order-details mt-4">
                <h6><i class="fas fa-receipt"></i> Detalles del Pedido</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Cliente:</strong> ${order.customer_name}</p>
                        <p><strong>Teléfono:</strong> ${order.customer_phone}</p>
                        <p><strong>Tipo:</strong> ${order.order_type === 'delivery' ? 'Delivery' : 'Recoger en tienda'}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Total:</strong> $${parseFloat(order.total).toFixed(2)}</p>
                        <p><strong>Método de pago:</strong> ${order.payment_method}</p>
                        ${order.estimated_delivery ? `<p><strong>Tiempo estimado:</strong> ${order.estimated_delivery}</p>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.classList.remove('d-none');
}

function getOrderStatusClass(status) {
    const statusClasses = {
        'pending': 'status-pending',
        'confirmed': 'status-confirmed',
        'preparing': 'status-preparing',
        'ready': 'status-ready',
        'delivered': 'status-delivered',
        'completed': 'status-delivered'
    };
    return statusClasses[status] || 'status-pending';
}

function getOrderStatusText(status) {
    const statusTexts = {
        'pending': 'Pendiente',
        'confirmed': 'Confirmado',
        'preparing': 'Preparando',
        'ready': 'Listo',
        'delivered': 'Entregado',
        'completed': 'Completado'
    };
    return statusTexts[status] || 'Desconocido';
}

function generateOrderTimeline(order) {
    const statuses = [
        { key: 'pending', title: 'Pedido Recibido', description: 'Tu pedido ha sido recibido y está siendo procesado' },
        { key: 'confirmed', title: 'Pedido Confirmado', description: 'Hemos confirmado tu pedido y comenzamos la preparación' },
        { key: 'preparing', title: 'Preparando', description: 'Nuestros chefs están preparando tu deliciosa comida' },
        { key: 'ready', title: 'Listo', description: order.order_type === 'delivery' ? 'Tu pedido está listo y en camino' : 'Tu pedido está listo para recoger' },
        { key: 'delivered', title: order.order_type === 'delivery' ? 'Entregado' : 'Completado', description: 'Tu pedido ha sido completado. ¡Disfruta!' }
    ];
    
    const currentStatusIndex = statuses.findIndex(s => s.key === order.status);
    
    return statuses.map((status, index) => {
        const isCompleted = index <= currentStatusIndex;
        const isActive = index === currentStatusIndex;
        const itemClass = isCompleted ? 'completed' : (isActive ? 'active' : '');
        
        return `
            <div class="timeline-item ${itemClass}">
                <div class="timeline-content">
                    <div class="timeline-title">${status.title}</div>
                    <div class="timeline-description">${status.description}</div>
                    ${isCompleted ? `<div class="timeline-time">${formatTime(order.updated_at)}</div>` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

// ===== EVENT LISTENERS & UTILITIES =====

function setupEventListeners() {
    // Keyboard shortcuts
    document.addEventListener('keydown', handleKeyboardShortcuts);
    
    // Window resize handler
    window.addEventListener('resize', debounce(handleWindowResize, 250));
    
    // Online/offline status
    window.addEventListener('online', handleOnlineStatus);
    window.addEventListener('offline', handleOfflineStatus);
    
    // Visibility change (tab switching)
    document.addEventListener('visibilitychange', handleVisibilityChange);
}

function handleKeyboardShortcuts(event) {
    // Ctrl/Cmd + K for search
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
        event.preventDefault();
        document.getElementById('productSearch').focus();
    }
    
    // Escape to close modals
    if (event.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal.show');
        if (openModals.length > 0) {
            bootstrap.Modal.getInstance(openModals[openModals.length - 1]).hide();
        }
    }
    
    // Ctrl/Cmd + Shift + D for dark mode toggle
    if ((event.ctrlKey || event.metaKey) && event.shiftKey && event.key === 'D') {
        event.preventDefault();
        toggleTheme();
    }
}

function handleWindowResize() {
    // Adjust layout for different screen sizes
    const isMobile = window.innerWidth < 768;
    
    if (isMobile) {
        // Mobile optimizations
        document.querySelectorAll('.cart-sidebar').forEach(el => {
            el.style.position = 'static';
        });
    } else {
        // Desktop optimizations
        document.querySelectorAll('.cart-sidebar').forEach(el => {
            el.style.position = 'sticky';
        });
    }
}

function handleOnlineStatus() {
    showToast('Conexión restaurada', 'success');
    // Retry failed requests
    retryFailedRequests();
}

function handleOfflineStatus() {
    showToast('Sin conexión a internet', 'error');
}

function handleVisibilityChange() {
    if (document.hidden) {
        // Page is hidden - pause animations, etc.
        pauseAnimations();
    } else {
        // Page is visible - resume animations
        resumeAnimations();
    }
}

function pauseAnimations() {
    document.querySelectorAll('.loading-spinner, .pulse').forEach(el => {
        el.style.animationPlayState = 'paused';
    });
}

function resumeAnimations() {
    document.querySelectorAll('.loading-spinner, .pulse').forEach(el => {
        el.style.animationPlayState = 'running';
    });
}

function retryFailedRequests() {
    // Implementation for retrying failed API requests
    console.log('Retrying failed requests...');
}

// ===== ENHANCED CART FUNCTIONALITY =====

function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    const cartCountSidebar = document.getElementById('cartCountSidebar');
    const cartCount = document.getElementById('cartCount');
    const cartTotal = document.getElementById('cartTotal');
    const cartTotalSection = document.querySelector('.cart-total');
    
    let total = 0;
    let itemCount = 0;
    let itemsHTML = '';
    
    if (cart.length === 0) {
        itemsHTML = `
            <div class="text-center text-muted p-4">
                <i class="fas fa-shopping-cart fa-3x mb-3 opacity-50"></i>
                <p>Tu carrito está vacío</p>
                <small>Agrega algunos productos deliciosos</small>
            </div>
        `;
        cartTotalSection.classList.add('d-none');
    } else {
        cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            itemCount += item.quantity;
            
            let sizeText = '';
            if (item.size) {
                sizeText = `<br><small class="text-primary"><i class="fas fa-expand-arrows-alt"></i> ${item.size.name}</small>`;
            }
            
            let additionalsText = '';
            if (item.additionals && item.additionals.length > 0) {
                additionalsText = '<br><small class="text-info"><i class="fas fa-plus"></i> ' + 
                    item.additionals.map(a => a.name).join(', ') + '</small>';
            }
            
            itemsHTML += `
                <div class="cart-item">
                    <div class="cart-item-header">
                        <h6 class="cart-item-name">${item.name}</h6>
                        <div class="cart-item-price">$${itemTotal.toFixed(2)}</div>
                    </div>
                    <div class="cart-item-details">
                        ${sizeText}
                        ${additionalsText}
                        ${item.notes ? `<br><small class="text-warning"><i class="fas fa-sticky-note"></i> ${item.notes}</small>` : ''}
                        <br><small class="text-muted">$${item.price.toFixed(2)} x ${item.quantity}</small>
                    </div>
                    <div class="quantity-controls">
                        <button class="quantity-btn" onclick="updateCartQuantity(${index}, -1)">-</button>
                        <span class="quantity-display">${item.quantity}</span>
                        <button class="quantity-btn" onclick="updateCartQuantity(${index}, 1)">+</button>
                        <button class="btn-remove" onclick="removeFromCartByIndex(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        cartTotalSection.classList.remove('d-none');
    }
    
    if (cartItems) cartItems.innerHTML = itemsHTML;
    if (cartCountSidebar) cartCountSidebar.textContent = itemCount;
    if (cartCount) cartCount.textContent = itemCount;
    if (cartTotal) cartTotal.textContent = total.toFixed(2);
    
    // Update modal cart as well
    const modalCartItems = document.getElementById('modalCartItems');
    const modalCartTotal = document.getElementById('modalCartTotal');
    if (modalCartItems) modalCartItems.innerHTML = itemsHTML;
    if (modalCartTotal) modalCartTotal.textContent = total.toFixed(2);
    
    // Animate cart count badge
    if (itemCount > 0) {
        const badge = document.getElementById('cartCount');
        if (badge) {
            badge.classList.add('pulse');
            setTimeout(() => badge.classList.remove('pulse'), 1000);
        }
    }
}

// ===== ENHANCED PRODUCT DISPLAY =====

function displayProducts(productsToShow) {
    const container = document.getElementById('productsContainer');
    
    // Remove skeleton cards
    container.querySelectorAll('.skeleton-card').forEach(card => card.remove());
    
    if (productsToShow.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4>No se encontraron productos</h4>
                <p class="text-muted">Intenta con otros términos de búsqueda o filtros</p>
                <button class="btn btn-primary" onclick="clearSearch()">
                    <i class="fas fa-times"></i> Limpiar filtros
                </button>
            </div>
        `;
        return;
    }
    
    // Clear container completely
    container.innerHTML = '';
    
    console.log('Displaying products:', productsToShow.length); // Debug log
    
    productsToShow.forEach((product, index) => {
        console.log('Creating card for product:', product.name); // Debug log
        const productCard = createProductCard(product, index);
        container.appendChild(productCard);
        
        // Observe images for lazy loading
        const img = productCard.querySelector('.product-image');
        if (img && imageObserver) {
            imageObserver.observe(img);
        }
    });
    
    // Add stagger animation
    setTimeout(() => {
        container.querySelectorAll('.product-card').forEach((card, index) => {
            setTimeout(() => {
                card.classList.add('fade-in');
            }, index * 100);
        });
    }, 50);
}

function createProductCard(product, index) {
    const card = document.createElement('div');
    card.className = 'product-card';
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'all 0.3s ease';
    
    // Use main_image if available, fallback to image, then placeholder
    const imageUrl = product.main_image || product.image || 'https://via.placeholder.com/300x200?text=' + encodeURIComponent(product.name);
    
    // Show base price or price range
    let priceDisplay = '';
    if (product.sizes && product.sizes.length > 0) {
        const minPrice = Math.min(...product.sizes.map(s => parseFloat(s.price)));
        const maxPrice = Math.max(...product.sizes.map(s => parseFloat(s.price)));
        if (minPrice === maxPrice) {
            priceDisplay = `$${minPrice.toFixed(2)}`;
        } else {
            priceDisplay = `$${minPrice.toFixed(2)} - $${maxPrice.toFixed(2)}`;
        }
    } else {
        priceDisplay = `$${parseFloat(product.price || product.base_price || 0).toFixed(2)}`;
    }
    
    // Category badge
    const categoryBadge = product.category_name ? 
        `<div class="category-badge" style="background-color: ${product.category_color || 'var(--primary-orange)'}">${product.category_name}</div>` : '';
    
    card.innerHTML = `
        <div class="product-image-container">
            <img data-src="${imageUrl}" 
                 class="product-image lazy" 
                 alt="${product.name}"
                 onerror="this.src='https://via.placeholder.com/300x200?text=' + encodeURIComponent('${product.name}')">
            ${categoryBadge}
        </div>
        <div class="product-card-body">
            <h5 class="product-title">${product.name}</h5>
            <p class="product-description">${product.description || 'Sin descripción'}</p>
            <div class="product-price">${priceDisplay}</div>
            ${product.sizes && product.sizes.length > 1 ? '<small class="text-muted">Múltiples tamaños disponibles</small>' : ''}
            <button class="btn-view-details w-100 mt-2" onclick="openProductModal(${product.id})">
                <i class="fas fa-eye"></i> Ver Detalles
            </button>
        </div>
    `;
    
    // Add click handler for the entire card
    card.addEventListener('click', (e) => {
        if (!e.target.closest('button')) {
            openProductModal(product.id);
        }
    });
    
    return card;
}

// ===== ENHANCED CATEGORY FILTERING =====

function loadCategories() {
    const categories = [...new Set(products.map(p => p.category_name || p.category).filter(Boolean))];
    const filterContainer = document.querySelector('.category-filters');
    
    // Clear existing category buttons (except "Todos")
    const existingButtons = filterContainer.querySelectorAll('.filter-btn:not([data-category="all"])');
    existingButtons.forEach(btn => btn.remove());
    
    categories.forEach(category => {
        const button = document.createElement('button');
        button.className = 'filter-btn';
        button.setAttribute('data-category', category);
        button.innerHTML = `<i class="fas fa-tag"></i> ${category.charAt(0).toUpperCase() + category.slice(1)}`;
        button.onclick = () => filterProducts(category);
        filterContainer.appendChild(button);
    });
}

function filterProducts(category) {
    currentFilters.category = category;
    
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-category="${category}"]`).classList.add('active');
    
    performSearch();
}

// Legacy functions - kept for compatibility
function addToCart(productId) {
    openProductModal(productId);
}

function updateQuantity(productId, change) {
    const index = cart.findIndex(item => parseInt(item.id) === parseInt(productId));
    if (index !== -1) {
        updateCartQuantity(index, change);
    }
}

function removeFromCart(productId) {
    const index = cart.findIndex(item => parseInt(item.id) === parseInt(productId));
    if (index !== -1) {
        removeFromCartByIndex(index);
    }
}
