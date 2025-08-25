<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

$errors = [];
$success = '';

if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $color = trim($_POST['color'] ?? '#007bff');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($name)) {
        $errors[] = 'El nombre de la categoría es obligatorio';
    } elseif (strlen($name) > 100) {
        $errors[] = 'El nombre no puede exceder 100 caracteres';
    }
    
    if (strlen($description) > 500) {
        $errors[] = 'La descripción no puede exceder 500 caracteres';
    }
    
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $errors[] = 'El color debe ser un código hexadecimal válido';
    }
    
    // Check if name already exists
    if (empty($errors)) {
        $existing = $db->fetchOne(
            "SELECT id FROM categories WHERE name = ?", 
            [$name]
        );
        
        if ($existing) {
            $errors[] = 'Ya existe una categoría con ese nombre';
        }
    }
    
    // Insert category
    if (empty($errors)) {
        try {
            $categoryId = $db->insert('categories', [
                'name' => $name,
                'description' => $description,
                'icon' => $icon,
                'color' => $color,
                'sort_order' => $sort_order,
                'status' => $status,
                'created_by' => $user['id']
            ]);
            
            header('Location: index.php?message=category_created&id=' . $categoryId);
            exit;
            
        } catch (Exception $e) {
            $errors[] = 'Error al crear la categoría: ' . $e->getMessage();
        }
    }
}

// Get next sort order
$nextSortOrder = $db->fetchOne("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM categories")['next_order'] ?? 1;

// Common icons for categories
$commonIcons = [
    'fas fa-hamburger' => 'Hamburguesa',
    'fas fa-pizza-slice' => 'Pizza',
    'fas fa-leaf' => 'Ensalada',
    'fas fa-glass-cheers' => 'Bebidas',
    'fas fa-ice-cream' => 'Postres',
    'fas fa-coffee' => 'Café',
    'fas fa-fish' => 'Pescado',
    'fas fa-drumstick-bite' => 'Pollo',
    'fas fa-bread-slice' => 'Pan',
    'fas fa-cheese' => 'Queso',
    'fas fa-apple-alt' => 'Frutas',
    'fas fa-carrot' => 'Vegetales',
    'fas fa-wine-glass' => 'Vinos',
    'fas fa-birthday-cake' => 'Pasteles',
    'fas fa-cookie-bite' => 'Galletas'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Categoría - Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-utensils me-2"></i>
                Restaurante Admin
            </a>
            
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="index.php">
                    <i class="fas fa-tags me-1"></i>Categorías
                </a>
            </div>
            
            <div class="navbar-nav">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?= htmlspecialchars($user['full_name']) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-plus-circle me-2 text-primary"></i>
                            Nueva Categoría
                        </h2>
                        <p class="text-muted mb-0">
                            Crea una nueva categoría para organizar tus productos
                        </p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver a Categorías
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Errors -->
        <?php if (!empty($errors)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Por favor corrige los siguientes errores:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-edit me-2"></i>
                            Información de la Categoría
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">
                                            Nombre de la Categoría <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                                               placeholder="Ej: Hamburguesas, Pizzas, Bebidas..." 
                                               maxlength="100" required>
                                        <div class="form-text">Máximo 100 caracteres</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Estado</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>
                                                Activa
                                            </option>
                                            <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>
                                                Inactiva
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Descripción</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3" maxlength="500" 
                                          placeholder="Descripción opcional de la categoría..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <div class="form-text">Máximo 500 caracteres</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="icon" class="form-label">Icono</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i id="iconPreview" class="<?= $_POST['icon'] ?? 'fas fa-tag' ?>"></i>
                                            </span>
                                            <select class="form-select" id="icon" name="icon">
                                                <option value="">Seleccionar icono</option>
                                                <?php foreach ($commonIcons as $iconClass => $iconName): ?>
                                                    <option value="<?= $iconClass ?>" 
                                                            <?= ($_POST['icon'] ?? '') === $iconClass ? 'selected' : '' ?>>
                                                        <?= $iconName ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-text">Selecciona un icono representativo</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="color" class="form-label">Color</label>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color" 
                                                   id="color" name="color" 
                                                   value="<?= $_POST['color'] ?? '#007bff' ?>" 
                                                   title="Seleccionar color">
                                            <input type="text" class="form-control" id="colorText" 
                                                   value="<?= $_POST['color'] ?? '#007bff' ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="sort_order" class="form-label">Orden</label>
                                        <input type="number" class="form-control" id="sort_order" name="sort_order" 
                                               value="<?= $_POST['sort_order'] ?? $nextSortOrder ?>" 
                                               min="0" max="999">
                                        <div class="form-text">Orden de visualización</div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>
                                    Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    Crear Categoría
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Preview -->
            <div class="col-lg-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-eye me-2"></i>
                            Vista Previa
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center">
                            <div class="mb-3">
                                <i id="previewIcon" class="<?= $_POST['icon'] ?? 'fas fa-tag' ?> fa-4x" 
                                   style="color: <?= $_POST['color'] ?? '#007bff' ?>"></i>
                            </div>
                            <h5 id="previewName"><?= htmlspecialchars($_POST['name'] ?? 'Nombre de la categoría') ?></h5>
                            <p class="text-muted" id="previewDescription">
                                <?= htmlspecialchars($_POST['description'] ?? 'Descripción de la categoría') ?>
                            </p>
                            <span class="badge bg-<?= ($_POST['status'] ?? 'active') === 'active' ? 'success' : 'secondary' ?>" 
                                  id="previewStatus">
                                <?= ucfirst($_POST['status'] ?? 'active') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tips -->
                <div class="card shadow mt-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            Consejos
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Usa nombres descriptivos y cortos
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Elige iconos representativos
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Usa colores distintivos
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check text-success me-2"></i>
                                Ordena por importancia
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live preview updates
        document.getElementById('name').addEventListener('input', function() {
            document.getElementById('previewName').textContent = this.value || 'Nombre de la categoría';
        });

        document.getElementById('description').addEventListener('input', function() {
            document.getElementById('previewDescription').textContent = this.value || 'Descripción de la categoría';
        });

        document.getElementById('icon').addEventListener('change', function() {
            const iconClass = this.value || 'fas fa-tag';
            document.getElementById('iconPreview').className = iconClass;
            document.getElementById('previewIcon').className = iconClass + ' fa-4x';
        });

        document.getElementById('color').addEventListener('input', function() {
            document.getElementById('colorText').value = this.value;
            document.getElementById('previewIcon').style.color = this.value;
        });

        document.getElementById('status').addEventListener('change', function() {
            const status = this.value;
            const badge = document.getElementById('previewStatus');
            badge.className = 'badge bg-' + (status === 'active' ? 'success' : 'secondary');
            badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        });

        // Character counters
        function updateCounter(inputId, counterId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            
            input.addEventListener('input', function() {
                const remaining = maxLength - this.value.length;
                counter.textContent = remaining + ' caracteres restantes';
                counter.className = remaining < 20 ? 'form-text text-warning' : 'form-text';
            });
        }

        // Initialize counters if elements exist
        if (document.getElementById('name')) {
            updateCounter('name', 'nameCounter', 100);
        }
        if (document.getElementById('description')) {
            updateCounter('description', 'descCounter', 500);
        }
    </script>
</body>
</html>
