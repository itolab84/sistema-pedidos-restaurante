<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Get employee ID from URL
$employeeId = $_GET['id'] ?? null;

if (!$employeeId) {
    header('Location: index.php');
    exit;
}

// Get employee data
$employee = $db->fetchOne(
    "SELECT * FROM employees WHERE id = ?",
    [$employeeId]
);

if (!$employee) {
    header('Location: index.php?error=employee_not_found');
    exit;
}

// Get employee schedules
$schedules = $db->fetchAll(
    "SELECT * FROM employee_schedules 
     WHERE employee_id = ? AND is_active = 1 
     ORDER BY FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')",
    [$employeeId]
);

// Get employee performance evaluations
$performances = $db->fetchAll(
    "SELECT ep.*, 
            CONCAT(evaluator.first_name, ' ', evaluator.last_name) as evaluator_name
     FROM employee_performance ep
     LEFT JOIN employees evaluator ON ep.evaluator_id = evaluator.id
     WHERE ep.employee_id = ?
     ORDER BY ep.evaluation_date DESC
     LIMIT 5",
    [$employeeId]
);

// Get delivery statistics if employee is a delivery person
$deliveryStats = null;
if ($employee['position'] === 'delivery') {
    $deliveryStats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_deliveries,
            COUNT(CASE WHEN delivery_status = 'delivered' THEN 1 END) as successful_deliveries,
            COUNT(CASE WHEN delivery_status = 'failed' THEN 1 END) as failed_deliveries,
            AVG(CASE 
                WHEN delivery_time IS NOT NULL AND pickup_time IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, pickup_time, delivery_time) 
                ELSE NULL 
            END) as avg_delivery_time,
            SUM(delivery_fee) as total_delivery_fees,
            SUM(tip_amount) as total_tips,
            COUNT(DISTINCT route_id) as routes_covered
         FROM delivery_assignments 
         WHERE delivery_employee_id = ?",
        [$employeeId]
    );
}

// Calculate employee statistics
$employeeStats = [
    'days_employed' => $db->fetchOne("SELECT DATEDIFF(CURDATE(), hire_date) as days FROM employees WHERE id = ?", [$employeeId])['days'] ?? 0,
    'schedules_count' => count($schedules),
    'performance_count' => count($performances),
    'avg_performance' => $db->fetchOne("SELECT AVG(overall_score) as avg FROM employee_performance WHERE employee_id = ?", [$employeeId])['avg'] ?? null,
    'latest_performance' => $performances[0] ?? null
];

// Days of week translation
$daysTranslation = [
    'monday' => 'Lunes',
    'tuesday' => 'Martes', 
    'wednesday' => 'Miércoles',
    'thursday' => 'Jueves',
    'friday' => 'Viernes',
    'saturday' => 'Sábado',
    'sunday' => 'Domingo'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de Empleado - Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle-large me-3">
                            <?= strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h2 class="mb-1">
                                <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                                <span class="badge bg-<?= $employee['status'] === 'active' ? 'success' : 'secondary' ?> ms-2">
                                    <?= ucfirst($employee['status']) ?>
                                </span>
                            </h2>
                            <p class="text-muted mb-0">
                                <?= ucfirst(str_replace('_', ' ', $employee['position'])) ?> - 
                                <?= ucfirst(str_replace('_', ' ', $employee['department'])) ?>
                            </p>
                            <small class="text-muted">
                                Código: <?= htmlspecialchars($employee['employee_code']) ?>
                            </small>
                        </div>
                    </div>
                    <div>
                        <a href="edit.php?id=<?= $employee['id'] ?>" class="btn btn-primary me-2">
                            <i class="fas fa-edit me-2"></i>
                            Editar
                        </a>
                        <a href="schedules.php?employee_id=<?= $employee['id'] ?>" class="btn btn-info me-2">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Horarios
                        </a>
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-left-primary shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Antigüedad
                                </div>
                                <div class="h6 mb-0 font-weight-bold text-gray-800">
                                    <?= floor($employeeStats['days_employed'] / 365) ?> años, 
                                    <?= floor(($employeeStats['days_employed'] % 365) / 30) ?> meses
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-info shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Horarios Activos
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $employeeStats['schedules_count'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-success shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Rendimiento
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $employeeStats['avg_performance'] ? number_format($employeeStats['avg_performance'], 1) . '/10' : 'N/A' ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-warning shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Evaluaciones
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $employeeStats['performance_count'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-star fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row">
            <!-- Personal Information -->
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-user me-2"></i>Información Personal
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Nombre Completo:</strong></td>
                                <td><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>
                                    <?php if ($employee['email']): ?>
                                        <a href="mailto:<?= htmlspecialchars($employee['email']) ?>">
                                            <?= htmlspecialchars($employee['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No especificado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Teléfono:</strong></td>
                                <td>
                                    <?php if ($employee['phone']): ?>
                                        <a href="tel:<?= htmlspecialchars($employee['phone']) ?>">
                                            <?= htmlspecialchars($employee['phone']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No especificado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Dirección:</strong></td>
                                <td>
                                    <?= $employee['address'] ? htmlspecialchars($employee['address']) : '<span class="text-muted">No especificada</span>' ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Fecha de Nacimiento:</strong></td>
                                <td>
                                    <?php if ($employee['birth_date']): ?>
                                        <?= date('d/m/Y', strtotime($employee['birth_date'])) ?>
                                        <small class="text-muted">
                                            (<?= floor((time() - strtotime($employee['birth_date'])) / (365.25 * 24 * 3600)) ?> años)
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">No especificada</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-phone me-2"></i>Contacto de Emergencia
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if ($employee['emergency_contact_name'] || $employee['emergency_contact_phone']): ?>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Nombre:</strong></td>
                                    <td><?= htmlspecialchars($employee['emergency_contact_name'] ?? 'No especificado') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Teléfono:</strong></td>
                                    <td>
                                        <?php if ($employee['emergency_contact_phone']): ?>
                                            <a href="tel:<?= htmlspecialchars($employee['emergency_contact_phone']) ?>">
                                                <?= htmlspecialchars($employee['emergency_contact_phone']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No especificado</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <p class="text-muted text-center py-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No se ha registrado información de contacto de emergencia
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Employment Information -->
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-briefcase me-2"></i>Información Laboral
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Código de Empleado:</strong></td>
                                <td><code><?= htmlspecialchars($employee['employee_code']) ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>Posición:</strong></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?= ucfirst(str_replace('_', ' ', $employee['position'])) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Departamento:</strong></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= ucfirst(str_replace('_', ' ', $employee['department'])) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Fecha de Contratación:</strong></td>
                                <td><?= date('d/m/Y', strtotime($employee['hire_date'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Salario:</strong></td>
                                <td>
                                    <?= $employee['salary'] ? '$' . number_format($employee['salary'], 2) : '<span class="text-muted">No especificado</span>' ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Estado:</strong></td>
                                <td>
                                    <span class="badge bg-<?= $employee['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($employee['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- System Information -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-cog me-2"></i>Información del Sistema
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Fecha de Registro:</strong></td>
                                <td><?= date('d/m/Y H:i', strtotime($employee['created_at'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Última Actualización:</strong></td>
                                <td><?= date('d/m/Y H:i', strtotime($employee['updated_at'])) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedules -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-calendar-alt me-2"></i>Horarios de Trabajo
                        </h6>
                        <a href="schedules.php?employee_id=<?= $employee['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i>Gestionar Horarios
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($schedules)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-gray-300 mb-3"></i>
                                <h6 class="text-gray-600">No hay horarios registrados</h6>
                                <p class="text-muted">Este empleado no tiene horarios de trabajo asignados</p>
                                <a href="schedules.php?employee_id=<?= $employee['id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Agregar Horarios
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($schedules as $schedule): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card border-left-info">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <?= $daysTranslation[$schedule['day_of_week']] ?>
                                                </h6>
                                                <p class="card-text">
                                                    <i class="fas fa-clock text-success me-1"></i>
                                                    <?= date('H:i', strtotime($schedule['start_time'])) ?>
                                                    -
                                                    <i class="fas fa-clock text-danger me-1"></i>
                                                    <?= date('H:i', strtotime($schedule['end_time'])) ?>
                                                </p>
                                                <small class="text-muted">
                                                    <?php
                                                    $start = strtotime($schedule['start_time']);
                                                    $end = strtotime($schedule['end_time']);
                                                    $duration = $end - $start;
                                                    $hours = floor($duration / 3600);
                                                    $minutes = floor(($duration % 3600) / 60);
                                                    echo "{$hours}h {$minutes}m";
                                                    ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance History -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-line me-2"></i>Historial de Rendimiento
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($performances)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-line fa-3x text-gray-300 mb-3"></i>
                                <h6 class="text-gray-600">No hay evaluaciones de rendimiento</h6>
                                <p class="text-muted">Este empleado no ha sido evaluado aún</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Puntualidad</th>
                                            <th>Calidad</th>
                                            <th>Trabajo en Equipo</th>
                                            <th>Servicio al Cliente</th>
                                            <th>Puntuación General</th>
                                            <th>Evaluador</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($performances as $performance): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($performance['evaluation_date'])) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $performance['punctuality_score'] >= 8 ? 'success' : ($performance['punctuality_score'] >= 6 ? 'warning' : 'danger') ?>">
                                                        <?= $performance['punctuality_score'] ?>/10
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $performance['quality_score'] >= 8 ? 'success' : ($performance['quality_score'] >= 6 ? 'warning' : 'danger') ?>">
                                                        <?= $performance['quality_score'] ?>/10
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $performance['teamwork_score'] >= 8 ? 'success' : ($performance['teamwork_score'] >= 6 ? 'warning' : 'danger') ?>">
                                                        <?= $performance['teamwork_score'] ?>/10
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $performance['customer_service_score'] >= 8 ? 'success' : ($performance['customer_service_score'] >= 6 ? 'warning' : 'danger') ?>">
                                                        <?= $performance['customer_service_score'] ?>/10
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong class="text-<?= $performance['overall_score'] >= 8 ? 'success' : ($performance['overall_score'] >= 6 ? 'warning' : 'danger') ?>">
                                                        <?= number_format($performance['overall_score'], 1) ?>/10
                                                    </strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($performance['evaluator_name'] ?? 'Sistema') ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delivery Statistics (if applicable) -->
        <?php if ($employee['position'] === 'delivery' && $deliveryStats): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-truck me-2"></i>Estadísticas de Delivery
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h4 class="text-primary"><?= $deliveryStats['total_deliveries'] ?></h4>
                                        <small class="text-muted">Total Deliveries</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h4 class="text-success"><?= $deliveryStats['successful_deliveries'] ?></h4>
                                        <small class="text-muted">Exitosos</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h4 class="text-info"><?= round($deliveryStats['avg_delivery_time'] ?? 0) ?> min</h4>
                                        <small class="text-muted">Tiempo Promedio</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h4 class="text-warning">$<?= number_format(($deliveryStats['total_delivery_fees'] ?? 0) + ($deliveryStats['total_tips'] ?? 0), 2) ?></h4>
                                        <small class="text-muted">Ingresos Totales</small>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($deliveryStats['total_deliveries'] > 0): ?>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Tasa de Éxito</h6>
                                        <div class="progress mb-3">
                                            <?php $successRate = ($deliveryStats['successful_deliveries'] / $deliveryStats['total_deliveries']) * 100; ?>
                                            <div class="progress-bar bg-<?= $successRate >= 90 ? 'success' : ($successRate >= 70 ? 'warning' : 'danger') ?>" 
                                                 style="width: <?= $successRate ?>%">
                                                <?= round($successRate, 1) ?>%
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Rutas Cubiertas</h6>
                                        <p class="mb-0">
                                            <span class="badge bg-info fs-6"><?= $deliveryStats['routes_covered'] ?></span>
                                            rutas diferentes
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Notes -->
        <?php if ($employee['notes']): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-sticky-note me-2"></i>Notas
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-0"><?= nl2br(htmlspecialchars($employee['notes'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .avatar-circle-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 24px;
        }
        
        .border-left-primary {
            border-left: 4px solid #007bff !important;
        }
        
        .border-left-success {
            border-left: 4px solid #28a745 !important;
        }
        
        .border-left-info {
            border-left: 4px solid #17a2b8 !important;
        }
        
        .border-left-warning {
            border-left: 4px solid #ffc107 !important;
        }
    </style>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
