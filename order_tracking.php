<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'flavorfinder';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['order_number']) || !isset($input['contact_info'])) {
        echo json_encode(['success' => false, 'message' => 'Número de pedido y información de contacto son requeridos']);
        exit;
    }
    
    $order_number = trim($input['order_number']);
    $contact_info = trim($input['contact_info']);
    
    if (empty($order_number) || empty($contact_info)) {
        echo json_encode(['success' => false, 'message' => 'Número de pedido y información de contacto no pueden estar vacíos']);
        exit;
    }
    
    try {
        // Buscar el pedido por número y verificar el email o teléfono del cliente
        $stmt = $pdo->prepare("
            SELECT o.*, c.first_name, c.last_name, c.email, c.phone_primary, c.phone_whatsapp,
                   ca.street_address, ca.city, ca.postal_code, ca.delivery_instructions
            FROM orders o 
            JOIN customers c ON o.customer_id = c.id 
            LEFT JOIN customer_addresses ca ON o.delivery_address_id = ca.id
            WHERE o.order_number = ? 
            AND (c.email = ? OR c.phone_primary = ? OR c.phone_whatsapp = ?)
        ");
        
        $stmt->execute([$order_number, $contact_info, $contact_info, $contact_info]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o información de contacto incorrecta']);
            exit;
        }
        
        // Obtener los items del pedido
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name as product_name, p.image_url
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order['id']]);
        $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Determinar el estado del pedido y crear timeline
        $timeline = [];
        $current_status = $order['status'];
        
        // Estados posibles: pending, confirmed, preparing, ready, out_for_delivery, delivered, cancelled
        $statuses = [
            'pending' => ['title' => 'Pedido Recibido', 'description' => 'Tu pedido ha sido recibido y está siendo procesado'],
            'confirmed' => ['title' => 'Pedido Confirmado', 'description' => 'Tu pedido ha sido confirmado y está en preparación'],
            'preparing' => ['title' => 'En Preparación', 'description' => 'Nuestro chef está preparando tu pedido'],
            'ready' => ['title' => 'Listo para Entrega', 'description' => 'Tu pedido está listo y será enviado pronto'],
            'out_for_delivery' => ['title' => 'En Camino', 'description' => 'Tu pedido está en camino hacia tu ubicación'],
            'delivered' => ['title' => 'Entregado', 'description' => 'Tu pedido ha sido entregado exitosamente'],
            'cancelled' => ['title' => 'Cancelado', 'description' => 'El pedido ha sido cancelado']
        ];
        
        $status_order = ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered'];
        $current_index = array_search($current_status, $status_order);
        
        foreach ($status_order as $index => $status) {
            if ($current_status === 'cancelled') {
                // Si está cancelado, solo mostrar pending y cancelled
                if ($status === 'pending' || $status === 'cancelled') {
                    $timeline[] = [
                        'status' => $status,
                        'title' => $statuses[$status]['title'],
                        'description' => $statuses[$status]['description'],
                        'completed' => true,
                        'active' => $status === 'cancelled',
                        'time' => $status === 'pending' ? $order['created_at'] : $order['updated_at']
                    ];
                }
            } else {
                $timeline[] = [
                    'status' => $status,
                    'title' => $statuses[$status]['title'],
                    'description' => $statuses[$status]['description'],
                    'completed' => $index <= $current_index,
                    'active' => $index === $current_index,
                    'time' => $index <= $current_index ? ($index === 0 ? $order['created_at'] : $order['updated_at']) : null
                ];
            }
        }
        
        // Calcular tiempo estimado de entrega
        $estimated_delivery = null;
        if ($current_status !== 'delivered' && $current_status !== 'cancelled') {
            $created_time = new DateTime($order['created_at']);
            $estimated_delivery = $created_time->add(new DateInterval('PT45M'))->format('Y-m-d H:i:s');
        }
        
        $response = [
            'success' => true,
            'order' => [
                'order_number' => $order['order_number'],
                'status' => $current_status,
                'status_display' => $statuses[$current_status]['title'],
                'created_at' => $order['created_at'],
                'updated_at' => $order['updated_at'],
                'estimated_delivery' => $estimated_delivery,
                'total_amount' => $order['total_amount'],
                'order_type' => $order['order_type'],
                'payment_method' => $order['payment_method'],
                'customer' => [
                    'name' => $order['first_name'] . ' ' . $order['last_name'],
                    'email' => $order['email'],
                    'phone' => $order['phone_primary']
                ],
                'delivery_address' => $order['order_type'] === 'delivery' ? [
                    'street_address' => $order['street_address'],
                    'city' => $order['city'],
                    'postal_code' => $order['postal_code'],
                    'delivery_instructions' => $order['delivery_instructions']
                ] : null,
                'items' => $order_items,
                'timeline' => $timeline
            ]
        ];
        
        echo json_encode($response);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al buscar el pedido: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
