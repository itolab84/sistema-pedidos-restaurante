// FlavorFinder Service Worker
const CACHE_NAME = 'flavorfinder-v1.0.0';
const STATIC_CACHE = 'flavorfinder-static-v1.0.0';
const DYNAMIC_CACHE = 'flavorfinder-dynamic-v1.0.0';

// Files to cache immediately
const STATIC_FILES = [
    '/',
    '/index.php',
    '/assets/css/style.css',
    '/assets/js/app.js',
    '/manifest.json',
    // Bootstrap & FontAwesome (CDN fallbacks)
    'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    // Google Fonts
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap',
    // Leaflet (for maps)
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
];

// API endpoints to cache with network-first strategy
const API_ENDPOINTS = [
    '/api/products.php',
    '/api/additionals.php',
    '/api/customer_lookup.php',
    '/api/customer_addresses.php'
];

// Install event - cache static files
self.addEventListener('install', event => {
    console.log('[SW] Installing Service Worker');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[SW] Caching static files');
                return cache.addAll(STATIC_FILES);
            })
            .catch(error => {
                console.error('[SW] Error caching static files:', error);
            })
    );
    
    // Force activation of new service worker
    self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('[SW] Activating Service Worker');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('[SW] Service Worker activated');
                return self.clients.claim();
            })
    );
});

// Fetch event - handle requests with different strategies
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip chrome-extension and other non-http requests
    if (!request.url.startsWith('http')) {
        return;
    }
    
    // Handle different types of requests
    if (isStaticFile(request.url)) {
        // Static files: Cache First
        event.respondWith(cacheFirst(request));
    } else if (isAPIRequest(request.url)) {
        // API requests: Network First with fallback
        event.respondWith(networkFirstWithFallback(request));
    } else if (isImageRequest(request.url)) {
        // Images: Cache First with network fallback
        event.respondWith(cacheFirstWithNetworkFallback(request));
    } else {
        // Other requests: Network First
        event.respondWith(networkFirst(request));
    }
});

// Cache First Strategy (for static files)
async function cacheFirst(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.error('[SW] Cache First failed:', error);
        return new Response('Offline content not available', { status: 503 });
    }
}

// Network First Strategy (for dynamic content)
async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network failed, trying cache:', request.url);
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            return caches.match('/') || new Response('Offline', { status: 503 });
        }
        
        return new Response('Offline', { status: 503 });
    }
}

// Network First with API Fallback
async function networkFirstWithFallback(request) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
            return networkResponse;
        }
        throw new Error('Network response not ok');
    } catch (error) {
        console.log('[SW] API Network failed, trying cache:', request.url);
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return empty success response for API calls when offline
        return new Response(JSON.stringify({
            success: false,
            message: 'Sin conexión a internet',
            offline: true
        }), {
            status: 200,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Cache First with Network Fallback (for images)
async function cacheFirstWithNetworkFallback(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        // Return placeholder image for failed image requests
        return new Response(
            '<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="#f8f9fa"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#6c757d">Imagen no disponible</text></svg>',
            { headers: { 'Content-Type': 'image/svg+xml' } }
        );
    }
}

// Helper functions
function isStaticFile(url) {
    return STATIC_FILES.some(file => url.includes(file)) ||
           url.includes('.css') ||
           url.includes('.js') ||
           url.includes('.woff') ||
           url.includes('.woff2') ||
           url.includes('fonts.googleapis.com') ||
           url.includes('cdn.jsdelivr.net') ||
           url.includes('cdnjs.cloudflare.com');
}

function isAPIRequest(url) {
    return url.includes('/api/') || API_ENDPOINTS.some(endpoint => url.includes(endpoint));
}

function isImageRequest(url) {
    return url.includes('.jpg') ||
           url.includes('.jpeg') ||
           url.includes('.png') ||
           url.includes('.gif') ||
           url.includes('.webp') ||
           url.includes('.svg') ||
           url.includes('/uploads/');
}

// Background Sync for offline orders
self.addEventListener('sync', event => {
    console.log('[SW] Background sync triggered:', event.tag);
    
    if (event.tag === 'background-order-sync') {
        event.waitUntil(syncOfflineOrders());
    }
});

// Sync offline orders when connection is restored
async function syncOfflineOrders() {
    try {
        // Get offline orders from IndexedDB or localStorage
        const offlineOrders = await getOfflineOrders();
        
        for (const order of offlineOrders) {
            try {
                const response = await fetch('/api/orders_new.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(order)
                });
                
                if (response.ok) {
                    await removeOfflineOrder(order.id);
                    console.log('[SW] Offline order synced:', order.id);
                }
            } catch (error) {
                console.error('[SW] Failed to sync order:', order.id, error);
            }
        }
    } catch (error) {
        console.error('[SW] Background sync failed:', error);
    }
}

// Push notifications for order updates
self.addEventListener('push', event => {
    console.log('[SW] Push notification received');
    
    const options = {
        body: 'Tu pedido ha sido actualizado',
        icon: '/assets/images/icon-192x192.png',
        badge: '/assets/images/badge-72x72.png',
        vibrate: [200, 100, 200],
        data: {
            url: '/#tracking'
        },
        actions: [
            {
                action: 'view',
                title: 'Ver Pedido',
                icon: '/assets/images/action-view.png'
            },
            {
                action: 'close',
                title: 'Cerrar',
                icon: '/assets/images/action-close.png'
            }
        ]
    };
    
    if (event.data) {
        const data = event.data.json();
        options.body = data.message || options.body;
        options.data = { ...options.data, ...data };
    }
    
    event.waitUntil(
        self.registration.showNotification('FlavorFinder', options)
    );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
    console.log('[SW] Notification clicked:', event.action);
    
    event.notification.close();
    
    if (event.action === 'view') {
        event.waitUntil(
            clients.openWindow(event.notification.data.url || '/#tracking')
        );
    }
});

// Placeholder functions for offline order management
async function getOfflineOrders() {
    // Implementation would use IndexedDB to store offline orders
    return [];
}

async function removeOfflineOrder(orderId) {
    // Implementation would remove order from IndexedDB
    console.log('[SW] Removing offline order:', orderId);
}

// Message handling for communication with main thread
self.addEventListener('message', event => {
    console.log('[SW] Message received:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({ version: CACHE_NAME });
    }
});

console.log('[SW] Service Worker loaded');
