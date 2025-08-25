<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * Función para consultar la tasa del dólar desde API externa
 *
 * @param string $fecha Fecha para la cual se quiere obtener la tasa (YYYY-MM-DD)
 * @param string $token Token de autorización
 * @return array Un array con la tasa del dólar o un mensaje de error
 */
function consultarTasaDolar($fecha, $token) {
    // Inicializar cURL
    $curl = curl_init();
    
    // Configurar opciones de cURL
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://core.gsoft.app/api/gsoft/services/dollar/?fecha=' . urlencode($fecha),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $token
        ),
        CURLOPT_SSL_VERIFYHOST => 0, // En producción, cambiar a 2
        CURLOPT_SSL_VERIFYPEER => 0  // En producción, cambiar a true
    ));

    // Ejecutar la solicitud
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    
    // Cerrar cURL
    curl_close($curl);

    // Manejar errores de cURL
    if ($error) {
        return [
            'success' => false,
            'error' => 'Error en la comunicación con el servidor: ' . $error,
            'httpCode' => 500
        ];
    }

    // Decodificar respuesta
    $data = json_decode($response, true);

    // Verificar si la decodificación fue exitosa
    if ($data === null) {
        return [
            'success' => false,
            'error' => 'Error al procesar la respuesta del servidor',
            'httpCode' => $httpCode,
            'rawResponse' => $response
        ];
    }

    // Verificar estado de la respuesta
    if ($httpCode === 200 && isset($data['monto'])) {
        return [
            'success' => true,
            'tasa' => floatval($data['monto']),
            'httpCode' => $httpCode,
            'fecha' => $fecha,
            'data' => $data
        ];
    } else {
        $mensajeError = isset($data['detail']) ? 'Error: ' . $data['detail'] : 'Error al obtener la tasa del dólar';
        return [
            'success' => false,
            'error' => $mensajeError,
            'httpCode' => $httpCode,
            'apiResponse' => $data
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Configuración
        $fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d'); // Default to today
        $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0b2tlbl90eXBlIjoiYWNjZXNzIiwiZXhwIjoxNzQ5NTk2MzQxLCJpYXQiOjE3NDk1OTU3NDEsImp0aSI6IjVmODEyNDUwYmZkMTRiMjZhYWM2MjJmOTAzNmRhODE2IiwidXNlcl9pZCI6ODZ9.wjbjtLf19a65b4cL73iejRD6WQe-HOey143HnYXu34s";
        
        // Consultar tasa del dólar
        $resultado = consultarTasaDolar($fecha, $token);
        
        if ($resultado['success']) {
            // Respuesta exitosa adaptada al formato del frontend
            echo json_encode([
                'success' => true,
                'rate' => $resultado['tasa'],
                'currency_from' => 'USD',
                'currency_to' => 'VES',
                'last_updated' => date('Y-m-d H:i:s'),
                'source' => 'gsoft_api',
                'fecha_consulta' => $fecha,
                'api_data' => $resultado['data'] // Datos completos de la API para debug
            ]);
        } else {
            // Error en la consulta, usar tasa de respaldo
            $tasaRespaldo = 36.50; // Tasa de respaldo
            
            echo json_encode([
                'success' => true, // Mantenemos success=true para no romper el frontend
                'rate' => $tasaRespaldo,
                'currency_from' => 'USD',
                'currency_to' => 'VES',
                'last_updated' => date('Y-m-d H:i:s'),
                'source' => 'fallback',
                'warning' => 'Usando tasa de respaldo debido a error en API externa',
                'api_error' => $resultado['error'],
                'fecha_consulta' => $fecha
            ]);
        }
        
    } catch (Exception $e) {
        // Error general, usar tasa de respaldo
        $tasaRespaldo = 36.50;
        
        echo json_encode([
            'success' => true, // Mantenemos success=true para no romper el frontend
            'rate' => $tasaRespaldo,
            'currency_from' => 'USD',
            'currency_to' => 'VES',
            'last_updated' => date('Y-m-d H:i:s'),
            'source' => 'fallback',
            'warning' => 'Usando tasa de respaldo debido a excepción',
            'exception' => $e->getMessage()
        ]);
    }
} else {
    // Método no permitido
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Use GET.',
        'rate' => 36.50, // Tasa de respaldo
        'currency_from' => 'USD',
        'currency_to' => 'VES'
    ]);
}
?>
