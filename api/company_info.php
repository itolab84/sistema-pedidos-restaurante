<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Include database configuration
    require_once '../config/database.php';
    
    // Create database connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get company information
    $stmt = $pdo->prepare("SELECT * FROM company_settings WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($company) {
        echo json_encode([
            'success' => true,
            'company' => [
                'name' => $company['company_name'] ?? 'FlavorFinder',
                'rif' => $company['rif'] ?? 'J-12345678-9',
                'phone' => $company['phone'] ?? '0414-1234567',
                'email' => $company['email'] ?? 'info@flavorfinder.com',
                'address' => $company['address'] ?? 'Caracas, Venezuela',
                'bank_name' => $company['bank_name'] ?? 'Banco Provincial',
                'bank_code' => $company['bank_code'] ?? '0108',
                'account_number' => $company['account_number'] ?? '0108-0000-00-0000000000',
                'account_holder_name' => $company['account_holder_name'] ?? 'FlavorFinder C.A.',
                'account_holder_id' => $company['account_holder_id'] ?? 'J-12345678-9'
            ]
        ]);
    } else {
        // Return default company information if no record exists
        echo json_encode([
            'success' => true,
            'company' => [
                'name' => 'FlavorFinder',
                'rif' => 'J-12345678-9',
                'phone' => '0414-1234567',
                'email' => 'info@flavorfinder.com',
                'address' => 'Caracas, Venezuela',
                'bank_name' => 'Banco Provincial',
                'bank_code' => '0108',
                'account_number' => '0108-0000-00-0000000000',
                'account_holder_name' => 'FlavorFinder C.A.',
                'account_holder_id' => 'J-12345678-9'
            ]
        ]);
    }
    
} catch (PDOException $e) {
    // Return default information on database error
    echo json_encode([
        'success' => true,
        'company' => [
            'name' => 'FlavorFinder',
            'rif' => 'J-12345678-9',
            'phone' => '0414-1234567',
            'email' => 'info@flavorfinder.com',
            'address' => 'Caracas, Venezuela',
            'bank_name' => 'Banco Provincial',
            'bank_code' => '0108',
            'account_number' => '0108-0000-00-0000000000',
            'account_holder_name' => 'FlavorFinder C.A.',
            'account_holder_id' => 'J-12345678-9'
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener información de la empresa',
        'error' => $e->getMessage()
    ]);
}
?>
