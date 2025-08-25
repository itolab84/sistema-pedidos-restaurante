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
    $pdo = new PDO($dsn, $username, $password, $options);
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['order_id'])) {
        $orderId = $_GET['order_id'];
        
        // Get order details with customer information
        $stmt = $pdo->prepare("
            SELECT 
                o.*,
                CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                cp.phone_number as customer_phone,
                ca.street_address,
                ca.city,
                ca.delivery_instructions
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            LEFT JOIN customer_phones cp ON c.id = cp.customer_id AND cp.is_primary = 1
            LEFT JOIN customer_addresses ca ON o.address_id = ca.id
            WHERE o.id = ? OR o.order_number = ?
        ");
        
        $stmt->execute([$orderId, $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo json_encode([
                'success' => false,
                'message' => 'Pedido no encontrado'
            ]);
            exit;
        }
        
        // Get order items
        $stmt = $pdo->prepare("
            SELECT 
                oi.*,
                p.name as product_name
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate estimated delivery time based on order status and type
        $estimatedDelivery = null;
        if ($order['order_type'] === 'delivery' && in_array($order['status'], ['confirmed', 'preparing'])) {
            $estimatedDelivery = '30-45 minutos';
        } elseif ($order['order_type'] === 'pickup' && in_array($order['status'], ['confirmed', 'preparing'])) {
            $estimatedDelivery = '15-20 minutos';
        }
        
        // Format order data
        $orderData = [
            'id' => $order['id'],
            'order_number' => $order['order_number'] ?? $order['id'],
            'status' => $order['status'],
            'order_type' => $order['order_type'],
            'total' => $order['total'],
            'payment_method' => $order['payment_method'],
            'customer_name' => $order['customer_name'],
            'customer_phone' => $order['customer_phone'],
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at'],
            'estimated_delivery' => $estimatedDelivery,
            'items' => $items
        ];
        
        // Add address info for delivery orders
        if ($order['order_type'] === 'delivery' && $order['street_address']) {
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
            'message' => 'Método no permitido o parámetros faltantes'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in order_tracking.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos'
    ]);
} catch (Exception $e) {
    error_log("General error in order_tracking.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>
