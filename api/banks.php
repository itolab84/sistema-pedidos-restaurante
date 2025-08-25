<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $sql = "
            SELECT id, achronym, code, name 
            FROM banks 
            WHERE show = 1 AND work = 1 
            ORDER BY name ASC
        ";
        
        $result = $conn->query($sql);
        $banks = [];
        
        while ($row = $result->fetch_assoc()) {
            $banks[] = [
                'id' => (int)$row['id'],
                'acronym' => $row['achronym'],
                'code' => $row['code'],
                'name' => $row['name']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'banks' => $banks,
            'total' => count($banks)
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener bancos: ' . $e->getMessage(),
            'banks' => []
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
