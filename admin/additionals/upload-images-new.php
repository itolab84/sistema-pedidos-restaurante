<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Get additional ID
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

// Get additional data
$additional = $db->fetchOne("SELECT * FROM additionals WHERE id = ?", [$id]);
if (!$additional) {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = 'success';

// Handle image upload - VERSIÓN SIMPLIFICADA
if ($_POST && isset($_FILES['images'])) {
    $uploadDir = '../../uploads/additionals/';
    
    // Crear directorio si no existe
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $uploadedCount = 0;
    $errors = [];
    
    // Manejar archivos múltiples
    $files = $_FILES['images'];
    $fileCount = count($files['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        // Saltar archivos vacíos
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $fileName = $files['name'][$i];
            $tmpName = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            $fileType = $files['type'][$i];
            
            // Validar tipo de archivo
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "Archivo $fileName: tipo no permitido";
                continue;
            }
            
            // Validar tamaño (5MB máximo)
            if ($fileSize > 5 * 1024 * 1024) {
                $errors[] = "Archivo $fileName: muy grande (máx 5MB)";
                continue;
            }
            
            // Generar nombre único
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = 'additional_' . $id . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
            $targetPath = $uploadDir . $newFileName;
            
            // Mover archivo
            if (move_uploaded_file($tmpName, $targetPath)) {
                // Guardar en base de datos
                try {
                    $conn = $db->getConnection();
                    $sql = "INSERT INTO additional_images (additional_id, image_path, original_name, file_size, mime_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                    $stmt = $conn->prepare($sql);
                    
                    if ($stmt) {
                        $imagePath = 'uploads/additionals/' . $newFileName;
                        $stmt->bind_param('issis', $id, $imagePath, $fileName, $fileSize, $fileType);
                        
                        if ($stmt->execute()) {
                            $uploadedCount++;
                        } else {
                            $errors[] = "Error BD para $fileName: " . $stmt->error;
                            // Eliminar archivo si falló la BD
                            if (file_exists($targetPath)) {
                                unlink($targetPath);
                            }
                        }
                    } else {
                        $errors[] = "Error preparando consulta para $fileName";
                    }
                } catch (Exception $e) {
                    $errors[] = "Excepción para $fileName: " . $e->getMessage();
                }
            } else {
                $errors[] = "Error moviendo archivo $fileName";
            }
        } else {
            $errors[] = "Error en archivo $fileName (código: " . $files['error'][$i] . ")";
        }
    }
    
    // Mensaje de resultado
    if ($uploadedCount > 0) {
        $message = "$uploadedCount imagen(es) subida(s) correctamente";
    }
    if (!empty($errors)) {
        $message .= ($uploadedCount > 0 ? '. ' : '') . 'Errores: ' . implode(', ', $errors);
        $messageType = $uploadedCount > 0 ? 'warning' : 'danger';
    }
}

// Handle image deletion
if ($_POST && isset($_POST['delete_image'])) {
    $imageId = (int)$_POST['image_id'];
    
    $image = $db->fetchOne("SELECT * FROM additional_images WHERE id = ? AND additional_id = ?", [$imageId, $id]);
    
    if ($image) {
        // Eliminar archivo
        $filePath = '../../' . $image['image_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Eliminar de BD
        $db->delete('additional_images', 'id = ?', [$imageId]);
        $message = 'Imagen eliminada correctamente';
    } else {
        $message = 'Imagen no encontrada';
        $messageType = 'danger';
    }
}

// Get existing images
$images = $db->fetchAll("SELECT * FROM additional_images WHERE additional_id = ? ORDER BY created_at DESC", [$id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imágenes - <?= htmlspecialchars($additional['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .image-preview {
            position: relative;
            margin: 10px;
        }
        .image-preview img {
            width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .delete-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
        }
        .delete-btn:hover {
            background: rgba(220, 53, 69, 1);
        }
    </style>
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-images me-2 text-primary"></i>Imágenes del Adicional</h2>
                        <p class="text-muted"><strong><?= htmlspecialchars($additional['name']) ?></strong></p>
                    </div>
                    <div>
                        <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-edit me-2"></i>Editar Adicional
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-cloud-upload-alt me-2"></i>Subir Imágenes</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="upload-area" onclick="document.getElementById('imageInput').click()">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <h5>Haz clic para seleccionar imágenes</h5>
                                <p class="text-muted">JPG, PNG, GIF, WEBP - Máximo 5MB cada una</p>
                                <input type="file" name="images[]" id="imageInput" multiple accept="image/*" style="display: none;">
                            </div>
                            
                            <div id="selectedFiles" class="mt-3" style="display: none;">
                                <h6>Archivos seleccionados:</h6>
                                <div id="fileList"></div>
                                <button type="submit" class="btn btn-success mt-3">
                                    <i class="fas fa-upload me-2"></i>Subir Imágenes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Images -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-images me-2"></i>Imágenes Actuales (<?= count($images) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($images)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-images fa-3x text-muted mb-3"></i>
                                <h5>No hay imágenes</h5>
                                <p class="text-muted">Sube la primera imagen para este adicional</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($images as $image): ?>
                                    <div class="col-md-3 mb-4">
                                        <div class="image-preview">
                                            <img src="../../<?= htmlspecialchars($image['image_path']) ?>" 
                                                 alt="<?= htmlspecialchars($image['original_name']) ?>" 
                                                 class="img-fluid">
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta imagen?')">
                                                <input type="hidden" name="delete_image" value="1">
                                                <input type="hidden" name="image_id" value="<?= $image['id'] ?>">
                                                <button type="submit" class="delete-btn">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <strong><?= htmlspecialchars($image['original_name']) ?></strong><br>
                                                <?= number_format($image['file_size'] / 1024, 1) ?> KB<br>
                                                <?= date('d/m/Y H:i', strtotime($image['created_at'])) ?>
                                            </small>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const imageInput = document.getElementById('imageInput');
        const selectedFiles = document.getElementById('selectedFiles');
        const fileList = document.getElementById('fileList');

        imageInput.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files.length > 0) {
                let html = '<ul class="list-group">';
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    html += `<li class="list-group-item d-flex justify-content-between">
                        <span><i class="fas fa-image me-2"></i>${file.name}</span>
                        <small class="text-muted">${(file.size / 1024).toFixed(1)} KB</small>
                    </li>`;
                }
                html += '</ul>';
                fileList.innerHTML = html;
                selectedFiles.style.display = 'block';
            }
        });

        // Form validation
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const files = imageInput.files;
            if (files.length === 0) {
                e.preventDefault();
                alert('Por favor selecciona al menos una imagen');
                return;
            }

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (!file.type.startsWith('image/')) {
                    e.preventDefault();
                    alert(`El archivo "${file.name}" no es una imagen válida`);
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    e.preventDefault();
                    alert(`El archivo "${file.name}" es muy grande (máximo 5MB)`);
                    return;
                }
            }
        });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
