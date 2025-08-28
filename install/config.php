<?php
/**
 * Configuración del Instalador del Sistema de Restaurante
 * Este archivo contiene las configuraciones y funciones necesarias para la instalación
 */

// Configuración de la instalación
define('INSTALLER_VERSION', '1.0.0');
define('SYSTEM_NAME', 'Sistema de Restaurante - Sabor Latino');
define('MIN_PHP_VERSION', '7.4.0');
define('REQUIRED_EXTENSIONS', ['mysqli', 'pdo', 'pdo_mysql', 'json', 'mbstring']);

// Configuración por defecto de la base de datos
define('DEFAULT_DB_HOST', 'localhost');
define('DEFAULT_DB_USER', 'root');
define('DEFAULT_DB_PASS', '');
define('DEFAULT_DB_NAME', 'restaurante_pedidos');

// Configuración del usuario administrador por defecto
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin123');
define('DEFAULT_ADMIN_EMAIL', 'admin@restaurante.com');
define('DEFAULT_ADMIN_NAME', 'Administrador Principal');

/**
 * Clase principal del instalador
 */
class RestaurantInstaller {
    private $db_connection = null;
    private $errors = [];
    private $warnings = [];
    private $success_messages = [];
    
    /**
     * Verificar requisitos del sistema
     */
    public function checkSystemRequirements() {
        $requirements = [
            'php_version' => false,
            'extensions' => [],
            'permissions' => [],
            'overall' => false
        ];
        
        // Verificar versión de PHP
        if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=')) {
            $requirements['php_version'] = true;
            $this->success_messages[] = "✓ PHP " . PHP_VERSION . " (requerido: " . MIN_PHP_VERSION . "+)";
        } else {
            $this->errors[] = "✗ PHP " . PHP_VERSION . " - Se requiere " . MIN_PHP_VERSION . " o superior";
        }
        
        // Verificar extensiones requeridas
        foreach (REQUIRED_EXTENSIONS as $extension) {
            if (extension_loaded($extension)) {
                $requirements['extensions'][$extension] = true;
                $this->success_messages[] = "✓ Extensión {$extension} disponible";
            } else {
                $requirements['extensions'][$extension] = false;
                $this->errors[] = "✗ Extensión {$extension} no disponible";
            }
        }
        
        // Verificar permisos de escritura
        $directories_to_check = [
            'config/',
            'uploads/',
            'uploads/products/',
            'uploads/additionals/',
            'assets/images/banners/'
        ];
        
        foreach ($directories_to_check as $dir) {
            if (!file_exists($dir)) {
                if (mkdir($dir, 0755, true)) {
                    $requirements['permissions'][$dir] = true;
                    $this->success_messages[] = "✓ Directorio {$dir} creado con permisos de escritura";
                } else {
                    $requirements['permissions'][$dir] = false;
                    $this->errors[] = "✗ No se pudo crear el directorio {$dir}";
                }
            } else {
                if (is_writable($dir)) {
                    $requirements['permissions'][$dir] = true;
                    $this->success_messages[] = "✓ Directorio {$dir} tiene permisos de escritura";
                } else {
                    $requirements['permissions'][$dir] = false;
                    $this->errors[] = "✗ Directorio {$dir} no tiene permisos de escritura";
                }
            }
        }
        
        // Verificar si config/database.php es escribible
        if (file_exists('config/database.php')) {
            if (is_writable('config/database.php')) {
                $requirements['permissions']['config/database.php'] = true;
                $this->success_messages[] = "✓ Archivo config/database.php es escribible";
            } else {
                $requirements['permissions']['config/database.php'] = false;
                $this->errors[] = "✗ Archivo config/database.php no es escribible";
            }
        }
        
        // Determinar si todos los requisitos se cumplen
        $requirements['overall'] = empty($this->errors);
        
        return $requirements;
    }
    
    /**
     * Probar conexión a la base de datos
     */
    public function testDatabaseConnection($host, $user, $pass, $dbname = null) {
        try {
            // Intentar conexión sin especificar base de datos
            $this->db_connection = new mysqli($host, $user, $pass);
            
            if ($this->db_connection->connect_error) {
                $this->errors[] = "Error de conexión: " . $this->db_connection->connect_error;
                return false;
            }
            
            $this->success_messages[] = "✓ Conexión al servidor MySQL exitosa";
            
            // Si se especifica una base de datos, intentar seleccionarla
            if ($dbname) {
                if ($this->db_connection->select_db($dbname)) {
                    $this->success_messages[] = "✓ Base de datos '{$dbname}' seleccionada";
                } else {
                    $this->warnings[] = "⚠ Base de datos '{$dbname}' no existe (se creará durante la instalación)";
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Error de conexión: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Crear base de datos y ejecutar esquema
     */
    public function createDatabase($host, $user, $pass, $dbname) {
        try {
            if (!$this->db_connection) {
                if (!$this->testDatabaseConnection($host, $user, $pass)) {
                    return false;
                }
            }
            
            // Crear base de datos si no existe
            $sql = "CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            if ($this->db_connection->query($sql)) {
                $this->success_messages[] = "✓ Base de datos '{$dbname}' creada/verificada";
            } else {
                $this->errors[] = "✗ Error creando base de datos: " . $this->db_connection->error;
                return false;
            }
            
            // Seleccionar base de datos
            if (!$this->db_connection->select_db($dbname)) {
                $this->errors[] = "✗ Error seleccionando base de datos: " . $this->db_connection->error;
                return false;
            }
            
            // Ejecutar esquema de base de datos
            $schema_file = __DIR__ . '/database.sql';
            if (!file_exists($schema_file)) {
                $this->errors[] = "✗ Archivo de esquema no encontrado: {$schema_file}";
                return false;
            }
            
            $schema_sql = file_get_contents($schema_file);
            if ($schema_sql === false) {
                $this->errors[] = "✗ Error leyendo archivo de esquema";
                return false;
            }
            
            // Ejecutar múltiples consultas
            if ($this->db_connection->multi_query($schema_sql)) {
                do {
                    if ($result = $this->db_connection->store_result()) {
                        $result->free();
                    }
                } while ($this->db_connection->next_result());
                
                $this->success_messages[] = "✓ Esquema de base de datos ejecutado exitosamente";
            } else {
                $this->errors[] = "✗ Error ejecutando esquema: " . $this->db_connection->error;
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Error creando base de datos: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Insertar datos de ejemplo
     */
    public function insertSampleData() {
        try {
            $sample_data_file = __DIR__ . '/sample_data.sql';
            if (!file_exists($sample_data_file)) {
                $this->warnings[] = "⚠ Archivo de datos de ejemplo no encontrado";
                return true; // No es crítico
            }
            
            $sample_sql = file_get_contents($sample_data_file);
            if ($sample_sql === false) {
                $this->warnings[] = "⚠ Error leyendo archivo de datos de ejemplo";
                return true; // No es crítico
            }
            
            // Ejecutar múltiples consultas
            if ($this->db_connection->multi_query($sample_sql)) {
                do {
                    if ($result = $this->db_connection->store_result()) {
                        $result->free();
                    }
                } while ($this->db_connection->next_result());
                
                $this->success_messages[] = "✓ Datos de ejemplo insertados exitosamente";
            } else {
                $this->warnings[] = "⚠ Error insertando datos de ejemplo: " . $this->db_connection->error;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->warnings[] = "Error insertando datos de ejemplo: " . $e->getMessage();
            return true; // No es crítico
        }
    }
    
    /**
     * Crear usuario administrador
     */
    public function createAdminUser($username, $password, $email, $full_name) {
        try {
            // Verificar si ya existe un usuario admin
            $check_sql = "SELECT id FROM admin_users WHERE username = ? OR email = ?";
            $stmt = $this->db_connection->prepare($check_sql);
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $this->warnings[] = "⚠ Usuario administrador ya existe";
                return true;
            }
            
            // Crear nuevo usuario administrador
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO admin_users (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')";
            $stmt = $this->db_connection->prepare($insert_sql);
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $full_name);
            
            if ($stmt->execute()) {
                $this->success_messages[] = "✓ Usuario administrador creado exitosamente";
                $this->success_messages[] = "  - Usuario: {$username}";
                $this->success_messages[] = "  - Email: {$email}";
                return true;
            } else {
                $this->errors[] = "✗ Error creando usuario administrador: " . $stmt->error;
                return false;
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Error creando usuario administrador: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Actualizar archivo de configuración de base de datos
     */
    public function updateDatabaseConfig($host, $user, $pass, $dbname) {
        try {
            $config_content = "<?php
// Database configuration
define('DB_HOST', '{$host}');
define('DB_USER', '{$user}');
define('DB_PASS', '{$pass}');
define('DB_NAME', '{$dbname}');

// Create PDO connection
try {
    \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\", DB_USER, DB_PASS);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException \$e) {
    die(\"Connection failed: \" . \$e->getMessage());
}

// Create MySQLi connection (for backward compatibility)
function getDBConnection() {
    \$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if (\$conn->connect_error) {
        die(\"Connection failed: \" . \$conn->connect_error);
    }
    
    return \$conn;
}
?>";
            
            $config_file = 'config/database.php';
            if (file_put_contents($config_file, $config_content)) {
                $this->success_messages[] = "✓ Archivo de configuración actualizado: {$config_file}";
                return true;
            } else {
                $this->errors[] = "✗ Error actualizando archivo de configuración";
                return false;
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Error actualizando configuración: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Crear archivo de configuración de autenticación
     */
    public function createAuthConfig() {
        try {
            $auth_config = "<?php
/**
 * Authentication Configuration
 */

session_start();

class Auth {
    private \$db;
    
    public function __construct() {
        require_once __DIR__ . '/../config/database.php';
        \$this->db = getDBConnection();
    }
    
    public function login(\$username, \$password) {
        \$stmt = \$this->db->prepare(\"SELECT id, username, email, password, full_name, role, status FROM admin_users WHERE (username = ? OR email = ?) AND status = 'active'\");
        \$stmt->bind_param(\"ss\", \$username, \$username);
        \$stmt->execute();
        \$result = \$stmt->get_result();
        
        if (\$user = \$result->fetch_assoc()) {
            if (password_verify(\$password, \$user['password'])) {
                \$_SESSION['admin_user'] = [
                    'id' => \$user['id'],
                    'username' => \$user['username'],
                    'email' => \$user['email'],
                    'full_name' => \$user['full_name'],
                    'role' => \$user['role']
                ];
                
                // Update last login
                \$update_stmt = \$this->db->prepare(\"UPDATE admin_users SET last_login = NOW() WHERE id = ?\");
                \$update_stmt->bind_param(\"i\", \$user['id']);
                \$update_stmt->execute();
                
                return true;
            }
        }
        
        return false;
    }
    
    public function logout() {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    public function isLoggedIn() {
        return isset(\$_SESSION['admin_user']);
    }
    
    public function requireLogin() {
        if (!\$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public function getUser() {
        return \$_SESSION['admin_user'] ?? null;
    }
    
    public function hasRole(\$role) {
        \$user = \$this->getUser();
        return \$user && \$user['role'] === \$role;
    }
    
    public function hasAnyRole(\$roles) {
        \$user = \$this->getUser();
        return \$user && in_array(\$user['role'], \$roles);
    }
}

\$auth = new Auth();
?>";
            
            $auth_file = 'admin/config/auth.php';
            if (!file_exists('admin/config/')) {
                mkdir('admin/config/', 0755, true);
            }
            
            if (file_put_contents($auth_file, $auth_config)) {
                $this->success_messages[] = "✓ Archivo de autenticación creado: {$auth_file}";
                return true;
            } else {
                $this->errors[] = "✗ Error creando archivo de autenticación";
                return false;
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Error creando configuración de autenticación: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Verificar instalación
     */
    public function verifyInstallation() {
        $verification = [
            'database' => false,
            'tables' => [],
            'admin_user' => false,
            'config_files' => [],
            'overall' => false
        ];
        
        try {
            // Verificar conexión a base de datos
            if ($this->db_connection && $this->db_connection->ping()) {
                $verification['database'] = true;
                $this->success_messages[] = "✓ Conexión a base de datos verificada";
            } else {
                $this->errors[] = "✗ No hay conexión a la base de datos";
                return $verification;
            }
            
            // Verificar tablas principales
            $required_tables = [
                'products', 'categories', 'orders', 'order_items', 
                'admin_users', 'customers', 'payment_methods', 
                'banners', 'company_settings'
            ];
            
            foreach ($required_tables as $table) {
                $result = $this->db_connection->query("SHOW TABLES LIKE '{$table}'");
                if ($result && $result->num_rows > 0) {
                    $verification['tables'][$table] = true;
                    $this->success_messages[] = "✓ Tabla '{$table}' existe";
                } else {
                    $verification['tables'][$table] = false;
                    $this->errors[] = "✗ Tabla '{$table}' no existe";
                }
            }
            
            // Verificar usuario administrador
            $admin_result = $this->db_connection->query("SELECT COUNT(*) as count FROM admin_users WHERE role = 'admin' AND status = 'active'");
            if ($admin_result) {
                $admin_count = $admin_result->fetch_assoc()['count'];
                if ($admin_count > 0) {
                    $verification['admin_user'] = true;
                    $this->success_messages[] = "✓ Usuario administrador encontrado";
                } else {
                    $this->errors[] = "✗ No se encontró usuario administrador";
                }
            }
            
            // Verificar archivos de configuración
            $config_files = [
                'config/database.php',
                'admin/config/auth.php'
            ];
            
            foreach ($config_files as $file) {
                if (file_exists($file)) {
                    $verification['config_files'][$file] = true;
                    $this->success_messages[] = "✓ Archivo de configuración '{$file}' existe";
                } else {
                    $verification['config_files'][$file] = false;
                    $this->errors[] = "✗ Archivo de configuración '{$file}' no existe";
                }
            }
            
            // Verificación general
            $verification['overall'] = empty($this->errors);
            
        } catch (Exception $e) {
            $this->errors[] = "Error verificando instalación: " . $e->getMessage();
        }
        
        return $verification;
    }
    
    /**
     * Obtener errores
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Obtener advertencias
     */
    public function getWarnings() {
        return $this->warnings;
    }
    
    /**
     * Obtener mensajes de éxito
     */
    public function getSuccessMessages() {
        return $this->success_messages;
    }
    
    /**
     * Limpiar mensajes
     */
    public function clearMessages() {
        $this->errors = [];
        $this->warnings = [];
        $this->success_messages = [];
    }
}
?>
