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
                
                $db->update('additionals', 
                    ['status' => $newStatus], 
                    'id = ?', 
                    [$id]
                );
                
                $message = 'Estado del adicional actualizado correctamente';
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Check if additional is used in products
                $productCount = $db->fetchOne(
                    "SELECT COUNT(*) as count FROM product_additionals WHERE additional_id = ?", 
                    [$id]
                )['count'];
                
                if ($productCount > 0) {
                    $message = 'No se puede eliminar el adicional porque está asociado a productos';
                    $messageType = 'danger';
                } else {
                    $db->delete('additionals', 'id = ?', [$id]);
                    $message = 'Adicional eliminado correctamente';
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
                                "UPDATE additionals SET status = 'active' WHERE id IN ($placeholders)",
                                $selectedIds
                            );
                            $message = count($selectedIds) . ' adicionales activados';
                            break;
                            
                        case 'deactivate':
                            $db->query(
                                "UPDATE additionals SET status = 'inactive' WHERE id IN ($placeholders)",
                                $selectedIds
                            );
                            $message = count($selectedIds) . ' adicionales desactivados';
                            break;
                    }
                }
                break;
        }
    }
}

// Get additionals with category info
$additionals = $db->fetchAll(
    "SELECT a.*, 
            ac.name as category_name,
            COUNT(pa.id) as product_count
     FROM additionals a
     LEFT JOIN additional_categories ac ON a.category_id = ac.id
     LEFT JOIN product_additionals pa ON a.id = pa.additional_id
     GROUP BY a.id
     ORDER BY ac.name ASC, a.name ASC"
);

$totalAdditionals = count($additionals);
$activeAdditionals = count(array_filter($additionals, fn($a) => $a['status'] === 'active'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionales - Administración</title>
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
                            <i class="fas fa-plus-circle me-2 text-primary"></i>
                            Gestión de Adicionales
                        </h2>
                        <p class="text-muted mb-0">
                            Administra los adicionales que se pueden agregar a los productos
                        </p>
                    </div>
                    <div>
                        <a href="categories.php" class="btn btn-outline-info me-2">
                            <i class="fas fa-layer-group me-2"></i>
                            Categorías de Adicionales
                        </a>
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>
                            Nuevo Adicional
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
                                    Total Adicionales
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $totalAdditionals ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-plus-circle fa-2x text-gray-300"></i>
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
                                    Adicionales Activos
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $activeAdditionals ?>
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
                                    Productos con Adicionales
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= array_sum(array_column($additionals, 'product_count')) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-link fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additionals Table -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    Lista de Adicionales
                                </h6>
                            </div>
                            <div class="col-auto">
                                <form method="POST" class="d-inline" id="bulkForm">
                                    <input type="hidden" name="action" value="bulk_action">
                                    <div class="input-group input-group-sm">
                                        <select name="bulk_action_type" class="form-select" required>
                                            <option value="">Acciones masivas</option>
                                            <option value="activate">Activar seleccionados</option>
                                            <option value="deactivate">Desactivar seleccionados</option>
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
                        <?php if (empty($additionals)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-plus-circle fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay adicionales registrados</h5>
                                <p class="text-muted">Comienza creando tu primer adicional</p>
                                <a href="create.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>
                                    Crear Primer Adicional
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
                                            <th>Adicional</th>
                                            <th>Categoría</th>
                                            <th>Precio</th>
                                            <th>Estado</th>
                                            <th>Productos</th>
                                            <th>Creado por</th>
                                            <th width="150">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($additionals as $additional): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_ids[]" 
                                                           value="<?= $additional['id'] ?>" 
                                                           class="form-check-input additional-checkbox">
                                                </td>
                                                <td>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($additional['name']) ?></h6>
                                                        <small class="text-muted">
                                                            ID: <?= $additional['id'] ?>
                                                            <?php if ($additional['description']): ?>
                                                                <br><?= htmlspecialchars($additional['description']) ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </td>
                                <td>
                                    <?php if ($additional['category_name']): ?>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($additional['category_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Sin categoría</span>
                                    <?php endif; ?>
                                </td>
                                                <td>
                                                    <span class="fw-bold text-success">
                                                        +$<?= number_format($additional['price'], 2) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="id" value="<?= $additional['id'] ?>">
                                                        <input type="hidden" name="current_status" value="<?= $additional['status'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-<?= $additional['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                            <i class="fas fa-<?= $additional['status'] === 'active' ? 'check' : 'times' ?>"></i>
                                                            <?= ucfirst($additional['status']) ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?= $additional['product_count'] ?> productos
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        Sistema
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="edit.php?id=<?= $additional['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-info" 
                                                                title="Gestionar imágenes"
                                                                onclick="openImageModal(<?= $additional['id'] ?>, '<?= htmlspecialchars($additional['name']) ?>')">
                                                            <i class="fas fa-images"></i>
                                                        </button>
                                                        <?php if ($additional['product_count'] == 0): ?>
                                                            <form method="POST" class="d-inline delete-form">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="id" value="<?= $additional['id'] ?>">
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
            const checkboxes = document.querySelectorAll('.additional-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all when individual checkboxes change
        document.querySelectorAll('.additional-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.additional-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.additional-checkbox:checked');
                const selectAll = document.getElementById('selectAll');
                
                selectAll.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAll.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            });
        });

        // Bulk actions form submission
        document.getElementById('bulkForm').addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.additional-checkbox:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Por favor selecciona al menos un adicional');
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

        // Función para abrir el modal de imágenes
        function openImageModal(additionalId, additionalName) {
            document.getElementById('modalAdditionalName').textContent = additionalName;
            document.getElementById('modalAdditionalId').value = additionalId;
            
            // Limpiar contenido previo
            document.getElementById('imageGallery').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando imágenes...</div>';
            document.getElementById('uploadForm').reset();
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
            
            // Cargar imágenes existentes
            loadImages(additionalId);
        }

        // Función para cargar imágenes existentes
        async function loadImages(additionalId) {
            try {
                const response = await fetch(`upload-images.php?action=get_images&id=${additionalId}`);
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
            // Crear modal de confirmación personalizado con z-index alto
            const confirmModal = document.createElement('div');
            confirmModal.id = 'deleteConfirmModal';
            confirmModal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full';
            confirmModal.style.zIndex = '9999'; // Z-index más alto que el modal principal
            
            confirmModal.innerHTML = `
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3 text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mt-4">Confirmar Eliminación</h3>
                        <div class="mt-2 px-7 py-3">
                            <p class="text-sm text-gray-500">
                                ¿Estás seguro de que deseas eliminar la imagen 
                                <span class="font-semibold text-gray-700">${imageName}</span>?
                            </p>
                            <p class="text-xs text-red-500 mt-2">Esta acción no se puede deshacer.</p>
                        </div>
                        <div class="flex justify-center space-x-4 mt-4">
                            <button onclick="closeDeleteConfirmModal()" 
                                    class="px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300">
                                Cancelar
                            </button>
                            <button onclick="executeImageDelete(${imageId})" 
                                    class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                                <i class="fas fa-trash mr-2"></i>Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(confirmModal);
        }
        
        // Función para cerrar el modal de confirmación
        function closeDeleteConfirmModal() {
            const modal = document.getElementById('deleteConfirmModal');
            if (modal) {
                modal.remove();
            }
        }
        
        // Función para ejecutar la eliminación
        async function executeImageDelete(imageId) {
            closeDeleteConfirmModal();
            
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
                    // Mostrar toast de éxito
                    showSuccessToast('Imagen eliminada correctamente');
                    loadImages(document.getElementById('modalAdditionalId').value);
                } else {
                    showErrorToast(data.message || 'Error al eliminar imagen');
                }
            } catch (error) {
                showErrorToast('Error al eliminar imagen');
            }
        }
        
        // Funciones para mostrar toasts personalizados
        function showSuccessToast(message) {
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 max-w-sm';
            toast.style.zIndex = '10000';
            toast.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-3"></i>
                    <span class="text-sm font-medium">${message}</span>
                    <button onclick="this.parentNode.parentNode.remove()" class="ml-4 text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Mostrar con animación
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);
            
            // Ocultar después de 3 segundos
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }
        
        function showErrorToast(message) {
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-4 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 max-w-sm';
            toast.style.zIndex = '10000';
            toast.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3"></i>
                    <span class="text-sm font-medium">${message}</span>
                    <button onclick="this.parentNode.parentNode.remove()" class="ml-4 text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Mostrar con animación
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);
            
            // Ocultar después de 5 segundos
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 5000);
        }

        // Manejar subida de imágenes
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'upload_images');
            formData.append('additional_id', document.getElementById('modalAdditionalId').value);
            
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
                    customModals.toast(data.message, 'success');
                    this.reset();
                    loadImages(document.getElementById('modalAdditionalId').value);
                } else {
                    customModals.toast(data.message || 'Error al subir imágenes', 'error');
                }
            } catch (error) {
                customModals.toast('Error al subir imágenes', 'error');
            } finally {
                uploadBtn.innerHTML = originalText;
                uploadBtn.disabled = false;
            }
        });
    </script>

    <!-- Modal de Gestión de Imágenes -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">
                        <i class="fas fa-images me-2"></i>
                        Gestión de Imágenes - <span id="modalAdditionalName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalAdditionalId">
                    
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

    <?php include '../includes/footer.php'; ?>
</body>
</html>
