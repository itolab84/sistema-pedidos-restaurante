<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Handle actions
$message = '';
$messageType = 'success';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_route':
                try {
                    $routeData = [
                        'route_name' => $_POST['route_name'],
                        'route_code' => $_POST['route_code'],
                        'description' => $_POST['description'] ?: null,
                        'estimated_time_minutes' => (int)$_POST['estimated_time_minutes'],
                        'max_orders_per_trip' => (int)$_POST['max_orders_per_trip'],
                        'coverage_area' => $_POST['coverage_area'] ?: null,
                        'is_active' => 1
                    ];
                    
                    $db->insert('delivery_routes', $routeData);
                    $message = 'Ruta creada exitosamente';
                } catch (Exception $e) {
                    $message = 'Error al crear ruta: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'update_route':
                try {
                    $id = (int)$_POST['id'];
                    $routeData = [
                        'route_name' => $_POST['route_name'],
                        'route_code' => $_POST['route_code'],
                        'description' => $_POST['description'] ?: null,
                        'estimated_time_minutes' => (int)$_POST['estimated_time_minutes'],
                        'max_orders_per_trip' => (int)$_POST['max_orders_per_trip'],
                        'coverage_area' => $_POST['coverage_area'] ?: null
                    ];
                    
                    $db->update('delivery_routes', $routeData, 'id = ?', [$id]);
                    $message = 'Ruta actualizada exitosamente';
                } catch (Exception $e) {
                    $message = 'Error al actualizar ruta: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'toggle_status':
                $id = (int)$_POST['id'];
                $currentStatus = (int)$_POST['current_status'];
                $newStatus = $currentStatus ? 0 : 1;
                
                $db->update('delivery_routes', 
                    ['is_active' => $newStatus], 
                    'id = ?', 
                    [$id]
                );
                
                $message = $newStatus ? 'Ruta activada' : 'Ruta desactivada';
                break;
        }
    }
}

// Get routes with statistics
$routes = $db->fetchAll(
    "SELECT dr.*, 
            COUNT(da.id) as total_deliveries,
            COUNT(CASE WHEN da.delivery_status = 'delivered' THEN 1 END) as successful_deliveries,
            AVG(CASE 
                WHEN da.delivery_time IS NOT NULL AND da.pickup_time IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, da.pickup_time, da.delivery_time) 
                ELSE NULL 
            END) as avg_delivery_time
     FROM delivery_routes dr
     LEFT JOIN delivery_assignments da ON dr.id = da.route_id
     GROUP BY dr.id
     ORDER BY dr.is_active DESC, dr.route_name ASC"
);

// Get statistics
$stats = [
    'total_routes' => count($routes),
    'active_routes' => count(array_filter($routes, function($r) { return $r['is_active']; })),
    'inactive_routes' => count(array_filter($routes, function($r) { return !$r['is_active']; })),
    'avg_time_all_routes' => $db->fetchOne("
        SELECT AVG(estimated_time_minutes) as avg_time 
        FROM delivery_routes 
        WHERE is_active = 1
    ")['avg_time'] ?? 0,
    'total_coverage_routes' => $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM delivery_routes 
        WHERE coverage_area IS NOT NULL AND coverage_area != '' AND is_active = 1
    ")['count'] ?? 0
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rutas de Delivery - Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-route me-2 text-primary"></i>
                            Rutas de Delivery
                        </h2>
                        <p class="text-muted mb-0">
                            Gestiona y optimiza las rutas de entrega para maximizar la eficiencia
                        </p>
                    </div>
                    <div>
                        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#createRouteModal">
                            <i class="fas fa-plus me-2"></i>
                            Nueva Ruta
                        </button>
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver a Deliveries
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-left-primary shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Rutas
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['total_routes'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-route fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-success shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Rutas Activas
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['active_routes'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-warning shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Tiempo Promedio
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= round($stats['avg_time_all_routes']) ?> min
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-info shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Con Cobertura
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['total_coverage_routes'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-map-marked-alt fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Routes Table -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>Lista de Rutas de Delivery
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($routes)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-route fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay rutas registradas</h5>
                                <p class="text-muted">Crea la primera ruta de delivery para comenzar</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRouteModal">
                                    <i class="fas fa-plus me-2"></i>Crear Primera Ruta
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre de Ruta</th>
                                            <th>Tiempo Estimado</th>
                                            <th>Capacidad</th>
                                            <th>Área de Cobertura</th>
                                            <th>Rendimiento</th>
                                            <th>Estado</th>
                                            <th width="150">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($routes as $route): ?>
                                            <tr class="<?= !$route['is_active'] ? 'table-secondary' : '' ?>">
                                                <td>
                                                    <span class="badge bg-<?= $route['is_active'] ? 'primary' : 'secondary' ?> fs-6">
                                                        <?= htmlspecialchars($route['route_code']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($route['route_name']) ?></h6>
                                                        <?php if ($route['description']): ?>
                                                            <small class="text-muted">
                                                                <?= htmlspecialchars($route['description']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-clock text-muted me-2"></i>
                                                        <span class="fw-bold"><?= $route['estimated_time_minutes'] ?> min</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-box text-muted me-2"></i>
                                                        <span><?= $route['max_orders_per_trip'] ?> órdenes</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($route['coverage_area']): ?>
                                                        <small class="text-muted" title="<?= htmlspecialchars($route['coverage_area']) ?>">
                                                            <?= htmlspecialchars(substr($route['coverage_area'], 0, 40)) ?>
                                                            <?= strlen($route['coverage_area']) > 40 ? '...' : '' ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">No especificada</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <small class="d-block">
                                                            <strong>Deliveries:</strong> <?= $route['total_deliveries'] ?>
                                                        </small>
                                                        <small class="d-block">
                                                            <strong>Exitosos:</strong> <?= $route['successful_deliveries'] ?>
                                                        </small>
                                                        <?php if ($route['avg_delivery_time']): ?>
                                                            <small class="d-block text-info">
                                                                <strong>Tiempo real:</strong> <?= round($route['avg_delivery_time']) ?> min
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="id" value="<?= $route['id'] ?>">
                                                        <input type="hidden" name="current_status" value="<?= $route['is_active'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-<?= $route['is_active'] ? 'success' : 'secondary' ?>">
                                                            <i class="fas fa-<?= $route['is_active'] ? 'check' : 'times' ?> me-1"></i>
                                                            <?= $route['is_active'] ? 'Activa' : 'Inactiva' ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="editRoute(<?= htmlspecialchars(json_encode($route)) ?>)"
                                                                title="Editar ruta">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                onclick="viewRouteDetails(<?= htmlspecialchars(json_encode($route)) ?>)"
                                                                title="Ver detalles">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
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
        </div>

    <!-- Create Route Modal -->
    <div class="modal fade" id="createRouteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Crear Nueva Ruta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_route">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="route_name" class="form-label">Nombre de la Ruta *</label>
                                    <input type="text" class="form-control" id="route_name" name="route_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="route_code" class="form-label">Código de Ruta *</label>
                                    <input type="text" class="form-control" id="route_code" name="route_code" 
                                           placeholder="Ej: RN01, RSP02" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="2" 
                                      placeholder="Descripción breve de la ruta..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="estimated_time_minutes" class="form-label">Tiempo Estimado (minutos) *</label>
                                    <input type="number" class="form-control" id="estimated_time_minutes" 
                                           name="estimated_time_minutes" min="5" max="180" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_orders_per_trip" class="form-label">Máximo Órdenes por Viaje *</label>
                                    <input type="number" class="form-control" id="max_orders_per_trip" 
                                           name="max_orders_per_trip" min="1" max="20" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="coverage_area" class="form-label">Área de Cobertura</label>
                            <textarea class="form-control" id="coverage_area" name="coverage_area" rows="3" 
                                      placeholder="Describe las zonas, colonias o áreas que cubre esta ruta..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Crear Ruta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Route Modal -->
    <div class="modal fade" id="editRouteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Ruta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editRouteForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_route">
                        <input type="hidden" name="id" id="edit_route_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_route_name" class="form-label">Nombre de la Ruta *</label>
                                    <input type="text" class="form-control" id="edit_route_name" name="route_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_route_code" class="form-label">Código de Ruta *</label>
                                    <input type="text" class="form-control" id="edit_route_code" name="route_code" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_estimated_time_minutes" class="form-label">Tiempo Estimado (minutos) *</label>
                                    <input type="number" class="form-control" id="edit_estimated_time_minutes" 
                                           name="estimated_time_minutes" min="5" max="180" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_max_orders_per_trip" class="form-label">Máximo Órdenes por Viaje *</label>
                                    <input type="number" class="form-control" id="edit_max_orders_per_trip" 
                                           name="max_orders_per_trip" min="1" max="20" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_coverage_area" class="form-label">Área de Cobertura</label>
                            <textarea class="form-control" id="edit_coverage_area" name="coverage_area" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Actualizar Ruta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Route Details Modal -->
    <div class="modal fade" id="routeDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>Detalles de la Ruta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="routeDetailsContent">
                    <!-- Content will be loaded by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit route function
        function editRoute(route) {
            document.getElementById('edit_route_id').value = route.id;
            document.getElementById('edit_route_name').value = route.route_name;
            document.getElementById('edit_route_code').value = route.route_code;
            document.getElementById('edit_description').value = route.description || '';
            document.getElementById('edit_estimated_time_minutes').value = route.estimated_time_minutes;
            document.getElementById('edit_max_orders_per_trip').value = route.max_orders_per_trip;
            document.getElementById('edit_coverage_area').value = route.coverage_area || '';
            
            const modal = new bootstrap.Modal(document.getElementById('editRouteModal'));
            modal.show();
        }

        // View route details function
        function viewRouteDetails(route) {
            const modal = new bootstrap.Modal(document.getElementById('routeDetailsModal'));
            const content = document.getElementById('routeDetailsContent');
            
            const successRate = route.total_deliveries > 0 ? 
                ((route.successful_deliveries / route.total_deliveries) * 100).toFixed(1) : 0;
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Información General</h6>
                        <table class="table table-borderless table-sm">
                            <tr><td><strong>Código:</strong></td><td>${route.route_code}</td></tr>
                            <tr><td><strong>Nombre:</strong></td><td>${route.route_name}</td></tr>
                            <tr><td><strong>Estado:</strong></td><td><span class="badge bg-${route.is_active ? 'success' : 'secondary'}">${route.is_active ? 'Activa' : 'Inactiva'}</span></td></tr>
                            <tr><td><strong>Tiempo Estimado:</strong></td><td>${route.estimated_time_minutes} minutos</td></tr>
                            <tr><td><strong>Capacidad:</strong></td><td>${route.max_orders_per_trip} órdenes</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Estadísticas de Rendimiento</h6>
                        <table class="table table-borderless table-sm">
                            <tr><td><strong>Total Deliveries:</strong></td><td>${route.total_deliveries}</td></tr>
                            <tr><td><strong>Exitosos:</strong></td><td>${route.successful_deliveries}</td></tr>
                            <tr><td><strong>Tasa de Éxito:</strong></td><td>${successRate}%</td></tr>
                            <tr><td><strong>Tiempo Real Promedio:</strong></td><td>${route.avg_delivery_time ? Math.round(route.avg_delivery_time) + ' min' : 'N/A'}</td></tr>
                            <tr><td><strong>Eficiencia:</strong></td><td>
                                ${route.avg_delivery_time ? 
                                    (route.avg_delivery_time <= route.estimated_time_minutes ? 
                                        '<span class="text-success">Eficiente</span>' : 
                                        '<span class="text-warning">Puede mejorar</span>') : 
                                    'Sin datos'}
                            </td></tr>
                        </table>
                    </div>
                </div>
                ${route.description ? `<div class="mt-3"><h6>Descripción</h6><p class="text-muted">${route.description}</p></div>` : ''}
                ${route.coverage_area ? `<div class="mt-3"><h6>Área de Cobertura</h6><p class="text-muted">${route.coverage_area}</p></div>` : ''}
            `;
            
            modal.show();
        }
    </script>

    <style>
        .border-left-primary {
            border-left: 4px solid #007bff !important;
        }
        
        .border-left-success {
            border-left: 4px solid #28a745 !important;
        }
        
        .border-left-info {
            border-left: 4px solid #17a2b8 !important;
        }
        
        .border-left-warning {
            border-left: 4px solid #ffc107 !important;
        }
    </style>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
