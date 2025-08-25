<?php
require_once '../config/auth.php';
$auth->requireLogin();
$db = AdminDB::getInstance();

$id = (int)($_GET['id'] ?? 1);
$message = '';
$maxFileSize = 2 * 1024 * 1024; // 2MB
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$uploadDir = __DIR__ . '/../../uploads/additionals/';
$relativeDir = 'uploads/additionals/';

// Crear directorio si no existe
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Procesar subida AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    if ($file['error'] !== 0) {
        http_response_code(400);
        echo "Error al subir archivo: " . $file['error'];
        exit;
    }
    if ($file['size'] > $maxFileSize) {
        http_response_code(400);
        echo "Archivo demasiado grande (máx 2MB)";
        exit;
    }
    if (!in_array($file['type'], $allowedTypes)) {
        http_response_code(400);
        echo "Tipo de archivo no permitido";
        exit;
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = 'img_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $targetPath = $uploadDir . $newName;
    $imagePath = $relativeDir . $newName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        try {
            $conn = $db->getConnection();
            $sql = "INSERT INTO additional_images (additional_id, image_path, original_name, file_size, mime_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('issis', $id, $imagePath, $file['name'], $file['size'], $file['type']);
            $stmt->execute();
            echo "OK";
        } catch (Exception $e) {
            http_response_code(500);
            echo "Error BD: " . $e->getMessage();
        }
    } else {
        http_response_code(500);
        echo "Error moviendo archivo";
    }
    exit;
}

// Eliminar imagen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $imgId = (int)$_GET['delete'];
    $img = $db->fetchOne("SELECT * FROM additional_images WHERE id = ? AND additional_id = ?", [$imgId, $id]);
    if ($img) {
        @unlink(__DIR__ . '/../../' . $img['image_path']);
        $db->execute("DELETE FROM additional_images WHERE id = ?", [$imgId]);
        $message = "Imagen eliminada";
    }
}

// Obtener imágenes existentes
$images = [];
try {
    $images = $db->fetchAll("SELECT * FROM additional_images WHERE additional_id = ? ORDER BY created_at DESC", [$id]);
} catch (Exception $e) {
    $message .= " | Error consultando BD: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gestión de Imágenes de Adicional</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h2>Imágenes de Adicional</h2>
    <p><strong>ID Adicional:</strong> <?= $id ?></p>
    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <div class="card mb-4">
        <div class="card-header">
        <h5>Subir Nuevas Imágenes</h5>

        </div>
        <div class="card-body">
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="mb-3">
                    <label>Seleccionar imagen (JPG, PNG, GIF, máx 2MB):</label>
                    <input type="file" name="image" class="form-control" accept="image/*" required>
                </div>
                <button type="submit" class="btn btn-primary">Subir Imagen</button>
            </form>
                <div id="progressContainer" style="display:none;">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: 0%;" id="progressBar"></div>
                    </div>
                    <p id="progressText"></p>
                </div>
                <script>
                    function uploadImage() {
                        var formData = new FormData(document.getElementById('uploadForm'));
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', 'upload-ultra-simple.php?id=<?= $id ?>', true);
                        
                        xhr.upload.onprogress = function(e) {
                            if (e.lengthComputable) {
                                var percentComplete = (e.loaded / e.total) * 100;
                                document.getElementById('progressBar').style.width = percentComplete + '%';
                                document.getElementById('progressText').innerText = Math.round(percentComplete) + '% cargado';
                                document.getElementById('progressContainer').style.display = 'block';
                            }
                        };
                        
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                document.getElementById('progressText').innerText = 'Carga completada!';
                                location.reload(); // Recargar la página para mostrar la nueva imagen
                            } else {
                                document.getElementById('progressText').innerText = 'Error al cargar la imagen: ' + xhr.responseText;
                            }
                        };
                        
                        xhr.send(formData);
                    }
                </script>

            <div id="uploadMsg"></div>
            <script>
                document.getElementById('uploadForm').onsubmit = function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'upload-ultra-simple.php?id=<?= $id ?>', true);

                    xhr.upload.onprogress = function(e) {
                        if (e.lengthComputable) {
                            var percent = (e.loaded / e.total) * 100;
                            document.getElementById('progressBar').style.width = percent + '%';
                            document.getElementById('progressText').innerText = Math.round(percent) + '% cargado';
                            document.getElementById('progressContainer').style.display = 'block';
                        }
                    };
                    xhr.onload = function() {
                        if (xhr.status === 200 && xhr.responseText === 'OK') {
                            document.getElementById('uploadMsg').innerHTML = '<div class="alert alert-success">Imagen subida correctamente</div>';
                            setTimeout(function(){ location.reload(); }, 1000);
                        } else {
                            document.getElementById('uploadMsg').innerHTML = '<div class="alert alert-danger">' + xhr.responseText + '</div>';
                        }
                    };
                    xhr.send(formData);
                };
            </script>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h5>Imágenes Existentes (<?= count($images) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($images)): ?>
                <p>No hay imágenes</p>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($images as $img): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <img src="../../<?= htmlspecialchars($img['image_path']) ?>"
                                     class="card-img-top" style="height: 150px; object-fit: cover;">
                                <div class="card-body p-2">
                                    <small>
                                        <strong>ID:</strong> <?= $img['id'] ?><br>
                                        <strong>Archivo:</strong> <?= htmlspecialchars($img['original_name']) ?><br>
                                        <strong>Tamaño:</strong> <?= number_format($img['file_size']/1024, 1) ?> KB<br>
                                        <strong>Fecha:</strong> <?= $img['created_at'] ?><br>
                                        <a href="?id=<?= $id ?>&delete=<?= $img['id'] ?>"
                                           class="btn btn-sm btn-danger mt-1"
                                           onclick="return confirm('¿Eliminar esta imagen?')">Eliminar</a>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mt-4">
        <a href="index.php" class="btn btn-secondary">← Volver a Adicionales</a>
        <a href="../index.php" class="btn btn-secondary">← Dashboard</a>
    </div>
</div>
</body>
</html>
