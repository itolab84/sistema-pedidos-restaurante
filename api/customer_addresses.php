<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $customerId = $_GET['customer_id'] ?? '';
    
    if (empty($customerId)) {
        echo json_encode(['success' => false, 'message' => 'Customer ID required']);
        exit;
    }
    
    try {
        $sql = "SELECT * FROM customer_addresses 
                WHERE customer_id = ? 
                ORDER BY is_primary DESC, created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $addresses = [];
        while ($row = $result->fetch_assoc()) {
            $addresses[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'addresses' => $addresses
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
