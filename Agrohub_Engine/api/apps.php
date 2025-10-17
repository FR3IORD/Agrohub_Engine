<?php
/**
 * Agrohub ERP Platform - Apps/Modules API
 * 
 * Handles module management, installation, and configuration
 */

require_once 'config.php';
require_once 'database.php';
require_once 'utils.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class AppsAPI {
    private $db;
    private $utils;
    
    public function __construct() {
        $this->db = new Database();
        $this->utils = new Utils();
    }
    
    /**
     * Route requests based on method and endpoint
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        
        // Remove 'api' and 'apps' from segments
        $segments = array_slice($segments, 2);
        
        try {
            switch ($method) {
                case 'GET':
                    if (empty($segments[0])) {
                        return $this->getModules();
                    } elseif ($segments[0] === 'user') {
                        return $this->getUserModules();
                    } elseif ($segments[0] === 'categories') {
                        return $this->getCategories();
                    } elseif (is_numeric($segments[0])) {
                        return $this->getModule($segments[0]);
                    } elseif (is_numeric($segments[0]) && isset($segments[1]) && $segments[1] === 'settings') {
                        return $this->getModuleSettings($segments[0]);
                    }
                    break;
                    
                case 'POST':
                    if (empty($segments[0])) {
                        return $this->createModule();
                    } elseif (is_numeric($segments[0]) && isset($segments[1])) {
                        if ($segments[1] === 'install') {
                            return $this->installModule($segments[0]);
                        } elseif ($segments[1] === 'uninstall') {
                            return $this->uninstallModule($segments[0]);
                        }
                    }
                    break;
                    
                case 'PUT':
                    if (is_numeric($segments[0])) {
                        if (isset($segments[1]) && $segments[1] === 'settings') {
                            return $this->updateModuleSettings($segments[0]);
                        } else {
                            return $this->updateModule($segments[0]);
                        }
                    }
                    break;
                    
                case 'DELETE':
                    if (is_numeric($segments[0])) {
                        return $this->deleteModule($segments[0]);
                    }
                    break;
            }
            
            return $this->utils->sendError('Endpoint not found', 404);
            
        } catch (Exception $e) {
            error_log('Apps API Error: ' . $e->getMessage());
            return $this->utils->sendError('Internal server error', 500);
        }
    }
    
    /**
     * Get all available modules
     */
    private function getModules() {
        $category = $_GET['category'] ?? null;
        $search = $_GET['search'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM modules WHERE is_active = 1";
        $params = [];
        
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        if ($search) {
            $sql .= " AND (name LIKE ? OR description LIKE ? OR technical_name LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY category, name LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse JSON fields
        foreach ($modules as &$module) {
            $module['dependencies'] = json_decode($module['dependencies'] ?? '[]', true);
            $module['features'] = json_decode($module['features'] ?? '[]', true);
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) FROM modules WHERE is_active = 1";
        $countParams = [];
        
        if ($category) {
            $countSql .= " AND category = ?";
            $countParams[] = $category;
        }
        
        if ($search) {
            $countSql .= " AND (name LIKE ? OR description LIKE ? OR technical_name LIKE ?)";
            $searchTerm = "%$search%";
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
        }
        
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetchColumn();
        
        return $this->utils->sendSuccess([
            'data' => $modules,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * Get user's installed modules
     */
    private function getUserModules() {
        $user = $this->utils->authenticateRequest();
        
        if (!$user) {
            return $this->utils->sendError('Authentication required', 401);
        }
        
        $sql = "SELECT m.*, um.status, um.settings as user_settings, um.installed_at 
                FROM modules m 
                JOIN user_modules um ON m.id = um.module_id 
                WHERE um.user_id = ? AND m.is_active = 1
                ORDER BY um.installed_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user['id']]);
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse JSON fields
        foreach ($modules as &$module) {
            $module['dependencies'] = json_decode($module['dependencies'] ?? '[]', true);
            $module['features'] = json_decode($module['features'] ?? '[]', true);
            $module['user_settings'] = json_decode($module['user_settings'] ?? '{}', true);
        }
        
        return $this->utils->sendSuccess($modules);
    }
    
    /**
     * Get module categories
     */
    private function getCategories() {
        $sql = "SELECT category, COUNT(*) as module_count 
                FROM modules 
                WHERE is_active = 1 
                GROUP BY category 
                ORDER BY category";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->utils->sendSuccess($categories);
    }
    
    /**
     * Get specific module
     */
    private function getModule($moduleId) {
        $stmt = $this->db->prepare("SELECT * FROM modules WHERE id = ? AND is_active = 1");
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$module) {
            return $this->utils->sendError('Module not found', 404);
        }
        
        // Parse JSON fields
        $module['dependencies'] = json_decode($module['dependencies'] ?? '[]', true);
        $module['features'] = json_decode($module['features'] ?? '[]', true);
        
        // Check if user has this module installed
        $user = $this->utils->authenticateRequest();
        if ($user) {
            $stmt = $this->db->prepare("SELECT status, installed_at FROM user_modules WHERE user_id = ? AND module_id = ?");
            $stmt->execute([$user['id'], $moduleId]);
            $userModule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $module['user_status'] = $userModule ? $userModule['status'] : 'not_installed';
            $module['installed_at'] = $userModule['installed_at'] ?? null;
        }
        
        return $this->utils->sendSuccess($module);
    }
    
    /**
     * Install module for user
     */
    private function installModule($moduleId) {
        $user = $this->utils->authenticateRequest();
        
        if (!$user) {
            return $this->utils->sendError('Authentication required', 401);
        }
        
        // Check if module exists
        $stmt = $this->db->prepare("SELECT * FROM modules WHERE id = ? AND is_active = 1");
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$module) {
            return $this->utils->sendError('Module not found', 404);
        }
        
        // Check if already installed
        $stmt = $this->db->prepare("SELECT id FROM user_modules WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$user['id'], $moduleId]);
        if ($stmt->fetch()) {
            return $this->utils->sendError('Module already installed', 409);
        }
        
        // Check dependencies
        $dependencies = json_decode($module['dependencies'] ?? '[]', true);
        if (!empty($dependencies)) {
            $dependencyCheck = $this->checkDependencies($user['id'], $dependencies);
            if (!$dependencyCheck['satisfied']) {
                return $this->utils->sendError('Missing dependencies: ' . implode(', ', $dependencyCheck['missing']), 400);
            }
        }
        
        // Install module
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("INSERT INTO user_modules (user_id, module_id, status, installed_at) VALUES (?, ?, 'active', NOW())");
            $stmt->execute([$user['id'], $moduleId]);
            
            // Log activity
            $this->logActivity($user['id'], "Installed module: {$module['name']}", 'Module Installation');
            
            $this->db->commit();
            
            return $this->utils->sendSuccess(['module_id' => $moduleId], 'Module installed successfully');
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Module installation error: ' . $e->getMessage());
            return $this->utils->sendError('Installation failed', 500);
        }
    }
    
    /**
     * Uninstall module for user
     */
    private function uninstallModule($moduleId) {
        $user = $this->utils->authenticateRequest();
        
        if (!$user) {
            return $this->utils->sendError('Authentication required', 401);
        }
        
        // Check if module is installed
        $stmt = $this->db->prepare("SELECT um.*, m.name FROM user_modules um JOIN modules m ON um.module_id = m.id WHERE um.user_id = ? AND um.module_id = ?");
        $stmt->execute([$user['id'], $moduleId]);
        $userModule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userModule) {
            return $this->utils->sendError('Module not installed', 404);
        }
        
        // Check if other modules depend on this one
        $dependents = $this->checkDependents($user['id'], $moduleId);
        if (!empty($dependents)) {
            return $this->utils->sendError('Cannot uninstall: Required by ' . implode(', ', $dependents), 400);
        }
        
        // Uninstall module
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("DELETE FROM user_modules WHERE user_id = ? AND module_id = ?");
            $stmt->execute([$user['id'], $moduleId]);
            
            // Log activity
            $this->logActivity($user['id'], "Uninstalled module: {$userModule['name']}", 'Module Removal');
            
            $this->db->commit();
            
            return $this->utils->sendSuccess([], 'Module uninstalled successfully');
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Module uninstallation error: ' . $e->getMessage());
            return $this->utils->sendError('Uninstallation failed', 500);
        }
    }
    
    /**
     * Create new module (admin only)
     */
    private function createModule() {
        $user = $this->utils->authenticateRequest();
        
        if (!$user || $user['role'] !== 'admin') {
            return $this->utils->sendError('Admin access required', 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['name', 'technical_name', 'category', 'description'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                return $this->utils->sendError("$field is required", 400);
            }
        }
        
        // Check if technical name is unique
        $stmt = $this->db->prepare("SELECT id FROM modules WHERE technical_name = ?");
        $stmt->execute([$input['technical_name']]);
        if ($stmt->fetch()) {
            return $this->utils->sendError('Technical name already exists', 409);
        }
        
        $data = [
            'name' => $input['name'],
            'technical_name' => $input['technical_name'],
            'category' => $input['category'],
            'description' => $input['description'],
            'icon' => $input['icon'] ?? 'fas fa-cube',
            'color' => $input['color'] ?? 'bg-gray-500',
            'version' => $input['version'] ?? '1.0.0',
            'dependencies' => json_encode($input['dependencies'] ?? []),
            'features' => json_encode($input['features'] ?? []),
            'price' => $input['price'] ?? 0
        ];
        
        $sql = "INSERT INTO modules (name, technical_name, category, description, icon, color, version, dependencies, features, price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute(array_values($data))) {
            $moduleId = $this->db->lastInsertId();
            
            // Log activity
            $this->logActivity($user['id'], "Created module: {$data['name']}", 'Module Management');
            
            return $this->utils->sendSuccess(['module_id' => $moduleId], 'Module created successfully');
        } else {
            return $this->utils->sendError('Failed to create module', 500);
        }
    }
    
    /**
     * Update module (admin only)
     */
    private function updateModule($moduleId) {
        $user = $this->utils->authenticateRequest();
        
        if (!$user || $user['role'] !== 'admin') {
            return $this->utils->sendError('Admin access required', 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Check if module exists
        $stmt = $this->db->prepare("SELECT * FROM modules WHERE id = ?");
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$module) {
            return $this->utils->sendError('Module not found', 404);
        }
        
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['name', 'description', 'icon', 'color', 'version', 'dependencies', 'features', 'price', 'is_active'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                if (in_array($field, ['dependencies', 'features'])) {
                    $updateFields[] = "$field = ?";
                    $params[] = json_encode($input[$field]);
                } else {
                    $updateFields[] = "$field = ?";
                    $params[] = $input[$field];
                }
            }
        }
        
        if (empty($updateFields)) {
            return $this->utils->sendError('No valid fields to update', 400);
        }
        
        $updateFields[] = "updated_at = NOW()";
        $params[] = $moduleId;
        
        $sql = "UPDATE modules SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($params)) {
            // Log activity
            $this->logActivity($user['id'], "Updated module: {$module['name']}", 'Module Management');
            
            return $this->utils->sendSuccess([], 'Module updated successfully');
        } else {
            return $this->utils->sendError('Failed to update module', 500);
        }
    }
    
    /**
     * Delete module (admin only)
     */
    private function deleteModule($moduleId) {
        $user = $this->utils->authenticateRequest();
        
        if (!$user || $user['role'] !== 'admin') {
            return $this->utils->sendError('Admin access required', 403);
        }
        
        // Check if module exists
        $stmt = $this->db->prepare("SELECT * FROM modules WHERE id = ?");
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$module) {
            return $this->utils->sendError('Module not found', 404);
        }
        
        // Check if module is installed by any users
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_modules WHERE module_id = ?");
        $stmt->execute([$moduleId]);
        $installCount = $stmt->fetchColumn();
        
        if ($installCount > 0) {
            return $this->utils->sendError('Cannot delete module: Currently installed by users', 400);
        }
        
        // Delete module
        $stmt = $this->db->prepare("DELETE FROM modules WHERE id = ?");
        if ($stmt->execute([$moduleId])) {
            // Log activity
            $this->logActivity($user['id'], "Deleted module: {$module['name']}", 'Module Management');
            
            return $this->utils->sendSuccess([], 'Module deleted successfully');
        } else {
            return $this->utils->sendError('Failed to delete module', 500);
        }
    }
    
    /**
     * Update module settings for user
     */
    private function updateModuleSettings($moduleId) {
        $user = $this->utils->authenticateRequest();
        
        if (!$user) {
            return $this->utils->sendError('Authentication required', 401);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Check if user has this module installed
        $stmt = $this->db->prepare("SELECT id FROM user_modules WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$user['id'], $moduleId]);
        
        if (!$stmt->fetch()) {
            return $this->utils->sendError('Module not installed', 404);
        }
        
        // Update settings
        $settings = json_encode($input ?? []);
        
        $stmt = $this->db->prepare("UPDATE user_modules SET settings = ?, updated_at = NOW() WHERE user_id = ? AND module_id = ?");
        
        if ($stmt->execute([$settings, $user['id'], $moduleId])) {
            // Log activity
            $this->logActivity($user['id'], "Updated module settings", 'Module Configuration');
            
            return $this->utils->sendSuccess([], 'Module settings updated successfully');
        } else {
            return $this->utils->sendError('Failed to update settings', 500);
        }
    }
    
    /**
     * Check if user has all required dependencies installed
     */
    private function checkDependencies($userId, $dependencies) {
        if (empty($dependencies)) {
            return ['satisfied' => true, 'missing' => []];
        }
        
        $placeholders = implode(',', array_fill(0, count($dependencies), '?'));
        $sql = "SELECT m.technical_name 
                FROM modules m 
                JOIN user_modules um ON m.id = um.module_id 
                WHERE um.user_id = ? AND m.technical_name IN ($placeholders) AND um.status = 'active'";
        
        $params = array_merge([$userId], $dependencies);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $installed = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_diff($dependencies, $installed);
        
        return [
            'satisfied' => empty($missing),
            'missing' => array_values($missing)
        ];
    }
    
    /**
     * Check which modules depend on a given module
     */
    private function checkDependents($userId, $moduleId) {
        // Get the technical name of the module
        $stmt = $this->db->prepare("SELECT technical_name FROM modules WHERE id = ?");
        $stmt->execute([$moduleId]);
        $technicalName = $stmt->fetchColumn();
        
        if (!$technicalName) {
            return [];
        }
        
        // Find modules that depend on this one
        $sql = "SELECT m.name 
                FROM modules m 
                JOIN user_modules um ON m.id = um.module_id 
                WHERE um.user_id = ? AND um.status = 'active' AND JSON_SEARCH(m.dependencies, 'one', ?) IS NOT NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $technicalName]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Log user activity
     */
    private function logActivity($userId, $description, $type = 'General') {
        $stmt = $this->db->prepare("INSERT INTO activities (user_id, action, description, module, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $userId,
            $description,
            $description,
            $type,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
}

// Handle the request
$appsAPI = new AppsAPI();
echo $appsAPI->handleRequest();
?>