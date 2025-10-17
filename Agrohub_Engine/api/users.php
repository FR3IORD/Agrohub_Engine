<?php
/**
 * Agrohub ERP Platform - Users API
 * 
 * Handles user management, profiles, and permissions
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

class UsersAPI {
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
        
        // Remove 'api' and 'users' from segments
        $segments = array_slice($segments, 2);
        
        try {
            switch ($method) {
                case 'GET':
                    if (empty($segments[0])) {
                        return $this->getUsers();
                    } elseif ($segments[0] === 'profile') {
                        return $this->getProfile();
                    } elseif (is_numeric($segments[0])) {
                        return $this->getUser($segments[0]);
                    } elseif ($segments[0] === 'stats') {
                        return $this->getUserStats();
                    }
                    break;
                    
                case 'POST':
                    if (empty($segments[0])) {
                        return $this->createUser();
                    } elseif ($segments[0] === 'avatar') {
                        return $this->uploadAvatar();
                    }
                    break;
                    
                case 'PUT':
                    if ($segments[0] === 'profile') {
                        return $this->updateProfile();
                    } elseif (is_numeric($segments[0])) {
                        return $this->updateUser($segments[0]);
                    }
                    break;
                    
                case 'DELETE':
                    if (is_numeric($segments[0])) {
                        return $this->deleteUser($segments[0]);
                    }
                    break;
            }
            
            return $this->utils->sendError('Endpoint not found', 404);
            
        } catch (Exception $e) {
            error_log('Users API Error: ' . $e->getMessage());
            return $this->utils->sendError('Internal server error', 500);
        }
    }
    
    /**
     * Get all users (admin only)
     */
    private function getUsers() {
        $user = $this->utils->authenticateRequest();
        
        if (!$user || !in_array($user['role'], ['admin', 'manager'])) {
            return $this->utils->sendError('Admin or manager access required', 403);
        }
        
        $search = $_GET['search'] ?? null;
        $role = $_GET['role'] ?? null;
        $status = $_GET['status'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, name, email, company, role, is_active, created_at, updated_at FROM users WHERE 1=1";
        $params = [];
        
        if ($search) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR company LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($role) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        
        if ($status !== null) {
            $sql .= " AND is_active = ?";
            $params[] = $status === 'active' ? 1 : 0;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add avatar for each user
        foreach ($users as &$userData) {
            $userData['avatar'] = $userData['avatar'] ?? strtoupper(substr($userData['name'], 0, 1));
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) FROM users WHERE 1=1";
        $countParams = [];
        
        if ($search) {
            $countSql .= " AND (name LIKE ? OR email LIKE ? OR company LIKE ?)";
            $searchTerm = "%$search%";
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
        }
        
        if ($role) {
            $countSql .= " AND role = ?";
            $countParams[] = $role;
        }
        
        if ($status !== null) {
            $countSql .= " AND is_active = ?";
            $countParams[] = $status === 'active' ? 1 : 0;
        }
        
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetchColumn();
        
        return $this->utils->sendSuccess([
            'data' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * Get current user profile
     */
    private function getProfile() {
        $user = $this->utils->authenticateRequest();
        
        if (!$user) {
            return $this->utils->sendError('Authentication required', 401);
        }
        
        // Remove password from response
        unset($user['password']);
        $user['avatar'] = $user['avatar'] ?: strtoupper(substr($user['name'], 0, 1));
        
        // Get user statistics
        $stats = $this->getUserModuleStats($user['id']);
        $user['stats'] = $stats;
        
        return $this->utils->sendSuccess($user);
    }
    
    /**
     * Get specific user (admin/manager only)
     */
    private function getUser($userId) {
        $user = $this->utils->authenticateRequest();
        
        if (!$user || !in_array($user['role'], ['admin', 'manager'])) {
            return $this->utils->sendError('Admin or manager access required', 403);
        }
        
        $stmt = $this->db->prepare("SELECT id, name, email, company, role, is_active, created_at, updated_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$targetUser) {
            return $this->utils->sendError('User not found', 404);
        }
        
        $targetUser['avatar'] = $targetUser['avatar'] ?: strtoupper(substr($targetUser['name'], 0, 1));
        
        // Get user statistics
        $stats = $this->getUserModuleStats($userId);
        $targetUser['stats'] = $stats;
        
        return $this->utils->sendSuccess($targetUser);
    }
    
    /**
     * Get user statistics
     */
    private function getUserStats() {
        $user = $this->utils->authenticateRequest();
        
        if (!$user || $user['role'] !== 'admin') {
            return $this->utils->sendError('Admin access required', 403);
        }
        
        // Total users
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        $totalUsers = $stmt->fetchColumn();
        
        // Active users
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1");
        $stmt->execute();
        $activeUsers = $stmt->fetchColumn();
        
        // Users by role
        $stmt = $this->db->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
        $stmt->execute();
        $usersByRole = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Recent registrations (last 30 days)
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $recentRegistrations = $stmt->fetchColumn();
        
        // Most active users (by module installations)
        $stmt = $this->db->prepare("
            SELECT u.name, u.email, COUNT(um.id) as module_count 
            FROM users u 
            LEFT JOIN user_modules um ON u.id = um.user_id 
            WHERE u.is_active = 1 
            GROUP BY u.id 
            ORDER BY module_count DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->utils->sendSuccess([
            'total_users' => (int)$totalUsers,
            'active_users' => (int)$activeUsers,
            'users_by_role' => $usersByRole,
            'recent_registrations' => (int)$recentRegistrations,
            'most_active_users' => $activeUsers
        ]);
    }
    
    /**
     * Create new user (admin only)
     */
    private function createUser() {
        $user = $this->utils->authenticateRequest();
        
        if (!$user || $user['role'] !== 'admin') {
            return $this->utils->sendError('Admin access required', 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['name', 'email', 'password'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                return $this->utils->sendError("$field is required", 400);
            }
        }
        
        $name = trim($input['name']);
        $email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
        $password = $input['password'];
        $company = isset($input['company']) ? trim($input['company']) : null;
        $role = isset($input['role']) ? $input['role'] : 'user';
        
        // Validate inputs
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->utils->sendError('Invalid email format', 400);
        }
        
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return $this->utils->sendError("Password must be at least " . PASSWORD_MIN_LENGTH . " characters", 400);
        }
        
        if (!in_array($role, ['admin', 'manager', 'user'])) {
            return $this->utils->sendError('Invalid role', 400);
        }
        
        // Check if email already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return $this->utils->sendError('Email already exists', 409);
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $avatar = strtoupper(substr($name, 0, 1));
        
        // Insert user
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password, company, avatar, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
        
        if ($stmt->execute([$name, $email, $hashedPassword, $company, $avatar, $role])) {
            $userId = $this->db->lastInsertId();
            
            // Log activity
            $this->logActivity($user['id'], "Created user: $name ($email)", 'User Management');
            
            return $this->utils->sendSuccess(['user_id' => $userId], 'User created successfully');
        } else {
            return $this->utils->sendError('Failed to create user', 500);
        }
    }
    
    /**
     * Update current user profile
     */
    private function updateProfile() {
        $user = $this->utils->authenticateRequest();
        
        if (!$user) {
            return $this->utils->sendError('Authentication required', 401);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['name', 'company'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field]) && $input[$field] !== '') {
                $updateFields[] = "$field = ?";
                $params[] = trim($input[$field]);
            }
        }
        
        // Handle email separately with validation
        if (isset($input['email']) && $input['email'] !== '') {
            $email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->utils->sendError('Invalid email format', 400);
            }
            
            // Check if email is already taken by another user
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetch()) {
                return $this->utils->sendError('Email already taken', 409);
            }
            
            $updateFields[] = "email = ?";
            $params[] = $email;
        }
        
        if (empty($updateFields)) {
            return $this->utils->sendError('No valid fields to update', 400);
        }
        
        $updateFields[] = "updated_at = NOW()";
        $params[] = $user['id'];
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($params)) {
            // Log activity
            $this->logActivity($user['id'], 'Updated profile', 'Profile');
            
            return $this->utils->sendSuccess([], 'Profile updated successfully');
        } else {
            return $this->utils->sendError('Failed to update profile', 500);
        }
    }
    
    /**
     * Update user (admin only)
     */
    private function updateUser($userId) {
        $user = $this->utils->authenticateRequest();
        
        if (!$user || $user['role'] !== 'admin') {
            return $this->utils->sendError('Admin access required', 403);
        }
        
        // Check if target user exists
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$targetUser) {
            return $this->utils->sendError('User not found', 404);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['name', 'company', 'role', 'is_active'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                if ($field === 'role' && !in_array($input[$field], ['admin', 'manager', 'user'])) {
                    return $this->utils->sendError('Invalid role', 400);
                }
                
                if ($field === 'is_active') {
                    $input[$field] = (bool)$input[$field] ? 1 : 0;
                }
                
                $updateFields[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
        
        // Handle email separately with validation
        if (isset($input['email']) && $input['email'] !== '') {
            $email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->utils->sendError('Invalid email format', 400);
            }
            
            // Check if email is already taken by another user
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                return $this->utils->sendError('Email already taken', 409);
            }
            
            $updateFields[] = "email = ?";
            $params[] = $email;
        }
        
        if (empty($updateFields)) {
            return $this->utils->sendError('No valid fields to update', 400);
        }
        
        $updateFields[] = "updated_at = NOW()";
        $params[] = $userId;
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($params)) {
            // Log activity
            $this->logActivity($user['id'], "Updated user: {$targetUser['name']}", 'User Management');
            
            return $this->utils->sendSuccess([], 'User updated successfully');
        } else {
            return $this->utils->sendError('Failed to update user', 500);
        }
    }
    
    /**
     * Delete user (admin only)
     */
    private function deleteUser($userId) {
        $user = $this->utils->authenticateRequest();
        
        if (!$user || $user['role'] !== 'admin') {
            return $this->utils->sendError('Admin access required', 403);
        }
        
        // Prevent self-deletion
        if ($user['id'] == $userId) {
            return $this->utils->sendError('Cannot delete your own account', 400);
        }
        
        // Check if target user exists
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$targetUser) {
            return $this->utils->sendError('User not found', 404);
        }
        
        // Delete user and related data
        try {
            $this->db->beginTransaction();
            
            // Delete user modules
            $stmt = $this->db->prepare("DELETE FROM user_modules WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete user sessions
            $stmt = $this->db->prepare("DELETE FROM sessions WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete user activities (optional - you might want to keep for audit)
            // $stmt = $this->db->prepare("DELETE FROM activities WHERE user_id = ?");
            // $stmt->execute([$userId]);
            
            // Delete user
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            // Log activity
            $this->logActivity($user['id'], "Deleted user: {$targetUser['name']}", 'User Management');
            
            $this->db->commit();
            
            return $this->utils->sendSuccess([], 'User deleted successfully');
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('User deletion error: ' . $e->getMessage());
            return $this->utils->sendError('Failed to delete user', 500);
        }
    }
    
    /**
     * Upload user avatar
     */
    private function uploadAvatar() {
        $user = $this->utils->authenticateRequest();
        
        if (!$user) {
            return $this->utils->sendError('Authentication required', 401);
        }
        
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            return $this->utils->sendError('No valid file uploaded', 400);
        }
        
        $file = $_FILES['avatar'];
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes)) {
            return $this->utils->sendError('Invalid file type. Only JPEG, PNG, and GIF are allowed', 400);
        }
        
        // Validate file size (2MB max)
        if ($file['size'] > 2 * 1024 * 1024) {
            return $this->utils->sendError('File too large. Maximum size is 2MB', 400);
        }
        
        // Create uploads directory if it doesn't exist
        $uploadDir = '../uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Update user avatar in database
            $avatarUrl = '/uploads/avatars/' . $filename;
            $stmt = $this->db->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?");
            
            if ($stmt->execute([$avatarUrl, $user['id']])) {
                // Log activity
                $this->logActivity($user['id'], 'Updated avatar', 'Profile');
                
                return $this->utils->sendSuccess(['avatar_url' => $avatarUrl], 'Avatar uploaded successfully');
            } else {
                // Clean up uploaded file if database update fails
                unlink($filepath);
                return $this->utils->sendError('Failed to update avatar', 500);
            }
        } else {
            return $this->utils->sendError('Failed to upload file', 500);
        }
    }
    
    /**
     * Get user module statistics
     */
    private function getUserModuleStats($userId) {
        // Installed modules count
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_modules WHERE user_id = ?");
        $stmt->execute([$userId]);
        $installedModules = $stmt->fetchColumn();
        
        // Modules by category
        $stmt = $this->db->prepare("
            SELECT m.category, COUNT(*) as count 
            FROM user_modules um 
            JOIN modules m ON um.module_id = m.id 
            WHERE um.user_id = ? 
            GROUP BY m.category
        ");
        $stmt->execute([$userId]);
        $modulesByCategory = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Recent installations (last 30 days)
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM user_modules 
            WHERE user_id = ? AND installed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$userId]);
        $recentInstallations = $stmt->fetchColumn();
        
        return [
            'installed_modules' => (int)$installedModules,
            'modules_by_category' => $modulesByCategory,
            'recent_installations' => (int)$recentInstallations
        ];
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
$usersAPI = new UsersAPI();
echo $usersAPI->handleRequest();
?>