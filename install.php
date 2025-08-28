<?php
/**
 * INSTALADOR DEL SISTEMA DE RESTAURANTE
 * Versión 1.0.0
 * 
 * Este instalador configura automáticamente:
 * - Base de datos completa
 * - Sistema de administración
 * - Datos de ejemplo
 * - Configuraciones iniciales
 */

require_once 'install/config.php';

$installer = new RestaurantInstaller();
$step = $_GET['step'] ?? 'welcome';
$action = $_POST['action'] ?? '';

// Procesar acciones
if ($action) {
    switch ($action) {
        case 'check_requirements':
            $step = 'requirements';
            break;
        case 'test_database':
            $step = 'database';
            break;
        case 'install_system':
            $step = 'install';
            break;
        case 'complete':
            $step = 'complete';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - <?= SYSTEM_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .installer-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .installer-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .installer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .installer-body {
            padding: 2rem;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0.5rem;
            background: #e9ecef;
            color: #6c757d;
            font-weight: bold;
        }
        .step.active {
            background: #667eea;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .step-line {
            width: 50px;
            height: 2px;
            background: #e9ecef;
            margin-top: 19px;
        }
        .step-line.completed {
            background: #28a745;
        }
        .message-box {
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .message-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .message-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .message-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .progress-container {
            margin: 2rem 0;
        }
        .btn-installer {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-installer:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .installation-log {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        .log-entry {
            margin: 0.25rem 0;
            padding: 0.25rem 0;
        }
        .log-success { color: #28a745; }
        .log-error { color: #dc3545; }
        .log-warning { color: #ffc107; }
        .log-info { color: #17a2b8; }
    </style>
</head>
<body>
    <div class="installer-container">
        <div class="installer-card">
            <div class="installer-header">
                <h1><i class="fas fa-utensils me-3"></i><?= SYSTEM_NAME ?></h1>
                <p class="mb-0">Instalador Automático v<?= INSTALLER_VERSION ?></p>
            </div>
            
            <div class="installer-body">
                <!-- Indicador de pasos -->
                <div class="step-indicator">
                    <div class="step <?= $step === 'welcome' ? 'active' : ($step !== 'welcome' ? 'completed' : '') ?>">1</div>
                    <div class="step-line <?= $step !== 'welcome' && $step !== 'requirements' ? 'completed' : '' ?>"></div>
                    <div class="step <?= $step === 'requirements' ? 'active' : (in_array($step, ['database', 'install', 'complete']) ? 'completed' : '') ?>">2</div>
                    <div class="step-line <?= in_array($step, ['database', 'install', 'complete']) ? 'completed' : '' ?>"></div>
                    <div class="step <?= $step === 'database' ? 'active' : (in_array($step, ['install', 'complete']) ? 'completed' : '') ?>">3</div>
                    <div class="step-line <?= in_array($step, ['install', 'complete']) ? 'completed' : '' ?>"></div>
                    <div class="step <?= $step === 'install' ? 'active' : ($step === 'complete' ? 'completed' : '') ?>">4</div>
                    <div class="step-line <?= $step === 'complete' ? 'completed' : '' ?>"></div>
                    <div class="step <?= $step === 'complete' ? 'active' : '' ?>">5</div>
                </div>

                <?php if ($step === 'welcome'): ?>
                    <!-- Paso 1: Bienvenida -->
                    <div class="text-center">
                        <i class="fas fa-rocket fa-4x text-primary mb-4"></i>
                        <h2>¡Bienvenido al Instalador!</h2>
                        <p class="lead">Este asistente te guiará a través de la instalación completa del sistema de restaurante.</p>
                        
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-database fa-2x text-info mb-3"></i>
                                        <h5>Base de Datos</h5>
                                        <p class="small">Configuración automática de todas las tablas y relaciones</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-user-shield fa-2x text-success mb-3"></i>
                                        <h5>Administración</h5>
                                        <p class="small">Sistema completo de usuarios y autenticación</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-box fa-2x text-warning mb-3"></i>
                                        <h5>Datos de Ejemplo</h5>
                                        <p class="small">Productos, categorías y configuraciones iniciales</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <form method="post">
                                <input type="hidden" name="action" value="check_requirements">
                                <button type="submit" class="btn btn-installer btn-lg">
                                    <i class="fas fa-play me-2"></i>Comenzar Instalación
                                </button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($step === 'requirements'): ?>
                    <!-- Paso 2: Verificación de Requisitos -->
                    <h2><i class="fas fa-check-circle me-2"></i>Verificación de Requisitos</h2>
                    <p>Verificando que tu servidor cumple con todos los requisitos necesarios...</p>
                    
                    <?php
                    $requirements = $installer->checkSystemRequirements();
                    ?>
                    
                    <div class="installation-log">
                        <?php foreach ($installer->getSuccessMessages() as $message): ?>
                            <div class="log-entry log-success"><?= htmlspecialchars($message) ?></div>
                        <?php endforeach; ?>
                        
                        <?php foreach ($installer->getWarnings() as $warning): ?>
                            <div class="log-entry log-warning"><?= htmlspecialchars($warning) ?></div>
                        <?php endforeach; ?>
                        
                        <?php foreach ($installer->getErrors() as $error): ?>
                            <div class="log-entry log-error"><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($requirements['overall']): ?>
                        <div class="message-box message-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>¡Excelente!</strong> Tu servidor cumple con todos los requisitos necesarios.
                        </div>
                        
                        <div class="text-center mt-4">
                            <form method="post">
                                <input type="hidden" name="action" value="test_database">
                                <button type="submit" class="btn btn-installer">
                                    <i class="fas fa-arrow-right me-2"></i>Continuar con la Base de Datos
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="message-box message-error">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Requisitos no cumplidos.</strong> Por favor, corrige los errores antes de continuar.
                        </div>
                        
                        <div class="text-center mt-4">
                            <form method="post">
                                <input type="hidden" name="action" value="check_requirements">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fas fa-redo me-2"></i>Verificar Nuevamente
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                <?php elseif ($step === 'database'): ?>
                    <!-- Paso 3: Configuración de Base de Datos -->
                    <h2><i class="fas fa-database me-2"></i>Configuración de Base de Datos</h2>
                    <p>Configura la conexión a tu base de datos MySQL.</p>
                    
                    <?php
                    if ($_POST['action'] === 'test_database' && !empty($_POST['db_host'])) {
                        $db_host = $_POST['db_host'];
                        $db_user = $_POST['db_user'];
                        $db_pass = $_POST['db_pass'];
                        $db_name = $_POST['db_name'];
                        
                        $connection_test = $installer->testDatabaseConnection($db_host, $db_user, $db_pass, $db_name);
                        
                        if ($connection_test) {
                            echo '<div class="message-box message-success">';
                            echo '<i class="fas fa-check-circle me-2"></i><strong>Conexión exitosa!</strong>';
                            echo '</div>';
                            
                            echo '<div class="installation-log">';
                            foreach ($installer->getSuccessMessages() as $message) {
                                echo '<div class="log-entry log-success">' . htmlspecialchars($message) . '</div>';
                            }
                            foreach ($installer->getWarnings() as $warning) {
                                echo '<div class="log-entry log-warning">' . htmlspecialchars($warning) . '</div>';
                            }
                            echo '</div>';
                            
                            echo '<div class="text-center mt-4">';
                            echo '<form method="post">';
                            echo '<input type="hidden" name="action" value="install_system">';
                            echo '<input type="hidden" name="db_host" value="' . htmlspecialchars($db_host) . '">';
                            echo '<input type="hidden" name="db_user" value="' . htmlspecialchars($db_user) . '">';
                            echo '<input type="hidden" name="db_pass" value="' . htmlspecialchars($db_pass) . '">';
                            echo '<input type="hidden" name="db_name" value="' . htmlspecialchars($db_name) . '">';
                            echo '<button type="submit" class="btn btn-installer">';
                            echo '<i class="fas fa-rocket me-2"></i>Instalar Sistema';
                            echo '</button>';
                            echo '</form>';
                            echo '</div>';
                        } else {
                            echo '<div class="message-box message-error">';
                            echo '<i class="fas fa-exclamation-triangle me-2"></i><strong>Error de conexión</strong>';
                            echo '</div>';
                            
                            echo '<div class="installation-log">';
                            foreach ($installer->getErrors() as $error) {
                                echo '<div class="log-entry log-error">' . htmlspecialchars($error) . '</div>';
                            }
                            echo '</div>';
                        }
                    }
                    ?>
                    
                    <form method="post" class="mt-4">
                        <input type="hidden" name="action" value="test_database">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="db_host" class="form-label">Servidor de Base de Datos</label>
                                    <input type="text" class="form-control" id="db_host" name="db_host" 
                                           value="<?= $_POST['db_host'] ?? DEFAULT_DB_HOST ?>" required>
                                    <div class="form-text">Generalmente 'localhost'</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="db_name" class="form-label">Nombre de la Base de Datos</label>
                                    <input type="text" class="form-control" id="db_name" name="db_name" 
                                           value="<?= $_POST['db_name'] ?? DEFAULT_DB_NAME ?>" required>
                                    <div class="form-text">Se creará si no existe</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="db_user" class="form-label">Usuario de Base de Datos</label>
                                    <input type="text" class="form-control" id="db_user" name="db_user" 
                                           value="<?= $_POST['db_user'] ?? DEFAULT_DB_USER ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="db_pass" class="form-label">Contraseña</label>
                                    <input type="password" class="form-control" id="db_pass" name="db_pass" 
                                           value="<?= $_POST['db_pass'] ?? DEFAULT_DB_PASS ?>">
                                    <div class="form-text">Dejar vacío si no tiene contraseña</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-installer">
                                <i class="fas fa-plug me-2"></i>Probar Conexión
                            </button>
                        </div>
                    </form>

                <?php elseif ($step === 'install'): ?>
                    <!-- Paso 4: Instalación -->
                    <h2><i class="fas fa-cogs me-2"></i>Instalando Sistema</h2>
                    <p>Instalando base de datos, configuraciones y datos de ejemplo...</p>
                    
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%" id="installProgress"></div>
                        </div>
                    </div>
                    
                    <div class="installation-log" id="installLog">
                        <div class="log-entry log-info">Iniciando instalación...</div>
                    </div>
                    
                    <?php
                    if ($_POST['action'] === 'install_system') {
                        $db_host = $_POST['db_host'];
                        $db_user = $_POST['db_user'];
                        $db_pass = $_POST['db_pass'];
                        $db_name = $_POST['db_name'];
                        
                        $installer->clearMessages();
                        
                        // Paso 1: Crear base de datos
                        echo '<script>updateProgress(20, "Creando base de datos...");</script>';
                        $db_created = $installer->createDatabase($db_host, $db_user, $db_pass, $db_name);
                        
                        if ($db_created) {
                            // Paso 2: Insertar datos de ejemplo
                            echo '<script>updateProgress(50, "Insertando datos de ejemplo...");</script>';
                            $installer->insertSampleData();
                            
                            // Paso 3: Crear usuario administrador
                            echo '<script>updateProgress(70, "Creando usuario administrador...");</script>';
                            $admin_created = $installer->createAdminUser(
                                DEFAULT_ADMIN_USER, 
                                DEFAULT_ADMIN_PASS, 
                                DEFAULT_ADMIN_EMAIL, 
                                DEFAULT_ADMIN_NAME
                            );
                            
                            // Paso 4: Actualizar configuraciones
                            echo '<script>updateProgress(85, "Actualizando configuraciones...");</script>';
                            $config_updated = $installer->updateDatabaseConfig($db_host, $db_user, $db_pass, $db_name);
                            $auth_created = $installer->createAuthConfig();
                            
                            // Paso 5: Verificar instalación
                            echo '<script>updateProgress(100, "Verificando instalación...");</script>';
                            $verification = $installer->verifyInstallation();
                            
                            // Mostrar resultados
                            echo '<script>';
                            foreach ($installer->getSuccessMessages() as $message) {
                                echo 'addLogEntry("' . addslashes($message) . '", "success");';
                            }
                            foreach ($installer->getWarnings() as $warning) {
                                echo 'addLogEntry("' . addslashes($warning) . '", "warning");';
                            }
                            foreach ($installer->getErrors() as $error) {
                                echo 'addLogEntry("' . addslashes($error) . '", "error");';
                            }
                            echo '</script>';
                            
                            if ($verification['overall']) {
                                echo '<div class="message-box message-success mt-4">';
                                echo '<i class="fas fa-check-circle me-2"></i>';
                                echo '<strong>¡Instalación completada exitosamente!</strong>';
                                echo '</div>';
                                
                                echo '<div class="text-center mt-4">';
                                echo '<form method="post">';
                                echo '<input type="hidden" name="action" value="complete">';
                                echo '<button type="submit" class="btn btn-installer">';
                                echo '<i class="fas fa-flag-checkered me-2"></i>Finalizar Instalación';
                                echo '</button>';
                                echo '</form>';
                                echo '</div>';
                            } else {
                                echo '<div class="message-box message-error mt-4">';
                                echo '<i class="fas fa-exclamation-triangle me-2"></i>';
                                echo '<strong>La instalación se completó con errores.</strong> Revisa el log para más detalles.';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="message-box message-error mt-4">';
                            echo '<i class="fas fa-exclamation-triangle me-2"></i>';
                            echo '<strong>Error durante la instalación.</strong> No se pudo crear la base de datos.';
                            echo '</div>';
                        }
                    }
                    ?>

                <?php elseif ($step === 'complete'): ?>
                    <!-- Paso 5: Instalación Completada -->
                    <div class="text-center">
                        <i class="fas fa-check-circle fa-4x text-success mb-4"></i>
                        <h2>¡Instalación Completada!</h2>
                        <p class="lead">El sistema ha sido instalado exitosamente y está listo para usar.</p>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5><i class="fas fa-user-shield me-2"></i>Acceso Administrativo</h5>
                                        <p><strong>Usuario:</strong> <?= DEFAULT_ADMIN_USER ?></p>
                                        <p><strong>Contraseña:</strong> <?= DEFAULT_ADMIN_PASS ?></p>
                                        <a href="admin/login.php" class="btn btn-primary">
                                            <i class="fas fa-sign-in-alt me-2"></i>Ir al Panel de Administración
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5><i class="fas fa-store me-2"></i>Sitio Web</h5>
                                        <p>Tu tienda online está lista para recibir pedidos</p>
                                        <a href="index.php" class="btn btn-success">
                                            <i class="fas fa-external-link-alt me-2"></i>Ver Sitio Web
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning mt-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Importante:</strong> Por seguridad, elimina o renombra la carpeta 'install' y el archivo 'install.php' después de completar la instalación.
                        </div>
                        
                        <div class="mt-4">
                            <h5>Próximos pasos recomendados:</h5>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Cambiar la contraseña del administrador</li>
                                <li><i class="fas fa-check text-success me-2"></i>Configurar información de la empresa</li>
                                <li><i class="fas fa-check text-success me-2"></i>Agregar productos y categorías</li>
                                <li><i class="fas fa-check text-success me-2"></i>Configurar métodos de pago</li>
                                <li><i class="fas fa-check text-success me-2"></i>Personalizar el diseño del sitio</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateProgress(percent, message) {
            const progressBar = document.getElementById('installProgress');
            if (progressBar) {
                progressBar.style.width = percent + '%';
                progressBar.textContent = percent + '%';
            }
            
            if (message) {
                addLogEntry(message, 'info');
            }
        }
        
        function addLogEntry(message, type = 'info') {
            const log = document.getElementById('installLog');
            if (log) {
                const entry = document.createElement('div');
                entry.className = 'log-entry log-' + type;
                entry.textContent = message;
                log.appendChild(entry);
                log.scrollTop = log.scrollHeight;
            }
        }
        
        // Auto-scroll del log
        document.addEventListener('DOMContentLoaded', function() {
            const log = document.getElementById('installLog');
            if (log) {
                log.scrollTop = log.scrollHeight;
            }
        });
    </script>
</body>
</html>
