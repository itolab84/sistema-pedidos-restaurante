<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Get filter parameters
$employeeFilter = $_GET['employee'] ?? '';
$dateFromFilter = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$dateToFilter = $_GET['date_to'] ?? date('Y-m-d'); // Today
$routeFilter = $_GET['route'] ?? '';

// Build query conditions
$conditions = ['da.delivery_status = "delivered"']; // Only completed deliveries
$params = [];

if ($employeeFilter) {
    $conditions[] = "da.delivery_employee_id = ?";
    $params[] = $employeeFilter;
}

if ($routeFilter) {
    $conditions[] = "da.route_id = ?";
    $params[] = $routeFilter;
}

if ($dateFromFilter) {
    $conditions[] = "DATE(da.delivery_time) >= ?";
    $params[] = $dateFromFilter;
}

if ($dateToFilter) {
    $conditions[] = "DATE(da.delivery_time) <= ?";
    $params[] = $dateToFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

// Get delivery performance data
$performanceData = $db->fetchAll(
    "SELECT 
        e.id as employee_id,
        CONCAT(e.first_name, ' ', e.last_name) as delivery_person,
        e.phone as employee_phone,
        COUNT(da.id) as total_deliveries,
        AVG(TIMESTAMPDIFF(MINUTE, da.pickup_time, da.delivery_time)) as avg_delivery_time,
        MIN(TIMESTAMPDIFF(MINUTE, da.pickup_time, da.delivery_time)) as min_delivery_time,
        MAX(TIMESTAMPDIFF(MINUTE, da.pickup_time, da.delivery_time)) as max_delivery_time,
        SUM(da.delivery_fee) as total_delivery_fees,
        SUM(da.tip_amount) as total_tips,
        COUNT(DISTINCT DATE(da.delivery_time)) as active_days,
        COUNT(DISTINCT da.route_id) as routes_covered
     FROM delivery_assignments da
     INNER JOIN employees e ON da.delivery_employee_id = e.id
     $whereClause
     GROUP BY e.id, e.first_name, e.last_name, e.phone
     ORDER BY total_deliveries DESC",
    $params
);

// Get daily performance for chart
$dailyPerformance = $db->fetchAll(
    "SELECT 
        DATE(da.delivery_time) as delivery_date,
        COUNT(da.id) as daily_deliveries,
        AVG(TIMESTAMPDIFF(MINUTE, da.pickup_time, da.delivery_time)) as daily_avg_time,
        SUM(da.delivery_fee + da.tip_amount) as daily_earnings
     FROM delivery_assignments da
     INNER JOIN employees e ON da.delivery_employee_id = e.id
     $whereClause
     GROUP BY DATE(da.delivery_time)
     ORDER BY delivery_date ASC",
    $params
);

// Get route performance
$routePerformance = $db->fetchAll(
    "SELECT 
        dr.route_name,
        dr.route_code,
        dr.estimated_time_minutes,
        COUNT(da.id) as total_deliveries,
        AVG(TIMESTAMPDIFF(MINUTE, da.pickup_time, da.delivery_time)) as avg_actual_time,
        (dr.estimated_time_minutes - AVG(TIMESTAMPDIFF(MINUTE, da.pickup_time, da.delivery_time))) as time_difference
     FROM delivery_assignments da
     INNER JOIN delivery_routes dr ON da.route_id = dr.id
     INNER JOIN employees e ON da.delivery_employee_id = e.id
     $whereClause AND da.route_id IS NOT NULL
     GROUP BY dr.id, dr.route_name, dr.route_code, dr.estimated_time_minutes
     ORDER BY total_deliveries DESC",
    $params
);

// Calculate overall statistics
$overallStats = [
    'total_deliveries' => array_sum(array_column($performanceData, 'total_deliveries')),
    'avg_delivery_time' => count($performanceData) > 0 ? 
        array_sum(array_column($performanceData, 'avg_delivery_time')) / count($performanceData) : 0,
    'total_earnings' => array_sum(array_column($performanceData, 'total_delivery_fees')) + 
                      array_sum(array_column($performanceData, 'total_tips')),
    'active_employees' => count($performanceData),
    'best_performer' => count($performanceData) > 0 ? $performanceData[0]['delivery_person'] : 'N/A',
    'total_tips' => array_sum(array_column($performanceData, 'total_tips'))
];

// Get delivery employees for filter
$deliveryEmployees = $db->fetchAll(
    "SELECT id, CONCAT(first_name, ' ', last_name) as full_name 
     FROM employees 
     WHERE position = 'delivery' AND status = 'active' 
     ORDER BY first_name, last_name"
);

// Get routes for filter
$routes = $db->fetchAll("SELECT * FROM delivery_routes WHERE is_active = 1 ORDER BY route_name");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rendimiento de Delivery - Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-chart-line me-2 text-primary"></i>
                            Rendimiento de Delivery
                        </h2>
                        <p class="text-muted mb-0">
                            Analiza el rendimiento de repartidores y optimiza las operaciones de entrega
                        </p>
                    </div>
                    <div>
                        <button class="btn btn-success me-2" onclick="exportReport()">
                            <i class="fas fa-download me-2"></i>
                            Exportar Reporte
                        </button>
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver a Deliveries
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="employee" class="form-label">Repartidor</label>
                                <select class="form-select" id="employee" name="employee">
                                    <option value="">Todos los repartidores</option>
                                    <?php foreach ($deliveryEmployees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" <?= $employeeFilter == $emp['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($emp['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="route" class="form-label">Ruta</label>
                                <select class="form-select" id="route" name="route">
                                    <option value="">Todas las rutas</option>
                                    <?php foreach ($routes as $route): ?>
                                        <option value="<?= $route['id'] ?>" <?= $routeFilter == $route['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($route['route_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Desde</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?= htmlspecialchars($dateFromFilter) ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Hasta</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?= htmlspecialchars($dateToFilter) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filtrar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card border-left-primary shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Entregas
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $overallStats['total_deliveries'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-truck fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-left-info shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Tiempo Promedio
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= round($overallStats['avg_delivery_time']) ?> min
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-left-success shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Ingresos Totales
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?= number_format($overallStats['total_earnings'], 2) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-left-warning shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Repartidores Activos
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $overallStats['active_employees'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-left-secondary shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                    Total Propinas
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?= number_format($overallStats['total_tips'], 2) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-left-primary shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Mejor Repartidor
                                </div>
                                <div class="h6 mb-0 font-weight-bold text-gray-800" style="font-size: 0.8rem;">
                                    <?= htmlspecialchars($overallStats['best_performer']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-trophy fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-line me-2"></i>Rendimiento Diario
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyPerformanceChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie me-2"></i>Distribución por Repartidor
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="employeeDistributionChart" width="400" height="400"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Tables -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-user-tie me-2"></i>Rendimiento por Repartidor
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($performanceData)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-line fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay datos de rendimiento</h5>
                                <p class="text-muted">No se encontraron entregas completadas en el período seleccionado</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Repartidor</th>
                                            <th>Entregas</th>
                                            <th>Tiempo Promedio</th>
                                            <th>Mejor Tiempo</th>
                                            <th>Peor Tiempo</th>
                                            <th>Ingresos</th>
                                            <th>Propinas</th>
                                            <th>Días Activos</th>
                                            <th>Eficiencia</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($performanceData as $index => $employee): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="rank-badge me-2">
                                                            <?php if ($index == 0): ?>
                                                                <i class="fas fa-trophy text-warning"></i>
                                                            <?php elseif ($index == 1): ?>
                                                                <i class="fas fa-medal text-secondary"></i>
                                                            <?php elseif ($index == 2): ?>
                                                                <i class="fas fa-award text-warning"></i>
                                                            <?php else: ?>
                                                                <span class="badge bg-light text-dark"><?= $index + 1 ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars($employee['delivery_person']) ?></strong>
                                                            <?php if ($employee['employee_phone']): ?>
                                                                <br><small class="text-muted"><?= htmlspecialchars($employee['employee_phone']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary fs-6"><?= $employee['total_deliveries'] ?></span>
                                                </td>
                                                <td>
                                                    <span class="fw-bold"><?= round($employee['avg_delivery_time']) ?> min</span>
                                                </td>
                                                <td>
                                                    <span class="text-success"><?= round($employee['min_delivery_time']) ?> min</span>
                                                </td>
                                                <td>
                                                    <span class="text-danger"><?= round($employee['max_delivery_time']) ?> min</span>
                                                </td>
                                                <td>
                                                    <span class="text-success fw-bold">$<?= number_format($employee['total_delivery_fees'], 2) ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-info fw-bold">$<?= number_format($employee['total_tips'], 2) ?></span>
                                                </td>
                                                <td>
                                                    <?= $employee['active_days'] ?> días
                                                </td>
                                                <td>
                                                    <?php 
                                                    $deliveriesPerDay = $employee['active_days'] > 0 ? $employee['total_deliveries'] / $employee['active_days'] : 0;
                                                    $efficiencyClass = $deliveriesPerDay >= 10 ? 'success' : ($deliveriesPerDay >= 5 ? 'warning' : 'danger');
                                                    ?>
                                                    <span class="badge bg-<?= $efficiencyClass ?>">
                                                        <?= number_format($deliveriesPerDay, 1) ?>/día
                                                    </span>
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
            
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-route me-2"></i>Rendimiento por Ruta
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($routePerformance)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-route fa-2x text-gray-300 mb-3"></i>
                                <p class="text-muted">No hay datos de rutas</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Ruta</th>
                                            <th>Entregas</th>
                                            <th>Tiempo</th>
                                            <th>Eficiencia</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($routePerformance as $route): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($route['route_code']) ?></strong>
                                                        <br><small class="text-muted"><?= htmlspecialchars($route['route_name']) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= $route['total_deliveries'] ?></span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <small>Est: <?= $route['estimated_time_minutes'] ?>m</small>
                                                        <br><small>Real: <?= round($route['avg_actual_time']) ?>m</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $efficiency = $route['time_difference'];
                                                    $efficiencyClass = $efficiency > 0 ? 'success' : 'warning';
                                                    $efficiencyIcon = $efficiency > 0 ? 'arrow-up' : 'arrow-down';
                                                    ?>
                                                    <span class="text-<?= $efficiencyClass ?>">
                                                        <i class="fas fa-<?= $efficiencyIcon ?>"></i>
                                                        <?= abs(round($efficiency)) ?>m
                                                    </span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Daily Performance Chart
        const dailyCtx = document.getElementById('dailyPerformanceChart').getContext('2d');
        const dailyData = <?= json_encode($dailyPerformance) ?>;
        
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyData.map(d => new Date(d.delivery_date).toLocaleDateString('es-ES')),
                datasets: [{
                    label: 'Entregas por Día',
                    data: dailyData.map(d => d.daily_deliveries),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y'
                }, {
                    label: 'Tiempo Promedio (min)',
                    data: dailyData.map(d => Math.round(d.daily_avg_time)),
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Fecha'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Número de Entregas'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Tiempo Promedio (min)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Employee Distribution Chart
        const employeeCtx = document.getElementById('employeeDistributionChart').getContext('2d');
        const employeeData = <?= json_encode($performanceData) ?>;
        
        new Chart(employeeCtx, {
            type: 'doughnut',
            data: {
                labels: employeeData.map(e => e.delivery_person),
                datasets: [{
                    data: employeeData.map(e => e.total_deliveries),
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40',
                        '#FF6384',
                        '#C9CBCF'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    title: {
                        display: true,
                        text: 'Entregas por Repartidor'
                    }
                }
            }
        });

        // Export report function
        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            
            // Create a temporary link to download
            const link = document.createElement('a');
            link.href = 'export_performance.php?' + params.toString();
            link.download = 'rendimiento_delivery_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>

    <style>
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
        
        .border-left-secondary {
            border-left: 4px solid #6c757d !important;
        }
        
        .rank-badge {
            width: 30px;
            text-align: center;
        }
    </style>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
