<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$conn = getDBConnection();

// Create new order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Debug: Log received data
    error_log('Order data received: ' . json_encode($data));
    
    // Validate required fields - support both single and multiple payments
    if (!isset($data['customer']) || !isset($data['items'])) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos - faltan campos principales']);
        exit;
    }
    
    // Check for payment method (single or multiple)
    if (!isset($data['payment_method']) && !isset($data['payment_methods'])) {
        echo json_encode(['success' => false, 'message' => 'Método de pago requerido']);
        exit;
    }
    
    $customer = $data['customer'];
    
    // Validate customer data
    if (!isset($customer['first_name']) || !isset($customer['last_name']) || !isset($customer['email'])) {
        echo json_encode(['success' => false, 'message' => 'Datos del cliente incompletos']);
        exit;
    }
    
    // Extract customer data
    $first_name = $conn->real_escape_string($customer['first_name']);
    $last_name = $conn->real_escape_string($customer['last_name']);
    $customer_name = $first_name . ' ' . $last_name;
    $customer_email = $conn->real_escape_string($customer['email']);
    
    // Extract phone numbers
    $customer_phone = '';
    $whatsapp_phone = '';
    if (isset($customer['phones']) && is_array($customer['phones'])) {
        foreach ($customer['phones'] as $phone) {
            if ($phone['is_primary']) {
                $customer_phone = $conn->real_escape_string($phone['phone_number']);
            }
            if (isset($phone['is_whatsapp']) && $phone['is_whatsapp']) {
                $whatsapp_phone = $conn->real_escape_string($phone['phone_number']);
            }
        }
    }
    
    // Handle payment method (single or multiple)
    $payment_method = '';
    $payment_methods = [];
    
    if (isset($data['payment_methods']) && is_array($data['payment_methods'])) {
        // Multiple payments system
        $payment_methods = $data['payment_methods'];
        if (empty($payment_methods)) {
            echo json_encode(['success' => false, 'message' => 'Debe agregar al menos un método de pago']);
            exit;
        }
        // Create a summary for the main payment_method field
        $payment_method = implode(', ', array_map(function($p) {
            return $p['method'] . ' ($' . number_format($p['amount'], 2) . ')';
        }, $payment_methods));
    } elseif (isset($data['payment_method'])) {
        // Single payment system (backward compatibility)
        $payment_method = $data['payment_method'];
    }
    
    $payment_method = $conn->real_escape_string($payment_method);
    $order_type = $conn->real_escape_string($data['order_type'] ?? 'delivery');
    $delivery_fee = 0; // Set delivery fee to 0 as requested
    //$delivery_fee = floatval($data['delivery_fee'] ?? 0);
    $items = $data['items'];
    
    // Calculate subtotal from items
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    // Get total in USD from frontend (required) - this should match the "Total" shown in order summary
    $total_usd = isset($data['total_usd']) ? floatval($data['total_usd']) : $subtotal;
    
    // Use total_paid if available (for multiple payments), otherwise use total_usd
    $total = isset($data['total_paid']) ? floatval($data['total_paid']) : $total_usd;
    
    // Get exchange rate (default if not provided)
    $exchange_rate = isset($data['exchange_rate']) ? floatval($data['exchange_rate']) : 36.50;
    
    // Calculate total_amount in bolivares (total_usd * exchange_rate)
    $total_amount_bolivares = round($total_usd * $exchange_rate, 2);
    
    // Determine order status based on payment methods
    $order_status = 'pending'; // Default status
    $has_cash_payment = false;
    $has_electronic_payment = false;
    
    if (!empty($payment_methods)) {
        foreach ($payment_methods as $payment) {
            $method_lower = strtolower($payment['method']);
            
            // Check for electronic payments (Pagomovil, Debito inmediato)
            if (strpos($method_lower, 'Pagomovil') !== false || 
                strpos($method_lower, 'pago móvil') !== false ||
                strpos($method_lower, 'Débito Inmediato') !== false ||
                strpos($method_lower, 'debito inmediato') !== false) {
                $has_electronic_payment = true;
            }
            
            // Check for cash payments
            if (strpos($method_lower, 'efectivo') !== false) {
                $has_cash_payment = true;
            }
        }
    } elseif (isset($data['payment_method'])) {
        // Single payment method check
        $method_lower = strtolower($data['payment_method']);
        if (strpos($method_lower, 'Pagomovil') !== false || 
            strpos($method_lower, 'pago móvil') !== false ||
            strpos($method_lower, 'Débito Inmediato') !== false ||
            strpos($method_lower, 'debito inmediato') !== false) {
            $has_electronic_payment = true;
        }
        if (strpos($method_lower, 'efectivo') !== false) {
            $has_cash_payment = true;
        }
    }
    
    // Set order status: 'confirmed' if only electronic payments, 'pending' if has cash
    if ($has_electronic_payment && !$has_cash_payment) {
        $order_status = 'confirmed';
    }
    
    // Handle address data for delivery orders
    $address_data = null;
    if ($order_type === 'delivery') {
        if (isset($data['address_id'])) {
            $address_data = ['address_id' => intval($data['address_id'])];
        } elseif (isset($data['address'])) {
            $address_data = $data['address'];
        }
    }
    
    // Handle pickup store for pickup orders
    $pickup_store_id = null;
    if ($order_type === 'pickup' && isset($data['pickup_store_id'])) {
        $pickup_store_id = intval($data['pickup_store_id']);
    }
    
    try {
        $conn->begin_transaction();
        
        // First, create or get customer ID
        $customer_id = null;
        
        // Check if customer exists by email
        $customer_check_sql = "SELECT id FROM customers WHERE email = '$customer_email' LIMIT 1";
        $customer_result = $conn->query($customer_check_sql);
        
        if ($customer_result && $customer_result->num_rows > 0) {
            // Customer exists, get ID
            $customer_row = $customer_result->fetch_assoc();
            $customer_id = $customer_row['id'];
        } else {
            // Customer doesn't exist, create new customer
            $customer_insert_sql = "INSERT INTO customers (
                                        first_name, last_name, email, created_at
                                    ) VALUES (
                                        '$first_name', '$last_name', '$customer_email', NOW()
                                    )";
            
            if (!$conn->query($customer_insert_sql)) {
                throw new Exception('Error al crear el cliente: ' . $conn->error);
            }
            
            $customer_id = $conn->insert_id;
            
            // Insert customer phones if provided
            if (!empty($customer_phone)) {
                $phone_sql = "INSERT INTO customer_phones (
                                customer_id, phone_number, phone_type, is_primary, is_whatsapp, created_at
                              ) VALUES (
                                $customer_id, '$customer_phone', 'mobile', 1, 0, NOW()
                              )";
                $conn->query($phone_sql); // Don't throw error if phone insert fails
            }
            
            // Insert WhatsApp phone if different from primary
            if (isset($data['customer']['phones']) && is_array($data['customer']['phones'])) {
                foreach ($data['customer']['phones'] as $phone) {
                    if (isset($phone['is_whatsapp']) && $phone['is_whatsapp'] && 
                        $phone['phone_number'] !== $customer_phone) {
                        $whatsapp_phone_escaped = $conn->real_escape_string($phone['phone_number']);
                        $whatsapp_sql = "INSERT INTO customer_phones (
                                           customer_id, phone_number, phone_type, is_primary, is_whatsapp, created_at
                                         ) VALUES (
                                           $customer_id, '$whatsapp_phone_escaped', 'mobile', 0, 1, NOW()
                                         )";
                        $conn->query($whatsapp_sql); // Don't throw error if phone insert fails
                    }
                }
            }
        }
        
        // Insert order with customer_id and correct amounts and status
        $sql = "INSERT INTO orders (
                    customer_id, customer_name, customer_email, customer_phone,
                    total_amount, total_amount_usd, payment_method, status, created_at
                ) VALUES (
                    $customer_id, '$customer_name', '$customer_email', '$customer_phone',
                    $total_amount_bolivares, $total_usd, '$payment_method', '$order_status', NOW()
                )";
        
        if (!$conn->query($sql)) {
            throw new Exception('Error al crear el pedido: ' . $conn->error);
        }
        
        $order_id = $conn->insert_id;
        
        // Insert order items with enhanced data
        foreach ($items as $item) {
            $product_id = intval($item['id']);
            $quantity = intval($item['quantity']);
            $price = floatval($item['price']);
            
            // Create item description with size and additionals
            $item_description = '';
            if (isset($item['size']) && $item['size']) {
                $item_description .= 'Tamaño: ' . $item['size']['name'] . '; ';
            }
            if (isset($item['additionals']) && is_array($item['additionals']) && count($item['additionals']) > 0) {
                $additionals_names = array_map(function($add) { return $add['name']; }, $item['additionals']);
                $item_description .= 'Adicionales: ' . implode(', ', $additionals_names) . '; ';
            }
            if (isset($item['notes']) && !empty($item['notes'])) {
                $item_description .= 'Notas: ' . $item['notes'];
            }
            
            $item_description = $conn->real_escape_string(trim($item_description, '; '));
            
            // Use only basic columns that should exist in order_items table
            $sql = "INSERT INTO order_items (
                        order_id, product_id, quantity, price
                    ) VALUES (
                        $order_id, $product_id, $quantity, $price
                    )";
            
            if (!$conn->query($sql)) {
                throw new Exception('Error al insertar item del pedido: ' . $conn->error);
            }
        }
        
        // Store payment methods details in order_payments table
        if (!empty($payment_methods)) {
            foreach ($payment_methods as $payment) {
                $current_payment_method = $conn->real_escape_string($payment['method']);
                $payment_type = isset($payment['type']) ? $conn->real_escape_string($payment['type']) : 'electronic';
                $amount = floatval($payment['amount']);
                $status = isset($payment['status']) ? $conn->real_escape_string($payment['status']) : 'paid';
                $validated = isset($payment['validated']) ? ($payment['validated'] ? 1 : 0) : 0;
                
                // Initialize payment fields
                $bank_origin = 'NULL';
                $bank_destination = 'NULL';
                $reference = 'NULL';
                $phone = 'NULL';
                $change_amount = 0.00;
                $validation_data = 'NULL';
                $transaction_id = 'NULL';
                
                // Handle electronic payments (Pago Móvil, Débito, Tarjetas)
                if ($payment_type === 'electronic') {
                    if (isset($payment['validation_data'])) {
                        $validation_info = $payment['validation_data'];
                        
                        // Extract bank information
                        if (isset($validation_info['bank_origin_name'])) {
                            $bank_origin = "'" . $conn->real_escape_string($validation_info['bank_origin_name']) . "'";
                        }
                        if (isset($validation_info['bank_destiny_name'])) {
                            $bank_destination = "'" . $conn->real_escape_string($validation_info['bank_destiny_name']) . "'";
                        }
                        
                        // Store full validation data as JSON
                        $validation_data = "'" . $conn->real_escape_string(json_encode($validation_info)) . "'";
                    }
                    
                    // Extract reference and phone
                    if (isset($payment['reference'])) {
                        $reference = "'" . $conn->real_escape_string($payment['reference']) . "'";
                        $transaction_id = $reference; // Use reference as transaction_id for electronic payments
                    }
                    if (isset($payment['phone'])) {
                        $phone = "'" . $conn->real_escape_string($payment['phone']) . "'";
                    }
                }
                
                // Handle cash payments and change information
                if ($payment_type === 'cash') {
                    if (isset($payment['change_info'])) {
                        $change_info = $payment['change_info'];
                        $change_amount = floatval($change_info['change'] ?? $change_info['change_ves'] ?? 0);
                        
                        // Register change history if there's change to be returned
                        if ($change_amount > 0) {
                            $change_currency = 'USD'; // Default currency
                            $change_method = 'cash'; // Default method
                            $customer_phone_change = '';
                            $customer_cedula_change = '';
                            $bank_code_change = '';
                            $bank_name_change = '';
                            
                            // Determine currency and method based on payment method
                            $method_lower = strtolower($current_payment_method);
                            if (strpos($method_lower, 'bolívar') !== false || strpos($method_lower, 'bolivar') !== false) {
                                $change_currency = 'VES';
                                $change_method = 'pagomovil'; // Bolivares change is usually via Pagomovil
                            }
                            
                            // Extract change method details if provided
                            if (isset($change_info['change_method'])) {
                                $change_method = $change_info['change_method'];
                            }
                            
                            if (isset($change_info['pagomovil_data'])) {
                                $pagomovil_data = $change_info['pagomovil_data'];
                                $customer_phone_change = $conn->real_escape_string($pagomovil_data['phone'] ?? '');
                                $customer_cedula_change = $conn->real_escape_string($pagomovil_data['cedula'] ?? '');
                                $bank_code_change = $conn->real_escape_string($pagomovil_data['bank_id'] ?? '');
                                
                                // Get bank name from bank code
                                if (!empty($bank_code_change)) {
                                    $bank_query = "SELECT name FROM banks WHERE code = '$bank_code_change' LIMIT 1";
                                    $bank_result = $conn->query($bank_query);
                                    if ($bank_result && $bank_result->num_rows > 0) {
                                        $bank_row = $bank_result->fetch_assoc();
                                        $bank_name_change = $conn->real_escape_string($bank_row['name']);
                                    }
                                }
                            }
                            
                            // Calculate amounts for change history
                            $amount_paid = floatval($change_info['amount_received'] ?? $change_info['amount_received_ves'] ?? $amount);
                            $order_amount = floatval($change_info['amount_to_pay'] ?? $change_info['amount_to_pay_usd'] ?? $total_usd);
                            
                            // Insert change history record
                            $change_sql = "INSERT INTO change_history (
                                order_id, customer_id, amount_paid, order_amount, change_amount,
                                change_currency, change_method, payment_status,
                                customer_phone, customer_cedula, bank_code, bank_name,
                                notes, created_at
                            ) VALUES (
                                $order_id, $customer_id, $amount_paid, $order_amount, $change_amount,
                                '$change_currency', '$change_method', 'pending',
                                " . (!empty($customer_phone_change) ? "'$customer_phone_change'" : 'NULL') . ",
                                " . (!empty($customer_cedula_change) ? "'$customer_cedula_change'" : 'NULL') . ",
                                " . (!empty($bank_code_change) ? "'$bank_code_change'" : 'NULL') . ",
                                " . (!empty($bank_name_change) ? "'$bank_name_change'" : 'NULL') . ",
                                'Vuelto generado automáticamente desde orden #$order_id',
                                NOW()
                            )";
                            
                            if (!$conn->query($change_sql)) {
                                error_log('Error al registrar historial de vuelto: ' . $conn->error);
                                // Don't throw exception, just log the error
                            }
                            
                            // Save customer change method for future use (if pagomovil data is provided)
                            if ($change_method === 'pagomovil' && !empty($customer_phone_change) && 
                                !empty($customer_cedula_change) && !empty($bank_code_change)) {
                                
                                // Check if this customer change method already exists
                                $existing_method_sql = "SELECT id FROM customer_change_methods 
                                                       WHERE customer_id = $customer_id 
                                                       AND phone_number = '$customer_phone_change' 
                                                       AND cedula = '$customer_cedula_change' 
                                                       AND bank_code = '$bank_code_change' 
                                                       LIMIT 1";
                                
                                $existing_result = $conn->query($existing_method_sql);
                                
                                if (!$existing_result || $existing_result->num_rows === 0) {
                                    // Method doesn't exist, create new one
                                    $customer_method_sql = "INSERT INTO customer_change_methods (
                                        customer_id, phone_number, cedula, bank_code, bank_name,
                                        is_default, created_at, updated_at
                                    ) VALUES (
                                        $customer_id, '$customer_phone_change', '$customer_cedula_change', 
                                        '$bank_code_change', " . (!empty($bank_name_change) ? "'$bank_name_change'" : 'NULL') . ",
                                        1, NOW(), NOW()
                                    )";
                                    
                                    if (!$conn->query($customer_method_sql)) {
                                        error_log('Error al guardar método de pago frecuente del cliente: ' . $conn->error);
                                        // Don't throw exception, just log the error
                                    } else {
                                        error_log('Método de pago frecuente guardado para cliente ID: ' . $customer_id);
                                    }
                                } else {
                                    // Method exists, update the updated_at timestamp
                                    $update_method_sql = "UPDATE customer_change_methods 
                                                         SET updated_at = NOW() 
                                                         WHERE customer_id = $customer_id 
                                                         AND phone_number = '$customer_phone_change' 
                                                         AND cedula = '$customer_cedula_change' 
                                                         AND bank_code = '$bank_code_change'";
                                    
                                    $conn->query($update_method_sql);
                                    error_log('Método de pago frecuente actualizado para cliente ID: ' . $customer_id);
                                }
                            }
                        }
                    } elseif (isset($payment['change'])) {
                        $change_amount = floatval($payment['change']);
                    }
                    $transaction_id = "'CASH_" . $order_id . "_" . time() . "'"; // Generate transaction ID for cash
                }
                
                // Determine payment status for payments table (must match ENUM values)
                $payment_status = 'completed'; // Default for validated payments
                if ($status === 'pending' || $status === 'pending_validation') {
                    $payment_status = 'pending';
                } elseif ($status === 'failed') {
                    $payment_status = 'failed';
                } else {
                    // Ensure we always have a valid ENUM value
                    $payment_status = 'completed';
                }
                
                // Calculate amounts based on payment method
                $method_lower = strtolower($current_payment_method);
                $amount_for_order_payments = $amount; // Default: amount in USD
                $change_amount_value = $amount; // Default: amount in USD (real amount paid)
                
                // For Pagomovil, Debito inmediato, or Efectivo bolivares: convert to bolivares
                if (strpos($method_lower, 'pagomovil') !== false || 
                    strpos($method_lower, 'pago móvil') !== false ||
                    strpos($method_lower, 'débito inmediato') !== false ||
                    strpos($method_lower, 'debito inmediato') !== false ||
                    (strpos($method_lower, 'efectivo') !== false && strpos($method_lower, 'bolívar') !== false)) {
                    
                    // For these methods: amount in order_payments should be in bolivares
                    $amount_for_order_payments = round($amount * $exchange_rate, 2);
                    // change_amount keeps the real amount paid (in this case, the bolivares amount)
                    $change_amount_value = $amount_for_order_payments;
                }
                // For Efectivo dolares: keep USD amount
                elseif (strpos($method_lower, 'efectivo') !== false && 
                        (strpos($method_lower, 'dólar') !== false || strpos($method_lower, 'dollar') !== false)) {
                    // Keep USD amounts as they are
                    $amount_for_order_payments = $amount;
                    $change_amount_value = $amount;
                }
                
                // Insert into payments table
                // amount: total order amount in USD
                // amount_order: total order amount in bolivares (converted by exchange rate)
                $payments_sql = "INSERT INTO payments (
                    order_id, payment_method, payment_status, amount, amount_order,
                    transaction_id, payment_date, processed_by, notes, created_at
                ) VALUES (
                    $order_id, '$current_payment_method', '$payment_status', $total_usd, $total_amount_bolivares,
                    $transaction_id, NOW(), 1,
                    " . ($validation_data !== 'NULL' ? "'Pago validado automáticamente'" : "'Pago registrado'") . ",
                    NOW()
                )";
                
                if (!$conn->query($payments_sql)) {
                    throw new Exception('Error al guardar en tabla payments: ' . $conn->error);
                }
                
                // Get the payment ID that was just inserted
                $payment_id = $conn->insert_id;
                
                // Insert payment record in order_payments table with payment_id
                // amount: payment amount in USD for USD methods, in bolivares for bolivares methods
                // change_amount: real amount paid by the customer
                $payment_sql = "INSERT INTO order_payments (
                    order_id, payment_id, payment_method, payment_type, amount,
                    bank_origin, bank_destination, reference, phone,
                    change_amount, status, validated, validation_data,
                    created_at
                ) VALUES (
                    $order_id, $payment_id, '$current_payment_method', '$payment_type', $amount_for_order_payments,
                    $bank_origin, $bank_destination, $reference, $phone,
                    $change_amount_value, '$status', $validated, $validation_data,
                    NOW()
                )";
                
                if (!$conn->query($payment_sql)) {
                    throw new Exception('Error al guardar método de pago: ' . $conn->error);
                }
            }
        } else {
            // Handle single payment method (backward compatibility)
            $single_payment_method = $conn->real_escape_string($data['payment_method'] ?? 'Efectivo');
            $transaction_id = "'SINGLE_" . $order_id . "_" . time() . "'";
            
            // Calculate amount_order based on payment method and currency
            $amount_order_value = $total_usd; // Default to USD total
            $change_amount_value = $total_usd; // Default to USD total
            
            // Check if payment method is in bolivares
            $method_lower = strtolower($single_payment_method);
            $is_bolivares_method = (
                strpos($method_lower, 'efectivo') !== false && strpos($method_lower, 'bolívar') !== false
            ) || (
                strpos($method_lower, 'pagomovil') !== false || 
                strpos($method_lower, 'pago móvil') !== false ||
                strpos($method_lower, 'débito inmediato') !== false ||
                strpos($method_lower, 'debito inmediato') !== false
            );
            
            if ($is_bolivares_method) {
                // For bolivares methods: multiply USD amount by exchange rate and round to 2 decimals
                $amount_order_value = round($total_usd * $exchange_rate, 2);
                $change_amount_value = round($total_usd * $exchange_rate, 2);
            } elseif (strpos($method_lower, 'efectivo') !== false && strpos($method_lower, 'dólar') !== false) {
                // For "efectivo dolares": use the total amount and round to 2 decimals
                $amount_order_value = round($total, 2);
                $change_amount_value = round($total, 2);
            }
            
            // Insert into payments table
            // amount: total order amount in USD
            // amount_order: total order amount in bolivares
            $payments_sql = "INSERT INTO payments (
                order_id, payment_method, payment_status, amount, amount_order,
                transaction_id, payment_date, processed_by, notes, created_at
            ) VALUES (
                $order_id, '$single_payment_method', 'completed', $total_usd, $total_amount_bolivares,
                $transaction_id, NOW(), 1, 'Pago único registrado', NOW()
            )";
            
            if (!$conn->query($payments_sql)) {
                throw new Exception('Error al guardar en tabla payments: ' . $conn->error);
            }
            
            // Get the payment ID that was just inserted
            $payment_id = $conn->insert_id;
            
            // Insert into order_payments table with payment_id
            $payment_sql = "INSERT INTO order_payments (
                order_id, payment_id, payment_method, payment_type, amount, change_amount, status, validated, created_at
            ) VALUES (
                $order_id, $payment_id, '$single_payment_method', 'cash', $amount_order_value, $change_amount_value, 'paid', 1, NOW()
            )";
            
            if (!$conn->query($payment_sql)) {
                throw new Exception('Error al guardar método de pago: ' . $conn->error);
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Pedido creado exitosamente',
            'order_id' => $order_id,
            'total' => $total,
            'status' => $order_status,
            'payment_methods_count' => count($payment_methods)
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Order creation error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get order details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    
    $sql = "SELECT o.*, 
                   GROUP_CONCAT(
                       CONCAT(
                           p.name,
                           ' x', oi.quantity, ' ($', oi.price, ')'
                       ) SEPARATOR ', '
                   ) as items
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE o.id = $order_id
            GROUP BY o.id";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        echo json_encode(['success' => true, 'order' => $order]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
    }
}

$conn->close();
?>
