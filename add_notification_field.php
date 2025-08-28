<?php
/**
 * Script para agregar el campo 'notification' a la tabla orders
 * Este campo marcará si una orden ya fue notificada (1) o no (0)
 */

require_once 'config/database.php';

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Agregando campo 'notification' a la tabla orders...\n";
    
    // Verificar si el campo ya existe
    $stmt = $db->prepare("SHOW COLUMNS FROM orders LIKE 'notification'");
    $stmt->execute();
    $exists = $stmt->fetch();
    
    if (!$exists) {
        // Agregar el campo notification
        $sql = "ALTER TABLE orders ADD COLUMN notification TINYINT(1) DEFAULT 0 COMMENT 'Indica si la orden ya fue notificada (0=no, 1=si)'";
        $db->exec($sql);
        echo "✅ Campo 'notification' agregado exitosamente.\n";
        
        // Marcar todas las órdenes existentes como no notificadas (0)
        $updateSql = "UPDATE orders SET notification = 0";
        $db->exec($updateSql);
        echo "✅ Órdenes existentes marcadas como no notificadas.\n";
        
    } else {
        echo "ℹ️ El campo 'notification' ya existe en la tabla orders.\n";
    }
    
    // Mostrar estructura actualizada
    echo "\nEstructura actual de la tabla orders:\n";
    $stmt = $db->prepare("DESCRIBE orders");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']} ({$column['Null']}, Default: {$column['Default']})\n";
    }
    
    echo "\n✅ Proceso completado exitosamente.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
