<?php
// Get current page for active navigation
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Determine active menu item
$activeMenu = '';
if ($currentDir === 'admin' || $currentPage === 'index.php') {
    $activeMenu = 'dashboard';
} elseif ($currentDir === 'categories') {
    $activeMenu = 'categories';
} elseif ($currentDir === 'products' || $currentDir === 'additionals') {
    $activeMenu = 'products';
} elseif ($currentDir === 'customers') {
    $activeMenu = 'customers';
} elseif ($currentDir === 'orders') {
    $activeMenu = 'orders';
} elseif ($currentDir === 'payments') {
    $activeMenu = 'payments';
} elseif ($currentDir === 'banners') {
    $activeMenu = 'banners';
} elseif ($currentDir === 'employees' || $currentDir === 'delivery') {
    $activeMenu = 'configuration';
} elseif ($currentDir === 'payment_methods' || $currentDir === 'company' || $currentDir === 'integrations') {
    $activeMenu = 'configuration';
}
?>

<!-- Sidebar Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Brand -->
    <a href="<?= $currentDir === 'admin' ? 'index.php' : '../index.php' ?>" class="sidebar-brand">
        <i class="fas fa-utensils"></i>
        <span>FlavorFinder Admin</span>
    </a>
    
    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="sidebar-nav-item">
            <a href="<?= $currentDir === 'admin' ? 'index.php' : '../index.php' ?>" 
               class="sidebar-nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="sidebar-nav-text">Dashboard</span>
            </a>
        </div>
        
        <div class="sidebar-nav-item">
            <a href="<?= $currentDir === 'admin' ? 'categories/' : ($currentDir === 'categories' ? 'index.php' : '../categories/') ?>" 
               class="sidebar-nav-link <?= $activeMenu === 'categories' ? 'active' : '' ?>">
                <i class="fas fa-tags"></i>
                <span class="sidebar-nav-text">Categorías</span>
            </a>
        </div>
        
        <!-- Products with Submenu -->
        <div class="sidebar-nav-item has-submenu">
            <a href="#" class="sidebar-nav-link <?= $activeMenu === 'products' ? 'active' : '' ?>" 
               onclick="toggleSubmenu(this)">
                <i class="fas fa-box"></i>
                <span class="sidebar-nav-text">Productos</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <div class="sidebar-submenu <?= $activeMenu === 'products' ? 'show' : '' ?>">
                <a href="<?= $currentDir === 'admin' ? 'products/' : ($currentDir === 'products' ? 'index.php' : '../products/') ?>" 
                   class="sidebar-submenu-link <?= $currentDir === 'products' ? 'active' : '' ?>">
                    <i class="fas fa-box"></i>
                    <span>Gestionar Productos</span>
                </a>
                <a href="<?= $currentDir === 'admin' ? 'additionals/' : ($currentDir === 'additionals' ? 'index.php' : '../additionals/') ?>" 
                   class="sidebar-submenu-link <?= $currentDir === 'additionals' ? 'active' : '' ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span>Adicionales</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-nav-item">
            <a href="<?= $currentDir === 'admin' ? 'customers/' : ($currentDir === 'customers' ? 'index.php' : '../customers/') ?>" 
               class="sidebar-nav-link <?= $activeMenu === 'customers' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span class="sidebar-nav-text">Clientes</span>
            </a>
        </div>
        
        <div class="sidebar-nav-item">
            <a href="<?= $currentDir === 'admin' ? 'orders/' : ($currentDir === 'orders' ? 'index.php' : '../orders/') ?>" 
               class="sidebar-nav-link <?= $activeMenu === 'orders' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i>
                <span class="sidebar-nav-text">Órdenes</span>
            </a>
        </div>
        
        <div class="sidebar-nav-item">
            <a href="<?= $currentDir === 'admin' ? 'payments/' : ($currentDir === 'payments' ? 'index.php' : '../payments/') ?>" 
               class="sidebar-nav-link <?= $activeMenu === 'payments' ? 'active' : '' ?>">
                <i class="fas fa-credit-card"></i>
                <span class="sidebar-nav-text">Pagos</span>
            </a>
        </div>
        
        <div class="sidebar-nav-item">
            <a href="<?= $currentDir === 'admin' ? 'banners/' : ($currentDir === 'banners' ? 'index.php' : '../banners/') ?>" 
               class="sidebar-nav-link <?= $activeMenu === 'banners' ? 'active' : '' ?>">
                <i class="fas fa-images"></i>
                <span class="sidebar-nav-text">Banners</span>
            </a>
        </div>
        
        <!-- Configuration with Submenu -->
        <div class="sidebar-nav-item has-submenu">
            <a href="#" class="sidebar-nav-link <?= $activeMenu === 'configuration' ? 'active' : '' ?>" 
               onclick="toggleSubmenu(this)">
                <i class="fas fa-cogs"></i>
                <span class="sidebar-nav-text">Configuración</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <div class="sidebar-submenu <?= $activeMenu === 'configuration' ? 'show' : '' ?>">
                <a href="<?= $currentDir === 'admin' ? 'employees/' : ($currentDir === 'employees' ? 'index.php' : '../employees/') ?>" 
                   class="sidebar-submenu-link <?= $currentDir === 'employees' ? 'active' : '' ?>">
                    <i class="fas fa-user-tie"></i>
                    <span>Empleados</span>
                </a>
                <a href="<?= $currentDir === 'admin' ? 'delivery/' : ($currentDir === 'delivery' ? 'index.php' : '../delivery/') ?>" 
                   class="sidebar-submenu-link <?= $currentDir === 'delivery' ? 'active' : '' ?>">
                    <i class="fas fa-truck"></i>
                    <span>Delivery</span>
                </a>
                <a href="<?= $currentDir === 'admin' ? 'payment_methods/' : ($currentDir === 'payment_methods' ? 'index.php' : '../payment_methods/') ?>" 
                   class="sidebar-submenu-link <?= $currentDir === 'payment_methods' ? 'active' : '' ?>">
                    <i class="fas fa-money-check-alt"></i>
                    <span>Métodos de Pagos</span>
                </a>
                <a href="<?= $currentDir === 'admin' ? 'company/' : ($currentDir === 'company' ? 'index.php' : '../company/') ?>" 
                   class="sidebar-submenu-link <?= $currentDir === 'company' ? 'active' : '' ?>">
                    <i class="fas fa-building"></i>
                    <span>Empresa</span>
                </a>
                <a href="<?= $currentDir === 'admin' ? 'integrations/' : ($currentDir === 'integrations' ? 'index.php' : '../integrations/') ?>" 
                   class="sidebar-submenu-link <?= $currentDir === 'integrations' ? 'active' : '' ?>">
                    <i class="fas fa-plug"></i>
                    <span>Integraciones</span>
                </a>
            </div>
        </div>
    </nav>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Header -->
    <div class="top-header">
        <button class="mobile-toggle" id="mobileToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Notifications and User Section -->
        <div class="header-actions">
            <!-- Notifications Dropdown -->
            <div class="dropdown me-3">
                <button class="btn btn-outline-secondary position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notification-badge" style="display: none;">
                        0
                    </span>
                </button>
                <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                    <div class="dropdown-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-bell me-2"></i>Notificaciones</span>
                        <div>
                            <button class="btn btn-sm btn-outline-secondary me-1" onclick="toggleSound()" title="Alternar sonido" id="soundToggle">
                                <i class="fas fa-volume-up"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="clearNotifications()" title="Limpiar notificaciones">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div id="notifications-list" class="notifications-list">
                        <div class="text-center text-muted p-3">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            No hay notificaciones nuevas
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-footer text-center">
                        <a href="<?= $currentDir === 'admin' ? 'orders/' : ($currentDir === 'orders' ? 'index.php' : '../orders/') ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye me-1"></i>Ver todas las órdenes
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- User Dropdown -->
            <div class="user-dropdown dropdown">
                <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i>
                    <?= htmlspecialchars($user['full_name']) ?>
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item" href="<?= $currentDir === 'admin' ? 'logout.php' : '../logout.php' ?>">
                            <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Page Content -->
    <div class="container-fluid">

<!-- Incluir CSS del sistema simple de notificaciones -->
<link rel="stylesheet" href="<?= $currentDir === 'admin' ? 'assets/css/notifications_simple.css' : '../assets/css/notifications_simple.css' ?>">

<!-- Incluir JavaScript del sistema simple de notificaciones -->
<script src="<?= $currentDir === 'admin' ? 'assets/js/notifications_simple.js' : '../assets/js/notifications_simple.js' ?>"></script>

<script>
function toggleSubmenu(element) {
    event.preventDefault();
    const submenu = element.nextElementSibling;
    const arrow = element.querySelector('.submenu-arrow');
    
    if (submenu.classList.contains('show')) {
        submenu.classList.remove('show');
        arrow.style.transform = 'rotate(0deg)';
    } else {
        submenu.classList.add('show');
        arrow.style.transform = 'rotate(180deg)';
    }
}

// Auto-expand submenu if we're in a submenu page
document.addEventListener('DOMContentLoaded', function() {
    const activeSubmenu = document.querySelector('.sidebar-submenu.show');
    if (activeSubmenu) {
        const arrow = activeSubmenu.previousElementSibling.querySelector('.submenu-arrow');
        if (arrow) {
            arrow.style.transform = 'rotate(180deg)';
        }
    }
    
    // Sidebar toggle functionality
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebarToggle && sidebar && mainContent) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Change icon
            const icon = sidebarToggle.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-arrow-right');
            } else {
                icon.classList.remove('fa-arrow-right');
                icon.classList.add('fa-bars');
            }
        });
    }
});
</script>
