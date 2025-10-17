<?php
/**
 * Complete Setup Script for Agrohub ERP Platform
 * This will fix all database issues and create the admin user
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🚀 Agrohub ERP Platform Setup</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:40px;} .success{color:green;} .error{color:red;} .info{color:blue;} code{background:#f0f0f0;padding:2px 5px;}</style>";

try {
    // Connect to agrohub_erp database
    $pdo = new PDO("mysql:host=localhost;dbname=agrohub_erp;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>📊 Database Setup</h2>";
    
    // Step 1: Check and add username column
    echo "<h3>Step 1: Fix Users Table Schema</h3>";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'username'");
    $hasUsername = $stmt->fetch();
    
    if (!$hasUsername) {
        echo "<div class='info'>🔧 Adding username column...</div>";
        $pdo->exec("ALTER TABLE users ADD COLUMN username varchar(100) DEFAULT NULL AFTER email");
        
        // Add unique index (ignore if exists)
        try {
            $pdo->exec("ALTER TABLE users ADD UNIQUE KEY idx_username (username)");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                throw $e;
            }
        }
        
        // Update existing users
        $pdo->exec("UPDATE users SET username = SUBSTRING_INDEX(email, '@', 1) WHERE username IS NULL");
        
        echo "<div class='success'>✅ Username column added and configured</div>";
    } else {
        echo "<div class='success'>✅ Username column already exists</div>";
    }
    
    // Step 2: Create admin user
    echo "<h3>Step 2: Create Admin User</h3>";
    
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (name, username, email, password, role, is_active, avatar, created_at) 
            VALUES (?, ?, ?, ?, ?, 1, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            username = VALUES(username), 
            password = VALUES(password), 
            role = VALUES(role),
            is_active = 1
        ");
        
        $stmt->execute([
            'Administrator',
            'admin',
            'admin@agrohub.ge',
            $adminPassword,
            'admin',
            'A'
        ]);
        
        echo "<div class='success'>✅ Admin user created/updated successfully</div>";
        echo "<div class='info'>📋 Credentials: <code>admin</code> / <code>admin123</code></div>";
        
    } catch (PDOException $e) {
        echo "<div class='error'>❌ Failed to create admin user: " . $e->getMessage() . "</div>";
    }
    
    // Step 3: Install violations module
    echo "<h3>Step 3: Install Violations Module</h3>";
    
    try {
        // Check if violations module exists
        $stmt = $pdo->prepare("SELECT id FROM modules WHERE technical_name = 'violations'");
        $stmt->execute();
        $module = $stmt->fetch();
        
        if (!$module) {
            $stmt = $pdo->prepare("
                INSERT INTO modules (name, technical_name, category, description, icon, color, version, features, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            
            $stmt->execute([
                'Violations Management',
                'violations',
                'Services',
                'Comprehensive violation reporting and management system',
                'fas fa-exclamation-triangle',
                'bg-red-500',
                '1.0.0',
                '["Violation Reporting","Report Management","Analytics","Dashboard Integration"]'
            ]);
            
            $moduleId = $pdo->lastInsertId();
            echo "<div class='success'>✅ Violations module installed (ID: $moduleId)</div>";
        } else {
            $moduleId = $module['id'];
            echo "<div class='success'>✅ Violations module already exists (ID: $moduleId)</div>";
        }
        
        // Auto-install for admin user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin' OR email = 'admin@agrohub.ge'");
        $stmt->execute();
        $adminUser = $stmt->fetch();
        
        if ($adminUser) {
            // Check if already installed
            $stmt = $pdo->prepare("SELECT id FROM user_modules WHERE user_id = ? AND module_id = ?");
            $stmt->execute([$adminUser['id'], $moduleId]);
            
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO user_modules (user_id, module_id, status, installed_at) VALUES (?, ?, 'active', NOW())");
                $stmt->execute([$adminUser['id'], $moduleId]);
                echo "<div class='success'>✅ Violations module auto-installed for admin</div>";
            } else {
                echo "<div class='success'>✅ Violations module already installed for admin</div>";
            }
        }
        
    } catch (PDOException $e) {
        echo "<div class='error'>❌ Failed to install violations module: " . $e->getMessage() . "</div>";
    }
    
    // Step 4: Test connections
    echo "<h3>Step 4: Test Database Connections</h3>";
    
    // Test phplogin connection
    try {
        $phploginPdo = new PDO("mysql:host=localhost;dbname=phplogin;charset=utf8mb4", "root", "root");
        $phploginPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $phploginPdo->query("SELECT COUNT(*) as count FROM accounts");
        $result = $stmt->fetch();
        echo "<div class='success'>✅ PHPLogin database connected: {$result['count']} users available</div>";
        
    } catch (PDOException $e) {
        echo "<div class='error'>❌ PHPLogin database connection failed: " . $e->getMessage() . "</div>";
    }
    
    echo "<h2>🎉 Setup Complete!</h2>";
    echo "<div class='success'>";
    echo "<h3>✅ What was set up:</h3>";
    echo "• Users table schema fixed (username column added)<br>";
    echo "• Admin user created with credentials: <code>admin</code> / <code>admin123</code><br>";
    echo "• Violations module installed and activated<br>";
    echo "• Database connections tested and working<br>";
    echo "</div>";
    
    echo "<h3>🚀 Next Steps:</h3>";
    echo "<ol>";
    echo "<li><a href='/Agrohub_Engine/' target='_blank' style='color:blue;'>Go to Login Page</a></li>";
    echo "<li>Login with: <code>admin</code> / <code>admin123</code></li>";
    echo "<li>You can also login with any PHPLogin user (IT, admins, GM gelovani, etc.)</li>";
    echo "<li>Access violations module at: <a href='/Agrohub_Engine/modules/violations/' target='_blank' style='color:blue;'>/modules/violations/</a></li>";
    echo "</ol>";
    
    echo "<h3>📱 Available User Accounts:</h3>";
    echo "<div style='background:#f9f9f9;padding:10px;margin:10px 0;'>";
    echo "<strong>Local Admin:</strong> admin / admin123<br>";
    echo "<strong>PHPLogin Users:</strong> IT, admins, GM gelovani, GM vake, etc. (use their existing passwords)<br>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Setup failed: " . $e->getMessage() . "</div>";
    echo "<div class='info'>💡 Make sure MySQL is running and the database 'agrohub_erp' exists.</div>";
}
?>
