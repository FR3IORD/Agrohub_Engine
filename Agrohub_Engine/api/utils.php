<?php
/**
 * Agrohub ERP Platform - Utility Functions
 * 
 * Common utility functions and helpers
 */

require_once 'config.php';

class Utils {
    private $db;
    private static $columnCache = [];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Check if a column exists in a table (cached per-request)
     */
    public function hasColumn($table, $column) {
        $key = $table . '.' . $column;
        if (isset(self::$columnCache[$key])) {
            return self::$columnCache[$key];
        }
        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            $exists = ($stmt->rowCount() > 0);
        } catch (Exception $e) {
            error_log('hasColumn error: ' . $e->getMessage());
            $exists = false;
        }
        self::$columnCache[$key] = $exists;
        return $exists;
    }
    
    public function sendResponse($data, $success = true, $message = '', $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'data' => $data,
            'message' => $message,
            'error' => $success ? null : $message
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    public function sendSuccess($data, $message = 'Success') {
        $this->sendResponse($data, true, $message, 200);
    }
    
    public function sendError($message, $statusCode = 400, $data = null) {
        $this->sendResponse($data, false, $message, $statusCode);
    }
    
    public function generateJWT($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $headerEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $payloadEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, JWT_SECRET, true);
        $signatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }
    
    public function verifyJWT($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        
        list($header, $payload, $signature) = $parts;
        
        $expectedSignature = hash_hmac('sha256', $header . "." . $payload, JWT_SECRET, true);
        $expectedSignatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));
        
        if ($signature !== $expectedSignatureEncoded) return false;
        
        $payloadData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) return false;
        
        return $payloadData;
    }
    
    /**
     * Verify user in PHPLogin database
     */
    public function verifyExternalPhplogin($identifier, $password) {
        try {
            $pdo = new PDO(
                "mysql:host=" . PHPL_DB_HOST . ";dbname=" . PHPL_DB_NAME . ";charset=" . PHPL_DB_CHARSET,
                PHPL_DB_USER,
                PHPL_DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Try to find user by username or email
            $sql = "SELECT * FROM " . PHPL_DB_TABLE_ACCOUNTS . " WHERE username = ? OR email = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                return [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'] ?? null,
                    'role' => $user['role'] ?? 'user',
                    'source' => 'phplogin'
                ];
            }
            
            return null;
            
        } catch (PDOException $e) {
            error_log("PHPLogin verification error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create local user from external authentication - FIXED VERSION
     */
    public function createLocalUserFromExternal($externalUser, $defaultRole = 'user') {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if username column exists (cached)
            $hasUsername = $this->hasColumn('users', 'username');
            
            $email = $externalUser['email'] ?? ($externalUser['username'] . '@agrohub.local');
            $username = $externalUser['username'];
            
            // Check if user already exists by email or username
            if ($hasUsername) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
                $stmt->execute([$email, $username]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
            }
            
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingUser) {
                return $existingUser;
            }
            
            // Create new user
            $name = $externalUser['username']; // Use username as name
            $hashedPassword = password_hash('temp_password_' . time(), PASSWORD_DEFAULT);
            
            if ($hasUsername) {
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, username, email, password, role, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$name, $username, $email, $hashedPassword, $externalUser['role'] ?? $defaultRole]);
            } else {
                // Fallback for when username column doesn't exist
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, is_active, created_at) 
                    VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$name, $email, $hashedPassword, $externalUser['role'] ?? $defaultRole]);
            }
            
            $userId = $pdo->lastInsertId();
            
            // Get the created user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Create local user error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user modules
     */
    public function getUserModules($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT m.*, um.status 
                FROM modules m 
                JOIN user_modules um ON m.id = um.module_id 
                WHERE um.user_id = ? AND m.is_active = 1 AND um.status = 'active'
                ORDER BY m.category, m.name
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get modules error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Authenticate request using JWT token
     */
    public function authenticateRequest() {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $authHeader);
        
        if (empty($token)) {
            $token = $_COOKIE['auth_token'] ?? '';
        }
        
        if (empty($token)) return false;
        
        $payload = $this->verifyJWT($token);
        if (!$payload || !isset($payload['user_id'])) return false;
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$payload['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }
}
?>