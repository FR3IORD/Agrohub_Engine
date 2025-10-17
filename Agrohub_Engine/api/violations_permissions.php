<?php
/**
 * Violations Permissions Management API
 * For Admin Panel - Manage user permissions for Violations module
 * Now supports MODULE-SPECIFIC ROLES via role_type field
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/utils.php';

$utils = new Utils();
$user = $utils->authenticateRequest();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access only']);
    exit();
}

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        
        // ============================================
        // GET ALL USERS WITH VIOLATIONS ACCESS + MODULE ROLE
        // ============================================
        case 'get_users':
            $stmt = $db->query("
                SELECT 
                    u.id,
                    u.username,
                    u.name,
                    u.email,
                    u.role AS global_role,
                    COALESCE(uaa.is_enabled, 0) as has_access,
                    COALESCE(vup.role_type, NULL) as role_type,
                    COALESCE(vup.can_view_all, 0) as can_view_all,
                    COALESCE(vup.can_view_own, 0) as can_view_own,
                    COALESCE(vup.can_view_branch, 0) as can_view_branch,
                    COALESCE(vup.can_create, 0) as can_create,
                    COALESCE(vup.can_edit_own, 0) as can_edit_own,
                    COALESCE(vup.can_edit_all, 0) as can_edit_all,
                    COALESCE(vup.can_delete, 0) as can_delete,
                    COALESCE(vup.can_apply_sanctions, 0) as can_apply_sanctions,
                    COALESCE(vup.can_reject, 0) as can_reject,
                    COALESCE(vup.can_view_sanctions, 0) as can_view_sanctions,
                    COALESCE(vup.can_view_photos, 0) as can_view_photos,
                    COALESCE(vup.can_export, 0) as can_export,
                    COALESCE(vup.can_view_analytics, 0) as can_view_analytics
                FROM users u
                LEFT JOIN (
                    SELECT uaa.user_id, uaa.is_enabled
                    FROM user_app_access uaa
                    INNER JOIN modules m ON uaa.module_id = m.id
                    WHERE m.technical_name = 'violations'
                ) uaa ON u.id = uaa.user_id
                LEFT JOIN violation_user_permissions vup ON u.id = vup.user_id
                WHERE u.is_active = 1
                ORDER BY u.name ASC
            ");
            
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => ['users' => $users],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
        
        // ============================================
        // UPDATE USER PERMISSIONS + ROLE TYPE
        // ============================================
        case 'update_permissions':
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = intval($input['user_id'] ?? 0);
            
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'User ID required']);
                exit();
            }
            
            // Build permissions array
            $permissions = [];
            $allowedPerms = [
                'role_type', // NEW: Module-specific role
                'can_view_all', 'can_view_own', 'can_view_branch',
                'can_create', 'can_edit_own', 'can_edit_all', 'can_delete',
                'can_apply_sanctions', 'can_reject', 'can_view_sanctions',
                'can_view_photos', 'can_export', 'can_view_analytics'
            ];
            
            foreach ($allowedPerms as $perm) {
                if (isset($input[$perm])) {
                    if ($perm === 'role_type') {
                        $permissions[$perm] = $input[$perm]; // String value
                    } else {
                        $permissions[$perm] = intval($input[$perm]); // Integer value
                    }
                }
            }
            
            if (empty($permissions)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No permissions to update']);
                exit();
            }
            
            // Check if permissions exist
            $stmt = $db->prepare("SELECT id FROM violation_user_permissions WHERE user_id = ?");
            $stmt->execute([$userId]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Update
                $fields = [];
                $values = [];
                foreach ($permissions as $key => $value) {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
                $values[] = $userId;
                
                $sql = "UPDATE violation_user_permissions SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE user_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($values);
            } else {
                // Insert
                $permissions['user_id'] = $userId;
                $fields = array_keys($permissions);
                $placeholders = array_fill(0, count($fields), '?');
                $values = array_values($permissions);
                
                $sql = "INSERT INTO violation_user_permissions (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $db->prepare($sql);
                $stmt->execute($values);
            }
            
            echo json_encode([
                'success' => true,
                'data' => ['message' => 'Permissions updated successfully'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
        
        // ============================================
        // BULK UPDATE - Set VM users to create-only
        // ============================================
        case 'bulk_set_vm_permissions':
            $stmt = $db->query("
                SELECT id, name, username FROM users 
                WHERE (username LIKE 'vm %' OR username LIKE 'VM %')
                AND is_active = 1
            ");
            $vmUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $count = 0;
            foreach ($vmUsers as $vmUser) {
                $vmUserId = $vmUser['id'];
                
                $stmt = $db->prepare("SELECT id FROM violation_user_permissions WHERE user_id = ?");
                $stmt->execute([$vmUserId]);
                
                if ($stmt->fetch()) {
                    $stmt = $db->prepare("
                        UPDATE violation_user_permissions SET
                            role_type = 'vm',
                            can_view_all = 0,
                            can_view_own = 1,
                            can_view_branch = 0,
                            can_create = 1,
                            can_edit_own = 0,
                            can_edit_all = 0,
                            can_delete = 0,
                            can_apply_sanctions = 0,
                            can_reject = 0,
                            can_view_sanctions = 0,
                            can_view_photos = 1,
                            can_export = 0,
                            can_view_analytics = 0,
                            updated_at = NOW()
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$vmUserId]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO violation_user_permissions (
                            user_id, role_type, can_view_all, can_view_own, can_view_branch,
                            can_create, can_edit_own, can_edit_all, can_delete,
                            can_apply_sanctions, can_reject, can_view_sanctions,
                            can_view_photos, can_export, can_view_analytics
                        ) VALUES (?, 'vm', 0, 1, 0, 1, 0, 0, 0, 0, 0, 0, 1, 0, 0)
                    ");
                    $stmt->execute([$vmUserId]);
                }
                $count++;
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'message' => "$count VM users configured successfully",
                    'count' => $count,
                    'users' => array_column($vmUsers, 'name')
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
        
        // ============================================
        // GET PERMISSION PRESETS (WITH ROLE_TYPE)
        // ============================================
        case 'get_presets':
            $presets = [
                'vm' => [
                    'name' => 'Video Monitor (Create Only)',
                    'role_type' => 'vm',
                    'permissions' => [
                        'can_view_all' => 0, 'can_view_own' => 1, 'can_view_branch' => 0,
                        'can_create' => 1, 'can_edit_own' => 0, 'can_edit_all' => 0,
                        'can_delete' => 0, 'can_apply_sanctions' => 0, 'can_reject' => 0,
                        'can_view_sanctions' => 0, 'can_view_photos' => 1, 'can_export' => 0,
                        'can_view_analytics' => 0
                    ]
                ],
                'gm' => [
                    'name' => 'General Manager',
                    'role_type' => 'gm',
                    'permissions' => [
                        'can_view_all' => 0, 'can_view_own' => 1, 'can_view_branch' => 1,
                        'can_create' => 1, 'can_edit_own' => 1, 'can_edit_all' => 0,
                        'can_delete' => 0, 'can_apply_sanctions' => 1, 'can_reject' => 1,
                        'can_view_sanctions' => 1, 'can_view_photos' => 1, 'can_export' => 1,
                        'can_view_analytics' => 0
                    ]
                ],
                'production' => [
                    'name' => 'Production Manager',
                    'role_type' => 'production',
                    'permissions' => [
                        'can_view_all' => 1, 'can_view_own' => 1, 'can_view_branch' => 1,
                        'can_create' => 0, 'can_edit_own' => 0, 'can_edit_all' => 0,
                        'can_delete' => 0, 'can_apply_sanctions' => 0, 'can_reject' => 0,
                        'can_view_sanctions' => 1, 'can_view_photos' => 1, 'can_export' => 1,
                        'can_view_analytics' => 1
                    ]
                ],
                'operations' => [
                    'name' => 'Operations (Full Control)',
                    'role_type' => 'operations',
                    'permissions' => [
                        'can_view_all' => 1, 'can_view_own' => 1, 'can_view_branch' => 1,
                        'can_create' => 1, 'can_edit_own' => 1, 'can_edit_all' => 1,
                        'can_delete' => 1, 'can_apply_sanctions' => 1, 'can_reject' => 1,
                        'can_view_sanctions' => 1, 'can_view_photos' => 1, 'can_export' => 1,
                        'can_view_analytics' => 1
                    ]
                ],
                'director' => [
                    'name' => 'Director (Read Only)',
                    'role_type' => 'director',
                    'permissions' => [
                        'can_view_all' => 1, 'can_view_own' => 1, 'can_view_branch' => 1,
                        'can_create' => 0, 'can_edit_own' => 0, 'can_edit_all' => 0,
                        'can_delete' => 0, 'can_apply_sanctions' => 0, 'can_reject' => 0,
                        'can_view_sanctions' => 1, 'can_view_photos' => 1, 'can_export' => 1,
                        'can_view_analytics' => 1
                    ]
                ],
                'audit' => [
                    'name' => 'Audit (Read Only)',
                    'role_type' => 'audit',
                    'permissions' => [
                        'can_view_all' => 1, 'can_view_own' => 1, 'can_view_branch' => 1,
                        'can_create' => 0, 'can_edit_own' => 0, 'can_edit_all' => 0,
                        'can_delete' => 0, 'can_apply_sanctions' => 0, 'can_reject' => 0,
                        'can_view_sanctions' => 1, 'can_view_photos' => 1, 'can_export' => 1,
                        'can_view_analytics' => 1
                    ]
                ],
                'hr' => [
                    'name' => 'HR (Completed Only)',
                    'role_type' => 'hr',
                    'permissions' => [
                        'can_view_all' => 1, 'can_view_own' => 1, 'can_view_branch' => 1,
                        'can_create' => 0, 'can_edit_own' => 0, 'can_edit_all' => 0,
                        'can_delete' => 0, 'can_apply_sanctions' => 0, 'can_reject' => 0,
                        'can_view_sanctions' => 1, 'can_view_photos' => 1, 'can_export' => 1,
                        'can_view_analytics' => 0
                    ]
                ],
                'admin' => [
                    'name' => 'Admin (Full Access)',
                    'role_type' => 'admin',
                    'permissions' => [
                        'can_view_all' => 1, 'can_view_own' => 1, 'can_view_branch' => 1,
                        'can_create' => 1, 'can_edit_own' => 1, 'can_edit_all' => 1,
                        'can_delete' => 1, 'can_apply_sanctions' => 1, 'can_reject' => 1,
                        'can_view_sanctions' => 1, 'can_view_photos' => 1, 'can_export' => 1,
                        'can_view_analytics' => 1
                    ]
                ]
            ];
            
            echo json_encode([
                'success' => true,
                'data' => ['presets' => $presets],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
        
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action',
                'available_actions' => ['get_users', 'update_permissions', 'bulk_set_vm_permissions', 'get_presets']
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Violations Permissions API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}