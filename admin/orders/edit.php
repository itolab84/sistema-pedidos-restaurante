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
    "SELECT * FROM orders WHERE id = ?",
    [$orderId]
);

if (!$order) {
    header('Location: index.php?error=order_not_found');
    exit;
}

// Get order items
$orderItems = $db->fetchAll(
    "SELECT oi.*, p.name as product_name
     FROM order_items oi
     LEFT JOIN products p ON oi.product_id = p.id
     WHERE oi.order_id = ?
     ORDER BY oi.id",
    [$orderId]
);

// Handle form submission
$message = '';
$messageType = 'success';

if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_order':
            try {
                $db->query("START TRANSACTION");
                
                // Update order basic info
                $db->update('orders', [
                    'customer_name' => $_POST['customer_name'],
                    'customer_email' => $_POST['customer_email'],
                    'customer_phone' => $_POST['customer_phone'],
                    'payment_method' => $_POST['payment_method'],
                    'notes' => $_POST['notes'] ?? ''
                ], 'id = ?', [$orderId]);
                
                // Add status history if status changed
                if ($_POST['status'] !== $order['status']) {
                    $db->update('orders', ['status' => $_POST['status']], 'id = ?', [$orderId]);
                    
                    $db->insert('order_status_history', [
                        'order_id' => $orderId,
                        'status' => $_POST['status'],
                        'previous_status' => $order['status'],
                        'changed_by' => $user['username'],
                        'notes' => 'Actualizado desde edición de orden'
                    ]);
                }
                
                $db->query("COMMIT");
                $message = 'Orden actualizada correctamente';
                
                // Refresh order data
                $order = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
                
            } catch (Exception $e) {
                $db->query("ROLLBACK");
                $message = 'Error al actualizar la orden: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'update_item':
            $itemId = (int)$_POST['item_id'];
            $newQuantity = (int)$_POST['quantity'];
            $newPrice = (float)$_POST['price'];
            
            if ($newQuantity > 0 && $newPrice >= 0) {
                try {
                    $db->update('order_items', [
                        'quantity' => $newQuantity,
                        'price' => $newPrice
                    ], 'id = ? AND order_id = ?', [$itemId, $orderId]);
                    
                    // Recalculate order total
                    $newTotal = $db->fetchOne(
                        "SELECT SUM(quantity * price) as total FROM order_items WHERE order_id = ?",
                        [$orderId]
                    )['total'];
                    
                    $db->update('orders', ['total_amount' => $newTotal], 'id = ?', [$orderId]);
                    
                    $message = 'Producto actualizado correctamente';
                    
                    // Refresh data
                    $order = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
                    $orderItems = $db->fetchAll(
                        "SELECT oi.*, p.name as product_name
                         FROM order_items oi
                         LEFT JOIN products p ON oi.product_id = p.id
                         WHERE oi.order_id = ?
                         ORDER BY oi.id",
                        [$orderId]
                    );
                    
                } catch (Exception $e) {
                    $message = 'Error al actualizar el producto: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            } else {
                $message = 'Cantidad y precio deben ser válidos';
                $messageType = 'danger';
            }
            break;
            
        case 'remove_item':
            $itemId = (int)$_POST['item_id'];
            
            try {
                $db->delete('order_items', 'id = ? AND order_id = ?', [$itemId, $orderId]);
                
                // Recalculate order total
                $newTotal = $db->fetchOne(
                    "SELECT COALESCE(SUM(quantity * price), 0) as total FROM order_items WHERE order_id = ?",
                    [$orderId]
                )['total'];
                
                $db->update('orders', ['total_amount' => $newTotal], 'id = ?', [$orderId]);
                
                $message = 'Producto eliminado correctamente';
                
                // Refresh data
                $order = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
                $orderItems = $db->fetchAll(
                    "SELECT oi.*, p.name as product_name
                     FROM order_items oi
                     LEFT JOIN products p ON oi.product_id = p.id
                     WHERE oi.order_id = ?
                     ORDER BY oi.id",
                    [$orderId]
                );
                
            } catch (Exception $e) {
                $message = 'Error al eliminar el producto: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'add_product':
            $productId = (int)$_POST['product_id'];
            $quantity = (int)$_POST['quantity'];
            $price = (float)$_POST['price'];
            $notes = $_POST['notes'] ?? '';
            
            if ($productId > 0 && $quantity > 0 && $price >= 0) {
                try {
                    // Get product name for verification
                    $product = $db->fetchOne("SELECT name FROM products WHERE id = ?", [$productId]);
                    
                    if ($product) {
                        $db->insert('order_items', [
                            'order_id' => $orderId,
                            'product_id' => $productId,
                            'quantity' => $quantity,
                            'price' => $price,
                            'notes' => $notes
                        ]);
                        
                        // Recalculate order total
                        $newTotal = $db->fetchOne(
                            "SELECT SUM(quantity * price) as total FROM order_items WHERE order_id = ?",
                            [$orderId]
                        )['total'];
                        
                        $db->update('orders', ['total_amount' => $newTotal], 'id = ?', [$orderId]);
                        
                        $message = 'Producto agregado correctamente';
                        
                        // Refresh data
                        $order = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
                        $orderItems = $db->fetchAll(
                            "SELECT oi.*, p.name as product_name
                             FROM order_items oi
                             LEFT JOIN products p ON oi.product_id = p.id
                             WHERE oi.order_id = ?
                             ORDER BY oi.id",
                            [$orderId]
                        );
                    } else {
                        $message = 'Producto no encontrado';
                        $messageType = 'danger';
                    }
                    
                } catch (Exception $e) {
                    $message = 'Error al agregar el producto: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            } else {
                $message = 'Datos del producto no válidos';
                $messageType = 'danger';
            }
            break;
    }
}

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
    <title>Editar Orden #<?= $order['id'] ?> - Administración</title>
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
                            <i class="fas fa-edit me-2 text-primary"></i>
                            Editar Orden #<?= $order['id'] ?>
                        </h2>
                        <p class="text-muted mb-0">
                            Modificar detalles de la orden
                        </p>
                    </div>
                    <div>
                        <a href="view.php?id=<?= $order['id'] ?>" class="btn btn-outline-info">
                            <i class="fas fa-eye me-2"></i>Ver Detalles
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver
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

        <div class="row">
            <!-- Order Information -->
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-info-circle me-2"></i>Información de la Orden
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_order">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="customer_name" class="form-label">Nombre del Cliente</label>
                                        <input type="text" class="form-control" id="customer_name" name="customer_name" 
                                               value="<?= htmlspecialchars($order['customer_name']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="customer_email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="customer_email" name="customer_email" 
                                               value="<?= htmlspecialchars($order['customer_email']) ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="customer_phone" class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="customer_phone" name="customer_phone" 
                                               value="<?= htmlspecialchars($order['customer_phone'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Estado</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <?php foreach ($statusLabels as $status => $label): ?>
                                                <option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>>
                                                    <?= $label ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="payment_method" class="form-label">Método de Pago</label>
                                <select class="form-select" id="payment_method" name="payment_method" required>
                                    <option value="efectivo" <?= $order['payment_method'] === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                                    <option value="tarjeta" <?= $order['payment_method'] === 'tarjeta' ? 'selected' : '' ?>>Tarjeta</option>
                                    <option value="transferencia" <?= $order['payment_method'] === 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
                                    <option value="pago_movil" <?= $order['payment_method'] === 'pago_movil' ? 'selected' : '' ?>>Pago Móvil</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notas</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Guardar Cambios
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-receipt me-2"></i>Resumen de la Orden
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>ID de Orden:</strong></td>
                                <td>#<?= $order['id'] ?></td>
                            </tr>
                            <tr>
                                <td><strong>Fecha:</strong></td>
                                <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Estado Actual:</strong></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?= $statusLabels[$order['status']] ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Total:</strong></td>
                                <td><strong class="text-success">$<?= number_format($order['total_amount'], 2) ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-shopping-cart me-2"></i>Productos de la Orden
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orderItems)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-cart fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay productos en esta orden</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Precio Unit.</th>
                                            <th>Cantidad</th>
                                            <th>Subtotal</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orderItems as $item): ?>
                                            <tr id="item-<?= $item['id'] ?>">
                                                <td>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($item['product_name']) ?></h6>
                                                        <?php if (!empty($item['notes'])): ?>
                                                            <small class="text-info">
                                                                <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($item['notes']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline" onsubmit="return updateItem(<?= $item['id'] ?>)">
                                                        <input type="hidden" name="action" value="update_item">
                                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                        <div class="input-group input-group-sm" style="width: 100px;">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" step="0.01" min="0" class="form-control" 
                                                                   name="price" value="<?= $item['price'] ?>" 
                                                                   onchange="this.form.submit()">
                                                        </div>
                                                    </form>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline" onsubmit="return updateItem(<?= $item['id'] ?>)">
                                                        <input type="hidden" name="action" value="update_item">
                                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                        <input type="hidden" name="price" value="<?= $item['price'] ?>">
                                                        <div class="input-group input-group-sm" style="width: 80px;">
                                                            <input type="number" min="1" max="99" class="form-control text-center" 
                                                                   name="quantity" value="<?= $item['quantity'] ?>" 
                                                                   onchange="this.form.submit()">
                                                        </div>
                                                    </form>
                                                </td>
                                                <td>
                                                    <strong>$<?= number_format($item['price'] * $item['quantity'], 2) ?></strong>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="removeItem(<?= $item['id'] ?>)" 
                                                            title="Eliminar producto">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-active">
                                            <th colspan="3">Total:</th>
                                            <th class="text-success">$<?= number_format($order['total_amount'], 2) ?></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Add Product Section -->
                        <div class="mt-4 pt-4 border-top">
                            <h6 class="mb-3">
                                <i class="fas fa-plus me-2"></i>Agregar Nuevo Producto
                            </h6>
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="add_product">
                                
                                <div class="col-md-4">
                                    <label for="product_id" class="form-label">Producto</label>
                                    <select class="form-select" id="product_id" name="product_id" required>
                                        <option value="">Seleccionar producto...</option>
                                        <?php
                                        // Get available products
                                        $products = $db->fetchAll("SELECT id, name, price FROM products WHERE status = 'active' ORDER BY name");
                                        foreach ($products as $product):
                                        ?>
                                            <option value="<?= $product['id'] ?>" data-price="<?= $product['price'] ?>">
                                                <?= htmlspecialchars($product['name']) ?> - $<?= number_format($product['price'], 2) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="add_quantity" class="form-label">Cantidad</label>
                                    <input type="number" class="form-control" id="add_quantity" name="quantity" 
                                           min="1" max="99" value="1" required>
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="add_price" class="form-label">Precio</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               id="add_price" name="price" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="add_notes" class="form-label">Notas (Opcional)</label>
                                    <input type="text" class="form-control" id="add_notes" name="notes" 
                                           placeholder="Notas especiales...">
                                </div>
                                
                                <div class="col-md-1">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Remove Item Modal -->
    <div class="modal fade" id="removeItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de que desea eliminar este producto de la orden?</p>
                    <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" id="removeItemForm" class="d-inline">
                        <input type="hidden" name="action" value="remove_item">
                        <input type="hidden" name="item_id" id="removeItemId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateItem(itemId) {
            // Show loading indicator
            const row = document.getElementById('item-' + itemId);
            if (row) {
                row.style.opacity = '0.6';
            }
            return true;
        }

        function removeItem(itemId) {
            document.getElementById('removeItemId').value = itemId;
            const modal = new bootstrap.Modal(document.getElementById('removeItemModal'));
            modal.show();
        }

        // Auto-save functionality for inputs
        document.querySelectorAll('input[type="number"]').forEach(input => {
            let timeout;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    if (this.form && this.value !== this.defaultValue) {
                        // Visual feedback
                        this.style.borderColor = '#ffc107';
                        setTimeout(() => {
                            this.style.borderColor = '';
                        }, 1000);
                    }
                }, 500);
            });
        });

        // Auto-fill price when product is selected
        document.getElementById('product_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            
            if (price) {
                document.getElementById('add_price').value = price;
            }
        });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
