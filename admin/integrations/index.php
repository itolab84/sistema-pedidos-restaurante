<?php
require_once '../config/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireLogin();
$user = $auth->getCurrentUser();

$pageTitle = 'Integraciones API - FlavorFinder Admin';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_integration':
                $id = intval($_POST['id']);
                $api_key = trim($_POST['api_key']);
                $api_secret = trim($_POST['api_secret']);
                $endpoint_url = trim($_POST['endpoint_url']);
                $status = $_POST['status'];
                $configuration = json_encode($_POST['configuration'] ?? []);
                
                $stmt = $conn->prepare("UPDATE api_integrations SET api_key = ?, api_secret = ?, endpoint_url = ?, status = ?, configuration = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $api_key, $api_secret, $endpoint_url, $status, $configuration, $id);
                
                if ($stmt->execute()) {
                    $success_message = "Integración actualizada exitosamente";
                } else {
                    $error_message = "Error al actualizar la integración";
                }
                break;
                
            case 'test_connection':
                $id = intval($_POST['id']);
                // Here you would implement the actual API testing logic
                $success_message = "Prueba de conexión realizada (funcionalidad en desarrollo)";
                break;
        }
    }
}

// Get integrations
$conn = getDBConnection();
$result = $conn->query("SELECT * FROM api_integrations ORDER BY service_name");
$integrations = $result->fetch_all(MYSQLI_ASSOC);

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
                <i class="fas fa-plug me-2"></i>Integraciones API
            </h1>
            <p class="text-muted">Configura las conexiones con servicios externos</p>
        </div>
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

    <div class="row">
        <?php foreach ($integrations as $integration): ?>
            <div class="col-lg-4 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <?php if ($integration['service_name'] === 'Pagomovil API'): ?>
                                <i class="fas fa-mobile-alt me-2 text-primary"></i>
                            <?php elseif ($integration['service_name'] === 'Débito Inmediato API'): ?>
                                <i class="fas fa-credit-card me-2 text-success"></i>
                            <?php elseif ($integration['service_name'] === 'WhatsApp API'): ?>
                                <i class="fab fa-whatsapp me-2 text-success"></i>
                            <?php else: ?>
                                <i class="fas fa-plug me-2 text-info"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($integration['service_name']) ?>
                        </h5>
                        <span class="badge <?= $integration['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $integration['status'] === 'active' ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="integration-info mb-3">
                            <?php if ($integration['service_name'] === 'Pagomovil API'): ?>
                                <p class="text-muted">Validación automática de pagos móviles</p>
                                <ul class="list-unstyled small">
                                    <li><i class="fas fa-check text-success me-1"></i> Verificación de transacciones</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Confirmación automática</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Notificaciones en tiempo real</li>
                                </ul>
                            <?php elseif ($integration['service_name'] === 'Débito Inmediato API'): ?>
                                <p class="text-muted">Procesamiento de débitos bancarios</p>
                                <ul class="list-unstyled small">
                                    <li><i class="fas fa-check text-success me-1"></i> Débitos automáticos</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Validación bancaria</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Reportes de transacciones</li>
                                </ul>
                            <?php elseif ($integration['service_name'] === 'WhatsApp API'): ?>
                                <p class="text-muted">Comunicación automática con clientes</p>
                                <ul class="list-unstyled small">
                                    <li><i class="fas fa-check text-success me-1"></i> Confirmación de pedidos</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Estado de delivery</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Promociones y ofertas</li>
                                </ul>
                            <?php endif; ?>
                        </div>
                        
                        <div class="connection-status mb-3">
                            <?php if ($integration['api_key']): ?>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-key text-warning me-2"></i>
                                    <small class="text-muted">API configurada</small>
                                </div>
                            <?php else: ?>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    <small class="text-muted">Requiere configuración</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between">
                            <button class="btn btn-primary btn-sm" onclick="configureIntegration(<?= $integration['id'] ?>)">
                                <i class="fas fa-cog"></i> Configurar
                            </button>
                            <?php if ($integration['api_key']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="test_connection">
                                    <input type="hidden" name="id" value="<?= $integration['id'] ?>">
                                    <button type="submit" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-plug"></i> Probar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Configuration Modal -->
    <div class="modal fade" id="configurationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Configurar Integración</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_integration">
                        <input type="hidden" name="id" id="config_id">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Importante:</strong> Mantén esta información segura. Nunca compartas tus claves API.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="status" id="config_status">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">API Key</label>
                            <input type="password" class="form-control" name="api_key" id="config_api_key" 
                                   placeholder="Ingresa tu clave API">
                            <small class="text-muted">Clave proporcionada por el proveedor del servicio</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">API Secret</label>
                            <input type="password" class="form-control" name="api_secret" id="config_api_secret" 
                                   placeholder="Ingresa tu clave secreta">
                            <small class="text-muted">Clave secreta para autenticación</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Endpoint URL</label>
                            <input type="url" class="form-control" name="endpoint_url" id="config_endpoint_url" 
                                   placeholder="https://api.ejemplo.com/v1/">
                            <small class="text-muted">URL base del servicio API</small>
                        </div>
                        
                        <div class="mb-3" id="additional_config">
                            <!-- Additional configuration fields will be added here based on service type -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Configuración</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const integrations = <?= json_encode($integrations) ?>;

    function configureIntegration(id) {
        const integration = integrations.find(i => i.id == id);
        if (integration) {
            document.getElementById('config_id').value = integration.id;
            document.getElementById('config_status').value = integration.status;
            document.getElementById('config_api_key').value = integration.api_key || '';
            document.getElementById('config_api_secret').value = integration.api_secret || '';
            document.getElementById('config_endpoint_url').value = integration.endpoint_url || '';
            
            // Update modal title
            document.querySelector('#configurationModal .modal-title').textContent = 
                'Configurar ' + integration.service_name;
            
            // Add service-specific configuration fields
            addServiceSpecificFields(integration.service_name);
            
            new bootstrap.Modal(document.getElementById('configurationModal')).show();
        }
    }

    function addServiceSpecificFields(serviceName) {
        const container = document.getElementById('additional_config');
        container.innerHTML = '';
        
        if (serviceName === 'WhatsApp API') {
            container.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Número de WhatsApp Business</label>
                    <input type="text" class="form-control" name="configuration[phone_number]" 
                           placeholder="+58414XXXXXXX">
                    <small class="text-muted">Número registrado en WhatsApp Business</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Webhook URL</label>
                    <input type="url" class="form-control" name="configuration[webhook_url]" 
                           placeholder="https://tudominio.com/webhook/whatsapp">
                    <small class="text-muted">URL para recibir notificaciones</small>
                </div>
            `;
        } else if (serviceName === 'Pagomovil API') {
            container.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Banco Emisor</label>
                    <select class="form-select" name="configuration[bank_code]">
                        <option value="">Seleccionar banco</option>
                        <option value="0102">Banco de Venezuela</option>
                        <option value="0104">Venezolano de Crédito</option>
                        <option value="0105">Banco Mercantil</option>
                        <option value="0108">Banco Provincial</option>
                        <option value="0114">Bancaribe</option>
                        <option value="0115">Banco Exterior</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Timeout (segundos)</label>
                    <input type="number" class="form-control" name="configuration[timeout]" 
                           value="30" min="10" max="300">
                    <small class="text-muted">Tiempo límite para validación</small>
                </div>
            `;
        } else if (serviceName === 'Débito Inmediato API') {
            container.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Código de Comercio</label>
                    <input type="text" class="form-control" name="configuration[merchant_code]" 
                           placeholder="12345678">
                    <small class="text-muted">Código asignado por el banco</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Terminal ID</label>
                    <input type="text" class="form-control" name="configuration[terminal_id]" 
                           placeholder="TERM001">
                    <small class="text-muted">Identificador del terminal</small>
                </div>
            `;
        }
    }

    // Show/hide API keys
    document.addEventListener('DOMContentLoaded', function() {
        const apiKeyInputs = document.querySelectorAll('input[type="password"]');
        apiKeyInputs.forEach(input => {
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'btn btn-outline-secondary btn-sm position-absolute end-0 top-50 translate-middle-y me-2';
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            toggleBtn.style.zIndex = '10';
            
            input.parentElement.style.position = 'relative';
            input.parentElement.appendChild(toggleBtn);
            
            toggleBtn.addEventListener('click', function() {
                if (input.type === 'password') {
                    input.type = 'text';
                    this.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    input.type = 'password';
                    this.innerHTML = '<i class="fas fa-eye"></i>';
                }
            });
        });
    });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
