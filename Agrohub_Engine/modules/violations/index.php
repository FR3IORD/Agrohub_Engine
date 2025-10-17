<?php
/**
 * Violations Module - Admin Dashboard
 * Fully responsive for mobile, tablet, and desktop
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

if (!violationsHasModuleAccess($user['id'])) {
    http_response_code(403);
    die('
    <!DOCTYPE html>
    <html lang="ka">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>წვდომა აკრძალულია</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: #f5f5f9; display: flex; align-items: center; justify-content: center; 
                min-height: 100vh; padding: 1rem;
            }
            .box { 
                background: white; padding: 2rem; border-radius: 12px; 
                box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%;
                border: 1px solid #e8e8eb;
            }
            .icon { font-size: 3rem; margin-bottom: 1rem; }
            h1 { color: #2c3e50; font-size: 1.25rem; font-weight: 600; margin-bottom: 0.75rem; }
            p { color: #95a5a6; font-size: 0.9rem; line-height: 1.6; margin: 0.75rem 0; }
            .btn { 
                display: inline-block; margin-top: 1rem; padding: 0.6rem 1.25rem; 
                background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%); color: white; text-decoration: none; 
                border-radius: 8px; font-weight: 500; font-size: 0.9rem;
            }
        </style>
    </head>
    <body>
        <div class="box">
            <div class="icon">🚫</div>
            <h1>წვდომა აკრძალულია</h1>
            <p>თქვენ არ გაქვთ წვდომა დარღვევების მოდულზე.</p>
            <a href="../../index.html" class="btn">← უკან</a>
        </div>
    </body>
    </html>
    ');
}

$db = violationsGetDB();
$permissions = violationsGetUserPermissions($user['id'], $user['role']);

// Get violations-specific role
$stmt = $db->prepare("SELECT role_type FROM violation_user_permissions WHERE user_id = ?");
$stmt->execute([$user['id']]);
$moduleRole = $stmt->fetch(PDO::FETCH_ASSOC);

$violationsRole = $moduleRole['role_type'] ?? null;
$globalRole = strtoupper($user['role']);

// ============================================
// ROLE-BASED ROUTING
// ============================================

if ($violationsRole === 'vm' || $globalRole === 'VM') {
    header('Location: create.php');
    exit();
}

if ($violationsRole === 'gm' || $globalRole === 'GM' || $globalRole === 'MANAGER') {
    header('Location: manager.php');
    exit();
}

if ($violationsRole === 'hr' || $globalRole === 'HR') {
    header('Location: hr.php');
    exit();
}

if ($violationsRole === 'director' || $globalRole === 'DIRECTOR') {
    header('Location: director.php');
    exit();
}

if ($violationsRole === 'operations' || $globalRole === 'OPERATIONS') {
    header('Location: operations.php');
    exit();
}

if ($violationsRole === 'audit' || $globalRole === 'AUDIT') {
    header('Location: audit.php');
    exit();
}

// Admin stays here
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard - Violations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f9;
            color: #2c3e50;
            font-size: 14px;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .nav {
            background: white;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e8e8eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .nav-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: #2c3e50;
        }
        
        .brand-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }
        
        .brand-text {
            font-size: 1rem;
            font-weight: 600;
        }
        
        .nav-link {
            display: none;
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-trigger {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.65rem;
            background: #f5f5f9;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        
        .user-avatar {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .user-name {
            display: none;
        }
        
        .user-dropdown {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: white;
            border: 1px solid #e8e8eb;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 180px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s;
            z-index: 1000;
        }
        
        .user-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-header {
            padding: 0.75rem;
            border-bottom: 1px solid #e8e8eb;
        }
        
        .dropdown-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        
        .dropdown-email {
            font-size: 0.75rem;
            color: #95a5a6;
            margin-top: 0.25rem;
        }
        
        .dropdown-menu {
            padding: 0.5rem;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            color: #666;
            text-decoration: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .dropdown-item:hover {
            background: #f5f5f9;
        }
        
        .dropdown-item.danger {
            color: #e74c3c;
        }
        
        .dropdown-item.danger:hover {
            background: #fee;
        }
        
        .container {
            padding: 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            margin-bottom: 1rem;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .page-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .page-desc {
            font-size: 0.85rem;
            color: #95a5a6;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.5rem 0.85rem;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #f5f5f9;
            color: #666;
            border: 1px solid #e8e8eb;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .stat {
            background: white;
            border: 1px solid #e8e8eb;
            border-radius: 8px;
            padding: 0.85rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: #95a5a6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.35rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .filters {
            background: white;
            border: 1px solid #e8e8eb;
            border-radius: 8px;
            padding: 0.85rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }
        
        .form-input,
        .form-select {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1px solid #e8e8eb;
            border-radius: 6px;
            font-size: 0.9rem;
            font-family: inherit;
        }
        
        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
        }
        
        .cards {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.85rem;
        }
        
        .card {
            background: white;
            border: 1px solid #e8e8eb;
            border-radius: 8px;
            padding: 0.95rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            cursor: pointer;
        }
        
        .card:active {
            transform: scale(0.98);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.65rem;
        }
        
        .card-id {
            font-weight: 700;
            color: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            font-size: 0.95rem;
        }
        
        .badge {
            padding: 0.3rem 0.65rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-pending {
            background: #fff4e6;
            color: #e67e22;
        }
        
        .badge-progress {
            background: #e8f4fd;
            color: #0077c2;
        }
        
        .badge-done {
            background: #e8f8f5;
            color: #27ae60;
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.4rem;
        }
        
        .card-desc {
            font-size: 0.85rem;
            color: #666;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.65rem;
            border-top: 1px solid #f5f5f9;
            margin-top: 0.65rem;
        }
        
        .meta {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            font-size: 0.75rem;
            color: #95a5a6;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        
        .empty {
            text-align: center;
            padding: 2.5rem 1rem;
            color: #95a5a6;
        }
        
        .empty i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
        }
        
        .empty h3 {
            font-size: 1.1rem;
            margin-bottom: 0.35rem;
        }
        
        .empty p {
            font-size: 0.85rem;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .spin {
            animation: spin 1s linear infinite;
        }
        
        /* Tablet */
        @media (min-width: 640px) {
            .container {
                padding: 1.25rem;
            }
            
            .stats {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .filter-row {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .user-name {
                display: inline;
            }
        }
        
        /* Desktop */
        @media (min-width: 1024px) {
            .nav {
                padding: 0.75rem 2rem;
            }
            
            .nav-left {
                gap: 1.5rem;
            }
            
            .nav-link {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                color: #666;
                text-decoration: none;
                font-size: 0.875rem;
                padding: 0.4rem 0.75rem;
                border-radius: 6px;
            }
            
            .nav-link:hover {
                background: #f5f5f9;
                color: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            }
            
            .brand-text {
                font-size: 1.1rem;
            }
            
            .container {
                padding: 1.5rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .filter-row {
                grid-template-columns: repeat(4, 1fr);
                gap: 1rem;
            }
            
            .cards {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            }
        }
    </style>
</head>
<body>
    
    <nav class="nav">
        <div class="nav-left">
            <a href="../../index.html" class="brand">
                <div class="brand-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <span class="brand-text">Agrohub</span>
            </a>
            <a href="../../index.html" class="nav-link">
                <i class="fas fa-arrow-left"></i> უკან
            </a>
        </div>
        
        <div class="user-menu">
            <div class="user-trigger" onclick="toggleMenu()">
                <div class="user-avatar">
                    <?php echo mb_strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                </div>
                <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: #95a5a6;"></i>
            </div>
            
            <div class="user-dropdown" id="dropdown">
                <div class="dropdown-header">
                    <div class="dropdown-name"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div class="dropdown-email"><?php echo htmlspecialchars($user['email'] ?? $user['username']); ?></div>
                </div>
                
                <div class="dropdown-menu">
                    <a href="../../index.php" class="dropdown-item">
                        <i class="fas fa-home"></i>
                        <span>მთავარი</span>
                    </a>
                    
                    <a href="#" onclick="logout(event)" class="dropdown-item danger">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>გასვლა</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        
        <div class="header">
            <div class="header-top">
                <div>
                    <h1 class="page-title">დარღვევების მართვა</h1>
                    <p class="page-desc">სრული წვდომა ყველა დარღვევაზე</p>
                </div>
                
                <div class="actions">
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        <span style="display:none;">ახალი</span>
                    </a>
                    
                    <button class="btn btn-secondary" onclick="exportReport()">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="stats">
            <div class="stat">
                <div class="stat-label">სულ</div>
                <div class="stat-value" id="stat-total">0</div>
            </div>
            
            <div class="stat">
                <div class="stat-label">აქტიური</div>
                <div class="stat-value" style="color: #e74c3c;" id="stat-active">0</div>
            </div>
            
            <div class="stat">
                <div class="stat-label">მიმდინარე</div>
                <div class="stat-value" style="color: #e67e22;" id="stat-progress">0</div>
            </div>
            
            <div class="stat">
                <div class="stat-label">დასრულებული</div>
                <div class="stat-value" style="color: #27ae60;" id="stat-done">0</div>
            </div>
        </div>

        <div class="filters">
            <div class="filter-row">
                <input type="text" 
                       id="search" 
                       class="form-input" 
                       placeholder="ძიება..."
                       onkeyup="filter()">
                
                <select id="filter-progress" class="form-select" onchange="filter()">
                    <option value="">ყველა სტატუსი</option>
                    <option value="pending">განსახილველი</option>
                    <option value="in_progress">მიმდინარე</option>
                    <option value="completed">დასრულებული</option>
                </select>
                
                <select id="filter-branch" class="form-select" onchange="filter()">
                    <option value="">ყველა ფილიალი</option>
                </select>
                
                <input type="month" 
                       id="filter-month" 
                       class="form-input"
                       onchange="filter()">
            </div>
        </div>

        <div id="container">
            <div class="empty">
                <i class="fas fa-spinner spin"></i>
                <p>იტვირთება...</p>
            </div>
        </div>

    </div>

    <script>
        const token = localStorage.getItem('auth_token');
        let data = [];
        let branches = [];
        
        if (!token) window.location.href = '../../index.html';
        
        document.addEventListener('DOMContentLoaded', load);
        
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('dropdown');
            const trigger = document.querySelector('.user-trigger');
            
            if (dropdown && !dropdown.contains(e.target) && !trigger.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
        
        function toggleMenu() {
            document.getElementById('dropdown').classList.toggle('active');
        }
        
        function logout(e) {
            e.preventDefault();
            if (confirm('გსურთ გასვლა?')) {
                localStorage.removeItem('auth_token');
                window.location.href = '../../index.html';
            }
        }
        
        async function load() {
            try {
                await loadBranches();
                
                const r = await fetch('api/violations.php?action=list&limit=500', {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                
                const d = await r.json();
                
                if (d.success && d.data?.violations) {
                    data = d.data.violations;
                    updateStats();
                    render(data);
                } else {
                    showEmpty();
                }
            } catch (e) {
                console.error(e);
                showEmpty();
            }
        }
        
        async function loadBranches() {
            try {
                const r = await fetch('/api/admin.php?action=get_branches', {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const d = await r.json();
                
                if (d.success && d.branches) {
                    branches = d.branches;
                    const sel = document.getElementById('filter-branch');
                    branches.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.id;
                        opt.textContent = b.name;
                        sel.appendChild(opt);
                    });
                }
            } catch (e) {}
        }
        
        function updateStats() {
            const total = data.length;
            const active = data.filter(v => !v.progress || v.progress === 'pending').length;
            const progress = data.filter(v => v.progress === 'in_progress').length;
            const done = data.filter(v => v.progress === 'completed').length;
            
            document.getElementById('stat-total').textContent = total;
            document.getElementById('stat-active').textContent = active;
            document.getElementById('stat-progress').textContent = progress;
            document.getElementById('stat-done').textContent = done;
        }
        
        function render(items) {
            const container = document.getElementById('container');
            
            if (items.length === 0) {
                showEmpty();
                return;
            }
            
            container.innerHTML = '<div class="cards">' + 
                items.map(v => `
                    <div class="card" onclick="viewViolation(${v.id})">
                        <div class="card-header">
                            <span class="card-id">#${v.id}</span>
                            <span class="badge ${getBadge(v.progress)}">${getStatus(v.progress)}</span>
                        </div>
                        
                        <h3 class="card-title">${esc(v.category || 'უსათაურო')}</h3>
                        <p class="card-desc">${esc(v.category_comment || 'აღწერა არ არის')}</p>
                        
                        <div class="card-footer">
                            <div class="meta">
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>${formatDate(v.processed_date)}</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-building"></i>
                                    <span>${esc(v.branch_name || 'N/A')}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('') + 
            '</div>';
        }
        
        function showEmpty() {
            document.getElementById('container').innerHTML = `
                <div class="empty">
                    <i class="fas fa-inbox"></i>
                    <h3>დარღვევები არ არის</h3>
                    <p>რეპორტები გამოჩნდება აქ</p>
                </div>
            `;
        }
        
        function filter() {
            const search = document.getElementById('search').value.toLowerCase();
            const progress = document.getElementById('filter-progress').value;
            const branch = document.getElementById('filter-branch').value;
            const month = document.getElementById('filter-month').value;
            
            let filtered = data.filter(v => {
                const matchSearch = !search || 
                    (v.category && v.category.toLowerCase().includes(search)) ||
                    (v.category_comment && v.category_comment.toLowerCase().includes(search)) ||
                    (v.fullname && v.fullname.toLowerCase().includes(search));
                
                const matchProgress = !progress || v.progress === progress;
                const matchBranch = !branch || v.branch_id == branch;
                
                let matchMonth = true;
                if (month) {
                    const d = new Date(v.processed_date || v.created_at);
                    const [y, m] = month.split('-');
                    matchMonth = d.getFullYear() == y && (d.getMonth() + 1) == m;
                }
                
                return matchSearch && matchProgress && matchBranch && matchMonth;
            });
            
            render(filtered);
        }
        
        function exportReport() {
            if (!confirm('Export CSV?')) return;
            
            try {
                let csv = 'ID,კატეგორია,ფილიალი,სტატუსი,თარიღი\n';
                
                data.forEach(v => {
                    csv += `${v.id},"${v.category || ''}","${v.branch_name || ''}","${getStatus(v.progress)}","${v.processed_date || ''}"\n`;
                });
                
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `violations_${new Date().toISOString().split('T')[0]}.csv`;
                a.click();
                URL.revokeObjectURL(url);
                
                alert('✅ ჩამოიტვირთა!');
            } catch (e) {
                alert('❌ Export ვერ მოხერხდა');
            }
        }
        
        function viewViolation(id) {
            window.location.href = `report.php?id=${id}`;
        }
        
        function getBadge(progress) {
            if (progress === 'completed') return 'badge-done';
            if (progress === 'in_progress') return 'badge-progress';
            return 'badge-pending';
        }
        
        function getStatus(progress) {
            if (progress === 'completed') return 'დასრულებული';
            if (progress === 'in_progress') return 'მიმდინარე';
            return 'განსახილველი';
        }
        
        function formatDate(d) {
            if (!d) return 'N/A';
            const date = new Date(d);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        }
        
        function esc(t) {
            if (!t) return '';
            const d = document.createElement('div');
            d.textContent = t;
            return d.innerHTML;
        }
    </script>

</body>
</html>