<?php
// Debug file to test database connections and user authentication
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Set headers to avoid caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agrohub ERP Login Debugger</title>
    <style>
        body { font-family: -apple-system, system-ui, sans-serif; line-height: 1.5; padding: 20px; max-width: 1000px; margin: 0 auto; }
        h1 { color: #333; }
        h2 { color: #555; margin-top: 30px; padding-top: 10px; border-top: 1px solid #eee; }
        code { background: #f5f5f5; padding: 2px 5px; border-radius: 3px; font-size: 0.9em; }
        .success { color: #22c55e; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; }
        tr:hover { background-color: #f9f9f9; }
        .test-btn { padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .test-btn:hover { background: #2563eb; }
        .box { background: #f9fafb; border: 1px solid #e5e7eb; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .debug-form { display: flex; gap: 10px; margin-bottom: 20px; }
        .debug-form input[type="text"], .debug-form input[type="password"] { 
            padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; flex-grow: 1;
        }
        #result { margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 6px; }
    </style>
</head>
<body>
    <h1>🔍 Agrohub ERP Login Debugger</h1>
    <p>This tool tests the login system and database connections.</p>
    
    <div class="box">
        <h2>Test Database Connections</h2>
        <button id="test-db" class="test-btn">Test DB Connections</button>
        <div id="db-results"></div>
    </div>
    
    <div class="box">
        <h2>List Users from Databases</h2>
        <button id="list-users" class="test-btn">List Users</button>
        <div id="users-results"></div>
    </div>
    
    <div class="box">
        <h2>Manual Login Test</h2>
        <div class="debug-form">
            <input type="text" id="identifier" placeholder="Username or Email">
            <input type="password" id="password" placeholder="Password">
            <button id="test-login" class="test-btn">Test Login</button>
        </div>
        <div id="login-results"></div>
    </div>
    
    <div id="result"></div>
    
    <script>
        // Test database connections
        document.getElementById('test-db').addEventListener('click', async () => {
            const results = document.getElementById('db-results');
            results.innerHTML = '<p>Testing connections...</p>';
            
            try {
                const response = await fetch('api/debug_api.php?action=test_db', {
                    method: 'POST'
                });
                
                const data = await response.json();
                let html = '<h3>Database Connection Results:</h3>';
                
                if (data.success) {
                    html += '<p class="success">✅ All database connections successful!</p>';
                    html += '<pre>' + JSON.stringify(data.data, null, 2) + '</pre>';
                } else {
                    html += '<p class="error">❌ Connection error: ' + data.error + '</p>';
                }
                
                results.innerHTML = html;
            } catch (error) {
                results.innerHTML = '<p class="error">❌ Error: ' + error.message + '</p>';
            }
        });
        
        // List users
        document.getElementById('list-users').addEventListener('click', async () => {
            const results = document.getElementById('users-results');
            results.innerHTML = '<p>Fetching users...</p>';
            
            try {
                const response = await fetch('api/debug_api.php?action=list_users', {
                    method: 'POST'
                });
                
                const data = await response.json();
                let html = '<h3>Users in Databases:</h3>';
                
                if (data.success) {
                    // agrohub_erp users
                    html += '<h4>Users in agrohub_erp:</h4>';
                    if (data.data.agrohub_users && data.data.agrohub_users.length > 0) {
                        html += '<table><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>';
                        data.data.agrohub_users.forEach(user => {
                            html += `<tr>
                                <td>${user.id}</td>
                                <td>${user.name || '-'}</td>
                                <td>${user.email || '-'}</td>
                                <td>${user.role || '-'}</td>
                            </tr>`;
                        });
                        html += '</table>';
                    } else {
                        html += '<p>No users found in agrohub_erp</p>';
                    }
                    
                    // phplogin users
                    html += '<h4>Users in phplogin:</h4>';
                    if (data.data.phplogin_users && data.data.phplogin_users.length > 0) {
                        html += '<table><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>';
                        data.data.phplogin_users.forEach(user => {
                            html += `<tr>
                                <td>${user.id}</td>
                                <td>${user.username || '-'}</td>
                                <td>${user.email || '-'}</td>
                                <td>${user.role || '-'}</td>
                            </tr>`;
                        });
                        html += '</table>';
                    } else {
                        html += '<p>No users found in phplogin</p>';
                    }
                } else {
                    html += '<p class="error">❌ Error: ' + data.error + '</p>';
                }
                
                results.innerHTML = html;
            } catch (error) {
                results.innerHTML = '<p class="error">❌ Error: ' + error.message + '</p>';
            }
        });
        
        // Test login
        document.getElementById('test-login').addEventListener('click', async () => {
            const identifier = document.getElementById('identifier').value;
            const password = document.getElementById('password').value;
            const results = document.getElementById('login-results');
            
            results.innerHTML = '<p>Testing login...</p>';
            
            if (!identifier || !password) {
                results.innerHTML = '<p class="error">❌ Please enter both identifier and password</p>';
                return;
            }
            
            try {
                const response = await fetch('api/debug_api.php?action=test_login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        identifier,
                        password
                    })
                });
                
                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    results.innerHTML = `
                        <p class="error">❌ Invalid JSON response:</p>
                        <pre>${text}</pre>
                        <p>Error: ${e.message}</p>
                    `;
                    return;
                }
                
                let html = '<h3>Login Test Results:</h3>';
                
                if (data.success) {
                    html += '<p class="success">✅ Login successful!</p>';
                    html += '<h4>User:</h4>';
                    html += '<pre>' + JSON.stringify(data.data.user, null, 2) + '</pre>';
                    html += '<h4>Token:</h4>';
                    html += '<code>' + data.data.token + '</code>';
                } else {
                    html += '<p class="error">❌ Login failed: ' + (data.error || data.message || 'Unknown error') + '</p>';
                    if (data.data) {
                        html += '<pre>' + JSON.stringify(data.data, null, 2) + '</pre>';
                    }
                }
                
                results.innerHTML = html;
            } catch (error) {
                results.innerHTML = '<p class="error">❌ Error: ' + error.message + '</p>';
            }
        });
    </script>
</body>
</html>
