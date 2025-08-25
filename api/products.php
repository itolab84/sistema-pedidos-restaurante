<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$conn = getDBConnection();

// Get all products with related data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Check if requesting a specific product
        $productId = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if ($productId) {
            // Get single product with all related data
            $product = getProductWithDetails($conn, $productId);
            if ($product) {
                echo json_encode(['success' => true, 'product' => $product]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
            }
        } else {
            // Get all products with basic related data
            $products = getAllProductsWithDetails($conn);
            echo json_encode(['success' => true, 'products' => $products]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// Function to get all products with related data
function getAllProductsWithDetails($conn) {
    $sql = "
        SELECT 
            p.*,
            c.name as category_name,
            c.icon as category_icon,
            c.color as category_color
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active'
        ORDER BY c.sort_order, p.sort_order, p.name
    ";
    
    $result = $conn->query($sql);
    $products = [];
    
    while ($row = $result->fetch_assoc()) {
        $productId = $row['id'];
        
        // Get product images
        $row['images'] = getProductImages($conn, $productId);
        
        // Get available sizes and prices
        $row['sizes'] = getProductSizes($conn, $productId);
        
        // Get available additionals
        $row['additionals'] = getProductAdditionals($conn, $productId);
        
        // Set main image (first image or default)
        if (!empty($row['images'])) {
            $row['main_image'] = $row['images'][0]['image_path'];
        } else {
            $row['main_image'] = $row['image']; // Fallback to original image field
        }
        
        $products[] = $row;
    }
    
    return $products;
}

// Function to get single product with full details
function getProductWithDetails($conn, $productId) {
    $sql = "
        SELECT 
            p.*,
            c.name as category_name,
            c.description as category_description,
            c.icon as category_icon,
            c.color as category_color
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.status = 'active'
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Get product images
        $row['images'] = getProductImages($conn, $productId);
        
        // Get available sizes and prices
        $row['sizes'] = getProductSizes($conn, $productId);
        
        // Get available additionals
        $row['additionals'] = getProductAdditionals($conn, $productId);
        
        // Set main image
        if (!empty($row['images'])) {
            $row['main_image'] = $row['images'][0]['image_path'];
        } else {
            $row['main_image'] = $row['image'];
        }
        
        return $row;
    }
    
    return null;
}

// Function to get product images
function getProductImages($conn, $productId) {
    $sql = "
        SELECT image_path, original_name, created_at
        FROM product_images 
        WHERE product_id = ? 
        ORDER BY created_at ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
    
    return $images;
}

// Function to get product sizes and prices
function getProductSizes($conn, $productId) {
    $sql = "
        SELECT 
            ps.id,
            ps.name,
            ps.description,
            ps.multiplier,
            psp.price,
            ps.sort_order
        FROM product_sizes ps
        INNER JOIN product_size_prices psp ON ps.id = psp.size_id
        WHERE psp.product_id = ? AND ps.status = 'active'
        ORDER BY ps.sort_order ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sizes = [];
    while ($row = $result->fetch_assoc()) {
        $sizes[] = $row;
    }
    
    return $sizes;
}

// Function to get product additionals
function getProductAdditionals($conn, $productId) {
    $sql = "
        SELECT 
            a.id,
            a.name,
            a.description,
            a.price,
            ac.name as category_name,
            pa.is_default
        FROM additionals a
        INNER JOIN product_additionals pa ON a.id = pa.additional_id
        LEFT JOIN additional_categories ac ON a.category_id = ac.id
        WHERE pa.product_id = ? AND a.status = 'active'
        ORDER BY ac.sort_order, a.name
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $additionals = [];
    while ($row = $result->fetch_assoc()) {
        $additionals[] = $row;
    }
    
    return $additionals;
}

$conn->close();
?>
