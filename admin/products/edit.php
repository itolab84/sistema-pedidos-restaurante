<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

$errors = [];
$success = '';
$product = null;

// Get product ID
$productId = (int)($_GET['id'] ?? 0);

if (!$productId) {
    header('Location: index.php?error=invalid_id');
    exit;
}

// Get product data
$product = $db->fetchOne(
    "SELECT p.*, c.name as category_name FROM products p 
     LEFT JOIN categories c ON p.category_id = c.id 
     WHERE p.id = ?", 
    [$productId]
);

if (!$product) {
    header('Location: index.php?error=product_not_found');
    exit;
}

// Get categories for dropdown
$categories = $db->fetchAll(
    "SELECT id, name, icon, color FROM categories WHERE status = 'active' ORDER BY sort_order ASC, name ASC"
);

// Handle form submission
if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $image = trim($_POST['image'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($name)) {
        $errors[] = 'El nombre del producto es obligatorio';
    } elseif (strlen($name) > 255) {
        $errors[] = 'El nombre no puede exceder 255 caracteres';
    }
    
    if (strlen($description) > 1000) {
        $errors[] = 'La descripción no puede exceder 1000 caracteres';
    }
    
    if ($price <= 0) {
        $errors[] = 'El precio debe ser mayor a 0';
    } elseif ($price > 9999.99) {
        $errors[] = 'El precio no puede exceder $9,999.99';
    }
    
    if ($category_id <= 0) {
        $errors[] = 'Debe seleccionar una categoría';
    } else {
        // Verify category exists
        $categoryExists = $db->fetchOne(
            "SELECT id FROM categories WHERE id = ? AND status = 'active'", 
            [$category_id]
        );
        if (!$categoryExists) {
            $errors[] = 'La categoría seleccionada no es válida';
        }
    }
    
    if ($image && !filter_var($image, FILTER_VALIDATE_URL)) {
        $errors[] = 'La URL de la imagen no es válida';
    }
    
    // Check if name already exists (excluding current product)
    if (empty($errors)) {
        $existing = $db->fetchOne(
            "SELECT id FROM products WHERE name = ? AND id != ?", 
            [$name, $productId]
        );
        
        if ($existing) {
            $errors[] = 'Ya existe otro producto con ese nombre';
        }
    }
    
    // Update product
    if (empty($errors)) {
        try {
            $db->update('products', [
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'category_id' => $category_id,
                'image' => $image,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$productId]);
            
            header('Location: index.php?message=product_updated&id=' . $productId);
            exit;
            
        } catch (Exception $e) {
            $errors[] = 'Error al actualizar el producto: ' . $e->getMessage();
        }
    }
} else {
    // Pre-fill form with existing data
    $_POST = $product;
}

// Get order count for this product
$orderCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM order_items WHERE product_id = ?", 
    [$productId]
)['count'] ?? 0;

// Sample images for quick selection
$sampleImages = [
    'Hamburguesa' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=500',
    'Pizza' => 'https://images.unsplash.com/photo-1604382354936-07c5d9983bd3?w=500',
    'Ensalada' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=500',
    'Tacos' => 'https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=500',
    'Pasta' => 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?w=500',
    'Sándwich' => 'https://images.unsplash.com/photo-1528735602780-2552fd46c7af?w=500',
    'Sopa' => 'https://images.unsplash.com/photo-1547592166-23ac45744acd?w=500',
    'Postre' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=500',
    'Bebida' => 'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=500',
    'Café' => 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=500'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - Administración</title>
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
                <a class="nav-link" href="../categories/">
                    <i class="fas fa-tags me-1"></i>Categorías
                </a>
                <a class="nav-link" href="index.php">
                    <i class="fas fa-box me-1"></i>Productos
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
                            <i class="fas fa-edit me-2 text-primary"></i>
                            Editar Producto
                        </h2>
                        <p class="text-muted mb-0">
                            Modifica la información del producto "<?= htmlspecialchars($product['name']) ?>"
                        </p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver a Productos
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

        <!-- Info Alert -->
        <?php if ($orderCount > 0): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Información:</strong> Este producto tiene <?= $orderCount ?> orden(es) asociada(s).
                        Los cambios afectarán las órdenes futuras, no las existentes.
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
                            Información del Producto
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">
                                            Nombre del Producto <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                                               placeholder="Ej: Hamburguesa Clásica, Pizza Margarita..." 
                                               maxlength="255" required>
                                        <div class="form-text">Máximo 255 caracteres</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Estado</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" <?= ($_POST['status'] ?? '') === 'active' ? 'selected' : '' ?>>
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
                                          rows="4" maxlength="1000" 
                                          placeholder="Descripción detallada del producto..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <div class="form-text">Máximo 1000 caracteres</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">
                                            Categoría <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="category_id" name="category_id" required>
                                            <option value="">Seleccionar categoría</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= $category['id'] ?>" 
                                                        <?= ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (empty($categories)): ?>
                                            <div class="form-text text-warning">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                No hay categorías activas. <a href="../categories/create.php">Crear una categoría</a>
                                            </div>
                                        <?php endif; ?>
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
                                                   value="<?= $_POST['price'] ?? '' ?>" 
                                                   step="0.01" min="0.01" max="9999.99" 
                                                   placeholder="0.00" required>
                                        </div>
                                        <div class="form-text">Precio en dólares (máximo $9,999.99)</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">URL de la Imagen</label>
                                <input type="url" class="form-control" id="image" name="image" 
                                       value="<?= htmlspecialchars($_POST['image'] ?? '') ?>" 
                                       placeholder="https://ejemplo.com/imagen.jpg">
                                <div class="form-text">URL completa de la imagen del producto</div>
                            </div>

                            <!-- Quick Image Selection -->
                            <div class="mb-4">
                                <label class="form-label">Imágenes Sugeridas</label>
                                <div class="row">
                                    <?php foreach ($sampleImages as $name => $url): ?>
                                        <div class="col-md-3 col-sm-4 col-6 mb-2">
                                            <div class="card h-100 sample-image-card <?= ($_POST['image'] ?? '') === $url ? 'border-primary' : '' ?>" 
                                                 style="cursor: pointer;" 
                                                 onclick="selectSampleImage('<?= $url ?>', '<?= $name ?>')">
                                                <img src="<?= $url ?>" class="card-img-top" style="height: 100px; object-fit: cover;" alt="<?= $name ?>">
                                                <div class="card-body p-2 text-center">
                                                    <small class="text-muted"><?= $name ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Image Management Section -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <label class="form-label mb-0">Gestión de Imágenes</label>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="openImageModal(<?= $productId ?>, '<?= htmlspecialchars($product['name']) ?>')">
                                        <i class="fas fa-images me-1"></i>
                                        Gestionar Imágenes
                                    </button>
                                </div>
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row" id="currentImages">
                                            <div class="col-12 text-center text-muted py-3">
                                                <i class="fas fa-spinner fa-spin"></i> Cargando imágenes...
                                            </div>
                                        </div>
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
                                    Actualizar Producto
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
                                <img id="previewImage" 
                                     src="<?= $_POST['image'] ?? 'https://via.placeholder.com/200x150?text=Imagen+del+Producto' ?>" 
                                     class="img-fluid rounded" 
                                     style="max-height: 200px; object-fit: cover;" 
                                     alt="Vista previa">
                            </div>
                            <h5 id="previewName"><?= htmlspecialchars($_POST['name'] ?? 'Nombre del producto') ?></h5>
                            <p class="text-muted" id="previewDescription">
                                <?= htmlspecialchars($_POST['description'] ?? 'Descripción del producto') ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-<?= ($_POST['status'] ?? 'active') === 'active' ? 'success' : 'secondary' ?>" 
                                      id="previewStatus">
                                    <?= ucfirst($_POST['status'] ?? 'active') ?>
                                </span>
                                <h4 class="text-success mb-0" id="previewPrice">
                                    $<?= number_format($_POST['price'] ?? 0, 2) ?>
                                </h4>
                            </div>
                            <div class="mt-2" id="previewCategory">
                                <?php if (!empty($_POST['category_id'])): ?>
                                    <?php 
                                    $selectedCategory = array_filter($categories, fn($c) => $c['id'] == $_POST['category_id']);
                                    $selectedCategory = reset($selectedCategory);
                                    ?>
                                    <?php if ($selectedCategory): ?>
                                        <small class="text-muted">
                                            <i class="<?= $selectedCategory['icon'] ?> me-1" style="color: <?= $selectedCategory['color'] ?>"></i>
                                            <?= htmlspecialchars($selectedCategory['name']) ?>
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <small class="text-muted">Selecciona una categoría</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="card shadow mt-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Información del Producto
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <strong>ID:</strong> <?= $product['id'] ?>
                            </li>
                            <li class="mb-2">
                                <strong>Órdenes:</strong> <?= $orderCount ?>
                            </li>
                            <li class="mb-2">
                                <strong>Categoría Actual:</strong> <?= htmlspecialchars($product['category_name'] ?: 'Sin categoría') ?>
                            </li>
                            <li class="mb-2">
                                <strong>Creado:</strong> <?= date('d/m/Y H:i', strtotime($product['created_at'])) ?>
                            </li>
                            <?php if ($product['updated_at']): ?>
                            <li class="mb-0">
                                <strong>Actualizado:</strong> <?= date('d/m/Y H:i', strtotime($product['updated_at'])) ?>
                            </li>
                            <?php endif; ?>
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
            document.getElementById('previewName').textContent = this.value || 'Nombre del producto';
        });

        document.getElementById('description').addEventListener('input', function() {
            document.getElementById('previewDescription').textContent = this.value || 'Descripción del producto';
        });

        document.getElementById('price').addEventListener('input', function() {
            const price = parseFloat(this.value) || 0;
            document.getElementById('previewPrice').textContent = '$' + price.toFixed(2);
        });

        document.getElementById('image').addEventListener('input', function() {
            const img = document.getElementById('previewImage');
            img.src = this.value || 'https://via.placeholder.com/200x150?text=Imagen+del+Producto';
            img.onerror = function() {
                this.src = 'https://via.placeholder.com/200x150?text=Error+al+cargar';
            };
        });

        document.getElementById('status').addEventListener('change', function() {
            const status = this.value;
            const badge = document.getElementById('previewStatus');
            badge.className = 'badge bg-' + (status === 'active' ? 'success' : 'secondary');
            badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        });

        document.getElementById('category_id').addEventListener('change', function() {
            const categoryId = this.value;
            const categoryText = this.options[this.selectedIndex].text;
            const previewCategory = document.getElementById('previewCategory');
            
            if (categoryId) {
                previewCategory.innerHTML = '<small class="text-muted">' + categoryText + '</small>';
            } else {
                previewCategory.innerHTML = '<small class="text-muted">Selecciona una categoría</small>';
            }
        });

        // Sample image selection
        function selectSampleImage(url, name) {
            document.getElementById('image').value = url;
            document.getElementById('previewImage').src = url;
            
            // Highlight selected card
            document.querySelectorAll('.sample-image-card').forEach(card => {
                card.classList.remove('border-primary');
            });
            event.currentTarget.classList.add('border-primary');
        }

        // Add hover effects to sample images
        document.querySelectorAll('.sample-image-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
                this.style.transition = 'transform 0.2s';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Load current images on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCurrentImages(<?= $productId ?>);
        });

        // Function to load current images in the edit form
        async function loadCurrentImages(productId) {
            try {
                const response = await fetch(`upload-images.php?action=get_images&id=${productId}`);
                const data = await response.json();
                
                const container = document.getElementById('currentImages');
                
                if (data.success && data.images.length > 0) {
                    container.innerHTML = data.images.slice(0, 4).map(img => `
                        <div class="col-md-3 col-6 mb-2">
                            <div class="card">
                                <img src="../../${img.image_path}" class="card-img-top" style="height: 80px; object-fit: cover;" alt="${img.original_name}">
                                <div class="card-body p-1">
                                    <small class="text-muted d-block text-truncate">${img.original_name}</small>
                                </div>
                            </div>
                        </div>
                    `).join('') + (data.images.length > 4 ? `
                        <div class="col-12 text-center mt-2">
                            <small class="text-muted">+${data.images.length - 4} imágenes más</small>
                        </div>
                    ` : '');
                } else {
                    container.innerHTML = '<div class="col-12 text-center text-muted py-3"><i class="fas fa-images fa-2x mb-2"></i><br>No hay imágenes subidas</div>';
                }
            } catch (error) {
                document.getElementById('currentImages').innerHTML = '<div class="col-12 text-center text-danger py-3"><i class="fas fa-exclamation-triangle"></i> Error al cargar imágenes</div>';
            }
        }

        // Función para abrir el modal de imágenes
        function openImageModal(productId, productName) {
            const modalProductName = document.getElementById('modalProductName');
            const modalProductId = document.getElementById('modalProductId');
            const imageGallery = document.getElementById('imageGallery');
            const uploadForm = document.getElementById('uploadForm');
            
            if (modalProductName) modalProductName.textContent = productName;
            if (modalProductId) modalProductId.value = productId;
            
            // Limpiar contenido previo
            if (imageGallery) {
                imageGallery.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando imágenes...</div>';
            }
            if (uploadForm) {
                uploadForm.reset();
            }
            
            // Configurar el formulario de subida
            setupUploadForm();
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
            
            // Cargar imágenes existentes
            loadImages(productId);
        }

        // Función para cargar imágenes existentes
        async function loadImages(productId) {
            try {
                const response = await fetch(`upload-images.php?action=get_images&id=${productId}`);
                const data = await response.json();
                
                const gallery = document.getElementById('imageGallery');
                
                if (data.success && data.images.length > 0) {
                    gallery.innerHTML = data.images.map(img => `
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <img src="../../${img.image_path}" class="card-img-top" style="height: 150px; object-fit: cover;" alt="${img.original_name}">
                                <div class="card-body p-2">
                                    <small class="text-muted d-block">
                                        <strong>${img.original_name}</strong><br>
                                        ${(img.file_size/1024).toFixed(1)} KB • ${img.created_at}
                                    </small>
                                    <button type="button" class="btn btn-sm btn-danger mt-1 w-100" onclick="deleteImage(${img.id}, '${img.original_name}')">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    gallery.innerHTML = '<div class="col-12 text-center text-muted py-4"><i class="fas fa-images fa-2x mb-2"></i><br>No hay imágenes</div>';
                }
            } catch (error) {
                document.getElementById('imageGallery').innerHTML = '<div class="col-12 text-center text-danger py-4"><i class="fas fa-exclamation-triangle"></i> Error al cargar imágenes</div>';
            }
        }

        // Función para eliminar imagen
        async function deleteImage(imageId, imageName) {
            if (confirm(`¿Estás seguro de que deseas eliminar la imagen "${imageName}"?`)) {
                try {
                    const response = await fetch('upload-images.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_image&image_id=${imageId}`
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showSuccessToast('Imagen eliminada correctamente');
                        loadImages(document.getElementById('modalProductId').value);
                        loadCurrentImages(document.getElementById('modalProductId').value);
                    } else {
                        showErrorToast(data.message || 'Error al eliminar imagen');
                    }
                } catch (error) {
                    showErrorToast('Error al eliminar imagen');
                }
            }
        }

        // Funciones para mostrar toasts
        function showSuccessToast(message) {
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 p-3';
            toast.style.zIndex = '11';
            toast.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header bg-success text-white">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong class="me-auto">Éxito</strong>
                        <button type="button" class="btn-close btn-close-white" onclick="this.closest('.position-fixed').remove()"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function showErrorToast(message) {
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 p-3';
            toast.style.zIndex = '11';
            toast.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header bg-danger text-white">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong class="me-auto">Error</strong>
                        <button type="button" class="btn-close btn-close-white" onclick="this.closest('.position-fixed').remove()"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }

        // Manejar subida de imágenes - se configurará cuando se abra el modal
        function setupUploadForm() {
            const uploadForm = document.getElementById('uploadForm');
            if (uploadForm && !uploadForm.hasAttribute('data-setup')) {
                uploadForm.setAttribute('data-setup', 'true');
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
                        const response = await fetch('upload-images.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            showSuccessToast(data.message);
                            this.reset();
                            loadImages(document.getElementById('modalProductId').value);
                            loadCurrentImages(document.getElementById('modalProductId').value);
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
        }
    </script>

    <!-- Modal de Gestión de Imágenes -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">
                        <i class="fas fa-images me-2"></i>
                        Gestión de Imágenes - <span id="modalProductName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalProductId">
                    
                    <!-- Formulario de subida -->
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
                                <button type="submit" class="btn btn-primary" id="uploadBtn">
                                    <i class="fas fa-upload me-2"></i>Subir Imágenes
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Galería de imágenes -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-images me-2"></i>Imágenes Existentes</h6>
                        </div>
                        <div class="card-body">
                            <div class="row" id="imageGallery">
                                <!-- Las imágenes se cargarán aquí dinámicamente -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
