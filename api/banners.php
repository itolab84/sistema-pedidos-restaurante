<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $conn = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get banners for display
        $position = $_GET['position'] ?? '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        // Build query
        $whereConditions = ["b.status = 'active'"];
        $params = [];
        
        // Check date constraints
        $now = date('Y-m-d H:i:s');
        $whereConditions[] = "(b.start_date IS NULL OR b.start_date <= ?)";
        $whereConditions[] = "(b.end_date IS NULL OR b.end_date >= ?)";
        $params[] = $now;
        $params[] = $now;
        
        if ($position) {
            $whereConditions[] = "b.position = ?";
            $params[] = $position;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $sql = "
            SELECT 
                b.id,
                b.title,
                b.description,
                b.image_url,
                b.link_type,
                b.product_id,
                b.external_url,
                b.position,
                b.sort_order,
                p.name as product_name,
                p.price as product_price
            FROM banners b
            LEFT JOIN products p ON b.product_id = p.id
            {$whereClause}
            ORDER BY b.position, b.sort_order, b.created_at DESC
            LIMIT ?
        ";
        
        $params[] = $limit;
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('s', count($params) - 1) . 'i', ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $banners = [];
        while ($row = $result->fetch_assoc()) {
            // Increment view count
            $updateStmt = $conn->prepare("UPDATE banners SET view_count = view_count + 1 WHERE id = ?");
            $updateStmt->bind_param('i', $row['id']);
            $updateStmt->execute();
            
            $banners[] = [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'image_url' => $row['image_url'],
                'link_type' => $row['link_type'],
                'product_id' => $row['product_id'] ? (int)$row['product_id'] : null,
                'external_url' => $row['external_url'],
                'position' => $row['position'],
                'sort_order' => (int)$row['sort_order'],
                'product_name' => $row['product_name'],
                'product_price' => $row['product_price'] ? (float)$row['product_price'] : null
            ];
        }
        
        echo json_encode([
            'success' => true,
            'banners' => $banners,
            'count' => count($banners)
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle banner click tracking
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['banner_id'])) {
            throw new Exception('Banner ID es requerido');
        }
        
        $bannerId = (int)$input['banner_id'];
        
        // Increment click count
        $stmt = $conn->prepare("UPDATE banners SET click_count = click_count + 1 WHERE id = ? AND status = 'active'");
        $stmt->bind_param('i', $bannerId);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al registrar el clic');
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Banner no encontrado o inactivo');
        }
        
        // Get banner details for redirect
        $stmt = $conn->prepare("
            SELECT link_type, product_id, external_url 
            FROM banners 
            WHERE id = ? AND status = 'active'
        ");
        $stmt->bind_param('i', $bannerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $banner = $result->fetch_assoc();
        
        if (!$banner) {
            throw new Exception('Banner no encontrado');
        }
        
        $redirectUrl = null;
        
        if ($banner['link_type'] === 'product' && $banner['product_id']) {
            // Generate product URL (adjust based on your URL structure)
            $redirectUrl = '/reserve/product.php?id=' . $banner['product_id'];
        } elseif ($banner['link_type'] === 'url' && $banner['external_url']) {
            $redirectUrl = $banner['external_url'];
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Clic registrado correctamente',
            'redirect_url' => $redirectUrl
        ]);
        
    } else {
        throw new Exception('Método no permitido');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
