<?php
// DIAGNÓSTICO COMPLETO DEL SISTEMA
require_once '../config/auth.php';
$auth->requireLogin();
$db = AdminDB::getInstance();

echo "<h2>🔍 DIAGNÓSTICO COMPLETO DEL SISTEMA</h2>";

// 1. Configuración PHP
echo "<h3>1. Configuración PHP</h3>";
echo "<ul>";
echo "<li><strong>file_uploads:</strong> " . (ini_get('file_uploads') ? '✅ Habilitado' : '❌ Deshabilitado') . "</li>";
echo "<li><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</li>";
echo "<li><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</li>";
echo "<li><strong>max_file_uploads:</strong> " . ini_get('max_file_uploads') . "</li>";
echo "<li><strong>memory_limit:</strong> " . ini_get('memory_limit') . "</li>";
echo "</ul>";

// 2. Directorio de uploads
echo "<h3>2. Directorio de Uploads</h3>";
$uploadDir = '../../uploads/additionals/';
echo "<ul>";
echo "<li><strong>Ruta:</strong> " . realpath($uploadDir) . "</li>";
echo "<li><strong>Existe:</strong> " . (is_dir($uploadDir) ? '✅ Sí' : '❌ No') . "</li>";
echo "<li><strong>Escribible:</strong> " . (is_writable($uploadDir) ? '✅ Sí' : '❌ No') . "</li>";
echo "<li><strong>Permisos:</strong> " . (file_exists($uploadDir) ? substr(sprintf('%o', fileperms($uploadDir)), -4) : 'N/A') . "</li>";
echo "</ul>";

// 3. Base de datos
echo "<h3>3. Base de Datos</h3>";
try {
    $conn = $db->getConnection();
    echo "<ul>";
    echo "<li><strong>Conexión:</strong> ✅ OK</li>";
    
    // Verificar tabla
    $result = $conn->query("SHOW TABLES LIKE 'additional_images'");
    echo "<li><strong>Tabla additional_images:</strong> " . ($result->num_rows > 0 ? '✅ Existe' : '❌ No existe') . "</li>";
    
    if ($result->num_rows > 0) {
        // Verificar estructura
        $structure = $conn->query("DESCRIBE additional_images");
        echo "<li><strong>Estructura de tabla:</strong><br>";
        echo "<table border='1' style='margin: 10px;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $structure->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table></li>";
        
        // Contar registros
        $count = $conn->query("SELECT COUNT(*) as total FROM additional_images")->fetch_assoc();
        echo "<li><strong>Registros existentes:</strong> " . $count['total'] . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<ul><li><strong>Error BD:</strong> ❌ " . $e->getMessage() . "</li></ul>";
}

// 4. Verificar adicionales
echo "<h3>4. Adicionales Disponibles</h3>";
try {
    $additionals = $db->fetchAll("SELECT id, name FROM additionals ORDER BY id LIMIT 5");
    if (empty($additionals)) {
        echo "<p>❌ No hay adicionales en la base de datos</p>";
    } else {
        echo "<ul>";
        foreach ($additionals as $add) {
            echo "<li><strong>ID " . $add['id'] . ":</strong> " . htmlspecialchars($add['name']) . 
                 " - <a href='upload-images.php?id=" . $add['id'] . "'>Probar subida</a></li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error consultando adicionales: " . $e->getMessage() . "</p>";
}

// 5. Test de escritura
echo "<h3>5. Test de Escritura</h3>";
$testFile = $uploadDir . 'test_' . time() . '.txt';
try {
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    if (file_put_contents($testFile, 'Test de escritura: ' . date('Y-m-d H:i:s'))) {
        echo "<p>✅ Escritura exitosa: " . $testFile . "</p>";
        if (file_exists($testFile)) {
            unlink($testFile); // Limpiar
            echo "<p>✅ Eliminación exitosa</p>";
        }
    } else {
        echo "<p>❌ No se pudo escribir archivo de prueba</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error en test de escritura: " . $e->getMessage() . "</p>";
}

// 6. Variables $_POST y $_FILES
echo "<h3>6. Variables de Request</h3>";
echo "<ul>";
echo "<li><strong>Método:</strong> " . $_SERVER['REQUEST_METHOD'] . "</li>";
echo "<li><strong>\$_POST:</strong> " . (empty($_POST) ? 'Vacío' : print_r($_POST, true)) . "</li>";
echo "<li><strong>\$_FILES:</strong> " . (empty($_FILES) ? 'Vacío' : print_r($_FILES, true)) . "</li>";
echo "</ul>";

// 7. Enlaces útiles
echo "<h3>7. Enlaces de Prueba</h3>";
echo "<ul>";
echo "<li><a href='upload-images.php?id=1'>Subir imágenes (ID=1)</a></li>";
echo "<li><a href='upload-ultra-simple.php?id=1'>Versión ultra simple (ID=1)</a></li>";
echo "<li><a href='../../upload_simple.php'>Test independiente</a></li>";
echo "<li><a href='index.php'>Volver a adicionales</a></li>";
echo "</ul>";
?>
