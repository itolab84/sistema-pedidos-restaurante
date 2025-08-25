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
            case 'update_status':
                $id = (int)$_POST['id'];
                $newStatus = $_POST['new_status'];
                
                $validStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'];
                if (in_array($newStatus, $validStatuses)) {
                    try {
                        // Get current status
                        $currentOrder = $db->fetchOne("SELECT status FROM orders WHERE id = ?", [$id]);
                        $currentStatus = $currentOrder['status'] ?? 'pending';
                        
                        // Start transaction
                        $db->query("START TRANSACTION");
                        
                        // Update order status
                        $db->update('orders', 
                            ['status' => $newStatus], 
                            'id = ?', 
                            [$id]
                        );
                        
                        // Insert status history
                        $db->insert('order_status_history', [
                            'order_id' => $id,
                            'status' => $newStatus,
                            'previous_status' => $currentStatus,
                            'changed_by' => $user['username'],
                            'notes' => 'Actualizado desde lista de órdenes'
                        ]);
                        
                        // Commit transaction
                        $db->query("COMMIT");
                        
                        $message = 'Estado de la orden actualizado correctamente';
                    } catch (Exception $e) {
                        $db->query("ROLLBACK");
                        $message = 'Error al actualizar el estado: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Estado no válido';
                    $messageType = 'danger';
                }
                break;
                
            case 'bulk_action':
                $selectedIds = $_POST['selected_ids'] ?? [];
                $bulkAction = $_POST['bulk_action_type'];
                
                if (!empty($selectedIds)) {
                    $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
                    
                    switch ($bulkAction) {
                        case 'mark_processing':
                            try {
                                $db->query("START TRANSACTION");
                                
                                // Update orders
                                $db->query(
                                    "UPDATE orders SET status = 'preparing' WHERE id IN ($placeholders)",
                                    $selectedIds
                                );
                                
                                // Insert history for each order
                                foreach ($selectedIds as $orderId) {
                                    $currentOrder = $db->fetchOne("SELECT status FROM orders WHERE id = ?", [$orderId]);
                                    $db->insert('order_status_history', [
                                        'order_id' => $orderId,
                                        'status' => 'preparing',
                                        'previous_status' => $currentOrder['status'] ?? 'pending',
                                        'changed_by' => $user['username'],
                                        'notes' => 'Actualización masiva - marcado como preparando'
                                    ]);
                                }
                                
                                $db->query("COMMIT");
                                $message = count($selectedIds) . ' órdenes marcadas como preparando';
                            } catch (Exception $e) {
                                $db->query("ROLLBACK");
                                $message = 'Error en actualización masiva: ' . $e->getMessage();
                                $messageType = 'danger';
                            }
                            break;
                            
                        case 'mark_completed':
                            try {
                                $db->query("START TRANSACTION");
                                
                                // Update orders
                                $db->query(
                                    "UPDATE orders SET status = 'delivered' WHERE id IN ($placeholders)",
                                    $selectedIds
                                );
                                
                                // Insert history for each order
                                foreach ($selectedIds as $orderId) {
                                    $currentOrder = $db->fetchOne("SELECT status FROM orders WHERE id = ?", [$orderId]);
                                    $db->insert('order_status_history', [
                                        'order_id' => $orderId,
                                        'status' => 'delivered',
                                        'previous_status' => $currentOrder['status'] ?? 'pending',
                                        'changed_by' => $user['username'],
                                        'notes' => 'Actualización masiva - marcado como entregado'
                                    ]);
                                }
                                
                                $db->query("COMMIT");
                                $message = count($selectedIds) . ' órdenes marcadas como entregadas';
                            } catch (Exception $e) {
                                $db->query("ROLLBACK");
                                $message = 'Error en actualización masiva: ' . $e->getMessage();
                                $messageType = 'danger';
                            }
                            break;
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$searchFilter = $_GET['search'] ?? '';

// Build query conditions
$conditions = [];
$params = [];

if ($statusFilter) {
    $conditions[] = "o.status = ?";
    $params[] = $statusFilter;
}

if ($dateFilter) {
    $conditions[] = "DATE(o.created_at) = ?";
    $params[] = $dateFilter;
}

if ($searchFilter) {
    $conditions[] = "(o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.id = ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $params[] = is_numeric($searchFilter) ? $searchFilter : 0;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get orders with details
$orders = $db->fetchAll(
    "SELECT o.*, 
            COUNT(oi.id) as item_count,
            GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ') as items_summary
     FROM orders o
     LEFT JOIN order_items oi ON o.id = oi.order_id
     LEFT JOIN products p ON oi.product_id = p.id
     $whereClause
     GROUP BY o.id
     ORDER BY o.created_at DESC",
    $params
);

// Get statistics
$stats = [
    'total_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders")['count'] ?? 0,
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")['count'] ?? 0,
    'processing_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status IN ('confirmed', 'preparing', 'ready')")['count'] ?? 0,
    'completed_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'delivered'")['count'] ?? 0,
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()")['count'] ?? 0,
    'today_revenue' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'")['total'] ?? 0
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Órdenes - Administración</title>
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
                            <i class="fas fa-shopping-cart me-2 text-primary"></i>
                            Gestión de Órdenes
                        </h2>
                        <p class="text-muted mb-0">
                            Administra y da seguimiento a las órdenes del restaurante
                        </p>
                    </div>
                    <div>
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
                                    Total Órdenes
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['total_orders'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
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
                                    Pendientes
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['pending_orders'] ?>
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
                                    En Proceso
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['processing_orders'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-cog fa-2x text-gray-300"></i>
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
                                    Completadas
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['completed_orders'] ?>
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
                <div class="card border-left-primary shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Hoy
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['today_orders'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
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
                                    Ingresos Hoy
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?= number_format($stats['today_revenue'], 2) ?>
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

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Estado</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Todos los estados</option>
                                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                                    <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmado</option>
                                    <option value="preparing" <?= $statusFilter === 'preparing' ? 'selected' : '' ?>>Preparando</option>
                                    <option value="ready" <?= $statusFilter === 'ready' ? 'selected' : '' ?>>Listo</option>
                                    <option value="out_for_delivery" <?= $statusFilter === 'out_for_delivery' ? 'selected' : '' ?>>En Camino</option>
                                    <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Entregado</option>
                                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date" class="form-label">Fecha</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Buscar</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($searchFilter) ?>" 
                                       placeholder="ID, nombre o email del cliente">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filtrar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    Lista de Órdenes
                                    <?php if ($statusFilter || $dateFilter || $searchFilter): ?>
                                        <small class="text-muted">(Filtrado)</small>
                                        <a href="index.php" class="btn btn-sm btn-outline-secondary ms-2">
                                            <i class="fas fa-times me-1"></i>Limpiar
                                        </a>
                                    <?php endif; ?>
                                </h6>
                            </div>
                            <div class="col-auto">
                                <form method="POST" class="d-inline" id="bulkForm">
                                    <input type="hidden" name="action" value="bulk_action">
                                    <div class="input-group input-group-sm">
                                        <select name="bulk_action_type" class="form-select" required>
                                            <option value="">Acciones masivas</option>
                                            <option value="mark_processing">Marcar preparando</option>
                                            <option value="mark_completed">Marcar entregadas</option>
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
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay órdenes</h5>
                                <p class="text-muted">
                                    <?php if ($statusFilter || $dateFilter || $searchFilter): ?>
                                        No se encontraron órdenes con los filtros aplicados
                                    <?php else: ?>
                                        Aún no se han realizado órdenes
                                    <?php endif; ?>
                                </p>
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
                                            <th>Productos</th>
                                            <th>Total</th>
                                            <th>Pago</th>
                                            <th>Estado</th>
                                            <th>Fecha</th>
                                            <th width="150">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_ids[]" 
                                                           value="<?= $order['id'] ?>" 
                                                           class="form-check-input order-checkbox">
                                                </td>
                                                <td>
                                                    <div>
                                                        <h6 class="mb-0">#<?= $order['id'] ?></h6>
                                                        <small class="text-muted">
                                                            <?= $order['item_count'] ?> producto(s)
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($order['customer_name']) ?></h6>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($order['customer_email']) ?>
                                                            <?php if ($order['customer_phone']): ?>
                                                                <br><?= htmlspecialchars($order['customer_phone']) ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small class="text-muted" title="<?= htmlspecialchars($order['items_summary']) ?>">
                                                        <?= htmlspecialchars(substr($order['items_summary'], 0, 50)) ?>
                                                        <?= strlen($order['items_summary']) > 50 ? '...' : '' ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-success">
                                                        $<?= number_format($order['total_amount'], 2) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="id" value="<?= $order['id'] ?>">
                                                        <select name="new_status" class="form-select form-select-sm" 
                                                                onchange="this.form.submit()" 
                                                                style="width: auto;">
                                                            <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                                                            <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmado</option>
                                                            <option value="preparing" <?= $order['status'] === 'preparing' ? 'selected' : '' ?>>Preparando</option>
                                                            <option value="ready" <?= $order['status'] === 'ready' ? 'selected' : '' ?>>Listo</option>
                                                            <option value="out_for_delivery" <?= $order['status'] === 'out_for_delivery' ? 'selected' : '' ?>>En Camino</option>
                                                            <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Entregado</option>
                                                            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= date('d/m/Y', strtotime($order['created_at'])) ?>
                                                        <br>
                                                        <?= date('H:i', strtotime($order['created_at'])) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view.php?id=<?= $order['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Ver detalles">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit.php?id=<?= $order['id'] ?>" 
                                                           class="btn btn-sm btn-outline-warning" 
                                                           title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                title="Imprimir" 
                                                                onclick="printOrder(<?= $order['id'] ?>)">
                                                            <i class="fas fa-print"></i>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all when individual checkboxes change
        document.querySelectorAll('.order-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.order-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
                const selectAll = document.getElementById('selectAll');
                
                selectAll.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAll.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            });
        });

        // Bulk actions form submission
        document.getElementById('bulkForm').addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.order-checkbox:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Por favor selecciona al menos una orden');
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

        // Print order function
        function printOrder(orderId) {
            window.open('print.php?id=' + orderId, '_blank', 'width=800,height=600');
        }

        // Auto-refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
