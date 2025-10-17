<?php
/**
 * Violations Module - Internal Admin Panel
 * Manage permissions and MODULE-SPECIFIC ROLES for Violations module
 */

define('VIOLATIONS_MODULE', true);

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/database.php';
require_once __DIR__ . '/../../api/utils.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$utils = new Utils();
$user = $utils->authenticateRequest();

if (!$user) {
    header('Location: ../../index.html');
    exit();
}

// Check if user is admin
if ($user['role'] !== 'admin') {
    http_response_code(403);
    die('
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="assets/css/violations.css">
    </head>
    <body>
        <div style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f5f6f8;">
            <div style="background:white;padding:3rem;border-radius:16px;text-align:center;max-width:500px;border:1px solid #e5e7eb;">
                <div style="font-size:4rem;margin-bottom:1.5rem;">🔒</div>
                <h1 style="color:#1f2937;font-size:1.75rem;font-weight:800;margin-bottom:1rem;">Admin Only</h1>
                <p style="color:#6b7280;">Only administrators can access Violations Admin Panel.</p>
                <a href="index.php" style="display:inline-block;margin-top:1.5rem;padding:0.75rem 2rem;background:#7c3aed;color:white;text-decoration:none;border-radius:8px;font-weight:600;">← Back to Violations</a>
            </div>
        </div>
    </body>
    </html>
    ');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Violations Admin Panel - Agrohub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/violations.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .admin-header {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .admin-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .admin-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
        }
        
        .admin-title h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #1f2937;
        }
        
        .admin-description {
            color: #6b7280;
            font-size: 1rem;
            margin-left: 76px;
        }
        
        .action-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .info-box h3 {
            color: #1e40af;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .info-box ul {
            color: #1e40af;
            font-size: 0.9rem;
            line-height: 1.8;
            margin-left: 1.5rem;
        }
        
        .loading-spinner {
            text-align: center;
            padding: 4rem;
            color: #6b7280;
        }
        
        .loading-spinner i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #7c3aed;
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-menu-trigger {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: var(--bg-secondary);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid transparent;
        }
        
        .user-menu-trigger:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary);
        }
        
        .user-dropdown {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            min-width: 250px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 1000;
        }
        
        .user-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .dropdown-header-name {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        
        .dropdown-header-email {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
        
        .dropdown-menu {
            padding: 0.5rem;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .dropdown-item:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .dropdown-item i {
            width: 20px;
            text-align: center;
            font-size: 1rem;
        }
        
        .dropdown-divider {
            height: 1px;
            background: var(--border-color);
            margin: 0.5rem 0;
        }
        
        .dropdown-item.danger {
            color: #ef4444;
        }
        
        .dropdown-item.danger:hover {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .permission-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .permission-card.vm-user { border-left: 4px solid #f59e0b; }
        .permission-card.gm-user { border-left: 4px solid #3b82f6; }
        .permission-card.audit-user { border-left: 4px solid #8b5cf6; }
        .permission-card.hr-user { border-left: 4px solid #10b981; }
        .permission-card.production-user { border-left: 4px solid #ec4899; }
        .permission-card.operations-user { border-left: 4px solid #f97316; }
        .permission-card.director-user { border-left: 4px solid #06b6d4; }
        
        .permission-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        
        .permission-card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .permission-card-subtitle {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }
        
        .permission-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            transition: background 0.2s;
        }
        
        .permission-checkbox:hover {
            background: #f9fafb;
        }
        
        .permission-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .permission-checkbox span {
            font-size: 0.9rem;
            color: #1f2937;
            font-weight: 500;
        }
        
        .role-selector {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .role-select {
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .role-select:hover {
            border-color: #7c3aed;
        }
        
        .role-select:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }
        
        .role-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .role-badge.vm { background: #fef3c7; color: #92400e; }
        .role-badge.gm { background: #dbeafe; color: #1e40af; }
        .role-badge.audit { background: #ede9fe; color: #5b21b6; }
        .role-badge.hr { background: #d1fae5; color: #065f46; }
        .role-badge.production { background: #fce7f3; color: #9f1239; }
        .role-badge.operations { background: #fed7aa; color: #9a3412; }
        .role-badge.director { background: #cffafe; color: #155e75; }
        .role-badge.admin { background: #fee2e2; color: #991b1b; }
        .role-badge.default { background: #e5e7eb; color: #4b5563; }
    </style>
</head>
<body>
    
    <!-- Header -->
    <header class="violations-header">
        <div class="violations-header-container">
            <div class="header-left">
                <a href="../../index.php" class="header-logo">
                    <div class="header-logo-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <span class="header-logo-text">Agrohub</span>
                </a>
            </div>
            
            <div class="header-actions">
                <a href="index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Violations</span>
                </a>
                
                <div class="user-menu">
                    <div class="user-menu-trigger" onclick="toggleUserMenu()">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                        <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                        <i class="fas fa-chevron-down" style="font-size: 0.75rem; color: #9ca3af;"></i>
                    </div>
                    
                    <div class="user-dropdown" id="userDropdown">
                        <div class="dropdown-header">
                            <div class="dropdown-header-name"><?php echo htmlspecialchars($user['name']); ?></div>
                            <div class="dropdown-header-email"><?php echo htmlspecialchars($user['email'] ?? $user['username']); ?></div>
                        </div>
                        
                        <div class="dropdown-menu">
                            <a href="../../index.php" class="dropdown-item">
                                <i class="fas fa-home"></i>
                                <span>Dashboard</span>
                            </a>
                            
                            <a href="index.php" class="dropdown-item">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Violations List</span>
                            </a>
                            
                            <a href="../../admin.html" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                <span>System Admin</span>
                            </a>
                            
                            <a href="#" onclick="viewMyProfile(event)" class="dropdown-item">
                                <i class="fas fa-user"></i>
                                <span>My Profile</span>
                            </a>
                            
                            <div class="dropdown-divider"></div>
                            
                            <a href="#" onclick="logout(event)" class="dropdown-item danger">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="admin-container">
        
        <div class="admin-header">
            <div class="admin-title">
                <div class="admin-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div>
                    <h1>Violations Admin Panel</h1>
                </div>
            </div>
            <p class="admin-description">
                Manage MODULE-SPECIFIC user roles and permissions for Violations Management (independent from global roles)
            </p>
        </div>

        <div class="action-bar">
            <button onclick="bulkSetVMPermissions()" class="btn btn-primary">
                <i class="fas fa-users-cog"></i>
                Auto-Configure VM Users
            </button>
            
            <button onclick="bulkSetGMPermissions()" class="btn btn-secondary">
                <i class="fas fa-user-tie"></i>
                Auto-Configure GM Users
            </button>
            
            <button onclick="exportPermissions()" class="btn btn-secondary">
                <i class="fas fa-download"></i>
                Export Permissions
            </button>
            
            <button onclick="refreshPermissions()" class="btn btn-secondary">
                <i class="fas fa-sync"></i>
                Refresh
            </button>
        </div>

        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> Module-Specific Role-Based Access Control</h3>
            <ul>
                <li><strong>Important:</strong> Global role (user/admin) in users table remains unchanged</li>
                <li><strong>VM (Video Monitor):</strong> Create violations only → <code>create.php</code></li>
                <li><strong>GM (General Manager):</strong> Manage branch violations → <code>manager.php</code></li>
                <li><strong>Production Manager:</strong> View all branches → <code>production.php</code></li>
                <li><strong>Operations:</strong> Full control → <code>operations.php</code></li>
                <li><strong>Director:</strong> View all (read-only) → <code>director.php</code></li>
                <li><strong>Audit:</strong> View all (read-only) → <code>audit.php</code></li>
                <li><strong>HR:</strong> View completed only → <code>hr.php</code></li>
                <li><strong>Admin:</strong> Full dashboard access</li>
            </ul>
        </div>

        <div id="violations-permissions-container">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading user permissions...</p>
            </div>
        </div>

    </main>

    <script>
        const authToken = localStorage.getItem('auth_token');
        const API_BASE_URL = '../../api/violations_permissions.php';
        let allUsers = [];
        
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const trigger = document.querySelector('.user-menu-trigger');
            
            if (dropdown && !dropdown.contains(event.target) && !trigger.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            if (!authToken) {
                window.location.href = '../../index.html';
                return;
            }
            
            loadViolationsPermissions();
        });
        
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('active');
        }
        
        function viewMyProfile(e) {
            e.preventDefault();
            alert('Profile page under development');
        }
        
        function logout(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                localStorage.removeItem('auth_token');
                window.location.href = '../../index.html';
            }
        }
        
        async function loadViolationsPermissions() {
            try {
                const res = await fetch(`${API_BASE_URL}?action=get_users`, {
                    headers: { 'Authorization': `Bearer ${authToken}` }
                });
                
                const data = await res.json();
                
                if (!data.success) {
                    showError(data.error || 'Failed to load users');
                    return;
                }
                
                allUsers = data.data.users;
                renderPermissions(allUsers);
                
            } catch (error) {
                console.error('Load permissions error:', error);
                showError('Failed to load permissions: ' + error.message);
            }
        }
        
        function detectUserRoleType(user) {
            // Use role_type from database if set
            if (user.role_type) {
                return user.role_type;
            }
            
            // Fallback: detect from username
            const un = user.username.toLowerCase();
            
            if (un.startsWith('vm ') || ['vm gelovani', 'vm batumi', 'vm sport', 'vm maghlivi', 'vm maglivi', 'vm vake', 'vm avchala', 'vm chiladze', 'vm sanapiro', 'vm gldani', 'vm qutaisi', 'vm sporti'].includes(un)) {
                return 'vm';
            }
            
            if (un.startsWith('gm ') || ['gm gelovani', 'gm batumi', 'gm sport', 'gm maghlivi', 'gm vake', 'gm avchala', 'gm chiladze', 'gm sanapiro', 'gm gldani', 'gm qutaisi'].includes(un)) {
                return 'gm';
            }
            
            if (un.includes('prod') || un.includes('production')) {
                return 'production';
            }
            
            if (un.includes('operations') || un === 'operations') {
                return 'operations';
            }
            
            if (un.includes('director') || un.includes('ceo') || un.includes('გენერალური')) {
                return 'director';
            }
            
            if (un.includes('audit')) {
                return 'audit';
            }
            
            if (un.includes('hr') || un.startsWith('hr ')) {
                return 'hr';
            }
            
            // Check global role as final fallback
            if (user.global_role === 'admin') {
                return 'admin';
            }
            
            return 'default';
        }
        
        function renderPermissions(users) {
            const container = document.getElementById('violations-permissions-container');
            
            const vmUsers = users.filter(u => detectUserRoleType(u) === 'vm');
            const gmUsers = users.filter(u => detectUserRoleType(u) === 'gm');
            const prodUsers = users.filter(u => detectUserRoleType(u) === 'production');
            const opsUsers = users.filter(u => detectUserRoleType(u) === 'operations');
            const dirUsers = users.filter(u => detectUserRoleType(u) === 'director');
            const auditUsers = users.filter(u => detectUserRoleType(u) === 'audit');
            const hrUsers = users.filter(u => detectUserRoleType(u) === 'hr');
            const otherUsers = users.filter(u => detectUserRoleType(u) === 'default' && u.global_role !== 'admin');
            const adminUsers = users.filter(u => u.global_role === 'admin' || detectUserRoleType(u) === 'admin');
            
            container.innerHTML = `
                ${vmUsers.length > 0 ? `
                <div style="margin-bottom: 3rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-video" style="color: #f59e0b;"></i>
                        VM - Video Monitor (${vmUsers.length})
                        <span style="font-size: 0.8rem; font-weight: 400; color: #6b7280;">→ create.php</span>
                    </h2>
                    ${vmUsers.map(user => renderPermissionCard(user, 'vm')).join('')}
                </div>
                ` : ''}
                
                ${gmUsers.length > 0 ? `
                <div style="margin-bottom: 3rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-user-tie" style="color: #3b82f6;"></i>
                        GM - General Manager (${gmUsers.length})
                        <span style="font-size: 0.8rem; font-weight: 400; color: #6b7280;">→ manager.php</span>
                    </h2>
                    ${gmUsers.map(user => renderPermissionCard(user, 'gm')).join('')}
                </div>
                ` : ''}
                
                ${prodUsers.length > 0 ? `
                <div style="margin-bottom: 3rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-industry" style="color: #ec4899;"></i>
                        Production Manager (${prodUsers.length})
                        <span style="font-size: 0.8rem; font-weight: 400; color: #6b7280;">→ production.php</span>
                    </h2>
                    ${prodUsers.map(user => renderPermissionCard(user, 'production')).join('')}
                </div>
                ` : ''}
                
                ${opsUsers.length > 0 ? `
                <div style="margin-bottom: 3rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-cogs" style="color: #f97316;"></i>
                        Operations (${opsUsers.length})
                        <span style="font-size: 0.8rem; font-weight: 400; color: #6b7280;">→ operations.php</span>
                    </h2>
                    ${opsUsers.map(user => renderPermissionCard(user, 'operations')).join('')}
                </div>
                ` : ''}
                
                ${dirUsers.length > 0 ? `
                <div style="margin-bottom: 3rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-briefcase" style="color: #06b6d4;"></i>
                        Director (${dirUsers.length})
                        <span style="font-size: 0.8rem; font-weight: 400; color: #6b7280;">→ director.php (read-only)</span>
                    </h2>
                    ${dirUsers.map(user => renderPermissionCard(user, 'director')).join('')}
                </div>
                ` : ''}
                
                ${auditUsers.length > 0 ? `
                <div style="margin-bottom: 3rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-shield-alt" style="color: #8b5cf6;"></i>
                        Audit (${auditUsers.length})
                        <span style="font-size: 0.8rem; font-weight: 400; color: #6b7280;">→ audit.php (read-only)</span>
                    </h2>
                    ${auditUsers.map(user => renderPermissionCard(user, 'audit')).join('')}
                </div>
                ` : ''}
                
                ${hrUsers.length > 0 ? `
                <div style="margin-bottom: 3rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-users" style="color: #10b981;"></i>
                        HR (${hrUsers.length})
                        <span style="font-size: 0.8rem; font-weight: 400; color: #6b7280;">→ hr.php (completed only)</span>
                    </h2>
                    ${hrUsers.map(user => renderPermissionCard(user, 'hr')).join('')}
                </div>
                ` : ''}
                
                ${adminUsers.length > 0 ? `
                <div style="margin-bottom: 3rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-crown" style="color: #ef4444;"></i>
                        Admin (${adminUsers.length})
                        <span style="font-size: 0.8rem; font-weight: 400; color: #6b7280;">→ Full dashboard</span>
                    </h2>
                    ${adminUsers.map(user => renderPermissionCard(user, 'admin')).join('')}
                </div>
                ` : ''}
                
                ${otherUsers.length > 0 ? `
                <div>
                    <h2 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-users" style="color: #6b7280;"></i>
                        Other Users (${otherUsers.length})
                    </h2>
                    ${otherUsers.map(user => renderPermissionCard(user, 'default')).join('')}
                </div>
                ` : ''}
            `;
        }
        
        function renderPermissionCard(user, roleType) {
            const permissions = {
                can_create: user.can_create || 0,
                can_view_own: user.can_view_own || 0,
                can_view_all: user.can_view_all || 0,
                can_view_branch: user.can_view_branch || 0,
                can_edit_own: user.can_edit_own || 0,
                can_edit_all: user.can_edit_all || 0,
                can_delete: user.can_delete || 0,
                can_apply_sanctions: user.can_apply_sanctions || 0,
                can_view_photos: user.can_view_photos || 0,
                can_export: user.can_export || 0
            };
            
            return `
                <div class="permission-card ${roleType}-user">
                    <div class="permission-card-header">
                        <div>
                            <div class="permission-card-title">
                                ${escapeHtml(user.name)}
                                <span class="role-badge ${roleType}">${roleType.toUpperCase()}</span>
                            </div>
                            <div class="permission-card-subtitle">
                                <i class="fas fa-user"></i> ${escapeHtml(user.username)} | 
                                <i class="fas fa-shield-alt"></i> Global: ${escapeHtml(user.global_role)} | 
                                <i class="fas fa-tag"></i> Module: ${user.role_type || 'Not Set'}
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            ${user.has_access ? '<span style="background:#d1fae5;color:#065f46;padding:0.4rem 0.8rem;border-radius:20px;font-size:0.8rem;font-weight:700;"><i class="fas fa-check"></i> ACCESS</span>' : '<span style="background:#fee2e2;color:#991b1b;padding:0.4rem 0.8rem;border-radius:20px;font-size:0.8rem;font-weight:700;"><i class="fas fa-times"></i> NO ACCESS</span>'}
                            
                            <div class="role-selector">
                                <select class="role-select" onchange="applyRolePreset(${user.id}, this.value, this)">
                                    <option value="">Apply Role...</option>
                                    <option value="vm">VM</option>
                                    <option value="gm">GM</option>
                                    <option value="production">Production</option>
                                    <option value="operations">Operations</option>
                                    <option value="director">Director</option>
                                    <option value="audit">Audit</option>
                                    <option value="hr">HR</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="permission-grid">
                        ${renderCheckbox(user.id, 'can_create', permissions.can_create, 'plus-circle', 'Create')}
                        ${renderCheckbox(user.id, 'can_view_own', permissions.can_view_own, 'eye', 'View Own')}
                        ${renderCheckbox(user.id, 'can_view_all', permissions.can_view_all, 'eye', 'View All')}
                        ${renderCheckbox(user.id, 'can_view_branch', permissions.can_view_branch, 'building', 'View Branch')}
                        ${renderCheckbox(user.id, 'can_edit_own', permissions.can_edit_own, 'edit', 'Edit Own')}
                        ${renderCheckbox(user.id, 'can_edit_all', permissions.can_edit_all, 'edit', 'Edit All')}
                        ${renderCheckbox(user.id, 'can_delete', permissions.can_delete, 'trash', 'Delete')}
                        ${renderCheckbox(user.id, 'can_apply_sanctions', permissions.can_apply_sanctions, 'gavel', 'Sanctions')}
                        ${renderCheckbox(user.id, 'can_view_photos', permissions.can_view_photos, 'image', 'View Photos')}
                        ${renderCheckbox(user.id, 'can_export', permissions.can_export, 'download', 'Export')}
                    </div>
                </div>
            `;
        }
        
        function renderCheckbox(userId, perm, checked, icon, label) {
            return `
                <label class="permission-checkbox">
                    <input type="checkbox" 
                           ${checked ? 'checked' : ''}
                           onchange="updatePermission(${userId}, '${perm}', this.checked)">
                    <span>
                        <i class="fas fa-${icon}"></i> ${label}
                    </span>
                </label>
            `;
        }
        
        async function applyRolePreset(userId, role, selectElement) {
            if (!role) return;
            
            if (!confirm(`Apply "${role.toUpperCase()}" role preset?`)) {
                selectElement.value = '';
                return;
            }
            
            const presets = {
                'vm': { role_type: 'vm', can_create: 1, can_view_own: 1, can_view_all: 0, can_view_branch: 0, can_edit_own: 0, can_edit_all: 0, can_delete: 0, can_apply_sanctions: 0, can_reject: 0, can_view_sanctions: 0, can_view_photos: 1, can_export: 0, can_view_analytics: 0 },
                'gm': { role_type: 'gm', can_create: 1, can_view_own: 1, can_view_all: 0, can_view_branch: 1, can_edit_own: 1, can_edit_all: 0, can_delete: 0, can_apply_sanctions: 1, can_reject: 1, can_view_sanctions: 1, can_view_photos: 1, can_export: 1, can_view_analytics: 0 },
                'production': { role_type: 'production', can_create: 0, can_view_own: 0, can_view_all: 1, can_view_branch: 1, can_edit_own: 0, can_edit_all: 0, can_delete: 0, can_apply_sanctions: 0, can_reject: 0, can_view_sanctions: 1, can_view_photos: 1, can_export: 1, can_view_analytics: 1 },
                'operations': { role_type: 'operations', can_create: 1, can_view_own: 1, can_view_all: 1, can_view_branch: 1, can_edit_own: 1, can_edit_all: 1, can_delete: 1, can_apply_sanctions: 1, can_reject: 1, can_view_sanctions: 1, can_view_photos: 1, can_export: 1, can_view_analytics: 1 },
                'director': { role_type: 'director', can_create: 0, can_view_own: 0, can_view_all: 1, can_view_branch: 1, can_edit_own: 0, can_edit_all: 0, can_delete: 0, can_apply_sanctions: 0, can_reject: 0, can_view_sanctions: 1, can_view_photos: 1, can_export: 1, can_view_analytics: 1 },
                'audit': { role_type: 'audit', can_create: 0, can_view_own: 0, can_view_all: 1, can_view_branch: 1, can_edit_own: 0, can_edit_all: 0, can_delete: 0, can_apply_sanctions: 0, can_reject: 0, can_view_sanctions: 1, can_view_photos: 1, can_export: 1, can_view_analytics: 1 },
                'hr': { role_type: 'hr', can_create: 0, can_view_own: 0, can_view_all: 1, can_view_branch: 1, can_edit_own: 0, can_edit_all: 0, can_delete: 0, can_apply_sanctions: 0, can_reject: 0, can_view_sanctions: 1, can_view_photos: 1, can_export: 1, can_view_analytics: 0 },
                'admin': { role_type: 'admin', can_create: 1, can_view_own: 1, can_view_all: 1, can_view_branch: 1, can_edit_own: 1, can_edit_all: 1, can_delete: 1, can_apply_sanctions: 1, can_reject: 1, can_view_sanctions: 1, can_view_photos: 1, can_export: 1, can_view_analytics: 1 }
            };
            
            const permissions = presets[role];
            if (!permissions) {
                selectElement.value = '';
                return;
            }
            
            permissions.user_id = userId;
            
            try {
                const res = await fetch(`${API_BASE_URL}?action=update_permissions`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${authToken}` },
                    body: JSON.stringify(permissions)
                });
                
                const data = await res.json();
                
                if (data.success) {
                    showToast(`✅ ${role.toUpperCase()} applied`, 'success');
                    loadViolationsPermissions();
                } else {
                    showToast('❌ Failed', 'error');
                }
                
            } catch (error) {
                console.error('Error:', error);
                showToast('❌ Failed', 'error');
            }
            
            selectElement.value = '';
        }
        
        async function updatePermission(userId, permission, value) {
            try {
                const res = await fetch(`${API_BASE_URL}?action=update_permissions`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${authToken}` },
                    body: JSON.stringify({ user_id: userId, [permission]: value ? 1 : 0 })
                });
                
                const data = await res.json();
                
                if (data.success) {
                    showToast('✅ Updated', 'success');
                } else {
                    showToast('❌ Failed', 'error');
                    event.target.checked = !value;
                }
                
            } catch (error) {
                console.error('Error:', error);
                showToast('❌ Failed', 'error');
                event.target.checked = !value;
            }
        }
        
        async function bulkSetVMPermissions() {
            if (!confirm('⚠️ Configure all VM users?')) return;
            
            try {
                const res = await fetch(`${API_BASE_URL}?action=bulk_set_vm_permissions`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${authToken}` }
                });
                
                const data = await res.json();
                
                if (data.success) {
                    showToast(`✅ ${data.data.count} VM users configured`, 'success');
                    loadViolationsPermissions();
                } else {
                    showToast('❌ Failed', 'error');
                }
                
            } catch (error) {
                console.error('Error:', error);
                showToast('❌ Failed', 'error');
            }
        }
        
        async function bulkSetGMPermissions() {
            if (!confirm('⚠️ Configure all GM users?')) return;
            
            const gmUsers = allUsers.filter(u => {
                const un = u.username.toLowerCase();
                return un.startsWith('gm ') || ['gm gelovani', 'gm batumi', 'gm sport', 'gm maghlivi', 'gm vake', 'gm avchala', 'gm chiladze', 'gm sanapiro', 'gm gldani', 'gm qutaisi'].includes(un);
            });
            
            let successCount = 0;
            for (const user of gmUsers) {
                try {
                    const permissions = {
                        user_id: user.id,
                        role_type: 'gm',
                        can_create: 1, can_view_own: 1, can_view_all: 0, can_view_branch: 1,
                        can_edit_own: 1, can_edit_all: 0, can_delete: 0, can_apply_sanctions: 1,
                        can_reject: 1, can_view_sanctions: 1, can_view_photos: 1, can_export: 1,
                        can_view_analytics: 0
                    };
                    
                    const res = await fetch(`${API_BASE_URL}?action=update_permissions`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${authToken}` },
                        body: JSON.stringify(permissions)
                    });
                    
                    const data = await res.json();
                    if (data.success) successCount++;
                } catch (e) {
                    console.error('Error:', e);
                }
            }
            
            showToast(`✅ ${successCount} GM users configured`, 'success');
            setTimeout(() => loadViolationsPermissions(), 1000);
        }
        
        async function exportPermissions() {
            if (!confirm('Export to CSV?')) return;
            
            try {
                let csv = 'ID,Name,Username,Global Role,Module Role,Access,Create,View Own,View All,Delete\n';
                
                allUsers.forEach(u => {
                    const moduleRole = detectUserRoleType(u);
                    csv += `${u.id},"${u.name}","${u.username}","${u.global_role}","${moduleRole}",${u.has_access ? 'Yes' : 'No'},${u.can_create ? 'Yes' : 'No'},${u.can_view_own ? 'Yes' : 'No'},${u.can_view_all ? 'Yes' : 'No'},${u.can_delete ? 'Yes' : 'No'}\n`;
                });
                
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `violations_permissions_${new Date().toISOString().split('T')[0]}.csv`;
                a.click();
                window.URL.revokeObjectURL(url);
                
                showToast('✅ Exported', 'success');
                
            } catch (error) {
                console.error('Error:', error);
                showToast('❌ Export failed', 'error');
            }
        }
        
        function refreshPermissions() {
            loadViolationsPermissions();
            showToast('🔄 Refreshing...', 'info');
        }
        
        function showError(message) {
            document.getElementById('violations-permissions-container').innerHTML = `
                <div style="text-align:center;padding:3rem;">
                    <i class="fas fa-exclamation-circle" style="font-size:4rem;color:#ef4444;margin-bottom:1rem;"></i>
                    <p style="color:#6b7280;font-size:1rem;">${escapeHtml(message)}</p>
                    <button onclick="loadViolationsPermissions()" class="btn btn-primary" style="margin-top:1.5rem;">
                        <i class="fas fa-sync"></i> Retry
                    </button>
                </div>
            `;
        }
        
        function showToast(message, type = 'info') {
            const colors = { success: '#10b981', error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
            
            const toast = document.createElement('div');
            toast.style.cssText = `position:fixed;bottom:2rem;right:2rem;background:${colors[type]};color:white;padding:1rem 1.5rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:9999;font-weight:600;`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => toast.remove(), 3000);
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>

</body>
</html>