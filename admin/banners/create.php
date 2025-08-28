<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        $db->query("START TRANSACTION");
        
        // Validate required fields
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $position = $_POST['position'];
        $status = $_POST['status'];
        $linkType = $_POST['link_type'];
        $sortOrder = (int)$_POST['sort_order'];
        
        if (empty($title)) {
            throw new Exception('El título es requerido');
        }
        
        // Handle image upload
        $imageUrl = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../assets/images/banners/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new Exception('Formato de imagen no válido. Use: ' . implode(', ', $allowedExtensions));
            }
            
            // Generate unique filename
            $fileName = 'banner_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
                throw new Exception('Error al subir la imagen');
            }
            
            $imageUrl = '/reserve/assets/images/banners/' . $fileName;
        } else {
            throw new Exception('La imagen es requerida');
        }
        
        // Handle link configuration
        $productId = null;
        $externalUrl = null;
        
        if ($linkType === 'product') {
            $productId = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
            if (!$productId) {
                throw new Exception('Debe seleccionar un producto');
            }
        } elseif ($linkType === 'url') {
            $externalUrl = trim($_POST['external_url']);
            if (empty($externalUrl)) {
                throw new Exception('Debe ingresar una URL externa');
            }
            if (!filter_var($externalUrl, FILTER_VALIDATE_URL)) {
                throw new Exception('La URL externa no es válida');
            }
        }
        
        // Handle dates
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
            throw new Exception('La fecha de inicio no puede ser posterior a la fecha de fin');
        }
        
        // Insert banner
        $bannerId = $db->insert('banners', [
            'title' => $title,
            'description' => $description,
            'image_url' => $imageUrl,
            'link_type' => $linkType,
            'product_id' => $productId,
            'external_url' => $externalUrl,
            'position' => $position,
            'status' => $status,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'sort_order' => $sortOrder
        ]);
        
        $db->query("COMMIT");
        
        // Redirect to index with success message
        header('Location: index.php?success=created');
        exit;
        
    } catch (Exception $e) {
        $db->query("ROLLBACK");
        $error = $e->getMessage();
    }
}

// Get products for dropdown
$products = $db->fetchAll("SELECT id, name FROM products WHERE status = 'active' ORDER BY name");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Banner - Administración</title>
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
                            <i class="fas fa-plus me-2 text-primary"></i>
                            Crear Nuevo Banner
                        </h2>
                        <p class="text-muted mb-0">
                            Crea un nuevo banner publicitario para el sitio web
                        </p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Create Form -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-edit me-2"></i>Información del Banner
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Título *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Descripción</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"
                                                  placeholder="Descripción opcional del banner"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Imagen del Banner *</label>
                                        <input type="file" class="form-control" id="image" name="image" 
                                               accept="image/*" required>
                                        <div class="form-text">
                                            Formatos permitidos: JPG, PNG, GIF, WebP. Tamaño recomendado: 1200x400px
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Configuration -->
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="position" class="form-label">Posición *</label>
                                        <select class="form-select" id="position" name="position" required>
                                            <option value="">Seleccionar posición...</option>
                                            <option value="hero" <?= ($_POST['position'] ?? '') === 'hero' ? 'selected' : '' ?>>Hero (Principal)</option>
                                            <option value="sidebar" <?= ($_POST['position'] ?? '') === 'sidebar' ? 'selected' : '' ?>>Sidebar (Lateral)</option>
                                            <option value="footer" <?= ($_POST['position'] ?? '') === 'footer' ? 'selected' : '' ?>>Footer (Pie)</option>
                                            <option value="popup" <?= ($_POST['position'] ?? '') === 'popup' ? 'selected' : '' ?>>Popup (Emergente)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Estado *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Activo</option>
                                            <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="sort_order" class="form-label">Orden de Visualización</label>
                                        <input type="number" class="form-control" id="sort_order" name="sort_order" 
                                               value="<?= htmlspecialchars($_POST['sort_order'] ?? '0') ?>" min="0">
                                        <div class="form-text">
                                            Menor número = mayor prioridad
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Link Configuration -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h5 class="mb-3">
                                        <i class="fas fa-link me-2"></i>Configuración de Enlace
                                    </h5>
                                    
                                    <div class="mb-3">
                                        <label for="link_type" class="form-label">Tipo de Enlace</label>
                                        <select class="form-select" id="link_type" name="link_type" onchange="toggleLinkFields()">
                                            <option value="none" <?= ($_POST['link_type'] ?? 'none') === 'none' ? 'selected' : '' ?>>Sin enlace</option>
                                            <option value="product" <?= ($_POST['link_type'] ?? '') === 'product' ? 'selected' : '' ?>>Enlazar a producto</option>
                                            <option value="url" <?= ($_POST['link_type'] ?? '') === 'url' ? 'selected' : '' ?>>URL externa</option>
                                        </select>
                                    </div>
                                    
                                    <div id="product_field" class="mb-3" style="display: none;">
                                        <label for="product_id" class="form-label">Producto</label>
                                        <select class="form-select" id="product_id" name="product_id">
                                            <option value="">Seleccionar producto...</option>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?= $product['id'] ?>" 
                                                        <?= ($_POST['product_id'] ?? '') == $product['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($product['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div id="url_field" class="mb-3" style="display: none;">
                                        <label for="external_url" class="form-label">URL Externa</label>
                                        <input type="url" class="form-control" id="external_url" name="external_url" 
                                               value="<?= htmlspecialchars($_POST['external_url'] ?? '') ?>"
                                               placeholder="https://ejemplo.com">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Date Configuration -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h5 class="mb-3">
                                        <i class="fas fa-calendar me-2"></i>Programación (Opcional)
                                    </h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="start_date" class="form-label">Fecha de Inicio</label>
                                                <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                                       value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                                                <div class="form-text">
                                                    Dejar vacío para activar inmediatamente
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="end_date" class="form-label">Fecha de Fin</label>
                                                <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                                       value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
                                                <div class="form-text">
                                                    Dejar vacío para no tener fecha de expiración
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <hr>
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="index.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-2"></i>Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Crear Banner
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleLinkFields() {
            const linkType = document.getElementById('link_type').value;
            const productField = document.getElementById('product_field');
            const urlField = document.getElementById('url_field');
            
            // Hide all fields first
            productField.style.display = 'none';
            urlField.style.display = 'none';
            
            // Show relevant field
            if (linkType === 'product') {
                productField.style.display = 'block';
            } else if (linkType === 'url') {
                urlField.style.display = 'block';
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleLinkFields();
        });
        
        // Image preview functionality
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Create preview if it doesn't exist
                    let preview = document.getElementById('image_preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.id = 'image_preview';
                        preview.className = 'mt-2';
                        e.target.parentNode.appendChild(preview);
                    }
                    
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Vista previa" 
                             class="img-thumbnail" style="max-width: 300px; max-height: 200px;">
                    `;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
