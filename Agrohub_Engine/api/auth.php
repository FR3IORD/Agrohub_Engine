<?php
/**
 * Authentication API - ENHANCED VERSION WITH ERROR HANDLING
 */

// Start by enabling error reporting for debugging
error_reporting(E_ALL);
// NOTE: We set display_errors and error_log after loading config so we can
// honor environment and LOG_LEVEL settings. Default to not displaying errors
// until config is available.
ini_set('display_errors', 0);

// JSON header FIRST
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Credentials: true');

// Allow CORS
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// NOTE: Request logging moved below (after config is loaded) so we can
// honor configuration flags and avoid unconditional per-request disk I/O.

// Suppress any output from includes
ob_start();
require_once 'config.php';
require_once 'database.php';
require_once 'utils.php';
ob_end_clean();

// Conditional request logging helper
function auth_request_log($message) {
    $logDir = __DIR__ . '/../logs';
    $logFile = $logDir . '/auth_requests.log';

    // Only log if globally enabled and either debug level or development environment
    if (!defined('LOG_ENABLED') || !LOG_ENABLED) {
        return;
    }
    if (!defined('ENVIRONMENT')) {
        return;
    }
    if (ENVIRONMENT !== 'development' && (!defined('LOG_LEVEL') || LOG_LEVEL !== 'debug')) {
        // Not development and not debug logging level
        return;
    }

    $logMsg = date('Y-m-d H:i:s') . " | " . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . " " . ($_SERVER['REQUEST_URI'] ?? '') . " | " . $message . "\n";
    try {
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        if (file_exists($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
            @rename($logFile, $logFile . '.old');
        }
        // Suppress errors to avoid breaking requests
        @file_put_contents($logFile, $logMsg, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        // swallow
    }
}

// Optionally log the incoming request (only in development/debug)
auth_request_log('request received');

// Configure PHP error logging target based on environment
if (defined('ENVIRONMENT') && (ENVIRONMENT === 'development' || (defined('LOG_LEVEL') && LOG_LEVEL === 'debug'))) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/auth_debug.log');
} else {
    // Production-like behavior: log only to main log file if enabled
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    if (defined('LOG_ENABLED') && LOG_ENABLED && defined('LOG_FILE')) {
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/../logs/app.log');
    } else {
        ini_set('log_errors', 0);
    }
}

// Get action
$action = $_GET['action'] ?? '';

if (empty($action)) {
    // Try to get action from POST body
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

try {
    $utils = new Utils();
    $db = Database::getInstance()->getConnection();
    
    // Check database structure and ensure phplogin_id exists
    ensureDatabaseStructure($db);
    
    switch ($action) {
        case 'login':
            handleLogin($db, $utils);
            break;
        case 'verify':
            handleVerify($db, $utils);
            break;
        case 'logout':
            handleLogout($utils);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit();
    }
    
} catch (Exception $e) {
    error_log('Auth exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit();
}

// Ensure database structure has required columns
function ensureDatabaseStructure($db) {
    $flagFile = __DIR__ . '/../.schema_ok';
    // If we've already verified/updated schema, skip expensive checks
    if (file_exists($flagFile)) {
        return;
    }
    static $checked = false;
    if ($checked) return;
    try {
        // Quick check that users table exists
        try {
            $test = $db->query("SELECT 1 FROM users LIMIT 1");
        } catch (Exception $e) {
            // Users table missing or inaccessible; skip schema updates
            error_log('Users table check failed: ' . $e->getMessage());
            return;
        }
        // Check if phplogin_id column exists
        $cols = ['phplogin_id','username'];
        $missing = [];
        foreach ($cols as $col) {
            try {
                $s = $db->query("SHOW COLUMNS FROM users LIKE '$col'");
                $exists = ($s->rowCount() > 0);
            } catch (Exception $e) {
                $exists = false;
            }
            if (!$exists) $missing[] = $col;
        }

        if (!empty($missing)) {
            // Only attempt schema changes if we are in development mode to avoid locks in production
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                foreach ($missing as $col) {
                    if ($col === 'phplogin_id') {
                        error_log("Adding missing phplogin_id column to users table");
                        $db->exec("ALTER TABLE users ADD COLUMN phplogin_id INT NULL AFTER id");
                    }
                    if ($col === 'username') {
                        error_log("Adding missing username column to users table");
                        $db->exec("ALTER TABLE users ADD COLUMN username VARCHAR(255) AFTER name");
                    }
                }
            } else {
                error_log('Database schema missing columns: ' . implode(',', $missing) . ' - run migrations in setup environment');
            }
        }
        $checked = true;
        
    // If we reached here without exception, mark schema as OK to avoid repeating ALTERs on every request
    @file_put_contents($flagFile, date('c') . " - schema verified\n");
    } catch (Exception $e) {
        error_log("Database structure check failed: " . $e->getMessage());
        // Continue anyway - it might be another issue
    }
}

function handleLogin($db, $utils) {
    try {
        // Get input data
        $rawInput = file_get_contents('php://input');
        error_log('Raw input: ' . $rawInput);
        
        $input = json_decode($rawInput, true);
        error_log('Login attempt with: ' . json_encode($input));
        
        $identifier = trim($input['identifier'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($identifier) || empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'მომხმარებელი და პაროლი აუცილებელია']);
            exit();
        }
        
        error_log("Attempting login for user: $identifier");
        
        // Try to authenticate against phplogin database
        $phploginUser = authenticatePhploginDirect($identifier, $password);
        
        if ($phploginUser) {
            error_log("Successfully authenticated with phplogin: " . json_encode($phploginUser));
            
            // Start PHP session for phplogin users
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            
            $_SESSION['loggedin'] = true;
            $_SESSION['id'] = $phploginUser['id']; // Store phplogin user ID
            $_SESSION['username'] = $phploginUser['username'];
            $_SESSION['role'] = $phploginUser['role'];
            $_SESSION['authenticated'] = 1;
            $_SESSION['phplogin'] = true; // Mark as phplogin user
            
            // Create or update local user
            $localUser = getOrCreateLocalUser($db, $phploginUser);
            
            if ($localUser) {
                // Generate token
                $token = $utils->generateJWT([
                    'user_id' => $localUser['id'],
                    'phplogin_id' => $phploginUser['id'], // Include phplogin ID in token
                    'email' => $localUser['email'] ?? '',
                    'role' => $localUser['role'] ?? 'user',
                    'exp' => time() + 3600
                ]);
                
                // Set cookie with appropriate path and domain
                setcookie('auth_token', $token, [
                    'expires' => time() + 3600,
                    'path' => '/',
                    'secure' => false,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                
                // Clean user data
                unset($localUser['password']);
                
                // Set default avatar
                $name = $localUser['name'] ?? $localUser['username'] ?? 'User';
                $localUser['avatar'] = $localUser['avatar'] ?? strtoupper(substr($name, 0, 1));
                
                // Store phplogin ID reference
                $localUser['phplogin_id'] = $phploginUser['id'];
                
                // Log successful login
                error_log("Login successful for phplogin user: $identifier");
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'token' => $token,
                        'user' => $localUser,
                        'source' => 'phplogin'
                    ],
                    'message' => 'წარმატებით შეხვედით'
                ]);
                exit();
            }
        }
        
        // If phplogin fails, try local database
        error_log("Phplogin auth failed, trying local database");
        $localUser = authenticateLocalUser($db, $identifier, $password);
        
        if ($localUser) {
            error_log("Successfully authenticated with local database: " . $localUser['id']);
            
            // Check if active
            if (isset($localUser['is_active']) && !$localUser['is_active']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'თქვენი ანგარიში დეაქტივირებულია']);
                exit();
            }
            
            // Generate token
            $token = $utils->generateJWT([
                'user_id' => $localUser['id'],
                'email' => $localUser['email'] ?? '',
                'role' => $localUser['role'] ?? 'user',
                'exp' => time() + 3600
            ]);
            
            // Set cookie
            setcookie('auth_token', $token, time() + 3600, '/', '', false, true);
            
            // Clean user data
            unset($localUser['password']);
            $localUser['avatar'] = $localUser['avatar'] ?? strtoupper(substr($localUser['name'] ?? '', 0, 1));
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'user' => $localUser
                ],
                'message' => 'წარმატებით შეხვედით'
            ]);
            exit();
        }
        
        // Authentication failed
        error_log("Authentication failed for: $identifier");
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'არასწორი მომხმარებელი ან პაროლი']);
        exit();
        
    } catch (Exception $e) {
        error_log("Login exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Authentication error: ' . $e->getMessage()]);
        exit();
    }
}

function authenticatePhploginDirect($identifier, $password) {
    try {
        error_log("Connecting to PHPLogin database");
        
        $pdo = new PDO(
            "mysql:host=" . PHPL_DB_HOST . ";dbname=" . PHPL_DB_NAME . ";charset=utf8mb4",
            PHPL_DB_USER, 
            PHPL_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // First try with exact username match
        error_log("Querying for username: $identifier");
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE username = ? LIMIT 1");
        $stmt->execute([$identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If not found, try email
        if (!$user) {
            error_log("Username not found, trying email");
            $stmt = $pdo->prepare("SELECT * FROM accounts WHERE email = ? LIMIT 1");
            $stmt->execute([$identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // If user exists, verify password
        if ($user && password_verify($password, $user['password'])) {
            error_log("Password verified for phplogin user: " . $user['username']);
            return [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'] ?? ($user['username'] . '@local'),
                'role' => $user['role'] ?? 'user',
                'status' => $user['status']
            ];
        }
        
        error_log("PHPLogin authentication failed for: $identifier");
        return null;
        
    } catch (Exception $e) {
        error_log("PHPLogin error: " . $e->getMessage());
        return null;
    }
}

function authenticateLocalUser($db, $identifier, $password) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE (email = ? OR name = ? OR username = ?)");
        $stmt->execute([$identifier, $identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Local authentication error: " . $e->getMessage());
        return null;
    }
}

function getOrCreateLocalUser($db, $phploginUser) {
    try {
        // First, ensure the phplogin_id column exists
        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'phplogin_id'");
            if ($stmt->rowCount() === 0) {
                // Add the column if it doesn't exist
                $db->exec("ALTER TABLE users ADD COLUMN phplogin_id INT NULL AFTER id");
                error_log("Added phplogin_id column to users table");
            }
        } catch (Exception $e) {
            error_log("Error checking/creating phplogin_id column: " . $e->getMessage());
            // Continue anyway - it might work with existing users
        }
        
        // Check if user already exists by phplogin_id
        $stmt = $db->prepare("SELECT * FROM users WHERE phplogin_id = ?");
        $stmt->execute([$phploginUser['id']]);
        $localUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($localUser) {
            error_log("Found existing local user with phplogin_id: " . $phploginUser['id']);
            return $localUser;
        }
        
        // Check by email if exists
        if (!empty($phploginUser['email'])) {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$phploginUser['email']]);
            $localUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($localUser) {
                // Link existing user to phplogin id
                error_log("Linking existing local user by email to phplogin_id: " . $phploginUser['id']);
                $updateStmt = $db->prepare("UPDATE users SET phplogin_id = ? WHERE id = ?");
                $updateStmt->execute([$phploginUser['id'], $localUser['id']]);
                return $localUser;
            }
        }
        
        // Check by username if exists
        $stmt = $db->prepare("SELECT * FROM users WHERE name = ? OR username = ?");
        $stmt->execute([$phploginUser['username'], $phploginUser['username']]);
        $localUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($localUser) {
            // Link existing user to phplogin id
            error_log("Linking existing local user by username to phplogin_id: " . $phploginUser['id']);
            $updateStmt = $db->prepare("UPDATE users SET phplogin_id = ? WHERE id = ?");
            $updateStmt->execute([$phploginUser['id'], $localUser['id']]);
            return $localUser;
        }
        
        // Create new user
        error_log("Creating new local user from phplogin user: " . json_encode($phploginUser));
        
        $name = $phploginUser['username'];
        $email = $phploginUser['email'] ?? ($phploginUser['username'] . '@local');
        $tempPassword = password_hash(uniqid(), PASSWORD_DEFAULT);
        $role = mapPhploginRole($phploginUser['role'] ?? '');
        
        // Check if username column exists
        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'username'");
            $hasUsernameColumn = ($stmt->rowCount() > 0);
            
            if (!$hasUsernameColumn) {
                // Add the column if it doesn't exist
                $db->exec("ALTER TABLE users ADD COLUMN username VARCHAR(255) AFTER name");
                error_log("Added username column to users table");
                $hasUsernameColumn = true; // Now we have it
            }
        } catch (Exception $e) {
            error_log("Error checking/creating username column: " . $e->getMessage());
            // Continue with existing structure
            $hasUsernameColumn = false;
        }
        
        try {
            if ($hasUsernameColumn) {
                $stmt = $db->prepare("
                    INSERT INTO users (phplogin_id, username, name, email, password, role, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([
                    $phploginUser['id'],
                    $phploginUser['username'],
                    $name,
                    $email,
                    $tempPassword,
                    $role
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO users (phplogin_id, name, email, password, role, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([
                    $phploginUser['id'],
                    $name,
                    $email,
                    $tempPassword,
                    $role
                ]);
            }
            
            $newUserId = $db->lastInsertId();
            
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$newUserId]);
            $newUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Created new local user with ID: $newUserId");
            return $newUser;
            
        } catch (Exception $e) {
            error_log("Error creating local user: " . $e->getMessage());
            throw $e; // Re-throw to be handled by the caller
        }
        
    } catch (Exception $e) {
        error_log("Error in getOrCreateLocalUser: " . $e->getMessage());
        throw $e; // Re-throw to be handled by the caller
    }
}

function mapPhploginRole($phploginRole) {
    // Map phplogin roles to local roles
    $phploginRole = strtolower($phploginRole);
    
    if ($phploginRole == 'ადმინი' || $phploginRole == 'admin') {
        return 'admin';
    }
    
    if (strpos($phploginRole, 'gm') !== false || strpos($phploginRole, 'manager') !== false) {
        return 'manager';
    }
    
    return 'user';
}

function handleVerify($db, $utils) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
    
    if (empty($token)) {
        $token = $_COOKIE['auth_token'] ?? '';
    }
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No token']);
        exit();
    }
    
    $payload = $utils->verifyJWT($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit();
    }
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    unset($user['password']);
    $user['avatar'] = $user['avatar'] ?: strtoupper(substr($user['name'] ?? '', 0, 1));
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user' => $user,
            'token' => $token,
            'valid' => true
        ]
    ]);
    exit();
}

function handleLogout($utils) {
    setcookie('auth_token', '', time() - 3600, '/', '', false, true);
    echo json_encode(['success' => true, 'message' => 'წარმატებით გამოხვედით']);
    exit();
}
?>