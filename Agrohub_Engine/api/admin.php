<?php
// =====================================================
// Agrohub Admin Panel API - COMPLETE FIXED VERSION
// =====================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// JSON error handler
function jsonErrorHandler($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true;
}
set_error_handler('jsonErrorHandler');

// JSON exception handler
function jsonExceptionHandler($exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
    exit;
}
set_exception_handler('jsonExceptionHandler');

// Database connection
try {
    $dbPath = __DIR__ . '/database.php';
    if (!file_exists($dbPath)) {
        throw new Exception("Database file not found");
    }
    
    require_once $dbPath;
    $db = Database::getInstance()->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    error_log('Database error: ' . $e->getMessage());
    sendError('Database connection failed', 500);
}

// =====================================================
// Verify admin authentication
// =====================================================
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (empty($token)) {
    sendError('Unauthorized - No token provided', 401);
}

try {
    // Decode JWT token (simple version - you should use proper JWT library)
    $tokenParts = explode('.', $token);
    if (count($tokenParts) === 3) {
        $payload = json_decode(base64_decode($tokenParts[1]), true);
        $tokenUserId = $payload['user_id'] ?? null;
    } else {
        $tokenUserId = null;
    }
    
    // Fallback for testing
    if (!$tokenUserId) {
        session_start();
        $tokenUserId = $_SESSION['user_id'] ?? 1;
    }
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$tokenUserId]);
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminUser) {
        sendError('User not found', 401);
    }
    
    if (strtolower($adminUser['role']) !== 'admin') {
        sendError('Access denied - Admin privileges required', 403);
    }
    
} catch (Exception $e) {
    error_log('Authentication error: ' . $e->getMessage());
    sendError('Authentication failed', 401);
}

// =====================================================
// Router - FIXED TO HANDLE BOTH GET AND POST
// =====================================================

// Get action from GET or POST
$action = $_GET['action'] ?? '';

// If no action in GET, check POST body
if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $postData = json_decode($rawInput, true);
        $action = $postData['action'] ?? '';
    }
}

// Log the action for debugging
error_log("Admin API Action: '$action' | Method: {$_SERVER['REQUEST_METHOD']}");

if (empty($action)) {
    error_log("No action provided. GET: " . print_r($_GET, true));
    sendError('No action specified', 400);
}

try {
    switch ($action) {
        // ==================== USERS ====================
        case 'get_users':
            getUsers($db);
            break;
        
        case 'create_user':
            createUser($db, $adminUser);
            break;
        
        case 'update_user':
            updateUser($db, $adminUser);
            break;
        
        case 'delete_user':
            deleteUser($db, $adminUser);
            break;
        
        case 'toggle_user_status':
            toggleUserStatus($db, $adminUser);
            break;
        
        // ==================== APPS ====================
        case 'get_all_apps':
            getAllApps($db);
            break;
        
        case 'create_app':
            createApp($db, $adminUser);
            break;
        
        case 'update_app':
            updateApp($db, $adminUser);
            break;
        
        case 'delete_app':
            deleteApp($db, $adminUser);
            break;
        
        case 'toggle_app_status':
            toggleAppStatus($db, $adminUser);
            break;
        
        // ==================== APP ACCESS ====================
        case 'get_user_apps':
            getUserApps($db);
            break;
        
        case 'grant_app_access':
            grantAppAccess($db, $adminUser);
            break;
        
        case 'revoke_app_access':
            revokeAppAccess($db, $adminUser);
            break;
        
        // ==================== ACTIVITY LOGS ====================
        case 'get_activity_logs':
            getActivityLogs($db);
            break;
        
        default:
            sendError('Invalid action: ' . $action, 400);
    }
} catch (Exception $e) {
    error_log('Action error: ' . $e->getMessage());
    sendError('Operation failed: ' . $e->getMessage(), 500);
}

$db = null;

// =====================================================
// USERS FUNCTIONS
// =====================================================

function getUsers($db) {
    try {
        $search = $_GET['search'] ?? '';
        $role = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $sql = "SELECT id, name, email, company, role, avatar, is_active, 
                       email_verified_at, created_at, updated_at
                FROM users WHERE 1=1";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR company LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if (!empty($role)) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        
        if ($status !== '') {
            $isActive = $status === 'active' ? 1 : 0;
            $sql .= " AND is_active = ?";
            $params[] = $isActive;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get last login
        foreach ($users as &$user) {
            $stmt = $db->prepare("SELECT created_at FROM activities WHERE user_id = ? AND (action LIKE '%login%' OR description LIKE '%logged in%') ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$user['id']]);
            $lastLogin = $stmt->fetch(PDO::FETCH_ASSOC);
            $user['last_login_at'] = $lastLogin ? $lastLogin['created_at'] : null;
            $user['is_active'] = (bool)$user['is_active'];
        }
        
        sendSuccess(['users' => $users]);
        
    } catch (Exception $e) {
        error_log('Get users error: ' . $e->getMessage());
        sendError('Failed to fetch users');
    }
}

function createUser($db, $adminUser) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            sendError('Name, email and password are required', 400);
        }
        
        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            sendError('Invalid email format', 400);
        }
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            sendError('Email already exists', 400);
        }
        
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password, company, role, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $isActive = isset($data['is_active']) ? intval($data['is_active']) : 1;
        
        $stmt->execute([
            $data['name'],
            $data['email'],
            $hashedPassword,
            $data['company'] ?? null,
            $data['role'] ?? 'user',
            $isActive
        ]);
        
        $userId = $db->lastInsertId();
        
        logActivity($db, $adminUser['id'], 'create_user', "Created user: {$data['name']} (ID: $userId)");
        
        sendSuccess(['message' => 'User created successfully', 'user_id' => $userId]);
        
    } catch (Exception $e) {
        error_log('Create user error: ' . $e->getMessage());
        sendError('Failed to create user: ' . $e->getMessage());
    }
}

function updateUser($db, $adminUser) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            sendError('User ID is required', 400);
        }
        
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$data['id']]);
        if (!$stmt->fetch()) {
            sendError('User not found', 404);
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['name'])) {
            $updates[] = "name = ?";
            $params[] = $data['name'];
        }
        
        if (isset($data['email'])) {
            // Validate email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                sendError('Invalid email format', 400);
            }
            
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$data['email'], $data['id']]);
            if ($stmt->fetch()) {
                sendError('Email already exists', 400);
            }
            $updates[] = "email = ?";
            $params[] = $data['email'];
        }
        
        if (isset($data['company'])) {
            $updates[] = "company = ?";
            $params[] = $data['company'];
        }
        
        if (isset($data['role'])) {
            $updates[] = "role = ?";
            $params[] = $data['role'];
        }
        
        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = intval($data['is_active']);
        }
        
        if (!empty($data['password'])) {
            $updates[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        if (empty($updates)) {
            sendError('No fields to update', 400);
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $data['id'];
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        logActivity($db, $adminUser['id'], 'update_user', "Updated user ID: {$data['id']}");
        
        sendSuccess(['message' => 'User updated successfully']);
        
    } catch (Exception $e) {
        error_log('Update user error: ' . $e->getMessage());
        sendError('Failed to update user: ' . $e->getMessage());
    }
}

function deleteUser($db, $adminUser) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['id'] ?? 0;
        
        if (empty($userId)) {
            sendError('User ID is required', 400);
        }
        
        if ($userId == $adminUser['id']) {
            sendError('Cannot delete your own account', 400);
        }
        
        // Check if user exists
        $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            sendError('User not found', 404);
        }
        
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        logActivity($db, $adminUser['id'], 'delete_user', "Deleted user: {$user['name']} (ID: $userId)");
        
        sendSuccess(['message' => 'User deleted successfully']);
        
    } catch (Exception $e) {
        error_log('Delete user error: ' . $e->getMessage());
        sendError('Failed to delete user: ' . $e->getMessage());
    }
}

function toggleUserStatus($db, $adminUser) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            sendError('User ID is required', 400);
        }
        
        $newStatus = isset($data['is_active']) ? intval($data['is_active']) : 0;
        
        $stmt = $db->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $data['id']]);
        
        $statusText = $newStatus ? 'activated' : 'deactivated';
        logActivity($db, $adminUser['id'], 'toggle_user_status', "User $statusText ID: {$data['id']}");
        
        sendSuccess(['message' => 'User status updated', 'new_status' => (bool)$newStatus]);
        
    } catch (Exception $e) {
        error_log('Toggle status error: ' . $e->getMessage());
        sendError('Failed to toggle user status: ' . $e->getMessage());
    }
}

// =====================================================
// APPS FUNCTIONS
// =====================================================

function getAllApps($db) {
    try {
        $sql = "SELECT m.*, 
                       COUNT(DISTINCT uaa.user_id) as user_count,
                       COUNT(DISTINCT um.user_id) as access_count
                FROM modules m
                LEFT JOIN user_app_access uaa ON m.id = uaa.module_id AND uaa.is_enabled = 1
                LEFT JOIN user_modules um ON m.id = um.module_id
                GROUP BY m.id
                ORDER BY m.name";
        
        $stmt = $db->query($sql);
        $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($apps as &$app) {
            $app['is_active'] = (bool)$app['is_active'];
            $app['user_count'] = intval($app['user_count']);
            $app['access_count'] = intval($app['access_count']);
        }
        
        sendSuccess(['apps' => $apps]);
        
    } catch (Exception $e) {
        error_log('Get apps error: ' . $e->getMessage());
        sendError('Failed to fetch apps: ' . $e->getMessage());
    }
}

function createApp($db, $adminUser) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name'])) {
            sendError('App name is required', 400);
        }
        
        $stmt = $db->prepare("
            INSERT INTO modules (name, technical_name, description, url, icon, color, version, category, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $technicalName = strtolower(str_replace(' ', '_', $data['name']));
        $isActive = isset($data['is_active']) ? intval($data['is_active']) : 1;
        
        $stmt->execute([
            $data['name'],
            $technicalName,
            $data['description'] ?? null,
            $data['url'] ?? null,
            $data['icon'] ?? 'fas fa-cube',
            $data['color'] ?? 'from-blue-400 to-blue-600',
            $data['version'] ?? '1.0',
            $data['category'] ?? 'General',
            $isActive
        ]);
        
        $appId = $db->lastInsertId();
        
        logActivity($db, $adminUser['id'], 'create_app', "Created app: {$data['name']} (ID: $appId)");
        
        sendSuccess(['message' => 'App created successfully', 'app_id' => $appId]);
        
    } catch (Exception $e) {
        error_log('Create app error: ' . $e->getMessage());
        sendError('Failed to create app: ' . $e->getMessage());
    }
}

function updateApp($db, $adminUser) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            sendError('App ID is required', 400);
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['name'])) {
            $updates[] = "name = ?";
            $params[] = $data['name'];
        }
        
        if (isset($data['description'])) {
            $updates[] = "description = ?";
            $params[] = $data['description'];
        }
        
        if (isset($data['url'])) {
            $updates[] = "url = ?";
            $params[] = $data['url'];
        }
        
        if (isset($data['icon'])) {
            $updates[] = "icon = ?";
            $params[] = $data['icon'];
        }
        
        if (isset($data['color'])) {
            $updates[] = "color = ?";
            $params[] = $data['color'];
        }
        
        if (isset($data['version'])) {
            $updates[] = "version = ?";
            $params[] = $data['version'];
        }
        
        if (isset($data['category'])) {
            $updates[] = "category = ?";
            $params[] = $data['category'];
        }
        
        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = intval($data['is_active']);
        }
        
        if (empty($updates)) {
            sendError('No fields to update', 400);
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $data['id'];
        
        $sql = "UPDATE modules SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        logActivity($db, $adminUser['id'], 'update_app', "Updated app ID: {$data['id']}");
        
        sendSuccess(['message' => 'App updated successfully']);
        
    } catch (Exception $e) {
        error_log('Update app error: ' . $e->getMessage());
        sendError('Failed to update app: ' . $e->getMessage());
    }
}

function deleteApp($db, $adminUser) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $appId = $data['id'] ?? 0;
        
        if (empty($appId)) {
            sendError('App ID is required', 400);
        }
        
        // Get app name before deleting
        $stmt = $db->prepare("SELECT name FROM modules WHERE id = ?");
        $stmt->execute([$appId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$app) {
            sendError('App not found', 404);
        }
        
        $stmt = $db->prepare("DELETE FROM modules WHERE id = ?");
        $stmt->execute([$appId]);
        
        logActivity($db, $adminUser['id'], 'delete_app', "Deleted app: {$app['name']} (ID: $appId)");
        
        sendSuccess(['message' => 'App deleted successfully']);
        
    } catch (Exception $e) {
        error_log('Delete app error: ' . $e->getMessage());
        sendError('Failed to delete app: ' . $e->getMessage());
    }
}

function toggleAppStatus($db, $adminUser) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            sendError('App ID is required', 400);
        }
        
        $newStatus = isset($data['is_active']) ? intval($data['is_active']) : 0;
        
        $stmt = $db->prepare("UPDATE modules SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $data['id']]);
        
        $statusText = $newStatus ? 'activated' : 'deactivated';
        logActivity($db, $adminUser['id'], 'toggle_app_status', "App $statusText ID: {$data['id']}");
        
        sendSuccess(['message' => 'App status updated', 'new_status' => (bool)$newStatus]);
        
    } catch (Exception $e) {
        error_log('Toggle app status error: ' . $e->getMessage());
        sendError('Failed to toggle app status: ' . $e->getMessage());
    }
}

// =====================================================
// APP ACCESS FUNCTIONS
// =====================================================

function getUserApps($db) {
    try {
        $userId = $_GET['user_id'] ?? 0;
        
        if (empty($userId)) {
            sendError('User ID is required', 400);
        }
        
        // Get all apps
        $stmt = $db->query("SELECT id, name, technical_name, icon, color, description FROM modules WHERE is_active = 1 ORDER BY name");
        $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get user's apps
        $stmt = $db->prepare("SELECT module_id as app_id FROM user_app_access WHERE user_id = ? AND is_enabled = 1");
        $stmt->execute([$userId]);
        $userApps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendSuccess(['apps' => $apps, 'user_apps' => $userApps]);
        
    } catch (Exception $e) {
        error_log('Get user apps error: ' . $e->getMessage());
        sendError('Failed to fetch user apps: ' . $e->getMessage());
    }
}

function grantAppAccess($db, $adminUser) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['user_id']) || empty($data['app_id'])) {
            sendError('User ID and App ID are required', 400);
        }
        
        // Check if access already exists
        $stmt = $db->prepare("SELECT id FROM user_app_access WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$data['user_id'], $data['app_id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = $db->prepare("UPDATE user_app_access SET is_enabled = 1, granted_by = ?, granted_at = NOW() WHERE id = ?");
            $stmt->execute([$adminUser['id'], $existing['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO user_app_access (user_id, module_id, is_enabled, granted_by, granted_at) VALUES (?, ?, 1, ?, NOW())");
            $stmt->execute([$data['user_id'], $data['app_id'], $adminUser['id']]);
        }
        
        logActivity($db, $adminUser['id'], 'grant_app_access', "Granted app access: User {$data['user_id']}, App {$data['app_id']}");
        
        sendSuccess(['message' => 'Access granted successfully']);
        
    } catch (Exception $e) {
        error_log('Grant access error: ' . $e->getMessage());
        sendError('Failed to grant access: ' . $e->getMessage());
    }
}

function revokeAppAccess($db, $adminUser) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['user_id']) || empty($data['app_id'])) {
            sendError('User ID and App ID are required', 400);
        }
        
        $stmt = $db->prepare("UPDATE user_app_access SET is_enabled = 0 WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$data['user_id'], $data['app_id']]);
        
        // If no record exists, create one as disabled
        if ($stmt->rowCount() === 0) {
            $stmt = $db->prepare("INSERT INTO user_app_access (user_id, module_id, is_enabled, granted_by, granted_at) VALUES (?, ?, 0, ?, NOW())");
            $stmt->execute([$data['user_id'], $data['app_id'], $adminUser['id']]);
        }
        
        logActivity($db, $adminUser['id'], 'revoke_app_access', "Revoked app access: User {$data['user_id']}, App {$data['app_id']}");
        
        sendSuccess(['message' => 'Access revoked successfully']);
        
    } catch (Exception $e) {
        error_log('Revoke access error: ' . $e->getMessage());
        sendError('Failed to revoke access: ' . $e->getMessage());
    }
}

// =====================================================
// ACTIVITY LOGS
// =====================================================

function getActivityLogs($db) {
    try {
        $limit = $_GET['limit'] ?? 100;
        
        $sql = "SELECT a.*, u.name as user_name 
                FROM activities a
                LEFT JOIN users u ON a.user_id = u.id
                ORDER BY a.created_at DESC
                LIMIT ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([intval($limit)]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendSuccess(['logs' => $logs]);
        
    } catch (Exception $e) {
        error_log('Get logs error: ' . $e->getMessage());
        sendError('Failed to fetch logs: ' . $e->getMessage());
    }
}

// =====================================================
// UTILITY FUNCTIONS
// =====================================================

function logActivity($db, $userId, $action, $description) {
    try {
        $stmt = $db->prepare("INSERT INTO activities (user_id, action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $userId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log('Log activity error: ' . $e->getMessage());
    }
}

function sendSuccess($data) {
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
?>