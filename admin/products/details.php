<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Get product ID
$productId = (int)($_GET['id'] ?? 0);
if (!$productId) {
    header('Location: index.php');
    exit;
}

// Get product info
$product = $db->fetchOne(
    "SELECT p.*, c.name as category_name FROM products p 
     LEFT JOIN categories c ON p.category_id = c.id 
     WHERE p.id = ?", 
    [$productId]
);

if (!$product) {
    header('Location: index.php');
    exit;
}

// Handle actions
$message = '';
$messageType = 'success';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_size':
                $sizeId = (int)$_POST['size_id'];
                $price = (float)$_POST['price'];
                
                if ($sizeId && $price > 0) {
                    try {
                        $db->insert('product_size_prices', [
                            'product_id' => $productId,
                            'size_id' => $sizeId,
                            'price' => $price
                        ]);
                        $message = 'Tamaño agregado correctamente';
                    } catch (Exception $e) {
                        $message = 'Error: Este tamaño ya existe para este producto';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Datos inválidos';
                    $messageType = 'danger';
                }
                break;
                
            case 'remove_size':
                $sizeId = (int)$_POST['size_id'];
                $db->delete('product_size_prices', 'product_id = ? AND size_id = ?', [$productId, $sizeId]);
                $message = 'Tamaño eliminado correctamente';
                break;
                
            case 'add_additional':
                $additionalId = (int)$_POST['additional_id'];
                $isDefault = isset($_POST['is_default']) ? 1 : 0;
                
                if ($additionalId) {
                    try {
                        $db->insert('product_additionals', [
                            'product_id' => $productId,
                            'additional_id' => $additionalId,
                            'is_default' => $isDefault
                        ]);
                        $message = 'Adicional agregado correctamente';
                    } catch (Exception $e) {
                        $message = 'Error: Este adicional ya existe para este producto';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Datos inválidos';
                    $messageType = 'danger';
                }
                break;
                
            case 'remove_additional':
                $additionalId = (int)$_POST['additional_id'];
                $db->delete('product_additionals', 'product_id = ? AND additional_id = ?', [$productId, $additionalId]);
                $message = 'Adicional eliminado correctamente';
                break;
                
            case 'toggle_default_additional':
                $additionalId = (int)$_POST['additional_id'];
                $currentDefault = (int)$_POST['current_default'];
                $newDefault = $currentDefault ? 0 : 1;
                
                $db->update('product_additionals', 
                    ['is_default' => $newDefault], 
                    'product_id = ? AND additional_id = ?', 
                    [$productId, $additionalId]
                );
                $message = 'Estado del adicional actualizado';
                break;
        }
    }
}

// Get current product sizes
$productSizes = $db->fetchAll(
    "SELECT psp.*, ps.name, ps.description, ps.multiplier 
     FROM product_size_prices psp
     JOIN product_sizes ps ON psp.size_id = ps.id
     WHERE psp.product_id = ?
     ORDER BY ps.sort_order", 
    [$productId]
);

// Get available sizes (not already assigned)
$availableSizes = $db->fetchAll(
    "SELECT ps.* FROM product_sizes ps
     WHERE ps.status = 'active' 
     AND ps.id NOT IN (
         SELECT size_id FROM product_size_prices WHERE product_id = ?
     )
     ORDER BY ps.sort_order", 
    [$productId]
);

// Get current product additionals
$productAdditionals = $db->fetchAll(
    "SELECT pa.*, a.name, a.description, a.price, ac.name as category_name
     FROM product_additionals pa
     JOIN additionals a ON pa.additional_id = a.id
     JOIN additional_categories ac ON a.category_id = ac.id
     WHERE pa.product_id = ?
     ORDER BY ac.sort_order, a.name", 
    [$productId]
);

// Get available additionals (not already assigned)
$availableAdditionals = $db->fetchAll(
    "SELECT a.*, ac.name as category_name
     FROM additionals a
     JOIN additional_categories ac ON a.category_id = ac.id
     WHERE a.status = 'active' 
     AND a.id NOT IN (
         SELECT additional_id FROM product_additionals WHERE product_id = ?
     )
     ORDER BY ac.sort_order, a.name", 
    [$productId]
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Tamaños y Adicionales - <?= htmlspecialchars($product['name']) ?></title>
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
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Productos</a></li>
                                <li class="breadcrumb-item active">Gestionar Detalles</li>
                            </ol>
                        </nav>
                        <h2 class="mb-1">
                            <i class="fas fa-cogs me-2 text-primary"></i>
                            Gestionar Tamaños y Adicionales
                        </h2>
                        <p class="text-muted mb-0">
                            Producto: <strong><?= htmlspecialchars($product['name']) ?></strong>
                            <?php if ($product['category_name']): ?>
                                - Categoría: <strong><?= htmlspecialchars($product['category_name']) ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver a Productos
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
            <!-- Product Sizes Section -->
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-expand-arrows-alt me-2"></i>
                            Tamaños del Producto
                        </h6>
                    </div>
                    <div class="card-body">
                        <!-- Add Size Form -->
                        <?php if (!empty($availableSizes)): ?>
                            <form method="POST" class="mb-4">
                                <input type="hidden" name="action" value="add_size">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <select name="size_id" class="form-select" required>
                                            <option value="">Seleccionar tamaño</option>
                                            <?php foreach ($availableSizes as $size): ?>
                                                <option value="<?= $size['id'] ?>">
                                                    <?= htmlspecialchars($size['name']) ?>
                                                    (x<?= $size['multiplier'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="number" name="price" class="form-control" 
                                               placeholder="Precio" step="0.01" min="0" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>

                        <!-- Current Sizes -->
                        <?php if (empty($productSizes)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-expand-arrows-alt fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay tamaños configurados</h5>
                                <p class="text-muted">Agrega tamaños para este producto</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Tamaño</th>
                                            <th>Multiplicador</th>
                                            <th>Precio</th>
                                            <th width="80">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($productSizes as $size): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($size['name']) ?></strong>
                                                    <?php if ($size['description']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($size['description']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>x<?= $size['multiplier'] ?></td>
                                                <td>$<?= number_format($size['price'], 2) ?></td>
                                                <td>
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('¿Eliminar este tamaño?')">
                                                        <input type="hidden" name="action" value="remove_size">
                                                        <input type="hidden" name="size_id" value="<?= $size['size_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
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

            <!-- Product Additionals Section -->
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-plus-circle me-2"></i>
                            Adicionales del Producto
                        </h6>
                    </div>
                    <div class="card-body">
                        <!-- Add Additional Form -->
                        <?php if (!empty($availableAdditionals)): ?>
                            <form method="POST" class="mb-4">
                                <input type="hidden" name="action" value="add_additional">
                                <div class="row g-2">
                                    <div class="col-md-7">
                                        <select name="additional_id" class="form-select" required>
                                            <option value="">Seleccionar adicional</option>
                                            <?php 
                                            $currentCategory = '';
                                            foreach ($availableAdditionals as $additional): 
                                                if ($currentCategory !== $additional['category_name']):
                                                    if ($currentCategory !== '') echo '</optgroup>';
                                                    echo '<optgroup label="' . htmlspecialchars($additional['category_name']) . '">';
                                                    $currentCategory = $additional['category_name'];
                                                endif;
                                            ?>
                                                <option value="<?= $additional['id'] ?>">
                                                    <?= htmlspecialchars($additional['name']) ?>
                                                    (+$<?= number_format($additional['price'], 2) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                            <?php if ($currentCategory !== '') echo '</optgroup>'; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_default" id="isDefault">
                                            <label class="form-check-label" for="isDefault">
                                                Por defecto
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>

                        <!-- Current Additionals -->
                        <?php if (empty($productAdditionals)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-plus-circle fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay adicionales configurados</h5>
                                <p class="text-muted">Agrega adicionales para este producto</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Adicional</th>
                                            <th>Precio</th>
                                            <th>Estado</th>
                                            <th width="120">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($productAdditionals as $additional): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($additional['name']) ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars($additional['category_name']) ?></small>
                                                    <?php if ($additional['description']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($additional['description']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>+$<?= number_format($additional['price'], 2) ?></td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle_default_additional">
                                                        <input type="hidden" name="additional_id" value="<?= $additional['additional_id'] ?>">
                                                        <input type="hidden" name="current_default" value="<?= $additional['is_default'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-<?= $additional['is_default'] ? 'success' : 'secondary' ?>">
                                                            <i class="fas fa-<?= $additional['is_default'] ? 'check' : 'times' ?>"></i>
                                                            <?= $additional['is_default'] ? 'Por defecto' : 'Opcional' ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('¿Eliminar este adicional?')">
                                                        <input type="hidden" name="action" value="remove_additional">
                                                        <input type="hidden" name="additional_id" value="<?= $additional['additional_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
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

    <?php include '../includes/footer.php'; ?>
</body>
</html>
