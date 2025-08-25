<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

$customer_id = (int)($_GET['id'] ?? 0);

if (!$customer_id) {
    header('Location: index.php');
    exit;
}

// Get customer information
$customer = $db->fetchOne(
    "SELECT * FROM customers WHERE id = ? AND status = 'active'",
    [$customer_id]
);

if (!$customer) {
    header('Location: index.php?error=customer_not_found');
    exit;
}

$message = '';
$messageType = 'success';

if ($_POST) {
    try {
        // Validar datos requeridos
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $birth_date = $_POST['birth_date'] ?? null;
        $gender = $_POST['gender'] ?? null;
        $customer_status = $_POST['customer_status'] ?? 'new';
        $notes = trim($_POST['notes'] ?? '');
        $preferences = trim($_POST['preferences'] ?? '');
        
        // Validaciones
        if (empty($first_name)) {
            throw new Exception('El nombre es requerido');
        }
        
        if (empty($last_name)) {
            throw new Exception('El apellido es requerido');
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El email no tiene un formato válido');
        }
        
        // Verificar email único si se proporciona y es diferente al actual
        if (!empty($email) && $email !== $customer['email']) {
            $existing = $db->fetchOne("SELECT id FROM customers WHERE email = ? AND status = 'active' AND id != ?", [$email, $customer_id]);
            if ($existing) {
                throw new Exception('Ya existe otro cliente con este email');
            }
        }
        
        // Actualizar cliente
        $customer_data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email ?: null,
            'birth_date' => $birth_date ?: null,
            'gender' => $gender ?: null,
            'customer_status' => $customer_status,
            'notes' => $notes ?: null,
            'preferences' => $preferences ?: null
        ];
        
        $db->update('customers', $customer_data, 'id = ?', [$customer_id]);
        
        $message = 'Cliente actualizado exitosamente';
        $messageType = 'success';
        
        // Actualizar los datos para mostrar en el formulario
        $customer = array_merge($customer, $customer_data);
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Get customer phones
$phones = $db->fetchAll(
    "SELECT * FROM customer_phones WHERE customer_id = ? ORDER BY is_primary DESC, id ASC",
    [$customer_id]
);

// Get customer addresses
$addresses = $db->fetchAll(
    "SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_primary DESC, id ASC",
    [$customer_id]
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></title>
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
                        <div class="avatar-circle me-3">
                            <?= strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h2 class="mb-1">
                                <i class="fas fa-edit me-2 text-primary"></i>
                                Editar Cliente
                            </h2>
                            <p class="text-muted mb-0">
                                <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>
                                <span class="ms-2">
                                    <i class="fas fa-barcode me-1"></i>
                                    <?= htmlspecialchars($customer['customer_code']) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div>
                        <a href="view.php?id=<?= $customer['id'] ?>" class="btn btn-outline-primary me-2">
                            <i class="fas fa-eye me-2"></i>Ver Perfil
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

        <!-- Form -->
        <form method="POST" action="">
            <div class="row">
                <!-- Información Personal -->
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-user me-2"></i>Información Personal
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($customer['first_name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Apellido *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($customer['last_name']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="birth_date" class="form-label">Fecha de Nacimiento</label>
                                    <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                           value="<?= htmlspecialchars($customer['birth_date'] ?? '') ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="gender" class="form-label">Género</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">Seleccionar</option>
                                        <option value="M" <?= ($customer['gender'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
                                        <option value="F" <?= ($customer['gender'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option>
                                        <option value="Other" <?= ($customer['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Otro</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="notes" class="form-label">Notas</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                                              placeholder="Notas adicionales sobre el cliente"><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="preferences" class="form-label">Preferencias</label>
                                    <textarea class="form-control" id="preferences" name="preferences" rows="2" 
                                              placeholder="Preferencias alimentarias, alergias, etc."><?= htmlspecialchars($customer['preferences'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Contacto Actual -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-address-book me-2"></i>Información de Contacto Actual
                            </h6>
                            <a href="addresses.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit me-1"></i>Gestionar Contactos
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Phones -->
                                <div class="col-md-6">
                                    <h6><i class="fas fa-phone me-2"></i>Teléfonos</h6>
                                    <?php if (empty($phones)): ?>
                                        <p class="text-muted">No hay teléfonos registrados</p>
                                        <a href="addresses.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-plus me-1"></i>Agregar Teléfono
                                        </a>
                                    <?php else: ?>
                                        <?php foreach ($phones as $phone): ?>
                                            <div class="mb-2">
                                                <strong><?= ucfirst($phone['phone_type']) ?>:</strong>
                                                <a href="tel:<?= htmlspecialchars($phone['phone_number']) ?>">
                                                    <?= htmlspecialchars($phone['phone_number']) ?>
                                                </a>
                                                <?php if ($phone['is_primary']): ?>
                                                    <span class="badge bg-primary ms-1">Principal</span>
                                                <?php endif; ?>
                                                <?php if ($phone['is_whatsapp']): ?>
                                                    <span class="badge bg-success ms-1">WhatsApp</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Addresses -->
                                <div class="col-md-6">
                                    <h6><i class="fas fa-map-marker-alt me-2"></i>Direcciones</h6>
                                    <?php if (empty($addresses)): ?>
                                        <p class="text-muted">No hay direcciones registradas</p>
                                        <a href="addresses.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-plus me-1"></i>Agregar Dirección
                                        </a>
                                    <?php else: ?>
                                        <?php foreach ($addresses as $address): ?>
                                            <div class="mb-3">
                                                <strong><?= ucfirst($address['address_type']) ?>:</strong>
                                                <?php if ($address['is_primary']): ?>
                                                    <span class="badge bg-primary ms-1">Principal</span>
                                                <?php endif; ?>
                                                <br>
                                                <small>
                                                    <?= htmlspecialchars($address['street_address']) ?><br>
                                                    <?= htmlspecialchars($address['city']) ?>
                                                    <?php if ($address['state']): ?>
                                                        , <?= htmlspecialchars($address['state']) ?>
                                                    <?php endif; ?>
                                                    <?php if ($address['postal_code']): ?>
                                                        <?= htmlspecialchars($address['postal_code']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Customer Status -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-cog me-2"></i>Estado del Cliente
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="customer_status" class="form-label">Estado</label>
                                <select class="form-select" id="customer_status" name="customer_status">
                                    <option value="new" <?= ($customer['customer_status'] ?? 'new') === 'new' ? 'selected' : '' ?>>Nuevo</option>
                                    <option value="regular" <?= ($customer['customer_status'] ?? '') === 'regular' ? 'selected' : '' ?>>Regular</option>
                                    <option value="vip" <?= ($customer['customer_status'] ?? '') === 'vip' ? 'selected' : '' ?>>VIP</option>
                                    <option value="inactive" <?= ($customer['customer_status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                                </select>
                                <small class="form-text text-muted">
                                    El estado se puede actualizar automáticamente basado en las compras
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Info -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-info-circle me-2"></i>Información del Cliente
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted">Código de Cliente</small>
                                <div class="h6 mb-0"><?= htmlspecialchars($customer['customer_code']) ?></div>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">Fecha de Registro</small>
                                <div class="h6 mb-0"><?= date('d/m/Y H:i', strtotime($customer['registration_date'])) ?></div>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">Total de Órdenes</small>
                                <div class="h6 mb-0"><?= number_format($customer['total_orders']) ?></div>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">Total Gastado</small>
                                <div class="h6 mb-0 text-success">$<?= number_format($customer['total_spent'], 2) ?></div>
                            </div>
                            <?php if ($customer['last_order_date']): ?>
                                <div class="mb-0">
                                    <small class="text-muted">Última Orden</small>
                                    <div class="h6 mb-0"><?= date('d/m/Y', strtotime($customer['last_order_date'])) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card shadow">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Guardar Cambios
                                </button>
                                <a href="view.php?id=<?= $customer['id'] ?>" class="btn btn-outline-info">
                                    <i class="fas fa-eye me-2"></i>Ver Perfil
                                </a>
                                <a href="addresses.php?id=<?= $customer['id'] ?>" class="btn btn-outline-success">
                                    <i class="fas fa-map-marker-alt me-2"></i>Gestionar Contactos
                                </a>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!firstName) {
                e.preventDefault();
                alert('El nombre es requerido');
                document.getElementById('first_name').focus();
                return;
            }
            
            if (!lastName) {
                e.preventDefault();
                alert('El apellido es requerido');
                document.getElementById('last_name').focus();
                return;
            }
            
            if (email && !isValidEmail(email)) {
                e.preventDefault();
                alert('Por favor ingrese un email válido');
                document.getElementById('email').focus();
                return;
            }
        });
        
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        // Auto-save draft functionality (optional enhancement)
        let saveTimeout;
        document.querySelectorAll('input, textarea, select').forEach(element => {
            element.addEventListener('input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    // Could implement auto-save draft here
                    console.log('Auto-saving draft...');
                }, 2000);
            });
        });
    </script>

    <style>
        .avatar-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }
    </style>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
