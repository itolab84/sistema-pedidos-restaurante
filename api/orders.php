<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$conn = getDBConnection();

// Create new order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['customer_name']) || !isset($data['items']) || !isset($data['payment_method'])) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }
    
    $customer_name = $conn->real_escape_string($data['customer_name']);
    $customer_email = $conn->real_escape_string($data['customer_email'] ?? '');
    $customer_phone = $conn->real_escape_string($data['customer_phone'] ?? '');
    $payment_method = $conn->real_escape_string($data['payment_method']);
    $items = $data['items'];
    
    // Calculate total
    $total = 0;
    foreach ($items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    
    // Insert order
    $sql = "INSERT INTO orders (customer_name, customer_email, customer_phone, total_amount, payment_method) 
            VALUES ('$customer_name', '$customer_email', '$customer_phone', $total, '$payment_method')";
    
    if ($conn->query($sql)) {
        $order_id = $conn->insert_id;
        
        // Insert order items
        foreach ($items as $item) {
            $product_id = $item['id'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            
            $sql = "INSERT INTO order_items (order_id, product_id, quantity, price) 
                    VALUES ($order_id, $product_id, $quantity, $price)";
            $conn->query($sql);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Pedido creado exitosamente',
            'order_id' => $order_id,
            'total' => $total
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear el pedido']);
    }
}

// Get order details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    
    $sql = "SELECT o.*, 
                   GROUP_CONCAT(CONCAT(p.name, ' x', oi.quantity, ' ($', oi.price, ')') SEPARATOR ', ') as items
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE o.id = $order_id
            GROUP BY o.id";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        echo json_encode(['success' => true, 'order' => $order]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
    }
}

$conn->close();
?>
