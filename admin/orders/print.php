<?php
require_once '../config/auth.php';
$auth->requireLogin();

$db = AdminDB::getInstance();

// Get order ID
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    die('ID de orden no válido');
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
    die('Orden no encontrada');
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

// Status labels
$statusLabels = [
    'pending' => 'Pendiente',
    'confirmed' => 'Confirmado',
    'preparing' => 'Preparando',
    'ready' => 'Listo',
    'out_for_delivery' => 'En Camino',
    'delivered' => 'Entregado',
    'cancelled' => 'Cancelado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden #<?= $order['id'] ?> - Impresión</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            background: #fff;
        }
        
        .receipt {
            width: 80mm;
            margin: 0 auto;
            padding: 10px;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .restaurant-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .restaurant-info {
            font-size: 10px;
            margin-bottom: 2px;
        }
        
        .order-info {
            margin-bottom: 15px;
        }
        
        .order-info table {
            width: 100%;
            font-size: 11px;
        }
        
        .order-info td {
            padding: 2px 0;
        }
        
        .customer-info {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 10px 0;
            margin-bottom: 15px;
        }
        
        .customer-info h3 {
            font-size: 12px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .items {
            margin-bottom: 15px;
        }
        
        .items h3 {
            font-size: 12px;
            margin-bottom: 10px;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
        }
        
        .item {
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px dotted #ccc;
        }
        
        .item:last-child {
            border-bottom: none;
        }
        
        .item-name {
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .item-details {
            font-size: 10px;
            color: #666;
            margin-bottom: 3px;
        }
        
        .item-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .item-notes {
            font-size: 10px;
            font-style: italic;
            color: #333;
            margin-top: 3px;
        }
        
        .totals {
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-top: 15px;
        }
        
        .total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .total-line.final {
            font-weight: bold;
            font-size: 14px;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 10px;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dashed #000;
            font-size: 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border: 1px solid #000;
            font-weight: bold;
            text-transform: uppercase;
            margin: 5px 0;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .receipt {
                width: 100%;
                margin: 0;
                padding: 5px;
            }
            
            .no-print {
                display: none;
            }
        }
        
        .print-button {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        🖨️ Imprimir
    </button>
    
    <div class="receipt">
        <!-- Header -->
        <div class="header">
            <div class="restaurant-name">FLAVORFINDER</div>
            <div class="restaurant-info">Restaurante Express</div>
            <div class="restaurant-info">Tel: (555) 123-4567</div>
            <div class="restaurant-info">www.flavorfinder.com</div>
        </div>
        
        <!-- Order Info -->
        <div class="order-info">
            <table>
                <tr>
                    <td><strong>ORDEN:</strong></td>
                    <td>#<?= $order['id'] ?></td>
                </tr>
                <tr>
                    <td><strong>FECHA:</strong></td>
                    <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                </tr>
                <tr>
                    <td><strong>ESTADO:</strong></td>
                    <td>
                        <span class="status-badge">
                            <?= $statusLabels[$order['status']] ?? $order['status'] ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><strong>PAGO:</strong></td>
                    <td><?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Customer Info -->
        <div class="customer-info">
            <h3>Información del Cliente</h3>
            <div><strong>Nombre:</strong> <?= htmlspecialchars($order['customer_name']) ?></div>
            <div><strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?></div>
            <?php if ($order['customer_phone']): ?>
                <div><strong>Teléfono:</strong> <?= htmlspecialchars($order['customer_phone']) ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Items -->
        <div class="items">
            <h3>Productos Ordenados</h3>
            <?php foreach ($orderItems as $item): ?>
                <div class="item">
                    <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                    
                    <?php if ($item['product_description']): ?>
                        <div class="item-details"><?= htmlspecialchars($item['product_description']) ?></div>
                    <?php endif; ?>
                    
                    <div class="item-line">
                        <span><?= $item['quantity'] ?> x $<?= number_format($item['price'], 2) ?></span>
                        <span><strong>$<?= number_format($item['price'] * $item['quantity'], 2) ?></strong></span>
                    </div>
                    
                    <?php if (!empty($item['notes'])): ?>
                        <div class="item-notes">
                            📝 <?= htmlspecialchars($item['notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Totals -->
        <div class="totals">
            <div class="total-line">
                <span>Subtotal:</span>
                <span>$<?= number_format($order['total_amount'], 2) ?></span>
            </div>
            
            <div class="total-line">
                <span>Delivery:</span>
                <span>$0.00</span>
            </div>
            
            <div class="total-line final">
                <span>TOTAL:</span>
                <span>$<?= number_format($order['total_amount'], 2) ?></span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div>¡Gracias por su preferencia!</div>
            <div>Síguenos en redes sociales</div>
            <div>@FlavorFinderRestaurant</div>
            <div style="margin-top: 10px;">
                Impreso: <?= date('d/m/Y H:i:s') ?>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 500);
        // };
        
        // Close window after printing
        window.onafterprint = function() {
            // window.close();
        };
    </script>
</body>
</html>
