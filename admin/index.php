<?php
require_once 'config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Get dashboard statistics
$stats = [
    'total_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders")['count'] ?? 0,
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")['count'] ?? 0,
    'total_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE status = 'active'")['count'] ?? 0,
    'total_categories' => $db->fetchOne("SELECT COUNT(*) as count FROM categories WHERE status = 'active'")['count'] ?? 0,
    'today_sales' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE DATE(created_at) = CURDATE()")['total'] ?? 0
];

// Get recent orders
$recent_orders = $db->fetchAll(
    "SELECT o.*, TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as minutes_ago 
     FROM orders o 
     ORDER BY o.created_at DESC 
     LIMIT 10"
);

// Get top products
$top_products = $db->fetchAll(
    "SELECT p.name, p.image, COUNT(oi.id) as order_count, SUM(oi.quantity) as total_quantity
     FROM products p
     LEFT JOIN order_items oi ON p.id = oi.product_id
     LEFT JOIN orders o ON oi.order_id = o.id
     WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY p.id
     ORDER BY total_quantity DESC
     LIMIT 5"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Administración Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-gradient-primary text-white">
                    <div class="card-body">
                        <h4 class="card-title mb-1">
                            <i class="fas fa-sun me-2"></i>
                            ¡Bienvenido, <?= htmlspecialchars($user['full_name']) ?>!
                        </h4>
                        <p class="card-text mb-0">
                            Hoy es <?= date('l, d \d\e F \d\e Y') ?> - 
                            <span class="badge bg-light text-dark"><?= ucfirst($user['role']) ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Órdenes Totales
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['total_orders']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Órdenes Pendientes
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['pending_orders']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Productos Activos
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['total_products']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-box fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Ventas Hoy
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?= number_format($stats['today_sales'], 2) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Row -->
        <div class="row">
            <!-- Recent Orders -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>Órdenes Recientes
                        </h6>
                        <a href="orders/" class="btn btn-sm btn-primary">Ver Todas</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_orders)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No hay órdenes recientes</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                            <th>Tiempo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td>#<?= $order['id'] ?></td>
                                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                                <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $order['status'] == 'pending' ? 'warning' : 'success' ?>">
                                                        <?= ucfirst($order['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($order['minutes_ago'] < 60): ?>
                                                        <?= $order['minutes_ago'] ?> min
                                                    <?php else: ?>
                                                        <?= floor($order['minutes_ago'] / 60) ?>h <?= $order['minutes_ago'] % 60 ?>m
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Products -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-star me-2"></i>Productos Populares (7 días)
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($top_products)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-chart-bar fa-2x mb-3"></i>
                                <p>No hay datos suficientes</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($top_products as $product): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <img src="<?= $product['image'] ?: 'https://via.placeholder.com/50x50?text=IMG' ?>" 
                                         class="rounded me-3" width="50" height="50" style="object-fit: cover;">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($product['name']) ?></h6>
                                        <small class="text-muted">
                                            <?= $product['total_quantity'] ?> vendidos
                                        </small>
                                    </div>
                                    <span class="badge bg-primary"><?= $product['order_count'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-bolt me-2"></i>Acciones Rápidas
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="categories/create.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-plus-circle me-2"></i>
                                    Nueva Categoría
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="products/create.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-plus-circle me-2"></i>
                                    Nuevo Producto
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="orders/" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-list me-2"></i>
                                    Ver Órdenes
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="customers/" class="btn btn-outline-info w-100">
                                    <i class="fas fa-users me-2"></i>
                                    Ver Clientes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh dashboard every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
