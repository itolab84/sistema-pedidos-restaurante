/**
 * Sistema de Notificaciones para Panel de Administración
 * Detecta nuevas órdenes y reproduce sonidos/notificaciones
 */

class AdminNotifications {
    constructor() {
        this.lastCheck = new Date().toISOString().slice(0, 19).replace('T', ' ');
        this.checkInterval = null;
        this.isActive = true;
        this.soundEnabled = true;
        this.notificationsEnabled = true;
        this.checkFrequency = 10000; // 10 segundos
        this.isFirstCheck = true; // Flag to track initial load
        
        // Configuración de sonidos
        this.sounds = {
            newOrder: null,
            urgentOrder: null
        };
        
        this.init();
    }
    
    init() {
        this.setupSounds();
        this.requestNotificationPermission();
        this.startChecking();
        this.setupControls();
        this.setupVisibilityHandler();
        this.createNotificationContainer();
    }
    
    setupSounds() {
        try {
            // Crear sonido de notificación usando Web Audio API
            this.createNotificationSound();
        } catch (error) {
            console.warn('No se pudo configurar el sonido:', error);
        }
    }
    
    createNotificationSound() {
        // Crear sonido de campana usando Web Audio API
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        
        this.playNotificationSound = () => {
            if (!this.soundEnabled) return;
            
            try {
                // Crear sonido de campana "tilin"
                this.playBellSound(audioContext);
            } catch (error) {
                console.warn('Error al reproducir sonido:', error);
            }
        };
    }
    
    playBellSound(audioContext) {
        // Sonido de campana con múltiples tonos
        const frequencies = [800, 1000, 1200, 800]; // Frecuencias para simular campana
        const durations = [0.1, 0.1, 0.1, 0.2]; // Duraciones de cada tono
        let startTime = audioContext.currentTime;
        
        frequencies.forEach((freq, index) => {
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.setValueAtTime(freq, startTime);
            oscillator.type = 'sine';
            
            // Envelope para sonido más natural
            gainNode.gain.setValueAtTime(0, startTime);
            gainNode.gain.linearRampToValueAtTime(0.3, startTime + 0.01);
            gainNode.gain.exponentialRampToValueAtTime(0.01, startTime + durations[index]);
            
            oscillator.start(startTime);
            oscillator.stop(startTime + durations[index]);
            
            startTime += durations[index];
        });
    }
    
    async requestNotificationPermission() {
        if ('Notification' in window) {
            if (Notification.permission === 'default') {
                await Notification.requestPermission();
            }
            this.notificationsEnabled = Notification.permission === 'granted';
        }
    }
    
    startChecking() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
        
        this.checkInterval = setInterval(() => {
            if (this.isActive) {
                this.checkForNewOrders();
            }
        }, this.checkFrequency);
        
        // Verificar inmediatamente
        this.checkForNewOrders();
    }
    
    async checkForNewOrders() {
        try {
            // Detectar la ruta base correcta
            const basePath = this.getBasePath();
            
            // LÓGICA SIMPLIFICADA: Solo buscar órdenes con notification = 0
            let url = `${basePath}api/check_new_orders.php`;
            
            console.log(`🔍 Consultando API: ${url}`);
            
            const response = await fetch(url);
            
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response received:', text);
                throw new Error('Server returned non-JSON response');
            }
            
            const data = await response.json();
            console.log(`📊 API Response:`, data);
            
            if (data.success && data.new_orders_count > 0) {
                console.log(`🔔 Encontradas ${data.new_orders_count} órdenes sin notificar`);
                await this.handleNewOrders(data.new_orders);
                this.updateStats(data.stats);
            } else {
                console.log(`✅ No hay órdenes pendientes de notificación`);
            }
            
            // Update timestamp only if we got a valid response
            if (data.timestamp) {
                this.lastCheck = data.timestamp;
            }
            
        } catch (error) {
            console.error('❌ Error al verificar nuevas órdenes:', error);
            
            // Show user-friendly error message
            if (error.message.includes('JSON') || error.message.includes('HTTP error')) {
                this.showErrorToast('Error de conexión con el servidor. Verificando nuevamente...');
            }
        }
    }
    
    async handleNewOrders(orders) {
        const wasFirstCheck = this.isFirstCheck;
        this.isFirstCheck = false; // Marcar que ya no es la primera verificación
        
        // Recopilar IDs de órdenes para marcar como notificadas SOLO si no es la primera carga
        const orderIds = [];
        
        orders.forEach(order => {
            // Siempre agregar a la lista de notificaciones
            this.addToNotificationList(order);
            
            // Solo mostrar notificaciones toast y sonido para órdenes verdaderamente nuevas (no en carga inicial)
            if (!wasFirstCheck) {
                this.showOrderNotification(order);
                this.playNotificationSound();
                // Solo agregar a la lista para marcar como notificadas si NO es la primera carga
                orderIds.push(order.id);
            }
        });
        
        // Actualizar badge de notificaciones
        this.updateNotificationBadge(orders.length);
        
        // Actualizar tabla de órdenes recientes si estamos en el dashboard
        this.updateRecentOrdersTable(orders);
        
        // Si es la primera carga y hay órdenes, mostrar mensaje informativo y limpiar el placeholder
        if (wasFirstCheck && orders.length > 0) {
            this.showInfoToast(`Se encontraron ${orders.length} órdenes pendientes de notificación`);
            this.clearNotificationPlaceholder();
            console.log(`🔄 Primera carga: NO se marcan como notificadas aún`);
        }
        
        // IMPORTANTE: Marcar órdenes como notificadas SOLO si NO fue la primera carga
        if (orderIds.length > 0) {
            console.log(`📝 Marcando ${orderIds.length} órdenes como notificadas (no primera carga)`);
            await this.markOrdersAsNotified(orderIds);
        } else if (wasFirstCheck) {
            console.log(`🔄 Primera carga: ${orders.length} órdenes encontradas pero NO marcadas como notificadas`);
        }
    }
    
    async markOrdersAsNotified(orderIds) {
        try {
            const basePath = this.getBasePath();
            const url = `${basePath}api/mark_orders_notified.php`;
            
            console.log(`📝 Marcando ${orderIds.length} órdenes como notificadas:`, orderIds);
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ order_ids: orderIds })
            });
            
            if (response.ok) {
                const result = await response.json();
                console.log(`✅ ${result.message}`);
            } else {
                console.warn(`⚠️ Error al marcar órdenes como notificadas: ${response.status}`);
            }
            
        } catch (error) {
            console.warn('⚠️ Error al marcar órdenes como notificadas:', error);
        }
    }
    
    showOrderNotification(order) {
        // Notificación del navegador
        if (this.notificationsEnabled) {
            const notification = new Notification('Nueva Orden Recibida', {
                body: `Orden #${order.id} - ${order.customer_name} - $${order.total_amount}`,
                icon: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCA2NCA2NCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMzIiIGN5PSIzMiIgcj0iMzIiIGZpbGw9IiMyOGE3NDUiLz4KPHN2ZyB4PSIxNiIgeT0iMTYiIHdpZHRoPSIzMiIgaGVpZ2h0PSIzMiIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJ3aGl0ZSI+CjxwYXRoIGQ9Ik03IDRWMkg5VjRIMTVWMkgxN1Y0SDE5QTIgMiAwIDAgMSAyMSA2VjIwQTIgMiAwIDAgMSAxOSAyMkg1QTIgMiAwIDAgMSAzIDIwVjZBMiAyIDAgMCAxIDUgNEg3Wk01IDZWMjBIMTlWNkg1Wk03IDhIOVYxMEg3VjhaTTExIDhIMTNWMTBIMTFWOFpNMTUgOEgxN1YxMEgxNVY4Wk03IDEySDlWMTRIN1YxMlpNMTEgMTJIMTNWMTRIMTFWMTJaTTE1IDEySDEzVjE0SDE1VjEyWiIvPgo8L3N2Zz4KPC9zdmc+',
                tag: `order-${order.id}`,
                requireInteraction: true
            });
            
            notification.onclick = () => {
                window.focus();
                window.location.href = `orders/view.php?id=${order.id}`;
                notification.close();
            };
            
            // Auto cerrar después de 10 segundos
            setTimeout(() => notification.close(), 10000);
        }
        
        // Notificación toast en la página
        this.showToastNotification(order);
    }
    
    showToastNotification(order) {
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        toast.innerHTML = `
            <div class="notification-header">
                <i class="fas fa-shopping-cart text-success"></i>
                <strong>Nueva Orden</strong>
                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
            <div class="notification-body">
                <div><strong>Orden #${order.id}</strong></div>
                <div>Cliente: ${order.customer_name}</div>
                <div>Total: $${order.total_amount}</div>
                <div>Pago: ${order.payment_method}</div>
            </div>
            <div class="notification-actions">
                <button class="btn btn-sm btn-primary" onclick="window.location.href='orders/view.php?id=${order.id}'">
                    Ver Orden
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="this.closest('.notification-toast').remove()">
                    Cerrar
                </button>
            </div>
        `;
        
        const container = document.getElementById('notification-container');
        container.appendChild(toast);
        
        // Animar entrada
        setTimeout(() => toast.classList.add('show'), 100);
        
        // Auto remover después de 15 segundos
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 15000);
    }
    
    addToNotificationList(order) {
        const notificationsList = document.getElementById('notifications-list');
        if (!notificationsList) return;
        
        const item = document.createElement('div');
        item.className = 'notification-item new';
        item.innerHTML = `
            <div class="notification-icon">
                <i class="fas fa-shopping-cart text-success"></i>
            </div>
            <div class="notification-content">
                <div class="notification-title">Nueva Orden #${order.id}</div>
                <div class="notification-text">${order.customer_name} - $${order.total_amount}</div>
                <div class="notification-time">${this.formatTimeAgo(order.seconds_ago)}</div>
            </div>
            <div class="notification-actions">
                <a href="orders/view.php?id=${order.id}" class="btn btn-sm btn-outline-primary">Ver</a>
            </div>
        `;
        
        notificationsList.insertBefore(item, notificationsList.firstChild);
        
        // Remover clase 'new' después de 5 segundos
        setTimeout(() => item.classList.remove('new'), 5000);
    }
    
    updateNotificationBadge(count) {
        const badge = document.getElementById('notification-badge');
        if (badge) {
            const currentCount = parseInt(badge.textContent) || 0;
            const newCount = currentCount + count;
            badge.textContent = newCount;
            badge.style.display = newCount > 0 ? 'inline' : 'none';
        }
    }
    
    updateStats(stats) {
        // Actualizar estadísticas en el dashboard
        const elements = {
            'total-orders': stats.total_orders,
            'pending-orders': stats.pending_orders,
            'today-orders': stats.today_orders,
            'today-sales': `$${parseFloat(stats.today_sales).toFixed(2)}`
        };
        
        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
                element.classList.add('updated');
                setTimeout(() => element.classList.remove('updated'), 1000);
            }
        });
    }
    
    updateRecentOrdersTable(newOrders) {
        const tableBody = document.querySelector('.table tbody');
        if (!tableBody) return; // No estamos en el dashboard
        
        newOrders.forEach(order => {
            // Verificar si la orden ya existe en la tabla para evitar duplicados
            const existingRow = tableBody.querySelector(`tr td:first-child`);
            const existingOrderIds = Array.from(tableBody.querySelectorAll('tr td:first-child')).map(td => {
                const text = td.textContent.trim();
                return text.startsWith('#') ? text.substring(1) : text;
            });
            
            // Si la orden ya existe, no la agregamos
            if (existingOrderIds.includes(order.id.toString())) {
                console.log(`🔄 Orden #${order.id} ya existe en la tabla, omitiendo duplicado`);
                return;
            }
            
            // Crear nueva fila para la orden
            const newRow = document.createElement('tr');
            newRow.className = 'table-success'; // Resaltar como nueva
            newRow.setAttribute('data-order-id', order.id); // Agregar atributo para identificación
            newRow.innerHTML = `
                <td>#${order.id}</td>
                <td>${this.escapeHtml(order.customer_name)}</td>
                <td>$${parseFloat(order.total_amount).toFixed(2)}</td>
                <td>
                    <span class="badge bg-${order.status === 'pending' ? 'warning' : 'success'}">
                        ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                    </span>
                </td>
                <td>Hace un momento</td>
            `;
            
            // Insertar al inicio de la tabla
            tableBody.insertBefore(newRow, tableBody.firstChild);
            console.log(`✅ Agregada orden #${order.id} a la tabla de órdenes recientes`);
            
            // Remover la última fila si hay más de 10
            const rows = tableBody.querySelectorAll('tr');
            if (rows.length > 10) {
                tableBody.removeChild(rows[rows.length - 1]);
            }
            
            // Remover el resaltado después de 5 segundos
            setTimeout(() => {
                newRow.classList.remove('table-success');
            }, 5000);
        });
        
        // Si la tabla estaba vacía, remover el mensaje de "no hay órdenes"
        const emptyMessage = document.querySelector('.text-center.text-muted.py-4');
        if (emptyMessage) {
            emptyMessage.remove();
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    createNotificationContainer() {
        if (document.getElementById('notification-container')) return;
        
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.className = 'notification-container';
        document.body.appendChild(container);
    }
    
    setupControls() {
        // Crear controles de notificaciones en la navegación
        this.createNotificationControls();
    }
    
    createNotificationControls() {
        const nav = document.querySelector('.navbar-nav');
        if (!nav) return;
        
        const notificationItem = document.createElement('li');
        notificationItem.className = 'nav-item dropdown';
        notificationItem.innerHTML = `
            <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                <i class="fas fa-bell"></i>
                <span class="badge bg-danger" id="notification-badge" style="display: none;">0</span>
            </a>
            <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                <div class="dropdown-header d-flex justify-content-between align-items-center">
                    <span>Notificaciones</span>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="adminNotifications.toggleSound()" title="Alternar sonido">
                            <i class="fas fa-volume-${this.soundEnabled ? 'up' : 'mute'}"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="adminNotifications.clearNotifications()" title="Limpiar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <div id="notifications-list" class="notifications-list">
                    <div class="text-center text-muted p-3">No hay notificaciones nuevas</div>
                </div>
            </div>
        `;
        
        nav.appendChild(notificationItem);
    }
    
    setupVisibilityHandler() {
        document.addEventListener('visibilitychange', () => {
            this.isActive = !document.hidden;
            if (this.isActive) {
                // Verificar inmediatamente cuando la página vuelve a ser visible
                this.checkForNewOrders();
            }
        });
    }
    
    toggleSound() {
        this.soundEnabled = !this.soundEnabled;
        const icon = document.querySelector('#notificationDropdown i');
        if (icon) {
            icon.className = `fas fa-volume-${this.soundEnabled ? 'up' : 'mute'}`;
        }
        
        // Guardar preferencia
        localStorage.setItem('admin_sound_enabled', this.soundEnabled);
        
        // Mostrar feedback
        this.showToast(this.soundEnabled ? 'Sonido activado' : 'Sonido desactivado');
    }
    
    clearNotifications() {
        const notificationsList = document.getElementById('notifications-list');
        if (notificationsList) {
            notificationsList.innerHTML = '<div class="text-center text-muted p-3">No hay notificaciones nuevas</div>';
        }
        
        const badge = document.getElementById('notification-badge');
        if (badge) {
            badge.textContent = '0';
            badge.style.display = 'none';
        }
    }
    
    clearNotificationPlaceholder() {
        const notificationsList = document.getElementById('notifications-list');
        if (notificationsList) {
            const placeholder = notificationsList.querySelector('.text-center.text-muted');
            if (placeholder) {
                placeholder.remove();
            }
        }
    }
    
    showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'toast-message';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    }
    
    showErrorToast(message) {
        const toast = document.createElement('div');
        toast.className = 'toast-message error';
        toast.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            <span>${message}</span>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000); // Show error messages longer
    }
    
    showInfoToast(message) {
        const toast = document.createElement('div');
        toast.className = 'toast-message info';
        toast.innerHTML = `
            <i class="fas fa-info-circle"></i>
            <span>${message}</span>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    getBasePath() {
        // Detectar si estamos en admin/ o en la raíz
        const currentPath = window.location.pathname;
        if (currentPath.includes('/admin/')) {
            return '../';
        } else {
            return './';
        }
    }
    
    formatTimeAgo(seconds) {
        if (seconds < 60) return 'Hace un momento';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `Hace ${minutes} min`;
        const hours = Math.floor(minutes / 60);
        return `Hace ${hours}h ${minutes % 60}m`;
    }
    
    destroy() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
        this.isActive = false;
    }
}

// Función de inicialización más robusta
function initializeNotifications() {
    try {
        console.log('Inicializando sistema de notificaciones...');
        window.adminNotifications = new AdminNotifications();
        console.log('Sistema de notificaciones inicializado correctamente');
    } catch (error) {
        console.error('Error inicializando notificaciones:', error);
        // Reintentar después de 2 segundos
        setTimeout(initializeNotifications, 2000);
    }
}

// Múltiples formas de inicialización para asegurar que funcione
if (document.readyState === 'loading') {
    // DOM aún cargando
    document.addEventListener('DOMContentLoaded', initializeNotifications);
} else {
    // DOM ya cargado
    initializeNotifications();
}

// Backup: inicializar después de que la ventana esté completamente cargada
window.addEventListener('load', () => {
    if (!window.adminNotifications) {
        console.log('Inicialización de respaldo ejecutándose...');
        initializeNotifications();
    }
});

// Limpiar al salir de la página
window.addEventListener('beforeunload', () => {
    if (window.adminNotifications) {
        window.adminNotifications.destroy();
    }
});

// Debug: Exponer función para inicialización manual
window.initNotifications = initializeNotifications;
