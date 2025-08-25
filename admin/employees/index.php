<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Handle actions
$message = '';
$messageType = 'success';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $id = (int)$_POST['id'];
                $newStatus = $_POST['new_status'];
                
                $validStatuses = ['active', 'inactive', 'suspended', 'terminated'];
                if (in_array($newStatus, $validStatuses)) {
                    $db->update('employees', 
                        ['status' => $newStatus], 
                        'id = ?', 
                        [$id]
                    );
                    
                    $message = 'Estado del empleado actualizado correctamente';
                } else {
                    $message = 'Estado no válido';
                    $messageType = 'danger';
                }
                break;
                
            case 'add_employee':
                try {
                    $employeeData = [
                        'first_name' => $_POST['first_name'],
                        'last_name' => $_POST['last_name'],
                        'email' => $_POST['email'] ?: null,
                        'phone' => $_POST['phone'] ?: null,
                        'address' => $_POST['address'] ?: null,
                        'hire_date' => $_POST['hire_date'],
                        'birth_date' => $_POST['birth_date'] ?: null,
                        'position' => $_POST['position'],
                        'department' => $_POST['department'],
                        'salary' => $_POST['salary'] ?: null,
                        'emergency_contact_name' => $_POST['emergency_contact_name'] ?: null,
                        'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?: null,
                        'notes' => $_POST['notes'] ?: null
                    ];
                    
                    $db->insert('employees', $employeeData);
                    $message = 'Empleado agregado exitosamente';
                } catch (Exception $e) {
                    $message = 'Error al agregar empleado: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'bulk_action':
                $selectedIds = $_POST['selected_ids'] ?? [];
                $bulkAction = $_POST['bulk_action_type'];
                
                if (!empty($selectedIds)) {
                    $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
                    
                    switch ($bulkAction) {
                        case 'activate':
                            $db->query(
                                "UPDATE employees SET status = 'active' WHERE id IN ($placeholders)",
                                $selectedIds
                            );
                            $message = count($selectedIds) . ' empleados activados';
                            break;
                            
                        case 'suspend':
                            $db->query(
                                "UPDATE employees SET status = 'suspended' WHERE id IN ($placeholders)",
                                $selectedIds
                            );
                            $message = count($selectedIds) . ' empleados suspendidos';
                            break;
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$positionFilter = $_GET['position'] ?? '';
$departmentFilter = $_GET['department'] ?? '';
$searchFilter = $_GET['search'] ?? '';

// Build query conditions
$conditions = [];
$params = [];

if ($statusFilter) {
    $conditions[] = "e.status = ?";
    $params[] = $statusFilter;
}

if ($positionFilter) {
    $conditions[] = "e.position = ?";
    $params[] = $positionFilter;
}

if ($departmentFilter) {
    $conditions[] = "e.department = ?";
    $params[] = $departmentFilter;
}

if ($searchFilter) {
    $conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ? OR e.employee_code LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get employees with performance data
$employees = $db->fetchAll(
    "SELECT e.*, 
            DATEDIFF(CURDATE(), e.hire_date) as days_employed,
            (SELECT AVG(ep.overall_score) FROM employee_performance ep WHERE ep.employee_id = e.id) as avg_performance,
            (SELECT COUNT(*) FROM employee_schedules es WHERE es.employee_id = e.id AND es.is_active = 1) as schedule_count
     FROM employees e
     $whereClause
     ORDER BY e.status ASC, e.department ASC, e.last_name ASC",
    $params
);

// Get statistics
$stats = [
    'total_employees' => $db->fetchOne("SELECT COUNT(*) as count FROM employees")['count'] ?? 0,
    'active_employees' => $db->fetchOne("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")['count'] ?? 0,
    'inactive_employees' => $db->fetchOne("SELECT COUNT(*) as count FROM employees WHERE status = 'inactive'")['count'] ?? 0,
    'suspended_employees' => $db->fetchOne("SELECT COUNT(*) as count FROM employees WHERE status = 'suspended'")['count'] ?? 0,
    'delivery_employees' => $db->fetchOne("SELECT COUNT(*) as count FROM employees WHERE position = 'delivery' AND status = 'active'")['count'] ?? 0,
    'avg_salary' => $db->fetchOne("SELECT AVG(salary) as avg FROM employees WHERE status = 'active' AND salary IS NOT NULL")['avg'] ?? 0
];

// Get unique positions and departments for filters
$positions = $db->fetchAll("SELECT DISTINCT position FROM employees ORDER BY position");
$departments = $db->fetchAll("SELECT DISTINCT department FROM employees ORDER BY department");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados - Administración</title>
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
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-user-tie me-2 text-primary"></i>
                            Administración de Empleados
                        </h2>
                        <p class="text-muted mb-0">
                            Gestiona empleados, roles, horarios y seguimiento de rendimiento
                        </p>
                    </div>
                    <div>
                        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                            <i class="fas fa-plus me-2"></i>
                            Nuevo Empleado
                        </button>
                        <a href="schedules.php" class="btn btn-info me-2">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Horarios
                        </a>
                        <a href="performance.php" class="btn btn-warning me-2">
                            <i class="fas fa-chart-line me-2"></i>
                            Rendimiento
                        </a>
                        <button class="btn btn-outline-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>
                            Actualizar
                        </button>
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

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card border-left-primary shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Empleados
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['total_employees'] ?>
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
                <div class="card border-left-success shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Activos
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['active_employees'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-check fa-2x text-gray-300"></i>
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
                                    Suspendidos
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['suspended_employees'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-times fa-2x text-gray-300"></i>
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
                                    Delivery
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['delivery_employees'] ?>
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
                <div class="card border-left-secondary shadow">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                    Inactivos
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['inactive_employees'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-slash fa-2x text-gray-300"></i>
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
                                    Salario Promedio
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?= number_format($stats['avg_salary'], 0) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
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
                            <div class="col-md-2">
                                <label for="status" class="form-label">Estado</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Todos los estados</option>
                                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Activo</option>
                                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                                    <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspendido</option>
                                    <option value="terminated" <?= $statusFilter === 'terminated' ? 'selected' : '' ?>>Terminado</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="position" class="form-label">Posición</label>
                                <select class="form-select" id="position" name="position">
                                    <option value="">Todas las posiciones</option>
                                    <?php foreach ($positions as $pos): ?>
                                        <option value="<?= $pos['position'] ?>" <?= $positionFilter === $pos['position'] ? 'selected' : '' ?>>
                                            <?= ucfirst(str_replace('_', ' ', $pos['position'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="department" class="form-label">Departamento</label>
                                <select class="form-select" id="department" name="department">
                                    <option value="">Todos los departamentos</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['department'] ?>" <?= $departmentFilter === $dept['department'] ? 'selected' : '' ?>>
                                            <?= ucfirst(str_replace('_', ' ', $dept['department'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Buscar</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($searchFilter) ?>" 
                                       placeholder="Nombre, email o código">
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

        <!-- Employees Table -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    Lista de Empleados
                                    <?php if ($statusFilter || $positionFilter || $departmentFilter || $searchFilter): ?>
                                        <small class="text-muted">(Filtrado)</small>
                                        <a href="index.php" class="btn btn-sm btn-outline-secondary ms-2">
                                            <i class="fas fa-times me-1"></i>Limpiar
                                        </a>
                                    <?php endif; ?>
                                </h6>
                            </div>
                            <div class="col-auto">
                                <form method="POST" class="d-inline" id="bulkForm">
                                    <input type="hidden" name="action" value="bulk_action">
                                    <div class="input-group input-group-sm">
                                        <select name="bulk_action_type" class="form-select" required>
                                            <option value="">Acciones masivas</option>
                                            <option value="activate">Activar</option>
                                            <option value="suspend">Suspender</option>
                                        </select>
                                        <button type="submit" class="btn btn-outline-primary">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($employees)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-user-tie fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay empleados registrados</h5>
                                <p class="text-muted">
                                    <?php if ($statusFilter || $positionFilter || $searchFilter): ?>
                                        No se encontraron empleados con los filtros aplicados
                                    <?php else: ?>
                                        Aún no se han registrado empleados
                                    <?php endif; ?>
                                </p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                    <i class="fas fa-plus me-2"></i>Agregar Primer Empleado
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" id="selectAll" class="form-check-input">
                                            </th>
                                            <th>Empleado</th>
                                            <th>Posición</th>
                                            <th>Departamento</th>
                                            <th>Contacto</th>
                                            <th>Antigüedad</th>
                                            <th>Rendimiento</th>
                                            <th>Estado</th>
                                            <th width="150">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $employee): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_ids[]" 
                                                           value="<?= $employee['id'] ?>" 
                                                           class="form-check-input employee-checkbox">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle me-3">
                                                            <?= strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)) ?>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0">
                                                                <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                                                            </h6>
                                                            <small class="text-muted">
                                                                <?= htmlspecialchars($employee['employee_code']) ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?= ucfirst(str_replace('_', ' ', $employee['position'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= ucfirst(str_replace('_', ' ', $employee['department'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <?php if ($employee['email']): ?>
                                                            <small class="d-block">
                                                                <i class="fas fa-envelope me-1"></i>
                                                                <?= htmlspecialchars($employee['email']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if ($employee['phone']): ?>
                                                            <small class="d-block">
                                                                <i class="fas fa-phone me-1"></i>
                                                                <?= htmlspecialchars($employee['phone']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <small class="d-block">
                                                            <?= floor($employee['days_employed'] / 365) ?> años, 
                                                            <?= floor(($employee['days_employed'] % 365) / 30) ?> meses
                                                        </small>
                                                        <small class="text-muted">
                                                            Desde <?= date('d/m/Y', strtotime($employee['hire_date'])) ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($employee['avg_performance']): ?>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar bg-<?= 
                                                                $employee['avg_performance'] >= 8 ? 'success' : 
                                                                ($employee['avg_performance'] >= 6 ? 'warning' : 'danger') 
                                                            ?>" 
                                                                 style="width: <?= ($employee['avg_performance'] / 10) * 100 ?>%">
                                                                <?= number_format($employee['avg_performance'], 1) ?>/10
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <small class="text-muted">Sin evaluaciones</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="id" value="<?= $employee['id'] ?>">
                                                        <select name="new_status" class="form-select form-select-sm" 
                                                                onchange="this.form.submit()" 
                                                                style="width: auto;">
                                                            <option value="active" <?= $employee['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                                                            <option value="inactive" <?= $employee['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                                                            <option value="suspended" <?= $employee['status'] === 'suspended' ? 'selected' : '' ?>>Suspendido</option>
                                                            <option value="terminated" <?= $employee['status'] === 'terminated' ? 'selected' : '' ?>>Terminado</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view.php?id=<?= $employee['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Ver perfil">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit.php?id=<?= $employee['id'] ?>" 
                                                           class="btn btn-sm btn-outline-success" 
                                                           title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="schedules.php?employee_id=<?= $employee['id'] ?>" 
                                                           class="btn btn-sm btn-outline-info" 
                                                           title="Horarios">
                                                            <i class="fas fa-calendar-alt"></i>
                                                        </a>
                                                    </div>
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

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Agregar Nuevo Empleado
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_employee">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Apellido *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" id="phone" name="phone">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Dirección</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="hire_date" class="form-label">Fecha de Contratación *</label>
                                    <input type="date" class="form-control" id="hire_date" name="hire_date" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="birth_date" class="form-label">Fecha de Nacimiento</label>
                                    <input type="date" class="form-control" id="birth_date" name="birth_date">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="salary" class="form-label">Salario</label>
                                    <input type="number" class="form-control" id="salary" name="salary" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="position" class="form-label">Posición *</label>
                                    <select class="form-select" id="position" name="position" required>
                                        <option value="">Seleccionar posición...</option>
                                        <option value="manager">Gerente</option>
                                        <option value="chef">Chef</option>
                                        <option value="waiter">Mesero</option>
                                        <option value="cashier">Cajero</option>
                                        <option value="delivery">Repartidor</option>
                                        <option value="kitchen_assistant">Asistente de Cocina</option>
                                        <option value="cleaner">Personal de Limpieza</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department" class="form-label">Departamento *</label>
                                    <select class="form-select" id="department" name="department" required>
                                        <option value="">Seleccionar departamento...</option>
                                        <option value="kitchen">Cocina</option>
                                        <option value="service">Servicio</option>
                                        <option value="delivery">Delivery</option>
                                        <option value="administration">Administración</option>
                                        <option value="cleaning">Limpieza</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emergency_contact_name" class="form-label">Contacto de Emergencia</label>
                                    <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" placeholder="Nombre completo">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emergency_contact_phone" class="form-label">Teléfono de Emergencia</label>
                                    <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Notas adicionales sobre el empleado..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Agregar Empleado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.employee-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all when individual checkboxes change
        document.querySelectorAll('.employee-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.employee-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.employee-checkbox:checked');
                const selectAll = document.getElementById('selectAll');
                
                selectAll.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAll.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            });
        });

        // Bulk actions form submission
        document.getElementById('bulkForm').addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.employee-checkbox:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Por favor selecciona al menos un empleado');
                return;
            }
            
            // Add selected IDs to form
            checkedBoxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_ids[]';
                input.value = checkbox.value;
                this.appendChild(input);
            });
        });

        // Set default hire date to today
        document.getElementById('hire_date').value = new Date().toISOString().split('T')[0];
    </script>

    <style>
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
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
        
        .border-left-secondary {
            border-left: 4px solid #6c757d !important;
        }
    </style>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
