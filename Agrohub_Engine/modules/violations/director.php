<?php
/**
 * Violations Module - Director Dashboard
 * For Directors/CEO - Executive overview (read-only)
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

// Check module access
if (!violationsHasModuleAccess($user['id'])) {
    http_response_code(403);
    die('Access Denied');
}

$permissions = violationsGetUserPermissions($user['id'], $user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Director Dashboard - Violations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/violations.css">
    <style>
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
        
        .director-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .executive-notice {
            background: #ecfeff;
            border-left: 4px solid #06b6d4;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .executive-notice i {
            font-size: 1.5rem;
            color: #06b6d4;
        }
        
        .executive-notice-text {
            flex: 1;
        }
        
        .executive-notice-title {
            font-weight: 700;
            color: #155e75;
            margin-bottom: 0.25rem;
        }
        
        .executive-notice-description {
            font-size: 0.9rem;
            color: #155e75;
        }
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
                <div class="director-badge">
                    <i class="fas fa-briefcase"></i>
                    <span>Director</span>
                </div>
                
                <a href="../../index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Apps</span>
                </a>
                
                <!-- User Menu with Dropdown -->
                <div class="user-menu">
                    <div class="user-menu-trigger" onclick="toggleUserMenu()">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                        <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                        <i class="fas fa-chevron-down" style="font-size: 0.75rem; color: #9ca3af;"></i>
                    </div>
                    
                    <!-- Dropdown Menu -->
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
    <main class="violations-main">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title-section">
                <p class="page-subtitle">Executive Dashboard</p>
                <h1 class="page-title">Violations <span class="highlight">Overview</span></h1>
                <p class="page-description">
                    Comprehensive executive view of all violations and compliance metrics across the organization.
                </p>
            </div>
            
            <div class="action-buttons">
                <button class="btn btn-secondary" onclick="exportExecutiveReport()">
                    <i class="fas fa-download"></i>
                    Export Executive Report
                </button>
            </div>
        </div>

        <!-- Executive Notice -->
        <div class="executive-notice">
            <i class="fas fa-eye"></i>
            <div class="executive-notice-text">
                <div class="executive-notice-title">Executive View - Read-Only Access</div>
                <div class="executive-notice-description">
                    You have complete visibility of all violations. This is a monitoring dashboard with export capabilities.
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Violations</div>
                <div class="stat-value" id="stat-total">0</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Critical Issues</div>
                <div class="stat-value" id="stat-critical" style="color: #ef4444;">0</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">In Progress</div>
                <div class="stat-value" id="stat-progress" style="color: #f59e0b;">0</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Resolved Rate</div>
                <div class="stat-value" id="stat-rate" style="color: #10b981;">0%</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <div class="filters-grid">
                <input type="text" 
                       id="search-input" 
                       class="filter-input" 
                       placeholder="Search violations..."
                       onkeyup="filterViolations()">
                
                <select id="filter-status" class="filter-select" onchange="filterViolations()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                </select>
                
                <select id="filter-severity" class="filter-select" onchange="filterViolations()">
                    <option value="">All Severity</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                </select>
                
                <select id="filter-branch" class="filter-select" onchange="filterViolations()">
                    <option value="">All Branches</option>
                </select>
                
                <select id="filter-type" class="filter-select" onchange="filterViolations()">
                    <option value="">All Types</option>
                </select>
            </div>
        </div>

        <!-- Violations Cards Grid -->
        <div id="violations-container">
            <div class="loading-state">
                <i class="fas fa-spinner loading-spinner"></i>
                <p style="margin-top: 1rem; color: #6b7280;">Loading violations...</p>
            </div>
        </div>

    </main>

    <script>
        const authToken = localStorage.getItem('auth_token');
        const permissions = <?php echo json_encode($permissions); ?>;
        
        let allViolations = [];
        let branches = [];
        
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
            
            loadTypes();
            loadBranches();
            loadViolations();
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
        
        async function loadTypes() {
            try {
                const res = await fetch('api/violations.php?action=types', {
                    headers: { 'Authorization': `Bearer ${authToken}` }
                });
                const data = await res.json();
                
                if (data.success && data.data?.types) {
                    const select = document.getElementById('filter-type');
                    data.data.types.forEach(type => {
                        const opt = document.createElement('option');
                        opt.value = type.id;
                        opt.textContent = type.name;
                        select.appendChild(opt);
                    });
                }
            } catch (e) {
                console.error('Types error:', e);
            }
        }
        
        async function loadBranches() {
            try {
                const res = await fetch('/api/admin.php?action=get_branches', {
                    headers: { 'Authorization': `Bearer ${authToken}` }
                });
                const data = await res.json();
                
                if (data.success && data.branches) {
                    branches = data.branches;
                    const select = document.getElementById('filter-branch');
                    branches.forEach(branch => {
                        const opt = document.createElement('option');
                        opt.value = branch.id;
                        opt.textContent = branch.name;
                        select.appendChild(opt);
                    });
                }
            } catch (e) {
                console.error('Branches error:', e);
            }
        }
        
        async function loadViolations() {
            try {
                const res = await fetch('api/violations.php?action=list&limit=200', {
                    headers: { 'Authorization': `Bearer ${authToken}` }
                });
                const data = await res.json();
                
                if (data.success && data.data?.violations) {
                    allViolations = data.data.violations;
                    updateStats();
                    renderViolations(allViolations);
                } else {
                    renderEmpty();
                }
            } catch (e) {
                console.error('Load error:', e);
                renderError();
            }
        }
        
        function updateStats() {
            const total = allViolations.length;
            const critical = allViolations.filter(v => v.severity === 'critical').length;
            const progress = allViolations.filter(v => v.status === 'in_progress').length;
            const resolved = allViolations.filter(v => v.status === 'resolved' || v.status === 'closed').length;
            const rate = total > 0 ? Math.round((resolved / total) * 100) : 0;
            
            document.getElementById('stat-total').textContent = total;
            document.getElementById('stat-critical').textContent = critical;
            document.getElementById('stat-progress').textContent = progress;
            document.getElementById('stat-rate').textContent = rate + '%';
        }
        
        function renderViolations(violations) {
            const container = document.getElementById('violations-container');
            
            if (violations.length === 0) {
                renderEmpty();
                return;
            }
            
            container.innerHTML = '<div class="cards-grid">' + 
                violations.map(v => `
                    <div class="violation-card" onclick="viewViolation(${v.id})">
                        <div class="violation-card-header">
                            <div class="violation-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <span class="violation-id">#${v.id}</span>
                        </div>
                        
                        <div class="violation-card-body">
                            <h3 class="violation-title">${escapeHtml(v.title || v.type_name || 'Untitled')}</h3>
                            <p class="violation-description">${escapeHtml(v.description || 'No description')}</p>
                        </div>
                        
                        <div class="violation-card-footer">
                            <div class="violation-meta">
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>${formatDate(v.violation_date)}</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-building"></i>
                                    <span>${escapeHtml(v.branch_name || 'N/A')}</span>
                                </div>
                            </div>
                            <div>
                                <span class="violation-badge ${getStatusBadge(v.status)}">${v.status || 'Pending'}</span>
                            </div>
                        </div>
                    </div>
                `).join('') + 
            '</div>';
        }
        
        function renderEmpty() {
            const container = document.getElementById('violations-container');
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-check-circle empty-icon" style="color: #10b981;"></i>
                    <h2 class="empty-title">Excellent Performance</h2>
                    <p class="empty-description">No violations found matching your criteria.</p>
                </div>
            `;
        }
        
        function renderError() {
            const container = document.getElementById('violations-container');
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-circle empty-icon" style="color: #ef4444;"></i>
                    <h2 class="empty-title">Failed to Load</h2>
                    <p class="empty-description">Unable to load violations. Please try again.</p>
                    <button onclick="loadViolations()" class="btn btn-primary"><i class="fas fa-sync"></i> Retry</button>
                </div>
            `;
        }
        
        function filterViolations() {
            const search = document.getElementById('search-input').value.toLowerCase();
            const status = document.getElementById('filter-status').value;
            const severity = document.getElementById('filter-severity').value;
            const branch = document.getElementById('filter-branch').value;
            const type = document.getElementById('filter-type').value;
            
            let filtered = allViolations.filter(v => {
                const matchSearch = !search || 
                    (v.title && v.title.toLowerCase().includes(search)) ||
                    (v.description && v.description.toLowerCase().includes(search)) ||
                    (v.branch_name && v.branch_name.toLowerCase().includes(search));
                
                const matchStatus = !status || v.status === status;
                const matchSeverity = !severity || v.severity === severity;
                const matchBranch = !branch || v.branch_id == branch;
                const matchType = !type || v.violation_type_id == type;
                
                return matchSearch && matchStatus && matchSeverity && matchBranch && matchType;
            });
            
            renderViolations(filtered);
        }
        
        async function exportExecutiveReport() {
            if (!confirm('Export executive summary report?')) {
                return;
            }
            
            try {
                let csv = 'ID,Title,Type,Status,Severity,Branch,Date,Reported By,Description\n';
                
                allViolations.forEach(v => {
                    csv += `${v.id},"${v.title}","${v.type_name}","${v.status}","${v.severity}","${v.branch_name}","${v.violation_date}","${v.reported_by_name}","${v.description}"\n`;
                });
                
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `executive_report_${new Date().toISOString().split('T')[0]}.csv`;
                a.click();
                window.URL.revokeObjectURL(url);
                
                alert('✅ Executive report exported!');
                
            } catch (error) {
                console.error('Export error:', error);
                alert('❌ Export failed');
            }
        }
        
        function viewViolation(id) {
            window.location.href = `report.php?id=${id}`;
        }
        
        function getStatusBadge(status) {
            const badges = {
                'pending': 'badge-pending',
                'in_progress': 'badge-in-progress',
                'resolved': 'badge-resolved',
                'closed': 'badge-closed'
            };
            return badges[status?.toLowerCase()] || 'badge-pending';
        }
        
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
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