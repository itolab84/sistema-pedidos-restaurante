<?php
// Script para crear imágenes de prueba para los banners

function createBannerImage($filename, $width, $height, $text, $bgColor = [255, 140, 0], $textColor = [255, 255, 255]) {
    // Crear imagen
    $image = imagecreate($width, $height);
    
    // Definir colores
    $backgroundColor = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
    $textColorResource = imagecolorallocate($image, $textColor[0], $textColor[1], $textColor[2]);
    $borderColor = imagecolorallocate($image, 200, 200, 200);
    
    // Llenar fondo
    imagefill($image, 0, 0, $backgroundColor);
    
    // Agregar borde
    imagerectangle($image, 0, 0, $width-1, $height-1, $borderColor);
    
    // Agregar texto
    $fontSize = 5;
    $textWidth = imagefontwidth($fontSize) * strlen($text);
    $textHeight = imagefontheight($fontSize);
    
    $x = ($width - $textWidth) / 2;
    $y = ($height - $textHeight) / 2;
    
    imagestring($image, $fontSize, $x, $y, $text, $textColorResource);
    
    // Guardar imagen
    $fullPath = "assets/images/banners/" . $filename;
    
    // Determinar formato por extensión
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($image, $fullPath, 90);
            break;
        case 'png':
            imagepng($image, $fullPath);
            break;
        case 'webp':
            imagewebp($image, $fullPath, 90);
            break;
        default:
            imagejpeg($image, $fullPath, 90);
    }
    
    // Limpiar memoria
    imagedestroy($image);
    
    return file_exists($fullPath);
}

echo "=== CREANDO IMÁGENES DE BANNERS ===\n";

// Crear las imágenes necesarias
$banners = [
    [
        'filename' => 'banner1.jpg',
        'width' => 1200,
        'height' => 400,
        'text' => 'BANNER PRINCIPAL - OFERTAS ESPECIALES',
        'bgColor' => [231, 76, 60], // Rojo
        'textColor' => [255, 255, 255]
    ],
    [
        'filename' => 'banner2.jpg',
        'width' => 400,
        'height' => 300,
        'text' => 'OFERTA LATERAL',
        'bgColor' => [52, 152, 219], // Azul
        'textColor' => [255, 255, 255]
    ],
    [
        'filename' => 'banner_1756310227_68af2ad391985.webp',
        'width' => 1200,
        'height' => 400,
        'text' => 'DELICIOSO POLLO - VER PRODUCTO',
        'bgColor' => [155, 89, 182], // Púrpura
        'textColor' => [255, 255, 255]
    ]
];

foreach ($banners as $banner) {
    echo "Creando: {$banner['filename']} ({$banner['width']}x{$banner['height']})... ";
    
    $success = createBannerImage(
        $banner['filename'],
        $banner['width'],
        $banner['height'],
        $banner['text'],
        $banner['bgColor'],
        $banner['textColor']
    );
    
    if ($success) {
        echo "✅ CREADO\n";
    } else {
        echo "❌ ERROR\n";
    }
}

echo "\n=== VERIFICANDO ARCHIVOS CREADOS ===\n";

// Verificar que se crearon correctamente
require_once 'config/database.php';

try {
    $conn = getDBConnection();
    $result = $conn->query('SELECT id, title, image_url, position, status FROM banners WHERE status = "active"');
    
    while ($row = $result->fetch_assoc()) {
        $imagePath = $_SERVER['DOCUMENT_ROOT'] . $row['image_url'];
        $exists = file_exists($imagePath) ? '✅' : '❌';
        $size = file_exists($imagePath) ? filesize($imagePath) . ' bytes' : 'N/A';
        
        echo "Banner: {$row['title']} | Archivo: $exists | Tamaño: $size\n";
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "Error verificando: " . $e->getMessage() . "\n";
}

echo "\n¡Imágenes de banners creadas! Ahora los banners deberían mostrarse correctamente.\n";
?>
