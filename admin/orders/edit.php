<?php
require_once '../config/auth.php';
require_once '../includes/debug_helper.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Check if debug mode is enabled
$debugEnabled = isDebugEnabled();

// Function to validate Pago Móvil using the same API as frontend
function validatePagomovil($amount, $reference, $mobile = '', $sender = '') {
    return validatePayment($amount, $reference, 'pagomovil', $mobile, $sender);
}

// Function to validate Transferencia using the same API as frontend
function validateTransferencia($amount, $reference, $mobile = '', $sender = '') {
    return validatePayment($amount, $reference, 'transferencia', $mobile, $sender);
}

// Generic function to validate payments using the API
function validatePayment($amount, $reference, $method, $mobile = '', $sender = '') {
    try {
        // Prepare data for API call
        $paymentData = [
            'amount' => $amount,
            'reference' => $reference,
            'mobile' => $mobile,
            'sender' => $sender,
            'method' => $method
        ];
        
        // Call the validation API using a simpler approach
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        // Construct the API URL more directly
        $baseUrl = $protocol . '://' . $host;
        
        // Get the document root relative path
        $scriptPath = $_SERVER['SCRIPT_NAME']; // /admin/orders/edit.php
        $pathParts = explode('/', trim($scriptPath, '/'));
        
        // Remove the last 3 parts (admin, orders, edit.php) to get to root
        $rootParts = array_slice($pathParts, 0, -3);
        $rootPath = '/' . implode('/', $rootParts);
        if ($rootPath === '/') $rootPath = '';
        
        $apiUrl = $baseUrl . $rootPath . '/api/validate_payment.php';
        
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($paymentData),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ));
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($curl);
        
        curl_close($curl);
        
        // Handle different response scenarios
        if ($curlError) {
            // API not available - allow manual registration
            return [
                'success' => true,
                'manual_validation' => true,
                'message' => 'API de validación no disponible. Se permite registro manual.',
                'data' => [
                    'validation_type' => 'manual',
                    'reason' => 'API connection error: ' . curl_strerror($curlError),
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
        }
        
        if ($httpCode === 200) {
            // API responded successfully - register payment automatically
            $apiResponse = json_decode($response, true);
            if ($apiResponse && isset($apiResponse['success'])) {
                if ($apiResponse['success']) {
                    return [
                        'success' => true,
                        'manual_validation' => false,
                        'message' => 'Pago Móvil validado correctamente por la API.',
                        'data' => $apiResponse['data'] ?? []
                    ];
                } else {
                    // API returned success=false, but with 200 status
                    return [
                        'success' => false,
                        'message' => $apiResponse['message'] ?? 'Error en la validación del pago'
                    ];
                }
            } else {
                // Invalid API response format
                return [
                    'success' => false,
                    'message' => 'Respuesta inválida de la API de validación'
                ];
            }
        } elseif ($httpCode === 400) {
            // API returned error 400 - show the error message
            $apiResponse = json_decode($response, true);
            $errorMessage = 'Error de validación';
            
            if ($apiResponse && isset($apiResponse['message'])) {
                $errorMessage = $apiResponse['message'];
            } elseif ($apiResponse && isset($apiResponse['error'])) {
                $errorMessage = $apiResponse['error'];
            }
            
            return [
                'success' => false,
                'message' => $errorMessage
            ];
        } else {
            // Other HTTP errors - allow manual registration
            return [
                'success' => true,
                'manual_validation' => true,
                'message' => 'Error en la API de validación (HTTP ' . $httpCode . '). Se permite registro manual.',
                'data' => [
                    'validation_type' => 'manual',
                    'reason' => 'HTTP error: ' . $httpCode,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
        }
        
    } catch (Exception $e) {
        // Exception occurred - allow manual registration
        return [
            'success' => true,
            'manual_validation' => true,
            'message' => 'Error interno en la validación. Se permite registro manual.',
            'data' => [
                'validation_type' => 'manual',
                'reason' => 'Exception: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
    }
}

// Get order ID
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    header('Location: index.php?error=invalid_id');
    exit;
}

// Get order details
$order = $db->fetchOne(
    "SELECT * FROM orders WHERE id = ?",
    [$orderId]
);

if (!$order) {
    header('Location: index.php?error=order_not_found');
    exit;
}

// Get order items
$orderItems = $db->fetchAll(
    "SELECT oi.*, p.name as product_name
     FROM order_items oi
     LEFT JOIN products p ON oi.product_id = p.id
     WHERE oi.order_id = ?
     ORDER BY oi.id",
    [$orderId]
);

// Calculate payment balance
$totalPaid = $db->fetchOne(
    "SELECT COALESCE(SUM(COALESCE(p.amount, op.amount)), 0) as total_paid
     FROM order_payments op
     LEFT JOIN payments p ON op.payment_id = p.id
     WHERE op.order_id = ? AND COALESCE(p.payment_status, op.status) IN ('completed', 'paid')",
    [$orderId]
)['total_paid'] ?? 0;

// Calculate change amount (saldo a favor)
$changeAmount = $db->fetchOne(
    "SELECT COALESCE(SUM(change_amount), 0) as total_change
     FROM change_history
     WHERE order_id = ? AND payment_status = 'pending'",
    [$orderId]
)['total_change'] ?? 0;

// Calculate current order total
$currentTotal = $order['total_amount_usd'] ?? $order['total_amount'];

// Calculate available balance (paid + change amount)
$availableBalance = $totalPaid + $changeAmount;

// Calculate difference (positive = amount due, negative = credit balance)
$balanceDifference = $currentTotal - $availableBalance;

// Handle messages from URL parameters (POST-Redirect-GET pattern)
$message = '';
$messageType = 'success';

// Check for success/error messages from redirects
if (isset($_GET['success'])) {
    $messageType = 'success';
    switch ($_GET['success']) {
        case 'order_updated':
            $message = 'Orden actualizada correctamente';
            break;
        case 'item_updated':
            $message = 'Producto actualizado correctamente';
            break;
        case 'item_removed':
            $message = 'Producto eliminado correctamente';
            break;
        case 'product_added':
            $message = 'Producto agregado correctamente';
            break;
        case 'payment_processed':
            $message = 'Pago procesado correctamente';
            break;
        case 'pagomovil_validated':
            $message = 'Pago Móvil validado y procesado correctamente';
            break;
        case 'transferencia_validated':
            $message = 'Transferencia validada y procesada correctamente';
            break;
        case 'payment_pending_validation':
            $message = 'Pago registrado. Requiere validación manual para procesar';
            $messageType = 'warning';
            break;
        default:
            $message = 'Operación completada correctamente';
    }
} elseif (isset($_GET['error'])) {
    $messageType = 'danger';
    $message = urldecode($_GET['error']);
}

if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_order':
            try {
                $db->query("START TRANSACTION");
                
                // Update order basic info
                $db->update('orders', [
                    'customer_name' => $_POST['customer_name'],
                    'customer_email' => $_POST['customer_email'],
                    'customer_phone' => $_POST['customer_phone'],
                    'payment_method' => $_POST['payment_method'],
                    'notes' => $_POST['notes'] ?? ''
                ], 'id = ?', [$orderId]);
                
                // Add status history if status changed
                if ($_POST['status'] !== $order['status']) {
                    $db->update('orders', ['status' => $_POST['status']], 'id = ?', [$orderId]);
                    
                    $db->insert('order_status_history', [
                        'order_id' => $orderId,
                        'status' => $_POST['status'],
                        'previous_status' => $order['status'],
                        'changed_by' => $user['username'],
                        'notes' => 'Actualizado desde edición de orden'
                    ]);
                }
                
                $db->query("COMMIT");
                
                // POST-Redirect-GET: Redirect to prevent form resubmission
                header('Location: edit.php?id=' . $orderId . '&success=order_updated');
                exit;
                
            } catch (Exception $e) {
                $db->query("ROLLBACK");
                // POST-Redirect-GET: Redirect with error message
                header('Location: edit.php?id=' . $orderId . '&error=' . urlencode($e->getMessage()));
                exit;
            }
            break;
            
        case 'update_item':
            $itemId = (int)$_POST['item_id'];
            $newQuantity = (int)$_POST['quantity'];
            $newPrice = (float)$_POST['price'];
            
            if ($newQuantity > 0 && $newPrice >= 0) {
                try {
                    $db->update('order_items', [
                        'quantity' => $newQuantity,
                        'price' => $newPrice
                    ], 'id = ? AND order_id = ?', [$itemId, $orderId]);
                    
                    // Recalculate order total
                    $newTotal = $db->fetchOne(
                        "SELECT SUM(quantity * price) as total FROM order_items WHERE order_id = ?",
                        [$orderId]
                    )['total'];
                    
                    $db->update('orders', [
                        'total_amount' => $newTotal,
                        'total_amount_usd' => $newTotal
                    ], 'id = ?', [$orderId]);
                    
                    // POST-Redirect-GET: Redirect to prevent form resubmission
                    header('Location: edit.php?id=' . $orderId . '&success=item_updated');
                    exit;
                    
                } catch (Exception $e) {
                    // POST-Redirect-GET: Redirect with error message
                    header('Location: edit.php?id=' . $orderId . '&error=' . urlencode($e->getMessage()));
                    exit;
                }
            } else {
                // POST-Redirect-GET: Redirect with error message
                header('Location: edit.php?id=' . $orderId . '&error=' . urlencode('Cantidad y precio deben ser válidos'));
                exit;
            }
            break;
            
        case 'remove_item':
            $itemId = (int)$_POST['item_id'];
            
            try {
                $db->delete('order_items', 'id = ? AND order_id = ?', [$itemId, $orderId]);
                
                // Recalculate order total
                $newTotal = $db->fetchOne(
                    "SELECT COALESCE(SUM(quantity * price), 0) as total FROM order_items WHERE order_id = ?",
                    [$orderId]
                )['total'];
                
                $db->update('orders', [
                    'total_amount' => $newTotal,
                    'total_amount_usd' => $newTotal
                ], 'id = ?', [$orderId]);
                
                // POST-Redirect-GET: Redirect to prevent form resubmission
                header('Location: edit.php?id=' . $orderId . '&success=item_removed');
                exit;
                
            } catch (Exception $e) {
                // POST-Redirect-GET: Redirect with error message
                header('Location: edit.php?id=' . $orderId . '&error=' . urlencode($e->getMessage()));
                exit;
            }
            break;
            
        case 'add_product':
            $productId = (int)$_POST['product_id'];
            $quantity = (int)$_POST['quantity'];
            $price = (float)$_POST['price'];
            $notes = $_POST['notes'] ?? '';
            
            if ($productId > 0 && $quantity > 0 && $price >= 0) {
                try {
                    // Get product name for verification
                    $product = $db->fetchOne("SELECT name FROM products WHERE id = ?", [$productId]);
                    
                    if ($product) {
                        $db->insert('order_items', [
                            'order_id' => $orderId,
                            'product_id' => $productId,
                            'quantity' => $quantity,
                            'price' => $price,
                            'notes' => $notes
                        ]);
                        
                        // Recalculate order total
                        $newTotal = $db->fetchOne(
                            "SELECT SUM(quantity * price) as total FROM order_items WHERE order_id = ?",
                            [$orderId]
                        )['total'];
                        
                        $db->update('orders', [
                            'total_amount' => $newTotal,
                            'total_amount_usd' => $newTotal
                        ], 'id = ?', [$orderId]);
                        
                        // POST-Redirect-GET: Redirect to prevent form resubmission
                        header('Location: edit.php?id=' . $orderId . '&success=product_added');
                        exit;
                    } else {
                        // POST-Redirect-GET: Redirect with error message
                        header('Location: edit.php?id=' . $orderId . '&error=' . urlencode('Producto no encontrado'));
                        exit;
                    }
                    
                } catch (Exception $e) {
                    // POST-Redirect-GET: Redirect with error message
                    header('Location: edit.php?id=' . $orderId . '&error=' . urlencode($e->getMessage()));
                    exit;
                }
            } else {
                // POST-Redirect-GET: Redirect with error message
                header('Location: edit.php?id=' . $orderId . '&error=' . urlencode('Datos del producto no válidos'));
                exit;
            }
            break;
            
        case 'process_payment':
            $paymentMethod = $_POST['payment_method'];
            $amount = (float)$_POST['amount'];
            $reference = $_POST['reference'] ?? '';
            $mobile = $_POST['mobile'] ?? '';
            $sender = $_POST['sender'] ?? '';
            
            if ($amount > 0 && $balanceDifference > 0) {
                try {
                    $db->query("START TRANSACTION");
                    
                    $paymentStatus = 'completed';
                    $orderPaymentStatus = 'paid';
                    $validationData = null;
                    $successMessage = 'payment_processed';
                    $amountUSD = $amount; // Default to USD amount
                    $exchangeRate = 1; // Default exchange rate
                    
                    // Get exchange rate for currency conversion
                    $methodsRequiringConversion = ['pagomovil', 'debito_inmediato', 'efectivo_bolivares', 'tarjeta', 'transferencia'];
                    if (in_array($paymentMethod, $methodsRequiringConversion)) {
                        // Get exchange rate from API
                        $exchangeResponse = file_get_contents('http://' . $_SERVER['HTTP_HOST'] . '/reserve/api/exchange_rate.php');
                        $exchangeData = json_decode($exchangeResponse, true);
                        
                        if ($exchangeData && $exchangeData['success'] && $exchangeData['rate']) {
                            $exchangeRate = $exchangeData['rate'];
                            $amountUSD = $amount / $exchangeRate; // Convert VES to USD
                        } else {
                            // Fallback exchange rate
                            $exchangeRate = 36.50;
                            $amountUSD = $amount / $exchangeRate;
                        }
                    }
                    
                    // If it's pagomovil, validate with API first
                    if ($paymentMethod === 'pagomovil') {
                        if (empty($reference) || !preg_match('/^\d{6}$/', $reference)) {
                            throw new Exception('La referencia de Pago Móvil debe tener exactamente 6 dígitos');
                        }
                        
                        // Call validation API
                        $validationResult = validatePagomovil($amountUSD, $reference, $mobile, $sender);
                        
                        if (!$validationResult['success']) {
                            throw new Exception('Error en validación de Pago Móvil: ' . $validationResult['message']);
                        }
                        
                        // Prepare validation data for order_payments table
                        $validationDataArray = [
                            'bank_origin' => $validationResult['data']['bank_origin_name'] ?? 'N/A',
                            'bank_destination' => $validationResult['data']['bank_destiny_name'] ?? 'N/A', 
                            'phone' => $validationResult['data']['mobile'] ?? ($mobile ?: 'N/A'),
                            'validate' => 1,
                            'amount' => $amount,
                            'amount_usd' => $amountUSD,
                            'exchange_rate' => $exchangeRate,
                            'reference' => $reference,
                            'validated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        // Check if it's manual validation due to API failure
                        if (isset($validationResult['manual_validation']) && $validationResult['manual_validation']) {
                            $paymentStatus = 'pending';
                            $orderPaymentStatus = 'pending_validation';
                            $validationDataArray['validate'] = 0;
                            $validationDataArray['validation_status'] = 'pending_manual_review';
                            $validationDataArray['api_error'] = $validationResult['data']['reason'] ?? 'Manual validation required';
                            $successMessage = 'payment_pending_validation';
                        } else {
                            $paymentStatus = 'completed';
                            $orderPaymentStatus = 'paid';
                            $validationDataArray['validate'] = 1;
                            $validationDataArray['validation_status'] = 'api_validated';
                            $successMessage = 'pagomovil_validated';
                        }
                        
                        $validationData = json_encode($validationDataArray);
                        
                    } else if ($paymentMethod === 'transferencia') {
                        if (empty($reference)) {
                            throw new Exception('La referencia de transferencia es requerida');
                        }
                        
                        // Call validation API for transferencia
                        $validationResult = validateTransferencia($amountUSD, $reference, $mobile, $sender);
                        
                        if (!$validationResult['success']) {
                            throw new Exception('Error en validación de Transferencia: ' . $validationResult['message']);
                        }
                        
                        // Prepare validation data for order_payments table
                        $validationDataArray = [
                            'bank_origin' => $validationResult['data']['bank_origin_name'] ?? 'N/A',
                            'bank_destination' => $validationResult['data']['bank_destiny_name'] ?? 'N/A', 
                            'phone' => $validationResult['data']['mobile'] ?? ($mobile ?: 'N/A'),
                            'validate' => 1,
                            'amount' => $amount,
                            'amount_usd' => $amountUSD,
                            'exchange_rate' => $exchangeRate,
                            'reference' => $reference,
                            'validated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        // Check if it's manual validation due to API failure
                        if (isset($validationResult['manual_validation']) && $validationResult['manual_validation']) {
                            $paymentStatus = 'pending';
                            $orderPaymentStatus = 'pending_validation';
                            $validationDataArray['validate'] = 0;
                            $validationDataArray['validation_status'] = 'pending_manual_review';
                            $validationDataArray['api_error'] = $validationResult['data']['reason'] ?? 'Manual validation required';
                            $successMessage = 'payment_pending_validation';
                        } else {
                            $paymentStatus = 'completed';
                            $orderPaymentStatus = 'paid';
                            $validationDataArray['validate'] = 1;
                            $validationDataArray['validation_status'] = 'api_validated';
                            $successMessage = 'transferencia_validated';
                        }
                        
                        $validationData = json_encode($validationDataArray);
                        
                    } else if ($paymentMethod === 'debito_inmediato') {
                        $paymentStatus = 'pending';
                        $orderPaymentStatus = 'pending_validation';
                        $successMessage = 'payment_pending_validation';
                        
                        // Prepare validation data for debito_inmediato
                        $validationDataArray = [
                            'bank_origin' => 'N/A',
                            'bank_destination' => 'N/A',
                            'phone' => 'N/A',
                            'validate' => 0,
                            'amount' => $amount,
                            'amount_usd' => $amountUSD,
                            'exchange_rate' => $exchangeRate,
                            'reference' => $reference,
                            'validation_status' => 'pending_manual_review',
                            'validated_at' => date('Y-m-d H:i:s')
                        ];
                        $validationData = json_encode($validationDataArray);
                    }
                    
                    // Create payment record
                    $paymentId = $db->insert('payments', [
                        'order_id' => $orderId,
                        'payment_method' => $paymentMethod,
                        'payment_status' => $paymentStatus,
                        'amount' => $amountUSD, // Amount in USD (divided by exchange rate)
                        'amount_order' => $amount, // Amount paid in original currency
                        'transaction_id' => $reference,
                        'payment_date' => date('Y-m-d H:i:s'),
                        'processed_by' => $user['id'] ?? null,
                        'notes' => 'pago registrado'
                    ]);
                    
                    // Create order_payment record
                    $db->insert('order_payments', [
                        'payment_id' => $paymentId,
                        'order_id' => $orderId,
                        'payment_method' => $paymentMethod,
                        'amount' => $amountUSD, // Use USD amount for order balance calculations
                        'reference' => $reference,
                        'status' => $orderPaymentStatus,
                        'validation_data' => $validationData
                    ]);
                    
                    // Update order status if payment is completed
                    if ($paymentStatus === 'completed') {
                        $newTotalPaid = $totalPaid + $amount;
                        if ($newTotalPaid >= $currentTotal) {
                            $db->update('orders', ['status' => 'confirmed'], 'id = ?', [$orderId]);
                            
                            $db->insert('order_status_history', [
                                'order_id' => $orderId,
                                'status' => 'confirmed',
                                'previous_status' => $order['status'],
                                'changed_by' => $user['username'],
                                'notes' => 'Pago completado - orden confirmada automáticamente'
                            ]);
                        }
                    }
                    
                    $db->query("COMMIT");
                    
                    // POST-Redirect-GET: Redirect to prevent form resubmission
                    header('Location: edit.php?id=' . $orderId . '&success=' . $successMessage);
                    exit;
                    
                } catch (Exception $e) {
                    $db->query("ROLLBACK");
                    // POST-Redirect-GET: Redirect with error message
                    header('Location: edit.php?id=' . $orderId . '&error=' . urlencode($e->getMessage()));
                    exit;
                }
            } else {
                // POST-Redirect-GET: Redirect with error message
                header('Location: edit.php?id=' . $orderId . '&error=' . urlencode('Monto de pago no válido'));
                exit;
            }
            break;
    }
}

// Status labels
$statusLabels = [
    'pending' => 'Pendiente',
    'confirmed' => 'Confirmado',
    'preparing' => 'Preparando',
    'ready' => 'Listo',
    'out_for_delivery' => 'En Camino',
    'delivered' => 'Entregado',
    'cancelled' => 'Cancelado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Orden #<?= $order['id'] ?> - Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-edit me-2 text-primary"></i>
                            Editar Orden #<?= $order['id'] ?>
                        </h2>
                        <p class="text-muted mb-0">
                            Modificar detalles de la orden
                        </p>
                    </div>
                    <div>
                        <a href="view.php?id=<?= $order['id'] ?>" class="btn btn-outline-info">
                            <i class="fas fa-eye me-2"></i>Ver Detalles
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Balance Summary -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-calculator me-2"></i>Resumen Financiero
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h5 class="text-info">Total de la Orden</h5>
                                    <h3 class="text-info">$<?= number_format($currentTotal, 2) ?></h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h5 class="text-success">Total Pagado</h5>
                                    <h3 class="text-success">$<?= number_format($totalPaid, 2) ?></h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h5 class="text-warning">Saldo a Favor</h5>
                                    <h3 class="text-warning">$<?= number_format($changeAmount, 2) ?></h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <?php if ($balanceDifference > 0): ?>
                                        <h5 class="text-danger">Por Cobrar</h5>
                                        <h3 class="text-danger">$<?= number_format($balanceDifference, 2) ?></h3>
                                    <?php elseif ($balanceDifference < 0): ?>
                                        <h5 class="text-success">Crédito Disponible</h5>
                                        <h3 class="text-success">$<?= number_format(abs($balanceDifference), 2) ?></h3>
                                    <?php else: ?>
                                        <h5 class="text-success">Balanceado</h5>
                                        <h3 class="text-success">$0.00</h3>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($balanceDifference > 0): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Diferencia por cobrar:</strong> $<?= number_format($balanceDifference, 2) ?>
                                    <button class="btn btn-sm btn-primary ms-3" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                        <i class="fas fa-credit-card me-1"></i>Procesar Pago
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Order Information -->
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-info-circle me-2"></i>Información de la Orden
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_order">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="customer_name" class="form-label">Nombre del Cliente</label>
                                        <input type="text" class="form-control" id="customer_name" name="customer_name" 
                                               value="<?= htmlspecialchars($order['customer_name']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="customer_email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="customer_email" name="customer_email" 
                                               value="<?= htmlspecialchars($order['customer_email']) ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="customer_phone" class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="customer_phone" name="customer_phone" 
                                               value="<?= htmlspecialchars($order['customer_phone'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Estado</label>
                                        <select class="form-select" id="status" name="status" required 
                                                <?= ($balanceDifference > 0) ? 'disabled title="Debe completar el pago antes de cambiar el estado"' : '' ?>>
                                            <?php foreach ($statusLabels as $status => $label): ?>
                                                <option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>>
                                                    <?= $label ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($balanceDifference > 0): ?>
                                            <small class="text-warning">
                                                <i class="fas fa-lock me-1"></i>Complete el pago para habilitar cambios de estado
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="payment_method" class="form-label">Método de Pago Principal</label>
                                <select class="form-select" id="payment_method" name="payment_method" required>
                                    <option value="efectivo" <?= $order['payment_method'] === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                                    <option value="tarjeta" <?= $order['payment_method'] === 'tarjeta' ? 'selected' : '' ?>>Tarjeta</option>
                                    <option value="transferencia" <?= $order['payment_method'] === 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
                                    <option value="pago_movil" <?= $order['payment_method'] === 'pago_movil' ? 'selected' : '' ?>>Pago Móvil</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notas</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Guardar Cambios
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-receipt me-2"></i>Resumen de la Orden
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>ID de Orden:</strong></td>
                                <td>#<?= $order['id'] ?></td>
                            </tr>
                            <tr>
                                <td><strong>Fecha:</strong></td>
                                <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Estado Actual:</strong></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?= $statusLabels[$order['status']] ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Total:</strong></td>
                                <td><strong class="text-success">$<?= number_format($currentTotal, 2) ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-shopping-cart me-2"></i>Productos de la Orden
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orderItems)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-cart fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay productos en esta orden</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Precio Unit.</th>
                                            <th>Cantidad</th>
                                            <th>Subtotal</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orderItems as $item): ?>
                                            <tr id="item-<?= $item['id'] ?>">
                                                <td>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($item['product_name']) ?></h6>
                                                        <?php if (!empty($item['notes'])): ?>
                                                            <small class="text-info">
                                                                <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($item['notes']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline" onsubmit="return updateItem(<?= $item['id'] ?>)">
                                                        <input type="hidden" name="action" value="update_item">
                                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                        <div class="input-group input-group-sm" style="width: 100px;">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" step="0.01" min="0" class="form-control" 
                                                                   name="price" value="<?= $item['price'] ?>" 
                                                                   onchange="this.form.submit()">
                                                        </div>
                                                    </form>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline" onsubmit="return updateItem(<?= $item['id'] ?>)">
                                                        <input type="hidden" name="action" value="update_item">
                                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                        <input type="hidden" name="price" value="<?= $item['price'] ?>">
                                                        <div class="input-group input-group-sm" style="width: 80px;">
                                                            <input type="number" min="1" max="99" class="form-control text-center" 
                                                                   name="quantity" value="<?= $item['quantity'] ?>" 
                                                                   onchange="this.form.submit()">
                                                        </div>
                                                    </form>
                                                </td>
                                                <td>
                                                    <strong>$<?= number_format($item['price'] * $item['quantity'], 2) ?></strong>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="removeItem(<?= $item['id'] ?>)" 
                                                            title="Eliminar producto">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-active">
                                            <th colspan="3">Total:</th>
                                            <th class="text-success">$<?= number_format($currentTotal, 2) ?></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Add Product Section -->
                        <div class="mt-4 pt-4 border-top">
                            <h6 class="mb-3">
                                <i class="fas fa-plus me-2"></i>Agregar Nuevo Producto
                            </h6>
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="add_product">
                                
                                <div class="col-md-4">
                                    <label for="product_id" class="form-label">Producto</label>
                                    <select class="form-select" id="product_id" name="product_id" required>
                                        <option value="">Seleccionar producto...</option>
                                        <?php
                                        // Get available products
                                        $products = $db->fetchAll("SELECT id, name, price FROM products WHERE status = 'active' ORDER BY name");
                                        foreach ($products as $product):
                                        ?>
                                            <option value="<?= $product['id'] ?>" data-price="<?= $product['price'] ?>">
                                                <?= htmlspecialchars($product['name']) ?> - $<?= number_format($product['price'], 2) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="add_quantity" class="form-label">Cantidad</label>
                                    <input type="number" class="form-control" id="add_quantity" name="quantity" 
                                           min="1" max="99" value="1" required>
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="add_price" class="form-label">Precio</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               id="add_price" name="price" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="add_notes" class="form-label">Notas (Opcional)</label>
                                    <input type="text" class="form-control" id="add_notes" name="notes" 
                                           placeholder="Notas especiales...">
                                </div>
                                
                                <div class="col-md-1">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Procesar Pago Adicional</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="process_payment">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Diferencia por cobrar:</strong> $<?= number_format($balanceDifference, 2) ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_method_modal" class="form-label">Método de Pago</label>
                            <select class="form-select" id="payment_method_modal" name="payment_method" required>
                                <option value="">Seleccionar método...</option>
                                <?php
                                // Get active payment methods from database
                                $paymentMethods = $db->fetchAll("SELECT name FROM payment_methods WHERE status = 'active' ORDER BY name");
                                foreach ($paymentMethods as $method):
                                ?>
                                    <option value="<?= htmlspecialchars($method['name']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $method['name']))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount_modal" class="form-label">Monto a Pagar</label>
                            <div class="input-group">
                                <span class="input-group-text" id="currency-symbol">$</span>
                                <input type="number" step="0.01" min="0.01" 
                                       class="form-control" id="amount_modal" name="amount" 
                                       value="<?= $balanceDifference ?>" required>
                            </div>
                            <div id="conversion-info" style="display: none;" class="mt-2"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reference_modal" class="form-label">Referencia</label>
                            <input type="text" class="form-control" id="reference_modal" name="reference" 
                                   placeholder="Número de referencia o comprobante">
                            <small class="text-muted" id="reference-help">Para Pago Móvil: últimos 6 dígitos de la referencia</small>
                        </div>
                        
                        <!-- Campos adicionales para Pago Móvil -->
                        <div id="pagomovil-fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="mobile_modal" class="form-label">Teléfono (Opcional)</label>
                                        <input type="text" class="form-control" id="mobile_modal" name="mobile" 
                                               placeholder="04XX-XXXXXXX">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sender_modal" class="form-label">Remitente (Opcional)</label>
                                        <input type="text" class="form-control" id="sender_modal" name="sender" 
                                               placeholder="Nombre del remitente">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info" id="pagomovil-validation-info" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Pago Móvil:</strong> Se validará automáticamente con la API externa antes de procesar.
                        </div>
                        
                        <div class="alert alert-warning" id="electronic-payment-warning" style="display: none;">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Nota:</strong> Los pagos de Débito Inmediato requieren validación manual antes de poder cambiar el estado de la orden.
                        </div>
                        
                        <!-- API Logs Section -->
                        <div id="api-logs-section" style="display: none;">
                            <hr>
                            <h6><i class="fas fa-code me-2"></i>Logs de API</h6>
                            
                            <!-- Request Details - Always Visible -->
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-arrow-up me-2"></i>Petición Enviada
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div id="request-details"></div>
                                </div>
                            </div>
                            
                            <!-- Response Details - Always Visible -->
                            <div class="card mb-3">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-arrow-down me-2"></i>Respuesta Recibida
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div id="response-details"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="process-payment-btn">
                            <span class="btn-text">
                                <i class="fas fa-credit-card me-2"></i>Procesar Pago
                            </span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                Validando...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Remove Item Modal -->
    <div class="modal fade" id="removeItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de que desea eliminar este producto de la orden?</p>
                    <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" id="removeItemForm" class="d-inline">
                        <input type="hidden" name="action" value="remove_item">
                        <input type="hidden" name="item_id" id="removeItemId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Debug mode configuration from PHP
        const debugEnabled = <?= $debugEnabled ? 'true' : 'false' ?>;
        
        function updateItem(itemId) {
            // Show loading indicator
            const row = document.getElementById('item-' + itemId);
            if (row) {
                row.style.opacity = '0.6';
            }
            return true;
        }

        function removeItem(itemId) {
            document.getElementById('removeItemId').value = itemId;
            const modal = new bootstrap.Modal(document.getElementById('removeItemModal'));
            modal.show();
        }

        // Auto-save functionality for inputs
        document.querySelectorAll('input[type="number"]').forEach(input => {
            let timeout;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    if (this.form && this.value !== this.defaultValue) {
                        // Visual feedback
                        this.style.borderColor = '#ffc107';
                        setTimeout(() => {
                            this.style.borderColor = '';
                        }, 1000);
                    }
                }, 500);
            });
        });

        // Auto-fill price when product is selected
        document.getElementById('product_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            
            if (price) {
                document.getElementById('add_price').value = price;
            }
        });

        // Show/hide payment method specific fields and warnings
        document.getElementById('payment_method_modal').addEventListener('change', function() {
            const paymentMethod = this.value;
            const pagomovilFields = document.getElementById('pagomovil-fields');
            const pagomovilInfo = document.getElementById('pagomovil-validation-info');
            const electronicWarning = document.getElementById('electronic-payment-warning');
            const referenceField = document.getElementById('reference_modal');
            const referenceHelp = document.getElementById('reference-help');
            const amountField = document.getElementById('amount_modal');
            
            // Hide all by default
            pagomovilFields.style.display = 'none';
            pagomovilInfo.style.display = 'none';
            electronicWarning.style.display = 'none';
            referenceField.required = false;
            
            // Métodos que requieren conversión de moneda
            const methodsRequiringConversion = ['pagomovil', 'debito_inmediato', 'efectivo_bolivares', 'tarjeta', 'transferencia'];
            
            if (paymentMethod === 'pagomovil') {
                pagomovilFields.style.display = 'block';
                pagomovilInfo.style.display = 'block';
                referenceField.required = true;
                referenceField.placeholder = 'Últimos 6 dígitos de la referencia';
                referenceHelp.textContent = 'Para Pago Móvil: últimos 6 dígitos de la referencia (requerido)';
            } else if (paymentMethod === 'debito_inmediato') {
                electronicWarning.style.display = 'block';
                referenceField.placeholder = 'Número de referencia o comprobante';
                referenceHelp.textContent = 'Número de referencia o comprobante (opcional)';
            } else {
                referenceField.placeholder = 'Número de referencia o comprobante';
                referenceHelp.textContent = 'Número de referencia o comprobante (opcional)';
            }
            
            // Aplicar conversión de moneda si es necesario
            if (methodsRequiringConversion.includes(paymentMethod)) {
                convertCurrencyForPayment();
            } else {
                // Restaurar monto original en USD para efectivo USD
                const originalAmount = <?= $balanceDifference ?>;
                amountField.value = originalAmount.toFixed(2);
                
                // Cambiar símbolo de moneda a USD
                const currencySymbol = document.getElementById('currency-symbol');
                if (currencySymbol) {
                    currencySymbol.textContent = '$';
                }
                
                // Restaurar validación max para USD
                amountField.max = originalAmount;
                
                // Ocultar información de conversión
                const conversionInfo = document.getElementById('conversion-info');
                if (conversionInfo) {
                    conversionInfo.style.display = 'none';
                }
            }
        });
        
        // Función para convertir moneda usando la API de exchange rate
        async function convertCurrencyForPayment() {
            const amountField = document.getElementById('amount_modal');
            const originalAmount = <?= $balanceDifference ?>;
            
            try {
                // Mostrar indicador de carga
                amountField.style.opacity = '0.6';
                amountField.disabled = true;
                
                // Consultar tasa de cambio
                const response = await fetch('/reserve/api/exchange_rate.php');
                const data = await response.json();
                
                if (data.success && data.rate) {
                    // Convertir USD a VES (Bolívares)
                    const convertedAmount = originalAmount * data.rate;
                    amountField.value = convertedAmount.toFixed(2);
                    
                    // Cambiar símbolo de moneda a Bolívares
                    const currencySymbol = document.getElementById('currency-symbol');
                    if (currencySymbol) {
                        currencySymbol.textContent = 'Bs.';
                    }
                    
                    // Remover validación max para permitir montos en bolívares
                    amountField.removeAttribute('max');
                    
                    // Mostrar información de la conversión
                    const conversionInfo = document.getElementById('conversion-info');
                    if (conversionInfo) {
                        conversionInfo.innerHTML = `
                            <small class="text-info">
                                <i class="fas fa-exchange-alt me-1"></i>
                                Convertido: $${originalAmount.toFixed(2)} USD × ${data.rate.toFixed(2)} = Bs. ${convertedAmount.toFixed(2)}
                                ${data.source === 'fallback' ? ' (Tasa de respaldo)' : ''}
                            </small>
                        `;
                        conversionInfo.style.display = 'block';
                    }
                } else {
                    // Error en la conversión, usar tasa de respaldo
                    const fallbackRate = 36.50;
                    const convertedAmount = originalAmount * fallbackRate;
                    amountField.value = convertedAmount.toFixed(2);
                    
                    // Cambiar símbolo de moneda a Bolívares
                    const currencySymbol = document.getElementById('currency-symbol');
                    if (currencySymbol) {
                        currencySymbol.textContent = 'Bs.';
                    }
                    
                    // Remover validación max para permitir montos en bolívares
                    amountField.removeAttribute('max');
                    
                    const conversionInfo = document.getElementById('conversion-info');
                    if (conversionInfo) {
                        conversionInfo.innerHTML = `
                            <small class="text-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Convertido con tasa de respaldo: $${originalAmount.toFixed(2)} USD × ${fallbackRate} = Bs. ${convertedAmount.toFixed(2)}
                            </small>
                        `;
                        conversionInfo.style.display = 'block';
                    }
                }
            } catch (error) {
                console.error('Error al obtener tasa de cambio:', error);
                
                // Error en la consulta, usar tasa de respaldo
                const fallbackRate = 36.50;
                const convertedAmount = originalAmount * fallbackRate;
                amountField.value = convertedAmount.toFixed(2);
                
                // Cambiar símbolo de moneda a Bolívares
                const currencySymbol = document.getElementById('currency-symbol');
                if (currencySymbol) {
                    currencySymbol.textContent = 'Bs.';
                }
                
                // Remover validación max para permitir montos en bolívares
                amountField.removeAttribute('max');
                
                const conversionInfo = document.getElementById('conversion-info');
                if (conversionInfo) {
                    conversionInfo.innerHTML = `
                        <small class="text-danger">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Error de conexión. Usando tasa de respaldo: Bs. ${convertedAmount.toFixed(2)}
                        </small>
                    `;
                    conversionInfo.style.display = 'block';
                }
            } finally {
                // Restaurar estado del campo
                amountField.style.opacity = '1';
                amountField.disabled = false;
            }
        }

        // Function to display API logs
        function displayApiLogs(apiLogs) {
            const apiLogsSection = document.getElementById('api-logs-section');
            const requestDetails = document.getElementById('request-details');
            const responseDetails = document.getElementById('response-details');
            
            if (apiLogs) {
                // Show the logs section
                apiLogsSection.style.display = 'block';
                
                // Display request details
                requestDetails.innerHTML = `
                    <div class="mb-3">
                        <strong>URL:</strong> <code>${apiLogs.request_sent || 'N/A'}</code>
                    </div>
                    <div class="mb-3">
                        <strong>Headers:</strong>
                        <pre class="bg-light p-2 rounded"><code>${Array.isArray(apiLogs.request_headers) ? apiLogs.request_headers.join('\n') : 'N/A'}</code></pre>
                    </div>
                    <div class="mb-3">
                        <strong>Body:</strong>
                        <pre class="bg-light p-2 rounded"><code>${apiLogs.request_body || 'N/A'}</code></pre>
                    </div>
                `;
                
                // Display response details
                responseDetails.innerHTML = `
                    <div class="mb-3">
                        <strong>Status:</strong> <span class="badge ${apiLogs.response_received && apiLogs.response_received.includes('200') ? 'bg-success' : 'bg-danger'}">${apiLogs.response_received || 'N/A'}</span>
                    </div>
                    <div class="mb-3">
                        <strong>Response Body:</strong>
                        <pre class="bg-light p-2 rounded"><code>${apiLogs.response_body || 'Sin respuesta'}</code></pre>
                    </div>
                    ${apiLogs.fallback_action ? `
                    <div class="mb-3">
                        <strong>Acción de Fallback:</strong>
                        <div class="alert alert-warning">${apiLogs.fallback_action}</div>
                    </div>
                    ` : ''}
                `;
            } else {
                apiLogsSection.style.display = 'none';
            }
        }

        // Handle payment form submission with loading spinner and API logging
        document.querySelector('#paymentModal form').addEventListener('submit', function(e) {
            const btn = document.getElementById('process-payment-btn');
            const btnText = btn.querySelector('.btn-text');
            const btnLoading = btn.querySelector('.btn-loading');
            const paymentMethod = document.getElementById('payment_method_modal').value;
            
            // Hide API logs initially
            document.getElementById('api-logs-section').style.display = 'none';
            
            // Show spinner for Pago Móvil and Transferencia validation
            if (paymentMethod === 'pagomovil' || paymentMethod === 'transferencia') {
                btnText.classList.add('d-none');
                btnLoading.classList.remove('d-none');
                btn.disabled = true;
                
                // Update text based on payment method
                const methodText = paymentMethod === 'pagomovil' ? 'Pago Móvil' : 'Transferencia';
                btnLoading.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Validando ${methodText}...`;
                
                // For Pago Móvil and Transferencia, we'll intercept the form submission to show API logs
                e.preventDefault();
                
                // Get form data
                const formData = new FormData(this);
                
                // First, call the validation API directly to get logs
                const validationData = {
                    amount: formData.get('amount'),
                    reference: formData.get('reference'),
                    method: paymentMethod === 'transferencia' ? '' : paymentMethod
                };
                
                // Only add mobile if it's not empty
                const mobile = formData.get('mobile');
                if (mobile && mobile.trim() !== '') {
                    validationData.mobile = mobile;
                }
                
                // Only add sender if it's not empty
                const sender = formData.get('sender');
                if (sender && sender.trim() !== '') {
                    validationData.sender = sender;
                }
                
                // Call validation API directly
                fetch('/reserve/api/validate_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(validationData)
                })
                .then(response => response.json())
                .then(apiResult => {
                    // Reset button state
                    btnText.classList.remove('d-none');
                    btnLoading.classList.add('d-none');
                    btn.disabled = false;
                    
                    // Show API logs if available and debug is enabled
                    if (debugEnabled && apiResult.api_logs) {
                        displayApiLogs(apiResult.api_logs);
                    }
                    
                    // Show result message
                    const alertClass = apiResult.success ? 'alert-success' : 'alert-danger';
                    const alertIcon = apiResult.success ? 'check-circle' : 'exclamation-triangle';
                    
                    // Create alert message
                    const alertHtml = `
                        <div class="alert ${alertClass} mt-3">
                            <i class="fas fa-${alertIcon} me-2"></i>
                            <strong>Resultado de Validación:</strong> ${apiResult.message}
                        </div>
                    `;
                    
                    // Insert alert before API logs section
                    const apiLogsSection = document.getElementById('api-logs-section');
                    apiLogsSection.insertAdjacentHTML('beforebegin', alertHtml);
                    
                    // If validation was successful, proceed automatically
                    if (apiResult.success) {
                        // Check if it's automatic validation (API success) or manual validation
                        if (apiResult.manual_validation) {
                            // Manual validation - show option to proceed
                            const proceedHtml = `
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>¿Desea proceder con el registro manual del pago?</strong>
                                    <button type="button" class="btn btn-sm btn-primary ms-3" onclick="proceedWithPayment()">
                                        <i class="fas fa-check me-1"></i>Sí, Registrar Manualmente
                                    </button>
                                </div>
                            `;
                            apiLogsSection.insertAdjacentHTML('beforebegin', proceedHtml);
                        } else {
                            // Automatic validation - proceed immediately
                            setTimeout(() => {
                                proceedWithPayment();
                            }, 2000); // Wait 2 seconds to show the success message
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    // Reset button state
                    btnText.classList.remove('d-none');
                    btnLoading.classList.add('d-none');
                    btn.disabled = false;
                    
                    // API not available - allow manual registration
                    const errorHtml = `
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>API no disponible:</strong> No se pudo conectar con el servicio de validación. Se permite registro manual.
                        </div>
                    `;
                    const apiLogsSection = document.getElementById('api-logs-section');
                    apiLogsSection.insertAdjacentHTML('beforebegin', errorHtml);
                    
                    // Show option to proceed with manual registration
                    const proceedHtml = `
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>¿Desea proceder con el registro manual del pago?</strong>
                            <button type="button" class="btn btn-sm btn-primary ms-3" onclick="proceedWithPayment()">
                                <i class="fas fa-check me-1"></i>Sí, Registrar Manualmente
                            </button>
                        </div>
                    `;
                    apiLogsSection.insertAdjacentHTML('beforebegin', proceedHtml);
                });
                
            } else if (paymentMethod === 'debito_inmediato') {
                btnText.classList.add('d-none');
                btnLoading.classList.remove('d-none');
                btn.disabled = true;
                
                btnLoading.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';
            } else {
                btnText.classList.add('d-none');
                btnLoading.classList.remove('d-none');
                btn.disabled = true;
                
                btnLoading.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando Pago...';
            }
        });
        
        // Function to proceed with payment after validation
        window.proceedWithPayment = function() {
            // Submit the original form
            document.querySelector('#paymentModal form').submit();
        };

        // Modal form reset functionality to prevent resubmission issues
        document.addEventListener('DOMContentLoaded', function() {
            // Reset payment modal when closed
            const paymentModal = document.getElementById('paymentModal');
            if (paymentModal) {
                paymentModal.addEventListener('hidden.bs.modal', function() {
                    // Reset form
                    const form = this.querySelector('form');
                    if (form) {
                        form.reset();
                        
                        // Reset dynamic elements
                        const paymentMethodSelect = document.getElementById('payment_method_modal');
                        const amountField = document.getElementById('amount_modal');
                        const currencySymbol = document.getElementById('currency-symbol');
                        const conversionInfo = document.getElementById('conversion-info');
                        const referenceField = document.getElementById('reference_modal');
                        const referenceHelp = document.getElementById('reference-help');
                        
                        // Reset payment method specific fields
                        document.getElementById('pagomovil-fields').style.display = 'none';
                        document.getElementById('pagomovil-validation-info').style.display = 'none';
                        document.getElementById('electronic-payment-warning').style.display = 'none';
                        document.getElementById('api-logs-section').style.display = 'none';
                        
                        // Reset currency symbol and amount
                        if (currencySymbol) currencySymbol.textContent = '$';
                        if (amountField) {
                            amountField.value = <?= $balanceDifference ?>;
                            amountField.removeAttribute('max');
                            amountField.style.opacity = '1';
                            amountField.disabled = false;
                        }
                        if (conversionInfo) conversionInfo.style.display = 'none';
                        
                        // Reset reference field
                        if (referenceField) {
                            referenceField.required = false;
                            referenceField.placeholder = 'Número de referencia o comprobante';
                        }
                        if (referenceHelp) {
                            referenceHelp.textContent = 'Para Pago Móvil: últimos 6 dígitos de la referencia';
                        }
                        
                        // Reset button state
                        const btn = document.getElementById('process-payment-btn');
                        const btnText = btn.querySelector('.btn-text');
                        const btnLoading = btn.querySelector('.btn-loading');
                        if (btnText && btnLoading) {
                            btnText.classList.remove('d-none');
                            btnLoading.classList.add('d-none');
                            btn.disabled = false;
                        }
                        
                        // Remove any dynamic alerts
                        const alerts = this.querySelectorAll('.alert:not(.alert-info):not(.alert-warning)');
                        alerts.forEach(alert => {
                            if (!alert.classList.contains('alert-info') || 
                                !alert.textContent.includes('Diferencia por cobrar')) {
                                alert.remove();
                            }
                        });
                    }
                });
            }
            
            // Reset remove item modal when closed
            const removeItemModal = document.getElementById('removeItemModal');
            if (removeItemModal) {
                removeItemModal.addEventListener('hidden.bs.modal', function() {
                    const form = document.getElementById('removeItemForm');
                    if (form) {
                        form.reset();
                        document.getElementById('removeItemId').value = '';
                    }
                });
            }
        });

        // Auto-refresh balance information every 30 seconds
        setInterval(function() {
            // Only refresh if no modals are open
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 30000);
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
