<?php
// Script para crear la tabla additional_images
require_once 'admin/config/database.php';

$db = AdminDB::getInstance();

$sql = "
CREATE TABLE IF NOT EXISTS `additional_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `additional_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_additional_id` (`additional_id`),
  KEY `idx_is_primary` (`is_primary`),
  KEY `idx_sort_order` (`sort_order`),
  CONSTRAINT `fk_additional_images_additional` FOREIGN KEY (`additional_id`) REFERENCES `additionals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->query($sql);
    echo "✅ Tabla 'additional_images' creada exitosamente!\n";
    echo "La tabla está lista para almacenar múltiples imágenes de adicionales.\n";
} catch (Exception $e) {
    echo "❌ Error al crear la tabla: " . $e->getMessage() . "\n";
}
?>
