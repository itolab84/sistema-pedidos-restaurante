<?php
require_once 'config/database.php';

try {
    $conn = getDBConnection();
    $conn->query("UPDATE banners SET status = 'active', image_url = 'assets/images/banners/banner1.jpg' WHERE id = 1");
    echo "✅ Banner 1 activado correctamente\n";
    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
