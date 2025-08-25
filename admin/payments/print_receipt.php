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

if (!$paymentId) {
    die('ID de pago requerido');
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
        die('Pago no encontrado');
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
        die('Pago no encontrado');
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

// Get company info (if available)
$companyInfo = [
    'name' => 'FlavorFinder Restaurant',
    'address' => 'Dirección del Restaurante',
    'phone' => 'Teléfono del Restaurante',
    'email' => 'info@flavorfinder.com'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pago #<?= $payment['id'] ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            background: white;
        }
        
        .receipt {
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .company-info {
            color: #666;
            font-size: 11px;
        }
        
        .receipt-title {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            color: #333;
        }
        
        .info-section {
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 2px 0;
        }
        
        .info-row.highlight {
            background-color: #f8f9fa;
            padding: 5px;
            border-left: 3px solid #007bff;
        }
        
        .label {
            font-weight: bold;
            color: #333;
        }
        
        .value {
            color: #666;
        }
        
        .amount {
            font-weight: bold;
            color: #28a745;
            font-size: 14px;
        }
        
        .status {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status.completed, .status.paid {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status.pending, .status.pending_validation {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status.failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .items-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .total-section {
            border-top: 2px solid #333;
            padding-top: 15px;
            margin-top: 20px;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 10px;
        }
        
        .validation-box {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 10px;
            margin: 15px 0;
        }
        
        .validation-title {
            font-weight: bold;
            color: #0c5460;
            margin-bottom: 5px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .receipt {
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <!-- Header -->
        <div class="header">
            <div class="company-name"><?= htmlspecialchars($companyInfo['name']) ?></div>
            <div class="company-info">
                <?= htmlspecialchars($companyInfo['address']) ?><br>
                Tel: <?= htmlspecialchars($companyInfo['phone']) ?> | Email: <?= htmlspecialchars($companyInfo['email']) ?>
            </div>
        </div>
        
        <div class="receipt-title">RECIBO DE PAGO</div>
        
        <!-- Payment Information -->
        <div class="info-section">
            <div class="info-row highlight">
                <span class="label">Número de Recibo:</span>
                <span class="value">#<?= str_pad($payment['id'], 6, '0', STR_PAD_LEFT) ?></span>
            </div>
            
            <div class="info-row">
                <span class="label">Fecha de Pago:</span>
                <span class="value">
                    <?php 
                    $paymentDate = $type === 'order' ? $payment['created_at'] : $payment['payment_date'];
                    echo date('d/m/Y H:i:s', strtotime($paymentDate));
                    ?>
                </span>
            </div>
            
            <div class="info-row">
                <span class="label">Orden Relacionada:</span>
                <span class="value">#<?= $payment['order_id'] ?></span>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="info-section">
            <h4 style="margin-bottom: 10px; color: #333;">Información del Cliente</h4>
            
            <div class="info-row">
                <span class="label">Nombre:</span>
                <span class="value"><?= htmlspecialchars($payment['customer_name']) ?></span>
            </div>
            
            <div class="info-row">
                <span class="label">Email:</span>
                <span class="value"><?= htmlspecialchars($payment['customer_email']) ?></span>
            </div>
            
            <?php if ($payment['customer_phone']): ?>
            <div class="info-row">
                <span class="label">Teléfono:</span>
                <span class="value"><?= htmlspecialchars($payment['customer_phone']) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <span class="label">Tipo de Orden:</span>
                <span class="value">
                    <?= $payment['order_type'] === 'delivery' ? 'Delivery' : 'Recoger en tienda' ?>
                </span>
            </div>
        </div>
        
        <!-- Payment Details -->
        <div class="info-section">
            <h4 style="margin-bottom: 10px; color: #333;">Detalles del Pago</h4>
            
            <div class="info-row highlight">
                <span class="label">Monto Pagado:</span>
                <span class="amount">$<?= number_format($payment['amount'], 2) ?></span>
            </div>
            
            <div class="info-row">
                <span class="label">Método de Pago:</span>
                <span class="value">
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
            
            <div class="info-row">
                <span class="label">Estado:</span>
                <span class="value">
                    <?php
                    $status = $type === 'order' ? $payment['status'] : $payment['payment_status'];
                    $statusTexts = [
                        'pending' => 'Pendiente',
                        'pending_validation' => 'Pendiente Validación',
                        'completed' => 'Completado',
                        'paid' => 'Pagado',
                        'failed' => 'Fallido',
                        'refunded' => 'Reembolsado'
                    ];
                    ?>
                    <span class="status <?= $status ?>">
                        <?= $statusTexts[$status] ?? ucfirst($status) ?>
                    </span>
                </span>
            </div>
            
            <?php if (isset($payment['transaction_id']) && $payment['transaction_id']): ?>
            <div class="info-row">
                <span class="label">ID Transacción:</span>
                <span class="value"><?= htmlspecialchars($payment['transaction_id']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($type === 'order' && $payment['reference']): ?>
            <div class="info-row">
                <span class="label">Referencia:</span>
                <span class="value"><?= htmlspecialchars($payment['reference']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($type === 'legacy' && isset($payment['processed_by_name'])): ?>
            <div class="info-row">
                <span class="label">Procesado por:</span>
                <span class="value"><?= htmlspecialchars($payment['processed_by_name']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Validation Data -->
        <?php if ($type === 'order' && $payment['validation_data']): ?>
        <div class="validation-box">
            <div class="validation-title">✓ Pago Validado Externamente</div>
            <?php foreach ($payment['validation_data'] as $key => $value): ?>
                <?php if ($value && $key !== 'validated_at'): ?>
                <div class="info-row">
                    <span class="label"><?= ucfirst(str_replace('_', ' ', $key)) ?>:</span>
                    <span class="value"><?= htmlspecialchars($value) ?></span>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Order Items -->
        <?php if (!empty($orderItems)): ?>
        <div class="info-section">
            <h4 style="margin-bottom: 10px; color: #333;">Productos de la Orden</h4>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-right">Cant.</th>
                        <th class="text-right">Precio Unit.</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $itemsTotal = 0;
                    foreach ($orderItems as $item): 
                        $itemTotal = $item['price'] * $item['quantity'];
                        $itemsTotal += $itemTotal;
                    ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($item['product_name'] ?? 'Producto eliminado') ?>
                            <?php if ($item['notes']): ?>
                                <br><small style="color: #666;"><?= htmlspecialchars($item['notes']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?= $item['quantity'] ?></td>
                        <td class="text-right">$<?= number_format($item['price'], 2) ?></td>
                        <td class="text-right">$<?= number_format($itemTotal, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Total Section -->
        <div class="total-section">
            <div class="info-row">
                <span class="label">Total de la Orden:</span>
                <span class="amount">$<?= number_format($payment['order_total'], 2) ?></span>
            </div>
            
            <div class="info-row highlight">
                <span class="label">Monto Pagado:</span>
                <span class="amount">$<?= number_format($payment['amount'], 2) ?></span>
            </div>
            
            <?php if ($payment['amount'] != $payment['order_total']): ?>
            <div class="info-row">
                <span class="label">
                    <?php if ($payment['amount'] < $payment['order_total']): ?>
                        Saldo Pendiente:
                    <?php else: ?>
                        Sobrepago:
                    <?php endif; ?>
                </span>
                <span class="amount" style="color: <?= $payment['amount'] < $payment['order_total'] ? '#dc3545' : '#17a2b8' ?>;">
                    $<?= number_format(abs($payment['order_total'] - $payment['amount']), 2) ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Notes -->
        <?php if (isset($payment['notes']) && $payment['notes']): ?>
        <div class="info-section">
            <h4 style="margin-bottom: 10px; color: #333;">Notas</h4>
            <div style="background-color: #f8f9fa; padding: 10px; border-radius: 5px;">
                <?= nl2br(htmlspecialchars($payment['notes'])) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <p><strong>¡Gracias por su preferencia!</strong></p>
            <p>Este recibo es válido como comprobante de pago</p>
            <p>Generado el <?= date('d/m/Y H:i:s') ?> por <?= htmlspecialchars($user['username']) ?></p>
        </div>
    </div>
    
    <script>
        // Auto print when page loads
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
