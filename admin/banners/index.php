<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Handle actions
$message = '';
$messageType = 'success';

if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'delete':
            $bannerId = (int)$_POST['banner_id'];
            try {
                $db->delete('banners', 'id = ?', [$bannerId]);
                $message = 'Banner eliminado correctamente';
            } catch (Exception $e) {
                $message = 'Error al eliminar banner: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'toggle_status':
            $bannerId = (int)$_POST['banner_id'];
            $currentStatus = $_POST['current_status'];
            $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
            
            try {
                $db->update('banners', ['status' => $newStatus], 'id = ?', [$bannerId]);
                $message = 'Estado del banner actualizado correctamente';
            } catch (Exception $e) {
                $message = 'Error al actualizar estado: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
    }
}

// Handle messages from URL parameters
if (isset($_GET['success'])) {
    $messageType = 'success';
    switch ($_GET['success']) {
        case 'created':
            $message = 'Banner creado correctamente';
            break;
        case 'updated':
            $message = 'Banner actualizado correctamente';
            break;
        default:
            $message = 'Operación completada correctamente';
    }
} elseif (isset($_GET['error'])) {
    $messageType = 'danger';
    $message = urldecode($_GET['error']);
}

// Get filters
$statusFilter = $_GET['status'] ?? '';
$positionFilter = $_GET['position'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($statusFilter) {
    $whereConditions[] = "b.status = ?";
    $params[] = $statusFilter;
}

if ($positionFilter) {
    $whereConditions[] = "b.position = ?";
    $params[] = $positionFilter;
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Get banners with product information
$banners = $db->fetchAll("
    SELECT b.*, p.name as product_name
    FROM banners b
    LEFT JOIN products p ON b.product_id = p.id
    {$whereClause}
    ORDER BY b.position, b.sort_order, b.created_at DESC
", $params);

// Get statistics
$stats = $db->fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(click_count) as total_clicks,
        SUM(view_count) as total_views
    FROM banners
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Banners - Administración</title>
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
                            <i class="fas fa-images me-2 text-primary"></i>
                            Gestión de Banners
                        </h2>
                        <p class="text-muted mb-0">
                            Administra los banners publicitarios del sitio web
                        </p>
                    </div>
                    <div>
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Nuevo Banner
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
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Banners
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['total']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-images fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Banners Activos
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['active']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Total Clics
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['total_clicks']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-mouse-pointer fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Total Vistas
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['total_views']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-eye fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-filter me-2"></i>Filtros
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">Estado</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Todos los estados</option>
                                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Activo</option>
                                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="position" class="form-label">Posición</label>
                                <select class="form-select" id="position" name="position">
                                    <option value="">Todas las posiciones</option>
                                    <option value="hero" <?= $positionFilter === 'hero' ? 'selected' : '' ?>>Hero</option>
                                    <option value="sidebar" <?= $positionFilter === 'sidebar' ? 'selected' : '' ?>>Sidebar</option>
                                    <option value="footer" <?= $positionFilter === 'footer' ? 'selected' : '' ?>>Footer</option>
                                    <option value="popup" <?= $positionFilter === 'popup' ? 'selected' : '' ?>>Popup</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Filtrar
                                    </button>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Banners Table -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>Lista de Banners
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($banners)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-images fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay banners registrados</h5>
                                <p class="text-gray-500">Comienza creando tu primer banner publicitario</p>
                                <a href="create.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Crear Banner
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Vista Previa</th>
                                            <th>Título</th>
                                            <th>Posición</th>
                                            <th>Enlace</th>
                                            <th>Estado</th>
                                            <th>Estadísticas</th>
                                            <th>Fechas</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($banners as $banner): ?>
                                            <tr>
                                                <td>
                                                    <div class="banner-preview">
                                                        <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . $banner['image_url'])): ?>
                                                            <img src="<?= htmlspecialchars($banner['image_url']) ?>" 
                                                                 alt="<?= htmlspecialchars($banner['title']) ?>" 
                                                                 class="img-thumbnail" 
                                                                 style="max-width: 100px; max-height: 60px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light border rounded d-flex align-items-center justify-content-center" 
                                                                 style="width: 100px; height: 60px;">
                                                                <i class="fas fa-image text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($banner['title']) ?></h6>
                                                        <?php if ($banner['description']): ?>
                                                            <small class="text-muted">
                                                                <?= htmlspecialchars(substr($banner['description'], 0, 50)) ?>
                                                                <?= strlen($banner['description']) > 50 ? '...' : '' ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?= ucfirst($banner['position']) ?>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">Orden: <?= $banner['sort_order'] ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($banner['link_type'] === 'product' && $banner['product_name']): ?>
                                                        <span class="badge bg-success">Producto</span>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($banner['product_name']) ?></small>
                                                    <?php elseif ($banner['link_type'] === 'url' && $banner['external_url']): ?>
                                                        <span class="badge bg-warning">URL Externa</span>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars(substr($banner['external_url'], 0, 30)) ?>
                                                            <?= strlen($banner['external_url']) > 30 ? '...' : '' ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Sin enlace</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="banner_id" value="<?= $banner['id'] ?>">
                                                        <input type="hidden" name="current_status" value="<?= $banner['status'] ?>">
                                                        <button type="submit" class="btn btn-sm <?= $banner['status'] === 'active' ? 'btn-success' : 'btn-secondary' ?>">
                                                            <i class="fas fa-<?= $banner['status'] === 'active' ? 'check' : 'times' ?>"></i>
                                                            <?= ucfirst($banner['status']) ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <i class="fas fa-mouse-pointer"></i> <?= number_format($banner['click_count']) ?> clics
                                                        <br>
                                                        <i class="fas fa-eye"></i> <?= number_format($banner['view_count']) ?> vistas
                                                    </small>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php if ($banner['start_date']): ?>
                                                            <i class="fas fa-play"></i> <?= date('d/m/Y', strtotime($banner['start_date'])) ?>
                                                            <br>
                                                        <?php endif; ?>
                                                        <?php if ($banner['end_date']): ?>
                                                            <i class="fas fa-stop"></i> <?= date('d/m/Y', strtotime($banner['end_date'])) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="edit.php?id=<?= $banner['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteBanner(<?= $banner['id'] ?>, '<?= htmlspecialchars($banner['title']) ?>')"
                                                                title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
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
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de que desea eliminar el banner <strong id="bannerTitle"></strong>?</p>
                    <p class="text-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" id="deleteForm" class="d-inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="banner_id" id="deleteBannerId">
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
        function deleteBanner(bannerId, bannerTitle) {
            document.getElementById('deleteBannerId').value = bannerId;
            document.getElementById('bannerTitle').textContent = bannerTitle;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
