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
        
        // Search for order by order ID and verify contact info (email or phone)
        $stmt = $conn->prepare("
            SELECT 
                o.*
            FROM orders o
            WHERE o.id = ? 
            AND (o.customer_email = ? OR o.customer_phone = ?)
        ");
        
        $stmt->bind_param("iss", $orderNumber, $contactInfo, $contactInfo);
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
        
        // Get order items with product names
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
        
        // Get order status history (with fallback if table doesn't exist)
        $statusHistory = [];
        try {
            $stmt = $conn->prepare("
                SELECT 
                    status,
                    previous_status,
                    changed_by,
                    notes,
                    created_at
                FROM order_status_history
                WHERE order_id = ?
                ORDER BY created_at ASC
            ");
            
            $stmt->bind_param("i", $order['id']);
            $stmt->execute();
            $historyResult = $stmt->get_result();
            while ($history = $historyResult->fetch_assoc()) {
                $statusHistory[] = $history;
            }
        } catch (mysqli_sql_exception $e) {
            // If table doesn't exist, create a basic history entry
            $statusHistory = [
                [
                    'status' => $order['status'],
                    'previous_status' => null,
                    'changed_by' => 'system',
                    'notes' => 'Estado actual de la orden',
                    'created_at' => $order['created_at']
                ]
            ];
        }
        
        // Calculate estimated delivery time based on order status
        $estimatedDelivery = null;
        $status = $order['status'] ?? 'pending';
        
        if (in_array($status, ['confirmed', 'preparing'])) {
            $estimatedDelivery = '30-45 minutos';
        } elseif ($status === 'ready') {
            $estimatedDelivery = 'Listo para entrega';
        } elseif ($status === 'out_for_delivery') {
            $estimatedDelivery = '10-20 minutos';
        } elseif ($status === 'delivered') {
            $estimatedDelivery = 'Entregado';
        }
        
        // Format order data
        $orderData = [
            'id' => $order['id'],
            'order_number' => $order['id'], // Using ID as order number
            'status' => $status,
            'order_type' => 'delivery', // Default since the simple structure doesn't have this field
            'total' => $order['total_amount'],
            'payment_method' => $order['payment_method'],
            'payment_status' => $order['payment_status'] ?? 'pending',
            'customer_name' => $order['customer_name'],
            'customer_phone' => $order['customer_phone'] ?? 'No disponible',
            'customer_email' => $order['customer_email'] ?? 'No disponible',
            'created_at' => $order['created_at'],
            'updated_at' => $order['created_at'], // Using created_at as fallback
            'estimated_delivery' => $estimatedDelivery,
            'items' => $items,
            'status_history' => $statusHistory
        ];
        
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
    error_log("Database error in order_tracking_fixed.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos. Por favor intente más tarde.'
    ]);
} catch (Exception $e) {
    error_log("General error in order_tracking_fixed.php: " . $e->getMessage());
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
