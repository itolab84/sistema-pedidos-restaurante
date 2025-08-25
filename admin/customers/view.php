<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

$customer_id = (int)($_GET['id'] ?? 0);
$created = isset($_GET['created']);

if (!$customer_id) {
    header('Location: index.php');
    exit;
}

// Get customer information
$customer = $db->fetchOne(
    "SELECT c.*, 
            DATEDIFF(NOW(), c.last_order_date) as days_since_last_order,
            CASE 
                WHEN c.last_order_date IS NULL THEN 'Nunca ha ordenado'
                WHEN DATEDIFF(NOW(), c.last_order_date) <= 30 THEN 'Cliente activo'
                WHEN DATEDIFF(NOW(), c.last_order_date) <= 90 THEN 'Cliente inactivo'
                ELSE 'Cliente inactivo'
            END as activity_description
     FROM customers c 
     WHERE c.id = ? AND c.status = 'active'",
    [$customer_id]
);

if (!$customer) {
    header('Location: index.php?error=customer_not_found');
    exit;
}

// Get customer phones
$phones = $db->fetchAll(
    "SELECT * FROM customer_phones WHERE customer_id = ? ORDER BY is_primary DESC, id ASC",
    [$customer_id]
);

// Get customer addresses
$addresses = $db->fetchAll(
    "SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_primary DESC, id ASC",
    [$customer_id]
);

// Get customer notes
$notes = $db->fetchAll(
    "SELECT cn.*, au.full_name as created_by_name 
     FROM customer_notes cn
     LEFT JOIN admin_users au ON cn.created_by = au.id
     WHERE cn.customer_id = ? 
     ORDER BY cn.created_at DESC",
    [$customer_id]
);

// Get order history
$orders = $db->fetchAll(
    "SELECT o.*, 
            COUNT(oi.id) as item_count,
            GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ') as items_summary
     FROM orders o
     LEFT JOIN order_items oi ON o.id = oi.order_id
     LEFT JOIN products p ON oi.product_id = p.id
     WHERE o.customer_id = ?
     GROUP BY o.id
     ORDER BY o.created_at DESC
     LIMIT 10",
    [$customer_id]
);

// Get favorite products
$favorite_products = $db->fetchAll(
    "SELECT p.name, p.image, COUNT(oi.id) as order_count, SUM(oi.quantity) as total_quantity
     FROM products p
     INNER JOIN order_items oi ON p.id = oi.product_id
     INNER JOIN orders o ON oi.order_id = o.id
     WHERE o.customer_id = ?
     GROUP BY p.id
     ORDER BY total_quantity DESC
     LIMIT 5",
    [$customer_id]
);

// Calculate customer statistics
$stats = [
    'avg_order_value' => $customer['total_orders'] > 0 ? $customer['total_spent'] / $customer['total_orders'] : 0,
    'orders_this_month' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())",
        [$customer_id]
    )['count'] ?? 0,
    'orders_this_year' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND YEAR(created_at) = YEAR(NOW())",
        [$customer_id]
    )['count'] ?? 0,
    'spent_this_year' => $db->fetchOne(
        "SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE customer_id = ? AND YEAR(created_at) = YEAR(NOW())",
        [$customer_id]
    )['total'] ?? 0
];

// Handle new note submission
$message = '';
$messageType = 'success';

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    try {
        $note_title = trim($_POST['note_title'] ?? '');
        $note_content = trim($_POST['note_content'] ?? '');
        $note_type = $_POST['note_type'] ?? 'general';
        $is_important = isset($_POST['is_important']) ? 1 : 0;
        
        if (empty($note_content)) {
            throw new Exception('El contenido de la nota es requerido');
        }
        
        $note_data = [
            'customer_id' => $customer_id,
            'note_title' => $note_title ?: null,
            'note_content' => $note_content,
            'note_type' => $note_type,
            'is_important' => $is_important,
            'created_by' => $user['id']
        ];
        
        $db->insert('customer_notes', $note_data);
        
        $message = 'Nota agregada exitosamente';
        header("Location: view.php?id=$customer_id&note_added=1");
        exit;
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

if (isset($_GET['note_added'])) {
    $message = 'Nota agregada exitosamente';
}

if ($created) {
    $message = 'Cliente creado exitosamente';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?> - Perfil Cliente</title>
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
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle-large me-3">
                            <?= strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h2 class="mb-1">
                                <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>
                                <span class="badge bg-<?= 
                                    $customer['customer_status'] === 'vip' ? 'success' : 
                                    ($customer['customer_status'] === 'regular' ? 'primary' : 
                                    ($customer['customer_status'] === 'new' ? 'info' : 'secondary')) 
                                ?> ms-2">
                                    <?= ucfirst($customer['customer_status']) ?>
                                </span>
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-barcode me-1"></i>
                                <?= htmlspecialchars($customer['customer_code']) ?>
                                <span class="ms-3">
                                    <i class="fas fa-calendar me-1"></i>
                                    Cliente desde <?= date('d/m/Y', strtotime($customer['registration_date'])) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div>
                        <a href="edit.php?id=<?= $customer['id'] ?>" class="btn btn-primary me-2">
                            <i class="fas fa-edit me-2"></i>Editar
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

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-left-primary shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Órdenes
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($customer['total_orders']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
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
                                    Total Gastado
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?= number_format($customer['total_spent'], 2) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                                    Promedio por Orden
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?= number_format($stats['avg_order_value'], 2) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
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
                                    Última Orden
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php if ($customer['last_order_date']): ?>
                                        <?= $customer['days_since_last_order'] ?> días
                                    <?php else: ?>
                                        Nunca
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Customer Information -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-user me-2"></i>Información Personal
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Nombre Completo:</strong></td>
                                        <td><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td>
                                            <?php if ($customer['email']): ?>
                                                <a href="mailto:<?= htmlspecialchars($customer['email']) ?>">
                                                    <?= htmlspecialchars($customer['email']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No registrado</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Fecha de Nacimiento:</strong></td>
                                        <td>
                                            <?php if ($customer['birth_date']): ?>
                                                <?= date('d/m/Y', strtotime($customer['birth_date'])) ?>
                                                <small class="text-muted">
                                                    (<?= date_diff(date_create($customer['birth_date']), date_create('today'))->y ?> años)
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">No registrada</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Género:</strong></td>
                                        <td>
                                            <?php if ($customer['gender']): ?>
                                                <?= $customer['gender'] === 'M' ? 'Masculino' : ($customer['gender'] === 'F' ? 'Femenino' : 'Otro') ?>
                                            <?php else: ?>
                                                <span class="text-muted">No especificado</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Estado:</strong></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $customer['customer_status'] === 'vip' ? 'success' : 
                                                ($customer['customer_status'] === 'regular' ? 'primary' : 
                                                ($customer['customer_status'] === 'new' ? 'info' : 'secondary')) 
                                            ?>">
                                                <?= ucfirst($customer['customer_status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Actividad:</strong></td>
                                        <td><?= htmlspecialchars($customer['activity_description']) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Registro:</strong></td>
                                        <td><?= date('d/m/Y H:i', strtotime($customer['registration_date'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Última Actualización:</strong></td>
                                        <td><?= date('d/m/Y H:i', strtotime($customer['updated_at'])) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if ($customer['preferences']): ?>
                            <div class="mt-3">
                                <strong>Preferencias:</strong>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($customer['preferences'])) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($customer['notes']): ?>
                            <div class="mt-3">
                                <strong>Notas:</strong>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($customer['notes'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-address-book me-2"></i>Información de Contacto
                        </h6>
                        <a href="addresses.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit me-1"></i>Gestionar
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Phones -->
                            <div class="col-md-6">
                                <h6><i class="fas fa-phone me-2"></i>Teléfonos</h6>
                                <?php if (empty($phones)): ?>
                                    <p class="text-muted">No hay teléfonos registrados</p>
                                <?php else: ?>
                                    <?php foreach ($phones as $phone): ?>
                                        <div class="mb-2">
                                            <strong><?= ucfirst($phone['phone_type']) ?>:</strong>
                                            <a href="tel:<?= htmlspecialchars($phone['phone_number']) ?>">
                                                <?= htmlspecialchars($phone['phone_number']) ?>
                                            </a>
                                            <?php if ($phone['is_primary']): ?>
                                                <span class="badge bg-primary ms-1">Principal</span>
                                            <?php endif; ?>
                                            <?php if ($phone['is_whatsapp']): ?>
                                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $phone['phone_number']) ?>" 
                                                   target="_blank" class="text-success ms-1">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Addresses -->
                            <div class="col-md-6">
                                <h6><i class="fas fa-map-marker-alt me-2"></i>Direcciones</h6>
                                <?php if (empty($addresses)): ?>
                                    <p class="text-muted">No hay direcciones registradas</p>
                                <?php else: ?>
                                    <?php foreach ($addresses as $address): ?>
                                        <div class="mb-3">
                                            <strong><?= ucfirst($address['address_type']) ?>:</strong>
                                            <?php if ($address['is_primary']): ?>
                                                <span class="badge bg-primary ms-1">Principal</span>
                                            <?php endif; ?>
                                            <br>
                                            <small>
                                                <?= htmlspecialchars($address['street_address']) ?><br>
                                                <?= htmlspecialchars($address['city']) ?>
                                                <?php if ($address['state']): ?>
                                                    , <?= htmlspecialchars($address['state']) ?>
                                                <?php endif; ?>
                                                <?php if ($address['postal_code']): ?>
                                                    <?= htmlspecialchars($address['postal_code']) ?>
                                                <?php endif; ?>
                                                <?php if ($address['delivery_instructions']): ?>
                                                    <br><em><?= htmlspecialchars($address['delivery_instructions']) ?></em>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order History -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-history me-2"></i>Historial de Órdenes (Últimas 10)
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-cart fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted">Este cliente aún no ha realizado órdenes</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Orden</th>
                                            <th>Fecha</th>
                                            <th>Productos</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>#<?= $order['id'] ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                                <td>
                                                    <small title="<?= htmlspecialchars($order['items_summary']) ?>">
                                                        <?= $order['item_count'] ?> producto(s)
                                                    </small>
                                                </td>
                                                <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $order['order_status'] === 'completed' ? 'success' : 
                                                        ($order['order_status'] === 'processing' ? 'warning' : 
                                                        ($order['order_status'] === 'cancelled' ? 'danger' : 'secondary')) 
                                                    ?>">
                                                        <?= ucfirst($order['order_status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="../orders/view.php?id=<?= $order['id'] ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
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

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Quick Stats -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie me-2"></i>Estadísticas Rápidas
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Órdenes este mes</small>
                            <div class="h5 mb-0"><?= $stats['orders_this_month'] ?></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Órdenes este año</small>
                            <div class="h5 mb-0"><?= $stats['orders_this_year'] ?></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Gastado este año</small>
                            <div class="h5 mb-0 text-success">$<?= number_format($stats['spent_this_year'], 2) ?></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Teléfonos registrados</small>
                            <div class="h5 mb-0"><?= count($phones) ?></div>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted">Direcciones registradas</small>
                            <div class="h5 mb-0"><?= count($addresses) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Favorite Products -->
                <?php if (!empty($favorite_products)): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-heart me-2"></i>Productos Favoritos
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($favorite_products as $product): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <img src="<?= $product['image'] ?: 'https://via.placeholder.com/40x40?text=IMG' ?>" 
                                         class="rounded me-3" width="40" height="40" style="object-fit: cover;">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($product['name']) ?></h6>
                                        <small class="text-muted">
                                            <?= $product['total_quantity'] ?> unidades en <?= $product['order_count'] ?> órdenes
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Customer Notes -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-sticky-note me-2"></i>Notas del Cliente
                        </h6>
                    </div>
                    <div class="card-body">
                        <!-- Add Note Form -->
                        <form method="POST" class="mb-4">
                            <input type="hidden" name="action" value="add_note">
                            <div class="mb-2">
                                <input type="text" class="form-control form-control-sm" name="note_title" 
                                       placeholder="Título (opcional)">
                            </div>
                            <div class="mb-2">
                                <textarea class="form-control form-control-sm" name="note_content" rows="3" 
                                          placeholder="Contenido de la nota..." required></textarea>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <select class="form-select form-select-sm" name="note_type">
                                        <option value="general">General</option>
                                        <option value="preference">Preferencia</option>
                                        <option value="complaint">Queja</option>
                                        <option value="compliment">Elogio</option>
                                        <option value="administrative">Administrativo</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_important" id="is_important">
                                        <label class="form-check-label" for="is_important">
                                            <small>Importante</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary mt-2">
                                <i class="fas fa-plus me-1"></i>Agregar Nota
                            </button>
                        </form>

                        <!-- Notes List -->
                        <?php if (empty($notes)): ?>
                            <p class="text-muted text-center">No hay notas registradas</p>
                        <?php else: ?>
                            <div class="notes-list" style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($notes as $note): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <?php if ($note['note_title']): ?>
                                            <h6 class="mb-1">
                                                <?= htmlspecialchars($note['note_title']) ?>
                                                <?php if ($note['is_important']): ?>
                                                    <i class="fas fa-exclamation-triangle text-warning ms-1"></i>
                                                <?php endif; ?>
                                            </h6>
                                        <?php endif; ?>
                                        <p class="mb-1"><?= nl2br(htmlspecialchars($note['note_content'])) ?></p>
                                        <small class="text-muted">
                                            <span class="badge bg-<?= 
                                                $note['note_type'] === 'complaint' ? 'danger' : 
                                                ($note['note_type'] === 'compliment' ? 'success' : 
                                                ($note['note_type'] === 'preference' ? 'info' : 'secondary')) 
                                            ?> me-1">
                                                <?= ucfirst($note['note_type']) ?>
                                            </span>
                                            <?= date('d/m/Y H:i', strtotime($note['created_at'])) ?>
                                            <?php if ($note['created_by_name']): ?>
                                                - <?= htmlspecialchars($note['created_by_name']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-bolt me-2"></i>Acciones Rápidas
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="edit.php?id=<?= $customer['id'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-edit me-2"></i>Editar Información
                            </a>
                            <a href="addresses.php?id=<?= $customer['id'] ?>" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-map-marker-alt me-2"></i>Gestionar Direcciones
                            </a>
                            <a href="../orders/index.php?search=<?= urlencode($customer['customer_code']) ?>" 
                               class="btn btn-outline-success btn-sm">
                                <i class="fas fa-shopping-cart me-2"></i>Ver Todas las Órdenes
                            </a>
                            <button class="btn btn-outline-warning btn-sm" onclick="exportCustomerData()">
                                <i class="fas fa-download me-2"></i>Exportar Datos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportCustomerData() {
            // Simple export functionality - could be enhanced
            const customerData = {
                customer_code: '<?= $customer['customer_code'] ?>',
                name: '<?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>',
                email: '<?= htmlspecialchars($customer['email']) ?>',
                total_orders: <?= $customer['total_orders'] ?>,
                total_spent: <?= $customer['total_spent'] ?>,
                registration_date: '<?= $customer['registration_date'] ?>',
                last_order_date: '<?= $customer['last_order_date'] ?>',
                customer_status: '<?= $customer['customer_status'] ?>'
            };
            
            const dataStr = JSON.stringify(customerData, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'cliente_<?= $customer['customer_code'] ?>_<?= date('Y-m-d') ?>.json';
            link.click();
        }
    </script>

    <style>
        .avatar-circle-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 24px;
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
        
        .notes-list {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
