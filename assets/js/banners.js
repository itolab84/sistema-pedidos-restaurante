/**
 * Banner Management System
 * Handles banner display, interactions, and analytics
 */

class BannerManager {
    constructor() {
        this.banners = [];
        this.currentBannerIndex = 0;
        this.autoRotateInterval = null;
        this.autoRotateDelay = 5000; // 5 seconds
        this.apiUrl = '/reserve/api/banners.php';
        
        this.init();
    }
    
    async init() {
        try {
            await this.loadBanners();
            this.renderBanners();
            this.setupEventListeners();
            this.startAutoRotate();
        } catch (error) {
            console.error('Error initializing banner manager:', error);
        }
    }
    
    async loadBanners(position = '', limit = 10) {
        try {
            const params = new URLSearchParams();
            if (position) params.append('position', position);
            if (limit) params.append('limit', limit.toString());
            
            const response = await fetch(`${this.apiUrl}?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.banners = data.banners;
                return this.banners;
            } else {
                throw new Error(data.message || 'Error loading banners');
            }
        } catch (error) {
            console.error('Error loading banners:', error);
            return [];
        }
    }
    
    renderBanners() {
        this.renderHeroBanners();
        this.renderSidebarBanners();
        this.renderFooterBanners();
        this.renderPopupBanners();
    }
    
    renderHeroBanners() {
        const heroBanners = this.banners.filter(banner => banner.position === 'hero');
        const container = document.getElementById('hero-banners');
        
        if (!container || heroBanners.length === 0) return;
        
        container.innerHTML = '';
        
        // Create carousel structure
        const carousel = document.createElement('div');
        carousel.className = 'banner-carousel';
        carousel.innerHTML = `
            <div class="banner-slides">
                ${heroBanners.map((banner, index) => this.createBannerSlide(banner, index === 0)).join('')}
            </div>
            ${heroBanners.length > 1 ? this.createCarouselControls(heroBanners.length) : ''}
        `;
        
        container.appendChild(carousel);
        
        // Setup carousel functionality
        if (heroBanners.length > 1) {
            this.setupCarousel(container);
        }
    }
    
    renderSidebarBanners() {
        const sidebarBanners = this.banners.filter(banner => banner.position === 'sidebar');
        const container = document.getElementById('sidebar-banners');
        
        if (!container || sidebarBanners.length === 0) return;
        
        container.innerHTML = sidebarBanners.map(banner => this.createSidebarBanner(banner)).join('');
    }
    
    renderFooterBanners() {
        const footerBanners = this.banners.filter(banner => banner.position === 'footer');
        const container = document.getElementById('footer-banners');
        
        if (!container || footerBanners.length === 0) return;
        
        container.innerHTML = footerBanners.map(banner => this.createFooterBanner(banner)).join('');
    }
    
    renderPopupBanners() {
        const popupBanners = this.banners.filter(banner => banner.position === 'popup');
        
        popupBanners.forEach(banner => {
            // Show popup after a delay
            setTimeout(() => {
                this.showPopupBanner(banner);
            }, 3000); // Show after 3 seconds
        });
    }
    
    createBannerSlide(banner, isActive = false) {
        return `
            <div class="banner-slide ${isActive ? 'active' : ''}" data-banner-id="${banner.id}">
                <div class="banner-content" ${banner.link_type !== 'none' ? `onclick="bannerManager.handleBannerClick(${banner.id})"` : ''}>
                    <img src="${banner.image_url}" alt="${this.escapeHtml(banner.title)}" class="banner-image">
                    <div class="banner-overlay">
                        <div class="banner-text">
                            <h2 class="banner-title">${this.escapeHtml(banner.title)}</h2>
                            ${banner.description ? `<p class="banner-description">${this.escapeHtml(banner.description)}</p>` : ''}
                            ${this.createBannerCTA(banner)}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    createSidebarBanner(banner) {
        return `
            <div class="sidebar-banner mb-3" data-banner-id="${banner.id}">
                <div class="card banner-card" ${banner.link_type !== 'none' ? `onclick="bannerManager.handleBannerClick(${banner.id})"` : ''}>
                    <img src="${banner.image_url}" alt="${this.escapeHtml(banner.title)}" class="card-img-top">
                    <div class="card-body">
                        <h6 class="card-title">${this.escapeHtml(banner.title)}</h6>
                        ${banner.description ? `<p class="card-text small">${this.escapeHtml(banner.description)}</p>` : ''}
                        ${this.createBannerCTA(banner, 'btn-sm')}
                    </div>
                </div>
            </div>
        `;
    }
    
    createFooterBanner(banner) {
        return `
            <div class="footer-banner col-md-4 mb-3" data-banner-id="${banner.id}">
                <div class="card banner-card h-100" ${banner.link_type !== 'none' ? `onclick="bannerManager.handleBannerClick(${banner.id})"` : ''}>
                    <img src="${banner.image_url}" alt="${this.escapeHtml(banner.title)}" class="card-img-top">
                    <div class="card-body">
                        <h6 class="card-title">${this.escapeHtml(banner.title)}</h6>
                        ${banner.description ? `<p class="card-text">${this.escapeHtml(banner.description)}</p>` : ''}
                        ${this.createBannerCTA(banner, 'btn-sm')}
                    </div>
                </div>
            </div>
        `;
    }
    
    createBannerCTA(banner, size = '') {
        if (banner.link_type === 'none') return '';
        
        let ctaText = 'Ver más';
        if (banner.link_type === 'product') {
            ctaText = banner.product_price ? `Ver producto - $${banner.product_price}` : 'Ver producto';
        } else if (banner.link_type === 'url') {
            ctaText = 'Visitar enlace';
        }
        
        return `<button class="btn btn-primary ${size}" onclick="event.stopPropagation(); bannerManager.handleBannerClick(${banner.id})">${ctaText}</button>`;
    }
    
    createCarouselControls(totalSlides) {
        return `
            <div class="banner-controls">
                <button class="banner-control prev" onclick="bannerManager.previousSlide()">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="banner-control next" onclick="bannerManager.nextSlide()">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="banner-indicators">
                ${Array.from({length: totalSlides}, (_, i) => 
                    `<button class="banner-indicator ${i === 0 ? 'active' : ''}" onclick="bannerManager.goToSlide(${i})"></button>`
                ).join('')}
            </div>
        `;
    }
    
    showPopupBanner(banner) {
        // Check if popup was already shown in this session
        const popupKey = `banner_popup_${banner.id}`;
        if (sessionStorage.getItem(popupKey)) return;
        
        const popup = document.createElement('div');
        popup.className = 'banner-popup-overlay';
        popup.innerHTML = `
            <div class="banner-popup" data-banner-id="${banner.id}">
                <button class="banner-popup-close" onclick="bannerManager.closePopup(this)">
                    <i class="fas fa-times"></i>
                </button>
                <div class="banner-popup-content" ${banner.link_type !== 'none' ? `onclick="bannerManager.handleBannerClick(${banner.id})"` : ''}>
                    <img src="${banner.image_url}" alt="${this.escapeHtml(banner.title)}" class="banner-popup-image">
                    <div class="banner-popup-text">
                        <h3>${this.escapeHtml(banner.title)}</h3>
                        ${banner.description ? `<p>${this.escapeHtml(banner.description)}</p>` : ''}
                        ${this.createBannerCTA(banner)}
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(popup);
        
        // Mark as shown
        sessionStorage.setItem(popupKey, 'true');
        
        // Auto-close after 10 seconds
        setTimeout(() => {
            if (popup.parentNode) {
                this.closePopup(popup.querySelector('.banner-popup-close'));
            }
        }, 10000);
    }
    
    closePopup(closeButton) {
        const popup = closeButton.closest('.banner-popup-overlay');
        if (popup) {
            popup.remove();
        }
    }
    
    setupCarousel(container) {
        const slides = container.querySelectorAll('.banner-slide');
        if (slides.length <= 1) return;
        
        this.currentBannerIndex = 0;
        this.updateCarouselIndicators(container);
    }
    
    nextSlide() {
        const container = document.getElementById('hero-banners');
        const slides = container.querySelectorAll('.banner-slide');
        
        if (slides.length <= 1) return;
        
        slides[this.currentBannerIndex].classList.remove('active');
        this.currentBannerIndex = (this.currentBannerIndex + 1) % slides.length;
        slides[this.currentBannerIndex].classList.add('active');
        
        this.updateCarouselIndicators(container);
    }
    
    previousSlide() {
        const container = document.getElementById('hero-banners');
        const slides = container.querySelectorAll('.banner-slide');
        
        if (slides.length <= 1) return;
        
        slides[this.currentBannerIndex].classList.remove('active');
        this.currentBannerIndex = this.currentBannerIndex === 0 ? slides.length - 1 : this.currentBannerIndex - 1;
        slides[this.currentBannerIndex].classList.add('active');
        
        this.updateCarouselIndicators(container);
    }
    
    goToSlide(index) {
        const container = document.getElementById('hero-banners');
        const slides = container.querySelectorAll('.banner-slide');
        
        if (index < 0 || index >= slides.length) return;
        
        slides[this.currentBannerIndex].classList.remove('active');
        this.currentBannerIndex = index;
        slides[this.currentBannerIndex].classList.add('active');
        
        this.updateCarouselIndicators(container);
    }
    
    updateCarouselIndicators(container) {
        const indicators = container.querySelectorAll('.banner-indicator');
        indicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === this.currentBannerIndex);
        });
    }
    
    startAutoRotate() {
        const heroBanners = this.banners.filter(banner => banner.position === 'hero');
        if (heroBanners.length <= 1) return;
        
        this.autoRotateInterval = setInterval(() => {
            this.nextSlide();
        }, this.autoRotateDelay);
    }
    
    stopAutoRotate() {
        if (this.autoRotateInterval) {
            clearInterval(this.autoRotateInterval);
            this.autoRotateInterval = null;
        }
    }
    
    async handleBannerClick(bannerId) {
        try {
            // Find the banner data
            const banner = this.getBannerById(bannerId);
            if (!banner) {
                console.error('Banner not found:', bannerId);
                return;
            }
            
            // Track the click
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    banner_id: bannerId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Handle different link types
                if (banner.link_type === 'product' && banner.product_id) {
                    // Open product modal instead of redirecting
                    this.openProductModal(banner.product_id);
                } else if (banner.link_type === 'url' && banner.external_url) {
                    // External URL - open in new tab
                    window.open(banner.external_url, '_blank');
                } else if (data.redirect_url) {
                    // Fallback to redirect URL from API
                    if (data.redirect_url.startsWith('http')) {
                        window.open(data.redirect_url, '_blank');
                    } else {
                        window.location.href = data.redirect_url;
                    }
                }
            }
        } catch (error) {
            console.error('Error tracking banner click:', error);
        }
    }
    
    async openProductModal(productId) {
        try {
            // Check if the main app's product modal function exists
            if (typeof window.showProductModal === 'function') {
                // Use the existing product modal function
                window.showProductModal(productId);
            } else if (typeof window.openProductModal === 'function') {
                // Alternative function name
                window.openProductModal(productId);
            } else {
                // Fallback: try to fetch product data and open modal manually
                const productResponse = await fetch(`/reserve/api/products.php?id=${productId}`);
                const productData = await productResponse.json();
                
                if (productData.success && productData.product) {
                    // Try to trigger the product modal with the product data
                    this.triggerProductModal(productData.product);
                } else {
                    // Last resort: redirect to product page
                    window.location.href = `/reserve/product.php?id=${productId}`;
                }
            }
        } catch (error) {
            console.error('Error opening product modal:', error);
            // Fallback to redirect
            window.location.href = `/reserve/product.php?id=${productId}`;
        }
    }
    
    triggerProductModal(product) {
        try {
            // Try to populate and show the existing product modal
            const modal = document.getElementById('productModal');
            if (modal) {
                // Populate modal fields
                const titleElement = document.getElementById('productModalTitle');
                const nameElement = document.getElementById('productModalName');
                const descriptionElement = document.getElementById('productModalDescription');
                const priceElement = document.getElementById('productModalPrice');
                const imageElement = document.getElementById('productModalMainImage');
                
                if (titleElement) titleElement.textContent = product.name;
                if (nameElement) nameElement.textContent = product.name;
                if (descriptionElement) descriptionElement.textContent = product.description || '';
                if (priceElement) priceElement.textContent = product.price;
                if (imageElement) {
                    imageElement.src = product.image_url || '/reserve/assets/images/placeholder.jpg';
                    imageElement.alt = product.name;
                }
                
                // Show the modal using Bootstrap
                const bootstrapModal = new bootstrap.Modal(modal);
                bootstrapModal.show();
                
                // Store product data for cart functionality
                if (typeof window.currentProduct !== 'undefined') {
                    window.currentProduct = product;
                }
            } else {
                throw new Error('Product modal not found');
            }
        } catch (error) {
            console.error('Error triggering product modal:', error);
            // Fallback to redirect
            window.location.href = `/reserve/product.php?id=${product.id}`;
        }
    }
    
    setupEventListeners() {
        // Pause auto-rotate on hover
        const heroContainer = document.getElementById('hero-banners');
        if (heroContainer) {
            heroContainer.addEventListener('mouseenter', () => this.stopAutoRotate());
            heroContainer.addEventListener('mouseleave', () => this.startAutoRotate());
        }
        
        // Close popup on overlay click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('banner-popup-overlay')) {
                this.closePopup(e.target.querySelector('.banner-popup-close'));
            }
        });
        
        // Close popup on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const popup = document.querySelector('.banner-popup-overlay');
                if (popup) {
                    this.closePopup(popup.querySelector('.banner-popup-close'));
                }
            }
        });
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Public methods for external use
    async refreshBanners(position = '') {
        await this.loadBanners(position);
        this.renderBanners();
    }
    
    getBannersByPosition(position) {
        return this.banners.filter(banner => banner.position === position);
    }
    
    getBannerById(id) {
        return this.banners.find(banner => banner.id === id);
    }
}

// Initialize banner manager when DOM is loaded
let bannerManager;

document.addEventListener('DOMContentLoaded', function() {
    bannerManager = new BannerManager();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BannerManager;
}
