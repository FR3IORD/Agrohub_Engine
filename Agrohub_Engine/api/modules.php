<?php
/**
 * Modules API - Fixed with proper user access control
 */

// CRITICAL: Disable all output before JSON
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header FIRST
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files - suppress any output
ob_start();
require_once 'config.php';
require_once 'database.php';
require_once 'utils.php';
ob_end_clean();

try {
    $utils = new Utils();
    $db = Database::getInstance();
    
    // Authenticate user
    $user = $utils->authenticateRequest();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            getModules($db, $user, $utils);
            break;
        case 'POST':
            installModule($db, $user, $utils);
            break;
        case 'DELETE':
            uninstallModule($db, $user, $utils);
            break;
        default:
            $utils->sendError('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log('Modules API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
    exit();
}

function getModules($db, $user, $utils) {
    try {
        // If authenticated and admin - show all modules with install status
        if ($user && $user['role'] === 'admin') {
            $sql = "SELECT m.*, 
                           CASE WHEN um.user_id IS NOT NULL THEN 1 ELSE 0 END as installed,
                           um.status as install_status,
                           um.installed_at
                    FROM modules m 
                    LEFT JOIN user_modules um ON m.id = um.module_id AND um.user_id = ?
                    WHERE m.is_active = 1 
                    ORDER BY m.category, m.name";
            $stmt = $db->prepare($sql);
            $stmt->execute([$user['id']]);
        } 
        // ✅ FIXED: Authenticated non-admin - only show modules they have access to
        else if ($user) {
            $sql = "SELECT m.*, 
                           1 as installed, 
                           'active' as install_status, 
                           uaa.granted_at as installed_at
                    FROM modules m 
                    INNER JOIN user_app_access uaa ON m.id = uaa.module_id 
                    WHERE uaa.user_id = ?
                    AND uaa.is_enabled = 1
                    AND m.is_active = 1
                    ORDER BY m.category, m.name";
            $stmt = $db->prepare($sql);
            $stmt->execute([$user['id']]);
        }
        // Public visitor - show all available modules
        else {
            $sql = "SELECT m.*, 0 as installed, 'available' as install_status, NULL as installed_at
                    FROM modules m
                    WHERE m.is_active = 1
                    ORDER BY m.category, m.name";
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }
        
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format modules
        $formatted_modules = array_map(function($module) {
            return [
                'id' => $module['technical_name'],
                'name' => $module['name'],
                'description' => $module['description'],
                'icon' => $module['icon'] ?: 'fas fa-cube',
                'category' => $module['category'],
                'color' => mapCategoryToGradient($module['category']),
                'installed' => (bool)$module['installed'],
                'version' => $module['version'],
                'features' => json_decode($module['features'] ?? '[]', true),
                'dependencies' => json_decode($module['dependencies'] ?? '[]', true),
                'status' => $module['install_status'] ?? 'available',
                'is_core' => (bool)($module['is_core'] ?? 0),
                'price' => floatval($module['price'] ?? 0),
                'installed_at' => $module['installed_at'] ?? null,
                'technical_name' => $module['technical_name']
            ];
        }, $modules);
        
        // Return JSON response
        $utils->sendSuccess([
            'modules' => $formatted_modules,
            'total_count' => count($formatted_modules),
            'user_role' => $user['role'] ?? null,
            'user_id' => $user['id'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log("Get modules error: " . $e->getMessage());
        $utils->sendError('Failed to load modules', 500);
    }
}

function installModule($db, $user, $utils) {
    if (!$user || $user['role'] !== 'admin') {
        $utils->sendError('Only administrators can install modules', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $module_technical_name = $input['module_id'] ?? '';
    
    if (empty($module_technical_name)) {
        $utils->sendError('Module ID is required', 400);
    }
    
    try {
        $pdo = $db->getPDO();
        $pdo->beginTransaction();
        
        // Get module
        $stmt = $db->prepare("SELECT id, name, technical_name, dependencies FROM modules WHERE technical_name = ? AND is_active = 1");
        $stmt->execute([$module_technical_name]);
        $module = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$module) {
            $pdo->rollBack();
            $utils->sendError('Module not found', 404);
        }
        
        // Check if already installed in user_modules (admin's personal install)
        $stmt = $db->prepare("SELECT id FROM user_modules WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$user['id'], $module['id']]);
        
        if (!$stmt->fetch()) {
            // Install for admin
            $stmt = $db->prepare("
                INSERT INTO user_modules (user_id, module_id, company_id, status, installed_at) 
                VALUES (?, ?, NULL, 'active', NOW())
            ");
            $stmt->execute([$user['id'], $module['id']]);
        }
        
        // ✅ ALSO: Grant admin access in user_app_access
        $stmt = $db->prepare("SELECT id FROM user_app_access WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$user['id'], $module['id']]);
        
        if (!$stmt->fetch()) {
            $stmt = $db->prepare("
                INSERT INTO user_app_access (user_id, module_id, is_enabled, granted_by, granted_at) 
                VALUES (?, ?, 1, ?, NOW())
            ");
            $stmt->execute([$user['id'], $module['id'], $user['id']]);
        } else {
            // Update if exists
            $stmt = $db->prepare("UPDATE user_app_access SET is_enabled = 1, granted_at = NOW() WHERE user_id = ? AND module_id = ?");
            $stmt->execute([$user['id'], $module['id']]);
        }
        
        // Log activity
        logActivity($db, $user['id'], 'module_install', "Installed module: {$module['name']}", $module['technical_name']);
        
        $pdo->commit();
        
        $utils->sendSuccess([
            'message' => "Module '{$module['name']}' installed successfully",
            'module' => [
                'id' => $module['technical_name'],
                'name' => $module['name'],
                'installed' => true,
                'status' => 'active'
            ]
        ]);
        
    } catch (Exception $e) {
        if (isset($pdo)) $pdo->rollBack();
        error_log("Install error: " . $e->getMessage());
        $utils->sendError('Installation failed: ' . $e->getMessage(), 500);
    }
}

function uninstallModule($db, $user, $utils) {
    if (!$user || $user['role'] !== 'admin') {
        $utils->sendError('Only administrators can uninstall modules', 403);
    }
    
    $module_technical_name = $_GET['module_id'] ?? '';
    
    if (empty($module_technical_name)) {
        $utils->sendError('Module ID is required', 400);
    }
    
    try {
        $pdo = $db->getPDO();
        $pdo->beginTransaction();
        
        $stmt = $db->prepare("
            SELECT m.id, m.name, m.technical_name, m.is_core 
            FROM modules m 
            WHERE m.technical_name = ?
        ");
        $stmt->execute([$module_technical_name]);
        $module = $stmt->fetch();
        
        if (!$module) {
            $pdo->rollBack();
            $utils->sendError('Module not found', 404);
        }
        
        if ($module['is_core']) {
            $pdo->rollBack();
            $utils->sendError('Cannot uninstall core module', 400);
        }
        
        // Remove from user_modules
        $stmt = $db->prepare("DELETE FROM user_modules WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$user['id'], $module['id']]);
        
        // ✅ ALSO: Revoke access in user_app_access
        $stmt = $db->prepare("UPDATE user_app_access SET is_enabled = 0 WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$user['id'], $module['id']]);
        
        logActivity($db, $user['id'], 'module_uninstall', "Uninstalled module: {$module['name']}", $module['technical_name']);
        
        $pdo->commit();
        
        $utils->sendSuccess([
            'message' => "Module '{$module['name']}' uninstalled successfully"
        ]);
        
    } catch (Exception $e) {
        if (isset($pdo)) $pdo->rollBack();
        error_log("Uninstall error: " . $e->getMessage());
        $utils->sendError('Uninstallation failed: ' . $e->getMessage(), 500);
    }
}

function mapCategoryToGradient($category) {
    $gradients = [
        'Website' => 'from-blue-400 to-blue-600',
        'Sales' => 'from-green-400 to-green-600', 
        'Finance' => 'from-purple-400 to-purple-600',
        'Services' => 'from-red-400 to-red-600',
        'Human Resources' => 'from-orange-400 to-orange-600',
        'Supply Chain' => 'from-indigo-400 to-indigo-600',
        'Marketing' => 'from-pink-400 to-pink-600',
        'Productivity' => 'from-teal-400 to-teal-600',
        'Customizations' => 'from-gray-400 to-gray-600'
    ];
    
    return $gradients[$category] ?? 'from-blue-400 to-blue-600';
}

function logActivity($db, $user_id, $action, $description, $module = null) {
    try {
        $stmt = $db->prepare("INSERT INTO activities (user_id, action, description, module, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $user_id,
            $action,
            $description,
            $module,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}
?>