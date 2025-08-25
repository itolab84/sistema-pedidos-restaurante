// Shopping cart functionality
let cart = [];
let products = [];
let additionals = [];
let currentProduct = null;
let currentCustomer = null;
let map = null;
let marker = null;

// Load products on page load
document.addEventListener('DOMContentLoaded', function() {
    loadProducts();
    loadAdditionals();
    initializeOrderTypeToggle();
});

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
    container.innerHTML = '';
    
    products.forEach(product => {
        const productCard = `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card product-card h-100" style="cursor: pointer;" onclick="openProductModal(${product.id})">
                    <img src="${product.image || 'https://via.placeholder.com/300x200?text=' + encodeURIComponent(product.name)}" 
                         class="card-img-top product-image" alt="${product.name}" style="height: 200px; object-fit: cover;">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">${product.name}</h5>
                        <p class="card-text flex-grow-1">${product.description}</p>
                        <div class="mt-auto">
                            <p class="price h5 text-success mb-2">$${parseFloat(product.price).toFixed(2)}</p>
                            <button class="btn btn-primary w-100" onclick="event.stopPropagation(); openProductModal(${product.id})">
                                <i class="fas fa-eye"></i> Ver Detalles
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.innerHTML += productCard;
    });
}

// Open product detail modal with enhanced features
async function openProductModal(productId) {
    productId = parseInt(productId);
    currentProduct = products.find(p => parseInt(p.id) === productId);
    
    if (!currentProduct) {
        console.error('Product not found:', productId);
        return;
    }
    
    // Populate modal with product data
    document.getElementById('productModalTitle').textContent = currentProduct.name;
    document.getElementById('productModalName').textContent = currentProduct.name;
    document.getElementById('productModalDescription').textContent = currentProduct.description;
    document.getElementById('productModalPrice').textContent = parseFloat(currentProduct.price).toFixed(2);
    
    // Load main image
    document.getElementById('productModalMainImage').src = currentProduct.image || 'https://via.placeholder.com/400x300?text=' + encodeURIComponent(currentProduct.name);
    
    // Load image gallery
    await loadProductImages(productId);
    
    // Load additionals for this product
    loadProductAdditionals(productId);
    
    // Reset form values
    document.getElementById('productQuantity').value = 1;
    document.getElementById('productNotes').value = '';
    
    // Update total price
    updateProductTotalPrice();
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();
}

// Load product images gallery
async function loadProductImages(productId) {
    try {
        const response = await fetch(`api/product_images.php?product_id=${productId}`);
        const data = await response.json();
        
        const thumbnailsContainer = document.getElementById('productImageThumbnails');
        thumbnailsContainer.innerHTML = '';
        
        if (data.success && data.images.length > 0) {
            data.images.forEach((image, index) => {
                const thumbnail = document.createElement('img');
                thumbnail.src = image.image_path;
                thumbnail.className = 'img-thumbnail me-2 mb-2';
                thumbnail.style.width = '80px';
                thumbnail.style.height = '80px';
                thumbnail.style.objectFit = 'cover';
                thumbnail.style.cursor = 'pointer';
                thumbnail.onclick = () => {
                    document.getElementById('productModalMainImage').src = image.image_path;
                };
                thumbnailsContainer.appendChild(thumbnail);
            });
        }
    } catch (error) {
        console.error('Error loading product images:', error);
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
    
    if (isDelivery) {
        deliverySection.style.display = 'block';
        pickupSection.style.display = 'none';
        deliveryFeeRow.style.display = 'flex';
    } else {
        deliverySection.style.display = 'none';
        pickupSection.style.display = 'block';
        deliveryFeeRow.style.display = 'none';
        loadStoreLocations();
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
        const response = await fetch('api/orders.php', {
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
