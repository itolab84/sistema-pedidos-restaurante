<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Get order ID
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    header('Location: index.php?error=invalid_id');
    exit;
}

// Get order details
$order = $db->fetchOne(
    "SELECT o.*, 
            COUNT(oi.id) as item_count,
            SUM(oi.quantity * oi.price) as items_total
     FROM orders o
     LEFT JOIN order_items oi ON o.id = oi.order_id
     WHERE o.id = ?
     GROUP BY o.id",
    [$orderId]
);

if (!$order) {
    header('Location: index.php?error=order_not_found');
    exit;
}

// Get order items
$orderItems = $db->fetchAll(
    "SELECT oi.*, p.name as product_name, p.description as product_description
     FROM order_items oi
     LEFT JOIN products p ON oi.product_id = p.id
     WHERE oi.order_id = ?
     ORDER BY oi.id",
    [$orderId]
);

// Get order payments
$orderPayments = $db->fetchAll(
    "SELECT op.*, 
            COALESCE(p.payment_method, op.payment_method) as payment_method,
            COALESCE(p.amount, op.amount) as payment_amount,
            COALESCE(p.transaction_id, op.reference) as reference,
            COALESCE(p.payment_status, op.status) as payment_status,
            COALESCE(p.payment_date, op.created_at) as payment_date,
            op.bank_origin, op.bank_destination, op.phone, op.change_amount
     FROM order_payments op
     LEFT JOIN payments p ON op.payment_id = p.id
     WHERE op.order_id = ?
     ORDER BY COALESCE(p.created_at, op.created_at) ASC",
    [$orderId]
);

// Get change history if exists
$changeHistory = $db->fetchAll(
    "SELECT ch.*, b.name as bank_name_lookup
     FROM change_history ch
     LEFT JOIN banks b ON ch.bank_code = b.code
     WHERE ch.order_id = ?
     ORDER BY ch.created_at DESC",
    [$orderId]
);

// Get order status history
$statusHistory = $db->fetchAll(
    "SELECT * FROM order_status_history 
     WHERE order_id = ? 
     ORDER BY created_at DESC",
    [$orderId]
);

// Handle status update
$message = '';
$messageType = 'success';

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $newStatus = $_POST['new_status'];
    $notes = $_POST['notes'] ?? '';
    $currentStatus = $order['status'];
    
    $validStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'];
    
    if (in_array($newStatus, $validStatuses) && $newStatus !== $currentStatus) {
        try {
            // Start transaction
            $db->query("START TRANSACTION");
            
            // Update order status
            $db->update('orders', 
                ['status' => $newStatus], 
                'id = ?', 
                [$orderId]
            );
            
            // Insert status history
            $db->insert('order_status_history', [
                'order_id' => $orderId,
                'status' => $newStatus,
                'previous_status' => $currentStatus,
                'changed_by' => $user['username'],
                'notes' => $notes
            ]);
            
            // Commit transaction
            $db->query("COMMIT");
            
            $message = 'Estado de la orden actualizado correctamente';
            
            // Refresh order data
            $order['status'] = $newStatus;
            
            // Refresh status history
            $statusHistory = $db->fetchAll(
                "SELECT * FROM order_status_history 
                 WHERE order_id = ? 
                 ORDER BY created_at DESC",
                [$orderId]
            );
            
        } catch (Exception $e) {
            $db->query("ROLLBACK");
            $message = 'Error al actualizar el estado: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = 'Estado no válido o igual al actual';
        $messageType = 'warning';
    }
}

// Status labels and colors
$statusLabels = [
    'pending' => ['label' => 'Pendiente', 'color' => 'warning'],
    'confirmed' => ['label' => 'Confirmado', 'color' => 'info'],
    'preparing' => ['label' => 'Preparando', 'color' => 'primary'],
    'ready' => ['label' => 'Listo', 'color' => 'success'],
    'out_for_delivery' => ['label' => 'En Camino', 'color' => 'info'],
    'delivered' => ['label' => 'Entregado', 'color' => 'success'],
    'cancelled' => ['label' => 'Cancelado', 'color' => 'danger']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden #<?= $order['id'] ?> - Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #fff;
            border: 3px solid #007bff;
        }
        .timeline-item.current::before {
            background: #007bff;
        }
        .timeline-item.completed::before {
            background: #28a745;
            border-color: #28a745;
        }
        .timeline-item.cancelled::before {
            background: #dc3545;
            border-color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-receipt me-2 text-primary"></i>
                            Orden #<?= $order['id'] ?>
                        </h2>
                        <p class="text-muted mb-0">
                            Detalles completos de la orden
                        </p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver
                        </a>
                        <button class="btn btn-outline-info" onclick="printOrder()">
                            <i class="fas fa-print me-2"></i>Imprimir
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

        <div class="row">
            <!-- Order Details -->
            <div class="col-md-8">
                <!-- Order Info -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-info-circle me-2"></i>Información de la Orden
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>ID de Orden:</strong></td>
                                        <td>#<?= $order['id'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Estado:</strong></td>
                                        <td>
                                            <span class="badge bg-<?= $statusLabels[$order['status']]['color'] ?>">
                                                <?= $statusLabels[$order['status']]['label'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Fecha de Orden:</strong></td>
                                        <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Método de Pago:</strong></td>
                                        <td><?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Cliente:</strong></td>
                                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td><?= htmlspecialchars($order['customer_email']) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Teléfono:</strong></td>
                                        <td><?= htmlspecialchars($order['customer_phone'] ?? 'No disponible') ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total:</strong></td>
                                        <td><strong class="text-success">$<?= number_format($order['total_amount_usd'] ?? $order['total_amount'], 2) ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <?php if (!empty($orderPayments)): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-credit-card me-2"></i>Información de Pagos
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Método</th>
                                        <th>Monto</th>
                                        <th>Referencia</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderPayments as $payment): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong class="text-success">
                                                    $<?= number_format($payment['payment_amount'], 2) ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($payment['reference'] ?? 'N/A') ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $payment['payment_status'] === 'completed' ? 'success' : ($payment['payment_status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                    <?= ucfirst($payment['payment_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y H:i', strtotime($payment['payment_date'])) ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Change History -->
                <?php if (!empty($changeHistory)): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-exchange-alt me-2"></i>Solicitudes de Vuelto
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Banco</th>
                                        <th>Teléfono</th>
                                        <th>Monto Pagado</th>
                                        <th>Monto Vuelto</th>
                                        <th>Método</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($changeHistory as $change): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($change['bank_name'] ?? ($change['bank_name_lookup'] ?? $change['bank_code'])) ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($change['customer_phone'] ?? 'N/A') ?>
                                            </td>
                                            <td>
                                                <strong class="text-info">
                                                    $<?= number_format($change['amount_paid'], 2) ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <strong class="text-warning">
                                                    <?= $change['change_currency'] === 'USD' ? '$' : 'Bs.' ?><?= number_format($change['change_amount'], 2) ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= ucfirst($change['change_method']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $change['payment_status'] === 'completed' ? 'success' : ($change['payment_status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                    <?= ucfirst($change['payment_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y H:i', strtotime($change['created_at'])) ?>
                                                </small>
                                                <?php if ($change['processed_at']): ?>
                                                    <br><small class="text-success">
                                                        Procesado: <?= date('d/m/Y H:i', strtotime($change['processed_at'])) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Order Items -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-shopping-cart me-2"></i>Productos Ordenados
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Precio Unit.</th>
                                        <th>Cantidad</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($item['product_name']) ?></h6>
                                                    <?php if ($item['product_description']): ?>
                                                        <small class="text-muted"><?= htmlspecialchars($item['product_description']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['notes'])): ?>
                                                        <br><small class="text-info">
                                                            <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($item['notes']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>$<?= number_format($item['price'], 2) ?></td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td><strong>$<?= number_format($item['price'] * $item['quantity'], 2) ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-active">
                                        <th colspan="3">Total:</th>
                                        <th class="text-success">$<?= number_format($order['total_amount_usd'] ?? $order['total_amount'], 2) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Management -->
            <div class="col-md-4">
                <!-- Update Status -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-edit me-2"></i>Actualizar Estado
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_status">
                            
                            <div class="mb-3">
                                <label for="new_status" class="form-label">Nuevo Estado</label>
                                <select class="form-select" id="new_status" name="new_status" required>
                                    <?php foreach ($statusLabels as $status => $info): ?>
                                        <option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>>
                                            <?= $info['label'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notas (Opcional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="Agregar notas sobre el cambio de estado..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Actualizar Estado
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Status History -->
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-history me-2"></i>Historial de Estados
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($statusHistory)): ?>
                            <p class="text-muted text-center">No hay historial disponible</p>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($statusHistory as $index => $history): ?>
                                    <div class="timeline-item <?= $index === 0 ? 'current' : '' ?>">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-<?= isset($statusLabels[$history['status']]) ? $statusLabels[$history['status']]['color'] : 'secondary' ?>">
                                                        <?= isset($statusLabels[$history['status']]) ? $statusLabels[$history['status']]['label'] : ucfirst($history['status']) ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        <?= date('d/m/Y H:i', strtotime($history['created_at'])) ?>
                                                    </small>
                                                </div>
                                                
                                                <?php if (!empty($history['previous_status']) && isset($statusLabels[$history['previous_status']])): ?>
                                                    <small class="text-muted">
                                                        Desde: <?= $statusLabels[$history['previous_status']]['label'] ?>
                                                    </small><br>
                                                <?php endif; ?>
                                                
                                                <small class="text-muted">
                                                    Por: <?= htmlspecialchars($history['changed_by']) ?>
                                                </small>
                                                
                                                <?php if (!empty($history['notes'])): ?>
                                                    <div class="mt-2">
                                                        <small class="text-info">
                                                            <i class="fas fa-comment"></i> 
                                                            <?= htmlspecialchars($history['notes']) ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printOrder() {
            window.open('print.php?id=<?= $order['id'] ?>', '_blank', 'width=800,height=600');
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
