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

// Handle form submission
$message = '';
$messageType = 'success';

if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] === 'update_employee') {
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
                'status' => $_POST['status'],
                'emergency_contact_name' => $_POST['emergency_contact_name'] ?: null,
                'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?: null,
                'notes' => $_POST['notes'] ?: null
            ];
            
            $db->update('employees', $employeeData, 'id = ?', [$employeeId]);
            $message = 'Empleado actualizado exitosamente';
            
            // Refresh employee data
            $employee = $db->fetchOne(
                "SELECT * FROM employees WHERE id = ?",
                [$employeeId]
            );
            
        } catch (Exception $e) {
            $message = 'Error al actualizar empleado: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Calculate employee statistics
$employeeStats = [
    'days_employed' => $db->fetchOne("SELECT DATEDIFF(CURDATE(), hire_date) as days FROM employees WHERE id = ?", [$employeeId])['days'] ?? 0,
    'schedules_count' => $db->fetchOne("SELECT COUNT(*) as count FROM employee_schedules WHERE employee_id = ? AND is_active = 1", [$employeeId])['count'] ?? 0,
    'performance_count' => $db->fetchOne("SELECT COUNT(*) as count FROM employee_performance WHERE employee_id = ?", [$employeeId])['count'] ?? 0,
    'avg_performance' => $db->fetchOne("SELECT AVG(overall_score) as avg FROM employee_performance WHERE employee_id = ?", [$employeeId])['avg'] ?? null
];

if ($employee['position'] === 'delivery') {
    $employeeStats['delivery_count'] = $db->fetchOne("SELECT COUNT(*) as count FROM delivery_assignments WHERE delivery_employee_id = ?", [$employeeId])['count'] ?? 0;
    $employeeStats['successful_deliveries'] = $db->fetchOne("SELECT COUNT(*) as count FROM delivery_assignments WHERE delivery_employee_id = ? AND delivery_status = 'delivered'", [$employeeId])['count'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Empleado - Administración</title>
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
                            <i class="fas fa-edit me-2 text-primary"></i>
                            Editar Empleado
                        </h2>
                        <p class="text-muted mb-0">
                            Actualiza la información de <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                        </p>
                    </div>
                    <div>
                        <a href="view.php?id=<?= $employee['id'] ?>" class="btn btn-info me-2">
                            <i class="fas fa-eye me-2"></i>
                            Ver Perfil
                        </a>
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver a Empleados
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

        <!-- Employee Stats -->
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
                                    Rendimiento Promedio
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
            
            <?php if ($employee['position'] === 'delivery'): ?>
                <div class="col-md-3">
                    <div class="card border-left-warning shadow">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Deliveries Exitosos
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $employeeStats['successful_deliveries'] ?>/<?= $employeeStats['delivery_count'] ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-truck fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="col-md-3">
                    <div class="card border-left-secondary shadow">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
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
            <?php endif; ?>
        </div>

        <!-- Edit Form -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-user-edit me-2"></i>Información del Empleado
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_employee">
                            
                            <!-- Personal Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="text-primary border-bottom pb-2">
                                        <i class="fas fa-user me-2"></i>Información Personal
                                    </h5>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">Nombre *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?= htmlspecialchars($employee['first_name']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Apellido *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?= htmlspecialchars($employee['last_name']) ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= htmlspecialchars($employee['email'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?= htmlspecialchars($employee['phone'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Dirección</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($employee['address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="birth_date" class="form-label">Fecha de Nacimiento</label>
                                        <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                               value="<?= $employee['birth_date'] ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Employment Information -->
                            <div class="row mb-4 mt-4">
                                <div class="col-12">
                                    <h5 class="text-primary border-bottom pb-2">
                                        <i class="fas fa-briefcase me-2"></i>Información Laboral
                                    </h5>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="hire_date" class="form-label">Fecha de Contratación *</label>
                                        <input type="date" class="form-control" id="hire_date" name="hire_date" 
                                               value="<?= $employee['hire_date'] ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="salary" class="form-label">Salario</label>
                                        <input type="number" class="form-control" id="salary" name="salary" 
                                               step="0.01" min="0" value="<?= $employee['salary'] ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Estado *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active" <?= $employee['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                                            <option value="inactive" <?= $employee['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                                            <option value="suspended" <?= $employee['status'] === 'suspended' ? 'selected' : '' ?>>Suspendido</option>
                                            <option value="terminated" <?= $employee['status'] === 'terminated' ? 'selected' : '' ?>>Terminado</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="position" class="form-label">Posición *</label>
                                        <select class="form-select" id="position" name="position" required>
                                            <option value="manager" <?= $employee['position'] === 'manager' ? 'selected' : '' ?>>Gerente</option>
                                            <option value="chef" <?= $employee['position'] === 'chef' ? 'selected' : '' ?>>Chef</option>
                                            <option value="waiter" <?= $employee['position'] === 'waiter' ? 'selected' : '' ?>>Mesero</option>
                                            <option value="cashier" <?= $employee['position'] === 'cashier' ? 'selected' : '' ?>>Cajero</option>
                                            <option value="delivery" <?= $employee['position'] === 'delivery' ? 'selected' : '' ?>>Repartidor</option>
                                            <option value="kitchen_assistant" <?= $employee['position'] === 'kitchen_assistant' ? 'selected' : '' ?>>Asistente de Cocina</option>
                                            <option value="cleaner" <?= $employee['position'] === 'cleaner' ? 'selected' : '' ?>>Personal de Limpieza</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="department" class="form-label">Departamento *</label>
                                        <select class="form-select" id="department" name="department" required>
                                            <option value="kitchen" <?= $employee['department'] === 'kitchen' ? 'selected' : '' ?>>Cocina</option>
                                            <option value="service" <?= $employee['department'] === 'service' ? 'selected' : '' ?>>Servicio</option>
                                            <option value="delivery" <?= $employee['department'] === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                                            <option value="administration" <?= $employee['department'] === 'administration' ? 'selected' : '' ?>>Administración</option>
                                            <option value="cleaning" <?= $employee['department'] === 'cleaning' ? 'selected' : '' ?>>Limpieza</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Emergency Contact -->
                            <div class="row mb-4 mt-4">
                                <div class="col-12">
                                    <h5 class="text-primary border-bottom pb-2">
                                        <i class="fas fa-phone me-2"></i>Contacto de Emergencia
                                    </h5>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="emergency_contact_name" class="form-label">Nombre del Contacto</label>
                                        <input type="text" class="form-control" id="emergency_contact_name" 
                                               name="emergency_contact_name" 
                                               value="<?= htmlspecialchars($employee['emergency_contact_name'] ?? '') ?>"
                                               placeholder="Nombre completo">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="emergency_contact_phone" class="form-label">Teléfono de Emergencia</label>
                                        <input type="tel" class="form-control" id="emergency_contact_phone" 
                                               name="emergency_contact_phone" 
                                               value="<?= htmlspecialchars($employee['emergency_contact_phone'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notas</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="Notas adicionales sobre el empleado..."><?= htmlspecialchars($employee['notes'] ?? '') ?></textarea>
                            </div>

                            <!-- Employee Code Display -->
                            <div class="row mb-4 mt-4">
                                <div class="col-12">
                                    <h5 class="text-primary border-bottom pb-2">
                                        <i class="fas fa-id-card me-2"></i>Información del Sistema
                                    </h5>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Código de Empleado</label>
                                        <input type="text" class="form-control" 
                                               value="<?= htmlspecialchars($employee['employee_code']) ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Fecha de Registro</label>
                                        <input type="text" class="form-control" 
                                               value="<?= date('d/m/Y H:i', strtotime($employee['created_at'])) ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Última Actualización</label>
                                        <input type="text" class="form-control" 
                                               value="<?= date('d/m/Y H:i', strtotime($employee['updated_at'])) ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <a href="index.php" class="btn btn-secondary">
                                                <i class="fas fa-times me-2"></i>Cancelar
                                            </a>
                                        </div>
                                        <div>
                                            <a href="schedules.php?employee_id=<?= $employee['id'] ?>" class="btn btn-info me-2">
                                                <i class="fas fa-calendar-alt me-2"></i>Gestionar Horarios
                                            </a>
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-save me-2"></i>Actualizar Empleado
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-update department based on position
        document.getElementById('position').addEventListener('change', function() {
            const position = this.value;
            const departmentSelect = document.getElementById('department');
            
            // Suggest department based on position
            const positionDepartmentMap = {
                'manager': 'administration',
                'chef': 'kitchen',
                'waiter': 'service',
                'cashier': 'service',
                'delivery': 'delivery',
                'kitchen_assistant': 'kitchen',
                'cleaner': 'cleaning'
            };
            
            if (positionDepartmentMap[position]) {
                departmentSelect.value = positionDepartmentMap[position];
            }
        });

        // Validate dates
        document.getElementById('birth_date').addEventListener('change', function() {
            const birthDate = new Date(this.value);
            const today = new Date();
            const age = today.getFullYear() - birthDate.getFullYear();
            
            if (age < 16 || age > 80) {
                alert('La edad del empleado debe estar entre 16 y 80 años');
                this.value = '';
            }
        });

        document.getElementById('hire_date').addEventListener('change', function() {
            const hireDate = new Date(this.value);
            const today = new Date();
            
            if (hireDate > today) {
                alert('La fecha de contratación no puede ser futura');
                this.value = '';
            }
        });
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
    </style>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
