<?php
require_once '../config/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireLogin();
$user = $auth->getCurrentUser();

$pageTitle = 'Métodos de Pagos - FlavorFinder Admin';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_company_method':
                $payment_method_id = intval($_POST['payment_method_id']);
                $bank_id = !empty($_POST['bank_id']) ? intval($_POST['bank_id']) : null;
                $account_number = trim($_POST['account_number']);
                $pagomovil_number = trim($_POST['pagomovil_number']);
                $account_holder_name = trim($_POST['account_holder_name']);
                $account_holder_id = trim($_POST['account_holder_id']);
                $notes = trim($_POST['notes']);
                $status = $_POST['status'];
                
                $stmt = $conn->prepare("INSERT INTO payment_methods_company (payment_method_id, bank_id, account_number, pagomovil_number, account_holder_name, account_holder_id, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iissssss", $payment_method_id, $bank_id, $account_number, $pagomovil_number, $account_holder_name, $account_holder_id, $notes, $status);
                
                if ($stmt->execute()) {
                    $success_message = "Configuración de método de pago agregada exitosamente";
                } else {
                    $error_message = "Error al agregar la configuración del método de pago";
                }
                break;
                
            case 'update_company_method':
                $id = intval($_POST['id']);
                $bank_id = !empty($_POST['bank_id']) ? intval($_POST['bank_id']) : null;
                $account_number = trim($_POST['account_number']);
                $pagomovil_number = trim($_POST['pagomovil_number']);
                $account_holder_name = trim($_POST['account_holder_name']);
                $account_holder_id = trim($_POST['account_holder_id']);
                $notes = trim($_POST['notes']);
                $status = $_POST['status'];
                
                $stmt = $conn->prepare("UPDATE payment_methods_company SET bank_id = ?, account_number = ?, pagomovil_number = ?, account_holder_name = ?, account_holder_id = ?, notes = ?, status = ? WHERE id = ?");
                $stmt->bind_param("issssssi", $bank_id, $account_number, $pagomovil_number, $account_holder_name, $account_holder_id, $notes, $status, $id);
                
                if ($stmt->execute()) {
                    $success_message = "Configuración actualizada exitosamente";
                } else {
                    $error_message = "Error al actualizar la configuración";
                }
                break;
                
            case 'toggle_status':
                $id = intval($_POST['id']);
                $new_status = $_POST['status'] === 'active' ? 'inactive' : 'active';
                
                $stmt = $conn->prepare("UPDATE payment_methods_company SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $id);
                
                if ($stmt->execute()) {
                    $success_message = "Estado actualizado exitosamente";
                } else {
                    $error_message = "Error al actualizar el estado";
                }
                break;
                
            case 'delete_company_method':
                $id = intval($_POST['id']);
                
                $stmt = $conn->prepare("DELETE FROM payment_methods_company WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $success_message = "Configuración eliminada exitosamente";
                } else {
                    $error_message = "Error al eliminar la configuración";
                }
                break;
        }
    }
}

// Check if the new table structure exists
$conn = getDBConnection();
$table_exists = false;
$needs_restructure = false;

try {
    $result = $conn->query("SHOW TABLES LIKE 'payment_methods_company'");
    $table_exists = $result->num_rows > 0;
    
    if (!$table_exists) {
        $needs_restructure = true;
    }
} catch (Exception $e) {
    $needs_restructure = true;
}

$company_methods = [];
$payment_methods = [];
$banks = [];

if (!$needs_restructure) {
    // Get payment methods with company configurations
    $query = "
        SELECT 
            pmc.id as config_id,
            pmc.payment_method_id,
            pmc.bank_id,
            pmc.account_number,
            pmc.pagomovil_number,
            pmc.account_holder_name,
            pmc.account_holder_id,
            pmc.notes,
            pmc.status as config_status,
            pmc.created_at,
            pm.name as method_name,
            b.name as bank_name,
            b.code as bank_code
        FROM payment_methods_company pmc
        INNER JOIN payment_methods pm ON pmc.payment_method_id = pm.id
        LEFT JOIN banks b ON pmc.bank_id = b.id
        ORDER BY pm.name, b.name
    ";
    $result = $conn->query($query);
    $company_methods = $result->fetch_all(MYSQLI_ASSOC);

    // Get available payment methods for the form
    $payment_methods = $conn->query("SELECT * FROM payment_methods WHERE status = 'active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

    // Get available banks for the form
    $banks = $conn->query("SELECT * FROM banks WHERE `show` = 1 AND `work` = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-money-check-alt me-2"></i>Métodos de Pagos
            </h1>
            <p class="text-muted">Gestiona las configuraciones bancarias de los métodos de pago</p>
        </div>
        <button class="btn btn-primary" onclick="showAddModal()">
            <i class="fas fa-plus me-2"></i>Agregar Configuración
        </button>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($needs_restructure): ?>
        <!-- Installation Required Message -->
        <div class="card shadow border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Reestructuración Requerida
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h6><i class="fas fa-info-circle me-2"></i>Sistema de Métodos de Pago No Configurado</h6>
                    <p class="mb-3">
                        Para usar esta funcionalidad, necesitas ejecutar la reestructuración de la base de datos 
                        que separará los métodos de pago de la información bancaria específica.
                    </p>
                    
                    <h6>¿Qué hace la reestructuración?</h6>
                    <ul class="mb-3">
                        <li>Modifica la tabla <code>payment_methods</code> para contener solo tipos de métodos</li>
                        <li>Crea la tabla <code>payment_methods_company</code> para información bancaria</li>
                        <li>Permite múltiples configuraciones por método de pago</li>
                        <li>Integra con la tabla <code>bank</code> existente</li>
                    </ul>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <a href="../../fix_payment_methods_structure.php" class="btn btn-warning">
                            <i class="fas fa-database me-2"></i>Ejecutar Reestructuración
                        </a>
                        <a href="../index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Dashboard
                        </a>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <h6><i class="fas fa-before me-2 text-danger"></i>Estructura Actual</h6>
                        <div class="card border-danger">
                            <div class="card-body">
                                <code>payment_methods</code>
                                <ul class="list-unstyled mt-2 small">
                                    <li>• id</li>
                                    <li>• name</li>
                                    <li>• account_number ❌</li>
                                    <li>• pagomovil_number ❌</li>
                                    <li>• status</li>
                                </ul>
                                <small class="text-muted">Información bancaria mezclada</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-after me-2 text-success"></i>Nueva Estructura</h6>
                        <div class="card border-success">
                            <div class="card-body">
                                <code>payment_methods</code> + <code>payment_methods_company</code>
                                <ul class="list-unstyled mt-2 small">
                                    <li>• Métodos separados de configuraciones</li>
                                    <li>• Múltiples bancos por método</li>
                                    <li>• Integración con tabla <code>bank</code></li>
                                    <li>• Información bancaria completa</li>
                                </ul>
                                <small class="text-success">Estructura optimizada</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Normal Payment Methods Interface -->
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Configuraciones de Métodos de Pago
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($company_methods)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-credit-card fa-3x text-gray-300 mb-3"></i>
                        <h5 class="text-gray-600">No hay configuraciones de métodos de pago</h5>
                        <p class="text-muted">Comienza agregando tu primera configuración bancaria</p>
                        <button class="btn btn-primary" onclick="showAddModal()">
                            <i class="fas fa-plus me-2"></i>Agregar Primera Configuración
                        </button>
                    </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Método de Pago</th>
                                <th>Banco</th>
                                <th>Número de Cuenta</th>
                                <th>Pago Móvil</th>
                                <th>Titular</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($company_methods as $method): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($method['method_name']) ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($method['bank_name']): ?>
                                            <div>
                                                <strong><?= htmlspecialchars($method['bank_name']) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars($method['bank_code']) ?></small>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">No requiere banco</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $method['account_number'] ? htmlspecialchars($method['account_number']) : '<span class="text-muted">N/A</span>' ?>
                                    </td>
                                    <td>
                                        <?= $method['pagomovil_number'] ? htmlspecialchars($method['pagomovil_number']) : '<span class="text-muted">N/A</span>' ?>
                                    </td>
                                    <td>
                                        <?php if ($method['account_holder_name']): ?>
                                            <div>
                                                <strong><?= htmlspecialchars($method['account_holder_name']) ?></strong>
                                                <?php if ($method['account_holder_id']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($method['account_holder_id']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $method['config_status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $method['config_status'] === 'active' ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-primary" onclick="editMethod(<?= $method['config_id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= $method['config_id'] ?>">
                                                <input type="hidden" name="status" value="<?= $method['config_status'] ?>">
                                                <button type="submit" class="btn btn-sm <?= $method['config_status'] === 'active' ? 'btn-warning' : 'btn-success' ?>">
                                                    <i class="fas <?= $method['config_status'] === 'active' ? 'fa-pause' : 'fa-play' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar esta configuración?')">
                                                <input type="hidden" name="action" value="delete_company_method">
                                                <input type="hidden" name="id" value="<?= $method['config_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
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
    <?php endif; ?>

    <!-- Add/Edit Method Modal -->
    <div class="modal fade" id="methodModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Agregar Configuración</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="methodForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="form_action" value="add_company_method">
                        <input type="hidden" name="id" id="form_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Método de Pago <span class="text-danger">*</span></label>
                                <select class="form-select" name="payment_method_id" id="payment_method_id" required>
                                    <option value="">Seleccionar método</option>
                                    <?php foreach ($payment_methods as $pm): ?>
                                        <option value="<?= $pm['id'] ?>"><?= htmlspecialchars($pm['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Banco</label>
                                <select class="form-select" name="bank_id" id="bank_id">
                                    <option value="">Sin banco específico</option>
                                    <?php foreach ($banks as $bank): ?>
                                        <option value="<?= $bank['id'] ?>"><?= htmlspecialchars($bank['name']) ?> (<?= htmlspecialchars($bank['code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Solo para métodos que requieren banco</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Número de Cuenta</label>
                                <input type="text" class="form-control" name="account_number" id="account_number" 
                                       placeholder="Ej: 0102-1234-56-1234567890">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Número de Pago Móvil</label>
                                <input type="text" class="form-control" name="pagomovil_number" id="pagomovil_number" 
                                       placeholder="Ej: 0414-1234567">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Titular de la Cuenta</label>
                                <input type="text" class="form-control" name="account_holder_name" id="account_holder_name" 
                                       placeholder="Nombre completo del titular">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cédula/RIF del Titular</label>
                                <input type="text" class="form-control" name="account_holder_id" id="account_holder_id" 
                                       placeholder="Ej: V-12345678 o J-12345678-9">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="status" id="status">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notas</label>
                            <textarea class="form-control" name="notes" id="notes" rows="3" 
                                      placeholder="Notas adicionales sobre esta configuración"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const companyMethods = <?= json_encode($company_methods) ?>;

    function showAddModal() {
        document.getElementById('modalTitle').textContent = 'Agregar Configuración';
        document.getElementById('form_action').value = 'add_company_method';
        document.getElementById('methodForm').reset();
        document.getElementById('form_id').value = '';
        new bootstrap.Modal(document.getElementById('methodModal')).show();
    }

    function editMethod(id) {
        const method = companyMethods.find(m => m.config_id == id);
        if (method) {
            document.getElementById('modalTitle').textContent = 'Editar Configuración';
            document.getElementById('form_action').value = 'update_company_method';
            document.getElementById('form_id').value = method.config_id;
            document.getElementById('payment_method_id').value = method.payment_method_id;
            document.getElementById('bank_id').value = method.bank_id || '';
            document.getElementById('account_number').value = method.account_number || '';
            document.getElementById('pagomovil_number').value = method.pagomovil_number || '';
            document.getElementById('account_holder_name').value = method.account_holder_name || '';
            document.getElementById('account_holder_id').value = method.account_holder_id || '';
            document.getElementById('status').value = method.config_status;
            document.getElementById('notes').value = method.notes || '';
            
            new bootstrap.Modal(document.getElementById('methodModal')).show();
        }
    }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
