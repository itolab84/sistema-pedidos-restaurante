<?php
// Prevent any HTML output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to prevent HTML output

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed'
        ]);
        exit;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['order_ids']) || !is_array($input['order_ids']) || empty($input['order_ids'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or missing order_ids'
        ]);
        exit;
    }
    
    $orderIds = $input['order_ids'];
    
    // Use the constants defined in database.php
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Mark orders as notified
    $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
    $stmt = $db->prepare("UPDATE orders SET notification = 1 WHERE id IN ($placeholders)");
    $stmt->execute($orderIds);
    
    $affectedRows = $stmt->rowCount();
    
    // Response
    echo json_encode([
        'success' => true,
        'message' => "Marked $affectedRows orders as notified",
        'affected_rows' => $affectedRows
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error marking orders as notified: ' . $e->getMessage()
    ]);
}
?>
