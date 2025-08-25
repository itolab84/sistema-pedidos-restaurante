

<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Get database connection for order_payments queries
require_once '../config/database.php';
$conn = getDBConnection();

$paymentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'legacy'; // legacy or order
$isModal = isset($_GET['modal']) && $_GET['modal'] == '1'; // Check if it's for modal

if (!$paymentId) {
    if ($isModal) {
        echo '<div class="alert alert-danger">ID de pago requerido</div>';
        exit;
    } else {
        header('Location: index.php');
        exit;
    }
}

if ($type === 'order') {
    // Get order payment details
    $sql = "SELECT 
                op.*,
                o.customer_name,
                o.customer_email,
                o.customer_phone,
                o.total_amount as order_total,
                o.status as order_status,
                o.created_at as order_date
            FROM order_payments op
            LEFT JOIN orders o ON op.order_id = o.id
            WHERE op.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $paymentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    
    if (!$payment) {
        header('Location: index.php?view=orders');
        exit;
    }
    
    // Decode validation data if exists
    if ($payment['validation_data']) {
        $payment['validation_data'] = json_decode($payment['validation_data'], true);
    }
    
    // Set default values for missing fields
    $payment['delivery_address'] = 'No disponible';
    $payment['order_type'] = 'delivery';
    
} else {
    // Get legacy payment details
    $payment = $db->fetchOne(
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
         WHERE p.id = ?",
        [$paymentId]
    );
    
    if (!$payment) {
        header('Location: index.php?view=legacy');
        exit;
    }
    
    // Set default values for missing fields
    $payment['delivery_address'] = 'No disponible';
    $payment['order_type'] = 'delivery';
}

// Get order items
$orderItems = $db->fetchAll(
    "SELECT oi.*, p.name as product_name
     FROM order_items oi
     LEFT JOIN products p ON oi.product_id = p.id
     WHERE oi.order_id = ?",
    [$payment['order_id']]
);

// If it's for modal, only return the content
if ($isModal) {
    ?>
    <div class="row">
    <?php
} else {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Detalles del Pago #<?= $payment['id'] ?> - Administración</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link href="../assets/css/admin.css" rel="stylesheet">
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
                                Detalles del Pago #<?= $payment['id'] ?>
                            </h2>
                            <p class="text-muted mb-0">
                                <?= $type === 'order' ? 'Pago de Orden' : 'Pago Legacy' ?> - Orden #<?= $payment['order_id'] ?>
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="index.php?view=<?= $type === 'order' ? 'orders' : 'legacy' ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Volver
                            </a>
                            <button class="btn btn-primary" onclick="printPayment()">
                                <i class="fas fa-print me-2"></i>Imprimir
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
    <?php
}
?>
            <!-- Payment Information -->
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-credit-card me-2"></i>Información del Pago
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>ID del Pago:</strong></div>
                            <div class="col-sm-8">#<?= $payment['id'] ?></div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Monto:</strong></div>
                            <div class="col-sm-8">
                                <span class="h5 text-success">$<?= number_format($payment['amount'], 2) ?></span>
                                <?php if ($payment['amount'] != $payment['order_total']): ?>
                                    <br>
                                    <small class="text-warning">
                                        Total orden: $<?= number_format($payment['order_total'], 2) ?>
                                        <?php if ($payment['amount'] < $payment['order_total']): ?>
                                            (Pago parcial)
                                        <?php else: ?>
                                            (Sobrepago)
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Método:</strong></div>
                            <div class="col-sm-8">
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
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Estado:</strong></div>
                            <div class="col-sm-8">
                                <?php
                                $status = $type === 'order' ? $payment['status'] : $payment['payment_status'];
                                $statusColors = [
                                    'pending' => 'warning',
                                    'pending_validation' => 'warning',
                                    'completed' => 'success',
                                    'paid' => 'success',
                                    'failed' => 'danger',
                                    'refunded' => 'info'
                                ];
                                $statusTexts = [
                                    'pending' => 'Pendiente',
                                    'pending_validation' => 'Pendiente Validación',
                                    'completed' => 'Completado',
                                    'paid' => 'Pagado',
                                    'failed' => 'Fallido',
                                    'refunded' => 'Reembolsado'
                                ];
                                ?>
                                <span class="badge bg-<?= $statusColors[$status] ?? 'secondary' ?>">
                                    <?= $statusTexts[$status] ?? ucfirst($status) ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($type === 'order' && $payment['payment_type']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Tipo:</strong></div>
                            <div class="col-sm-8">
                                <span class="badge bg-secondary">
                                    <?= ucfirst($payment['payment_type']) ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($payment['transaction_id']) && $payment['transaction_id']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Transacción:</strong></div>
                            <div class="col-sm-8">
                                <code><?= htmlspecialchars($payment['transaction_id']) ?></code>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($type === 'order' && $payment['reference']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Referencia:</strong></div>
                            <div class="col-sm-8">
                                <code><?= htmlspecialchars($payment['reference']) ?></code>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Fecha:</strong></div>
                            <div class="col-sm-8">
                                <?php 
                                $paymentDate = $type === 'order' ? $payment['created_at'] : $payment['payment_date'];
                                ?>
                                <?= date('d/m/Y H:i:s', strtotime($paymentDate)) ?>
                            </div>
                        </div>
                        
                        <?php if ($type === 'legacy' && isset($payment['processed_by_name'])): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Procesado por:</strong></div>
                            <div class="col-sm-8"><?= htmlspecialchars($payment['processed_by_name']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($payment['notes']) && $payment['notes']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Notas:</strong></div>
                            <div class="col-sm-8">
                                <div class="alert alert-info">
                                    <?= nl2br(htmlspecialchars($payment['notes'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($type === 'order' && $payment['validation_data']): ?>
                <!-- Validation Data -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-success">
                            <i class="fas fa-check-circle me-2"></i>Datos de Validación
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($payment['validation_data'] as $key => $value): ?>
                            <?php if ($value): ?>
                            <div class="row mb-2">
                                <div class="col-sm-4"><strong><?= ucfirst(str_replace('_', ' ', $key)) ?>:</strong></div>
                                <div class="col-sm-8"><?= htmlspecialchars($value) ?></div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Order Information -->
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-shopping-cart me-2"></i>Información de la Orden
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Orden ID:</strong></div>
                            <div class="col-sm-8">#<?= $payment['order_id'] ?></div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Cliente:</strong></div>
                            <div class="col-sm-8">
                                <strong><?= htmlspecialchars($payment['customer_name']) ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($payment['customer_email']) ?>
                                    <?php if ($payment['customer_phone']): ?>
                                        <br><?= htmlspecialchars($payment['customer_phone']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Total Orden:</strong></div>
                            <div class="col-sm-8">
                                <span class="h5 text-primary">$<?= number_format($payment['order_total'], 2) ?></span>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Estado Orden:</strong></div>
                            <div class="col-sm-8">
                                <span class="badge bg-info"><?= ucfirst($payment['order_status']) ?></span>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Tipo:</strong></div>
                            <div class="col-sm-8">
                                <span class="badge bg-secondary">
                                    <?= $payment['order_type'] === 'delivery' ? 'Delivery' : 'Recoger en tienda' ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Fecha Orden:</strong></div>
                            <div class="col-sm-8">
                                <?= date('d/m/Y H:i:s', strtotime($payment['order_date'])) ?>
                            </div>
                        </div>
                        
                        <?php if ($payment['delivery_address']): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Dirección:</strong></div>
                            <div class="col-sm-8">
                                <div class="alert alert-light">
                                    <?= nl2br(htmlspecialchars($payment['delivery_address'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Order Items -->
                <?php if (!empty($orderItems)): ?>
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>Productos de la Orden
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Precio Unit.</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($item['product_name'] ?? 'Producto eliminado') ?></strong>
                                            <?php if ($item['notes']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($item['notes']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>$<?= number_format($item['price'], 2) ?></td>
                                        <td><strong>$<?= number_format($item['price'] * $item['quantity'], 2) ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

<?php if (!$isModal): ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printPayment() {
            window.open('print_receipt.php?id=<?= $payment['id'] ?>&type=<?= $type ?>', '_blank', 'width=800,height=600');
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
<?php endif; ?>
