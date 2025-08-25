

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

// Load company information
let companyInfo = null;

async function loadCompanyInfo() {
    try {
        const response = await fetch('api/company_info.php');
        const data = await response.json();
        
        if (data.success) {
            companyInfo = data.company;
            console.log('Company info loaded:', companyInfo);
        }
    } catch (error) {
        console.error('Error loading company info:', error);
    }
}

// Update payment methods in checkout modal with accordion design
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
    
    // Create enhanced payment method selector with accordion
    const selectorHTML = `
        <div class="payment-method-selector">
            <label class="form-label">Método de Pago <span class="text-danger">*</span></label>
            <div class="accordion payment-accordion" id="paymentMethodAccordion">
                ${createPaymentMethodAccordionItems(paymentMethods)}
            </div>
            <input type="hidden" name="payment_method" id="selectedPaymentMethod" required>
        </div>
    `;
    
    container.innerHTML = selectorHTML;
}

// Create payment method accordion items
function createPaymentMethodAccordionItems(paymentMethods) {
    let itemsHTML = '';
    
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
    
    // Create accordion items for each grouped method
    let index = 0;
    Object.values(groupedMethods).forEach(group => {
        const methodValue = group.name;
        const description = getPaymentMethodDescription(group.name);
        const accordionId = `accordion-${index}`;
        
        itemsHTML += `
            <div class="accordion-item payment-method-card" data-method-value="${methodValue}">
                <h2 class="accordion-header" id="heading-${index}">
                    <button class="accordion-button collapsed payment-method-header" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#${accordionId}" 
                            aria-expanded="false" aria-controls="${accordionId}"
                            onclick="selectPaymentMethodFromAccordion('${methodValue}', this)">
                        <div class="d-flex align-items-center w-100">
                            <div class="payment-method-icon me-3">
                                ${group.icon}
                            </div>
                            <div class="payment-method-info flex-grow-1">
                                <div class="payment-method-name fw-bold">${group.name}</div>
                                <small class="text-muted">${description}</small>
                            </div>
                            <div class="payment-method-status me-3">
                                <span class="badge bg-success">Disponible</span>
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="${accordionId}" class="accordion-collapse collapse" 
                     aria-labelledby="heading-${index}" data-bs-parent="#paymentMethodAccordion">
                    <div class="accordion-body payment-method-details" id="details-${methodValue}">
                        <!-- Payment details will be loaded here -->
                    </div>
                </div>
            </div>
        `;
        index++;
    });
    
    return itemsHTML;
}

// Select payment method from accordion
function selectPaymentMethodFromAccordion(methodValue, element) {
    // Remove previous selection
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selection to clicked card
    const selectedCard = element.closest('.payment-method-card');
    if (selectedCard) {
        selectedCard.classList.add('selected');
    }
    
    // Update hidden input
    const hiddenInput = document.getElementById('selectedPaymentMethod');
    if (hiddenInput) {
        hiddenInput.value = methodValue;
    }
    
    // Load payment method details into accordion body
    const detailsContainer = document.getElementById(`details-${methodValue}`);
    if (detailsContainer) {
        loadPaymentMethodDetailsIntoAccordion(methodValue, detailsContainer);
    }
    
    // Update complete order button state
    updateCompleteOrderButtonState();
}

// Load payment method details into accordion
function loadPaymentMethodDetailsIntoAccordion(methodValue, container) {
    if (!methodValue || !container) return;
    
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
    
    let detailsHTML = '';
    const lowerMethodName = methodName.toLowerCase();
    
    // Check if it's Pagomovil method
    if (lowerMethodName.includes('pagomovil') || lowerMethodName.includes('pago móvil') || lowerMethodName.includes('pago movil')) {
        // Use the first active configuration for Pagomovil
        const activeConfig = methodConfigurations.find(config => config.config_id) || methodConfigurations[0];
        detailsHTML = createPagomovilFormInAccordion(
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
    
    container.innerHTML = detailsHTML;
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
            
            <!-- Company Info -->
            ${companyInfo ? createCompanyInfoHTML(companyInfo) : ''}
            
            <!-- Payment Info -->
            <div class="payment-info mb-3 p-2 bg-white rounded border" id="pagomovilPaymentInfo">
                ${bankName ? `<p class="mb-1"><strong>Banco:</strong> <span id="pagomovilBankName">${bankName}</span></p>` : ''}
                ${accountNumber ? `<p class="mb-1"><strong>Cuenta:</strong> <span id="pagomovilAccountNumber">${accountNumber}</span></p>` : ''}
                ${pagomovil ? `<p class="mb-1"><strong>Teléfono:</strong> <span id="pagomovilNumber">${pagomovil}</span></p>` : ''}
                ${holderName ? `<p class="mb-1"><strong>Titular:</strong> <span id="pagomovilHolderName">${holderName}</span></p>` : ''}
                ${notes ? `<p class="mb-1"><strong>Notas:</strong> <span id="pagomovilNotes">${notes}</span></p>` : ''}
            </div>
            
            <!-- Amount -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Monto a Pagar (VES)</label>
                    <div class="input-group">
                        <span class="input-group-text">Bs.</span>
                        <input type="number" class="form-control" id="pagomovilAmount" 
                               value="${amountInVes}" step="0.01" readonly>
                    </div>
                    <small class="text-muted">Tasa: $1 = Bs. ${exchangeRate}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Referencia <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="pagomovilReference" 
                           placeholder="Últimos 6 dígitos" maxlength="6" pattern="[0-9]{6}" required>
                    <small class="text-muted">Ingrese los últimos 6 dígitos de la referencia</small>
                </div>
            </div>
            
            <!-- Validate Button -->
            <div class="d-grid">
                <button type="button" class="btn btn-success" onclick="validatePagomovilPayment()">
                    <i class="fas fa-check-circle me-2"></i>Validar Pago
                </button>
            </div>
        </div>
        
        <script>
            // Store configurations globally for access
            window.pagomovilConfigurations = ${JSON.stringify(configurations)};
            
            // Function to update Pagomovil configuration
            function updatePagomovilConfig() {
                const select = document.getElementById('pagomovilConfigSelect');
                if (!select || !window.pagomovilConfigurations) return;
                
                const selectedIndex = parseInt(select.value);
                const config = window.pagomovilConfigurations[selectedIndex];
                
                if (config) {
                    // Update payment info display
                    const bankNameEl = document.getElementById('pagomovilBankName');
                    const accountNumberEl = document.getElementById('pagomovilAccountNumber');
                    const numberEl = document.getElementById('pagomovilNumber');
                    const holderNameEl = document.getElementById('pagomovilHolderName');
                    const notesEl = document.getElementById('pagomovilNotes');
                    
                    if (bankNameEl) bankNameEl.textContent = config.bank_name || '';
                    if (accountNumberEl) accountNumberEl.textContent = config.account_number || '';
                    if (numberEl) numberEl.textContent = config.pagomovil_number || '';
                    if (holderNameEl) holderNameEl.textContent = config.account_holder_name || '';
                    if (notesEl) notesEl.textContent = config.notes || '';
                }
            }
        </script>
    `;
}

// Create Pagomovil payment form for accordion
function createPagomovilFormInAccordion(bankName, accountNumber, pagomovil, holderName, notes, configurations = []) {
    const orderTotal = parseFloat(document.getElementById('checkoutTotal').textContent || 0);
    const amountInVes = (orderTotal * exchangeRate).toFixed(2);
    
    // Create options for multiple configurations if available
    let configurationOptions = '';
    if (configurations && configurations.length > 1) {
        configurationOptions = `
            <div class="mb-3">
                <label class="form-label">Seleccionar Cuenta</label>
                <select class="form-select" id="pagomovilConfigSelectAccordion" onchange="updatePagomovilConfigAccordion()">
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
        <div class="pagomovil-form-accordion">
            ${configurationOptions}
            
            <!-- Company Info -->
            ${companyInfo ? createCompanyInfoHTML(companyInfo) : ''}
            
            <!-- Payment Info -->
            <div class="payment-info mb-3 p-2 bg-white rounded border" id="pagomovilPaymentInfoAccordion">
                ${bankName ? `<p class="mb-1"><strong>Banco:</strong> <span id="pagomovilBankNameAccordion">${bankName}</span></p>` : ''}
                ${accountNumber ? `<p class="mb-1"><strong>Cuenta:</strong> <span id="pagomovilAccountNumberAccordion">${accountNumber}</span></p>` : ''}
                ${pagomovil ? `<p class="mb-1"><strong>Teléfono:</strong> <span id="pagomovilNumberAccordion">${pagomovil}</span></p>` : ''}
                ${holderName ? `<p class="mb-1"><strong>Titular:</strong> <span id="pagomovilHolderNameAccordion">${holderName}</span></p>` : ''}
                ${notes ? `<p class="mb-1"><strong>Notas:</strong> <span id="pagomovilNotesAccordion">${notes}</span></p>` : ''}
            </div>
            
            <!-- Amount -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Monto a Pagar (VES)</label>
                    <div class="input-group">
                        <span class="input-group-text">Bs.</span>
                        <input type="number" class="form-control" id="pagomovilAmountAccordion" 
                               value="${amountInVes}" step="0.01" readonly>
                    </div>
                    <small class="text-muted">Tasa: $1 = Bs. ${exchangeRate}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Referencia <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="pagomovilReferenceAccordion" 
                           placeholder="Últimos 6 dígitos" maxlength="6" pattern="[0-9]{6}" required>
                    <small class="text-muted">Ingrese los últimos 6 dígitos de la referencia</small>
                </div>
            </div>
            
            <!-- Validate Button -->
            <div class="d-grid">
                <button type="button" class="btn btn-success" onclick="validatePagomovilPaymentAccordion()">
                    <i class="fas fa-check-circle me-2"></i>Validar Pago
                </button>
            </div>
        </div>
        
        <script>
            // Store configurations globally for accordion access
            window.pagomovilConfigurationsAccordion = ${JSON.stringify(configurations)};
            
            // Function to update Pagomovil configuration in accordion
            function updatePagomovilConfigAccordion() {
                const select = document.getElementById('pagomovilConfigSelectAccordion');
                if (!select || !window.pagomovilConfigurationsAccordion) return;
                
                const selectedIndex = parseInt(select.value);
                const config = window.pagomovilConfigurationsAccordion[selectedIndex];
                
                if (config) {
                    // Update payment info display
                    const bankNameEl = document.getElementById('pagomovilBankNameAccordion');
                    const accountNumberEl = document.getElementById('pagomovilAccountNumberAccordion');
                    const numberEl = document.getElementById('pagomovilNumberAccordion');
                    const holderNameEl = document.getElementById('pagomovilHolderNameAccordion');
                    const notesEl = document.getElementById('pagomovilNotesAccordion');
                    
                    if (bankNameEl) bankNameEl.textContent = config.bank_name || '';
                    if (accountNumberEl) accountNumberEl.textContent = config.account_number || '';
                    if (numberEl) numberEl.textContent = config.pagomovil_number || '';
                    if (holderNameEl) holderNameEl.textContent = config.account_holder_name || '';
                    if (notesEl) notesEl.textContent = config.notes || '';
                }
            }
            
            // Function to validate Pagomovil payment in accordion
            function validatePagomovilPaymentAccordion() {
                const reference = document.getElementById('pagomovilReferenceAccordion').value.trim();
                const amount = document.getElementById('pagomovilAmountAccordion').value;
                
                if (!reference) {
                    showToast('Por favor ingrese la referencia del pago', 'error');
                    return;
                }
                
                if (reference.length !== 6 || !/^\\d{6}$/.test(reference)) {
                    showToast('La referencia debe tener exactamente 6 dígitos', 'error');
                    return;
                }
                
                // Show loading state
                const validateButton = document.querySelector('button[onclick="validatePagomovilPaymentAccordion()"]');
                const originalText = validateButton.innerHTML;
                validateButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Validando...';
                validateButton.disabled = true;
                
                // Call the main validation function
                validatePaymentWithAPI(parseFloat(amount), reference).then(validationResult => {
                    if (validationResult && validationResult.success) {
                        // Store payment data for order completion
                        window.paymentData = {
                            type: 'pagomovil',
                            reference: reference,
                            amount: amount,
                            validated: true,
                            validation_data: validationResult.data
                        };
                        
                        // Show validation details
                        showValidationDetailsAccordion(validationResult.data);
                        
                        showToast('¡Pago móvil validado exitosamente!', 'success');
                        
                        // Update complete order button state
                        updateCompleteOrderButtonState();
                    } else {
                        showToast('No se pudo validar el pago. Verifique la referencia e intente nuevamente.', 'error');
                    }
                }).catch(error => {
                    console.error('Error validating payment:', error);
                    showToast('Error al validar el pago. Intente nuevamente.', 'error');
                }).finally(() => {
                    // Restore button state
                    validateButton.innerHTML = originalText;
                    validateButton.disabled = false;
                });
            }
            
            // Show validation details in accordion
            function showValidationDetailsAccordion(validationData) {
                if (!validationData) return;
                
                // Find the pagomovil form container
                const pagomovilForm = document.querySelector('.pagomovil-form-accordion');
                if (!pagomovilForm) return;
                
                // Remove existing validation details
                const existingDetails = pagomovilForm.querySelector('.validation-details');
                if (existingDetails) {
                    existingDetails.remove();
                }
                
                // Create validation details HTML
                const validationHTML = \`
                    <div class="validation-details mt-3 p-3 bg-success bg-opacity-10 border border-success rounded">
                        <h6 class="text-success mb-2">
                            <i class="fas fa-check-circle"></i> Pago Validado Exitosamente
                        </h6>
                        <div class="row">
                            \${validationData.bank_origin_name ? \`
                            <div class="col-md-6 mb-2">
                                <small class="text-muted">Banco Origen:</small><br>
                                <strong>\${validationData.bank_origin_name}</strong>
                            </div>
                            \` : ''}
                            \${validationData.bank_destiny_name ? \`
                            <div class="col-md-6 mb-2">
                                <small class="text-muted">Banco Destino:</small><br>
                                <strong>\${validationData.bank_destiny_name}</strong>
                            </div>
                            \` : ''}
                            \${validationData.method_name ? \`
                            <div class="col-md-6 mb-2">
                                <small class="text-muted">Método:</small><br>
                                <strong>\${validationData.method_name}</strong>
                            </div>
                            \` : ''}
                            \${validationData.amount_usd ? \`
                            <div class="col-md-6 mb-2">
                                <small class="text-muted">Monto USD:</small><br>
                                <strong>$\${parseFloat(validationData.amount_usd).toFixed(2)}</strong>
                            </div>
                            \` : ''}
                            <div class="col-12 mb-2">
                                <small class="text-muted">Referencia:</small><br>
                                <strong>\${validationData.reference}</strong>
                            </div>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> Validado: \${new Date(validationData.validated_at).toLocaleString()}
                        </small>
                    </div>
                \`;
                
                // Insert validation details after the validate button
                const validateButton = pagomovilForm.querySelector('button[onclick="validatePagomovilPaymentAccordion()"]');
                if (validateButton && validateButton.parentNode) {
                    validateButton.parentNode.insertAdjacentHTML('afterend', validationHTML);
                }
            }
        </script>
    `;
}

// Create Efectivo payment form (USD)
function createEfectivoForm() {
    const orderTotal = parseFloat(document.getElementById('checkoutTotal').textContent || 0);
    
    return `
        <div class="payment-form-card efectivo-form">
            <div class="form-header">
                <h6><i class="fas fa-money-bill-wave me-2"></i>Pago en Efectivo (USD)</h6>
                <span class="badge bg-success">Dólares Americanos</span>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Total del Pedido</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" value="${orderTotal.toFixed(2)}" readonly>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Monto Recibido <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="efectivoAmount" 
                               step="0.01" min="${orderTotal}" onchange="calculateChange()" required>
                    </div>
                </div>
            </div>
            
            <!-- Change calculation -->
            <div id="changeSection" class="mb-3" style="display: none;">
                <div class="alert alert-info">
                    <h6><i class="fas fa-exchange-alt me-2"></i>Vuelto</h6>
                    <p class="mb-2">Vuelto a entregar: $<span id="changeAmount">0.00</span></p>
                    <div id="pagomovilChangeSection" style="display: none;">
                        <p class="mb-2 text-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            El vuelto será enviado por Pago Móvil
                        </p>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Teléfono <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="changePhone" 
                                       placeholder="0414-1234567" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cédula <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="changeCedula" 
                                       placeholder="V-12345678" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Banco <span class="text-danger">*</span></label>
                                <select class="form-select" id="changeBank" required>
                                    <option value="">Seleccione banco</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Create Efectivo Bolívares payment form
function createEfectivoBolivaresForm() {
    const orderTotal = parseFloat(document.getElementById('checkoutTotal').textContent || 0);
    const amountInVes = (orderTotal * exchangeRate).toFixed(2);
    
    return `
        <div class="payment-form-card efectivo-bolivares-form">
            <div class="form-header">
                <h6><i class="fas fa-money-bill-wave me-2"></i>Pago en Efectivo (Bolívares)</h6>
                <span class="badge bg-warning text-dark">Bolívares Venezolanos</span>
            </div>
            
            <div class="currency-conversion mb-3">
                <div class="conversion-info">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="conversion-item">
                                <label class="form-label">Total en USD</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" value="${orderTotal.toFixed(2)}" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="conversion-item">
                                <label class="form-label">Tasa de Cambio</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" value="${exchangeRate}" readonly>
                                    <span class="input-group-text">Bs/$</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="conversion-item">
                                <label class="form-label">Total en Bolívares</label>
                                <div class="input-group">
                                    <span class="input-group-text">Bs.</span>
                                    <input type="number" class="form-control" id="bolivaresTotal" value="${amountInVes}" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Monto Recibido (Bolívares) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Bs.</span>
                        <input type="number" class="form-control" id="bolivaresAmount" 
                               step="0.01" min="${amountInVes}" onchange="calculateBolivaresChange()" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Vuelto en Bolívares</label>
                    <div class="input-group">
                        <span class="input-group-text">Bs.</span>
                        <input type="number" class="form-control" id="bolivaresChange" value="0.00" readonly>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Información:</strong> El pago se realizará en bolívares venezolanos según la tasa de cambio actual.
                El vuelto se entregará en la misma moneda.
            </div>
        </div>
    `;
}

// Validate Pagomovil payment with external API
async function validatePagomovilPayment() {
    const reference = document.getElementById('pagomovilReference').value.trim();
    const amount = document.getElementById('pagomovilAmount').value;
    
    if (!reference) {
        showToast('Por favor ingrese la referencia del pago', 'error');
        return;
    }
    
    if (reference.length !== 6 || !/^\d{6}$/.test(reference)) {
        showToast('La referencia debe tener exactamente 6 dígitos', 'error');
        return;
    }
    
    // Show loading state
    const validateButton = document.querySelector('button[onclick="validatePagomovilPayment()"]');
    const originalText = validateButton.innerHTML;
    validateButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Validando...';
    validateButton.disabled = true;
    
    try {
        // Call validation API
        const validationResult = await validatePaymentWithAPI(parseFloat(amount), reference);
        
        if (validationResult && validationResult.success) {
            // Store payment data for order completion
            window.paymentData = {
                type: 'pagomovil',
                reference: reference,
                amount: amount,
                validated: true,
                validation_data: validationResult.data
            };
            
            // Show validation details
            showValidationDetails(validationResult.data);
            
            showToast('¡Pago móvil validado exitosamente!', 'success');
            
            // Enable complete order button
            const completeButton = document.querySelector('#checkoutModal .btn-success');
            if (completeButton) {
                completeButton.disabled = false;
                completeButton.innerHTML = '<i class="fas fa-check"></i> Completar Pedido';
            }
        } else {
            showToast('No se pudo validar el pago. Verifique la referencia e intente nuevamente.', 'error');
        }
    } catch (error) {
        console.error('Error validating payment:', error);
        showToast('Error al validar el pago. Intente nuevamente.', 'error');
    } finally {
        // Restore button state
        validateButton.innerHTML = originalText;
        validateButton.disabled = false;
    }
}

// Function to validate payment with external API
async function validatePaymentWithAPI(amount, reference, additionalData = {}) {
    try {
        const paymentData = {
            amount: parseFloat(amount),
            reference: reference.toString(),
            mobile: additionalData.mobile || "",
            sender: additionalData.sender || "",
            method: additionalData.method || ""
        };
        
        const response = await fetch('api/validate_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(paymentData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Store validation data globally
            window.paymentData = {
                ...window.paymentData,
                validation: result.data,
                validated: true,
                validated_at: new Date().toISOString()
            };
            
            return result;
        } else {
            console.error('Payment validation failed:', result.message);
            return null;
        }
    } catch (error) {
        console.error('Error validating payment:', error);
        return null;
    }
}

// Show validation details in the form
function showValidationDetails(validationData) {
    if (!validationData) return;
    
    // Find the pagomovil form container
    const pagomovilForm = document.querySelector('.pagomovil-form');
    if (!pagomovilForm) return;
    
    // Remove existing validation details
    const existingDetails = pagomovilForm.querySelector('.validation-details');
    if (existingDetails) {
        existingDetails.remove();
    }
    
    // Create validation details HTML
    const validationHTML = `
        <div class="validation-details mt-3 p-3 bg-success bg-opacity-10 border border-success rounded">
            <h6 class="text-success mb-2">
                <i class="fas fa-check-circle"></i> Pago Validado Exitosamente
            </h6>
            <div class="row">
                ${validationData.bank_origin_name ? `
                <div class="col-md-6 mb-2">
                    <small class="text-muted">Banco Origen:</small><br>
                    <strong>${validationData.bank_origin_name}</strong>
                </div>
                ` : ''}
                ${validationData.bank_destiny_name ? `
                <div class="col-md-6 mb-2">
                    <small class="text-muted">Banco Destino:</small><br>
                    <strong>${validationData.bank_destiny_name}</strong>
                </div>
                ` : ''}
                ${validationData.method_name ? `
                <div class="col-md-6 mb-2">
                    <small class="text-muted">Método:</small><br>
                    <strong>${validationData.method_name}</strong>
                </div>
                ` : ''}
                ${validationData.amount_usd ? `
                <div class="col-md-6 mb-2">
                    <small class="text-muted">Monto USD:</small><br>
                    <strong>$${parseFloat(validationData.amount_usd).toFixed(2)}</strong>
                </div>
                ` : ''}
                <div class="col-12 mb-2">
                    <small class="text-muted">Referencia:</small><br>
                    <strong>${validationData.reference}</strong>
                </div>
            </div>
            <small class="text-muted">
                <i class="fas fa-clock"></i> Validado: ${new Date(validationData.validated_at).toLocaleString()}
            </small>
        </div>
    `;
    
    // Insert validation details after the validate button
    const validateButton = pagomovilForm.querySelector('button[onclick="validatePagomovilPayment()"]');
    if (validateButton && validateButton.parentNode) {
        validateButton.parentNode.insertAdjacentHTML('afterend', validationHTML);
    }
}

// Calculate change for cash payments
function calculateChange() {
    const orderTotal = parseFloat(document.getElementById('checkoutTotal').textContent || 0);
    const amountReceived = parseFloat(document.getElementById('efectivoAmount').value || 0);
    const change = amountReceived - orderTotal;
    
    const changeSection = document.getElementById('changeSection');
    const changeAmount = document.getElementById('changeAmount');
    const pagomovilChangeSection = document.getElementById('pagomovilChangeSection');
    
    if (change > 0) {
        changeSection.style.display = 'block';
        changeAmount.textContent = change.toFixed(2);
        
        // If change is less than $5, offer Pagomovil return
        if (change < 5) {
            pagomovilChangeSection.style.display = 'block';
            loadBanksIntoSelect();
        } else {
            pagomovilChangeSection.style.display = 'none';
        }
        
        // Store payment data
        window.paymentData = {
            type: 'efectivo',
            amount_received: amountReceived,
            change: change,
            change_method: change < 5 ? 'pagomovil' : 'cash'
        };
    } else {
        changeSection.style.display = 'none';
        window.paymentData = {
            type: 'efectivo',
            amount_received: amountReceived,
            change: 0
        };
    }
}

// Calculate change for bolivares payments
function calculateBolivaresChange() {
    const bolivaresTotal = parseFloat(document.getElementById('bolivaresTotal').value || 0);
    const bolivaresReceived = parseFloat(document.getElementById('bolivaresAmount').value || 0);
    const change = bolivaresReceived - bolivaresTotal;
    
    const changeInput = document.getElementById('bolivaresChange');
    if (changeInput) {
        changeInput.value = change > 0 ? change.toFixed(2) : '0.00';
    }
    
    // Store payment data
    window.paymentData = {
        type: 'efectivo_bolivares',
        amount_received_ves: bolivaresReceived,
        amount_total_ves: bolivaresTotal,
        exchange_rate: exchangeRate,
        change_ves: change > 0 ? change : 0
    };
}

// Load banks into select dropdown
function loadBanksIntoSelect() {
    const bankSelect = document.getElementById('changeBank');
    if (!bankSelect || availableBanks.length === 0) return;
    
    bankSelect.innerHTML = '<option value="">Seleccione banco</option>';
    
    availableBanks.forEach(bank => {
        const option = document.createElement('option');
        option.value = bank.id;
        option.textContent = `${bank.name} (${bank.code})`;
        bankSelect.appendChild(option);
    });
}

// Load categories for filter
function loadCategories() {
    console.log('Loading categories from products:', products.length);
    console.log('Products data:', products);
    
    const categories = [...new Set(products.map(p => {
        console.log('Product category data:', p.category_name, p.category);
        return p.category_name || p.category;
    }).filter(Boolean))];
    
    const filterContainer = document.querySelector('.category-filters');
    
    if (!filterContainer) {
        console.error('Category filter container not found');
        return;
    }
    
    // Clear existing category buttons (except "Todos")
    const existingButtons = filterContainer.querySelectorAll('.filter-btn:not([data-category="all"])');
    existingButtons.forEach(btn => btn.remove());
    
    console.log('Available categories:', categories); // Debug log
    
    categories.forEach(category => {
        console.log('Creating button for category:', category);
        const button = document.createElement('button');
        button.className = 'filter-btn';
        button.setAttribute('data-category', category);
        button.innerHTML = `<i class="fas fa-tag"></i> ${category.charAt(0).toUpperCase() + category.slice(1)}`;
        button.onclick = () => filterProducts(category);
        filterContainer.appendChild(button);
    });
    
    console.log('Categories loaded successfully. Total buttons:', filterContainer.querySelectorAll('.filter-btn').length);
}

// Filter products by category
function filterProducts(category) {
    // Update active button
    const buttons = document.querySelectorAll('.filter-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    
    const activeBtn = document.querySelector(`[data-category="${category}"]`);
    if (activeBtn) activeBtn.classList.add('active');
    
    if (category === 'all') {
        displayProducts(products);
    } else {
        const filtered = products.filter(product => product.category_name === category);
        displayProducts(filtered);
    }
}

// Display products
function displayProducts(productsToShow) {
    const container = document.getElementById('productsContainer');
    if (!container) return;
    
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
    
    console.log('Displaying products:', productsToShow.length);
    
    productsToShow.forEach((product, index) => {
        const productCard = createProductCard(product, index);
        container.appendChild(productCard);
    });
    
    // Add stagger animation
    setTimeout(() => {
        container.querySelectorAll('.product-card').forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
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
    card.addEventListener('click', (e) => {
        if (!e.target.closest('button')) {
            openProductModal(product.id);
        }
    });
    
    return card;
}

// Open product modal
async function openProductModal(productId) {
    try {
        productId = parseInt(productId);
        
        const response = await fetch(`api/products.php?id=${productId}`);
        const data = await response.json();
        
        if (!data.success || !data.product) {
            showToast('Producto no encontrado', 'error');
            return;
        }
        
        currentProduct = data.product;
        
        // Populate modal
        document.getElementById('productModalTitle').textContent = currentProduct.name;
        document.getElementById('productModalName').textContent = currentProduct.name;
        document.getElementById('productModalDescription').textContent = currentProduct.description || 'Sin descripción';
        
        // Load main image
        const mainImage = document.getElementById('productModalMainImage');
        const imageUrl = currentProduct.main_image || currentProduct.image || 'https://via.placeholder.com/400x300?text=' + encodeURIComponent(currentProduct.name);
        mainImage.src = imageUrl;
        mainImage.onerror = function() {
            this.src = 'https://via.placeholder.com/400x300?text=' + encodeURIComponent(currentProduct.name);
        };
        
        // Load image gallery
        loadProductImageGallery(currentProduct.images);
        
        // Load sizes
        loadProductSizes(currentProduct.sizes);
        
        // Load additionals
        loadProductAdditionalsFromData(currentProduct.additionals);
        
        // Reset form
        document.getElementById('productQuantity').value = 1;
        document.getElementById('productNotes').value = '';
        
        // Set initial price and update total
        setInitialPrice();
        updateProductTotalPrice();
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('productModal'));
        modal.show();
        
    } catch (error) {
        console.error('Error opening product modal:', error);
        showToast('Error al abrir los detalles del producto', 'error');
    }
}

// Load product image gallery
function loadProductImageGallery(images) {
    const thumbnailsContainer = document.getElementById('productImageThumbnails');
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
                const mainImage = document.getElementById('productModalMainImage');
                if (mainImage) {
                    mainImage.src = image.image_path;
                }
            };
            thumbnailsContainer.appendChild(thumbnail);
        });
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
            sizeItem.className = 'size-option';
            sizeItem.innerHTML = `
                <input class="form-check-input size-radio" type="radio" 
                       name="product_size" value="${size.id}" id="size_${size.id}"
                       data-price="${size.price}" data-name="${size.name}"
                       ${index === 0 ? 'checked' : ''} onchange="updateProductTotalPrice()">
                <label class="size-label" for="size_${size.id}">
                    <div class="size-info">
                        <strong>${size.name}</strong>
                        ${size.description ? `<small>${size.description}</small>` : ''}
                    </div>
                    <span class="size-price">$${parseFloat(size.price).toFixed(2)}</span>
                </label>
            `;
            sizesContainer.appendChild(sizeItem);
        });
    } else {
        sizesSection.style.display = 'none';
    }
}

// Load product additionals
function loadProductAdditionalsFromData(additionals) {
    const container = document.getElementById('productAdditionalsContainer');
    const additionalsSection = document.getElementById('productAdditionals');
    
    if (!container || !additionalsSection) return;
    
    container.innerHTML = '';
    
    if (additionals && additionals.length > 0) {
        additionalsSection.style.display = 'block';
        
        additionals.forEach(additional => {
            const additionalItem = document.createElement('div');
            additionalItem.className = 'additional-option';
            additionalItem.innerHTML = `
                <input class="form-check-input additional-checkbox" type="checkbox" 
                       value="${additional.id}" id="additional_${additional.id}"
                       data-price="${additional.price}" data-name="${additional.name}"
                       ${additional.is_default ? 'checked' : ''} onchange="updateProductTotalPrice()">
                <label class="additional-label" for="additional_${additional.id}">
                    <div class="additional-info">
                        <strong>${additional.name}</strong>
                        ${additional.description ? `<small>${additional.description}</small>` : ''}
                        ${additional.category_name ? `<span class="badge bg-secondary">${additional.category_name}</span>` : ''}
                    </div>
                    <span class="additional-price">+$${parseFloat(additional.price).toFixed(2)}</span>
                </label>
            `;
            container.appendChild(additionalItem);
        });
    } else {
        additionalsSection.style.display = 'none';
    }
}

// Set initial price
function setInitialPrice() {
    const modalPrice = document.getElementById('productModalPrice');
    if (!modalPrice || !currentProduct) return;
    
    const selectedSize = document.querySelector('input[name="product_size"]:checked');
    if (selectedSize) {
        modalPrice.textContent = parseFloat(selectedSize.dataset.price).toFixed(2);
    } else {
        modalPrice.textContent = parseFloat(currentProduct.price || currentProduct.base_price || 0).toFixed(2);
    }
}

// Change quantity
function changeQuantity(change) {
    const quantityInput = document.getElementById('productQuantity');
    let currentQuantity = parseInt(quantityInput.value);
    let newQuantity = currentQuantity + change;
    
    if (newQuantity < 1) newQuantity = 1;
    if (newQuantity > 10) newQuantity = 10;
    
    quantityInput.value = newQuantity;
    updateProductTotalPrice();
}

// Update product total price
function updateProductTotalPrice() {
    if (!currentProduct) return;
    
    const quantity = parseInt(document.getElementById('productQuantity').value);
    let basePrice = 0;
    
    const selectedSize = document.querySelector('input[name="product_size"]:checked');
    if (selectedSize) {
        basePrice = parseFloat(selectedSize.dataset.price);
        const modalPrice = document.getElementById('productModalPrice');
        if (modalPrice) {
            modalPrice.textContent = basePrice.toFixed(2);
        }
    } else {
        basePrice = parseFloat(currentProduct.price || currentProduct.base_price || 0);
    }
    
    let totalPrice = basePrice * quantity;
    
    const selectedAdditionals = document.querySelectorAll('.additional-checkbox:checked');
    selectedAdditionals.forEach(checkbox => {
        totalPrice += parseFloat(checkbox.dataset.price) * quantity;
    });
    
    const totalPriceElement = document.getElementById('productTotalPrice');
    const totalPriceFooterElement = document.getElementById('productTotalPriceFooter');
    
    if (totalPriceElement) {
        totalPriceElement.textContent = totalPrice.toFixed(2);
    }
    if (totalPriceFooterElement) {
        totalPriceFooterElement.textContent = totalPrice.toFixed(2);
    }
}

// Add product to cart
function addProductToCart() {
    if (!currentProduct) return;
    
    const quantity = parseInt(document.getElementById('productQuantity').value);
    const notes = document.getElementById('productNotes').value.trim();
    
    let selectedSize = null;
    const sizeRadio = document.querySelector('input[name="product_size"]:checked');
    if (sizeRadio) {
        selectedSize = {
            id: sizeRadio.value,
            name: sizeRadio.dataset.name,
            price: parseFloat(sizeRadio.dataset.price)
        };
    }
    
    const selectedAdditionals = [];
    const additionalCheckboxes = document.querySelectorAll('.additional-checkbox:checked');
    additionalCheckboxes.forEach(checkbox => {
        selectedAdditionals.push({
            id: checkbox.value,
            name: checkbox.dataset.name,
            price: parseFloat(checkbox.dataset.price)
        });
    });
    
    let basePrice = selectedSize ? selectedSize.price : parseFloat(currentProduct.price || currentProduct.base_price || 0);
    let itemPrice = basePrice;
    selectedAdditionals.forEach(additional => {
        itemPrice += additional.price;
    });
    
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
    
    const itemKey = `${cartItem.id}_${selectedSize ? selectedSize.id : 'no_size'}_${JSON.stringify(selectedAdditionals)}_${notes}`;
    
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
    
    let displayName = currentProduct.name;
    if (selectedSize) {
        displayName += ` (${selectedSize.name})`;
    }
    
    showToast(`${quantity} x ${displayName} agregado al carrito`, 'success');
    
    bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
}

// Update cart display
function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    const modalCartItems = document.getElementById('modalCartItems');
    const cartCount = document.getElementById('cartCount');
    const mobileCartCount = document.getElementById('mobileCartCount');
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
    
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    if (cartCount) cartCount.textContent = totalItems;
    if (mobileCartCount) mobileCartCount.textContent = totalItems;
    if (cartTotal) cartTotal.textContent = total.toFixed(2);
    if (modalCartTotal) modalCartTotal.textContent = total.toFixed(2);
    
    // Guardar carrito en localStorage
    saveCartToStorage();
}

// Update cart quantity
function updateCartQuantity(index, change) {
    if (index < 0 || index >= cart.length) return;
    
    cart[index].quantity += change;
    
    if (cart[index].quantity <= 0) {
        removeFromCartByIndex(index);
    } else {
        updateCartDisplay();
    }
}

// Remove from cart by index
function removeFromCartByIndex(index) {
    if (index < 0 || index >= cart.length) return;
    
    const removedItem = cart.splice(index, 1)[0];
    updateCartDisplay();
    showToast(`${removedItem.name} eliminado del carrito`, 'info');
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

// Toggle order type
function toggleOrderType() {
    const deliverySection = document.getElementById('deliverySection');
    const pickupSection = document.getElementById('pickupSection');
    const deliveryFeeRow = document.getElementById('deliveryFeeRow');
    const isDelivery = document.getElementById('delivery').checked;
    
    const deliveryRequiredFields = document.querySelectorAll('.delivery-required');
    
    if (isDelivery) {
        deliverySection.style.display = 'block';
        pickupSection.style.display = 'none';
        deliveryFeeRow.style.display = 'flex';
        
        deliveryRequiredFields.forEach(field => {
            field.setAttribute('required', 'required');
        });
    } else {
        deliverySection.style.display = 'none';
        pickupSection.style.display = 'block';
        deliveryFeeRow.style.display = 'none';
        loadStoreLocations();
        
        deliveryRequiredFields.forEach(field => {
            field.removeAttribute('required');
        });
    }
    
    updateCheckoutTotal();
}

// Load store locations
function loadStoreLocations() {
    const container = document.getElementById('storeLocations');
    
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

// Proceed to checkout
function proceedToCheckout() {
    if (cart.length === 0) {
        showToast('Tu carrito está vacío', 'error');
        return;
    }
    
    // Cargar y actualizar métodos de pago antes de mostrar el modal
    loadPaymentMethods().then(() => {
        setTimeout(() => {
            initializeMap();
        }, 500);
        
        updateCheckoutSummary();
        
        const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
        modal.show();
    }).catch(error => {
        console.error('Error loading payment methods:', error);
        // Continuar con el modal incluso si hay error en la carga de métodos de pago
        setTimeout(() => {
            initializeMap();
        }, 500);
        
        updateCheckoutSummary();
        
        const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
        modal.show();
    });
}

// Update checkout summary
function updateCheckoutSummary() {
    const container = document.getElementById('checkoutCartItems');
    const subtotalElement = document.getElementById('checkoutSubtotal');
    
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

// Update checkout total
function updateCheckoutTotal() {
    const subtotal = parseFloat(document.getElementById('checkoutSubtotal').textContent || 0);
    const isDelivery = document.getElementById('delivery') && document.getElementById('delivery').checked;
    const deliveryFee = isDelivery ? 3.00 : 0;
    const total = subtotal + deliveryFee;
    
    document.getElementById('checkoutTotal').textContent = total.toFixed(2);
}

// Initialize map
function initializeMap() {
    if (map) return;
    
    const mapContainer = document.getElementById('map');
    if (!mapContainer) return;
    
    map = L.map('map').setView([10.4806, -66.9036], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
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

// Lookup customer
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

// Populate customer data
function populateCustomerData(customer) {
    document.getElementById('firstName').value = customer.first_name || '';
    document.getElementById('lastName').value = customer.last_name || '';
    document.getElementById('customerEmail').value = customer.email || '';
    
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
    const addressRadios = document.querySelectorAll('input[name="selected_address"]');
    addressRadios.forEach(radio => radio.checked = false);
}

// Complete order
async function completeOrder() {
    const form = document.getElementById('checkoutForm');
    const formData = new FormData(form);
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const isDelivery = document.getElementById('delivery').checked;
    
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
    
    const whatsappPhone = formData.get('phone_whatsapp');
    if (whatsappPhone && whatsappPhone.trim()) {
        orderData.customer.phones.push({
            phone_number: whatsappPhone.trim(),
            phone_type: 'mobile',
            is_primary: false,
            is_whatsapp: true
        });
    }
    
    if (isDelivery) {
        const selectedAddressId = document.querySelector('input[name="selected_address"]:checked')?.value;
        
        if (selectedAddressId) {
            orderData.address_id = selectedAddressId;
        } else {
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
            
            // Limpiar carrito y storage
            cart = [];
            clearCartStorage();
            updateCartDisplay();
            
            bootstrap.Modal.getInstance(document.getElementById('checkoutModal')).hide();
            
            form.reset();
            currentCustomer = null;
            
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
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Search functionality
function performSearch() {
    const searchTerm = document.getElementById('productSearch').value.trim().toLowerCase();
    const minPrice = parseFloat(document.getElementById('minPrice').value) || 0;
    const maxPrice = parseFloat(document.getElementById('maxPrice').value) || Infinity;
    
    if (!searchTerm && minPrice === 0 && maxPrice === Infinity) {
        displayProducts(products);
        return;
    }
    
    const filtered = products.filter(product => {
        const matchesSearch = !searchTerm || 
            product.name.toLowerCase().includes(searchTerm) ||
            (product.description && product.description.toLowerCase().includes(searchTerm)) ||
            (product.category_name && product.category_name.toLowerCase().includes(searchTerm));
        
        let productPrice = parseFloat(product.price || product.base_price || 0);
        if (product.sizes && product.sizes.length > 0) {
            productPrice = Math.min(...product.sizes.map(s => parseFloat(s.price)));
        }
        
        const matchesPrice = productPrice >= minPrice && productPrice <= maxPrice;
        
        return matchesSearch && matchesPrice;
    });
    
    displayProducts(filtered);
    
    // Update active filters
    updateActiveFilters(searchTerm, minPrice, maxPrice);
}

// Clear search
function clearSearch() {
    document.getElementById('productSearch').value = '';
    document.getElementById('minPrice').value = '';
    document.getElementById('maxPrice').value = '';
    document.getElementById('activeFilters').innerHTML = '';
    
    displayProducts(products);
    
    // Reset category filter
    const buttons = document.querySelectorAll('.filter-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    document.querySelector('[data-category="all"]').classList.add('active');
}

// Update active filters
function updateActiveFilters(searchTerm, minPrice, maxPrice) {
    const container = document.getElementById('activeFilters');
    let filtersHtml = '';
    
    if (searchTerm) {
        filtersHtml += `<span class="filter-tag">Búsqueda: "${searchTerm}" <i class="fas fa-times" onclick="clearSearchTerm()"></i></span>`;
    }
    
    if (minPrice > 0 || maxPrice < Infinity) {
        const priceText = minPrice > 0 && maxPrice < Infinity ? 
            `$${minPrice} - $${maxPrice}` : 
            minPrice > 0 ? `Desde $${minPrice}` : `Hasta $${maxPrice}`;
        filtersHtml += `<span class="filter-tag">Precio: ${priceText} <i class="fas fa-times" onclick="clearPriceFilter()"></i></span>`;
    }
    
    container.innerHTML = filtersHtml;
}

// Clear search term
function clearSearchTerm() {
    document.getElementById('productSearch').value = '';
    performSearch();
}

// Clear price filter
function clearPriceFilter() {
    document.getElementById('minPrice').value = '';
    document.getElementById('maxPrice').value = '';
    performSearch();
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
    
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    const toastElement = document.createElement('div');
    toastElement.innerHTML = toastHTML;
    toastContainer.appendChild(toastElement.firstElementChild);
    
    const toast = new bootstrap.Toast(toastContainer.lastElementChild);
    toast.show();
    
    setTimeout(() => {
        toastElement.remove();
    }, 3000);
}

// ===== PROMOTIONAL BANNERS FUNCTIONALITY =====

// Go to specific product (for banner clicks)
function goToProduct(productId) {
    console.log('Navigating to product:', productId);
    // Find the product and open its modal
    const product = products.find(p => p.id == productId);
    if (product) {
        openProductModal(productId);
    } else {
        // If product not found, show a message and scroll to products
        showToast('Producto destacado - ¡Explora nuestros productos!', 'info');
        document.getElementById('productsContainer').scrollIntoView({ behavior: 'smooth' });
    }
}

// Show special offers
function showSpecialOffers() {
    console.log('Showing special offers');
    // Filter products with discounts or special prices
    const specialProducts = products.filter(product => {
        // You can add logic here to identify special offers
        // For now, we'll show products with lower prices
        const price = parseFloat(product.price || product.base_price || 0);
        return price > 0 && price < 15; // Example: products under $15
    });
    
    if (specialProducts.length > 0) {
        displayProducts(specialProducts);
        showToast(`¡${specialProducts.length} ofertas especiales encontradas!`, 'success');
        
        // Update active filter button
        const buttons = document.querySelectorAll('.filter-btn');
        buttons.forEach(btn => btn.classList.remove('active'));
        
        // Scroll to products
        document.getElementById('productsContainer').scrollIntoView({ behavior: 'smooth' });
    } else {
        showToast('¡Próximamente tendremos ofertas especiales!', 'info');
    }
}

// Show popular items
function showPopularItems() {
    console.log('Showing popular items');
    // For now, show first few products as "popular"
    const popularProducts = products.slice(0, 6);
    
    if (popularProducts.length > 0) {
        displayProducts(popularProducts);
        showToast(`¡${popularProducts.length} productos populares!`, 'success');
        
        // Update active filter button
        const buttons = document.querySelectorAll('.filter-btn');
        buttons.forEach(btn => btn.classList.remove('active'));
        
        // Scroll to products
        document.getElementById('productsContainer').scrollIntoView({ behavior: 'smooth' });
    } else {
        showToast('Cargando productos populares...', 'info');
    }
}

// ===== ENHANCED SEARCH FUNCTIONALITY =====

// Clear search input and show/hide clear button
function clearSearchInput() {
    const searchInput = document.getElementById('productSearch');
    const clearButton = document.querySelector('.search-clear');
    
    if (searchInput) {
        searchInput.value = '';
        if (clearButton) {
            clearButton.style.display = 'none';
        }
        performSearch();
    }
}

// Show/hide clear button based on input content
function toggleClearButton() {
    const searchInput = document.getElementById('productSearch');
    const clearButton = document.querySelector('.search-clear');
    
    if (searchInput && clearButton) {
        if (searchInput.value.trim().length > 0) {
            clearButton.style.display = 'block';
        } else {
            clearButton.style.display = 'none';
        }
    }
}

// Enhanced search with suggestions
function setupEnhancedSearch() {
    const searchInput = document.getElementById('productSearch');
    if (!searchInput) return;
    
    // Add event listeners
    searchInput.addEventListener('input', function() {
        toggleClearButton();
        // You can add search suggestions here
        // showSearchSuggestions(this.value);
    });
    
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
    
    // Initialize clear button state
    toggleClearButton();
}

// Search suggestions (placeholder for future implementation)
function showSearchSuggestions(query) {
    const suggestionsContainer = document.getElementById('searchSuggestions');
    if (!suggestionsContainer || query.length < 2) {
        if (suggestionsContainer) {
            suggestionsContainer.classList.add('d-none');
        }
        return;
    }
    
    // Filter products for suggestions
    const suggestions = products
        .filter(product => 
            product.name.toLowerCase().includes(query.toLowerCase()) ||
            (product.description && product.description.toLowerCase().includes(query.toLowerCase())) ||
            (product.category_name && product.category_name.toLowerCase().includes(query.toLowerCase()))
        )
        .slice(0, 5); // Limit to 5 suggestions
    
    if (suggestions.length > 0) {
        const suggestionsHTML = suggestions.map(product => `
            <div class="suggestion-item" onclick="selectSuggestion('${product.name}')">
                <strong>${product.name}</strong>
                ${product.category_name ? `<small class="text-muted"> - ${product.category_name}</small>` : ''}
            </div>
        `).join('');
        
        suggestionsContainer.innerHTML = suggestionsHTML;
        suggestionsContainer.classList.remove('d-none');
    } else {
        suggestionsContainer.classList.add('d-none');
    }
}

// Select suggestion
function selectSuggestion(productName) {
    const searchInput = document.getElementById('productSearch');
    const suggestionsContainer = document.getElementById('searchSuggestions');
    
    if (searchInput) {
        searchInput.value = productName;
        toggleClearButton();
    }
    
    if (suggestionsContainer) {
        suggestionsContainer.classList.add('d-none');
    }
    
    performSearch();
}

// Hide suggestions when clicking outside
document.addEventListener('click', function(e) {
    const searchContainer = document.querySelector('.search-input-group');
    const suggestionsContainer = document.getElementById('searchSuggestions');
    
    if (suggestionsContainer && searchContainer && !searchContainer.contains(e.target)) {
        suggestionsContainer.classList.add('d-none');
    }
});

// Toggle filters for minimal search
function toggleFilters() {
    const compactFilters = document.getElementById('compactFilters');
    if (compactFilters) {
        if (compactFilters.style.display === 'none' || compactFilters.style.display === '') {
            compactFilters.style.display = 'block';
        } else {
            compactFilters.style.display = 'none';
        }
    }
}

// Initialize enhanced search when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setupEnhancedSearch();
});
