<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$conn = getDBConnection();

// Get payments for a specific order
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
    
    $sql = "SELECT 
                op.*,
                o.customer_name,
                o.total_amount as order_total
            FROM order_payments op
            LEFT JOIN orders o ON op.order_id = o.id
            WHERE op.order_id = $order_id
            ORDER BY op.created_at ASC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $payments = [];
        $total_paid = 0;
        
        while ($row = $result->fetch_assoc()) {
            // Parse validation_data JSON if exists
            if ($row['validation_data']) {
                $row['validation_data'] = json_decode($row['validation_data'], true);
            }
            
            $payments[] = $row;
            $total_paid += floatval($row['amount']);
        }
        
        echo json_encode([
            'success' => true,
            'payments' => $payments,
            'total_paid' => $total_paid,
            'payment_count' => count($payments)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al consultar pagos: ' . $conn->error
        ]);
    }
}

// Get payment statistics
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['stats'])) {
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
    
    // Payment methods summary
    $sql = "SELECT 
                payment_method,
                payment_type,
                COUNT(*) as payment_count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
            FROM order_payments 
            WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'
            GROUP BY payment_method, payment_type
            ORDER BY total_amount DESC";
    
    $result = $conn->query($sql);
    $payment_methods = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $payment_methods[] = $row;
        }
    }
    
    // Daily payments summary
    $sql = "SELECT 
                DATE(created_at) as payment_date,
                COUNT(*) as payment_count,
                SUM(amount) as total_amount,
                COUNT(DISTINCT order_id) as order_count
            FROM order_payments 
            WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'
            GROUP BY DATE(created_at)
            ORDER BY payment_date DESC";
    
    $result = $conn->query($sql);
    $daily_stats = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $daily_stats[] = $row;
        }
    }
    
    // Bank statistics for electronic payments
    $sql = "SELECT 
                bank_origin,
                bank_destination,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount
            FROM order_payments 
            WHERE payment_type = 'electronic' 
            AND DATE(created_at) BETWEEN '$date_from' AND '$date_to'
            AND bank_origin IS NOT NULL
            GROUP BY bank_origin, bank_destination
            ORDER BY total_amount DESC";
    
    $result = $conn->query($sql);
    $bank_stats = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bank_stats[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'date_range' => [
            'from' => $date_from,
            'to' => $date_to
        ],
        'payment_methods' => $payment_methods,
        'daily_stats' => $daily_stats,
        'bank_stats' => $bank_stats
    ]);
}

// Get all payments with pagination
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['order_id']) && !isset($_GET['stats'])) {
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = ($page - 1) * $limit;
    
    // Filters
    $where_conditions = [];
    $params = [];
    
    if (isset($_GET['payment_method']) && !empty($_GET['payment_method'])) {
        $where_conditions[] = "op.payment_method LIKE ?";
        $params[] = '%' . $_GET['payment_method'] . '%';
    }
    
    if (isset($_GET['payment_type']) && !empty($_GET['payment_type'])) {
        $where_conditions[] = "op.payment_type = ?";
        $params[] = $_GET['payment_type'];
    }
    
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $where_conditions[] = "op.status = ?";
        $params[] = $_GET['status'];
    }
    
    if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
        $where_conditions[] = "DATE(op.created_at) >= ?";
        $params[] = $_GET['date_from'];
    }
    
    if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
        $where_conditions[] = "DATE(op.created_at) <= ?";
        $params[] = $_GET['date_to'];
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Count total records
    $count_sql = "SELECT COUNT(*) as total FROM order_payments op $where_clause";
    $stmt = $conn->prepare($count_sql);
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $count_result = $stmt->get_result();
    $total_records = $count_result->fetch_assoc()['total'];
    
    // Get paginated results
    $sql = "SELECT 
                op.*,
                o.customer_name,
                o.customer_email,
                o.total_amount as order_total
            FROM order_payments op
            LEFT JOIN orders o ON op.order_id = o.id
            $where_clause
            ORDER BY op.created_at DESC
            LIMIT $limit OFFSET $offset";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Parse validation_data JSON if exists
            if ($row['validation_data']) {
                $row['validation_data'] = json_decode($row['validation_data'], true);
            }
            $payments[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'payments' => $payments,
        'pagination' => [
            'current_page' => $page,
            'total_records' => intval($total_records),
            'total_pages' => ceil($total_records / $limit),
            'limit' => $limit
        ]
    ]);
}

$conn->close();
?>
