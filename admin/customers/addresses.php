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

// Handle form submissions
if ($_POST) {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_phone':
                $phone_number = trim($_POST['phone_number'] ?? '');
                $phone_type = $_POST['phone_type'] ?? 'mobile';
                $is_primary = isset($_POST['is_primary']) ? 1 : 0;
                $is_whatsapp = isset($_POST['is_whatsapp']) ? 1 : 0;
                $notes = trim($_POST['phone_notes'] ?? '');
                
                if (empty($phone_number)) {
                    throw new Exception('El número de teléfono es requerido');
                }
                
                // If this is set as primary, remove primary from others
                if ($is_primary) {
                    $db->query("UPDATE customer_phones SET is_primary = 0 WHERE customer_id = ?", [$customer_id]);
                }
                
                $phone_data = [
                    'customer_id' => $customer_id,
                    'phone_number' => $phone_number,
                    'phone_type' => $phone_type,
                    'is_primary' => $is_primary,
                    'is_whatsapp' => $is_whatsapp,
                    'notes' => $notes ?: null
                ];
                
                $db->insert('customer_phones', $phone_data);
                $message = 'Teléfono agregado exitosamente';
                break;
                
            case 'edit_phone':
                $phone_id = (int)$_POST['phone_id'];
                $phone_number = trim($_POST['phone_number'] ?? '');
                $phone_type = $_POST['phone_type'] ?? 'mobile';
                $is_primary = isset($_POST['is_primary']) ? 1 : 0;
                $is_whatsapp = isset($_POST['is_whatsapp']) ? 1 : 0;
                $notes = trim($_POST['phone_notes'] ?? '');
                
                if (empty($phone_number)) {
                    throw new Exception('El número de teléfono es requerido');
                }
                
                // If this is set as primary, remove primary from others
                if ($is_primary) {
                    $db->query("UPDATE customer_phones SET is_primary = 0 WHERE customer_id = ? AND id != ?", [$customer_id, $phone_id]);
                }
                
                $phone_data = [
                    'phone_number' => $phone_number,
                    'phone_type' => $phone_type,
                    'is_primary' => $is_primary,
                    'is_whatsapp' => $is_whatsapp,
                    'notes' => $notes ?: null
                ];
                
                $db->update('customer_phones', $phone_data, 'id = ? AND customer_id = ?', [$phone_id, $customer_id]);
                $message = 'Teléfono actualizado exitosamente';
                break;
                
            case 'delete_phone':
                $phone_id = (int)$_POST['phone_id'];
                $db->delete('customer_phones', 'id = ? AND customer_id = ?', [$phone_id, $customer_id]);
                $message = 'Teléfono eliminado exitosamente';
                break;
                
            case 'add_address':
                $street_address = trim($_POST['street_address'] ?? '');
                $address_line_2 = trim($_POST['address_line_2'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $state = trim($_POST['state'] ?? '');
                $postal_code = trim($_POST['postal_code'] ?? '');
                $country = trim($_POST['country'] ?? 'México');
                $address_type = $_POST['address_type'] ?? 'home';
                $is_primary = isset($_POST['address_is_primary']) ? 1 : 0;
                $delivery_instructions = trim($_POST['delivery_instructions'] ?? '');
                
                if (empty($street_address)) {
                    throw new Exception('La dirección es requerida');
                }
                
                if (empty($city)) {
                    throw new Exception('La ciudad es requerida');
                }
                
                // If this is set as primary, remove primary from others
                if ($is_primary) {
                    $db->query("UPDATE customer_addresses SET is_primary = 0 WHERE customer_id = ?", [$customer_id]);
                }
                
                $address_data = [
                    'customer_id' => $customer_id,
                    'street_address' => $street_address,
                    'address_line_2' => $address_line_2 ?: null,
                    'city' => $city,
                    'state' => $state ?: null,
                    'postal_code' => $postal_code ?: null,
                    'country' => $country,
                    'address_type' => $address_type,
                    'is_primary' => $is_primary,
                    'delivery_instructions' => $delivery_instructions ?: null
                ];
                
                $db->insert('customer_addresses', $address_data);
                $message = 'Dirección agregada exitosamente';
                break;
                
            case 'edit_address':
                $address_id = (int)$_POST['address_id'];
                $street_address = trim($_POST['street_address'] ?? '');
                $address_line_2 = trim($_POST['address_line_2'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $state = trim($_POST['state'] ?? '');
                $postal_code = trim($_POST['postal_code'] ?? '');
                $country = trim($_POST['country'] ?? 'México');
                $address_type = $_POST['address_type'] ?? 'home';
                $is_primary = isset($_POST['address_is_primary']) ? 1 : 0;
                $delivery_instructions = trim($_POST['delivery_instructions'] ?? '');
                
                if (empty($street_address)) {
                    throw new Exception('La dirección es requerida');
                }
                
                if (empty($city)) {
                    throw new Exception('La ciudad es requerida');
                }
                
                // If this is set as primary, remove primary from others
                if ($is_primary) {
                    $db->query("UPDATE customer_addresses SET is_primary = 0 WHERE customer_id = ? AND id != ?", [$customer_id, $address_id]);
                }
                
                $address_data = [
                    'street_address' => $street_address,
                    'address_line_2' => $address_line_2 ?: null,
                    'city' => $city,
                    'state' => $state ?: null,
                    'postal_code' => $postal_code ?: null,
                    'country' => $country,
                    'address_type' => $address_type,
                    'is_primary' => $is_primary,
                    'delivery_instructions' => $delivery_instructions ?: null
                ];
                
                $db->update('customer_addresses', $address_data, 'id = ? AND customer_id = ?', [$address_id, $customer_id]);
                $message = 'Dirección actualizada exitosamente';
                break;
                
            case 'delete_address':
                $address_id = (int)$_POST['address_id'];
                $db->delete('customer_addresses', 'id = ? AND customer_id = ?', [$address_id, $customer_id]);
                $message = 'Dirección eliminada exitosamente';
                break;
        }
        
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
    <title>Gestionar Contactos - <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></title>
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
                                <i class="fas fa-address-book me-2 text-primary"></i>
                                Gestionar Contactos
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
                        <a href="edit.php?id=<?= $customer['id'] ?>" class="btn btn-outline-success me-2">
                            <i class="fas fa-edit me-2"></i>Editar Cliente
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

        <div class="row">
            <!-- Phones Section -->
            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-phone me-2"></i>Teléfonos
                        </h6>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPhoneModal">
                            <i class="fas fa-plus me-1"></i>Agregar Teléfono
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($phones)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-phone fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted">No hay teléfonos registrados</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPhoneModal">
                                    <i class="fas fa-plus me-2"></i>Agregar Primer Teléfono
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($phones as $phone): ?>
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <?= ucfirst($phone['phone_type']) ?>
                                                <?php if ($phone['is_primary']): ?>
                                                    <span class="badge bg-primary ms-1">Principal</span>
                                                <?php endif; ?>
                                                <?php if ($phone['is_whatsapp']): ?>
                                                    <span class="badge bg-success ms-1">WhatsApp</span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="mb-1">
                                                <a href="tel:<?= htmlspecialchars($phone['phone_number']) ?>" class="text-decoration-none">
                                                    <i class="fas fa-phone me-1"></i>
                                                    <?= htmlspecialchars($phone['phone_number']) ?>
                                                </a>
                                                <?php if ($phone['is_whatsapp']): ?>
                                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $phone['phone_number']) ?>" 
                                                       target="_blank" class="text-success ms-2">
                                                        <i class="fab fa-whatsapp"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </p>
                                            <?php if ($phone['notes']): ?>
                                                <small class="text-muted"><?= htmlspecialchars($phone['notes']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editPhone(<?= htmlspecialchars(json_encode($phone)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deletePhone(<?= $phone['id'] ?>, '<?= htmlspecialchars($phone['phone_number']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Addresses Section -->
            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-map-marker-alt me-2"></i>Direcciones
                        </h6>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                            <i class="fas fa-plus me-1"></i>Agregar Dirección
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($addresses)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-map-marker-alt fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted">No hay direcciones registradas</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                                    <i class="fas fa-plus me-2"></i>Agregar Primera Dirección
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($addresses as $address): ?>
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <?= ucfirst($address['address_type']) ?>
                                                <?php if ($address['is_primary']): ?>
                                                    <span class="badge bg-primary ms-1">Principal</span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="mb-1">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?= htmlspecialchars($address['street_address']) ?>
                                                <?php if ($address['address_line_2']): ?>
                                                    <br><small class="ms-3"><?= htmlspecialchars($address['address_line_2']) ?></small>
                                                <?php endif; ?>
                                            </p>
                                            <p class="mb-1">
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($address['city']) ?>
                                                    <?php if ($address['state']): ?>
                                                        , <?= htmlspecialchars($address['state']) ?>
                                                    <?php endif; ?>
                                                    <?php if ($address['postal_code']): ?>
                                                        <?= htmlspecialchars($address['postal_code']) ?>
                                                    <?php endif; ?>
                                                    <br><?= htmlspecialchars($address['country']) ?>
                                                </small>
                                            </p>
                                            <?php if ($address['delivery_instructions']): ?>
                                                <small class="text-info">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    <?= htmlspecialchars($address['delivery_instructions']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editAddress(<?= htmlspecialchars(json_encode($address)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteAddress(<?= $address['id'] ?>, '<?= htmlspecialchars($address['street_address']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <!-- Add Phone Modal -->
    <div class="modal fade" id="addPhoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-phone me-2"></i>Agregar Teléfono
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_address">
                    <input type="hidden" name="address_id" id="edit_address_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="edit_street_address" class="form-label">Dirección *</label>
                                <input type="text" class="form-control" id="edit_street_address" name="street_address" 
                                       placeholder="Calle y número" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_address_type" class="form-label">Tipo</label>
                                <select class="form-select" id="edit_address_type" name="address_type">
                                    <option value="home">Casa</option>
                                    <option value="work">Trabajo</option>
                                    <option value="delivery">Entrega</option>
                                    <option value="billing">Facturación</option>
                                    <option value="other">Otro</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_address_line_2" class="form-label">Dirección Línea 2</label>
                            <input type="text" class="form-control" id="edit_address_line_2" name="address_line_2" 
                                   placeholder="Apartamento, suite, etc. (opcional)">
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_city" class="form-label">Ciudad *</label>
                                <input type="text" class="form-control" id="edit_city" name="city" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_state" class="form-label">Estado</label>
                                <input type="text" class="form-control" id="edit_state" name="state">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_postal_code" class="form-label">Código Postal</label>
                                <input type="text" class="form-control" id="edit_postal_code" name="postal_code">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_country" class="form-label">País</label>
                            <input type="text" class="form-control" id="edit_country" name="country" value="México">
                        </div>
                        <div class="mb-3">
                            <label for="edit_delivery_instructions" class="form-label">Instrucciones de Entrega</label>
                            <textarea class="form-control" id="edit_delivery_instructions" name="delivery_instructions" rows="2" 
                                      placeholder="Referencias, instrucciones especiales..."></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_address_is_primary" name="address_is_primary">
                                <label class="form-check-label" for="edit_address_is_primary">
                                    Dirección principal
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modals -->
    <div class="modal fade" id="deletePhoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar el teléfono <strong id="delete_phone_number"></strong>?</p>
                    <p class="text-muted">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete_phone">
                        <input type="hidden" name="phone_id" id="delete_phone_id">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteAddressModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar la dirección <strong id="delete_address_text"></strong>?</p>
                    <p class="text-muted">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete_address">
                        <input type="hidden" name="address_id" id="delete_address_id">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Phone functions
        function editPhone(phone) {
            document.getElementById('edit_phone_id').value = phone.id;
            document.getElementById('edit_phone_number').value = phone.phone_number;
            document.getElementById('edit_phone_type').value = phone.phone_type;
            document.getElementById('edit_is_primary').checked = phone.is_primary == 1;
            document.getElementById('edit_is_whatsapp').checked = phone.is_whatsapp == 1;
            document.getElementById('edit_phone_notes').value = phone.notes || '';
            
            new bootstrap.Modal(document.getElementById('editPhoneModal')).show();
        }
        
        function deletePhone(phoneId, phoneNumber) {
            document.getElementById('delete_phone_id').value = phoneId;
            document.getElementById('delete_phone_number').textContent = phoneNumber;
            
            new bootstrap.Modal(document.getElementById('deletePhoneModal')).show();
        }
        
        // Address functions
        function editAddress(address) {
            document.getElementById('edit_address_id').value = address.id;
            document.getElementById('edit_street_address').value = address.street_address;
            document.getElementById('edit_address_line_2').value = address.address_line_2 || '';
            document.getElementById('edit_city').value = address.city;
            document.getElementById('edit_state').value = address.state || '';
            document.getElementById('edit_postal_code').value = address.postal_code || '';
            document.getElementById('edit_country').value = address.country;
            document.getElementById('edit_address_type').value = address.address_type;
            document.getElementById('edit_delivery_instructions').value = address.delivery_instructions || '';
            document.getElementById('edit_address_is_primary').checked = address.is_primary == 1;
            
            new bootstrap.Modal(document.getElementById('editAddressModal')).show();
        }
        
        function deleteAddress(addressId, addressText) {
            document.getElementById('delete_address_id').value = addressId;
            document.getElementById('delete_address_text').textContent = addressText;
            
            new bootstrap.Modal(document.getElementById('deleteAddressModal')).show();
        }
        
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Por favor complete todos los campos requeridos');
                }
            });
        });
        
        // Clear form when modals are hidden
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('hidden.bs.modal', function() {
                const form = this.querySelector('form');
                if (form) {
                    form.reset();
                    form.querySelectorAll('.is-invalid').forEach(field => {
                        field.classList.remove('is-invalid');
                    });
                }
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
        
        .is-invalid {
            border-color: #dc3545;
        }
    </style>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="phone_number" class="form-label">Número de Teléfono *</label>
                            <input type="text" class="form-control" id="phone_number" name="phone_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone_type" class="form-label">Tipo</label>
                            <select class="form-select" id="phone_type" name="phone_type">
                                <option value="mobile">Móvil</option>
                                <option value="home">Casa</option>
                                <option value="work">Trabajo</option>
                                <option value="other">Otro</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_primary" name="is_primary">
                                <label class="form-check-label" for="is_primary">
                                    Teléfono principal
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_whatsapp" name="is_whatsapp">
                                <label class="form-check-label" for="is_whatsapp">
                                    Tiene WhatsApp
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="phone_notes" class="form-label">Notas</label>
                            <input type="text" class="form-control" id="phone_notes" name="phone_notes" 
                                   placeholder="Notas adicionales...">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Phone Modal -->
    <div class="modal fade" id="editPhoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Teléfono
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_phone">
                    <input type="hidden" name="phone_id" id="edit_phone_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_phone_number" class="form-label">Número de Teléfono *</label>
                            <input type="text" class="form-control" id="edit_phone_number" name="phone_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_phone_type" class="form-label">Tipo</label>
                            <select class="form-select" id="edit_phone_type" name="phone_type">
                                <option value="mobile">Móvil</option>
                                <option value="home">Casa</option>
                                <option value="work">Trabajo</option>
                                <option value="other">Otro</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_primary" name="is_primary">
                                <label class="form-check-label" for="edit_is_primary">
                                    Teléfono principal
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_whatsapp" name="is_whatsapp">
                                <label class="form-check-label" for="edit_is_whatsapp">
                                    Tiene WhatsApp
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_phone_notes" class="form-label">Notas</label>
                            <input type="text" class="form-control" id="edit_phone_notes" name="phone_notes" 
                                   placeholder="Notas adicionales...">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Address Modal -->
    <div class="modal fade" id="addAddressModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-map-marker-alt me-2"></i>Agregar Dirección
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_address">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="street_address" class="form-label">Dirección *</label>
                                <input type="text" class="form-control" id="street_address" name="street_address" 
                                       placeholder="Calle y número" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="address_type" class="form-label">Tipo</label>
                                <select class="form-select" id="address_type" name="address_type">
                                    <option value="home">Casa</option>
                                    <option value="work">Trabajo</option>
                                    <option value="delivery">Entrega</option>
                                    <option value="billing">Facturación</option>
                                    <option value="other">Otro</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address_line_2" class="form-label">Dirección Línea 2</label>
                            <input type="text" class="form-control" id="address_line_2" name="address_line_2" 
                                   placeholder="Apartamento, suite, etc. (opcional)">
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="city" class="form-label">Ciudad *</label>
                                <input type="text" class="form-control" id="city" name="city" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="state" class="form-label">Estado</label>
                                <input type="text" class="form-control" id="state" name="state">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="postal_code" class="form-label">Código Postal</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="country" class="form-label">País</label>
                            <input type="text" class="form-control" id="country" name="country" value="México">
                        </div>
                        <div class="mb-3">
                            <label for="delivery_instructions" class="form-label">Instrucciones de Entrega</label>
                            <textarea class="form-control" id="delivery_instructions" name="delivery_instructions" rows="2" 
                                      placeholder="Referencias, instrucciones especiales..."></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="address_is_primary" name="address_is_primary">
                                <label class="form-check-label" for="address_is_primary">
                                    Dirección principal
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Address Modal -->
    <div class="modal fade" id="editAddressModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Dirección
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type
