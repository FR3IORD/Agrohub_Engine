<?php
/**
 * Violations Module - Authentication
 */

if (!defined('VIOLATIONS_MODULE')) {
    die('Direct access not permitted');
}

/**
 * Check if user is authenticated
 */
function violationsIsAuthenticated() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }
    
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        return violationsValidateToken($token);
    }
    
    return false;
}

/**
 * Validate JWT token
 */
function violationsValidateToken($token) {
    if (empty($token)) {
        return false;
    }
    
    try {
        if (class_exists('Utils')) {
            $utils = new Utils();
            $user = $utils->authenticateRequest();
            return $user !== null;
        }
        
        $db = violationsGetDB();
        $stmt = $db->prepare("
            SELECT u.id 
            FROM users u
            INNER JOIN sessions s ON u.id = s.user_id
            WHERE s.token = ? AND s.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
        
    } catch (Exception $e) {
        error_log("Violations Auth Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current authenticated user
 */
function violationsGetCurrentUser() {
    if (class_exists('Utils')) {
        $utils = new Utils();
        return $utils->authenticateRequest();
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $db = violationsGetDB();
        $stmt = $db->prepare("
            SELECT id, username, name, email, role, is_active, created_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Violations Get User Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Require authentication
 */
function violationsRequireAuth() {
    $user = violationsGetCurrentUser();
    
    if (!$user) {
        header('Location: ../../index.html');
        exit();
    }
    
    return $user;
}

/**
 * Check module access
 */
function violationsHasModuleAccess($userId) {
    try {
        $db = violationsGetDB();
        $stmt = $db->prepare("
            SELECT uaa.is_enabled 
            FROM user_app_access uaa
            INNER JOIN modules m ON uaa.module_id = m.id
            WHERE uaa.user_id = ? AND m.technical_name = 'violations' AND uaa.is_enabled = 1
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->rowCount() > 0;
        
    } catch (Exception $e) {
        error_log("Violations Module Access Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check specific permission
 */
function violationsHasPermission($user, $permission) {
    if (!$user) {
        return false;
    }
    
    if ($user['role'] === 'admin') {
        return true;
    }
    
    $permissions = violationsGetUserPermissions($user['id'], $user['role']);
    
    return isset($permissions[$permission]) && $permissions[$permission] === true;
}

/**
 * Require specific permission
 */
function violationsRequirePermission($user, $permission, $errorMessage = 'Access denied') {
    if (!violationsHasPermission($user, $permission)) {
        http_response_code(403);
        
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $errorMessage
            ]);
        } else {
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access Denied</title></head><body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f5f6f8;"><div style="background:white;padding:3rem;border-radius:16px;text-align:center;max-width:400px;"><h1 style="color:#1f2937;margin-bottom:1rem;">🚫 Access Denied</h1><p style="color:#6b7280;">' . htmlspecialchars($errorMessage) . '</p><a href="javascript:history.back()" style="display:inline-block;margin-top:1.5rem;padding:0.75rem 2rem;background:#7c3aed;color:white;text-decoration:none;border-radius:8px;">← Go Back</a></div></body></html>';
        }
        
        exit();
    }
}

return true;