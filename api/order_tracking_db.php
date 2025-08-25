<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

try {
    // Use MySQLi connection from the existing config
    $conn = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['order_number']) || !isset($input['contact_info'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Datos incompletos. Se requiere número de pedido y información de contacto.'
            ]);
            exit;
        }
        
        $orderNumber = trim($input['order_number']);
        $contactInfo = trim($input['contact_info']);
        
        if (empty($orderNumber) || empty($contactInfo)) {
            echo json_encode([
                'success' => false,
                'message' => 'Número de pedido e información de contacto son requeridos.'
            ]);
            exit;
        }
        
        // Search for order by order number or ID and verify contact info
        $stmt = $conn->prepare("
            SELECT 
                o.*,
                CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name,
                c.email as customer_email,
                cp.phone_number as customer_phone,
                ca.street_address,
                ca.city,
                ca.delivery_instructions
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            LEFT JOIN customer_phones cp ON c.id = cp.customer_id AND cp.is_primary = 1
            LEFT JOIN customer_addresses ca ON o.address_id = ca.id
            WHERE (o.id = ? OR o.order_number = ?) 
            AND (c.email = ? OR cp.phone_number = ? OR o.customer_email = ? OR o.customer_phone = ?)
        ");
        
        $stmt->bind_param("ssssss", $orderNumber, $orderNumber, $contactInfo, $contactInfo, $contactInfo, $contactInfo);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        
        if (!$order) {
            echo json_encode([
                'success' => false,
                'message' => 'Pedido no encontrado o información de contacto incorrecta.'
            ]);
            exit;
        }
        
        // Get order items
        $stmt = $conn->prepare("
            SELECT 
                oi.*,
                p.name as product_name
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        
        $stmt->bind_param("i", $order['id']);
        $stmt->execute();
        $itemsResult = $stmt->get_result();
        $items = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $items[] = $item;
        }
        
        // Calculate estimated delivery time based on order status and type
        $estimatedDelivery = null;
        $orderType = $order['order_type'] ?? 'delivery';
        $status = $order['status'] ?? $order['order_status'] ?? 'pending';
        
        if ($orderType === 'delivery' && in_array($status, ['confirmed', 'preparing'])) {
            $estimatedDelivery = '30-45 minutos';
        } elseif ($orderType === 'pickup' && in_array($status, ['confirmed', 'preparing'])) {
            $estimatedDelivery = '15-20 minutos';
        }
        
        // Format order data
        $orderData = [
            'id' => $order['id'],
            'order_number' => $order['order_number'] ?? $order['id'],
            'status' => $status,
            'order_type' => $orderType,
            'total' => $order['total'] ?? $order['total_amount'] ?? 0,
            'payment_method' => $order['payment_method'] ?? 'No especificado',
            'customer_name' => trim($order['customer_name']) ?: ($order['customer_name'] ?? 'Cliente'),
            'customer_phone' => $order['customer_phone'] ?? $order['customer_phone'] ?? 'No disponible',
            'customer_email' => $order['customer_email'] ?? 'No disponible',
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at'] ?? $order['created_at'],
            'estimated_delivery' => $estimatedDelivery,
            'items' => $items
        ];
        
        // Add address info for delivery orders
        if ($orderType === 'delivery' && !empty($order['street_address'])) {
            $orderData['delivery_address'] = [
                'street_address' => $order['street_address'],
                'city' => $order['city'],
                'delivery_instructions' => $order['delivery_instructions']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'order' => $orderData
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido. Use POST.'
        ]);
    }
    
} catch (mysqli_sql_exception $e) {
    error_log("Database error in order_tracking_db.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos. Por favor intente más tarde.'
    ]);
} catch (Exception $e) {
    error_log("General error in order_tracking_db.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor. Por favor intente más tarde.'
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
