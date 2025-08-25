<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get company information
        $company_result = $conn->query("SELECT razon_social, rif FROM company_settings LIMIT 1");
        $company_info = $company_result->num_rows > 0 ? $company_result->fetch_assoc() : [
            'razon_social' => 'FlavorFinder Restaurant',
            'rif' => 'J-12345678-9'
        ];
        
        // Check if the new table structure exists
        $table_check = $conn->query("SHOW TABLES LIKE 'payment_methods_company'");
        
        if ($table_check->num_rows === 0) {
            // Fallback to old structure if new table doesn't exist
            $query = "
                SELECT 
                    pm.id,
                    pm.name,
                    pm.status,
                    NULL as bank_name,
                    NULL as bank_code,
                    NULL as account_number,
                    NULL as pagomovil_number,
                    NULL as account_holder_name,
                    NULL as account_holder_id,
                    NULL as notes
                FROM payment_methods pm
                WHERE pm.status = 'active'
                ORDER BY pm.name
            ";
        } else {
            // Use new structure with company configurations
            $query = "
                SELECT DISTINCT
                    pm.id,
                    pm.name,
                    pm.status,
                    GROUP_CONCAT(DISTINCT b.name ORDER BY b.name SEPARATOR ', ') as bank_names,
                    GROUP_CONCAT(DISTINCT b.code ORDER BY b.name SEPARATOR ', ') as bank_codes,
                    GROUP_CONCAT(DISTINCT pmc.account_number ORDER BY b.name SEPARATOR ', ') as account_numbers,
                    GROUP_CONCAT(DISTINCT pmc.pagomovil_number ORDER BY b.name SEPARATOR ', ') as pagomovil_numbers,
                    GROUP_CONCAT(DISTINCT pmc.account_holder_name ORDER BY b.name SEPARATOR ', ') as account_holder_names,
                    GROUP_CONCAT(DISTINCT pmc.account_holder_id ORDER BY b.name SEPARATOR ', ') as account_holder_ids,
                    COUNT(pmc.id) as configurations_count,
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'config_id', pmc.id,
                            'bank_id', pmc.bank_id,
                            'bank_name', b.name,
                            'bank_code', b.code,
                            'account_number', pmc.account_number,
                            'pagomovil_number', pmc.pagomovil_number,
                            'account_holder_name', pmc.account_holder_name,
                            'account_holder_id', pmc.account_holder_id,
                            'notes', pmc.notes
                        )
                    ) as configurations
                FROM payment_methods pm
                INNER JOIN payment_methods_company pmc ON pm.id = pmc.payment_method_id
                LEFT JOIN banks b ON pmc.bank_id = b.id
                WHERE pm.status = 'active' AND pmc.status = 'active'
                GROUP BY pm.id, pm.name, pm.status
                ORDER BY pm.name
            ";
        }
        
        $result = $conn->query($query);
        $payment_methods = [];
        
        while ($row = $result->fetch_assoc()) {
            // Parse configurations JSON if it exists
            if (isset($row['configurations'])) {
                $row['configurations'] = json_decode($row['configurations'], true);
                // Filter out null configurations
                $row['configurations'] = array_filter($row['configurations'], function($config) {
                    return $config['config_id'] !== null;
                });
            } else {
                $row['configurations'] = [];
            }
            
            $payment_methods[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'company' => [
                'name' => $company_info['razon_social'],
                'rif' => $company_info['rif']
            ],
            'payment_methods' => $payment_methods,
            'has_new_structure' => $table_check->num_rows > 0
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener métodos de pago: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}

$conn->close();
?>
