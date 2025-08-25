<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$conn = getDBConnection();

// Get all additionals
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $sql = "SELECT * FROM additionals WHERE status = 'active' ORDER BY category, name";
        $result = $conn->query($sql);
        
        $additionals = [];
        while ($row = $result->fetch_assoc()) {
            $additionals[] = $row;
        }
        
        echo json_encode(['success' => true, 'additionals' => $additionals]);
        
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
