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
            case 'create':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $status = $_POST['status'] ?? 'active';
                
                if (empty($name)) {
                    $message = 'El nombre es obligatorio';
                    $messageType = 'danger';
                } else {
                try {
                    $db->insert('additional_categories', [
                        'name' => $name,
                        'description' => $description,
                        'status' => $status
                    ]);
                    
                    $message = 'Categoría de adicionales creada correctamente';
                } catch (Exception $e) {
                    $message = 'Error al crear la categoría: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                }
                break;
                
            case 'toggle_status':
                $id = (int)$_POST['id'];
                $currentStatus = $_POST['current_status'];
                $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
                
                $db->update('additional_categories', 
                    ['status' => $newStatus], 
                    'id = ?', 
                    [$id]
                );
                
                $message = 'Estado actualizado correctamente';
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Check if category has additionals
                $additionalCount = $db->fetchOne(
                    "SELECT COUNT(*) as count FROM additionals WHERE category_id = ?", 
                    [$id]
                )['count'];
                
                if ($additionalCount > 0) {
                    $message = 'No se puede eliminar la categoría porque tiene adicionales asociados';
                    $messageType = 'danger';
                } else {
                    $db->delete('additional_categories', 'id = ?', [$id]);
                    $message = 'Categoría eliminada correctamente';
                }
                break;
        }
    }
}

// Get categories with additional count
$categories = $db->fetchAll(
    "SELECT ac.*, 
            COUNT(a.id) as additional_count
     FROM additional_categories ac
     LEFT JOIN additionals a ON ac.id = a.category_id
     GROUP BY ac.id
     ORDER BY ac.name ASC"
);

$totalCategories = count($categories);
$activeCategories = count(array_filter($categories, fn($c) => $c['status'] === 'active'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías de Adicionales - Administración</title>
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
                            <i class="fas fa-layer-group me-2 text-primary"></i>
                            Categorías de Adicionales
                        </h2>
                        <p class="text-muted mb-0">
                            Organiza los adicionales en categorías para mejor gestión
                        </p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver a Adicionales
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
                                <i class="fas fa-layer-group fa-2x text-gray-300"></i>
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
                                    Adicionales Totales
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= array_sum(array_column($categories, 'additional_count')) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-plus-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Create Form -->
            <div class="col-lg-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-plus me-2"></i>
                            Nueva Categoría
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">
                                    Nombre <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       placeholder="Ej: Salsas, Bebidas, Extras..." 
                                       maxlength="100" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descripción</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3" maxlength="500" 
                                          placeholder="Descripción opcional..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Estado</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active">Activa</option>
                                    <option value="inactive">Inactiva</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>
                                Crear Categoría
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Categories List -->
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Lista de Categorías
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($categories)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-layer-group fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay categorías registradas</h5>
                                <p class="text-muted">Crea tu primera categoría usando el formulario</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Categoría</th>
                                            <th>Descripción</th>
                                            <th>Adicionales</th>
                                            <th>Estado</th>
                                            <th>Creado por</th>
                                            <th width="120">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <i class="fas fa-layer-group fa-2x text-primary"></i>
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
                                                        <?= $category['additional_count'] ?> adicionales
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
                                                    <small class="text-muted">
                                                        Sistema
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($category['additional_count'] == 0): ?>
                                                        <form method="POST" class="d-inline delete-form">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= $category['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-secondary" 
                                                                title="No se puede eliminar (tiene adicionales)" disabled>
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
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
