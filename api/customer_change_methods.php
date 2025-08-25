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
        case 'GET':
            if (isset($_GET['customer_id'])) {
                getCustomerChangeMethods($db, $_GET['customer_id']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'customer_id is required']);
            }
            break;
        case 'POST':
            createCustomerChangeMethod($db);
            break;
        case 'PUT':
            updateCustomerChangeMethod($db);
            break;
        case 'DELETE':
            deleteCustomerChangeMethod($db);
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

function getCustomerChangeMethods($db, $customer_id) {
    try {
        $sql = "SELECT ccm.*, b.name as bank_name_full 
                FROM customer_change_methods ccm
                LEFT JOIN banks b ON ccm.bank_code = b.code
                WHERE ccm.customer_id = :customer_id AND ccm.is_active = 1
                ORDER BY ccm.is_default DESC, ccm.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'methods' => $methods
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener métodos de cambio: ' . $e->getMessage()
        ]);
    }
}

function createCustomerChangeMethod($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos requeridos
        $required_fields = ['customer_id', 'phone_number', 'cedula', 'bank_code'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                throw new Exception("Campo requerido faltante: $field");
            }
        }
        
        // Obtener nombre del banco
        $bank_name = '';
        if (!empty($input['bank_code'])) {
            $bank_sql = "SELECT name FROM banks WHERE code = :bank_code";
            $bank_stmt = $db->prepare($bank_sql);
            $bank_stmt->bindParam(':bank_code', $input['bank_code']);
            $bank_stmt->execute();
            $bank_result = $bank_stmt->fetch(PDO::FETCH_ASSOC);
            $bank_name = $bank_result ? $bank_result['name'] : $input['bank_name'] ?? '';
        }
        
        // Si es método por defecto, desactivar otros métodos por defecto del cliente
        if (isset($input['is_default']) && $input['is_default']) {
            $update_sql = "UPDATE customer_change_methods SET is_default = 0 WHERE customer_id = :customer_id";
            $update_stmt = $db->prepare($update_sql);
            $update_stmt->bindParam(':customer_id', $input['customer_id']);
            $update_stmt->execute();
        }
        
        // Insertar nuevo método
        $sql = "INSERT INTO customer_change_methods 
                (customer_id, phone_number, cedula, bank_code, bank_name, is_default, is_active) 
                VALUES (:customer_id, :phone_number, :cedula, :bank_code, :bank_name, :is_default, 1)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'customer_id' => $input['customer_id'],
            'phone_number' => $input['phone_number'],
            'cedula' => $input['cedula'],
            'bank_code' => $input['bank_code'],
            'bank_name' => $bank_name,
            'is_default' => $input['is_default'] ?? false
        ]);
        
        $method_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Método de cambio creado exitosamente',
            'method_id' => $method_id
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Error al crear método de cambio: ' . $e->getMessage()
        ]);
    }
}

function updateCustomerChangeMethod($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || empty($input['id'])) {
            throw new Exception("ID del método es requerido");
        }
        
        $id = $input['id'];
        $updates = [];
        $params = ['id' => $id];
        
        // Campos que se pueden actualizar
        $updatable_fields = ['phone_number', 'cedula', 'bank_code', 'bank_name', 'is_default', 'is_active'];
        
        foreach ($updatable_fields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = :$field";
                $params[$field] = $input[$field];
            }
        }
        
        if (empty($updates)) {
            throw new Exception("No hay campos para actualizar");
        }
        
        // Si se está marcando como por defecto, desactivar otros métodos por defecto
        if (isset($input['is_default']) && $input['is_default']) {
            // Obtener customer_id del método actual
            $get_customer_sql = "SELECT customer_id FROM customer_change_methods WHERE id = :id";
            $get_customer_stmt = $db->prepare($get_customer_sql);
            $get_customer_stmt->bindParam(':id', $id);
            $get_customer_stmt->execute();
            $customer_data = $get_customer_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer_data) {
                $update_default_sql = "UPDATE customer_change_methods SET is_default = 0 WHERE customer_id = :customer_id AND id != :id";
                $update_default_stmt = $db->prepare($update_default_sql);
                $update_default_stmt->execute([
                    'customer_id' => $customer_data['customer_id'],
                    'id' => $id
                ]);
            }
        }
        
        $sql = "UPDATE customer_change_methods SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :id";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("No se encontró el método o no se realizaron cambios");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Método de cambio actualizado exitosamente'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar método de cambio: ' . $e->getMessage()
        ]);
    }
}

function deleteCustomerChangeMethod($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || empty($input['id'])) {
            throw new Exception("ID del método es requerido");
        }
        
        // Marcar como inactivo en lugar de eliminar
        $sql = "UPDATE customer_change_methods SET is_active = 0, updated_at = NOW() WHERE id = :id";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $input['id']);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("No se encontró el método de cambio");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Método de cambio eliminado exitosamente'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar método de cambio: ' . $e->getMessage()
        ]);
    }
}
?>
