<?php
/**
 * Debug API - For testing database connections and authentication
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/debug_api_errors.log');

header('Content-Type: application/json; charset=utf-8');

// Create logs directory if it doesn't exist
$logDir = dirname(__DIR__) . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

require_once 'config.php';
require_once 'database.php';
require_once 'utils.php';

$action = $_GET['action'] ?? '';
$utils = new Utils();

try {
    switch ($action) {
        case 'test_db':
            testDatabaseConnections();
            break;
            
        case 'list_users':
            listUsers();
            break;
            
        case 'test_login':
            testLogin();
            break;
            
        default:
            $utils->sendError('Invalid action');
    }
} catch (Exception $e) {
    error_log('Debug API error: ' . $e->getMessage());
    $utils->sendError('Error: ' . $e->getMessage());
}

function testDatabaseConnections() {
    global $utils;
    
    $results = [
        'agrohub_erp' => false,
        'phplogin' => false
    ];
    
    // Test agrohub_erp connection
    try {
        $db = Database::getInstance();
        $conn = $db->getPDO();
        $stmt = $conn->query("SELECT DATABASE() as db_name");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $results['agrohub_erp'] = [
            'connected' => true,
            'db_name' => $result['db_name'],
            'tables' => []
        ];
        
        // Get tables
        $stmt = $conn->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $results['agrohub_erp']['tables'][] = $row[0];
        }
        
    } catch (Exception $e) {
        $results['agrohub_erp'] = [
            'connected' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // Test phplogin connection
    try {
        $phplogin = new PDO(
            "mysql:host=" . PHPL_DB_HOST . ";dbname=" . PHPL_DB_NAME . ";charset=" . PHPL_DB_CHARSET,
            PHPL_DB_USER,
            PHPL_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $phplogin->query("SELECT DATABASE() as db_name");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $results['phplogin'] = [
            'connected' => true,
            'db_name' => $result['db_name'],
            'tables' => []
        ];
        
        // Get tables
        $stmt = $phplogin->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $results['phplogin']['tables'][] = $row[0];
        }
        
    } catch (Exception $e) {
        $results['phplogin'] = [
            'connected' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // Return results
    if ($results['agrohub_erp']['connected'] && $results['phplogin']['connected']) {
        $utils->sendSuccess($results);
    } else {
        $errors = [];
        if (!$results['agrohub_erp']['connected']) {
            $errors[] = 'agrohub_erp: ' . $results['agrohub_erp']['error'];
        }
        if (!$results['phplogin']['connected']) {
            $errors[] = 'phplogin: ' . $results['phplogin']['error'];
        }
        
        $utils->sendError('Database connection issues: ' . implode(', ', $errors), 500, $results);
    }
}

function listUsers() {
    global $utils;
    
    $results = [
        'agrohub_users' => [],
        'phplogin_users' => []
    ];
    
    // Get users from agrohub_erp
    try {
        $db = Database::getInstance();
        $conn = $db->getPDO();
        
        $stmt = $conn->query("SELECT id, name, email, role, is_active FROM users LIMIT 10");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Remove sensitive fields
            $row['password'] = '********';
            $results['agrohub_users'][] = $row;
        }
        
    } catch (Exception $e) {
        $results['agrohub_error'] = $e->getMessage();
    }
    
    // Get users from phplogin
    try {
        $phplogin = new PDO(
            "mysql:host=" . PHPL_DB_HOST . ";dbname=" . PHPL_DB_NAME . ";charset=" . PHPL_DB_CHARSET,
            PHPL_DB_USER,
            PHPL_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $phplogin->query("SELECT id, username, email, role, status FROM accounts LIMIT 10");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Remove sensitive fields
            $row['password'] = '********';
            $results['phplogin_users'][] = $row;
        }
        
    } catch (Exception $e) {
        $results['phplogin_error'] = $e->getMessage();
    }
    
    $utils->sendSuccess($results);
}

function testLogin() {
    global $utils;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $identifier = $input['identifier'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($identifier) || empty($password)) {
        $utils->sendError('Identifier and password are required');
    }
    
    // Debug info to log
    error_log("Test login attempt with identifier: $identifier");
    
    // Try local authentication first
    try {
        $db = Database::getInstance();
        $user = null;
        
        // Try agrohub_erp users
        $stmt = $db->prepare("SELECT * FROM users WHERE (email = ? OR name = ? OR username = ?)");
        $stmt->execute([$identifier, $identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            error_log("Local authentication successful for user: {$user['id']}");
            unset($user['password']);
            
            $token = 'debug_token_' . time();
            
            $utils->sendSuccess([
                'user' => $user,
                'token' => $token,
                'source' => 'agrohub_erp'
            ]);
            return;
        }
    } catch (Exception $e) {
        error_log("Local auth error: " . $e->getMessage());
    }
    
    // Try phplogin authentication
    try {
        $phplogin = new PDO(
            "mysql:host=" . PHPL_DB_HOST . ";dbname=" . PHPL_DB_NAME . ";charset=" . PHPL_DB_CHARSET,
            PHPL_DB_USER,
            PHPL_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Try to find user by username or email
        $stmt = $phplogin->prepare("SELECT * FROM accounts WHERE username = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Debug user data
            $debugUser = $user;
            $debugUser['password'] = substr($debugUser['password'], 0, 10) . '...'; 
            error_log("PHPLogin user found: " . print_r($debugUser, true));
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                error_log("PHPLogin authentication successful for user: {$user['username']}");
                unset($user['password']);
                
                $token = 'debug_token_' . time();
                
                $utils->sendSuccess([
                    'user' => $user,
                    'token' => $token,
                    'source' => 'phplogin'
                ]);
                return;
            } else {
                error_log("PHPLogin password verification failed");
                $utils->sendError('Invalid password', 401, ['password_info' => 'Invalid password for phplogin user']);
                return;
            }
        }
    } catch (Exception $e) {
        error_log("PHPLogin auth error: " . $e->getMessage());
    }
    
    // Authentication failed
    $utils->sendError('Invalid identifier or password', 401, ['error_details' => 'User not found in any database']);
}
?>
