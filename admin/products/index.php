<?php
require_once '../config/auth.php';
$auth->requireLogin();
$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

$message = '';
$messageType = 'success';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_status':
                $id = (int)$_POST['id'];
                $currentStatus = $_POST['current_status'];
                $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
                $db->update('products', ['status' => $newStatus], 'id = ?', [$id]);
                $message = 'Estado del producto actualizado correctamente';
                break;
            case 'delete':
                $id = (int)$_POST['id'];
                $orderCount = $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE product_id = ?", [$id])['count'];
                if ($orderCount > 0) {
                    $message = 'No se puede eliminar el producto porque tiene órdenes asociadas';
                    $messageType = 'danger';
                } else {
                    $db->delete('product_sizes', 'product_id = ?', [$id]);
                    $db->delete('product_additionals', 'product_id = ?', [$id]);
                    $db->delete('product_size_prices', 'product_id = ?', [$id]);
                    $db->delete('products', 'id = ?', [$id]);
                    $message = 'Producto eliminado correctamente';
                }
                break;
            case 'bulk_action':
                $selectedIds = $_POST['selected_ids'] ?? [];
                $bulkAction = $_POST['bulk_action_type'];
                if (!empty($selectedIds)) {
                    $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
                    switch ($bulkAction) {
                        case 'activate':
                            $db->query("UPDATE products SET status = 'active' WHERE id IN ($placeholders)", $selectedIds);
                            $message = count($selectedIds) . ' productos activados';
                            break;
                        case 'deactivate':
                            $db->query("UPDATE products SET status = 'inactive' WHERE id IN ($placeholders)", $selectedIds);
                            $message = count($selectedIds) . ' productos desactivados';
                            break;
                        case 'move_category':
                            $newCategoryId = (int)$_POST['new_category_id'];
                            if ($newCategoryId > 0) {
                                $db->query("UPDATE products SET category_id = ? WHERE id IN ($placeholders)", array_merge([$newCategoryId], $selectedIds));
                                $message = count($selectedIds) . ' productos movidos a nueva categoría';
                            } else {
                                $message = 'Debe seleccionar una categoría válida';
                                $messageType = 'danger';
                            }
                            break;
                        case 'delete':
                            $productsWithOrders = $db->fetchAll(
                                "SELECT DISTINCT p.id, p.name FROM products p INNER JOIN order_items oi ON p.id = oi.product_id WHERE p.id IN ($placeholders)",
                                $selectedIds
                            );
                            if (!empty($productsWithOrders)) {
                                $productNames = array_column($productsWithOrders, 'name');
                                $message = 'No se pueden eliminar los siguientes productos porque tienen órdenes asociadas: ' . implode(', ', $productNames);
                                $messageType = 'danger';
                            } else {
                                $db->query("DELETE FROM product_images WHERE product_id IN ($placeholders)", $selectedIds);
                                $db->query("DELETE FROM product_size_prices WHERE product_id IN ($placeholders)", $selectedIds);
                                $db->query("DELETE FROM product_additionals WHERE product_id IN ($placeholders)", $selectedIds);
                                $db->query("DELETE FROM products WHERE id IN ($placeholders)", $selectedIds);
                                $message = count($selectedIds) . ' productos eliminados correctamente';
                            }
                            break;
                    }
                }
                break;
        }
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$categoryFilter = (int)($_GET['category'] ?? 0);

$whereConditions = [];
$params = [];
if (!empty($search)) {
    $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryFilter > 0) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $categoryFilter;
}
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$totalQuery = "SELECT COUNT(DISTINCT p.id) as total FROM products p LEFT JOIN categories c ON p.category_id = c.id $whereClause";
$totalResult = $db->fetchOne($totalQuery, $params);
$totalProducts = $totalResult['total'];
$totalPages = ceil($totalProducts / $limit);

$products = $db->fetchAll(
    "SELECT p.*, c.name as category_name, c.color as category_color, c.icon as category_icon, au.full_name as created_by_name,
            COUNT(DISTINCT oi.id) as order_count, COUNT(DISTINCT psp.size_id) as size_count, COUNT(DISTINCT pa.id) as additional_count,
            pi.image_path as first_image
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     LEFT JOIN admin_users au ON p.created_by = au.id
     LEFT JOIN order_items oi ON p.id = oi.product_id
     LEFT JOIN product_size_prices psp ON p.id = psp.product_id
     LEFT JOIN product_additionals pa ON p.id = pa.product_id
     LEFT JOIN (
         SELECT product_id, image_path, ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY created_at ASC) as rn
         FROM product_images
     ) pi ON p.id = pi.product_id AND pi.rn = 1
     $whereClause
     GROUP BY p.id, c.name, c.color, c.icon, au.full_name, pi.image_path
     ORDER BY p.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

$allProductsStats = $db->fetchOne(
    "SELECT COUNT(*) as total_products,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products,
            AVG(price) as avg_price,
            SUM(price * (SELECT COUNT(*) FROM order_items WHERE product_id = p.id)) as total_revenue
     FROM products p"
);

$activeProducts = $allProductsStats['active_products'];
$totalRevenue = $allProductsStats['total_revenue'] ?? 0;
$avgPrice = $allProductsStats['avg_price'] ?? 0;

$allCategories = $db->fetchAll("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>

    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1"><i class="fas fa-box me-2 text-primary"></i>Gestión de Productos</h2>
                <p class="text-muted mb-0">Administra el catálogo de productos del restaurante</p>
            </div>
            <a href="create.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Nuevo Producto</a>
        </div>
    </div>

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

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-left-primary shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Productos</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalProducts ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-success shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Productos Activos</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $activeProducts ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-info shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Precio Promedio</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        $<?= $totalProducts > 0 ? number_format(array_sum(array_column($products, 'price')) / $totalProducts, 2) : '0.00' ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-warning shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Ingresos Estimados</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?= number_format($totalRevenue, 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Buscar productos</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nombre o descripción...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="category" class="form-label">Filtrar por categoría</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($allCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i>Limpiar filtros</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Lista de Productos 
                        <?php if (!empty($search) || $categoryFilter > 0): ?>
                            <small class="text-muted">(<?= $totalProducts ?> resultado<?= $totalProducts != 1 ? 's' : '' ?> encontrado<?= $totalProducts != 1 ? 's' : '' ?>)</small>
                        <?php endif; ?>
                    </h6>
                    <form method="POST" class="d-inline" id="bulkForm">
                        <input type="hidden" name="action" value="bulk_action">
                        <div class="input-group input-group-sm">
                            <select name="bulk_action_type" class="form-select" required id="bulkActionSelect">
                                <option value="">Acciones masivas</option>
                                <option value="activate">Activar seleccionados</option>
                                <option value="deactivate">Desactivar seleccionados</option>
                                <option value="move_category">Mover a categoría</option>
                                <option value="delete">Eliminar seleccionados</option>
                            </select>
                            <select name="new_category_id" class="form-select" id="categorySelect" style="display: none;">
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($allCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-outline-primary"><i class="fas fa-play"></i></button>
                        </div>
                    </form>
                </div>
                <div class="card-body">
                    <?php if (empty($products)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-box fa-3x text-gray-300 mb-3"></i>
                            <h5 class="text-gray-600">No hay productos registrados</h5>
                            <p class="text-muted">Comienza creando tu primer producto</p>
                            <a href="create.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Crear Primer Producto</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="50"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                        <th>Producto</th>
                                        <th>Categoría</th>
                                        <th>Precio</th>
                                        <th>Estado</th>
                                        <th>Detalles</th>
                                        <th>Órdenes</th>
                                        <th>Creado</th>
                                        <th width="180">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><input type="checkbox" name="selected_ids[]" value="<?= $product['id'] ?>" class="form-check-input product-checkbox"></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <?php 
                                                        $imageUrl = $product['first_image'] ?: $product['image'] ?: 'https://via.placeholder.com/50x50?text=IMG';
                                                        if ($product['first_image'] && !str_starts_with($product['first_image'], 'http')) {
                                                            $imageUrl = '/reserve/' . ltrim($product['first_image'], '/');
                                                        }
                                                        ?>
                                                        <img src="<?= $imageUrl ?>" class="rounded product-image" width="50" height="50" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='https://via.placeholder.com/50x50?text=IMG'">
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($product['name']) ?></h6>
                                                        <small class="text-muted">
                                                            ID: <?= $product['id'] ?>
                                                            <?php if ($product['description']): ?>
                                                                <br><?= htmlspecialchars(substr($product['description'], 0, 50)) ?>...
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($product['category_name']): ?>
                                                    <div class="d-flex align-items-center">
                                                        <i class="<?= $product['category_icon'] ?: 'fas fa-tag' ?> me-2" style="color: <?= $product['category_color'] ?>"></i>
                                                        <span><?= htmlspecialchars($product['category_name']) ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin categoría</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="fw-bold text-success">$<?= number_format($product['price'], 2) ?></span></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                                    <input type="hidden" name="current_status" value="<?= $product['status'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-<?= $product['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                        <i class="fas fa-<?= $product['status'] === 'active' ? 'check' : 'times' ?>"></i>
                                                        <?= ucfirst($product['status']) ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <?php if ($product['size_count'] > 0): ?>
                                                        <small class="badge bg-secondary mb-1"><?= $product['size_count'] ?> tamaños</small>
                                                    <?php endif; ?>
                                                    <?php if ($product['additional_count'] > 0): ?>
                                                        <small class="badge bg-info"><?= $product['additional_count'] ?> adicionales</small>
                                                    <?php endif; ?>
                                                    <?php if ($product['size_count'] == 0 && $product['additional_count'] == 0): ?>
                                                        <small class="text-muted">Sin detalles</small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-success"><?= $product['order_count'] ?> órdenes</span></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y', strtotime($product['created_at'])) ?><br>
                                                    <?= htmlspecialchars($product['created_by_name'] ?: 'Sistema') ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fas fa-edit"></i></a>
                                                    <button type="button" class="btn btn-sm btn-outline-info" title="Gestionar imágenes" onclick="openImageModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>')"><i class="fas fa-images"></i></button>
                                                    <a href="details.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Gestionar tamaños y adicionales"><i class="fas fa-cogs"></i></a>
                                                    <?php if ($product['order_count'] == 0): ?>
                                                        <form method="POST" class="d-inline delete-form">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-secondary" title="No se puede eliminar (tiene órdenes)" disabled><i class="fas fa-trash"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div class="text-muted">
                                    Mostrando <?= ($offset + 1) ?> - <?= min($offset + $limit, $totalProducts) ?> de <?= $totalProducts ?> productos
                                </div>
                                <nav aria-label="Paginación de productos">
                                    <ul class="pagination mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $categoryFilter > 0 ? '&category=' . $categoryFilter : '' ?>"><i class="fas fa-chevron-left"></i></a></li>
                                        <?php else: ?>
                                            <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i></span></li>
                                        <?php endif; ?>
                                        <?php
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);
                                        if ($startPage > 1): ?>
                                            <li class="page-item"><a class="page-link" href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $categoryFilter > 0 ? '&category=' . $categoryFilter : '' ?>">1</a></li>
                                            <?php if ($startPage > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $categoryFilter > 0 ? '&category=' . $categoryFilter : '' ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <?php if ($endPage < $totalPages): ?>
                                            <?php if ($endPage < $totalPages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            <li class="page-item"><a class="page-link" href="?page=<?= $totalPages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $categoryFilter > 0 ? '&category=' . $categoryFilter : '' ?>"><?= $totalPages ?></a></li>
                                        <?php endif; ?>
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $categoryFilter > 0 ? '&category=' . $categoryFilter : '' ?>"><i class="fas fa-chevron-right"></i></a></li>
                                        <?php else: ?>
                                            <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-right"></i></span></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Gestión de Imágenes -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel"><i class="fas fa-images me-2"></i>Gestión de Imágenes - <span id="modalProductName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalProductId">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-cloud-upload-alt me-2"></i>Subir Nuevas Imágenes</h6>
                        </div>
                        <div class="card-body">
                            <form id="uploadForm" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="imageFiles" class="form-label">Seleccionar imágenes (JPG, PNG, GIF, WEBP - Máx 5MB c/u)</label>
                                    <input type="file" class="form-control" id="imageFiles" name="image[]" accept="image/*" multiple required>
                                    <div class="form-text">Puedes seleccionar múltiples imágenes a la vez.</div>
                                </div>
                                <button type="submit" class="btn btn-primary" id="uploadBtn"><i class="fas fa-upload me-2"></i>Subir Imágenes</button>
                            </form>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-images me-2"></i>Imágenes Existentes</h6>
                        </div>
                        <div class="card-body">
                            <div class="row" id="imageGallery"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/custom-modals.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Select all functionality
        const selectAllElement = document.getElementById('selectAll');
        if (selectAllElement) {
            selectAllElement.addEventListener('change', function() {
                document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = this.checked);
            });
        }
        document.querySelectorAll('.product-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                const all = document.querySelectorAll('.product-checkbox');
                const checked = document.querySelectorAll('.product-checkbox:checked');
                if (selectAllElement) {
                    selectAllElement.checked = all.length === checked.length;
                    selectAllElement.indeterminate = checked.length > 0 && checked.length < all.length;
                }
            });
        });
        // Show/hide category selector
        const bulkActionSelect = document.getElementById('bulkActionSelect');
        if (bulkActionSelect) {
            bulkActionSelect.addEventListener('change', function() {
                const categorySelect = document.getElementById('categorySelect');
                if (categorySelect) {
                    categorySelect.style.display = this.value === 'move_category' ? 'block' : 'none';
                    categorySelect.required = this.value === 'move_category';
                }
            });
        }
        // Bulk actions form submission
        const bulkForm = document.getElementById('bulkForm');
        if (bulkForm) {
            bulkForm.addEventListener('submit', function(e) {
                const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    e.preventDefault();
                    alert('Por favor selecciona al menos un producto');
                    return;
                }
                const bulkAction = document.getElementById('bulkActionSelect').value;
                if (bulkAction === 'move_category') {
                    const categoryId = document.getElementById('categorySelect').value;
                    if (!categoryId) {
                        e.preventDefault();
                        alert('Por favor selecciona una categoría de destino');
                        return;
                    }
                }
                if (bulkAction === 'delete') {
                    e.preventDefault();
                    showBulkDeleteConfirmation(checkedBoxes);
                    return;
                }
                checkedBoxes.forEach(cb => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_ids[]';
                    input.value = cb.value;
                    this.appendChild(input);
                });
            });
        }
        // Manejar subida de imágenes
        const uploadForm = document.getElementById('uploadForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'upload_images');
                formData.append('product_id', document.getElementById('modalProductId').value);
                const uploadBtn = document.getElementById('uploadBtn');
                const originalText = uploadBtn.innerHTML;
                uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
                uploadBtn.disabled = true;
                try {
                    const response = await fetch('upload-images.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    if (data.success) {
                        showSuccessToast(data.message);
                        this.reset();
                        loadImages(document.getElementById('modalProductId').value);
                    } else {
                        showErrorToast(data.message || 'Error al subir imágenes');
                    }
                } catch (error) {
                    showErrorToast('Error al subir imágenes');
                } finally {
                    uploadBtn.innerHTML = originalText;
                    uploadBtn.disabled = false;
                }
            });
        }
    });

    function openImageModal(productId, productName) {
        document.getElementById('modalProductId').value = productId;
        document.getElementById('modalProductName').textContent = productName;
        // Limpia la galería antes de cargar
        document.getElementById('imageGallery').innerHTML = '<div class="text-center text-muted">Cargando imágenes...</div>';
        // Abre el modal
        var imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
        imageModal.show();
        // Carga las imágenes del producto
        loadImages(productId);
    }

    // Cargar imágenes existentes vía AJAX
    function loadImages(productId) {
        fetch('upload-images.php?action=get_images&id=' + productId)
            .then(response => response.json())
            .then(data => {
                const gallery = document.getElementById('imageGallery');
                if (data.success && data.images.length > 0) {
                    gallery.innerHTML = '';
                    data.images.forEach(img => {
                        const col = document.createElement('div');
                        col.className = 'col-md-3 mb-3';
                        col.innerHTML = `
                            <div class="card">
                                <img src="/reserve/${img.image_path}" class="card-img-top" style="height: 150px; object-fit: cover;">
                                <div class="card-body p-2">
                                    <small>
                                        <strong>ID:</strong> ${img.id}<br>
                                        <strong>Archivo:</strong> ${img.original_name}<br>
                                        <strong>Tamaño:</strong> ${(img.file_size/1024).toFixed(1)} KB<br>
                                        <strong>Fecha:</strong> ${img.created_at}<br>
                                        <button class="btn btn-sm btn-danger mt-1" onclick="deleteImage(${img.id}, ${productId})"><i class="fas fa-trash"></i> Eliminar</button>
                                    </small>
                                </div>
                            </div>
                        `;
                        gallery.appendChild(col);
                    });
                } else {
                    gallery.innerHTML = '<div class="text-center text-muted">No hay imágenes</div>';
                }
            });
    }

    // Eliminar imagen vía AJAX
    async function deleteImage(imageId, productId) {
        const confirmed = await confirmDelete('¿Estás seguro de eliminar esta imagen? Esta acción no se puede deshacer.');
        if (!confirmed) return;
        
        fetch('upload-images.php', {
            method: 'POST',
            body: new URLSearchParams({
                action: 'delete_image',
                image_id: imageId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess('Imagen eliminada correctamente');
                loadImages(productId);
            } else {
                showError(data.message || 'Error al eliminar la imagen');
            }
        })
        .catch(error => {
            showError('Error al eliminar la imagen');
        });
    }

    function showErrorToast(message) {
        let toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-danger border-0 position-fixed bottom-0 end-0 m-4';
        toast.role = 'alert';
        toast.ariaLive = 'assertive';
        toast.ariaAtomic = 'true';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-exclamation-triangle me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        document.body.appendChild(toast);
        let bsToast = new bootstrap.Toast(toast, { delay: 4000 });
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    function showSuccessToast(message) {
        let toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed bottom-0 end-0 m-4';
        toast.role = 'alert';
        toast.ariaLive = 'assertive';
        toast.ariaAtomic = 'true';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        document.body.appendChild(toast);
        let bsToast = new bootstrap.Toast(toast, { delay: 4000 });
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }
    </script>
</body>
</html>