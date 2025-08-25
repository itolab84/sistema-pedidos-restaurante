<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Handle form submission
$message = '';
$messageType = 'success';

if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name)) {
        $message = 'El nombre es obligatorio';
        $messageType = 'danger';
    } elseif ($price < 0) {
        $message = 'El precio no puede ser negativo';
        $messageType = 'danger';
    } else {
        try {
            $db->insert('additionals', [
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $message = 'Adicional creado correctamente';
            
            // Clear form
            $_POST = [];
        } catch (Exception $e) {
            $message = 'Error al crear el adicional: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get categories for dropdown
$categories = $db->fetchAll(
    "SELECT id, name FROM additional_categories WHERE status = 'active' ORDER BY name ASC"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Adicional - Administración</title>
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
                            <i class="fas fa-plus me-2 text-primary"></i>
                            Crear Nuevo Adicional
                        </h2>
                        <p class="text-muted mb-0">
                            Agrega un nuevo adicional al catálogo
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

        <!-- Create Form -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-plus me-2"></i>
                            Información del Adicional
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">
                                            Nombre <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                               placeholder="Ej: Queso extra, Salsa picante..." 
                                               maxlength="100" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="price" class="form-label">
                                            Precio <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" id="price" name="price" 
                                                   value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                                                   step="0.01" min="0" max="999.99" 
                                                   placeholder="0.00" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Categoría</label>
                                        <select class="form-select" id="category_id" name="category_id">
                                            <option value="">Sin categoría</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= $category['id'] ?>" 
                                                        <?= ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">
                                            <a href="categories.php" class="text-decoration-none">
                                                <i class="fas fa-plus me-1"></i>Gestionar categorías
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Estado</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>
                                                Activo
                                            </option>
                                            <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>
                                                Inactivo
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descripción</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3" maxlength="500" 
                                          placeholder="Descripción opcional del adicional..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <div class="form-text">Máximo 500 caracteres</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <a href="index.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-2"></i>
                                            Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>
                                            Crear Adicional
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Help Card -->
        <div class="row justify-content-center mt-4">
            <div class="col-lg-8">
                <div class="card shadow border-left-info">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <i class="fas fa-info-circle fa-2x text-info"></i>
                            </div>
                            <div class="col">
                                <h6 class="mb-1 text-info">Consejos para crear adicionales</h6>
                                <ul class="mb-0 small text-muted">
                                    <li>Usa nombres descriptivos y claros</li>
                                    <li>Establece precios competitivos</li>
                                    <li>Agrupa adicionales similares en categorías</li>
                                    <li>Mantén las descripciones breves pero informativas</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const price = parseFloat(document.getElementById('price').value);
            
            if (!name) {
                e.preventDefault();
                customAlert('El nombre es obligatorio', 'error');
                return;
            }
            
            if (isNaN(price) || price < 0) {
                e.preventDefault();
                customAlert('El precio debe ser un número válido mayor o igual a 0', 'error');
                return;
            }
        });
        
        // Character counter for description
        const descriptionField = document.getElementById('description');
        const maxLength = 500;
        
        descriptionField.addEventListener('input', function() {
            const remaining = maxLength - this.value.length;
            const formText = this.nextElementSibling;
            formText.textContent = `${remaining} caracteres restantes`;
            
            if (remaining < 50) {
                formText.classList.add('text-warning');
            } else {
                formText.classList.remove('text-warning');
            }
            
            if (remaining < 0) {
                formText.classList.add('text-danger');
                formText.classList.remove('text-warning');
            } else {
                formText.classList.remove('text-danger');
            }
        });
        
        // Auto-focus on name field
        document.getElementById('name').focus();
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
