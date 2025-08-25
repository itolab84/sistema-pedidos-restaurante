<?php
// Implementación simple y directa de subida de imágenes
require_once 'admin/config/database.php';

$db = AdminDB::getInstance();

echo "<h2>Subida Simple de Imágenes</h2>";

// Procesar subida
if ($_POST && isset($_FILES['image'])) {
    echo "<h3>Procesando...</h3>";
    
    $uploadDir = 'uploads/additionals/';
    
    // Crear directorio si no existe
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        echo "<p>✅ Directorio creado: $uploadDir</p>";
    }
    
    $file = $_FILES['image'];
    $additionalId = 1; // Usar adicional ID 1
    
    echo "<p><strong>Información del archivo:</strong></p>";
    echo "<ul>";
    echo "<li>Nombre: " . htmlspecialchars($file['name']) . "</li>";
    echo "<li>Tamaño: " . $file['size'] . " bytes</li>";
    echo "<li>Tipo: " . htmlspecialchars($file['type']) . "</li>";
    echo "<li>Error: " . $file['error'] . "</li>";
    echo "</ul>";
    
    if ($file['error'] === 0) {
        // Generar nombre único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = 'img_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
        $targetPath = $uploadDir . $newName;
        
        echo "<p><strong>Moviendo archivo:</strong></p>";
        echo "<p>De: " . $file['tmp_name'] . "</p>";
        echo "<p>A: " . $targetPath . "</p>";
        
        // Mover archivo
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo "<p style='color: green;'>✅ Archivo movido exitosamente</p>";
            
            // Guardar en base de datos
            try {
                $conn = $db->getConnection();
                
                // SQL simple
                $sql = "INSERT INTO additional_images (additional_id, image_path, original_name, file_size, mime_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                
                echo "<p><strong>Preparando consulta SQL:</strong></p>";
                echo "<p>SQL: " . htmlspecialchars($sql) . "</p>";
                
                $stmt = $conn->prepare($sql);
                
                if ($stmt) {
                    $imagePath = $targetPath;
                    $originalName = $file['name'];
                    $fileSize = $file['size'];
                    $mimeType = $file['type'];
                    
                    echo "<p><strong>Parámetros:</strong></p>";
                    echo "<ul>";
                    echo "<li>additional_id: $additionalId</li>";
                    echo "<li>image_path: $imagePath</li>";
                    echo "<li>original_name: $originalName</li>";
                    echo "<li>file_size: $fileSize</li>";
                    echo "<li>mime_type: $mimeType</li>";
                    echo "</ul>";
                    
                    // Bind y ejecutar
                    $stmt->bind_param('issis', $additionalId, $imagePath, $originalName, $fileSize, $mimeType);
                    
                    if ($stmt->execute()) {
                        $insertId = $conn->insert_id;
                        echo "<p style='color: green;'>✅ Guardado en base de datos con ID: $insertId</p>";
                        
                        // Verificar
                        $check = $db->fetchOne("SELECT * FROM additional_images WHERE id = ?", [$insertId]);
                        if ($check) {
                            echo "<p style='color: green;'>✅ Verificación exitosa - Registro encontrado</p>";
                            echo "<pre>" . print_r($check, true) . "</pre>";
                            
                            // Mostrar imagen
                            echo "<p><strong>Imagen subida:</strong></p>";
                            echo "<img src='$targetPath' style='max-width: 300px; border: 1px solid #ccc; padding: 10px;' alt='Imagen subida'>";
                            
                        } else {
                            echo "<p style='color: red;'>❌ Error: No se encontró el registro</p>";
                        }
                        
                    } else {
                        echo "<p style='color: red;'>❌ Error ejecutando: " . $stmt->error . "</p>";
                    }
                    
                } else {
                    echo "<p style='color: red;'>❌ Error preparando consulta: " . $conn->error . "</p>";
                }
                
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Excepción: " . $e->getMessage() . "</p>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ Error moviendo archivo</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Error en subida: " . $file['error'] . "</p>";
    }
}

// Mostrar imágenes existentes
echo "<hr><h3>Imágenes en Base de Datos</h3>";
try {
    $images = $db->fetchAll("SELECT * FROM additional_images ORDER BY created_at DESC LIMIT 10");
    if ($images) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Additional ID</th><th>Ruta</th><th>Nombre Original</th><th>Tamaño</th><th>Fecha</th><th>Imagen</th></tr>";
        foreach ($images as $img) {
            echo "<tr>";
            echo "<td>" . $img['id'] . "</td>";
            echo "<td>" . $img['additional_id'] . "</td>";
            echo "<td>" . htmlspecialchars($img['image_path']) . "</td>";
            echo "<td>" . htmlspecialchars($img['original_name']) . "</td>";
            echo "<td>" . number_format($img['file_size']) . " bytes</td>";
            echo "<td>" . $img['created_at'] . "</td>";
            echo "<td>";
            if (file_exists($img['image_path'])) {
                echo "<img src='" . htmlspecialchars($img['image_path']) . "' style='width: 100px; height: 60px; object-fit: cover;'>";
            } else {
                echo "❌ Archivo no existe";
            }
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No hay imágenes en la base de datos</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error consultando BD: " . $e->getMessage() . "</p>";
}
?>

<hr>
<h3>Subir Nueva Imagen</h3>
<form method="POST" enctype="multipart/form-data">
    <p>
        <label>Seleccionar imagen:</label><br>
        <input type="file" name="image" accept="image/*" required>
    </p>
    <p>
        <button type="submit" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px;">
            Subir Imagen
        </button>
    </p>
</form>

<hr>
<p><a href="debug_images.php">Ver diagnóstico completo</a></p>
<p><a href="admin/login.php">Ir al admin</a></p>
