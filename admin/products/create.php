<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

$errors = [];
$success = '';

// Get categories for dropdown
$categories = $db->fetchAll(
    "SELECT id, name, icon, color FROM categories WHERE status = 'active' ORDER BY sort_order ASC, name ASC"
);

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
    
    // Check if name already exists
    if (empty($errors)) {
        $existing = $db->fetchOne(
            "SELECT id FROM products WHERE name = ?", 
            [$name]
        );
        
        if ($existing) {
            $errors[] = 'Ya existe un producto con ese nombre';
        }
    }
    
    // Insert product
    if (empty($errors)) {
        try {
            $productId = $db->insert('products', [
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'category_id' => $category_id,
                'image' => $image,
                'status' => $status,
                'created_by' => $user['id']
            ]);
            
            // Handle image uploads
            if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
                $uploadDir = __DIR__ . '/../../uploads/products/';
                $webPath = '/reserve/uploads/products/'; // Ruta web correcta
                
                // Create directory if not exists
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxFileSize = 5 * 1024 * 1024; // 5MB
                $files = $_FILES['product_images'];
                $count = count($files['name']);

                for ($i = 0; $i < $count; $i++) {
                    $fileName = $files['name'][$i];
                    $tmpName = $files['tmp_name'][$i];
                    $fileSize = $files['size'][$i];
                    $fileType = $files['type'][$i];
                    $fileError = $files['error'][$i];

                    if ($fileError === 0 && $fileSize <= $maxFileSize && in_array($fileType, $allowedTypes)) {
                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $newName = 'product_' . $productId . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                        $targetPath = $uploadDir . $newName;
                        $imagePath = $webPath . $newName; // Usar ruta web

                        if (move_uploaded_file($tmpName, $targetPath)) {
                            try {
                                $db->insert('product_images', [
                                    'product_id' => $productId,
                                    'image_path' => $imagePath,
                                    'original_name' => $fileName,
                                    'file_size' => $fileSize,
                                    'mime_type' => $fileType,
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                                
                                // Set first image as main product image if no URL provided
                                if (empty($image) && $i === 0) {
                                    $db->update('products', ['image' => $imagePath], 'id = ?', [$productId]);
                                }
                            } catch (Exception $e) {
                                // Log error but don't stop the process
                                error_log("Error saving image to database: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
            
            header('Location: index.php?message=product_created&id=' . $productId);
            exit;
            
        } catch (Exception $e) {
            $errors[] = 'Error al crear el producto: ' . $e->getMessage();
        }
    }
}

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
    <title>Nuevo Producto - Administración</title>
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
                            <i class="fas fa-plus-circle me-2 text-primary"></i>
                            Nuevo Producto
                        </h2>
                        <p class="text-muted mb-0">
                            Agrega un nuevo producto al catálogo del restaurante
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
                        <form method="POST" action="" enctype="multipart/form-data">
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
                                            <div class="card h-100 sample-image-card" style="cursor: pointer;" 
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

                            <!-- File Upload Section -->
                            <div class="mb-4">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-cloud-upload-alt me-2"></i>
                                            Subir Imágenes del Producto
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="product_images" class="form-label">
                                                Seleccionar imágenes
                                            </label>
                                            <input type="file" 
                                                   class="form-control" 
                                                   id="product_images" 
                                                   name="product_images[]" 
                                                   accept="image/*" 
                                                   multiple>
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Formatos permitidos: JPG, PNG, GIF, WEBP. Máximo 5MB por archivo.
                                                <br>
                                                <i class="fas fa-lightbulb me-1"></i>
                                                Puedes seleccionar múltiples imágenes a la vez.
                                            </div>
                                        </div>
                                        
                                        <!-- Preview area for uploaded files -->
                                        <div id="uploadPreview" class="row" style="display: none;">
                                            <div class="col-12">
                                                <h6 class="text-muted mb-2">Vista previa de archivos seleccionados:</h6>
                                                <div id="previewContainer" class="d-flex flex-wrap gap-2"></div>
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
                                    Crear Producto
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
                                Usa nombres descriptivos y atractivos
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Incluye ingredientes principales
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Usa imágenes de alta calidad
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check text-success me-2"></i>
                                Precios competitivos y claros
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
            
            // Auto-fill name if empty
            const nameField = document.getElementById('name');
            if (!nameField.value.trim()) {
                nameField.value = name;
                document.getElementById('previewName').textContent = name;
            }
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

        // Handle file upload preview
        document.getElementById('product_images').addEventListener('change', function(e) {
            const files = e.target.files;
            const previewContainer = document.getElementById('previewContainer');
            const uploadPreview = document.getElementById('uploadPreview');
            
            // Clear previous previews
            previewContainer.innerHTML = '';
            
            if (files.length > 0) {
                uploadPreview.style.display = 'block';
                
                Array.from(files).forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewDiv = document.createElement('div');
                            previewDiv.className = 'position-relative';
                            previewDiv.innerHTML = `
                                <img src="${e.target.result}" 
                                     class="rounded border" 
                                     style="width: 80px; height: 80px; object-fit: cover;" 
                                     alt="Preview ${index + 1}">
                                <div class="position-absolute top-0 start-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 20px; height: 20px; font-size: 10px; margin: 2px;">
                                    ${index + 1}
                                </div>
                                <div class="text-center mt-1">
                                    <small class="text-muted">${file.name.length > 12 ? file.name.substring(0, 12) + '...' : file.name}</small>
                                    <br>
                                    <small class="text-muted">${(file.size / 1024).toFixed(1)} KB</small>
                                </div>
                            `;
                            previewContainer.appendChild(previewDiv);
                        };
                        reader.readAsDataURL(file);
                    }
                });
                
                // Update main preview with first image if no URL is set
                if (files.length > 0 && !document.getElementById('image').value.trim()) {
                    const firstFile = files[0];
                    if (firstFile.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            document.getElementById('previewImage').src = e.target.result;
                        };
                        reader.readAsDataURL(firstFile);
                    }
                }
            } else {
                uploadPreview.style.display = 'none';
            }
        });
    </script>
</body>
</html>
