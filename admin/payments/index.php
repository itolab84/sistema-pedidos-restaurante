<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Get database connection for order_payments queries
require_once '../config/database.php';
$conn = getDBConnection();

// Handle actions
$message = '';
$messageType = 'success';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_payment_status':
                $id = (int)$_POST['id'];
                $newStatus = $_POST['new_status'];
                
                $validStatuses = ['pending', 'completed', 'failed', 'refunded'];
                if (in_array($newStatus, $validStatuses)) {
                    $db->update('payments', 
                        ['payment_status' => $newStatus], 
                        'id = ?', 
                        [$id]
                    );
                    
                    $message = 'Estado del pago actualizado correctamente';
                } else {
                    $message = 'Estado no válido';
                    $messageType = 'danger';
                }
                break;
                
            case 'add_payment':
                $orderId = (int)$_POST['order_id'];
                $amount = (float)$_POST['amount'];
                $paymentMethod = $_POST['payment_method'];
                $transactionId = $_POST['transaction_id'] ?: null;
                $notes = $_POST['notes'] ?: null;
                
                try {
                    $paymentData = [
                        'order_id' => $orderId,
                        'payment_method' => $paymentMethod,
                        'payment_status' => 'completed',
                        'amount' => $amount,
                        'transaction_id' => $transactionId,
                        'processed_by' => $user['id'],
                        'notes' => $notes
                    ];
                    
                    $db->insert('payments', $paymentData);
                    $message = 'Pago registrado exitosamente';
                } catch (Exception $e) {
                    $message = 'Error al registrar el pago: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Determine which view to show - DEFAULT TO LEGACY
$view = $_GET['view'] ?? 'legacy';

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$methodFilter = $_GET['method'] ?? '';
$dateFromFilter = $_GET['date_from'] ?? '';
$dateToFilter = $_GET['date_to'] ?? '';
$searchFilter = $_GET['search'] ?? '';
$orderIdFilter = $_GET['order_id'] ?? '';

if ($view === 'orders') {
    // ORDER PAYMENTS VIEW - New system
    
    // Pagination
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause for order_payments
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if ($orderIdFilter) {
        $where_conditions[] = "op.order_id = ?";
        $params[] = intval($orderIdFilter);
        $types .= 'i';
    }
    
    if (!empty($methodFilter)) {
        $where_conditions[] = "op.payment_method LIKE ?";
        $params[] = '%' . $methodFilter . '%';
        $types .= 's';
    }
    
    if (!empty($statusFilter)) {
        $where_conditions[] = "op.status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }
    
    if (!empty($dateFromFilter)) {
        $where_conditions[] = "DATE(op.created_at) >= ?";
        $params[] = $dateFromFilter;
        $types .= 's';
    }
    
    if (!empty($dateToFilter)) {
        $where_conditions[] = "DATE(op.created_at) <= ?";
        $params[] = $dateToFilter;
        $types .= 's';
    }
    
    if (!empty($searchFilter)) {
        $where_conditions[] = "(o.customer_name LIKE ? OR o.customer_email LIKE ? OR op.reference LIKE ?)";
        $params[] = '%' . $searchFilter . '%';
        $params[] = '%' . $searchFilter . '%';
        $params[] = '%' . $searchFilter . '%';
        $types .= 'sss';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Count total records
    $count_sql = "SELECT COUNT(*) as total FROM order_payments op LEFT JOIN orders o ON op.order_id = o.id $where_clause";
    $stmt = $conn->prepare($count_sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $count_result = $stmt->get_result();
    $total_records = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get order payments
    $sql = "SELECT 
                op.*,
                o.customer_name,
                o.customer_email,
                o.total_amount as order_total,
                o.created_at as order_date
            FROM order_payments op
            LEFT JOIN orders o ON op.order_id = o.id
            $where_clause
            ORDER BY op.created_at DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $order_payments = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['validation_data']) {
            $row['validation_data'] = json_decode($row['validation_data'], true);
        }
        $order_payments[] = $row;
    }
    
    // Get payment method options for order payments
    $methods_sql = "SELECT DISTINCT payment_method FROM order_payments ORDER BY payment_method";
    $methods_result = $conn->query($methods_sql);
    $order_payment_methods = [];
    while ($row = $methods_result->fetch_assoc()) {
        $order_payment_methods[] = $row['payment_method'];
    }
    
    // Get order payments statistics
    $order_stats = [
        'total_payments' => $conn->query("SELECT COUNT(*) as count FROM order_payments")->fetch_assoc()['count'] ?? 0,
        'electronic_payments' => $conn->query("SELECT COUNT(*) as count FROM order_payments WHERE payment_type = 'electronic'")->fetch_assoc()['count'] ?? 0,
        'cash_payments' => $conn->query("SELECT COUNT(*) as count FROM order_payments WHERE payment_type = 'cash'")->fetch_assoc()['count'] ?? 0,
        'paid_payments' => $conn->query("SELECT COUNT(*) as count FROM order_payments WHERE status = 'paid'")->fetch_assoc()['count'] ?? 0,
        'today_payments' => $conn->query("SELECT COUNT(*) as count FROM order_payments WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'] ?? 0,
        'today_revenue' => $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM order_payments WHERE DATE(created_at) = CURDATE() AND status = 'paid'")->fetch_assoc()['total'] ?? 0,
        'total_revenue' => $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM order_payments WHERE status = 'paid'")->fetch_assoc()['total'] ?? 0
    ];
    
} else {
    // LEGACY PAYMENTS VIEW - Original system (DEFAULT)
    
    // Build query conditions for legacy payments
    $conditions = [];
    $params = [];
    
    if ($statusFilter) {
        $conditions[] = "p.payment_status = ?";
        $params[] = $statusFilter;
    }
    
    if ($methodFilter) {
        $conditions[] = "p.payment_method = ?";
        $params[] = $methodFilter;
    }
    
    if ($dateFromFilter) {
        $conditions[] = "DATE(p.payment_date) >= ?";
        $params[] = $dateFromFilter;
    }
    
    if ($dateToFilter) {
        $conditions[] = "DATE(p.payment_date) <= ?";
        $params[] = $dateToFilter;
    }
    
    if ($searchFilter) {
        $conditions[] = "(o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.id = ? OR p.transaction_id LIKE ?)";
        $params[] = "%$searchFilter%";
        $params[] = "%$searchFilter%";
        $params[] = is_numeric($searchFilter) ? $searchFilter : 0;
        $params[] = "%$searchFilter%";
    }
    
    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    // Get legacy payments with order details
    $payments = $db->fetchAll(
        "SELECT p.*, 
                o.customer_name, 
                o.customer_email, 
                o.customer_phone,
                o.total_amount as order_total,
                o.status as order_status,
                o.created_at as order_date,
                CONCAT(e.first_name, ' ', e.last_name) as processed_by_name
         FROM payments p
         INNER JOIN orders o ON p.order_id = o.id
         LEFT JOIN employees e ON p.processed_by = e.id
         $whereClause
         ORDER BY p.payment_date DESC",
        $params
    );
    
    // Get legacy statistics
    $stats = [
        'total_payments' => $db->fetchOne("SELECT COUNT(*) as count FROM payments")['count'] ?? 0,
        'completed_payments' => $db->fetchOne("SELECT COUNT(*) as count FROM payments WHERE payment_status = 'completed'")['count'] ?? 0,
        'pending_payments' => $db->fetchOne("SELECT COUNT(*) as count FROM payments WHERE payment_status = 'pending'")['count'] ?? 0,
        'failed_payments' => $db->fetchOne("SELECT COUNT(*) as count FROM payments WHERE payment_status = 'failed'")['count'] ?? 0,
        'today_payments' => $db->fetchOne("SELECT COUNT(*) as count FROM payments WHERE DATE(payment_date) = CURDATE()")['count'] ?? 0,
        'today_revenue' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(payment_date) = CURDATE() AND payment_status = 'completed'")['total'] ?? 0,
        'total_revenue' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'completed'")['total'] ?? 0
    ];
    
    // Get orders without payments for the add payment modal
    $ordersWithoutPayments = $db->fetchAll(
        "SELECT o.id, o.customer_name, o.total_amount, o.created_at
         FROM orders o
         LEFT JOIN payments p ON o.id = p.order_id
         WHERE p.id IS NULL AND o.status != 'cancelled'
         ORDER BY o.created_at DESC
         LIMIT 50"
    );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagos - Administración</title>
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
                            <i class="fas fa-credit-card me-2 text-primary"></i>
                            Administración de Pagos
                        </h2>
                        <p class="text-muted mb-0">
                            Gestiona todos los pagos recibidos y su vinculación con las órdenes
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <!-- View Toggle -->
                        <div class="btn-group" role="group">
                            <a href="?view=legacy" class="btn btn-sm <?= $view === 'legacy' ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <i class="fas fa-list me-1"></i>Pagos Legacy
                            </a>
                            <a href="?view=orders" class="btn btn-sm <?= $view === 'orders' ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <i class="fas fa-shopping-cart me-1"></i>Pagos de Órdenes
                            </a>
                        </div>
                        
                        <?php if ($view === 'legacy'): ?>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                            <i class="fas fa-plus me-2"></i>
                            Registrar Pago
                        </button>
                        <?php endif; ?>
                        
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

        <?php if ($view === 'legacy'): ?>
            <!-- LEGACY PAYMENTS VIEW (DEFAULT) -->
            
            <!-- Legacy Statistics -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card border-left-primary shadow">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Pagos
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $stats['total_payments'] ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-credit-card fa-2x text-gray-300"></i>
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
                                        Completados
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $stats['completed_payments'] ?>
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
                    <div class="card border-left-warning shadow">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Pendientes
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $stats['pending_payments'] ?>
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
                    <div class="card border-left-danger shadow">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                        Fallidos
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $stats['failed_payments'] ?>
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
                    <div class="card border-left-info shadow">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Hoy
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $stats['today_payments'] ?>
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

            <!-- Legacy Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <input type="hidden" name="view" value="legacy">
                                <div class="col-md-2">
                                    <label for="status" class="form-label">Estado</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">Todos los estados</option>
                                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completado</option>
                                        <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Fallido</option>
                                        <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Reembolsado</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="method" class="form-label">Método</label>
                                    <select class="form-select" id="method" name="method">
                                        <option value="">Todos los métodos</option>
                                        <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Efectivo</option>
                                        <option value="card" <?= $methodFilter === 'card' ? 'selected' : '' ?>>Tarjeta</option>
                                        <option value="transfer" <?= $methodFilter === 'transfer' ? 'selected' : '' ?>>Transferencia</option>
                                        <option value="digital_wallet" <?= $methodFilter === 'digital_wallet' ? 'selected' : '' ?>>Billetera Digital</option>
                                        <option value="pagomovil" <?= $methodFilter === 'pagomovil' ? 'selected' : '' ?>>Pago Móvil</option>
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
                                           placeholder="Cliente, orden, transacción">
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

            <!-- Legacy Payments Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        Lista de Pagos
                                        <?php if ($statusFilter || $methodFilter || $dateFromFilter || $dateToFilter || $searchFilter): ?>
                                            <small class="text-muted">(Filtrado)</small>
                                            <a href="?view=legacy" class="btn btn-sm btn-outline-secondary ms-2">
                                                <i class="fas fa-times me-1"></i>Limpiar
                                            </a>
                                        <?php endif; ?>
                                    </h6>
                                </div>
                                <div class="col-auto">
                                    <div class="text-muted">
                                        <strong>Total Ingresos:</strong> $<?= number_format($stats['total_revenue'], 2) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($payments)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-credit-card fa-3x text-gray-300 mb-3"></i>
                                    <h5 class="text-gray-600">No hay pagos registrados</h5>
                                    <p class="text-muted">
                                        <?php if ($statusFilter || $methodFilter || $searchFilter): ?>
                                            No se encontraron pagos con los filtros aplicados
                                        <?php else: ?>
                                            Aún no se han registrado pagos
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Pago ID</th>
                                                <th>Orden</th>
                                                <th>Cliente</th>
                                                <th>Monto</th>
                                                <th>Método</th>
                                                <th>Estado</th>
                                                <th>Transacción</th>
                                                <th>Fecha</th>
                                                <th>Procesado por</th>
                                                <th width="120">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): ?>
                                                <tr>
                                                    <td>
                                                        <strong>#<?= $payment['id'] ?></strong>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong>Orden #<?= $payment['order_id'] ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                Total: $<?= number_format($payment['order_total'], 2) ?>
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?= htmlspecialchars($payment['customer_name']) ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?= htmlspecialchars($payment['customer_email']) ?>
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold text-success">
                                                            $<?= number_format($payment['amount'], 2) ?>
                                                        </span>
                                                        <?php if ($payment['amount'] != $payment['order_total']): ?>
                                                            <br>
                                                            <small class="text-warning">
                                                                <?php if ($payment['amount'] < $payment['order_total']): ?>
                                                                    Pago parcial
                                                                <?php else: ?>
                                                                    Sobrepago
                                                                <?php endif; ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?php
                                                            $methods = [
                                                                'cash' => 'Efectivo',
                                                                'card' => 'Tarjeta',
                                                                'transfer' => 'Transferencia',
                                                                'digital_wallet' => 'Billetera Digital',
                                                                'pagomovil' => 'Pago Móvil'
                                                            ];
                                                            echo $methods[$payment['payment_method']] ?? ucfirst($payment['payment_method']);
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_payment_status">
                                                            <input type="hidden" name="id" value="<?= $payment['id'] ?>">
                                                            <select name="new_status" class="form-select form-select-sm" 
                                                                    onchange="this.form.submit()" 
                                                                    style="width: auto;">
                                                                <option value="pending" <?= $payment['payment_status'] === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                                                                <option value="completed" <?= $payment['payment_status'] === 'completed' ? 'selected' : '' ?>>Completado</option>
                                                                <option value="failed" <?= $payment['payment_status'] === 'failed' ? 'selected' : '' ?>>Fallido</option>
                                                                <option value="refunded" <?= $payment['payment_status'] === 'refunded' ? 'selected' : '' ?>>Reembolsado</option>
                                                            </select>
                                                        </form>
                                                    </td>
                                                    <td>
                                                        <?php if ($payment['transaction_id']): ?>
                                                            <code><?= htmlspecialchars($payment['transaction_id']) ?></code>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= date('d/m/Y', strtotime($payment['payment_date'])) ?>
                                                            <br>
                                                            <?= date('H:i', strtotime($payment['payment_date'])) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($payment['processed_by_name'] ?? 'Sistema') ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    title="Ver detalles" 
                                                                    onclick="viewPaymentDetails(<?= $payment['id'] ?>)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-info" 
                                                                    title="Imprimir recibo" 
                                                                    onclick="printReceipt(<?= $payment['id'] ?>)">
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

        <?php else: ?>
            <!-- ORDER PAYMENTS VIEW - New system -->
            
            <!-- Order Payments Statistics -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card border-left-primary shadow">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Pagos
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $order_stats['total_payments'] ?>
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
                    <div class="card border-left-info shadow">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Electrónicos
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $order_stats['electronic_payments'] ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-mobile-alt fa-2x text-gray-300"></i>
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
                                        Efectivo
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $order_stats['cash_payments'] ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
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
                                        Pagados
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $order_stats['paid_payments'] ?>
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
                                        <?= $order_stats['today_payments'] ?>
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
                                        $<?= number_format($order_stats['today_revenue'], 2) ?>
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

            <!-- Order Payments Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <input type="hidden" name="view" value="orders">
                                <div class="col-md-2">
                                    <label for="order_id" class="form-label">ID Orden</label>
                                    <input type="number" class="form-control" id="order_id" name="order_id" 
                                           value="<?= htmlspecialchars($orderIdFilter) ?>" 
                                           placeholder="Número de orden">
                                </div>
                                <div class="col-md-2">
                                    <label for="status" class="form-label">Estado</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">Todos los estados</option>
                                        <option value="pending_validation" <?= $statusFilter === 'pending_validation' ? 'selected' : '' ?>>Pendiente Validación</option>
                                        <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Pagado</option>
                                        <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Fallido</option>
                                        <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Reembolsado</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="method" class="form-label">Método</label>
                                    <select class="form-select" id="method" name="method">
                                        <option value="">Todos los métodos</option>
                                        <?php foreach ($order_payment_methods as $method): ?>
                                            <option value="<?= htmlspecialchars($method) ?>" <?= $methodFilter === $method ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($method) ?>
                                            </option>
                                        <?php endforeach; ?>
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

            <!-- Order Payments Cards -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        Pagos de Órdenes
                                        <?php if ($orderIdFilter || $statusFilter || $methodFilter || $dateFromFilter || $dateToFilter || $searchFilter): ?>
                                            <small class="text-muted">(Filtrado)</small>
                                            <a href="?view=orders" class="btn btn-sm btn-outline-secondary ms-2">
                                                <i class="fas fa-times me-1"></i>Limpiar
                                            </a>
                                        <?php endif; ?>
                                    </h6>
                                </div>
                                <div class="col-auto">
                                    <div class="text-muted">
                                        <strong>Total Ingresos:</strong> $<?= number_format($order_stats['total_revenue'], 2) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($order_payments)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-shopping-cart fa-3x text-gray-300 mb-3"></i>
                                    <h5 class="text-gray-600">No hay pagos de órdenes</h5>
                                    <p class="text-muted">
                                        <?php if ($orderIdFilter || $statusFilter || $methodFilter || $searchFilter): ?>
                                            No se encontraron pagos con los filtros aplicados
                                        <?php else: ?>
                                            Aún no se han registrado pagos de órdenes
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($order_payments as $payment): ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="card h-100 border-left-<?= $payment['status'] === 'paid' ? 'success' : ($payment['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title mb-0">
                                                            Orden #<?= $payment['order_id'] ?>
                                                        </h6>
                                                        <span class="badge bg-<?= $payment['status'] === 'paid' ? 'success' : ($payment['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $payment['status'])) ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <strong class="text-success">$<?= number_format($payment['amount'], 2) ?></strong>
                                                        <small class="text-muted">/ $<?= number_format($payment['order_total'], 2) ?></small>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <span class="badge bg-info"><?= htmlspecialchars($payment['payment_method']) ?></span>
                                                        <span class="badge bg-secondary"><?= ucfirst($payment['payment_type']) ?></span>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <small class="text-muted">
                                                            <strong>Cliente:</strong> <?= htmlspecialchars($payment['customer_name']) ?>
                                                        </small>
                                                    </div>
                                                    
                                                    <?php if ($payment['reference']): ?>
                                                        <div class="mb-2">
                                                            <small class="text-muted">
                                                                <strong>Referencia:</strong> 
                                                                <code><?= htmlspecialchars($payment['reference']) ?></code>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($payment['validation_data']): ?>
                                                        <div class="mb-2">
                                                            <small class="text-success">
                                                                <i class="fas fa-check-circle"></i> Validado externamente
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="text-muted">
                                                        <small>
                                                            <?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="card-footer bg-transparent">
                                                    <div class="btn-group w-100" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="viewOrderPaymentDetails(<?= $payment['id'] ?>)"
                                                                title="Ver detalles">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                onclick="printOrderPayment(<?= $payment['id'] ?>)"
                                                                title="Imprimir recibo">
                                                            <i class="fas fa-print"></i>
                                                        </button>
                                                        <?php if ($payment['validation_data']): ?>
                                                            <button class="btn btn-sm btn-outline-info" 
                                                                    onclick="viewValidationData(<?= $payment['id'] ?>)"
                                                                    title="Ver validación">
                                                                <i class="fas fa-check-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Paginación de pagos">
                                        <ul class="pagination justify-content-center">
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?view=orders&page=<?= $i ?><?= $orderIdFilter ? '&order_id=' . $orderIdFilter : '' ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?><?= $methodFilter ? '&method=' . $methodFilter : '' ?><?= $dateFromFilter ? '&date_from=' . $dateFromFilter : '' ?><?= $dateToFilter ? '&date_to=' . $dateToFilter : '' ?><?= $searchFilter ? '&search=' . urlencode($searchFilter) : '' ?>">
                                                        <?= $i ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <!-- Add Payment Modal (Legacy only) -->
    <?php if ($view === 'legacy'): ?>
        <div class="modal fade" id="addPaymentModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Registrar Nuevo Pago</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_payment">
                            
                            <div class="mb-3">
                                <label for="order_id" class="form-label">Orden *</label>
                                <select class="form-select" name="order_id" required>
                                    <option value="">Seleccionar orden</option>
                                    <?php foreach ($ordersWithoutPayments as $order): ?>
                                        <option value="<?= $order['id'] ?>">
                                            Orden #<?= $order['id'] ?> - <?= htmlspecialchars($order['customer_name']) ?> 
                                            ($<?= number_format($order['total_amount'], 2) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="amount" class="form-label">Monto *</label>
                                <input type="number" class="form-control" name="amount" step="0.01" min="0" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="payment_method" class="form-label">Método de Pago *</label>
                                <select class="form-select" name="payment_method" required>
                                    <option value="">Seleccionar método</option>
                                    <option value="cash">Efectivo</option>
                                    <option value="card">Tarjeta</option>
                                    <option value="transfer">Transferencia</option>
                                    <option value="digital_wallet">Billetera Digital</option>
                                    <option value="pagomovil">Pago Móvil</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="transaction_id" class="form-label">ID de Transacción</label>
                                <input type="text" class="form-control" name="transaction_id" 
                                       placeholder="Opcional - ID de referencia">
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notas</label>
                                <textarea class="form-control" name="notes" rows="3" 
                                          placeholder="Notas adicionales sobre el pago"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">Registrar Pago</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Payment Details Modal -->
    <div class="modal fade" id="paymentDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-receipt me-2"></i>Detalles del Pago
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="paymentDetailsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando detalles del pago...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="printPaymentBtn" onclick="printCurrentPayment()">
                        <i class="fas fa-print me-2"></i>Imprimir Recibo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPaymentId = null;
        let currentPaymentType = null;

        function viewPaymentDetails(paymentId) {
            // Vista de detalles del pago legacy en modal
            currentPaymentId = paymentId;
            currentPaymentType = 'legacy';
            loadPaymentDetailsInModal(paymentId, 'legacy');
        }

        function viewOrderPaymentDetails(paymentId) {
            // Vista de detalles del pago de orden en modal
            currentPaymentId = paymentId;
            currentPaymentType = 'order';
            loadPaymentDetailsInModal(paymentId, 'order');
        }

        function viewValidationData(paymentId) {
            // Vista de datos de validación en modal
            currentPaymentId = paymentId;
            currentPaymentType = 'order';
            loadPaymentDetailsInModal(paymentId, 'order');
        }

        function loadPaymentDetailsInModal(paymentId, type) {
            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
            modal.show();
            
            // Cargar contenido via AJAX
            fetch(`view_details.php?id=${paymentId}&type=${type}&modal=1`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('paymentDetailsContent').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading payment details:', error);
                    document.getElementById('paymentDetailsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error al cargar los detalles del pago. Por favor, intente nuevamente.
                        </div>
                    `;
                });
        }

        function printCurrentPayment() {
            if (currentPaymentId && currentPaymentType) {
                window.open(`print_receipt.php?id=${currentPaymentId}&type=${currentPaymentType}`, '_blank', 'width=800,height=600');
            }
        }

        function printReceipt(paymentId) {
            // Impresión de recibo legacy
            window.open('print_receipt.php?id=' + paymentId + '&type=legacy', '_blank', 'width=800,height=600');
        }

        function printOrderPayment(paymentId) {
            // Impresión de recibo de orden
            window.open('print_receipt.php?id=' + paymentId + '&type=order', '_blank', 'width=800,height=600');
        }

        // Auto-refresh every 60 seconds
        setTimeout(() => {
            location.reload();
        }, 60000);
    </script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
