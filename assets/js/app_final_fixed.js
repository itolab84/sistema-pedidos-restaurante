// Shopping cart functionality
let cart = [];
let products = [];
let additionals = [];
let currentProduct = null;
let currentCustomer = null;
let map = null;
let marker = null;

// Configuración del carrito persistente
const CART_STORAGE_KEY = 'flavorfinder_cart';
const CART_TIMESTAMP_KEY = 'flavorfinder_cart_timestamp';
const CART_EXPIRY_HOURS = 4;

// Funciones de persistencia del carrito
function saveCartToStorage() {
    try {
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
        localStorage.setItem(CART_TIMESTAMP_KEY, Date.now().toString());
    } catch (error) {
        console.error('Error saving cart to storage:', error);
    }
}

function loadCartFromStorage() {
    try {
        const savedCart = localStorage.getItem(CART_STORAGE_KEY);
        const savedTimestamp = localStorage.getItem(CART_TIMESTAMP_KEY);
        
        if (!savedCart || !savedTimestamp) {
            return;
        }
        
        const timestamp = parseInt(savedTimestamp);
        const now = Date.now();
        const hoursDiff = (now - timestamp) / (1000 * 60 * 60);
        
        if (hoursDiff > CART_EXPIRY_HOURS) {
            // Carrito expirado, limpiar storage
            clearCartStorage();
            return;
        }
        
        const parsedCart = JSON.parse(savedCart);
        if (Array.isArray(parsedCart)) {
            cart = parsedCart;
            updateCartDisplay();
        }
    } catch (error) {
        console.error('Error loading cart from storage:', error);
        clearCartStorage();
    }
}

function clearCartStorage() {
    try {
        localStorage.removeItem(CART_STORAGE_KEY);
        localStorage.removeItem(CART_TIMESTAMP_KEY);
    } catch (error) {
        console.error('Error clearing cart storage:', error);
    }
}

function isCartExpired() {
    try {
        const savedTimestamp = localStorage.getItem(CART_TIMESTAMP_KEY);
        if (!savedTimestamp) return true;
        
        const timestamp = parseInt(savedTimestamp);
        const now = Date.now();
        const hoursDiff = (now - timestamp) / (1000 * 60 * 60);
        
        return hoursDiff > CART_EXPIRY_HOURS;
    } catch (error) {
        return true;
    }
}

// Load products on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCartFromStorage(); // Cargar carrito persistente
    loadProducts();
    loadAdditionals();
    loadCompanyInfo(); // Cargar información de la empresa
    loadPaymentMethods(); // Cargar métodos de pago
    loadExchangeRate(); // Cargar tasa de cambio
    loadBanks(); // Cargar bancos
    initializeOrderTypeToggle();
    initializeTheme();
    setupEnhancedSearch();
    initializeCompleteOrderButton(); // Inicializar botón de completar pedido
});

// Load company information
async function loadCompanyInfo() {
    try {
        const response = await fetch('admin/company/api.php');
        const data = await response.json();
        
        if (data.success && data.company) {
            companyInfo = data.company;
            console.log('Company info loaded:', companyInfo);
        } else {
            // Fallback company info
            companyInfo = {
                name: 'FlavorFinder',
                rif: 'J-12345678-9',
                phone: '+58 414-1234567',
                email: 'info@flavorfinder.com'
            };
        }
    } catch (error) {
        console.error('Error loading company info:', error);
        // Fallback company info
        companyInfo = {
            name: 'FlavorFinder',
            rif: 'J-12345678-9',
            phone: '+58 414-1234567',
            email: 'info@flavorfinder.com'
        };
    }
}

// Initialize complete order button state
function initializeCompleteOrderButton() {
    const completeButton = document.querySelector('#checkoutModal .btn-success[onclick="completeOrder()"]');
    if (completeButton) {
        completeButton.disabled = true;
        completeButton.innerHTML = '<i class="fas fa-lock me-2"></i>Seleccione un método de pago';
    }
}

// Control complete order button state
function updateCompleteOrderButtonState() {
    const completeButton = document.querySelector('#checkoutModal .btn-success[onclick="completeOrder()"]');
    if (!completeButton) return;
    
    const selectedPaymentMethod = document.getElementById('selectedPaymentMethod')?.value;
    
    if (!selectedPaymentMethod) {
        completeButton.disabled = true;
        completeButton.innerHTML = '<i class="fas fa-lock me-2"></i>Seleccione un método de pago';
        return;
    }
    
    const methodName = selectedPaymentMethod.toLowerCase();
    
    // Check if payment requires validation
    if (methodName.includes('pagomovil') || methodName.includes('pago móvil') || methodName.includes('débito inmediato')) {
        // Check if payment is validated
        if (window.paymentData && window.paymentData.validated === true) {
            completeButton.disabled = false;
            completeButton.innerHTML = '<i class="fas fa-check me-2"></i>Completar Pedido';
        } else {
            completeButton.disabled = true;
            completeButton.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Debe validar el pago';
        }
    } else {
        // For other payment methods (cash, etc.), enable immediately
        completeButton.disabled = false;
        completeButton.innerHTML = '<i class="fas fa-check me-2"></i>Completar Pedido';
    }
}

// Initialize theme
function initializeTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcons(savedTheme);
}

// Toggle theme
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeIcons(newTheme);
}

// Update theme icons
function updateThemeIcons(theme) {
    const themeIcon = document.getElementById('themeIcon');
    const mobileThemeIcon = document.getElementById('mobileThemeIcon');
    
    if (themeIcon) {
        themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    if (mobileThemeIcon) {
        mobileThemeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// Mobile menu toggle
function toggleMobileMenu() {
    const mobileMenu = document.getElementById('mobileMenu');
    if (mobileMenu) {
        mobileMenu.classList.toggle('show');
    }
}

// Show order tracking modal
function showOrderTracking() {
    const modal = new bootstrap.Modal(document.getElementById('orderTrackingModal'));
    modal.show();
}

// Track order function
async function trackOrder() {
    const orderNumber = document.getElementById('orderTrackingNumber').value.trim();
    const contactInfo = document.getElementById('orderTrackingContact').value.trim();
    
    if (!orderNumber) {
        showToast('Por favor ingrese un número de pedido', 'error');
        return;
    }
    
    if (!contactInfo) {
        showToast('Por favor ingrese su email o teléfono para verificar el pedido', 'error');
        return;
    }
    
    // Show loading state
    const searchButton = document.querySelector('.btn-track-order');
    const originalText = searchButton.innerHTML;
    searchButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
    searchButton.disabled = true;
    
    try {
        const response = await fetch('api/order_tracking_fixed.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                order_number: orderNumber,
                contact_info: contactInfo
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayOrderStatusWithTimeline(result.order);
            showToast('Pedido encontrado exitosamente', 'success');
        } else {
            showToast(result.message || 'Pedido no encontrado o información de contacto incorrecta', 'error');
        }
    } catch (error) {
        console.error('Error tracking order:', error);
        showToast('Error al consultar el pedido. Por favor intente más tarde.', 'error');
    } finally {
        // Restore button state
        searchButton.innerHTML = originalText;
        searchButton.disabled = false;
    }
}

// Display order status with timeline
function displayOrderStatusWithTimeline(order) {
    const resultContainer = document.getElementById('orderTrackingResult');
    
    const statusColors = {
        'pending': 'warning',
        'confirmed': 'info',
        'preparing': 'primary',
        'ready': 'success',
        'delivered': 'success',
        'cancelled': 'danger'
    };
    
    const statusTexts = {
        'pending': 'Pendiente',
        'confirmed': 'Confirmado',
        'preparing': 'Preparando',
        'ready': 'Listo',
        'delivered': 'Entregado',
        'cancelled': 'Cancelado'
    };
    
    // Create timeline
    const statusOrder = ['pending', 'confirmed', 'preparing', 'ready', 'delivered'];
    const currentStatusIndex = statusOrder.indexOf(order.status);
    
    let timelineHtml = '<div class="order-timeline">';
    statusOrder.forEach((status, index) => {
        const isActive = index <= currentStatusIndex;
        const isCurrent = status === order.status;
        
        timelineHtml += `
            <div class="timeline-item ${isActive ? 'active' : ''} ${isCurrent ? 'current' : ''}">
                <div class="timeline-marker">
                    <i class="fas ${isActive ? 'fa-check' : 'fa-circle'}"></i>
                </div>
                <div class="timeline-content">
                    <h6>${statusTexts[status]}</h6>
                    ${isCurrent ? '<small class="text-muted">Estado actual</small>' : ''}
                </div>
            </div>
        `;
    });
    timelineHtml += '</div>';
    
    let itemsHtml = '';
    if (order.items && order.items.length > 0) {
        itemsHtml = order.items.map(item => `
            <div class="d-flex justify-content-between">
                <span>${item.product_name} x${item.quantity}</span>
                <span>$${parseFloat(item.price || 0).toFixed(2)}</span>
            </div>
        `).join('');
    }
    
    const html = `
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Pedido #${order.order_number}</h6>
                <span class="badge bg-${statusColors[order.status] || 'secondary'}">${statusTexts[order.status] || order.status}</span>
            </div>
            <div class="card-body">
                ${timelineHtml}
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Cliente:</strong> ${order.customer_name}</p>
                        <p><strong>Teléfono:</strong> ${order.customer_phone}</p>
                        <p><strong>Tipo:</strong> ${order.order_type === 'delivery' ? 'Delivery' : 'Recoger en tienda'}</p>
                        <p><strong>Pago:</strong> ${order.payment_method}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Total:</strong> $${parseFloat(order.total).toFixed(2)}</p>
                        <p><strong>Fecha:</strong> ${new Date(order.created_at).toLocaleString()}</p>
                        ${order.estimated_delivery ? `<p><strong>Tiempo estimado:</strong> ${order.estimated_delivery}</p>` : ''}
                    </div>
                </div>
                ${itemsHtml ? `
                    <hr>
                    <h6>Productos:</h6>
                    ${itemsHtml}
                ` : ''}
            </div>
        </div>
    `;
    
    resultContainer.innerHTML = html;
    resultContainer.classList.remove('d-none');
}

// Display order status (fallback for simple display)
function displayOrderStatus(order) {
    displayOrderStatusWithTimeline(order);
}

// Load products from API
async function loadProducts() {
    try {
        showLoadingState();
        const response = await fetch('api/products.php');
        const data = await response.json();
        
        if (data.success) {
            products = data.products;
            displayProducts(products);
            loadCategories();
        }
    } catch (error) {
        console.error('Error loading products:', error);
        showToast('Error al cargar productos', 'error');
    } finally {
        hideLoadingState();
    }
}

// Show loading state
function showLoadingState() {
    const container = document.getElementById('productsContainer');
    // Keep skeleton cards visible during loading
}

// Hide loading state
function hideLoadingState() {
    const container = document.getElementById('productsContainer');
    const skeletonCards = container.querySelectorAll('.skeleton-card');
    skeletonCards.forEach(card => card.remove());
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

// Load payment methods from API
let paymentMethods = [];
let exchangeRate = 36.50; // Default rate
let availableBanks = [];
let companyInfo = null;

async function loadPaymentMethods() {
    try {
        const response = await fetch('api/payment_methods.php');
        const data = await response.json();
        
        if (data.success) {
            paymentMethods = data.payment_methods;
            console.log('Payment methods loaded:', paymentMethods);
            updatePaymentMethodsInCheckout();
        } else {
            console.error('Error loading payment methods:', data.message);
            // Fallback to default payment methods
            paymentMethods = [
                { id: 1, name: 'Efectivo', configurations: [] },
                { id: 2, name: 'Tarjeta de Crédito', configurations: [] }
            ];
            updatePaymentMethodsInCheckout();
        }
    } catch (error) {
        console.error('Error loading payment methods:', error);
        // Fallback to default payment methods
        paymentMethods = [
            { id: 1, name: 'Efectivo', configurations: [] },
            { id: 2, name: 'Tarjeta de Crédito', configurations: [] }
        ];
        updatePaymentMethodsInCheckout();
    }
}

// Load exchange rate
async function loadExchangeRate() {
    try {
        const response = await fetch('api/exchange_rate.php');
        const data = await response.json();
        
        if (data.success) {
            exchangeRate = data.rate;
            console.log('Exchange rate loaded:', exchangeRate);
        }
    } catch (error) {
        console.error('Error loading exchange rate:', error);
    }
}

// Load banks
async function loadBanks() {
    try {
        const response = await fetch('api/banks.php');
        const data = await response.json();
        
        if (data.success) {
            availableBanks = data.banks;
            console.log('Banks loaded:', availableBanks);
        }
    } catch (error) {
        console.error('Error loading banks:', error);
    }
}

// Update payment methods in checkout modal with enhanced dropdown design
function updatePaymentMethodsInCheckout() {
    const paymentMethodContainer = document.querySelector('.payment-method-container');
    if (!paymentMethodContainer) {
        // Create container if it doesn't exist
        const paymentMethodSelect = document.querySelector('select[name="payment_method"]');
        if (!paymentMethodSelect) return;
        
        const container = document.createElement('div');
        container.className = 'payment-method-container';
        paymentMethodSelect.parentNode.insertBefore(container, paymentMethodSelect);
        paymentMethodSelect.style.display = 'none'; // Hide original select
    }
    
    // Clear existing content
    const container = document.querySelector('.payment-method-container');
    container.innerHTML = '';
    
    // Create enhanced payment method selector
    const selectorHTML = `
        <label class="form-label">Método de Pago <span class="text-danger">*</span></label>
        <div class="payment-methods-grid">
            ${createPaymentMethodCards(paymentMethods)}
        </div>
        <input type="hidden" name="payment_method" id="selectedPaymentMethod" required>
    `;
    
    container.innerHTML = selectorHTML;
}

// Create payment method cards
function createPaymentMethodCards(paymentMethods, company) {
    let cardsHTML = '';
    
    // Group payment methods by type
    const groupedMethods = {};
    
    paymentMethods.forEach(method => {
        const methodType = method.name.toLowerCase();
        
        if (!groupedMethods[methodType]) {
            groupedMethods[methodType] = {
                name: method.name,
                icon: getPaymentMethodIcon(method.name),
                configurations: []
            };
        }
        
        if (method.configurations && method.configurations.length > 0) {
            groupedMethods[methodType].configurations.push(...method.configurations);
        } else {
            // For methods without configurations, add a dummy configuration
            groupedMethods[methodType].configurations.push({
                config_id: method.id,
                method_name: method.name,
                is_default: true
            });
        }
    });
    
    // Create cards for each grouped method
    Object.values(groupedMethods).forEach(group => {
        const isPagomovil = group.name.toLowerCase().includes('pagomovil') || 
                           group.name.toLowerCase().includes('pago móvil') || 
                           group.name.toLowerCase().includes('pago movil');
        
        cardsHTML += `
            <div class="payment-method-group">
                <h6 class="payment-group-title">
                    <i class="fas fa-wallet me-2"></i>
                    ${group.name}
                </h6>
                <div class="payment-method-cards-container">
        `;
        
        // Create a single card for the grouped method
        const cardId = `payment_${group.name.replace(/\s+/g, '_').toLowerCase()}`;
        const methodValue = group.name;
        
        cardsHTML += `
            <div class="payment-method-card" data-method-value="${methodValue}" onclick="selectPaymentMethod('${methodValue}', '${cardId}')">
                <div class="payment-card-header">
                    <div class="payment-icon">
                        ${group.icon}
                    </div>
                    <div class="payment-title">
                        <h6 class="mb-0">${group.name}</h6>
                        <small class="text-muted">${group.configurations.length} opción(es) disponible(s)</small>
                    </div>
                </div>
                <div class="payment-card-body">
                    ${isPagomovil && company ? createCompanyInfoHTML(company) : ''}
                    <div class="payment-detail">
                        <i class="fas fa-info-circle me-1"></i> 
                        ${getPaymentMethodDescription(group.name)}
                    </div>
                </div>
                <div class="payment-card-footer">
                    <span class="payment-status">Disponible</span>
                </div>
            </div>
        `;
        
        // Close the group container
        cardsHTML += `
                </div>
            </div>
        `;
    });
    
    return cardsHTML;
}

// Create company information HTML
function createCompanyInfoHTML(company) {
    return `
        <div class="company-info mb-3 p-2 bg-light rounded">
            <h6 class="mb-2"><i class="fas fa-building me-2"></i>Información de la Empresa</h6>
            ${company.name ? `<div class="company-detail"><strong>Nombre:</strong> ${company.name}</div>` : ''}
            ${company.rif ? `<div class="company-detail"><strong>RIF:</strong> ${company.rif}</div>` : ''}
            <div class="separator my-2 border-top"></div>
            ${company.bank_name ? `<div class="company-detail"><strong>Banco:</strong> ${company.bank_name}</div>` : ''}
            ${company.bank_code ? `<div class="company-detail"><strong>Código Banco:</strong> ${company.bank_code}</div>` : ''}
            ${company.phone ? `<div class="company-detail"><strong>Teléfono:</strong> ${company.phone}</div>` : ''}
        </div>
    `;
}

// Get payment method icon
function getPaymentMethodIcon(methodName) {
    const name = methodName.toLowerCase();
    
    if (name.includes('pagomovil') || name.includes('pago móvil') || name.includes('pago movil')) {
        return '<i class="fas fa-mobile-alt"></i>';
    } else if (name.includes('efectivo')) {
        return '<i class="fas fa-money-bill-wave"></i>';
    } else if (name.includes('tarjeta') || name.includes('card')) {
        return '<i class="fas fa-credit-card"></i>';
    } else if (name.includes('transferencia') || name.includes('transfer')) {
        return '<i class="fas fa-exchange-alt"></i>';
    } else if (name.includes('zelle')) {
        return '<i class="fab fa-paypal"></i>';
    } else {
        return '<i class="fas fa-wallet"></i>';
    }
}

// Get payment method description
function getPaymentMethodDescription(methodName) {
    const name = methodName.toLowerCase();
    
    if (name.includes('efectivo') && name.includes('bolívar')) {
        return 'Pago en efectivo en bolívares';
    } else if (name.includes('efectivo')) {
        return 'Pago en efectivo en dólares';
    } else if (name.includes('tarjeta')) {
        return 'Pago con tarjeta de crédito/débito';
    } else {
        return 'Método de pago disponible';
    }
}

// Select payment method
function selectPaymentMethod(methodValue, cardId) {
    // Remove previous selection
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selection to clicked card
    const selectedCard = document.querySelector(`[data-method-value="${methodValue}"]`);
    if (selectedCard) {
        selectedCard.classList.add('selected');
    }
    
    // Update hidden input
    const hiddenInput = document.getElementById('selectedPaymentMethod');
    if (hiddenInput) {
        hiddenInput.value = methodValue;
    }
    
    // Show payment method details
    showPaymentMethodDetails(methodValue);
    
    // Update complete order button state
    updateCompleteOrderButtonState();
}

function showPaymentMethodDetails(methodValue) {
    // Remove existing payment details and forms
    const existingDetails = document.getElementById('paymentMethodDetails');
    if (existingDetails) {
        existingDetails.remove();
    }
    
    if (!methodValue) return;
    
    // Find the method and its configurations
    let selectedMethod = null;
    let methodConfigurations = [];
    let methodName = methodValue;
    
    // Find the method by name
    paymentMethods.forEach(method => {
        if (method.name === methodValue) {
            selectedMethod = method;
            methodName = method.name;
            methodConfigurations = method.configurations || [];
        }
    });
    
    // Create payment details section
    const detailsDiv = document.createElement('div');
    detailsDiv.id = 'paymentMethodDetails';
    detailsDiv.className = 'mt-4 payment-details-container';
    
    let detailsHTML = '';
    const lowerMethodName = methodName.toLowerCase();
    
    // Check if it's Pagomovil method
    if (lowerMethodName.includes('pagomovil') || lowerMethodName.includes('pago móvil') || lowerMethodName.includes('pago movil')) {
        // Use the first active configuration for Pagomovil
        const activeConfig = methodConfigurations.find(config => config.config_id) || methodConfigurations[0];
        detailsHTML = createPagomovilForm(
            activeConfig?.bank_name || '',
            activeConfig?.account_number || '',
            activeConfig?.pagomovil_number || '',
            activeConfig?.account_holder_name || '',
            activeConfig?.notes || '',
            methodConfigurations // Pass all configurations
        );
    }
    // Check if it's Efectivo Bolívares method
    else if (lowerMethodName.includes('efectivo') && lowerMethodName.includes('bolívar')) {
        detailsHTML = createEfectivoBolivaresForm();
    }
    // Check if it's regular Efectivo method
    else if (lowerMethodName.includes('efectivo')) {
        detailsHTML = createEfectivoForm();
    }
    // Other payment methods - show basic info
    else {
        const activeConfig = methodConfigurations.find(config => config.config_id) || methodConfigurations[0];
        detailsHTML = createBasicPaymentInfo(activeConfig);
    }
    
    detailsDiv.innerHTML = detailsHTML;
    
    // Insert after payment method container
    const container = document.querySelector('.payment-method-container');
    container.parentNode.insertBefore(detailsDiv, container.nextSibling);
}

// Create basic payment info for other methods
function createBasicPaymentInfo(config) {
    let infoHTML = '<div class="payment-info-card">';
    infoHTML += '<h6><i class="fas fa-info-circle me-2"></i>Información del Pago</h6>';
    
    if (config) {
        if (config.bank_name) {
            infoHTML += `<div class="info-item"><i class="fas fa-university me-2"></i><strong>Banco:</strong> ${config.bank_name}</div>`;
        }
        
        if (config.account_number) {
            infoHTML += `<div class="info-item"><i class="fas fa-credit-card me-2"></i><strong>Número de Cuenta:</strong> ${config.account_number}</div>`;
        }
        
        if (config.pagomovil_number) {
            infoHTML += `<div class="info-item"><i class="fas fa-mobile-alt me-2"></i><strong>Pago Móvil:</strong> ${config.pagomovil_number}</div>`;
        }
        
        if (config.account_holder_name) {
            infoHTML += `<div class="info-item"><i class="fas fa-user me-2"></i><strong>Titular:</strong> ${config.account_holder_name}</div>`;
        }
        
        if (config.notes) {
            infoHTML += `<div class="info-item"><i class="fas fa-sticky-note me-2"></i><strong>Notas:</strong> ${config.notes}</div>`;
        }
    }
    
    infoHTML += '<div class="alert alert-info mt-3 mb-0">';
    infoHTML += '<i class="fas fa-lightbulb me-2"></i>';
    infoHTML += 'Por favor, realice el pago usando estos datos y conserve el comprobante.';
    infoHTML += '</div>';
    infoHTML += '</div>';
    
    return infoHTML;
}

// Create Pagomovil payment form
function createPagomovilForm(bankName, accountNumber, pagomovil, holderName, notes, configurations = []) {
    const orderTotal = parseFloat(document.getElementById('checkoutTotal').textContent || 0);
    const amountInVes = (orderTotal * exchangeRate).toFixed(2);
    
    // Create options for multiple configurations if available
    let configurationOptions = '';
    if (configurations && configurations.length > 1) {
        configurationOptions = `
            <div class="mb-3">
                <label class="form-label">Seleccionar Cuenta</label>
                <select class="form-select" id="pagomovilConfigSelect" onchange="updatePagomovilConfig()">
                    ${configurations.map((config, index) => `
                        <option value="${index}" ${index === 0 ? 'selected' : ''}>
                            ${config.bank_name} - ${config.pagomovil_number}
                        </option>
                    `).join('')}
                </select>
            </div>
        `;
    }
    
    return `
        <div class="pagomovil-form">
            <h6><i class="fas fa-mobile-alt me-2"></i>Pago Móvil</h6>
            
            ${configurationOptions}
            
            <!-- Payment Info -->
            <div class="payment-info mb-3 p-2 bg-white rounded border" id="pagomovilPaymentInfo">
                ${bankName ? `<p class="mb-1"><strong>Banco:</strong> <span id="pagomovilBankName">${bankName}</span></p>` : ''}
                ${accountNumber ? `<p class="mb-1"><strong>Cuenta:</strong> <span id="pagomovilAccountNumber">${accountNumber}</span></p>` : ''}
                ${pagomovil ? `<p class="mb-1"><strong>Teléfono:</strong> <span id="pagomovilNumber">${pagomovil}</span></p>` : ''}
                ${holderName ? `<p class="mb-1"><strong>Titular:</strong> <span id="pagomovilHolderName">${holderName}</span></p>` :
