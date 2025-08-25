<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../admin/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'POST':
            createChangeHistory($db);
            break;
        case 'GET':
            if (isset($_GET['order_id'])) {
                getChangeHistoryByOrder($db, $_GET['order_id']);
            } elseif (isset($_GET['customer_id'])) {
                getChangeHistoryByCustomer($db, $_GET['customer_id']);
            } else {
                getChangeHistory($db);
            }
            break;
        case 'PUT':
            updateChangeHistory($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

function createChangeHistory($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos requeridos
        $required_fields = ['order_id', 'customer_id', 'amount_paid', 'order_amount', 'change_amount'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                throw new Exception("Campo requerido faltante: $field");
            }
        }
        
        // Preparar datos con valores por defecto
        $data = [
            'order_id' => $input['order_id'],
            'customer_id' => $input['customer_id'],
            'amount_paid' => $input['amount_paid'],
            'order_amount' => $input['order_amount'],
            'change_amount' => $input['change_amount'],
            'change_currency' => $input['change_currency'] ?? 'VES',
            'change_method' => $input['change_method'] ?? 'pagomovil',
            'employee_id' => $input['employee_id'] ?? null,
            'payment_status' => $input['payment_status'] ?? 'pending',
            'bank_response' => $input['bank_response'] ?? null,
            'reference_number' => $input['reference_number'] ?? null,
            'customer_phone' => $input['customer_phone'] ?? null,
            'customer_cedula' => $input['customer_cedula'] ?? null,
            'bank_code' => $input['bank_code'] ?? null,
            'bank_name' => $input['bank_name'] ?? null,
            'notes' => $input['notes'] ?? null
        ];
        
        // Insertar en la base de datos
        $sql = "INSERT INTO change_history (
            order_id, customer_id, amount_paid, order_amount, change_amount,
            change_currency, change_method, employee_id, payment_status,
            bank_response, reference_number, customer_phone, customer_cedula,
            bank_code, bank_name, notes
        ) VALUES (
            :order_id, :customer_id, :amount_paid, :order_amount, :change_amount,
            :change_currency, :change_method, :employee_id, :payment_status,
            :bank_response, :reference_number, :customer_phone, :customer_cedula,
            :bank_code, :bank_name, :notes
        )";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($data);
        
        $change_id = $db->lastInsertId();
        
        // Si hay información de método de cambio del cliente, guardarla
        if (isset($input['save_customer_method']) && $input['save_customer_method'] && 
            !empty($data['customer_phone']) && !empty($data['customer_cedula']) && !empty($data['bank_code'])) {
            
            saveCustomerChangeMethod($db, [
                'customer_id' => $data['customer_id'],
                'phone_number' => $data['customer_phone'],
                'cedula' => $data['customer_cedula'],
                'bank_code' => $data['bank_code'],
                'bank_name' => $data['bank_name']
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Historial de vuelto creado exitosamente',
            'change_id' => $change_id,
            'data' => $data
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Error al crear historial de vuelto: ' . $e->getMessage()
        ]);
    }
}

function getChangeHistory($db) {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
        
        $where_conditions = [];
        $params = [];
        
        if (!empty($status_filter)) {
            $where_conditions[] = "ch.payment_status = :status";
            $params['status'] = $status_filter;
        }
        
        if (!empty($date_from)) {
            $where_conditions[] = "DATE(ch.created_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if (!empty($date_to)) {
            $where_conditions[] = "DATE(ch.created_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Consulta principal
        $sql = "SELECT 
            ch.*,
            o.order_number,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.email as customer_email,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM change_history ch
        LEFT JOIN orders o ON ch.order_id = o.id
        LEFT JOIN customers c ON ch.customer_id = c.id
        LEFT JOIN employees e ON ch.employee_id = e.id
        $where_clause
        ORDER BY ch.created_at DESC
        LIMIT :limit OFFSET :offset";
        
        $stmt = $db->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contar total de registros
        $count_sql = "SELECT COUNT(*) as total 
                     FROM change_history ch 
                     LEFT JOIN orders o ON ch.order_id = o.id
                     LEFT JOIN customers c ON ch.customer_id = c.id
                     LEFT JOIN employees e ON ch.employee_id = e.id
                     $where_clause";
        
        $count_stmt = $db->prepare($count_sql);
        foreach ($params as $key => $value) {
            $count_stmt->bindValue(":$key", $value);
        }
        $count_stmt->execute();
        $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'data' => $changes,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_records' => (int)$total,
                'per_page' => $limit
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener historial de vueltos: ' . $e->getMessage()
        ]);
    }
}

function getChangeHistoryByOrder($db, $order_id) {
    try {
        $sql = "SELECT 
            ch.*,
            o.order_number,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.email as customer_email,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM change_history ch
        LEFT JOIN orders o ON ch.order_id = o.id
        LEFT JOIN customers c ON ch.customer_id = c.id
        LEFT JOIN employees e ON ch.employee_id = e.id
        WHERE ch.order_id = :order_id
        ORDER BY ch.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $changes
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener historial de vueltos por orden: ' . $e->getMessage()
        ]);
    }
}

function getChangeHistoryByCustomer($db, $customer_id) {
    try {
        $sql = "SELECT 
            ch.*,
            o.order_number,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.email as customer_email,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM change_history ch
        LEFT JOIN orders o ON ch.order_id = o.id
        LEFT JOIN customers c ON ch.customer_id = c.id
        LEFT JOIN employees e ON ch.employee_id = e.id
        WHERE ch.customer_id = :customer_id
        ORDER BY ch.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $changes
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener historial de vueltos por cliente: ' . $e->getMessage()
        ]);
    }
}

function updateChangeHistory($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || empty($input['id'])) {
            throw new Exception("ID del historial de vuelto es requerido");
        }
        
        $id = $input['id'];
        $updates = [];
        $params = ['id' => $id];
        
        // Campos que se pueden actualizar
        $updatable_fields = [
            'payment_status', 'bank_response', 'reference_number',
            'processed_at', 'notes', 'employee_id'
        ];
        
        foreach ($updatable_fields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = :$field";
                $params[$field] = $input[$field];
            }
        }
        
        if (empty($updates)) {
            throw new Exception("No hay campos para actualizar");
        }
        
        // Si se está marcando como completado, establecer processed_at
        if (isset($input['payment_status']) && $input['payment_status'] === 'completed' && !isset($input['processed_at'])) {
            $updates[] = "processed_at = NOW()";
        }
        
        $sql = "UPDATE change_history SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :id";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("No se encontró el historial de vuelto o no se realizaron cambios");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Historial de vuelto actualizado exitosamente'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar historial de vuelto: ' . $e->getMessage()
        ]);
    }
}

function saveCustomerChangeMethod($db, $data) {
    try {
        // Verificar si ya existe este método para el cliente
        $check_sql = "SELECT id FROM customer_change_methods 
                     WHERE customer_id = :customer_id 
                     AND phone_number = :phone_number 
                     AND cedula = :cedula 
                     AND bank_code = :bank_code";
        
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->execute([
            'customer_id' => $data['customer_id'],
            'phone_number' => $data['phone_number'],
            'cedula' => $data['cedula'],
            'bank_code' => $data['bank_code']
        ]);
        
        if ($check_stmt->rowCount() === 0) {
            // No existe, crear nuevo método
            $insert_sql = "INSERT INTO customer_change_methods 
                          (customer_id, phone_number, cedula, bank_code, bank_name, is_default) 
                          VALUES (:customer_id, :phone_number, :cedula, :bank_code, :bank_name, :is_default)";
            
            $insert_stmt = $db->prepare($insert_sql);
            $insert_stmt->execute([
                'customer_id' => $data['customer_id'],
                'phone_number' => $data['phone_number'],
                'cedula' => $data['cedula'],
                'bank_code' => $data['bank_code'],
                'bank_name' => $data['bank_name'],
                'is_default' => true
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error saving customer change method: " . $e->getMessage());
        return false;
    }
}
?>
