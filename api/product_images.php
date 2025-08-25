<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $productId = $_GET['product_id'] ?? '';
    
    if (empty($productId)) {
        echo json_encode(['success' => false, 'message' => 'Product ID required']);
        exit;
    }
    
    try {
        $sql = "SELECT * FROM product_images 
                WHERE product_id = ? 
                ORDER BY is_primary DESC, display_order ASC, created_at ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $images = [];
        while ($row = $result->fetch_assoc()) {
            $images[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'images' => $images
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>
