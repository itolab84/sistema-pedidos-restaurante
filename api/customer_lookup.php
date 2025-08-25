<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $lookup = $input['lookup'] ?? '';
    
    if (empty($lookup)) {
        echo json_encode(['success' => false, 'message' => 'Lookup value required']);
        exit;
    }
    
    try {
        // Search customer by email or phone
        $sql = "SELECT c.*, 
                       GROUP_CONCAT(DISTINCT CONCAT(cp.phone_number, '|', cp.phone_type, '|', cp.is_primary, '|', cp.is_whatsapp) SEPARATOR ';;') as phones
                FROM customers c
                LEFT JOIN customer_phones cp ON c.id = cp.customer_id
                WHERE c.email = ? OR c.id IN (
                    SELECT DISTINCT customer_id FROM customer_phones WHERE phone_number = ?
                )
                AND c.status = 'active'
                GROUP BY c.id
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $lookup, $lookup);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($customer = $result->fetch_assoc()) {
            // Parse phones
            $phones = [];
            if ($customer['phones']) {
                $phoneData = explode(';;', $customer['phones']);
                foreach ($phoneData as $phoneInfo) {
                    $parts = explode('|', $phoneInfo);
                    if (count($parts) >= 4) {
                        $phones[] = [
                            'phone_number' => $parts[0],
                            'phone_type' => $parts[1],
                            'is_primary' => (bool)$parts[2],
                            'is_whatsapp' => (bool)$parts[3]
                        ];
                    }
                }
            }
            
            $customer['phones'] = $phones;
            unset($customer['phones']); // Remove the concatenated string
            $customer['phones'] = $phones; // Add the parsed array
            
            echo json_encode([
                'success' => true,
                'customer' => $customer
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Customer not found'
            ]);
        }
        
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
