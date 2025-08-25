<?php
require_once '../config/auth.php';
require_once '../config/database.php';

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$conn = getDBConnection();
$page_title = "Pagos de Órdenes";

// Get filters
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$payment_type = isset($_GET['payment_type']) ? $_GET['payment_type'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($order_id) {
    $where_conditions[] = "op.order_id = ?";
    $params[] = $order_id;
    $types .= 'i';
}

if (!empty($payment_method)) {
    $where_conditions[] = "op.payment_method LIKE ?";
    $params[] = '%' . $payment_method . '%';
    $types .= 's';
}

if (!empty($payment_type)) {
    $where_conditions[] = "op.payment_type = ?";
    $params[] = $payment_type;
    $types .= 's';
}

if (!empty($status)) {
    $where_conditions[] = "op.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(op.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(op.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM order_payments op $where_clause";
$stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$count_result = $stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get payments
$sql = "SELECT 
            op.*,
            o.customer_name,
            o.customer_email,
            o.total_amount as order_total,
            o.created_at as order_date
        FROM order_payments op
        LEFT JOIN orders o ON op.order_id = o.id
        $where_clause
        ORDER BY op.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$payments = [];
while ($row = $result->fetch_assoc()) {
    if ($row['validation_data']) {
        $row['validation_data'] = json_decode($row['validation_data'], true);
    }
    $payments[] = $row;
}

// Get payment method options
$methods_sql = "SELECT DISTINCT payment_method FROM order_payments ORDER BY payment_method";
$methods_result = $conn->query($methods_sql);
$payment_methods = [];
while ($row = $methods_result->fetch_assoc()) {
    $payment_methods[] = $row['payment_method'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - FlavorFinder Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .payment-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .payment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .payment-electronic {
            border-left-color: #28a745;
        }
        .payment-cash {
            border-left-color: #ffc107;
        }
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        .validation-data {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 8px;
            font-size: 0.9em;
        }
        .filter-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-credit-card me-2"></i><?php echo $page_title; ?></h1>
                    <div>
                        <a href="../orders/" class="btn btn-outline-primary">
                            <i class="fas fa-shopping-cart me-1"></i>Ver Órdenes
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-chart-bar me-1"></i>Estadísticas
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card filter-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-filter me-2"></i>Filtros de Búsqueda
                        </h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">ID Orden</label>
                                <input type="number" class="form-control" name="order_id" value="<?php echo $order_id; ?>" placeholder="Ej: 123">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Método de Pago</label>
                                <select class="form-select" name="payment_method">
                                    <option value="">Todos</option>
                                    <?php foreach ($payment_methods as $method): ?>
                                        <option value="<?php echo htmlspecialchars($method); ?>" <?php echo $payment_method === $method ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($method); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="payment_type">
                                    <option value="">Todos</option>
                                    <option value="electronic" <?php echo $payment_type === 'electronic' ? 'selected' : ''; ?>>Electrónico</option>
                                    <option value="cash" <?php echo $payment_type === 'cash' ? 'selected' : ''; ?>>Efectivo</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="status">
                                    <option value="">Todos</option>
                                    <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Pagado</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="pending_validation" <?php echo $status === 'pending_validation' ? 'selected' : ''; ?>>Pendiente Validación</option>
                                    <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Fallido</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Desde</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Hasta</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-light">
                                    <i class="fas fa-search me-1"></i>Buscar
                                </button>
                                <a href="order_payments.php" class="btn btn-outline-light">
                                    <i class="fas fa-times me-1"></i>Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Stats Summary -->
                <?php if (!empty($payments)): ?>
                    <?php
                    $total_amount = array_sum(array_column($payments, 'amount'));
                    $electronic_count = count(array_filter($payments, function($p) { return $p['payment_type'] === 'electronic'; }));
                    $cash_count = count(array_filter($payments, function($p) { return $p['payment_type'] === 'cash'; }));
                    ?>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <h3>$<?php echo number_format($total_amount, 2); ?></h3>
                                    <p class="mb-0">Total en Pagos</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <h3><?php echo count($payments); ?></h3>
                                    <p class="mb-0">Total Pagos</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <h3><?php echo $electronic_count; ?></h3>
                                    <p class="mb-0">Electrónicos</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <h3><?php echo $cash_count; ?></h3>
                                    <p class="mb-0">Efectivo</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Payments List -->
                <div class="row">
                    <?php if (empty($payments)): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                    <h4>No se encontraron pagos</h4>
                                    <p class="text-muted">Intenta ajustar los filtros de búsqueda</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card payment-card payment-<?php echo $payment['payment_type']; ?> h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <strong>
                                            <i class="fas fa-<?php echo $payment['payment_type'] === 'electronic' ? 'mobile-alt' : 'money-bill-wave'; ?> me-1"></i>
                                            <?php echo htmlspecialchars($payment['payment_method']); ?>
                                        </strong>
                                        <span class="badge status-<?php echo $payment['status']; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <small class="text-muted">Orden #</small>
                                                <div><strong><?php echo $payment['order_id']; ?></strong></div>
                                            </div>
                                            <div class="col-6 text-end">
                                                <small class="text-muted">Monto</small>
                                                <div><strong class="text-success">$<?php echo number_format($payment['amount'], 2); ?></strong></div>
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <small class="text-muted">Cliente:</small>
                                            <div><?php echo htmlspecialchars($payment['customer_name']); ?></div>
                                        </div>

                                        <?php if ($payment['payment_type'] === 'electronic'): ?>
                                            <?php if ($payment['reference']): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">Referencia:</small>
                                                    <div><code><?php echo htmlspecialchars($payment['reference']); ?></code></div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($payment['bank_origin'] || $payment['bank_destination']): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">Bancos:</small>
                                                    <div>
                                                        <?php if ($payment['bank_origin']): ?>
                                                            <span class="badge bg-primary"><?php echo htmlspecialchars($payment['bank_origin']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($payment['bank_destination']): ?>
                                                            <i class="fas fa-arrow-right mx-1"></i>
                                                            <span class="badge bg-success"><?php echo htmlspecialchars($payment['bank_destination']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($payment['phone']): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">Teléfono:</small>
                                                    <div><?php echo htmlspecialchars($payment['phone']); ?></div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($payment['validation_data']): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">Datos de Validación:</small>
                                                    <div class="validation-data">
                                                        <?php if (isset($payment['validation_data']['amount_usd'])): ?>
                                                            <div><strong>Monto USD:</strong> $<?php echo number_format($payment['validation_data']['amount_usd'], 2); ?></div>
                                                        <?php endif; ?>
                                                        <?php if (isset($payment['validation_data']['method_name'])): ?>
                                                            <div><strong>Método:</strong> <?php echo htmlspecialchars($payment['validation_data']['method_name']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($payment['payment_type'] === 'cash' && $payment['change_amount'] > 0): ?>
                                            <div class="mb-2">
                                                <small class="text-muted">Vuelto:</small>
                                                <div><strong class="text-warning">$<?php echo number_format($payment['change_amount'], 2); ?></strong></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?>
                                        </small>
                                        <div class="float-end">
                                            <a href="../orders/view.php?id=<?php echo $payment['order_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Paginación">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>

                    <div class="text-center text-muted">
                        Mostrando <?php echo count($payments); ?> de <?php echo $total_records; ?> pagos
                        (Página <?php echo $page; ?> de <?php echo $total_pages; ?>)
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
