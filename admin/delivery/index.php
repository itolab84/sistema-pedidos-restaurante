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
            case 'update_delivery_status':
                $id = (int)$_POST['id'];
                $newStatus = $_POST['new_status'];
                
                $validStatuses = ['assigned', 'picked_up', 'in_transit', 'delivered', 'failed', 'returned'];
                if (in_array($newStatus, $validStatuses)) {
                    $updateData = ['delivery_status' => $newStatus];
                    
                    // Set timestamps based on status
                    if ($newStatus === 'picked_up' && !$_POST['current_pickup_time']) {
                        $updateData['pickup_time'] = date('Y-m-d H:i:s');
                    } elseif ($newStatus === 'delivered' && !$_POST['current_delivery_time']) {
                        $updateData['delivery_time'] = date('Y-m-d H:i:s');
                    }
                    
                    $db->update('delivery_assignments', $updateData, 'id = ?', [$id]);
                    $message = 'Estado de delivery actualizado correctamente';
                } else {
                    $message = 'Estado no válido';
                    $messageType = 'danger';
                }
                break;
                
            case 'assign_delivery':
                try {
                    $orderId = (int)$_POST['order_id'];
                    $employeeId = (int)$_POST['delivery_employee_id'];
                    $routeId = $_POST['route_id'] ? (int)$_POST['route_id'] : null;
                    $deliveryAddress = $_POST['delivery_address'];
                    $customerPhone = $_POST['customer_phone'] ?: null;
                    $deliveryNotes = $_POST['delivery_notes'] ?: null;
                    $deliveryFee = $_POST['delivery_fee'] ? (float)$_POST['delivery_fee'] : 0;
                    
                    $assignmentData = [
                        'order_id' => $orderId,
                        'delivery_employee_id' => $employeeId,
                        'route_id' => $routeId,
                        'delivery_address' => $deliveryAddress,
                        'customer_phone' => $customerPhone,
                        'delivery_notes' => $deliveryNotes,
                        'delivery_fee' => $deliveryFee,
                        'delivery_status' => 'assigned'
                    ];
                    
                    $db->insert('delivery_assignments', $assignmentData);
                    $message = 'Delivery asignado exitosamente';
                } catch (Exception $e) {
                    $message = 'Error al asignar delivery: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'bulk_assign_route':
                $selectedIds = $_POST['selected_ids'] ?? [];
                $routeId = $_POST['bulk_route_id'];
                
                if (!empty($selectedIds) && $routeId) {
                    $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
                    $params = array_merge([$routeId], $selectedIds);
                    
                    $db->query(
                        "UPDATE delivery_assignments SET route_id = ? WHERE id IN ($placeholders)",
                        $params
                    );
                    $message = count($selectedIds) . ' deliveries asignados a la ruta';
                }
                break;
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$employeeFilter = $_GET['employee'] ?? '';
$routeFilter = $_GET['route'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$searchFilter = $_GET['search'] ?? '';

// Build query conditions
$conditions = [];
$params = [];

if ($statusFilter) {
    $conditions[] = "da.delivery_status = ?";
    $params[] = $statusFilter;
}

if ($employeeFilter) {
    $conditions[] = "da.delivery_employee_id = ?";
    $params[] = $employeeFilter;
}

if ($routeFilter) {
    $conditions[] = "da.route_id = ?";
    $params[] = $routeFilter;
}

if ($dateFilter) {
    $conditions[] = "DATE(da.assigned_at) = ?";
    $params[] = $dateFilter;
}

if ($searchFilter) {
    $conditions[] = "(o.customer_name LIKE ? OR da.delivery_address LIKE ? OR o.id = ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $params[] = is_numeric($searchFilter) ? $searchFilter : 0;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get delivery assignments with details
$deliveries = $db->fetchAll(
    "SELECT da.*, 
            o.customer_name, 
            o.customer_email,
            o.total_amount as order_total,
            o.status as order_status,
            CONCAT(e.first_name, ' ', e.last_name) as delivery_person,
            e.phone as delivery_phone,
            dr.route_name,
            dr.route_code,
            CASE 
                WHEN da.delivery_time IS NOT NULL AND da.pickup_time IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, da.pickup_time, da.delivery_time) 
                ELSE NULL 
            END as delivery_time_minutes
     FROM delivery_assignments da
     INNER JOIN orders o ON da.order_id = o.id
     INNER JOIN employees e ON da.delivery_employee_id = e.id
     LEFT JOIN delivery_routes dr ON da.route_id = dr.id
     $whereClause
     ORDER BY da.assigned_at DESC",
    $params
);

// Get statistics
$stats = [
    'total_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM delivery_assignments")['count'] ?? 0,
    'assigned_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM delivery_assignments WHERE delivery_status = 'assigned'")['count'] ?? 0,
    'in_transit_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM delivery_assignments WHERE delivery_status IN ('picked_up', 'in_transit')")['count'] ?? 0,
    'delivered_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM delivery_assignments WHERE delivery_status = 'delivered'")['count'] ?? 0,
    'failed_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM delivery_assignments WHERE delivery_status IN ('failed', 'returned')")['count'] ?? 0,
    'today_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM delivery_assignments WHERE DATE(assigned_at) = CURDATE()")['count'] ?? 0,
    'avg_delivery_time' => $db->fetchOne("
        SELECT AVG(TIMESTAMPDIFF(MINUTE, pickup_time, delivery_time)) as avg_time 
        FROM delivery_assignments 
        WHERE delivery_status = 'delivered' AND pickup_time IS NOT NULL AND delivery_time IS NOT NULL
    ")['avg_time'] ?? 0
];

// Get delivery employees for filters and assignment
$deliveryEmployees = $db->fetchAll(
    "SELECT id, CONCAT(first_name, ' ', last_name) as full_name 
     FROM employees 
     WHERE position = 'delivery' AND status = 'active' 
     ORDER BY first_name, last_name"
);

// Get routes for filters and assignment
$routes = $db->fetchAll("SELECT * FROM delivery_routes WHERE is_active = 1 ORDER BY route_name");

// Get orders without delivery assignments for the assign modal
$ordersWithoutDelivery = $db->fetchAll(
    "SELECT o.id, o.customer_name, o.customer_phone, o.total_amount, o.created_at
     FROM orders o
     LEFT JOIN delivery_assignments da ON o.id = da.order_id
     WHERE da.id IS NULL AND o.status IN ('processing', 'completed')
     ORDER BY o.created_at DESC
     LIMIT 50"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery - Administración</title>
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
                            <i class="fas fa-truck me-2 text-primary"></i>
                            Administración de Delivery
                        </h2>
                        <p class="text-muted mb-0">
                            Gestiona rutas de delivery, asignación de repartidores y seguimiento de entregas
                        </p>
                    </div>
                    <div>
                        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#assignDeliveryModal">
                            <i class="fas fa-plus me-2"></i>
                            Asignar Delivery
                        </button>
                        <a href="routes.php" class="btn btn-info me-2">
                            <i class="fas fa-route me-2"></i>
                            Rutas
                        </a>
                        <a href="performance.php" class="btn btn-warning me-2">
                            <i class="fas fa-chart-line me-2"></i>
                            Rendimiento
                        </a>
                        <button class="btn btn-outline-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>
                            Actualizar
                        </button>
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
            <div class="col-md-2">
                <div class="card border-left-primary shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Deliveries
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['total_deliveries'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-truck fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-left-warning shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Asignados
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['assigned_deliveries'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-left-info shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    En Tránsito
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['in_transit_deliveries'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shipping-fast fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-left-success shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Entregados
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['delivered_deliveries'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-left-danger shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                    Fallidos
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['failed_deliveries'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-left-secondary shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                    Tiempo Promedio
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= round($stats['avg_delivery_time']) ?> min
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-stopwatch fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-2">
                                <label for="status" class="form-label">Estado</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Todos los estados</option>
                                    <option value="assigned" <?= $statusFilter === 'assigned' ? 'selected' : '' ?>>Asignado</option>
                                    <option value="picked_up" <?= $statusFilter === 'picked_up' ? 'selected' : '' ?>>Recogido</option>
                                    <option value="in_transit" <?= $statusFilter === 'in_transit' ? 'selected' : '' ?>>En Tránsito</option>
                                    <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Entregado</option>
                                    <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Fallido</option>
                                    <option value="returned" <?= $statusFilter === 'returned' ? 'selected' : '' ?>>Devuelto</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="employee" class="form-label">Repartidor</label>
                                <select class="form-select" id="employee" name="employee">
                                    <option value="">Todos los repartidores</option>
                                    <?php foreach ($deliveryEmployees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" <?= $employeeFilter == $emp['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($emp['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="route" class="form-label">Ruta</label>
                                <select class="form-select" id="route" name="route">
                                    <option value="">Todas las rutas</option>
                                    <?php foreach ($routes as $route): ?>
                                        <option value="<?= $route['id'] ?>" <?= $routeFilter == $route['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($route['route_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date" class="form-label">Fecha</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Buscar</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($searchFilter) ?>" 
                                       placeholder="Cliente, dirección, orden">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deliveries Table -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    Lista de Deliveries
                                    <?php if ($statusFilter || $employeeFilter || $routeFilter || $dateFilter || $searchFilter): ?>
                                        <small class="text-muted">(Filtrado)</small>
                                        <a href="index.php" class="btn btn-sm btn-outline-secondary ms-2">
                                            <i class="fas fa-times me-1"></i>Limpiar
                                        </a>
                                    <?php endif; ?>
                                </h6>
                            </div>
                            <div class="col-auto">
                                <form method="POST" class="d-inline" id="bulkForm">
                                    <input type="hidden" name="action" value="bulk_assign_route">
                                    <div class="input-group input-group-sm">
                                        <select name="bulk_route_id" class="form-select" required>
                                            <option value="">Asignar a ruta...</option>
                                            <?php foreach ($routes as $route): ?>
                                                <option value="<?= $route['id'] ?>">
                                                    <?= htmlspecialchars($route['route_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-outline-primary">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($deliveries)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-truck fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay deliveries registrados</h5>
                                <p class="text-muted">
                                    <?php if ($statusFilter || $employeeFilter || $searchFilter): ?>
                                        No se encontraron deliveries con los filtros aplicados
                                    <?php else: ?>
                                        Aún no se han asignado deliveries
                                    <?php endif; ?>
                                </p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignDeliveryModal">
                                    <i class="fas fa-plus me-2"></i>Asignar Primer Delivery
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" id="selectAll" class="form-check-input">
                                            </th>
                                            <th>Orden</th>
                                            <th>Cliente</th>
                                            <th>Repartidor</th>
                                            <th>Ruta</th>
                                            <th>Dirección</th>
                                            <th>Estado</th>
                                            <th>Tiempos</th>
                                            <th width="120">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deliveries as $delivery): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_ids[]" 
                                                           value="<?= $delivery['id'] ?>" 
                                                           class="form-check-input delivery-checkbox">
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong>Orden #<?= $delivery['order_id'] ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            $<?= number_format($delivery['order_total'], 2) ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($delivery['customer_name']) ?></strong>
                                                        <?php if ($delivery['customer_phone']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="fas fa-phone me-1"></i>
                                                                <?= htmlspecialchars($delivery['customer_phone']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($delivery['delivery_person']) ?></strong>
                                                        <?php if ($delivery['delivery_phone']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="fas fa-phone me-1"></i>
                                                                <?= htmlspecialchars($delivery['delivery_phone']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($delivery['route_name']): ?>
                                                        <span class="badge bg-info">
                                                            <?= htmlspecialchars($delivery['route_code']) ?>
                                                        </span>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($delivery['route_name']) ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin ruta</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small title="<?= htmlspecialchars($delivery['delivery_address']) ?>">
                                                        <?= htmlspecialchars(substr($delivery['delivery_address'], 0, 50)) ?>
                                                        <?= strlen($delivery['delivery_address']) > 50 ? '...' : '' ?>
                                                    </small>
                                                    <?php if ($delivery['delivery_fee'] > 0): ?>
                                                        <br>
                                                        <small class="text-success">
                                                            <i class="fas fa-dollar-sign me-1"></i>
                                                            <?= number_format($delivery['delivery_fee'], 2) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_delivery_status">
                                                        <input type="hidden" name="id" value="<?= $delivery['id'] ?>">
                                                        <input type="hidden" name="current_pickup_time" value="<?= $delivery['pickup_time'] ?>">
                                                        <input type="hidden" name="current_delivery_time" value="<?= $delivery['delivery_time'] ?>">
                                                        <select name="new_status" class="form-select form-select-sm" 
                                                                onchange="this.form.submit()" 
                                                                style="width: auto;">
                                                            <option value="assigned" <?= $delivery['delivery_status'] === 'assigned' ? 'selected' : '' ?>>Asignado</option>
                                                            <option value="picked_up" <?= $delivery['delivery_status'] === 'picked_up' ? 'selected' : '' ?>>Recogido</option>
                                                            <option value="in_transit" <?= $delivery['delivery_status'] === 'in_transit' ? 'selected' : '' ?>>En Tránsito</option>
                                                            <option value="delivered" <?= $delivery['delivery_status'] === 'delivered' ? 'selected' : '' ?>>Entregado</option>
                                                            <option value="failed" <?= $delivery['delivery_status'] === 'failed' ? 'selected' : '' ?>>Fallido</option>
                                                            <option value="returned" <?= $delivery['delivery_status'] === 'returned' ? 'selected' : '' ?>>Devuelto</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>
                                                    <div>
                                                        <small class="d-block">
                                                            <strong>Asignado:</strong>
                                                            <?= date('H:i', strtotime($delivery['assigned_at'])) ?>
                                                        </small>
                                                        <?php if ($delivery['pickup_time']): ?>
                                                            <small class="d-block text-info">
                                                                <strong>Recogido:</strong>
                                                                <?= date('H:i', strtotime($delivery['pickup_time'])) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if ($delivery['delivery_time']): ?>
                                                            <small class="d-block text-success">
                                                                <strong>Entregado:</strong>
                                                                <?= date('H:i', strtotime($delivery['delivery_time'])) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if ($delivery['delivery_time_minutes']): ?>
                                                            <small class="d-block text-muted">
                                                                <strong>Tiempo:</strong>
                                                                <?= $delivery['delivery_time_minutes'] ?> min
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="../orders/view.php?id=<?= $delivery['order_id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Ver orden">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                title="Ver detalles" 
                                                                onclick="showDeliveryDetails(<?= htmlspecialchars(json_encode($delivery)) ?>)">
                                                            <i class="fas fa-info-circle"></i>
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

    <!-- Assign Delivery Modal -->
    <div class="modal fade" id="assignDeliveryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Asignar Delivery
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_delivery">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="order_id" class="form-label">Orden *</label>
                                    <select class="form-select" id="order_id" name="order_id" required>
                                        <option value="">Seleccionar orden...</option>
                                        <?php foreach ($ordersWithoutDelivery as $order): ?>
                                            <option value="<?= $order['id'] ?>">
                                                Orden #<?= $order['id'] ?> - <?= htmlspecialchars($order['customer_name']) ?> 
                                                ($<?= number_format($order['total_amount'], 2) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Solo se muestran órdenes sin delivery asignado</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="delivery_employee_id" class="form-label">Repartidor *</label>
                                    <select class="form-select" id="delivery_employee_id" name="delivery_employee_id" required>
                                        <option value="">Seleccionar repartidor...</option>
                                        <?php foreach ($deliveryEmployees as $emp): ?>
                                            <option value="<?= $emp['id'] ?>">
                                                <?= htmlspecialchars($emp['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="route_id" class="form-label">Ruta</label>
                                    <select class="form-select" id="route_id" name="route_id">
                                        <option value="">Sin ruta específica</option>
                                        <?php foreach ($routes as $route): ?>
                                            <option value="<?= $route['id'] ?>">
                                                <?= htmlspecialchars($route['route_name']) ?> (<?= htmlspecialchars($route['route_code']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="delivery_fee" class="form-label">Costo de Delivery</label>
                                    <input type="number" class="form-control" id="delivery_fee" name="delivery_fee" 
                                           step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="delivery_address" class="form-label">Dirección de Entrega *</label>
                            <textarea class="form-control" id="delivery_address" name="delivery_address" rows="3" 
                                      placeholder="Dirección completa de entrega..." required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="customer_phone" class="form-label">Teléfono del Cliente</label>
                                    <input type="tel" class="form-control" id="customer_phone" name="customer_phone" 
                                           placeholder="Teléfono de contacto">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="delivery_notes" class="form-label">Notas de Entrega</label>
                            <textarea class="form-control" id="delivery_notes" name="delivery_notes" rows="3" 
                                      placeholder="Instrucciones especiales, referencias, etc..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Asignar Delivery
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delivery Details Modal -->
    <div class="modal fade" id="deliveryDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>Detalles del Delivery
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="deliveryDetailsContent">
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
        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.delivery-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all when individual checkboxes change
        document.querySelectorAll('.delivery-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.delivery-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.delivery-checkbox:checked');
                const selectAll = document.getElementById('selectAll');
                
                selectAll.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAll.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            });
        });

        // Bulk actions form submission
        document.getElementById('bulkForm').addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.delivery-checkbox:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Por favor selecciona al menos un delivery');
                return;
            }
            
            // Add selected IDs to form
            checkedBoxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_ids[]';
                input.value = checkbox.value;
                this.appendChild(input);
            });
        });

        // Show delivery details
        function showDeliveryDetails(delivery) {
            const modal = new bootstrap.Modal(document.getElementById('deliveryDetailsModal'));
            const content = document.getElementById('deliveryDetailsContent');
            
            const statusBadges = {
                'assigned': 'warning',
                'picked_up': 'info',
                'in_transit': 'primary',
                'delivered': 'success',
                'failed': 'danger',
                'returned': 'secondary'
            };
            
            const statusNames = {
                'assigned': 'Asignado',
                'picked_up': 'Recogido',
                'in_transit': 'En Tránsito',
                'delivered': 'Entregado',
                'failed': 'Fallido',
                'returned': 'Devuelto'
            };
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Información del Delivery</h6>
                        <table class="table table-borderless table-sm">
                            <tr><td><strong>ID:</strong></td><td>#${delivery.id}</td></tr>
                            <tr><td><strong>Estado:</strong></td><td><span class="badge bg-${statusBadges[delivery.delivery_status] || 'secondary'}">${statusNames[delivery.delivery_status] || delivery.delivery_status}</span></td></tr>
                            <tr><td><strong>Repartidor:</strong></td><td>${delivery.delivery_person}</td></tr>
                            <tr><td><strong>Ruta:</strong></td><td>${delivery.route_name || 'Sin ruta'}</td></tr>
                            <tr><td><strong>Costo:</strong></td><td>$${parseFloat(delivery.delivery_fee || 0).toFixed(2)}</td></tr>
                            <tr><td><strong>Propina:</strong></td><td>$${parseFloat(delivery.tip_amount || 0).toFixed(2)}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Información de la Orden</h6>
                        <table class="table table-borderless table-sm">
                            <tr><td><strong>Orden:</strong></td><td>#${delivery.order_id}</td></tr>
                            <tr><td><strong>Cliente:</strong></td><td>${delivery.customer_name}</td></tr>
                            <tr><td><strong>Total:</strong></td><td>$${parseFloat(delivery.order_total).toFixed(2)}</td></tr>
                            <tr><td><strong>Teléfono:</strong></td><td>${delivery.customer_phone || 'N/A'}</td></tr>
                            <tr><td><strong>Estado Orden:</strong></td><td>${delivery.order_status}</td></tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Dirección de Entrega</h6>
                        <p class="text-muted">${delivery.delivery_address}</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Tiempos</h6>
                        <table class="table table-borderless table-sm">
                            <tr><td><strong>Asignado:</strong></td><td>${new Date(delivery.assigned_at).toLocaleString('es-ES')}</td></tr>
                            <tr><td><strong>Recogido:</strong></td><td>${delivery.pickup_time ? new Date(delivery.pickup_time).toLocaleString('es-ES') : 'Pendiente'}</td></tr>
                            <tr><td><strong>Entregado:</strong></td><td>${delivery.delivery_time ? new Date(delivery.delivery_time).toLocaleString('es-ES') : 'Pendiente'}</td></tr>
                            ${delivery.delivery_time_minutes ? `<tr><td><strong>Tiempo Total:</strong></td><td>${delivery.delivery_time_minutes} minutos</td></tr>` : ''}
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Contacto del Repartidor</h6>
                        <table class="table table-borderless table-sm">
                            <tr><td><strong>Nombre:</strong></td><td>${delivery.delivery_person}</td></tr>
                            <tr><td><strong>Teléfono:</strong></td><td>${delivery.delivery_phone || 'N/A'}</td></tr>
                        </table>
                    </div>
                </div>
                ${delivery.delivery_notes ? `<div class="mt-3"><h6>Notas de Entrega</h6><p class="text-muted">${delivery.delivery_notes}</p></div>` : ''}
            `;
            
            modal.show();
        }

        // Auto-refresh every 30 seconds for real-time updates
        setTimeout(() => {
            location.reload();
        }, 30000);
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
        
        .border-left-danger {
            border-left: 4px solid #dc3545 !important;
        }
        
        .border-left-secondary {
            border-left: 4px solid #6c757d !important;
        }
    </style>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
