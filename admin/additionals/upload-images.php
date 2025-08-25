<?php
require_once '../config/auth.php';
$auth->requireLogin();
$db = AdminDB::getInstance();
$user = $auth->getCurrentUser();

// Handle AJAX requests
if (isset($_GET['action']) || isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? $_POST['action'];
    
    switch ($action) {
        case 'get_images':
            $id = (int)($_GET['id'] ?? 0);
            if ($id > 0) {
                try {
                    $images = $db->fetchAll("SELECT * FROM additional_images WHERE additional_id = ? ORDER BY created_at DESC", [$id]);
                    echo json_encode(['success' => true, 'images' => $images]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error al cargar imágenes: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID de adicional inválido']);
            }
            exit;
            
        case 'delete_image':
            $imageId = (int)($_POST['image_id'] ?? 0);
            if ($imageId > 0) {
                try {
                    $img = $db->fetchOne("SELECT * FROM additional_images WHERE id = ?", [$imageId]);
                    if ($img) {
                        // Eliminar archivo físico
                        $filePath = __DIR__ . '/../../' . $img['image_path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        // Eliminar de BD
                        $db->delete('additional_images', 'id = ?', [$imageId]);
                        echo json_encode(['success' => true, 'message' => 'Imagen eliminada correctamente']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Imagen no encontrada']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error al eliminar imagen: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID de imagen inválido']);
            }
            exit;
            
        case 'upload_images':
            $id = (int)($_POST['additional_id'] ?? 0);
            if ($id > 0 && isset($_FILES['image']) && !empty($_FILES['image']['name'][0])) {
                $uploadDir = __DIR__ . '/../../uploads/additionals/';
                $relativeDir = 'uploads/additionals/';
                
                // Crear directorio si no existe
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxFileSize = 5 * 1024 * 1024; // 5MB
                $success = 0;
                $errors = [];
                $files = $_FILES['image'];
                $count = count($files['name']);

                for ($i = 0; $i < $count; $i++) {
                    $fileName = $files['name'][$i];
                    $tmpName = $files['tmp_name'][$i];
                    $fileSize = $files['size'][$i];
                    $fileType = $files['type'][$i];
                    $fileError = $files['error'][$i];

                    if ($fileError !== 0) {
                        $errors[] = "Archivo $fileName: error al subir (código: $fileError)";
                        continue;
                    }
                    
                    if ($fileSize > $maxFileSize) {
                        $errors[] = "Archivo $fileName: demasiado grande (máx 5MB)";
                        continue;
                    }
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        $errors[] = "Archivo $fileName: tipo no permitido";
                        continue;
                    }
                    
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $newName = 'additional_' . $id . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $targetPath = $uploadDir . $newName;
                    $imagePath = $relativeDir . $newName;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        try {
                            $insertData = [
                                'additional_id' => $id,
                                'image_path' => $imagePath,
                                'original_name' => $fileName,
                                'file_size' => $fileSize,
                                'mime_type' => $fileType,
                                'created_at' => date('Y-m-d H:i:s')
                            ];
                            
                            $db->insert('additional_images', $insertData);
                            $success++;
                        } catch (Exception $e) {
                            $errors[] = "Archivo $fileName: error en base de datos - " . $e->getMessage();
                            // Eliminar archivo si falló la BD
                            if (file_exists($targetPath)) {
                                unlink($targetPath);
                            }
                        }
                    } else {
                        $errors[] = "Archivo $fileName: error moviendo archivo";
                    }
                }
                
                if ($success > 0) {
                    $message = "$success imagen(es) subida(s) correctamente";
                    if (!empty($errors)) {
                        $message .= ". Errores: " . implode(', ', $errors);
                    }
                    echo json_encode(['success' => true, 'message' => $message]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No se pudieron subir las imágenes: ' . implode(', ', $errors)]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No se recibieron archivos válidos']);
            }
            exit;
    }
}

$id = (int)($_GET['id'] ?? 1);
$message = '';
$messageType = 'success';

// Obtener información del adicional
$additional = $db->fetchOne("SELECT * FROM additionals WHERE id = ?", [$id]);
if (!$additional) {
    header('Location: index.php');
    exit;
}

// Directorio absoluto para guardar archivos
$uploadDir = __DIR__ . '/../../uploads/additionals/';
$relativeDir = 'uploads/additionals/';

// Crear directorio si no existe
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Eliminar imagen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $imgId = (int)$_GET['delete'];
    $img = $db->fetchOne("SELECT * FROM additional_images WHERE id = ? AND additional_id = ?", [$imgId, $id]);
    if ($img) {
        // Eliminar archivo físico
        $filePath = __DIR__ . '/../../' . $img['image_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        // Eliminar de BD
        $db->delete('additional_images', 'id = ?', [$imgId]);
        $message = 'Imagen eliminada correctamente';
        $messageType = 'success';
    }
}

// Procesar subida múltiple
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    if (!empty($_FILES['image']['name'][0])) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        $success = 0;
        $errors = [];
        $files = $_FILES['image'];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            $fileName = $files['name'][$i];
            $tmpName = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            $fileType = $files['type'][$i];
            $fileError = $files['error'][$i];

            if ($fileError !== 0) {
                $errors[] = "Archivo $fileName: error al subir (código: $fileError)";
                continue;
            }
            
            if ($fileSize > $maxFileSize) {
                $errors[] = "Archivo $fileName: demasiado grande (máx 5MB)";
                continue;
            }
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "Archivo $fileName: tipo no permitido";
                continue;
            }
            
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $newName = 'additional_' . $id . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $targetPath = $uploadDir . $newName;
            $imagePath = $relativeDir . $newName;

            if (move_uploaded_file($tmpName, $targetPath)) {
                try {
                    $insertData = [
                        'additional_id' => $id,
                        'image_path' => $imagePath,
                        'original_name' => $fileName,
                        'file_size' => $fileSize,
                        'mime_type' => $fileType,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $db->insert('additional_images', $insertData);
                    $success++;
                } catch (Exception $e) {
                    $errors[] = "Archivo $fileName: error en base de datos - " . $e->getMessage();
                    // Eliminar archivo si falló la BD
                    if (file_exists($targetPath)) {
                        unlink($targetPath);
                    }
                }
            } else {
                $errors[] = "Archivo $fileName: error moviendo archivo";
            }
        }
        
        if ($success > 0) {
            $message = "✅ $success imagen(es) subida(s) correctamente";
            $messageType = 'success';
        }
        if (!empty($errors)) {
            if ($message) $message .= "<br>";
            $message .= "❌ " . implode('<br>❌ ', $errors);
            $messageType = $success > 0 ? 'warning' : 'danger';
        }
    } else {
        $message = "❌ No se seleccionaron archivos";
        $messageType = 'danger';
    }
}

// Obtener imágenes existentes
$images = [];
try {
    $images = $db->fetchAll("SELECT * FROM additional_images WHERE additional_id = ? ORDER BY created_at DESC", [$id]);
} catch (Exception $e) {
    $message .= " | Error consultando BD: " . $e->getMessage();
    $messageType = 'danger';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Imágenes - <?= htmlspecialchars($additional['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .upload-zone {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .upload-zone:hover {
            border-color: #0d6efd;
            background-color: #f8f9ff;
        }
        .upload-zone.dragover {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .image-preview {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
        }
        .image-preview img {
            transition: transform 0.3s ease;
        }
        .image-preview:hover img {
            transform: scale(1.05);
        }
        .delete-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(220, 53, 69, 0.9);
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .image-preview:hover .delete-btn {
            opacity: 1;
        }
    </style>
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
                <a class="nav-link" href="../products/">
                    <i class="fas fa-box me-1"></i>Productos
                </a>
                <a class="nav-link active" href="index.php">
                    <i class="fas fa-plus-circle me-1"></i>Adicionales
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
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="index.php">Adicionales</a></li>
                <li class="breadcrumb-item active">Imágenes - <?= htmlspecialchars($additional['name']) ?></li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-images me-2 text-primary"></i>
                            Gestión de Imágenes
                        </h2>
                        <p class="text-muted mb-0">
                            Adicional: <strong><?= htmlspecialchars($additional['name']) ?></strong> 
                            (ID: <?= $id ?>)
                        </p>
                    </div>
                    <div>
                        <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-primary me-2">
                            <i class="fas fa-edit me-2"></i>Editar Adicional
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver
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
                        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'times-circle') ?> me-2"></i>
                        <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Upload Section -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-cloud-upload-alt me-2"></i>
                            Subir Imágenes
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="upload-zone mb-3" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <h5>Arrastra archivos aquí</h5>
                                <p class="text-muted mb-2">o haz clic para seleccionar</p>
                                <small class="text-muted">
                                    Formatos: JPG, PNG, GIF, WEBP<br>
                                    Tamaño máximo: 5MB por archivo
                                </small>
                            </div>
                            
                            <input type="file" 
                                   id="fileInput" 
                                   name="image[]" 
                                   class="d-none" 
                                   accept="image/*" 
                                   multiple 
                                   required>
                            
                            <div id="fileList" class="mb-3" style="display: none;">
                                <h6>Archivos seleccionados:</h6>
                                <ul id="selectedFiles" class="list-unstyled"></ul>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-upload me-2"></i>
                                Subir Imágenes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="card shadow mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Estadísticas
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-primary"><?= count($images) ?></h4>
                                <small class="text-muted">Total Imágenes</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-success">
                                    <?= number_format(array_sum(array_column($images, 'file_size')) / 1024 / 1024, 1) ?>MB
                                </h4>
                                <small class="text-muted">Espacio Usado</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Images Gallery -->
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-images me-2"></i>
                            Galería de Imágenes (<?= count($images) ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($images)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-images fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay imágenes</h5>
                                <p class="text-muted">Sube la primera imagen para este adicional</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($images as $img): ?>
                                    <div class="col-md-4 col-lg-3 mb-4">
                                        <div class="image-preview">
                                            <img src="../../<?= htmlspecialchars($img['image_path']) ?>" 
                                                 class="img-fluid rounded shadow-sm" 
                                                 style="width: 100%; height: 200px; object-fit: cover;"
                                                 alt="<?= htmlspecialchars($img['original_name']) ?>"
                                                 data-bs-toggle="modal" 
                                                 data-bs-target="#imageModal<?= $img['id'] ?>">
                                            
                                            <button type="button" 
                                                    class="delete-btn"
                                                    onclick="confirmDelete(<?= $img['id'] ?>, '<?= htmlspecialchars($img['original_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <small class="text-muted d-block">
                                                <strong><?= htmlspecialchars($img['original_name']) ?></strong>
                                            </small>
                                            <small class="text-muted">
                                                <?= number_format($img['file_size']/1024, 1) ?> KB • 
                                                <?= date('d/m/Y H:i', strtotime($img['created_at'])) ?>
                                            </small>
                                        </div>
                                    </div>

                                    <!-- Modal para vista previa -->
                                    <div class="modal fade" id="imageModal<?= $img['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><?= htmlspecialchars($img['original_name']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body text-center">
                                                    <img src="../../<?= htmlspecialchars($img['image_path']) ?>" 
                                                         class="img-fluid rounded" 
                                                         alt="<?= htmlspecialchars($img['original_name']) ?>">
                                                    <div class="mt-3">
                                                        <p class="text-muted">
                                                            <strong>Tamaño:</strong> <?= number_format($img['file_size']/1024, 1) ?> KB<br>
                                                            <strong>Tipo:</strong> <?= $img['mime_type'] ?><br>
                                                            <strong>Subida:</strong> <?= date('d/m/Y H:i:s', strtotime($img['created_at'])) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                    <button type="button" class="btn btn-danger" 
                                                            onclick="confirmDelete(<?= $img['id'] ?>, '<?= htmlspecialchars($img['original_name']) ?>')">
                                                        <i class="fas fa-trash me-2"></i>Eliminar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación de Eliminación con Tailwind CSS -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mt-4">Confirmar Eliminación</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        ¿Estás seguro de que deseas eliminar la imagen 
                        <span class="font-semibold text-gray-700" id="deleteFileName"></span>?
                    </p>
                    <p class="text-xs text-red-500 mt-2">Esta acción no se puede deshacer.</p>
                </div>
                <div class="flex justify-center space-x-4 mt-4">
                    <button onclick="closeDeleteModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Cancelar
                    </button>
                    <button onclick="executeDelete()" 
                            class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <i class="fas fa-trash mr-2"></i>Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Drag and drop functionality
        const uploadZone = document.querySelector('.upload-zone');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        const selectedFiles = document.getElementById('selectedFiles');

        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            fileInput.files = files;
            showSelectedFiles(files);
        });

        fileInput.addEventListener('change', (e) => {
            showSelectedFiles(e.target.files);
        });

        function showSelectedFiles(files) {
            if (files.length > 0) {
                fileList.style.display = 'block';
                selectedFiles.innerHTML = '';
                
                Array.from(files).forEach(file => {
                    const li = document.createElement('li');
                    li.className = 'text-muted';
                    li.innerHTML = `<i class="fas fa-file-image me-2"></i>${file.name} (${(file.size/1024).toFixed(1)} KB)`;
                    selectedFiles.appendChild(li);
                });
            } else {
                fileList.style.display = 'none';
            }
        }

        let deleteImageId = null;
        let deleteFileName = '';

        function confirmDelete(imageId, fileName) {
            deleteImageId = imageId;
            deleteFileName = fileName;
            document.getElementById('deleteFileName').textContent = fileName;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            deleteImageId = null;
            deleteFileName = '';
        }

        function executeDelete() {
            if (deleteImageId) {
                window.location.href = `upload-images.php?id=<?= $id ?>&delete=${deleteImageId}`;
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
