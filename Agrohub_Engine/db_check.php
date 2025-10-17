<?php
/**
 * Agrohub ERP Database Structure Checker and Fixer
 * This script checks and fixes common database structure issues
 */

// Set headers for browser output
header('Content-Type: text/html; charset=utf-8');
echo '<html><head><title>Database Structure Check</title>';
echo '<style>body{font-family:sans-serif;max-width:1000px;margin:0 auto;padding:20px}
.success{color:green;font-weight:bold} .error{color:red;font-weight:bold}
.warning{color:orange} pre{background:#f5f5f5;padding:10px;border-radius:5px;overflow:auto}
h2{border-bottom:1px solid #ddd;padding-bottom:5px}</style>';
echo '</head><body>';
echo '<h1>Agrohub ERP Database Structure Checker</h1>';

// Include the database configuration
require_once __DIR__ . '/api/config.php';

try {
    // Connect to the database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo '<p class="success">✅ Connected to agrohub_erp database successfully</p>';
    
    // Connect to phplogin database
    $phploginDb = new PDO(
        "mysql:host=" . PHPL_DB_HOST . ";dbname=" . PHPL_DB_NAME . ";charset=" . PHPL_DB_CHARSET,
        PHPL_DB_USER,
        PHPL_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo '<p class="success">✅ Connected to phplogin database successfully</p>';
    
    echo '<h2>Checking Database Structure</h2>';
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersTableExists = $stmt->rowCount() > 0;
    
    if ($usersTableExists) {
        echo '<p class="success">✅ Users table exists in agrohub_erp</p>';
        
        // Check for phplogin_id column in users table
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'phplogin_id'");
        $phploginIdColumnExists = $stmt->rowCount() > 0;
        
        if ($phploginIdColumnExists) {
            echo '<p class="success">✅ phplogin_id column exists in users table</p>';
        } else {
            echo '<p class="error">❌ phplogin_id column is missing in users table</p>';
            
            // Add the column
            echo '<p>Adding phplogin_id column to users table...</p>';
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN phplogin_id INT NULL AFTER id");
                echo '<p class="success">✅ phplogin_id column added successfully</p>';
            } catch (Exception $e) {
                echo '<p class="error">❌ Failed to add phplogin_id column: ' . $e->getMessage() . '</p>';
            }
        }
        
        // Check for username column in users table (some code expects this)
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'username'");
        $usernameColumnExists = $stmt->rowCount() > 0;
        
        if ($usernameColumnExists) {
            echo '<p class="success">✅ username column exists in users table</p>';
        } else {
            echo '<p class="error">❌ username column is missing in users table</p>';
            
            // Add the column
            echo '<p>Adding username column to users table...</p>';
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(255) AFTER name");
                echo '<p class="success">✅ username column added successfully</p>';
            } catch (Exception $e) {
                echo '<p class="error">❌ Failed to add username column: ' . $e->getMessage() . '</p>';
            }
        }
    } else {
        echo '<p class="error">❌ Users table does not exist in agrohub_erp</p>';
    }
    
    // Check for violations table
    $stmt = $pdo->query("SHOW TABLES LIKE 'violations'");
    $violationsTableExists = $stmt->rowCount() > 0;
    
    if ($violationsTableExists) {
        echo '<p class="success">✅ Violations table exists</p>';
    } else {
        echo '<p class="error">❌ Violations table is missing</p>';
        
        // Create the violations table
        echo '<p>Creating violations table...</p>';
        try {
            $pdo->exec("
                CREATE TABLE violations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    reported_by VARCHAR(100) NOT NULL,
                    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                    status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending',
                    assigned_to VARCHAR(100) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    resolved_at TIMESTAMP NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            echo '<p class="success">✅ Violations table created successfully</p>';
        } catch (Exception $e) {
            echo '<p class="error">❌ Failed to create violations table: ' . $e->getMessage() . '</p>';
        }
    }
    
    // Display some users for reference
    echo '<h2>User Accounts</h2>';
    
    // Show phplogin users
    $stmt = $phploginDb->query("SELECT id, username, email, role FROM accounts LIMIT 5");
    $phploginUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<h3>PHPLogin Users:</h3>';
    if (count($phploginUsers) > 0) {
        echo '<pre>' . print_r($phploginUsers, true) . '</pre>';
    } else {
        echo '<p class="warning">No users found in phplogin database</p>';
    }
    
    // Show agrohub_erp users
    $stmt = $pdo->query("SELECT id, name, email, role, phplogin_id FROM users LIMIT 5");
    $erpUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<h3>Agrohub ERP Users:</h3>';
    if (count($erpUsers) > 0) {
        echo '<pre>' . print_r($erpUsers, true) . '</pre>';
    } else {
        echo '<p class="warning">No users found in agrohub_erp database</p>';
    }
    
    // Link accounts if needed
    echo '<h2>User Account Linking</h2>';
    echo '<p>This will link PHPLogin accounts to Agrohub ERP accounts</p>';
    
    if (isset($_GET['link']) && $_GET['link'] === 'users') {
        // Get all phplogin users
        $stmt = $phploginDb->query("SELECT id, username, email, role FROM accounts");
        $allPhploginUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($allPhploginUsers as $phploginUser) {
            // Check if already exists in ERP by email or username
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phplogin_id = ?");
            $stmt->execute([$phploginUser['id']]);
            $erpUserByPhpLoginId = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($erpUserByPhpLoginId) {
                echo '<p class="success">✅ PHPLogin user ' . htmlspecialchars($phploginUser['username']) . 
                     ' (ID: ' . $phploginUser['id'] . ') already linked to ERP user ID: ' . $erpUserByPhpLoginId['id'] . '</p>';
                continue;
            }
            
            // Then check by username or email
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? OR name = ?");
            $email = $phploginUser['email'] ?: ($phploginUser['username'] . '@local');
            $stmt->execute([$email, $phploginUser['username'], $phploginUser['username']]);
            $erpUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($erpUser) {
                // Link existing user
                $stmt = $pdo->prepare("UPDATE users SET phplogin_id = ? WHERE id = ?");
                $stmt->execute([$phploginUser['id'], $erpUser['id']]);
                echo '<p class="success">✅ Linked PHPLogin user ' . htmlspecialchars($phploginUser['username']) . 
                     ' (ID: ' . $phploginUser['id'] . ') to ERP user ID: ' . $erpUser['id'] . '</p>';
            } else {
                // Create new ERP user with uniqueness check for email
                $name = $phploginUser['username'];
                $baseEmail = $phploginUser['email'] ?: ($phploginUser['username'] . '@local');
                $email = $baseEmail;
                $counter = 1;
                
                // Check if email exists and add counter if needed
                while (true) {
                    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $checkStmt->execute([$email]);
                    if (!$checkStmt->fetch()) break;
                    
                    // Email exists, add counter to make it unique
                    $email = str_replace('@', '.'.$counter.'@', $baseEmail);
                    $counter++;
                }
                
                $tempPassword = password_hash('temp' . time(), PASSWORD_DEFAULT);
                $role = strtolower($phploginUser['role']) === 'ადმინი' ? 'admin' : 'user';
                
                try {
                    $stmt = $pdo->prepare("
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
                    $newUserId = $pdo->lastInsertId();
                    echo '<p class="success">✅ Created ERP user for PHPLogin user ' . htmlspecialchars($phploginUser['username']) . 
                         ' with ID: ' . $newUserId . ($email !== $baseEmail ? ' (unique email: '.$email.')' : '') . '</p>';
                } catch (Exception $e) {
                    echo '<p class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '<p>Please fix user ' . htmlspecialchars($phploginUser['username']) . ' manually.</p>';
                }
            }
        }
    } else {
        echo '<p><a href="?link=users" style="display:inline-block;padding:10px 15px;background:#4CAF50;color:white;text-decoration:none;border-radius:5px;margin-top:10px;">Link PHPLogin Users to Agrohub ERP</a></p>';
    }
    
    echo '<hr>';
    echo '<p><a href="/Agrohub_Engine/">Back to Agrohub ERP</a></p>';
    
} catch (Exception $e) {
    echo '<p class="error">❌ Error: ' . $e->getMessage() . '</p>';
}

echo '</body></html>';
