<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Pedidos - Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Leaflet CSS for maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-utensils"></i> Restaurante Express</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#cartModal">
                            <i class="fas fa-shopping-cart"></i> Carrito 
                            <span class="badge bg-danger" id="cartCount">0</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <h2 class="mb-4">Menú de Productos</h2>
                
                <!-- Category Filter -->
                <div class="mb-4">
                    <div class="btn-group" role="group" id="categoryFilter">
                        <button type="button" class="btn btn-outline-primary active" onclick="filterProducts('all')">
                            Todos
                        </button>
                    </div>
                </div>
                
                <div id="productsContainer" class="row">
                    <!-- Products will be loaded here -->
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Carrito de Compras</h5>
                    </div>
                    <div class="card-body">
                        <div id="cartItems">
                            <p class="text-muted text-center">Tu carrito está vacío</p>
                        </div>
                        <div class="mt-3">
                            <h5>Total: $<span id="cartTotal">0.00</span></h5>
                            <button class="btn btn-success w-100 mt-2" onclick="proceedToCheckout()">
                                <i class="fas fa-credit-card"></i> Proceder al Pago
                            </button>
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
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle">Detalles del Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Image Gallery -->
                        <div class="col-md-6">
                            <div class="product-gallery">
                                <div class="main-image mb-3">
                                    <img id="productModalMainImage" src="" class="img-fluid rounded w-100" alt="" style="height: 400px; object-fit: cover;">
                                </div>
                                <div class="image-thumbnails" id="productImageThumbnails">
                                    <!-- Thumbnails will be loaded here -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Product Details -->
                        <div class="col-md-6">
                            <h4 id="productModalName"></h4>
                            <p id="productModalDescription" class="text-muted"></p>
                            <h5 class="text-success mb-3">$<span id="productModalPrice"></span></h5>
                            
                            <!-- Quantity Selector -->
                            <div class="mb-3">
                                <label class="form-label">Cantidad</label>
                                <div class="input-group" style="width: 150px;">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(-1)">-</button>
                                    <input type="number" class="form-control text-center" id="productQuantity" value="1" min="1" max="10">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(1)">+</button>
                                </div>
                            </div>

                            <!-- Additional Options -->
                            <div class="mb-3" id="productAdditionals">
                                <label class="form-label">Adicionales</label>
                                <div id="productAdditionalsContainer">
                                    <!-- Additionals will be loaded here -->
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="mb-3">
                                <label class="form-label">Notas u Observaciones</label>
                                <textarea class="form-control" id="productNotes" rows="3" placeholder="Ej: Sin cebolla, extra queso, etc."></textarea>
                            </div>

                            <!-- Total Price -->
                            <div class="mb-3">
                                <h5>Total: $<span id="productTotalPrice">0.00</span></h5>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="addProductToCart()">
                        <i class="fas fa-cart-plus"></i> Agregar al Carrito
                    </button>
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
                    <div class="row">
                        <!-- Customer Information -->
                        <div class="col-md-6">
                            <h6 class="mb-3"><i class="fas fa-user"></i> Información del Cliente</h6>
                            <form id="checkoutForm">
                                <!-- Customer Lookup -->
                                <div class="mb-3">
                                    <label class="form-label">Email o Teléfono (para buscar cliente existente)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="customerLookup" placeholder="Ingrese email o teléfono">
                                        <button class="btn btn-outline-primary" type="button" onclick="lookupCustomer()">
                                            <i class="fas fa-search"></i> Buscar
                                        </button>
                                    </div>
                                    <small class="text-muted">Si ya eres cliente, ingresa tu email o teléfono para cargar tus datos</small>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre *</label>
                                            <input type="text" class="form-control" name="first_name" id="firstName" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Apellido *</label>
                                            <input type="text" class="form-control" name="last_name" id="lastName" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" id="customerEmail" required>
                                </div>

                                <!-- Phone Numbers -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Teléfono Principal *</label>
                                            <input type="tel" class="form-control" name="phone_primary" id="phonePrimary" required>
                                            <small class="text-muted">Para llamadas</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">WhatsApp</label>
                                            <input type="tel" class="form-control" name="phone_whatsapp" id="phoneWhatsapp">
                                            <small class="text-muted">Para mensajes WhatsApp</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Order Type -->
                                <div class="mb-3">
                                    <label class="form-label">Tipo de Pedido *</label>
                                    <div class="btn-group w-100" role="group">
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

                                <!-- Delivery Address Section -->
                                <div id="deliverySection">
                                    <h6 class="mb-3"><i class="fas fa-map-marker-alt"></i> Dirección de Entrega</h6>
                                    
                                    <!-- Existing Addresses -->
                                    <div class="mb-3" id="existingAddresses" style="display: none;">
                                        <label class="form-label">Direcciones Guardadas</label>
                                        <div id="addressesList">
                                            <!-- Existing addresses will be loaded here -->
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="showNewAddressForm()">
                                            <i class="fas fa-plus"></i> Agregar Nueva Dirección
                                        </button>
                                    </div>

                                    <!-- New Address Form -->
                                    <div id="newAddressForm">
                                        <div class="mb-3">
                                            <label class="form-label">Dirección Completa *</label>
                                            <textarea class="form-control" name="street_address" id="streetAddress" rows="2" 
                                                      placeholder="Calle, número, colonia, referencias" required></textarea>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Ciudad *</label>
                                                    <input type="text" class="form-control" name="city" id="city" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Código Postal</label>
                                                    <input type="text" class="form-control" name="postal_code" id="postalCode">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Instrucciones de Entrega</label>
                                            <textarea class="form-control" name="delivery_instructions" id="deliveryInstructions" rows="2" 
                                                      placeholder="Ej: Casa azul, portón negro, tocar timbre"></textarea>
                                        </div>

                                        <!-- Map for Location -->
                                        <div class="mb-3">
                                            <label class="form-label">Ubicación en el Mapa</label>
                                            <div class="d-flex gap-2 mb-2">
                                                <button type="button" class="btn btn-sm btn-outline-success" onclick="getCurrentLocation()">
                                                    <i class="fas fa-crosshairs"></i> Mi Ubicación Actual
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="searchAddress()">
                                                    <i class="fas fa-search"></i> Buscar Dirección
                                                </button>
                                            </div>
                                            <div id="map" style="height: 300px; border-radius: 8px;"></div>
                                            <input type="hidden" name="latitude" id="latitude">
                                            <input type="hidden" name="longitude" id="longitude">
                                        </div>
                                    </div>
                                </div>

                                <!-- Pickup Location Section -->
                                <div id="pickupSection" style="display: none;">
                                    <h6 class="mb-3"><i class="fas fa-store"></i> Seleccionar Sucursal</h6>
                                    <div id="storeLocations">
                                        <!-- Store locations will be loaded here -->
                                    </div>
                                </div>

                                <!-- Payment Method -->
                                <div class="mb-3">
                                    <label class="form-label">Método de Pago *</label>
                                    <select class="form-select" name="payment_method" required>
                                        <option value="">Seleccione un método</option>
                                        <option value="efectivo">Efectivo</option>
                                        <option value="tarjeta">Tarjeta de Crédito/Débito</option>
                                        <option value="transferencia">Transferencia Bancaria</option>
                                        <option value="pago_movil">Pago Móvil</option>
                                        <option value="paypal">PayPal</option>
                                    </select>
                                </div>
                            </form>
                        </div>

                        <!-- Order Summary -->
                        <div class="col-md-6">
                            <h6 class="mb-3"><i class="fas fa-receipt"></i> Resumen del Pedido</h6>
                            <div class="card">
                                <div class="card-body">
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
                    </div>
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
    <script src="assets/js/app.js"></script>
</body>
</html>
