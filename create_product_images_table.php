<?php
// Script para crear la tabla product_images
require_once 'config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Conectado a la base de datos...\n";
    
    // Crear tabla product_images
    $sql = "CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_path VARCHAR(500) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_size INT NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_product_id (product_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ Tabla 'product_images' creada exitosamente!\n";
    
    // Verificar que la tabla se creó correctamente
    $result = $pdo->query("DESCRIBE product_images");
    echo "\n📋 Estructura de la tabla 'product_images':\n";
    echo "+-----------------+------------------+------+-----+---------+----------------+\n";
    echo "| Field           | Type             | Null | Key | Default | Extra          |\n";
    echo "+-----------------+------------------+------+-----+---------+----------------+\n";
    
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        printf("| %-15s | %-16s | %-4s | %-3s | %-7s | %-14s |\n",
            $row['Field'],
            $row['Type'],
            $row['Null'],
            $row['Key'],
            $row['Default'] ?? 'NULL',
            $row['Extra']
        );
    }
    echo "+-----------------+------------------+------+-----+---------+----------------+\n";
    
    echo "\n🎉 ¡Tabla creada exitosamente! Ahora puedes gestionar imágenes de productos.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
