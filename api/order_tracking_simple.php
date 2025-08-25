<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Get order ID from request
    $orderId = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['order_id'])) {
        $orderId = $_GET['order_id'];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $orderId = $input['order_number'] ?? null;
    }
    
    if (!$orderId) {
        echo json_encode([
            'success' => false,
            'message' => 'Número de pedido requerido'
        ]);
        exit;
    }
    
    // For now, return mock data for testing
    // In a real implementation, this would query the database
    $mockOrder = [
        'id' => $orderId,
        'order_number' => $orderId,
        'status' => 'confirmed',
        'order_type' => 'delivery',
        'total' => '25.50',
        'payment_method' => 'efectivo',
        'customer_name' => 'Cliente de Prueba',
        'customer_phone' => '555-0123',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'updated_at' => date('Y-m-d H:i:s'),
        'estimated_delivery' => '30-45 minutos',
        'items' => [
            [
                'product_name' => 'Hamburguesa Clásica',
                'quantity' => 2,
                'price' => '12.75'
            ],
            [
                'product_name' => 'Papas Fritas',
                'quantity' => 1,
                'price' => '5.50'
            ]
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'order' => $mockOrder
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>
