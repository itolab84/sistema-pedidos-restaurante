<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

// Get employee ID from URL parameter
$employeeId = $_GET['employee_id'] ?? null;

// Handle actions
$message = '';
$messageType = 'success';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_schedule':
                try {
                    $scheduleData = [
                        'employee_id' => (int)$_POST['employee_id'],
                        'day_of_week' => $_POST['day_of_week'],
                        'start_time' => $_POST['start_time'],
                        'end_time' => $_POST['end_time'],
                        'is_active' => 1
                    ];
                    
                    // Check if schedule already exists for this day
                    $existing = $db->fetchOne(
                        "SELECT id FROM employee_schedules WHERE employee_id = ? AND day_of_week = ? AND is_active = 1",
                        [$scheduleData['employee_id'], $scheduleData['day_of_week']]
                    );
                    
                    if ($existing) {
                        $message = 'Ya existe un horario activo para este día';
                        $messageType = 'warning';
                    } else {
                        $db->insert('employee_schedules', $scheduleData);
                        $message = 'Horario agregado exitosamente';
                    }
                } catch (Exception $e) {
                    $message = 'Error al agregar horario: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'update_schedule':
                try {
                    $id = (int)$_POST['id'];
                    $scheduleData = [
                        'day_of_week' => $_POST['day_of_week'],
                        'start_time' => $_POST['start_time'],
                        'end_time' => $_POST['end_time']
                    ];
                    
                    $db->update('employee_schedules', $scheduleData, 'id = ?', [$id]);
                    $message = 'Horario actualizado exitosamente';
                } catch (Exception $e) {
                    $message = 'Error al actualizar horario: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'toggle_schedule':
                $id = (int)$_POST['id'];
                $currentStatus = (int)$_POST['current_status'];
                $newStatus = $currentStatus ? 0 : 1;
                
                $db->update('employee_schedules', 
                    ['is_active' => $newStatus], 
                    'id = ?', 
                    [$id]
                );
                
                $message = $newStatus ? 'Horario activado' : 'Horario desactivado';
                break;
                
            case 'delete_schedule':
                $id = (int)$_POST['id'];
                $db->delete('employee_schedules', 'id = ?', [$id]);
                $message = 'Horario eliminado exitosamente';
                break;
        }
    }
}

// Get employees for dropdown
$employees = $db->fetchAll(
    "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, position, department 
     FROM employees 
     WHERE status = 'active' 
     ORDER BY first_name, last_name"
);

// Build query for schedules
$conditions = [];
$params = [];

if ($employeeId) {
    $conditions[] = "es.employee_id = ?";
    $params[] = $employeeId;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get schedules with employee info
$schedules = $db->fetchAll(
    "SELECT es.*, 
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            e.position,
            e.department,
            TIMEDIFF(es.end_time, es.start_time) as duration
     FROM employee_schedules es
     INNER JOIN employees e ON es.employee_id = e.id
     $whereClause
     ORDER BY e.first_name, e.last_name, 
              FIELD(es.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')",
    $params
);

// Get selected employee info if specified
$selectedEmployee = null;
if ($employeeId) {
    $selectedEmployee = $db->fetchOne(
        "SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM employees WHERE id = ?",
        [$employeeId]
    );
}

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
    <title>Horarios de Empleados - Administración</title>
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
                            <i class="fas fa-calendar-alt me-2 text-primary"></i>
                            Horarios de Empleados
                            <?php if ($selectedEmployee): ?>
                                - <?= htmlspecialchars($selectedEmployee['full_name']) ?>
                            <?php endif; ?>
                        </h2>
                        <p class="text-muted mb-0">
                            Gestiona los horarios de trabajo de los empleados
                        </p>
                    </div>
                    <div>
                        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                            <i class="fas fa-plus me-2"></i>
                            Nuevo Horario
                        </button>
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

        <!-- Employee Filter -->
        <?php if (!$employeeId): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-6">
                                    <label for="employee_id" class="form-label">Filtrar por Empleado</label>
                                    <select class="form-select" id="employee_id" name="employee_id" onchange="this.form.submit()">
                                        <option value="">Todos los empleados</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?= $emp['id'] ?>" <?= $employeeId == $emp['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($emp['full_name']) ?> - <?= ucfirst($emp['position']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Schedules Table -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-clock me-2"></i>Horarios de Trabajo
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($schedules)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-alt fa-3x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-600">No hay horarios registrados</h5>
                                <p class="text-muted">
                                    <?php if ($selectedEmployee): ?>
                                        No hay horarios para <?= htmlspecialchars($selectedEmployee['full_name']) ?>
                                    <?php else: ?>
                                        Aún no se han registrado horarios de empleados
                                    <?php endif; ?>
                                </p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                                    <i class="fas fa-plus me-2"></i>Agregar Primer Horario
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Empleado</th>
                                            <th>Día</th>
                                            <th>Hora Inicio</th>
                                            <th>Hora Fin</th>
                                            <th>Duración</th>
                                            <th>Estado</th>
                                            <th width="150">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <tr class="<?= !$schedule['is_active'] ? 'table-secondary' : '' ?>">
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($schedule['employee_name']) ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?= ucfirst($schedule['position']) ?> - <?= ucfirst($schedule['department']) ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?= $daysTranslation[$schedule['day_of_week']] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <i class="fas fa-clock text-success me-1"></i>
                                                    <?= date('H:i', strtotime($schedule['start_time'])) ?>
                                                </td>
                                                <td>
                                                    <i class="fas fa-clock text-danger me-1"></i>
                                                    <?= date('H:i', strtotime($schedule['end_time'])) ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Convert time duration to seconds for calculation
                                                    $startTime = strtotime($schedule['start_time']);
                                                    $endTime = strtotime($schedule['end_time']);
                                                    $durationSeconds = $endTime - $startTime;
                                                    
                                                    if ($durationSeconds > 0) {
                                                        $hours = floor($durationSeconds / 3600);
                                                        $minutes = floor(($durationSeconds % 3600) / 60);
                                                    } else {
                                                        $hours = 0;
                                                        $minutes = 0;
                                                    }
                                                    ?>
                                                    <span class="fw-bold">
                                                        <?= $hours ?>h <?= $minutes ?>m
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle_schedule">
                                                        <input type="hidden" name="id" value="<?= $schedule['id'] ?>">
                                                        <input type="hidden" name="current_status" value="<?= $schedule['is_active'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-<?= $schedule['is_active'] ? 'success' : 'secondary' ?>">
                                                            <i class="fas fa-<?= $schedule['is_active'] ? 'check' : 'times' ?> me-1"></i>
                                                            <?= $schedule['is_active'] ? 'Activo' : 'Inactivo' ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="editSchedule(<?= htmlspecialchars(json_encode($schedule)) ?>)"
                                                                title="Editar horario">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('¿Estás seguro de eliminar este horario?')">
                                                            <input type="hidden" name="action" value="delete_schedule">
                                                            <input type="hidden" name="id" value="<?= $schedule['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar horario">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
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

    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Agregar Horario
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_schedule">
                        
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Empleado *</label>
                            <select class="form-select" id="employee_id" name="employee_id" required>
                                <?php if ($selectedEmployee): ?>
                                    <option value="<?= $selectedEmployee['id'] ?>" selected>
                                        <?= htmlspecialchars($selectedEmployee['full_name']) ?>
                                    </option>
                                <?php else: ?>
                                    <option value="">Seleccionar empleado...</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>">
                                            <?= htmlspecialchars($emp['full_name']) ?> - <?= ucfirst($emp['position']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="day_of_week" class="form-label">Día de la Semana *</label>
                            <select class="form-select" id="day_of_week" name="day_of_week" required>
                                <option value="">Seleccionar día...</option>
                                <option value="monday">Lunes</option>
                                <option value="tuesday">Martes</option>
                                <option value="wednesday">Miércoles</option>
                                <option value="thursday">Jueves</option>
                                <option value="friday">Viernes</option>
                                <option value="saturday">Sábado</option>
                                <option value="sunday">Domingo</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_time" class="form-label">Hora de Inicio *</label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_time" class="form-label">Hora de Fin *</label>
                                    <input type="time" class="form-control" id="end_time" name="end_time" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Agregar Horario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Horario
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editScheduleForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_schedule">
                        <input type="hidden" name="id" id="edit_schedule_id">
                        
                        <div class="mb-3">
                            <label for="edit_day_of_week" class="form-label">Día de la Semana *</label>
                            <select class="form-select" id="edit_day_of_week" name="day_of_week" required>
                                <option value="monday">Lunes</option>
                                <option value="tuesday">Martes</option>
                                <option value="wednesday">Miércoles</option>
                                <option value="thursday">Jueves</option>
                                <option value="friday">Viernes</option>
                                <option value="saturday">Sábado</option>
                                <option value="sunday">Domingo</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_start_time" class="form-label">Hora de Inicio *</label>
                                    <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_end_time" class="form-label">Hora de Fin *</label>
                                    <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Actualizar Horario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit schedule function
        function editSchedule(schedule) {
            document.getElementById('edit_schedule_id').value = schedule.id;
            document.getElementById('edit_day_of_week').value = schedule.day_of_week;
            document.getElementById('edit_start_time').value = schedule.start_time;
            document.getElementById('edit_end_time').value = schedule.end_time;
            
            const modal = new bootstrap.Modal(document.getElementById('editScheduleModal'));
            modal.show();
        }

        // Validate time range
        document.getElementById('end_time').addEventListener('change', function() {
            const startTime = document.getElementById('start_time').value;
            const endTime = this.value;
            
            if (startTime && endTime && startTime >= endTime) {
                alert('La hora de fin debe ser posterior a la hora de inicio');
                this.value = '';
            }
        });

        document.getElementById('edit_end_time').addEventListener('change', function() {
            const startTime = document.getElementById('edit_start_time').value;
            const endTime = this.value;
            
            if (startTime && endTime && startTime >= endTime) {
                alert('La hora de fin debe ser posterior a la hora de inicio');
                this.value = '';
            }
        });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
