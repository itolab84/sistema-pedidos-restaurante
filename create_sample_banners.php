<?php
require_once 'config/database.php';

try {
    $conn = getDBConnection();
    
    // Verificar si ya existen banners
    $result = $conn->query('SELECT COUNT(*) as count FROM banners');
    $count = $result->fetch_assoc()['count'];
    
    echo "Banners existentes en BD: " . $count . "\n";
    
    if ($count == 0) {
        echo "Creando banners de ejemplo...\n";
        
        // Banner Hero 1
        $stmt = $conn->prepare('INSERT INTO banners (title, description, image_url, link_type, position, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssssi', 
            'Hamburguesas Premium', 
            'Ingredientes frescos, sabor incomparable', 
            'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=1200&h=400&fit=crop', 
            'none', 
            'hero', 
            'active', 
            1
        );
        $stmt->execute();
        echo "✅ Banner Hero 1 creado\n";
        
        // Banner Hero 2
        $stmt->bind_param('ssssssi', 
            'Bebidas Refrescantes', 
            'Perfectas para acompañar tu comida', 
            'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=1200&h=400&fit=crop', 
            'none', 
            'hero', 
            'active', 
            2
        );
        $stmt->execute();
        echo "✅ Banner Hero 2 creado\n";
        
        // Banner Hero 3
        $stmt->bind_param('ssssssi', 
            'Ofertas Especiales', 
            'Descuentos increíbles por tiempo limitado', 
            'https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=1200&h=400&fit=crop', 
            'none', 
            'hero', 
            'active', 
            3
        );
        $stmt->execute();
        echo "✅ Banner Hero 3 creado\n";
        
        // Banner Sidebar
        $stmt->bind_param('ssssssi', 
            'Postres Deliciosos', 
            'Endulza tu día con nuestros postres', 
            'https://images.unsplash.com/photo-1551024506-0bccd828d307?w=400&h=300&fit=crop', 
            'none', 
            'sidebar', 
            'active', 
            1
        );
        $stmt->execute();
        echo "✅ Banner Sidebar creado\n";
        
        // Banner Footer
        $stmt->bind_param('ssssssi', 
            'Delivery Gratis', 
            'En pedidos mayores a $25', 
            'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=400&h=250&fit=crop', 
            'none', 
            'footer', 
            'active', 
            1
        );
        $stmt->execute();
        echo "✅ Banner Footer creado\n";
        
        echo "\n🎉 Todos los banners de ejemplo han sido creados!\n";
    }
    
    // Mostrar banners existentes
    echo "\n📊 Banners activos en la base de datos:\n";
    $banners = $conn->query('SELECT * FROM banners WHERE status = "active" ORDER BY position, sort_order');
    while ($banner = $banners->fetch_assoc()) {
        echo "- " . $banner['title'] . " (" . $banner['position'] . ")\n";
    }
    
    echo "\n✅ Sistema de banners listo para usar!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
