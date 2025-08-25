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
            case 'toggle_status':
                $id = (int)$_POST['id'];
                $currentStatus = $_POST['current_status'];
                $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
                
                $db->update('categories', 
                    ['status' => $newStatus], 
                    'id = ?', 
                    [$id]
                );
                
                $message = 'Estado de categoría actualizado correctamente';
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Check if category has products
                $productCount = $db->fetchOne(
                    "SELECT COUNT(*) as count FROM products WHERE category_id = ?", 
                    [$id]
                )['count'];
                
                if ($productCount > 0) {
                    $message = 'No se puede eliminar la categoría porque tiene productos asociados';
                    $messageType = 'danger';
                } else {
                    $db->delete('categories', 'id = ?', [$id]);
                    $message = 'Categoría eliminada correctamente';
                }
                break;
                
            case 'bulk_action':
                $selectedIds = $_POST['selected_ids'] ?? [];
                $bulkAction = $_POST['bulk_action_type'];
                
                if (!empty($selectedIds)) {
                    $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
                    
                    switch ($bulkAction) {
                        case 'activate':
                            $db->query(
                                "UPDATE categories SET status = 'active' WHERE id IN ($placeholders)",
                                $selectedIds
                            );
                            $message = count($selectedIds) . ' categorías activadas';
                            break;
                            
                        case 'deactivate':
                            $db->query(
                                "UPDATE categories SET status = 'inactive' WHERE id IN ($placeholders)",
                                $selectedIds
                            );
                            $message = count($selectedIds) . ' categorías desactivadas';
                            break;
                    }
                }
                break;
        }
    }
}

// Get categories with product count
$categories = $db->fetchAll(
    "SELECT c.*, 
            COUNT(p.id) as product_count,
            au.full_name as created_by_name
     FROM categories c
     LEFT JOIN products p ON c.id = p.category_id
     LEFT JOIN admin_users au ON c.created_by = au.id
     GROUP BY c.id
     ORDER BY c.sort_order ASC, c.name ASC"
);

$totalCategories = count($categories);
$activeCategories = count(array_filter($categories, fn($c) => $c['status'] === 'active'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías - Administración</title>
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
                            <i class="fas fa-tags me-2 text-primary"></i>
                            Gestión de Categorías
                        </h2>
                        <p class="text-muted mb-0">
                            Administra las categorías de productos del restaurante
                        </p>
                    </div>
                    <div>
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>
                            Nueva Categoría
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

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-left-primary shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Categorías
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $totalCategories ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-tags fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-left-success shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Categorías Activas
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $activeCategories ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-left-info shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Productos Totales
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= array_sum(array_column($categories, 'product_count')) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-box fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categories Table -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    Lista de Categorías
                                </h6>
                            </div>
                            <div class="col-auto">
                                <form method="POST" class="d-inline" id="bulkForm">
                                    <input type="hidden" name="action" value="bulk_action">
                                    <div class="input-group input-group-sm">
                                        <select name="bulk_action_type" class="form-select" required>
                                            <option value="">Acciones masivas</option>
                                            <option value="activate">Activar seleccionadas</option>
                                            <option value="deactivate">Desactivar seleccionadas</option>
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
                        <?php if (empty($categories)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-tags fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay categorías registradas</h5>
                                <p class="text-muted">Comienza creando tu primera categoría</p>
                                <a href="create.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>
                                    Crear Primera Categoría
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
                                            <th>Categoría</th>
                                            <th>Descripción</th>
                                            <th>Productos</th>
                                            <th>Estado</th>
                                            <th>Orden</th>
                                            <th>Creado por</th>
                                            <th width="150">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_ids[]" 
                                                           value="<?= $category['id'] ?>" 
                                                           class="form-check-input category-checkbox">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <i class="<?= $category['icon'] ?: 'fas fa-tag' ?> fa-2x" 
                                                               style="color: <?= $category['color'] ?>"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?= htmlspecialchars($category['name']) ?></h6>
                                                            <small class="text-muted">ID: <?= $category['id'] ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="text-muted">
                                                        <?= htmlspecialchars($category['description'] ?: 'Sin descripción') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?= $category['product_count'] ?> productos
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="id" value="<?= $category['id'] ?>">
                                                        <input type="hidden" name="current_status" value="<?= $category['status'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-<?= $category['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                            <i class="fas fa-<?= $category['status'] === 'active' ? 'check' : 'times' ?>"></i>
                                                            <?= ucfirst($category['status']) ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= $category['sort_order'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($category['created_by_name'] ?: 'Sistema') ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="edit.php?id=<?= $category['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($category['product_count'] == 0): ?>
                                                            <form method="POST" class="d-inline delete-form">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="id" value="<?= $category['id'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-outline-secondary" 
                                                                    title="No se puede eliminar (tiene productos)" disabled>
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
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
            const checkboxes = document.querySelectorAll('.category-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all when individual checkboxes change
        document.querySelectorAll('.category-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.category-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.category-checkbox:checked');
                const selectAll = document.getElementById('selectAll');
                
                selectAll.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAll.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            });
        });

        // Bulk actions form submission
        document.getElementById('bulkForm').addEventListener('submit', async function(e) {
            const checkedBoxes = document.querySelectorAll('.category-checkbox:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                await customModals.alert('Por favor selecciona al menos una categoría', 'warning');
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

    <?php include '../includes/footer.php'; ?>
</body>
</html>
