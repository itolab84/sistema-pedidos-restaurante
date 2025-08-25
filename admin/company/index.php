<?php
require_once '../config/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireLogin();
$user = $auth->getCurrentUser();

$pageTitle = 'Datos de la Empresa - FlavorFinder Admin';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    $razon_social = trim($_POST['razon_social']);
    $rif = trim($_POST['rif']);
    $telefono = trim($_POST['telefono']);
    $direccion_fiscal = trim($_POST['direccion_fiscal']);
    
    // Check if company settings exist
    $result = $conn->query("SELECT id FROM company_settings LIMIT 1");
    
    if ($result->num_rows > 0) {
        // Update existing record
        $row = $result->fetch_assoc();
        $stmt = $conn->prepare("UPDATE company_settings SET razon_social = ?, rif = ?, telefono = ?, direccion_fiscal = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $razon_social, $rif, $telefono, $direccion_fiscal, $row['id']);
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO company_settings (razon_social, rif, telefono, direccion_fiscal) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $razon_social, $rif, $telefono, $direccion_fiscal);
    }
    
    if ($stmt->execute()) {
        $success_message = "Datos de la empresa actualizados exitosamente";
    } else {
        $error_message = "Error al actualizar los datos de la empresa";
    }
}

// Get company settings
$conn = getDBConnection();
$result = $conn->query("SELECT * FROM company_settings LIMIT 1");
$company = $result->num_rows > 0 ? $result->fetch_assoc() : [
    'razon_social' => '',
    'rif' => '',
    'telefono' => '',
    'direccion_fiscal' => ''
];

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
                <i class="fas fa-building me-2"></i>Datos de la Empresa
            </h1>
            <p class="text-muted">Configura la información legal y de contacto de tu empresa</p>
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
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>Información de la Empresa
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-building me-1"></i>Razón Social <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="razon_social" 
                                       value="<?= htmlspecialchars($company['razon_social']) ?>" 
                                       placeholder="Ej: FlavorFinder Restaurant C.A." required>
                                <small class="text-muted">Nombre legal completo de la empresa</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-id-card me-1"></i>RIF <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="rif" 
                                       value="<?= htmlspecialchars($company['rif']) ?>" 
                                       placeholder="Ej: J-12345678-9" required>
                                <small class="text-muted">Registro de Información Fiscal</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-phone me-1"></i>Teléfono
                                </label>
                                <input type="text" class="form-control" name="telefono" 
                                       value="<?= htmlspecialchars($company['telefono']) ?>" 
                                       placeholder="Ej: +58-212-1234567">
                                <small class="text-muted">Número de contacto principal</small>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-map-marker-alt me-1"></i>Dirección Fiscal <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" name="direccion_fiscal" rows="3" 
                                          placeholder="Ej: Av. Principal, Centro Comercial FlavorFinder, Caracas, Venezuela" required><?= htmlspecialchars($company['direccion_fiscal']) ?></textarea>
                                <small class="text-muted">Dirección registrada ante el SENIAT</small>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Vista Previa
                    </h5>
                </div>
                <div class="card-body">
                    <div class="company-preview">
                        <div class="text-center mb-3">
                            <i class="fas fa-utensils fa-3x text-primary mb-2"></i>
                            <h5 class="company-name">
                                <?= $company['razon_social'] ?: 'Nombre de la Empresa' ?>
                            </h5>
                        </div>
                        
                        <div class="company-details">
                            <div class="mb-2">
                                <strong><i class="fas fa-id-card me-2 text-muted"></i>RIF:</strong>
                                <span class="company-rif"><?= $company['rif'] ?: 'No configurado' ?></span>
                            </div>
                            
                            <div class="mb-2">
                                <strong><i class="fas fa-phone me-2 text-muted"></i>Teléfono:</strong>
                                <span class="company-phone"><?= $company['telefono'] ?: 'No configurado' ?></span>
                            </div>
                            
                            <div class="mb-2">
                                <strong><i class="fas fa-map-marker-alt me-2 text-muted"></i>Dirección:</strong>
                                <div class="company-address"><?= $company['direccion_fiscal'] ?: 'No configurada' ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow mt-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>Importante
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Esta información aparecerá en las facturas
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Debe coincidir con el registro del SENIAT
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Se usa para reportes fiscales
                        </li>
                        <li>
                            <i class="fas fa-check text-success me-2"></i>
                            Información visible para los clientes
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <style>
    .company-preview {
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        padding: 20px;
        background-color: #f8f9fa;
    }

    .company-name {
        color: #E67E22;
        font-weight: bold;
    }

    .company-details {
        font-size: 0.9rem;
    }

    .company-address {
        font-style: italic;
        color: #6c757d;
        margin-top: 5px;
    }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Live preview update
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = {
            'razon_social': '.company-name',
            'rif': '.company-rif',
            'telefono': '.company-phone',
            'direccion_fiscal': '.company-address'
        };
        
        Object.keys(inputs).forEach(inputName => {
            const input = document.querySelector(`input[name="${inputName}"], textarea[name="${inputName}"]`);
            const preview = document.querySelector(inputs[inputName]);
            
            if (input && preview) {
                input.addEventListener('input', function() {
                    const value = this.value.trim();
                    if (inputName === 'razon_social') {
                        preview.textContent = value || 'Nombre de la Empresa';
                    } else {
                        preview.textContent = value || 'No configurado';
                    }
                });
            }
        });
    });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
