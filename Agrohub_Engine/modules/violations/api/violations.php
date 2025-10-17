<?php
/**
 * Violations Module - REST API
 * Full CRUD with photo_url support
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('VIOLATIONS_MODULE', true);

require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/../../../api/database.php';
require_once __DIR__ . '/../../../api/utils.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$utils = new Utils();
$user = $utils->authenticateRequest();

if (!$user) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required',
        'code' => 'AUTH_REQUIRED'
    ]);
    exit();
}

if (!violationsHasModuleAccess($user['id'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Access denied to violations module',
        'code' => 'MODULE_ACCESS_DENIED'
    ]);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        
        // ============================================
        // LIST VIOLATIONS
        // ============================================
        case 'list':
            $permissions = violationsGetUserPermissions($user['id'], $user['role']);
            
            $db = violationsGetDB();
            
            $sql = "
                SELECT 
                    v.id,
                    v.branch_id,
                    v.user_id,
                    v.processing_date,
                    v.processed_date,
                    v.dvr,
                    v.camera,
                    v.incident_location,
                    v.category,
                    v.category_comment,
                    v.fact_identifier,
                    v.progress,
                    v.responsibility,
                    v.fullname,
                    v.fine_amount,
                    v.comment,
                    v.photo_url,
                    v.created_at,
                    v.updated_at,
                    b.name as branch_name
                FROM violations v
                LEFT JOIN branches b ON v.branch_id = b.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if (isset($_GET['branch_ids']) && !empty($_GET['branch_ids'])) {
                $branchIdsInput = is_string($_GET['branch_ids']) ? explode(',', $_GET['branch_ids']) : $_GET['branch_ids'];
                $branchIds = array_map('intval', array_filter($branchIdsInput));
                
                if (!empty($branchIds)) {
                    $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
                    $sql .= " AND v.branch_id IN ($placeholders)";
                    $params = array_merge($params, $branchIds);
                }
            } else if (!$permissions['can_view_all']) {
                if ($permissions['can_view_branch']) {
                    $stmt = $db->prepare("SELECT branch_id FROM user_branches WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                    $userBranches = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($userBranches)) {
                        $placeholders = implode(',', array_fill(0, count($userBranches), '?'));
                        $sql .= " AND v.branch_id IN ($placeholders)";
                        $params = array_merge($params, $userBranches);
                    } else {
                        echo json_encode([
                            'success' => true,
                            'data' => ['violations' => [], 'count' => 0, 'limit' => 0, 'offset' => 0],
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                        exit();
                    }
                } else if ($permissions['can_view_own']) {
                    $sql .= " AND v.user_id = ?";
                    $params[] = $user['id'];
                }
            }
            
            if (isset($_GET['progress']) && !empty($_GET['progress'])) {
                $sql .= " AND v.progress = ?";
                $params[] = $_GET['progress'];
            }
            
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = '%' . $_GET['search'] . '%';
                $sql .= " AND (v.dvr LIKE ? OR v.camera LIKE ? OR v.category LIKE ? OR v.fact_identifier LIKE ? OR b.name LIKE ?)";
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }
            
            $sql .= " ORDER BY v.id DESC";
            
            $limit = intval($_GET['limit'] ?? 200);
            $offset = intval($_GET['offset'] ?? 0);
            
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'violations' => $violations,
                    'count' => count($violations),
                    'limit' => $limit,
                    'offset' => $offset
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            break;
        
        // ============================================
        // CREATE VIOLATION
        // ============================================
        case 'create':
            violationsRequirePermission($user, 'can_create', 'Permission denied to create violations');
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON input',
                    'code' => 'INVALID_JSON'
                ]);
                exit();
            }
            
            $required = ['branch_id', 'dvr', 'camera', 'incident_location', 'category', 'fact_identifier'];
            
            foreach ($required as $field) {
                if (!isset($input[$field]) || trim($input[$field]) === '') {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => "Missing or empty required field: $field",
                        'code' => 'VALIDATION_ERROR',
                        'field' => $field
                    ]);
                    exit();
                }
            }
            
            $db = violationsGetDB();
            
            $stmt = $db->prepare("
                INSERT INTO violations (
                    branch_id,
                    user_id,
                    processing_date,
                    processed_date,
                    dvr,
                    camera,
                    incident_location,
                    category,
                    category_comment,
                    fact_identifier,
                    progress,
                    senttomail,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, NOW())
            ");
            
            $success = $stmt->execute([
                $input['branch_id'],
                $input['user_id'] ?? $user['id'],
                $input['processing_date'] ?? date('Y-m-d H:i:s'),
                $input['processed_date'] ?? date('Y-m-d H:i:s'),
                $input['dvr'],
                $input['camera'],
                $input['incident_location'],
                $input['category'],
                $input['category_comment'] ?? null,
                $input['fact_identifier']
            ]);
            
            if ($success) {
                $violationId = $db->lastInsertId();
                
                violationsLogActivity($input['user_id'] ?? $user['id'], 'create_violation', "Created violation #{$violationId}", $violationId);
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'violation_id' => $violationId,
                        'message' => 'Violation created successfully'
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to create violation',
                    'code' => 'CREATE_FAILED'
                ]);
            }
            break;
        
        // ============================================
        // UPDATE VIOLATION
        // ============================================
        case 'update':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Violation ID is required',
                    'code' => 'MISSING_ID'
                ]);
                exit();
            }
            
            $db = violationsGetDB();
            
            $stmt = $db->prepare("SELECT * FROM violations WHERE id = ?");
            $stmt->execute([$id]);
            $violation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$violation) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Violation not found',
                    'code' => 'NOT_FOUND'
                ]);
                exit();
            }
            
            $permissions = violationsGetUserPermissions($user['id'], $user['role']);
            
            $canEdit = $permissions['can_apply_sanctions'] || 
                       $permissions['can_edit_all'] ||
                       ($permissions['can_edit_own'] && $violation['user_id'] == $user['id']);
            
            if (!$canEdit) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Permission denied to edit this violation',
                    'code' => 'PERMISSION_DENIED'
                ]);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $fields = [];
            $values = [];
            
            $allowedFields = ['photo_url', 'responsibility', 'fullname', 'fine_amount', 'comment', 'progress', 'processed_date'];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $input[$field];
                }
            }
            
            if (empty($fields)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'No fields to update',
                    'code' => 'NO_DATA'
                ]);
                exit();
            }
            
            $fields[] = "updated_at = NOW()";
            $values[] = $id;
            
            $sql = "UPDATE violations SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $success = $stmt->execute($values);
            
            if ($success) {
                violationsLogActivity($user['id'], 'update_violation', "Updated violation #{$id}", $id);
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'message' => 'Violation updated successfully'
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update violation',
                    'code' => 'UPDATE_FAILED'
                ]);
            }
            break;
        
        // ============================================
        // UPLOAD PHOTO
        // ============================================
        case 'upload_photo':
            violationsRequirePermission($user, 'can_create', 'Permission denied to upload photos');
            
            $violationId = intval($_POST['violation_id'] ?? 0);
            
            if (!$violationId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Violation ID is required',
                    'code' => 'MISSING_ID'
                ]);
                exit();
            }
            
            if (!isset($_FILES['photo'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'No photo uploaded',
                    'code' => 'NO_FILE'
                ]);
                exit();
            }
            
            $file = $_FILES['photo'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Upload error: ' . $file['error'],
                    'code' => 'UPLOAD_ERROR'
                ]);
                exit();
            }
            
            $maxSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'File too large. Max: 10MB',
                    'code' => 'FILE_TOO_LARGE'
                ]);
                exit();
            }
            
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'];
            
            if (!in_array($ext, $allowedExts)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid file type',
                    'code' => 'INVALID_FILE_TYPE'
                ]);
                exit();
            }
            
            $filename = uniqid('violation_') . '_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filepath = $uploadDir . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to save file',
                    'code' => 'SAVE_FAILED'
                ]);
                exit();
            }
            
            $photoUrl = '/Agrohub_Engine/modules/violations/uploads/' . $filename;
            
            $db = violationsGetDB();
            
            $stmt = $db->prepare("
                INSERT INTO violation_photos (violation_id, filename, filepath, photo_url, filesize, uploaded_by, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $success = $stmt->execute([
                $violationId,
                $file['name'],
                $filename,
                $photoUrl,
                $file['size'],
                $user['id']
            ]);
            
            if ($success) {
                $stmtGet = $db->prepare("SELECT photo_url FROM violations WHERE id = ?");
                $stmtGet->execute([$violationId]);
                $existingPhotoUrl = $stmtGet->fetchColumn();
                
                $newPhotoUrl = $existingPhotoUrl ? $existingPhotoUrl . ',' . $photoUrl : $photoUrl;
                
                $stmtUpdate = $db->prepare("UPDATE violations SET photo_url = ? WHERE id = ?");
                $stmtUpdate->execute([$newPhotoUrl, $violationId]);
                
                violationsLogActivity($user['id'], 'upload_photo', "Uploaded photo for violation #{$violationId}", $violationId);
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'message' => 'Photo uploaded successfully',
                        'filename' => $filename,
                        'photo_url' => $photoUrl
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to save photo record',
                    'code' => 'DB_SAVE_FAILED'
                ]);
            }
            break;
        
        // ============================================
        // GET PHOTOS
        // ============================================
        case 'get_photos':
            $violationId = intval($_GET['violation_id'] ?? 0);
            
            if (!$violationId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Violation ID is required',
                    'code' => 'MISSING_ID'
                ]);
                exit();
            }
            
            $db = violationsGetDB();
            $stmt = $db->prepare("SELECT * FROM violation_photos WHERE violation_id = ? ORDER BY id ASC");
            $stmt->execute([$violationId]);
            $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'photos' => $photos
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
        
        // ============================================
        // GET BRANCHES
        // ============================================
        case 'branches':
            $db = violationsGetDB();
            $stmt = $db->query("SELECT id, name, code, address FROM branches ORDER BY name ASC");
            $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => ['branches' => $branches],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
        
        // ============================================
        // GET PERMISSIONS
        // ============================================
        case 'permissions':
            $permissions = violationsGetUserPermissions($user['id'], $user['role']);
            
            echo json_encode([
                'success' => true,
                'data' => ['permissions' => $permissions],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
        
        // ============================================
        // INVALID ACTION
        // ============================================
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action',
                'code' => 'INVALID_ACTION',
                'available_actions' => ['list', 'create', 'update', 'upload_photo', 'get_photos', 'branches', 'permissions']
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Violations API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'code' => 'INTERNAL_ERROR',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}