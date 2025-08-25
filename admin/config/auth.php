<?php
session_start();
require_once 'database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = AdminDB::getInstance();
    }
    
    // Login user
    public function login($username, $password) {
        $user = $this->db->fetchOne(
            "SELECT * FROM admin_users WHERE (username = ? OR email = ?) AND status = 'active'",
            [$username, $username]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $this->db->update(
                'admin_users',
                ['last_login' => date('Y-m-d H:i:s')],
                'id = ?',
                [$user['id']]
            );
            
            // Create session
            $_SESSION['admin_user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ];
            
            // Log session
            $this->logSession($user['id']);
            
            return true;
        }
        
        return false;
    }
    
    // Logout user
    public function logout() {
        if (isset($_SESSION['admin_user'])) {
            // Remove session from database
            $this->db->delete(
                'admin_sessions',
                'user_id = ? AND id = ?',
                [$_SESSION['admin_user']['id'], session_id()]
            );
            
            // Destroy session
            session_destroy();
        }
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['admin_user']);
    }
    
    // Get current user
    public function getCurrentUser() {
        return $_SESSION['admin_user'] ?? null;
    }
    
    // Check user role
    public function hasRole($role) {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        $roleHierarchy = [
            'admin' => 5,
            'manager' => 4,
            'cashier' => 3,
            'kitchen' => 2,
            'delivery' => 1
        ];
        
        $userLevel = $roleHierarchy[$user['role']] ?? 0;
        $requiredLevel = $roleHierarchy[$role] ?? 0;
        
        return $userLevel >= $requiredLevel;
    }
    
    // Require login
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    // Require specific role
    public function requireRole($role) {
        $this->requireLogin();
        if (!$this->hasRole($role)) {
            header('Location: index.php?error=insufficient_permissions');
            exit;
        }
    }
    
    // Log session
    private function logSession($userId) {
        $this->db->query(
            "INSERT INTO admin_sessions (id, user_id, ip_address, user_agent, last_activity) 
             VALUES (?, ?, ?, ?, NOW()) 
             ON DUPLICATE KEY UPDATE last_activity = NOW()",
            [
                session_id(),
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );
    }
    
    // Clean old sessions
    public function cleanOldSessions() {
        $this->db->delete(
            'admin_sessions',
            'last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
    }
}

// Global auth instance
$auth = new Auth();
?>
