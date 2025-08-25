<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlavorFinder - Restaurante Express</title>
    
    <!-- Preload critical fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Leaflet CSS for maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Custom FlavorFinder CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/product-modal-improvements.css" rel="stylesheet">
    
    <!-- Meta tags for SEO and PWA -->
    <meta name="description" content="FlavorFinder - Ordena tu comida favorita con una experiencia única que estimula tu apetito">
    <meta name="keywords" content="restaurante, comida, pedidos, delivery, FlavorFinder">
    <meta name="theme-color" content="#E67E22">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay d-none">
        <div class="loading-content">
            <div class="loading-spinner-large"></div>
            <div class="loading-text">Cargando FlavorFinder...</div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="navbar-content">
                <a class="navbar-brand" href="#">
                    <i class="fas fa-utensils"></i> FlavorFinder
                </a>
                
                <div class="navbar-menu">
                    <!-- Order Tracking -->
                    <a class="nav-link" href="#" onclick="showOrderTracking()">
                        <i class="fas fa-truck"></i> Seguir Pedido
                    </a>
                    
                    <!-- Dark Mode Toggle -->
                    <button class="theme-toggle" onclick="toggleTheme()" title="Cambiar tema">
                        <i class="fas fa-moon" id="themeIcon"></i>
                    </button>
                    
                    <!-- Cart -->
                    <a class="nav-link cart-link" href="#" data-bs-toggle="modal" data-bs-target="#cartModal">
                        <i class="fas fa-shopping-cart"></i> Carrito
                        <span class="cart-badge" id="cartCount">0</span>
                    </a>
                </div>
                
                <!-- Mobile Menu Toggle -->
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <!-- Mobile Menu -->
            <div class="mobile-menu" id="mobileMenu">
                <a class="mobile-nav-link" href="#" onclick="showOrderTracking()">
                    <i class="fas fa-truck"></i> Seguir Pedido
                </a>
                <button class="mobile-theme-toggle" onclick="toggleTheme()">
                    <i class="fas fa-moon" id="mobileThemeIcon"></i> Cambiar Tema
                </button>
                <a class="mobile-nav-link" href="#" data-bs-toggle="modal" data-bs-target="#cartModal">
                    <i class="fas fa-shopping-cart"></i> Carrito (<span id="mobileCartCount">0</span>)
                </a>
            </div>
        </div>
    </nav>

    <!-- Promotional Banners Carousel - Full Width -->
    <div class="promotional-banners-fullwidth">
            <div id="promotionalCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#promotionalCarousel" data-bs-slide-to="0" class="active"></button>
                    <button type="button" data-bs-target="#promotionalCarousel" data-bs-slide-to="1"></button>
                    <button type="button" data-bs-target="#promotionalCarousel" data-bs-slide-to="2"></button>
                </div>
                
                <div class="carousel-inner">
                    <!-- Banner 1 - Hamburguesas Premium -->
                    <div class="carousel-item active">
                        <div class="promotional-banner" onclick="goToProduct(1)" style="cursor: pointer;">
                            <div class="banner-content">
                                <div class="banner-text">
                                    <h2 class="banner-title">🍔 Hamburguesas Premium</h2>
                                    <p class="banner-subtitle">Ingredientes frescos, sabor incomparable</p>
                                    <div class="banner-cta">
                                        <span class="cta-text">¡Ordena ahora!</span>
                                        <i class="fas fa-arrow-right ms-2"></i>
                                    </div>
                                </div>
                                <div class="banner-image">
                                    <img src="https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=800&h=400&fit=crop&crop=center" 
                                         alt="Hamburguesas Premium" class="banner-img">
                                </div>
                            </div>
                            <div class="banner-overlay"></div>
                        </div>
                    </div>
                    
                    <!-- Banner 2 - Bebidas Refrescantes -->
                    <div class="carousel-item">
                        <div class="promotional-banner" onclick="filterProducts('Bebidas')" style="cursor: pointer;">
                            <div class="banner-content">
                                <div class="banner-text">
                                    <h2 class="banner-title">🥤 Bebidas Refrescantes</h2>
                                    <p class="banner-subtitle">Perfectas para acompañar tu comida</p>
                                    <div class="banner-cta">
                                        <span class="cta-text">Ver bebidas</span>
                                        <i class="fas fa-arrow-right ms-2"></i>
                                    </div>
                                </div>
                                <div class="banner-image">
                                    <img src="https://images.unsplash.com/photo-1544145945-f90425340c7e?w=800&h=400&fit=crop&crop=center" 
                                         alt="Bebidas Refrescantes" class="banner-img">
                                </div>
                            </div>
                            <div class="banner-overlay"></div>
                        </div>
                    </div>
                    
                    <!-- Banner 3 - Ofertas Especiales -->
                    <div class="carousel-item">
                        <div class="promotional-banner" onclick="showSpecialOffers()" style="cursor: pointer;">
                            <div class="banner-content">
                                <div class="banner-text">
                                    <h2 class="banner-title">🎉 Ofertas Especiales</h2>
                                    <p class="banner-subtitle">Descuentos increíbles por tiempo limitado</p>
                                    <div class="banner-cta">
                                        <span class="cta-text">Ver ofertas</span>
                                        <i class="fas fa-arrow-right ms-2"></i>
                                    </div>
                                </div>
                                <div class="banner-image">
                                    <img src="https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=800&h=400&fit=crop&crop=center" 
                                         alt="Ofertas Especiales" class="banner-img">
                                </div>
                            </div>
                            <div class="banner-overlay"></div>
                        </div>
                    </div>
                </div>
                
                <button class="carousel-control-prev" type="button" data-bs-target="#promotionalCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                    <span class="visually-hidden">Anterior</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#promotionalCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                    <span class="visually-hidden">Siguiente</span>
                </button>
            </div>
        </div>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Minimal Search Section -->
        <div class="minimal-search-section fade-in">
            <div class="search-input-group">
                <div class="search-icon">
                    <i class="fas fa-search"></i>
                </div>
                <input type="text" 
                       id="productSearch" 
                       class="minimal-search-input" 
                       placeholder="Buscar productos..."
                       autocomplete="off">
                <div class="search-clear" onclick="clearSearchInput()" style="display: none;">
                    <i class="fas fa-times"></i>
                </div>
                <div id="searchSuggestions" class="search-suggestions d-none"></div>
            </div>
            
            <!-- Compact Filters -->
            <div class="compact-filters" id="compactFilters" style="display: none;">
                <div class="price-filter-compact">
                    <input type="number" 
                           id="minPrice" 
                           class="price-input-compact" 
                           placeholder="Precio mín."
                           min="0" step="0.50">
                    <input type="number" 
                           id="maxPrice" 
                           class="price-input-compact" 
                           placeholder="Precio máx."
                           min="0" step="0.50">
                    <button class="btn-apply-filters" onclick="performSearch()">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
            </div>
            
            <!-- Filter Toggle -->
            <button class="filter-toggle" onclick="toggleFilters()" title="Filtros avanzados">
                <i class="fas fa-sliders-h"></i>
            </button>
            
            <!-- Active Filters Display -->
            <div id="activeFilters" class="active-filters-display"></div>
        </div>

        <!-- Quick Actions - Solo Ofertas y Populares -->
        <div class="quick-actions mb-4">
            <button class="quick-action-btn" onclick="showSpecialOffers()">
                <i class="fas fa-fire"></i>
                <span>Ofertas</span>
            </button>
            <button class="quick-action-btn" onclick="showPopularItems()">
                <i class="fas fa-star"></i>
                <span>Populares</span>
            </button>
        </div>

        <!-- Advanced Search Section (Legacy - keeping for compatibility) -->
        <div class="advanced-search fade-in d-none">
            <div class="search-row">
                <div class="search-field">
                    <label class="search-label" for="productSearch">
                        <i class="fas fa-search"></i> Buscar productos
                    </label>
                    <input type="text" 
                           id="productSearch" 
                           class="search-input-advanced" 
                           placeholder="Busca tu platillo favorito..."
                           autocomplete="off">
                    <div id="searchSuggestions" class="search-suggestions d-none"></div>
                </div>
                
                <button class="btn-search" onclick="performSearch()">
                    <i class="fas fa-search"></i> Buscar
                </button>
                
                <button class="btn-clear" onclick="clearSearch()">
                    <i class="fas fa-times"></i> Limpiar
                </button>
            </div>
            
            <!-- Price Range Filter -->
            <div class="price-range">
                <input type="number" 
                       id="minPrice" 
                       class="search-input-advanced" 
                       placeholder="Precio mín."
                       min="0" step="0.50">
                <span class="price-separator">-</span>
                <input type="number" 
                       id="maxPrice" 
                       class="search-input-advanced" 
                       placeholder="Precio máx."
                       min="0" step="0.50">
            </div>
            
            <!-- Active Filters -->
            <div id="activeFilters" class="active-filters"></div>
        </div>

        <!-- Category Filters -->
        <div class="category-filters slide-in-right">
            <button class="filter-btn active" data-category="all" onclick="filterProducts('all')">
                <i class="fas fa-th-large"></i> Todos
            </button>
            <!-- Dynamic category buttons will be loaded here -->
        </div>
        
        <!-- Products Grid -->
        <div id="productsContainer" class="products-grid">
            <!-- Skeleton Loading -->
            <div class="product-card skeleton-card">
                <div class="skeleton skeleton-image"></div>
                <div class="product-card-body">
                    <div class="skeleton skeleton-text title"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text price"></div>
                </div>
            </div>
            <!-- More skeleton cards... -->
            <div class="product-card skeleton-card">
                <div class="skeleton skeleton-image"></div>
                <div class="product-card-body">
                    <div class="skeleton skeleton-text title"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text price"></div>
                </div>
            </div>
            <div class="product-card skeleton-card">
                <div class="skeleton skeleton-image"></div>
                <div class="product-card-body">
                    <div class="skeleton skeleton-text title"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text price"></div>
                </div>
            </div>
        </div>
        
        <!-- Lazy Load Trigger -->
        <div id="lazyLoadTrigger" class="lazy-load-trigger d-none">
            <div class="loading-spinner"></div>
            <span class="ms-2">Cargando más productos...</span>
        </div>
    </div>

    <!-- Order Tracking Modal -->
    <div class="modal fade" id="orderTrackingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-truck"></i> Seguimiento de Pedido
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="order-tracking-container">
                        <div class="order-tracking-form">
                            <div class="tracking-input-group">
                                <input type="text" class="form-control" id="orderTrackingNumber" 
                                       placeholder=" " required>
                                <label for="orderTrackingNumber">Número de Pedido</label>
                            </div>
                            
                            <div class="tracking-input-group">
                                <input type="text" class="form-control" id="orderTrackingContact" 
                                       placeholder=" " required>
                                <label for="orderTrackingContact">Email o Teléfono</label>
                            </div>
                            
                            <button class="btn-track btn-track-order" onclick="trackOrder()">
                                <i class="fas fa-search"></i> Buscar Pedido
                            </button>
                        </div>
                        
                        <div id="orderTrackingResult" class="order-tracking-result d-none">
                            <!-- Order tracking content will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart Modal -->
    <div class="modal fade" id="cartModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Carrito de Compras</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="modalCartItems"></div>
                    <h5 class="mt-3">Total: $<span id="modalCartTotal">0.00</span></h5>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-success" onclick="proceedToCheckout()">
                        <i class="fas fa-credit-card"></i> Proceder al Pago
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Detail Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content product-modal">
                <div class="modal-header product-modal-header">
                    <div class="product-modal-title-section">
                        <h4 class="modal-title product-modal-title" id="productModalTitle">
                            <i class="fas fa-utensils me-2"></i>Detalles del Producto
                        </h4>
                        <div class="product-modal-badge">
                            <i class="fas fa-star text-warning"></i>
                            <span class="ms-1">Premium</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-custom" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body product-modal-body">
                    <div class="row g-4">
                        <!-- Image Gallery -->
                        <div class="col-lg-6">
                            <div class="product-gallery-enhanced">
                                <div class="main-image-container">
                                    <img id="productModalMainImage" src="" class="product-main-image" alt="">
                                    <div class="image-overlay">
                                        <button class="btn btn-light btn-sm zoom-btn">
                                            <i class="fas fa-search-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="image-thumbnails-enhanced mt-3" id="productImageThumbnails">
                                    <!-- Thumbnails will be loaded here -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Product Details -->
                        <div class="col-lg-6">
                            <div class="product-details-section">
                                <!-- Product Header -->
                                <div class="product-header mb-4">
                                    <h3 class="product-name" id="productModalName"></h3>
                                    <p class="product-description" id="productModalDescription"></p>
                                    <div class="price-section">
                                        <span class="current-price">$<span id="productModalPrice"></span></span>
                                        <span class="price-label">Precio base</span>
                                    </div>
                                </div>

                                <!-- Size Selector -->
                                <div class="option-section mb-4" id="productSizes" style="display: none;">
                                    <h6 class="option-title">
                                        <i class="fas fa-expand-arrows-alt me-2"></i>Selecciona el tamaño
                                    </h6>
                                    <div class="size-options" id="productSizesContainer">
                                        <!-- Size options will be loaded here -->
                                    </div>
                                </div>
                                
                                <!-- Additional Options -->
                                <div class="option-section mb-4" id="productAdditionals">
                                    <h6 class="option-title">
                                        <i class="fas fa-plus-circle me-2"></i>Adicionales
                                    </h6>
                                    <div class="additionals-grid" id="productAdditionalsContainer">
                                        <!-- Additionals will be loaded here -->
                                    </div>
                                </div>

                                <!-- Quantity Selector -->
                                <div class="option-section mb-4">
                                    <h6 class="option-title">
                                        <i class="fas fa-sort-numeric-up me-2"></i>Cantidad
                                    </h6>
                                    <div class="quantity-selector">
                                        <button class="quantity-btn quantity-minus" type="button" onclick="changeQuantity(-1)">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="quantity-input" id="productQuantity" value="1" min="1" max="10" readonly>
                                        <button class="quantity-btn quantity-plus" type="button" onclick="changeQuantity(1)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="option-section mb-4">
                                    <h6 class="option-title">
                                        <i class="fas fa-sticky-note me-2"></i>Notas especiales
                                    </h6>
                                    <textarea class="form-control notes-textarea" id="productNotes" rows="3" 
                                              placeholder="¿Alguna preferencia especial? Ej: Sin cebolla, extra queso, etc."></textarea>
                                </div>

                                <!-- Total Price Section -->
                                <div class="total-section">
                                    <div class="total-breakdown">
                                        <div class="total-line">
                                            <span>Subtotal:</span>
                                            <span class="total-amount">$<span id="productTotalPrice">0.00</span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer product-modal-footer">
                    <div class="footer-content">
                        <div class="total-display">
                            <span class="total-label">Total:</span>
                            <span class="total-price">$<span id="productTotalPriceFooter">0.00</span></span>
                        </div>
                        <div class="action-buttons">
                            <button type="button" class="btn btn-outline-secondary btn-cancel" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-primary btn-add-cart" onclick="addProductToCart()">
                                <i class="fas fa-cart-plus me-2"></i>Agregar al Carrito
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Checkout Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Finalizar Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Order Summary - Moved to Top -->
                    <div class="mb-4 checkout-order-summary">
                        <h6 class="mb-3 text-center"><i class="fas fa-receipt"></i> Resumen del Pedido</h6>
                        <div class="zara-summary-card">
                            <div class="zara-summary-body">
                                <div id="checkoutCartItems"></div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Subtotal:</strong>
                                    <strong>$<span id="checkoutSubtotal">0.00</span></strong>
                                </div>
                                <div class="d-flex justify-content-between" id="deliveryFeeRow">
                                    <span>Costo de Delivery:</span>
                                    <span>$<span id="deliveryFee">3.00</span></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <h5>Total:</h5>
                                    <h5 class="text-success">$<span id="checkoutTotal">0.00</span></h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Information - Moved to Top after Order Summary -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <!-- Customer Authentication Status -->
                            <div id="customerInfoDisplay" class="mb-3">
                                <!-- Customer auth info will be displayed here -->
                            </div>
                            
                            <!-- Expandable Customer Form -->
                            <div class="customer-info-expandable">
                                <div class="customer-info-header" onclick="toggleCustomerForm()">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user me-2"></i>
                                            <h6 class="mb-0">Información del Cliente</h6>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <span class="customer-form-status me-2" id="customerFormStatus">Completar datos</span>
                                            <i class="fas fa-chevron-down customer-form-arrow" id="customerFormArrow"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="customer-info-content" id="customerInfoContent">
                                    <div class="mb-3" style="height: 2px; background-color: #E67E22; border-radius: 1px; margin-top: 15px;"></div>
                                    
                                    <form id="checkoutForm">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label font-semibold">Nombre <span class="text-red-500 text-lg">*</span></label>
                                                    <input type="text" class="form-control border-2 focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all duration-200" 
                                                           name="first_name" id="firstName" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label font-semibold">Apellido <span class="text-red-500 text-lg">*</span></label>
                                                    <input type="text" class="form-control border-2 focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all duration-200" 
                                                           name="last_name" id="lastName" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label font-semibold">Email <span class="text-red-500 text-lg">*</span></label>
                                            <input type="email" class="form-control border-2 focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all duration-200" 
                                                   name="email" id="customerEmail" required>
                                        </div>

                                        <!-- Phone Numbers -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label font-semibold">Teléfono Principal <span class="text-red-500 text-lg">*</span></label>
                                                    <input type="tel" class="form-control border-2 focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all duration-200" 
                                                           name="phone_primary" id="phonePrimary" required>
                                                    <small class="text-muted">Para llamadas</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label font-semibold">WhatsApp</label>
                                                    <input type="tel" class="form-control border-2 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition-all duration-200" 
                                                           name="phone_whatsapp" id="phoneWhatsapp">
                                                    <small class="text-muted">Para mensajes WhatsApp</small>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Type - MOVED OUTSIDE EXPANDABLE CONTAINER -->
                    <div class="mb-4">
                        <label class="form-label">Tipo de Pedido *</label>
                        <div class="btn-group w-100 order-type-buttons" role="group">
                            <input type="radio" class="btn-check" name="order_type" id="delivery" value="delivery" checked>
                            <label class="btn btn-outline-primary" for="delivery">
                                <i class="fas fa-truck"></i> Delivery
                            </label>
                            
                            <input type="radio" class="btn-check" name="order_type" id="pickup" value="pickup">
                            <label class="btn btn-outline-primary" for="pickup">
                                <i class="fas fa-store"></i> Recoger en Tienda
                            </label>
                        </div>
                    </div>

                    <!-- Delivery Address Section - MOVED OUTSIDE EXPANDABLE CONTAINER -->
                    <div id="deliverySection" class="mb-4">
                        <h6 class="mb-3"><i class="fas fa-map-marker-alt"></i> Dirección de Entrega</h6>
                        
                        <!-- Existing Addresses -->
                        <div class="mb-3" id="existingAddresses" style="display: none;">
                            <label class="form-label">Direcciones Guardadas</label>
                            <div id="addressesList">
                                <!-- Existing addresses will be loaded here -->
                            </div>
                            <button type="button" class="btn btn-sm mt-2" onclick="showNewAddressForm()" style="background-color: #ff6b35; border-color: #ff6b35; color: white;">
                                <i class="fas fa-plus"></i> Agregar Nueva Dirección
                            </button>
                        </div>

                        <!-- New Address Form -->
                        <div id="newAddressForm">
                            <div class="mb-3">
                                <label class="form-label font-semibold">Dirección Completa <span class="text-red-500 text-lg">*</span></label>
                                <textarea class="form-control border-2 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200 delivery-required" 
                                          name="street_address" id="streetAddress" rows="2" required
                                          placeholder="Calle, número, colonia, referencias"></textarea>
                                <small class="text-red-600 font-medium">⚠️ Campo obligatorio para delivery</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label font-semibold">Ciudad <span class="text-red-500 text-lg">*</span></label>
                                        <input type="text" class="form-control border-2 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200 delivery-required" 
                                               name="city" id="city" required>
                                        <small class="text-red-600 font-medium">⚠️ Campo obligatorio para delivery</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label font-semibold">Código Postal</label>
                                        <input type="text" class="form-control border-2 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition-all duration-200" 
                                               name="postal_code" id="postalCode">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label font-semibold">Instrucciones de Entrega</label>
                                <textarea class="form-control border-2 focus:border-green-400 focus:ring-2 focus:ring-green-100 transition-all duration-200" 
                                          name="delivery_instructions" id="deliveryInstructions" rows="2" 
                                          placeholder="Ej: Casa azul, portón negro, tocar timbre"></textarea>
                            </div>

                            <!-- Map for Location -->
                            <div class="mb-3">
                                <label class="form-label font-semibold">Ubicación en el Mapa <span class="text-red-500 text-lg">*</span></label>
                                <div class="d-flex gap-2 mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-success hover:bg-green-50 transition-colors duration-200" onclick="getCurrentLocation()">
                                        <i class="fas fa-crosshairs"></i> Mi Ubicación Actual
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary hover:bg-blue-50 transition-colors duration-200" onclick="searchAddress()">
                                        <i class="fas fa-search"></i> Buscar Dirección
                                    </button>
                                </div>
                                <div id="map" class="border-2 border-red-300 rounded-lg" style="height: 300px;"></div>
                                <small class="text-red-600 font-medium">⚠️ Debe marcar su ubicación en el mapa</small>
                                <input type="hidden" name="latitude" id="latitude" class="delivery-required">
                                <input type="hidden" name="longitude" id="longitude" class="delivery-required">
                            </div>
                        </div>
                    </div>

                    <!-- Pickup Location Section - MOVED OUTSIDE EXPANDABLE CONTAINER -->
                    <div id="pickupSection" class="mb-4" style="display: none;">
                        <h6 class="mb-3"><i class="fas fa-store"></i> Seleccionar Sucursal</h6>
                        <div id="storeLocations">
                            <!-- Store locations will be loaded here -->
                        </div>
                    </div>

                    <!-- Payment Method - MOVED OUTSIDE EXPANDABLE CONTAINER -->
                    <div class="mb-4">
                        <label class="form-label">Método de Pago *</label>
                        <select class="form-select" name="payment_method" id="paymentMethodSelect" required>
                            <option value="">Cargando métodos de pago...</option>
                        </select>
                        <!-- Container for dynamic payment method details will be inserted here by JavaScript -->
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="completeOrder()">
                        <i class="fas fa-check"></i> Completar Pedido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Leaflet JS for maps -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="assets/js/app_final.js"></script>
</body>
</html>
