<?php
require_once '../config/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = AdminDB::getInstance();

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
        
        // Verificar email único si se proporciona
        if (!empty($email)) {
            $existing = $db->fetchOne("SELECT id FROM customers WHERE email = ? AND status = 'active'", [$email]);
            if ($existing) {
                throw new Exception('Ya existe un cliente con este email');
            }
        }
        
        // Insertar cliente
        $customer_data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email ?: null,
            'birth_date' => $birth_date ?: null,
            'gender' => $gender ?: null,
            'customer_status' => $customer_status,
            'notes' => $notes ?: null,
            'preferences' => $preferences ?: null,
            'created_by' => $user['id']
        ];
        
        $customer_id = $db->insert('customers', $customer_data);
        
        // Procesar teléfonos
        $phones = $_POST['phones'] ?? [];
        $phone_types = $_POST['phone_types'] ?? [];
        $phone_primary = $_POST['phone_primary'] ?? 0;
        $phone_whatsapp = $_POST['phone_whatsapp'] ?? [];
        
        foreach ($phones as $index => $phone) {
            $phone = trim($phone);
            if (!empty($phone)) {
                $phone_data = [
                    'customer_id' => $customer_id,
                    'phone_number' => $phone,
                    'phone_type' => $phone_types[$index] ?? 'mobile',
                    'is_primary' => ($index == $phone_primary) ? 1 : 0,
                    'is_whatsapp' => in_array($index, $phone_whatsapp) ? 1 : 0
                ];
                $db->insert('customer_phones', $phone_data);
            }
        }
        
        // Procesar direcciones
        $addresses = $_POST['addresses'] ?? [];
        $address_types = $_POST['address_types'] ?? [];
        $address_primary = $_POST['address_primary'] ?? 0;
        $cities = $_POST['cities'] ?? [];
        $states = $_POST['states'] ?? [];
        $postal_codes = $_POST['postal_codes'] ?? [];
        $delivery_instructions = $_POST['delivery_instructions'] ?? [];
        
        foreach ($addresses as $index => $address) {
            $address = trim($address);
            if (!empty($address)) {
                $address_data = [
                    'customer_id' => $customer_id,
                    'street_address' => $address,
                    'city' => trim($cities[$index] ?? ''),
                    'state' => trim($states[$index] ?? ''),
                    'postal_code' => trim($postal_codes[$index] ?? ''),
                    'address_type' => $address_types[$index] ?? 'home',
                    'is_primary' => ($index == $address_primary) ? 1 : 0,
                    'delivery_instructions' => trim($delivery_instructions[$index] ?? '') ?: null
                ];
                $db->insert('customer_addresses', $address_data);
            }
        }
        
        $message = 'Cliente creado exitosamente';
        $messageType = 'success';
        
        // Redirigir al perfil del cliente
        header("Location: view.php?id=$customer_id&created=1");
        exit;
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Cliente - Administración</title>
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
                            <i class="fas fa-user-plus me-2 text-primary"></i>
                            Nuevo Cliente
                        </h2>
                        <p class="text-muted mb-0">
                            Registra un nuevo cliente con toda su información de contacto
                        </p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver a Clientes
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
                                           value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Apellido *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="birth_date" class="form-label">Fecha de Nacimiento</label>
                                    <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                           value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="gender" class="form-label">Género</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">Seleccionar</option>
                                        <option value="M" <?= ($_POST['gender'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
                                        <option value="F" <?= ($_POST['gender'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option>
                                        <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Otro</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="notes" class="form-label">Notas</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                                              placeholder="Notas adicionales sobre el cliente"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="preferences" class="form-label">Preferencias</label>
                                    <textarea class="form-control" id="preferences" name="preferences" rows="2" 
                                              placeholder="Preferencias alimentarias, alergias, etc."><?= htmlspecialchars($_POST['preferences'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Teléfonos -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-phone me-2"></i>Teléfonos
                            </h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPhone()">
                                <i class="fas fa-plus me-1"></i>Agregar Teléfono
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="phones-container">
                                <div class="phone-row row mb-3">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" name="phones[]" placeholder="Número de teléfono">
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" name="phone_types[]">
                                            <option value="mobile">Móvil</option>
                                            <option value="home">Casa</option>
                                            <option value="work">Trabajo</option>
                                            <option value="other">Otro</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="phone_primary" value="0" checked>
                                            <label class="form-check-label">Principal</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="phone_whatsapp[]" value="0">
                                            <label class="form-check-label">WhatsApp</label>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePhone(this)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Direcciones -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-map-marker-alt me-2"></i>Direcciones
                            </h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addAddress()">
                                <i class="fas fa-plus me-1"></i>Agregar Dirección
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="addresses-container">
                                <div class="address-row mb-4 p-3 border rounded">
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label class="form-label">Dirección</label>
                                            <input type="text" class="form-control" name="addresses[]" placeholder="Calle y número">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Tipo</label>
                                            <select class="form-select" name="address_types[]">
                                                <option value="home">Casa</option>
                                                <option value="work">Trabajo</option>
                                                <option value="delivery">Entrega</option>
                                                <option value="billing">Facturación</option>
                                                <option value="other">Otro</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Ciudad</label>
                                            <input type="text" class="form-control" name="cities[]" placeholder="Ciudad">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Estado</label>
                                            <input type="text" class="form-control" name="states[]" placeholder="Estado">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Código Postal</label>
                                            <input type="text" class="form-control" name="postal_codes[]" placeholder="CP">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label class="form-label">Instrucciones de Entrega</label>
                                            <input type="text" class="form-control" name="delivery_instructions[]" 
                                                   placeholder="Referencias, instrucciones especiales...">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Principal</label>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="radio" name="address_primary" value="0" checked>
                                                <label class="form-check-label">Sí</label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Acción</label>
                                            <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="removeAddress(this)">
                                                <i class="fas fa-trash me-1"></i>Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-cog me-2"></i>Configuración
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="customer_status" class="form-label">Estado del Cliente</label>
                                <select class="form-select" id="customer_status" name="customer_status">
                                    <option value="new" <?= ($_POST['customer_status'] ?? 'new') === 'new' ? 'selected' : '' ?>>Nuevo</option>
                                    <option value="regular" <?= ($_POST['customer_status'] ?? '') === 'regular' ? 'selected' : '' ?>>Regular</option>
                                    <option value="vip" <?= ($_POST['customer_status'] ?? '') === 'vip' ? 'selected' : '' ?>>VIP</option>
                                    <option value="inactive" <?= ($_POST['customer_status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                                </select>
                                <small class="form-text text-muted">
                                    El estado se puede actualizar automáticamente basado en las compras
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Crear Cliente
                                </button>
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
        let phoneIndex = 1;
        let addressIndex = 1;

        function addPhone() {
            const container = document.getElementById('phones-container');
            const phoneRow = document.createElement('div');
            phoneRow.className = 'phone-row row mb-3';
            phoneRow.innerHTML = `
                <div class="col-md-4">
                    <input type="text" class="form-control" name="phones[]" placeholder="Número de teléfono">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="phone_types[]">
                        <option value="mobile">Móvil</option>
                        <option value="home">Casa</option>
                        <option value="work">Trabajo</option>
                        <option value="other">Otro</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="phone_primary" value="${phoneIndex}">
                        <label class="form-check-label">Principal</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="phone_whatsapp[]" value="${phoneIndex}">
                        <label class="form-check-label">WhatsApp</label>
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePhone(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(phoneRow);
            phoneIndex++;
        }

        function removePhone(button) {
            const phoneRows = document.querySelectorAll('.phone-row');
            if (phoneRows.length > 1) {
                button.closest('.phone-row').remove();
            } else {
                alert('Debe mantener al menos un campo de teléfono');
            }
        }

        function addAddress() {
            const container = document.getElementById('addresses-container');
            const addressRow = document.createElement('div');
            addressRow.className = 'address-row mb-4 p-3 border rounded';
            addressRow.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Dirección</label>
                        <input type="text" class="form-control" name="addresses[]" placeholder="Calle y número">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select class="form-select" name="address_types[]">
                            <option value="home">Casa</option>
                            <option value="work">Trabajo</option>
                            <option value="delivery">Entrega</option>
                            <option value="billing">Facturación</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Ciudad</label>
                        <input type="text" class="form-control" name="cities[]" placeholder="Ciudad">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <input type="text" class="form-control" name="states[]" placeholder="Estado">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Código Postal</label>
                        <input type="text" class="form-control" name="postal_codes[]" placeholder="CP">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Instrucciones de Entrega</label>
                        <input type="text" class="form-control" name="delivery_instructions[]" 
                               placeholder="Referencias, instrucciones especiales...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Principal</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="radio" name="address_primary" value="${addressIndex}">
                            <label class="form-check-label">Sí</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Acción</label>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="removeAddress(this)">
                            <i class="fas fa-trash me-1"></i>Eliminar
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(addressRow);
            addressIndex++;
        }

        function removeAddress(button) {
            const addressRows = document.querySelectorAll('.address-row');
            if (addressRows.length > 1) {
                button.closest('.address-row').remove();
            } else {
                alert('Debe mantener al menos un campo de dirección');
            }
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
