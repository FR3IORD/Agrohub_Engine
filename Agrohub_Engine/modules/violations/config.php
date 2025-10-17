<?php
/**
 * Violations Module - Configuration
 * Based on Agrohub ERP Design System
 */

defined('VIOLATIONS_MODULE') or define('VIOLATIONS_MODULE', true);

// Module directories
define('VIOLATIONS_BASE_DIR', __DIR__);
define('VIOLATIONS_UPLOAD_DIR', VIOLATIONS_BASE_DIR . '/uploads/');
define('VIOLATIONS_LOGS_DIR', VIOLATIONS_BASE_DIR . '/logs/');

// Upload settings
define('VIOLATIONS_MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB
define('VIOLATIONS_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Pagination
define('VIOLATIONS_PER_PAGE', 12);

// Ensure directories exist
foreach ([VIOLATIONS_UPLOAD_DIR, VIOLATIONS_LOGS_DIR] as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * Get database connection
 */
function violationsGetDB() {
    if (class_exists('Database')) {
        return Database::getInstance();
    }
    
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=agrohub_erp;charset=utf8mb4",
            'root',
            'root',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Violations DB Error: " . $e->getMessage());
        die("Database connection failed");
    }
}

/**
 * Get user permissions
 */
function violationsGetUserPermissions($userId, $userRole) {
    $rolePermissions = [
        'admin' => [
            'can_view_all' => true,
            'can_view_own' => true,
            'can_view_branch' => true,
            'can_create' => true,
            'can_edit_own' => true,
            'can_edit_all' => true,
            'can_delete' => true,
            'can_apply_sanctions' => true,
            'can_reject' => true,
            'can_view_sanctions' => true,
            'can_view_photos' => true,
            'can_export' => true,
            'can_view_analytics' => true
        ],
        'manager' => [
            'can_view_all' => false,
            'can_view_own' => true,
            'can_view_branch' => true,
            'can_create' => true,
            'can_edit_own' => true,
            'can_edit_all' => false,
            'can_delete' => false,
            'can_apply_sanctions' => true,
            'can_reject' => true,
            'can_view_sanctions' => true,
            'can_view_photos' => true,
            'can_export' => true,
            'can_view_analytics' => false
        ],
        'user' => [
            'can_view_all' => false,
            'can_view_own' => true,
            'can_view_branch' => false,
            'can_create' => false,
            'can_edit_own' => false,
            'can_edit_all' => false,
            'can_delete' => false,
            'can_apply_sanctions' => false,
            'can_reject' => false,
            'can_view_sanctions' => false,
            'can_view_photos' => true,
            'can_export' => false,
            'can_view_analytics' => false
        ]
    ];
    
    try {
        $db = violationsGetDB();
        $stmt = $db->prepare("SELECT * FROM violation_user_permissions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userPerms = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userPerms) {
            unset($userPerms['id'], $userPerms['user_id'], $userPerms['created_at'], $userPerms['updated_at']);
            foreach ($userPerms as $key => $value) {
                $userPerms[$key] = (bool)$value;
            }
            return $userPerms;
        }
    } catch (Exception $e) {
        error_log("Permissions Error: " . $e->getMessage());
    }
    
    return $rolePermissions[strtolower($userRole)] ?? $rolePermissions['user'];
}

/**
 * Log activity
 */
function violationsLogActivity($userId, $action, $description, $violationId = null) {
    try {
        $db = violationsGetDB();
        $stmt = $db->prepare("
            INSERT INTO violation_activity_logs (user_id, violation_id, action, description, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $violationId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

return true;