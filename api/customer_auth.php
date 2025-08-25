<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

session_start();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'check_session':
            checkCustomerSession();
            break;
        case 'login':
            customerLogin();
            break;
        case 'register':
            customerRegister();
            break;
        case 'logout':
            customerLogout();
            break;
        case 'add_change_method':
            addCustomerChangeMethod();
            break;
        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function checkCustomerSession() {
    if (!isset($_SESSION['customer_id'])) {
        echo json_encode([
            'success' => true,
            'logged_in' => false
        ]);
        return;
    }

    global $pdo;
    
    // Get customer data with addresses, phones, and change methods
    $stmt = $pdo->prepare("
        SELECT c.*, 
               GROUP_CONCAT(DISTINCT CONCAT(ca.id, '|', ca.street_address, '|', ca.city, '|', 
                           ca.postal_code, '|', ca.delivery_instructions, '|', ca.address_type, '|', 
                           ca.is_primary, '|', ca.latitude, '|', ca.longitude) SEPARATOR ';;') as addresses,
               GROUP_CONCAT(DISTINCT CONCAT(cp.id, '|', cp.phone_number, '|', cp.phone_type, '|', 
                           cp.is_primary, '|', cp.is_whatsapp) SEPARATOR ';;') as phones,
               GROUP_CONCAT(DISTINCT CONCAT(ccm.id, '|', ccm.phone_number, '|', ccm.cedula, '|', 
                           ccm.bank_code, '|', ccm.is_default) SEPARATOR ';;') as change_methods
        FROM customers c
        LEFT JOIN customer_addresses ca ON c.id = ca.customer_id
        LEFT JOIN customer_phones cp ON c.id = cp.customer_id
        LEFT JOIN customer_change_methods ccm ON c.id = ccm.customer_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    
    $stmt->execute([$_SESSION['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        unset($_SESSION['customer_id']);
        echo json_encode([
            'success' => true,
            'logged_in' => false
        ]);
        return;
    }
    
    // Parse addresses
    $addresses = [];
    if ($customer['addresses']) {
        foreach (explode(';;', $customer['addresses']) as $addressData) {
            $parts = explode('|', $addressData);
            if (count($parts) >= 9) {
                $addresses[] = [
                    'id' => $parts[0],
                    'street_address' => $parts[1],
                    'city' => $parts[2],
                    'postal_code' => $parts[3],
                    'delivery_instructions' => $parts[4],
                    'address_type' => $parts[5],
                    'is_primary' => (bool)$parts[6],
                    'latitude' => (float)$parts[7],
                    'longitude' => (float)$parts[8]
                ];
            }
        }
    }
    
    // Parse phones
    $phones = [];
    if ($customer['phones']) {
        foreach (explode(';;', $customer['phones']) as $phoneData) {
            $parts = explode('|', $phoneData);
            if (count($parts) >= 5) {
                $phones[] = [
                    'id' => $parts[0],
                    'phone_number' => $parts[1],
                    'phone_type' => $parts[2],
                    'is_primary' => (bool)$parts[3],
                    'is_whatsapp' => (bool)$parts[4]
                ];
            }
        }
    }
    
    // Parse change methods
    $changeMethods = [];
    if ($customer['change_methods']) {
        foreach (explode(';;', $customer['change_methods']) as $methodData) {
            $parts = explode('|', $methodData);
            if (count($parts) >= 5) {
                $changeMethods[] = [
                    'id' => $parts[0],
                    'phone_number' => $parts[1],
                    'cedula' => $parts[2],
                    'bank_code' => $parts[3],
                    'is_default' => (bool)$parts[4]
                ];
            }
        }
    }
    
    unset($customer['addresses'], $customer['phones'], $customer['change_methods']);
    $customer['addresses'] = $addresses;
    $customer['phones'] = $phones;
    $customer['change_methods'] = $changeMethods;
    
    echo json_encode([
        'success' => true,
        'logged_in' => true,
        'customer' => $customer
    ]);
}

function customerLogin() {
    $input = json_decode(file_get_contents('php://input'), true);
    $emailOrPhone = trim($input['email_or_phone'] ?? '');
    
    if (empty($emailOrPhone)) {
        throw new Exception('Email o teléfono requerido');
    }
    
    global $pdo;
    
    // Search by email or phone
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.* 
        FROM customers c
        LEFT JOIN customer_phones cp ON c.id = cp.customer_id
        WHERE c.email = ? OR cp.phone_number = ?
        LIMIT 1
    ");
    
    $stmt->execute([$emailOrPhone, $emailOrPhone]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        throw new Exception('Cliente no encontrado');
    }
    
    // Set session
    $_SESSION['customer_id'] = $customer['id'];
    
    // Get complete customer data
    checkCustomerSession();
}

function customerRegister() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $address = $input['address'] ?? [];
    
    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone)) {
        throw new Exception('Todos los campos son requeridos');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email no válido');
    }
    
    global $pdo;
    
    // Check if customer already exists
    $stmt = $pdo->prepare("
        SELECT c.id 
        FROM customers c
        LEFT JOIN customer_phones cp ON c.id = cp.customer_id
        WHERE c.email = ? OR cp.phone_number = ?
        LIMIT 1
    ");
    
    $stmt->execute([$email, $phone]);
    if ($stmt->fetch()) {
        throw new Exception('Ya existe un cliente con este email o teléfono');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Create customer
        $stmt = $pdo->prepare("
            INSERT INTO customers (first_name, last_name, email, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([$firstName, $lastName, $email]);
        $customerId = $pdo->lastInsertId();
        
        // Add phone
        $stmt = $pdo->prepare("
            INSERT INTO customer_phones (customer_id, phone_number, phone_type, is_primary, is_whatsapp, created_at)
            VALUES (?, ?, 'mobile', 1, 0, NOW())
        ");
        
        $stmt->execute([$customerId, $phone]);
        
        // Add address if provided
        if (!empty($address['street_address']) && !empty($address['city'])) {
            $stmt = $pdo->prepare("
                INSERT INTO customer_addresses (customer_id, street_address, city, postal_code, 
                                               delivery_instructions, address_type, is_primary, 
                                               latitude, longitude, created_at)
                VALUES (?, ?, ?, ?, ?, 'home', 1, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $customerId,
                $address['street_address'],
                $address['city'],
                $address['postal_code'] ?? '',
                $address['delivery_instructions'] ?? '',
                $address['latitude'] ?? 0,
                $address['longitude'] ?? 0
            ]);
        }
        
        $pdo->commit();
        
        // Set session
        $_SESSION['customer_id'] = $customerId;
        
        // Return customer data
        checkCustomerSession();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function customerLogout() {
    unset($_SESSION['customer_id']);
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Sesión cerrada correctamente'
    ]);
}

function addCustomerChangeMethod() {
    if (!isset($_SESSION['customer_id'])) {
        throw new Exception('Debe estar autenticado');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $phoneNumber = trim($input['phone_number'] ?? '');
    $cedula = trim($input['cedula'] ?? '');
    $bankCode = trim($input['bank_code'] ?? '');
    
    if (empty($phoneNumber) || empty($cedula) || empty($bankCode)) {
        throw new Exception('Todos los campos son requeridos');
    }
    
    global $pdo;
    
    // Check if method already exists
    $stmt = $pdo->prepare("
        SELECT id FROM customer_change_methods 
        WHERE customer_id = ? AND phone_number = ? AND cedula = ? AND bank_code = ?
    ");
    
    $stmt->execute([$_SESSION['customer_id'], $phoneNumber, $cedula, $bankCode]);
    
    if ($stmt->fetch()) {
        throw new Exception('Este método de pago ya existe');
    }
    
    // Add new change method
    $stmt = $pdo->prepare("
        INSERT INTO customer_change_methods (customer_id, phone_number, cedula, bank_code, is_default, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    
    $stmt->execute([$_SESSION['customer_id'], $phoneNumber, $cedula, $bankCode]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Método de pago agregado exitosamente'
    ]);
}
?>
