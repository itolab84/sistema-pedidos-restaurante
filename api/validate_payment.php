<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

// Función para obtener el token de pago desde api_integrations
function getPaymentToken($conn) {
    try {
        $sql = "SELECT api_key, endpoint_url FROM api_integrations WHERE service_name = 'Pagomovil API' AND status = 'active' LIMIT 1";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting payment token: " . $e->getMessage());
        return null;
    }
}

// Función para validar el pago con la API externa
function validatePaymentWithAPI($apiConfig, $paymentData) {
    $userAgent = "FlavorFinder/1.0";
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $apiConfig['endpoint_url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSL_VERIFYPEER => 2,
        CURLOPT_POSTFIELDS => json_encode($paymentData),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Token ' . $apiConfig['api_key'],
            'Content-Type: application/json'
        ),
        CURLOPT_USERAGENT => $userAgent
    ));

    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if (curl_errno($curl)) {
        $error_msg = curl_error($curl);
        curl_close($curl);
        return [
            'success' => false,
            'message' => "Error de conexión: " . $error_msg
        ];
    }

    curl_close($curl);
    
    $apiResponse = json_decode($response, true);
    
    if ($httpcode === 200 && $apiResponse) {
        return [
            'success' => true,
            'data' => $apiResponse
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Error en la validación del pago. Código: ' . $httpcode,
            'response' => $apiResponse
        ];
    }
}

// Procesar la solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Obtener datos del POST
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode([
                'success' => false,
                'message' => 'Datos de entrada inválidos'
            ]);
            exit;
        }
        
        // Validar campos requeridos
        $requiredFields = ['amount', 'reference'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                echo json_encode([
                    'success' => false,
                    'message' => "Campo requerido faltante: {$field}"
                ]);
                exit;
            }
        }
        
        $amount = floatval($input['amount']);
        $reference = trim($input['reference']);
        
        // Validar formato de referencia (6 dígitos)
        if (!preg_match('/^\d{6}$/', $reference)) {
            echo json_encode([
                'success' => false,
                'message' => 'La referencia debe tener exactamente 6 dígitos'
            ]);
            exit;
        }
        
        // Validar monto
        if ($amount <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'El monto debe ser mayor a cero'
            ]);
            exit;
        }
        
        // Obtener conexión a la base de datos
        $conn = getDBConnection();
        
        // Obtener configuración de la API
        $apiConfig = getPaymentToken($conn);
        
        if (!$apiConfig) {
            // Debug: Verificar qué hay en la tabla
            $debugSQL = "SELECT service_name, status FROM api_integrations";
            $debugResult = $conn->query($debugSQL);
            $debugData = [];
            if ($debugResult) {
                while ($row = $debugResult->fetch_assoc()) {
                    $debugData[] = $row;
                }
            }
            
            echo json_encode([
                'success' => false,
                'message' => 'Configuración de API de pagos no encontrada',
                'debug' => [
                    'looking_for' => 'Pagomovil API',
                    'status_required' => 'active',
                    'found_services' => $debugData
                ]
            ]);
            exit;
        }
        
        // Preparar datos para la API externa
        $paymentData = [
            "amount" => $amount,
            "reference" => $reference,
            "mobile" => $input['mobile'] ?? "",
            "sender" => $input['sender'] ?? "",
            "method" => $input['method'] ?? ""
        ];
        
        // Llamar a la API externa
        $validationResult = validatePaymentWithAPI($apiConfig, $paymentData);
        
        // Preparar información detallada de debug para mostrar en el formulario
        $debugInfo = [
            'timestamp' => date('Y-m-d H:i:s'),
            'api_config' => [
                'endpoint' => $apiConfig['endpoint_url'],
                'has_api_key' => !empty($apiConfig['api_key']),
                'api_key_preview' => !empty($apiConfig['api_key']) ? substr($apiConfig['api_key'], 0, 10) . '...' : 'No configurada'
            ],
            'request_data' => $paymentData,
            'validation_result' => $validationResult
        ];
        
        if ($validationResult['success']) {
            $apiResponse = $validationResult['data'];
            
            // Registrar la validación en logs (opcional)
            error_log("Payment validation successful for reference: {$reference}, amount: {$amount}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Pago validado exitosamente con API externa',
                'data' => [
                    'amount_usd' => $apiResponse['amount_usd'] ?? null,
                    'bank_origin_name' => $apiResponse['bank_origin_name'] ?? null,
                    'bank_destiny_name' => $apiResponse['bank_destiny_name'] ?? null,
                    'method_name' => $apiResponse['method_name'] ?? null,
                    'amount' => $apiResponse['amount'] ?? $amount,
                    'reference' => $reference,
                    'validated_at' => date('Y-m-d H:i:s')
                ],
                'debug' => $debugInfo,
                'api_logs' => [
                    'request_sent' => "POST {$apiConfig['endpoint_url']}",
                    'request_headers' => [
                        'Authorization: Token ' . substr($apiConfig['api_key'], 0, 10) . '...',
                        'Content-Type: application/json'
                    ],
                    'request_body' => json_encode($paymentData, JSON_PRETTY_PRINT),
                    'response_received' => 'HTTP 200 - Validación exitosa',
                    'response_body' => json_encode($apiResponse, JSON_PRETTY_PRINT)
                ]
            ]);
        } else {
            // Registrar el error en logs
            error_log("Payment validation failed for reference: {$reference}, error: " . $validationResult['message']);
            
            // Preparar logs detallados del error
            $errorLogs = [
                'request_sent' => "POST {$apiConfig['endpoint_url']}",
                'request_headers' => [
                    'Authorization: Token ' . substr($apiConfig['api_key'], 0, 10) . '...',
                    'Content-Type: application/json'
                ],
                'request_body' => json_encode($paymentData, JSON_PRETTY_PRINT),
                'response_received' => $validationResult['message'],
                'response_body' => isset($validationResult['response']) ? json_encode($validationResult['response'], JSON_PRETTY_PRINT) : 'Sin respuesta del servidor'
            ];
            
            // Si la API externa falla (error 500), permitir validación manual
            if (strpos($validationResult['message'], 'Código: 500') !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'API externa no disponible - Pago registrado para validación manual',
                    'manual_validation' => true,
                    'data' => [
                        'amount' => $amount,
                        'reference' => $reference,
                        'validated_at' => date('Y-m-d H:i:s'),
                        'validation_status' => 'pending_manual_review',
                        'api_error' => $validationResult['message']
                    ],
                    'debug' => $debugInfo,
                    'api_logs' => array_merge($errorLogs, [
                        'fallback_action' => 'Registrado para validación manual debido a error 500 del servidor externo'
                    ])
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error en validación: ' . $validationResult['message'],
                    'debug' => $debugInfo,
                    'api_logs' => $errorLogs
                ]);
            }
        }
        
    } catch (Exception $e) {
        error_log("Payment validation exception: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error interno del servidor'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}
?>
