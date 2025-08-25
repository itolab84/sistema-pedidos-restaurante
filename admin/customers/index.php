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
                
                $validStatuses = ['new', 'regular', 'vip', 'inactive'];
                if (in_array($newStatus, $validStatuses)) {
                    $db->update('customers', 
                        ['customer_status' => $newStatus], 
                        'id = ?', 
                        [$id]
                    );
                    
                    $message = 'Estado del cliente actualizado correctamente';
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
                        case 'mark_regular':
                            $db->query(
                                "UPDATE customers SET customer_status = 'regular' WHERE id IN ($placeholders)",
                                $selectedIds
                            );
                            $message = count($selectedIds) . ' clientes marcados como regulares';
                            break;
                            
                        case 'mark_vip':
                            $db->query(
                                "UPDATE customers SET customer_status = 'vip' WHERE id IN ($placeholders)",
                                $selectedIds
                            );
                            $message = count($selectedIds) . ' clientes marcados como VIP';
                            break;
                            
                        case 'deactivate':
                            $db->query(
                                "UPDATE customers SET status = 'inactive' WHERE id IN ($placeholders)",
                                $selectedIds
                            );
                            $message = count($selectedIds) . ' clientes desactivados';
                            break;
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$customerStatusFilter = $_GET['customer_status'] ?? '';
$searchFilter = $_GET['search'] ?? '';
$dateFromFilter = $_GET['date_from'] ?? '';
$dateToFilter = $_GET['date_to'] ?? '';

// Build query conditions
$conditions = ['c.status = ?'];
$params = ['active'];

if ($statusFilter) {
    switch ($statusFilter) {
        case 'active':
            $conditions[] = "c.last_order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'inactive':
            $conditions[] = "c.last_order_date < DATE_SUB(NOW(), INTERVAL 30 DAY) AND c.last_order_date IS NOT NULL";
            break;
        case 'never_ordered':
            $conditions[] = "c.last_order_date IS NULL";
            break;
    }
}

if ($customerStatusFilter) {
    $conditions[] = "c.customer_status = ?";
    $params[] = $customerStatusFilter;
}

if ($searchFilter) {
    $conditions[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.customer_code LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
}

if ($dateFromFilter) {
    $conditions[] = "DATE(c.registration_date) >= ?";
    $params[] = $dateFromFilter;
}

if ($dateToFilter) {
    $conditions[] = "DATE(c.registration_date) <= ?";
    $params[] = $dateToFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

// Get customers with statistics
$customers = $db->fetchAll(
    "SELECT c.*, 
            (SELECT COUNT(*) FROM customer_phones cp WHERE cp.customer_id = c.id) as phone_count,
            (SELECT COUNT(*) FROM customer_addresses ca WHERE ca.customer_id = c.id) as address_count,
            (SELECT COUNT(*) FROM customer_notes cn WHERE cn.customer_id = c.id) as notes_count,
            CASE 
                WHEN c.last_order_date IS NULL THEN 'Nunca'
                WHEN DATEDIFF(NOW(), c.last_order_date) <= 30 THEN 'Activo'
                WHEN DATEDIFF(NOW(), c.last_order_date) <= 90 THEN 'Inactivo'
                ELSE 'Inactivo'
            END as activity_status,
            DATEDIFF(NOW(), c.last_order_date) as days_since_last_order
     FROM customers c
     $whereClause
     ORDER BY c.last_order_date DESC, c.registration_date DESC",
    $params
);

// Get statistics
$stats = [
    'total_customers' => $db->fetchOne("SELECT COUNT(*) as count FROM customers WHERE status = 'active'")['count'] ?? 0,
    'new_customers' => $db->fetchOne("SELECT COUNT(*) as count FROM customers WHERE customer_status = 'new' AND status = 'active'")['count'] ?? 0,
    'regular_customers' => $db->fetchOne("SELECT COUNT(*) as count FROM customers WHERE customer_status = 'regular' AND status = 'active'")['count'] ?? 0,
    'vip_customers' => $db->fetchOne("SELECT COUNT(*) as count FROM customers WHERE customer_status = 'vip' AND status = 'active'")['count'] ?? 0,
    'active_customers' => $db->fetchOne("SELECT COUNT(*) as count FROM customers WHERE last_order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status = 'active'")['count'] ?? 0,
    'total_revenue' => $db->fetchOne("SELECT COALESCE(SUM(total_spent), 0) as total FROM customers WHERE status = 'active'")['total'] ?? 0
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Administración</title>
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
                            <i class="fas fa-users me-2 text-primary"></i>
                            Administración de Clientes
                        </h2>
                        <p class="text-muted mb-0">
                            Gestiona la información completa de tus clientes y su historial de fidelización
                        </p>
                    </div>
                    <div>
                        <a href="create.php" class="btn btn-primary me-2">
                            <i class="fas fa-plus me-2"></i>
                            Nuevo Cliente
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
                                    Total Clientes
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['total_customers']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                    Nuevos
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['new_customers']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-plus fa-2x text-gray-300"></i>
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
                                    Regulares
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['regular_customers']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-check fa-2x text-gray-300"></i>
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
                                    VIP
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['vip_customers']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-crown fa-2x text-gray-300"></i>
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
                                    Activos (30d)
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['active_customers']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-clock fa-2x text-gray-300"></i>
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
                                    Ingresos Total
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?= number_format($stats['total_revenue'], 2) ?>
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
                            <div class="col-md-2">
                                <label for="status" class="form-label">Actividad</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Todos</option>
                                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Activos (30d)</option>
                                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactivos</option>
                                    <option value="never_ordered" <?= $statusFilter === 'never_ordered' ? 'selected' : '' ?>>Sin órdenes</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="customer_status" class="form-label">Tipo Cliente</label>
                                <select class="form-select" id="customer_status" name="customer_status">
                                    <option value="">Todos los tipos</option>
                                    <option value="new" <?= $customerStatusFilter === 'new' ? 'selected' : '' ?>>Nuevo</option>
                                    <option value="regular" <?= $customerStatusFilter === 'regular' ? 'selected' : '' ?>>Regular</option>
                                    <option value="vip" <?= $customerStatusFilter === 'vip' ? 'selected' : '' ?>>VIP</option>
                                    <option value="inactive" <?= $customerStatusFilter === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Desde</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFromFilter) ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Hasta</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars($dateToFilter) ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Buscar</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($searchFilter) ?>" 
                                       placeholder="Nombre, email o código">
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

        <!-- Customers Table -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    Lista de Clientes
                                    <?php if ($statusFilter || $customerStatusFilter || $searchFilter || $dateFromFilter || $dateToFilter): ?>
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
                                            <option value="mark_regular">Marcar como Regular</option>
                                            <option value="mark_vip">Marcar como VIP</option>
                                            <option value="deactivate">Desactivar</option>
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
                        <?php if (empty($customers)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay clientes</h5>
                                <p class="text-muted">
                                    <?php if ($statusFilter || $customerStatusFilter || $searchFilter): ?>
                                        No se encontraron clientes con los filtros aplicados
                                    <?php else: ?>
                                        Aún no se han registrado clientes
                                    <?php endif; ?>
                                </p>
                                <a href="create.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Agregar Primer Cliente
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" id="selectAll" class="form-check-input">
                                            </th>
                                            <th>Cliente</th>
                                            <th>Contacto</th>
                                            <th>Estadísticas</th>
                                            <th>Estado</th>
                                            <th>Actividad</th>
                                            <th>Registro</th>
                                            <th width="150">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customers as $customer): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_ids[]" 
                                                           value="<?= $customer['id'] ?>" 
                                                           class="form-check-input customer-checkbox">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle me-3">
                                                            <?= strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)) ?>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0">
                                                                <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>
                                                            </h6>
                                                            <small class="text-muted">
                                                                <?= htmlspecialchars($customer['customer_code']) ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <?php if ($customer['email']): ?>
                                                            <small class="d-block">
                                                                <i class="fas fa-envelope me-1"></i>
                                                                <?= htmlspecialchars($customer['email']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-phone me-1"></i>
                                                            <?= $customer['phone_count'] ?> teléfono(s)
                                                        </small>
                                                        <small class="text-muted d-block">
                                                            <i class="fas fa-map-marker-alt me-1"></i>
                                                            <?= $customer['address_count'] ?> dirección(es)
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <small class="d-block">
                                                            <strong><?= $customer['total_orders'] ?></strong> órdenes
                                                        </small>
                                                        <small class="d-block text-success">
                                                            <strong>$<?= number_format($customer['total_spent'], 2) ?></strong>
                                                        </small>
                                                        <?php if ($customer['notes_count'] > 0): ?>
                                                            <small class="text-info">
                                                                <i class="fas fa-sticky-note me-1"></i>
                                                                <?= $customer['notes_count'] ?> nota(s)
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="id" value="<?= $customer['id'] ?>">
                                                        <select name="new_status" class="form-select form-select-sm" 
                                                                onchange="this.form.submit()" 
                                                                style="width: auto;">
                                                            <option value="new" <?= $customer['customer_status'] === 'new' ? 'selected' : '' ?>>Nuevo</option>
                                                            <option value="regular" <?= $customer['customer_status'] === 'regular' ? 'selected' : '' ?>>Regular</option>
                                                            <option value="vip" <?= $customer['customer_status'] === 'vip' ? 'selected' : '' ?>>VIP</option>
                                                            <option value="inactive" <?= $customer['customer_status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $customer['activity_status'] === 'Activo' ? 'success' : 
                                                        ($customer['activity_status'] === 'Nunca' ? 'secondary' : 'warning') 
                                                    ?>">
                                                        <?= $customer['activity_status'] ?>
                                                    </span>
                                                    <?php if ($customer['last_order_date']): ?>
                                                        <small class="d-block text-muted">
                                                            <?= $customer['days_since_last_order'] ?> días
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= date('d/m/Y', strtotime($customer['registration_date'])) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view.php?id=<?= $customer['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Ver perfil">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit.php?id=<?= $customer['id'] ?>" 
                                                           class="btn btn-sm btn-outline-success" 
                                                           title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="addresses.php?id=<?= $customer['id'] ?>" 
                                                           class="btn btn-sm btn-outline-info" 
                                                           title="Direcciones">
                                                            <i class="fas fa-map-marker-alt"></i>
                                                        </a>
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
            const checkboxes = document.querySelectorAll('.customer-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all when individual checkboxes change
        document.querySelectorAll('.customer-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.customer-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.customer-checkbox:checked');
                const selectAll = document.getElementById('selectAll');
                
                selectAll.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAll.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            });
        });

        // Bulk actions form submission
        document.getElementById('bulkForm').addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.customer-checkbox:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Por favor selecciona al menos un cliente');
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
    </script>

    <style>
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
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
