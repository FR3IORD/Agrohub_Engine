<?php
/**
 * Violations Module - GM Manager Dashboard
 * Clean minimal design matching main dashboard
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
    die('Access Denied');
}

$permissions = violationsGetUserPermissions($user['id'], $user['role']);

if (!$permissions['can_view_branch'] && !$permissions['can_apply_sanctions']) {
    http_response_code(403);
    die('Access Denied - Managers Only');
}

$db = violationsGetDB();
$userBranches = [];
$branchName = 'Unknown Branch';
$branchIds = [];

try {
    $stmt = $db->prepare("
        SELECT b.id, b.name 
        FROM user_branches ub
        INNER JOIN branches b ON ub.branch_id = b.id
        WHERE ub.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $userBranches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($userBranches)) {
        $username = mb_strtolower($user['username'], 'UTF-8');
        
        $branchKeywords = [
            'gelovani' => ['gelovani', 'გელოვანი'],
            'avchala' => ['avchala', 'ავჭალა'],
            'maghlivi' => ['maghlivi', 'maglivi', 'მაღლივი'],
            'sanapiro' => ['sanapiro', 'სანაპირო'],
            'sport' => ['sport', 'sporti', 'სპორტ'],
            'chiladze' => ['chiladze', 'ჭილაძე'],
            'vake' => ['vake', 'ვაკე'],
            'gldani' => ['gldani', 'გლდანი'],
            'qutaisi' => ['qutaisi', 'kutaisi', 'ქუთაისი'],
            'batumi' => ['batumi', 'ბათუმი']
        ];
        
        $stmt = $db->query("SELECT id, name FROM branches");
        $allBranches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($allBranches as $branch) {
            $branchNameLower = mb_strtolower($branch['name'], 'UTF-8');
            
            foreach ($branchKeywords as $key => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($username, $keyword) !== false && strpos($branchNameLower, $keyword) !== false) {
                        $userBranches[] = $branch;
                        break 3;
                    }
                }
            }
        }
    }
    
    if (!empty($userBranches)) {
        $branchNames = array_column($userBranches, 'name');
        $branchName = implode(', ', $branchNames);
        $branchIds = array_column($userBranches, 'id');
    }
    
} catch (Exception $e) {
    error_log("Branch detection error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - Violations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f9;
            color: #2c3e50;
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* Header */
        .top-nav {
            background: white;
            padding: 0.75rem 2rem;
            border-bottom: 1px solid #e8e8eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .nav-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: #2c3e50;
        }
        
        .logo-icon {
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
        
        .logo-text {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .nav-link {
            color: #666;
            text-decoration: none;
            font-size: 0.875rem;
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .nav-link:hover {
            background: #f5f5f9;
            color: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
        }
        
        .nav-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .branch-tag {
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
            background: #e8e8eb;
            border-radius: 6px;
            color: #666;
        }
        
        .user-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            background: #f5f5f9;
            border-radius: 6px;
            font-size: 0.85rem;
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
        
        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem;
        }
        
        .page-header {
            margin-bottom: 1.5rem;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .page-desc {
            font-size: 0.875rem;
            color: #95a5a6;
        }
        
        /* Info Box */
        .info-alert {
            background: #e8f4fd;
            border: 1px solid #b3d9f2;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            color: #0077c2;
        }
        
        /* Filter Tabs */
        .filter-nav {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            background: white;
            padding: 0.5rem;
            border-radius: 8px;
            border: 1px solid #e8e8eb;
        }
        
        .filter-btn {
            flex: 1;
            padding: 0.5rem 1rem;
            background: transparent;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            color: #666;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            color: white;
        }
        
        .filter-btn:hover:not(.active) {
            background: #f5f5f9;
        }
        
        /* Stats Cards */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #e8e8eb;
            border-radius: 8px;
            padding: 1rem;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #95a5a6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Table Container */
        .table-box {
            background: white;
            border: 1px solid #e8e8eb;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table-scroll {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 2600px;
            font-size: 0.85rem;
        }
        
        .data-table thead {
            background: #fafafa;
            border-bottom: 1px solid #e8e8eb;
        }
        
        .data-table th {
            padding: 0.75rem 0.5rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        
        .data-table td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid #f5f5f9;
        }
        
        .data-table tbody tr:hover {
            background: #fafafa;
        }
        
        .data-table tbody tr.completed {
            opacity: 0.5;
        }
        
        .readonly {
            background: #fafafa;
            color: #95a5a6;
            font-size: 0.8rem;
        }
        
        .date-info {
            font-size: 0.8rem;
            line-height: 1.5;
        }
        
        .date-info div {
            margin-bottom: 0.25rem;
        }
        
        /* Form Controls */
        .form-input,
        .form-select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #e8e8eb;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
            transition: all 0.2s;
        }
        
        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
        }
        
        .form-input:disabled,
        .form-select:disabled {
            background: #fafafa;
            cursor: not-allowed;
        }
        
        .form-select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            padding-right: 2rem;
        }
        
        .form-textarea {
            width: 100%;
            min-height: 60px;
            padding: 0.5rem;
            border: 1px solid #e8e8eb;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
            resize: vertical;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
        }
        
        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .btn-complete {
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            color: white;
        }
        
        .btn-complete:hover:not(:disabled) {
            background: #5a3d8a;
        }
        
        .btn-complete:disabled {
            background: #e8e8eb;
            color: #95a5a6;
            cursor: not-allowed;
        }
        
        .btn-view {
            padding: 0.4rem 0.75rem;
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .btn-view:hover:not(:disabled) {
            background: #5a3d8a;
        }
        
        .btn-view:disabled {
            background: #e8e8eb;
            color: #95a5a6;
            cursor: not-allowed;
        }
        
        /* Status Badge */
        .badge {
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
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
        
        /* Empty State */
        .empty {
            text-align: center;
            padding: 3rem;
            color: #95a5a6;
        }
        
        .empty i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-box {
            background: white;
            border-radius: 12px;
            width: 95%;
            max-width: 1600px;
            max-height: 90vh;
            overflow: auto;
        }
        
        .modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e8e8eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }
        
        .modal-title {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .modal-close {
            width: 32px;
            height: 32px;
            background: #f5f5f9;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.2rem;
            color: #666;
        }
        
        .modal-close:hover {
            background: #e8e8eb;
        }
        
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem;
        }
        
        .photo-item {
            background: white;
            border: 1px solid #e8e8eb;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .photo-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .photo-wrap {
            position: relative;
            overflow: hidden;
            background: #fafafa;
        }
        
        .photo-item img {
            width: 100%;
            height: 350px;
            object-fit: contain;
            cursor: zoom-in;
            transition: transform 0.3s;
        }
        
        .photo-item img.zoomed {
            cursor: zoom-out;
            transform: scale(1.4);
        }
        
        .photo-actions {
            padding: 0.75rem;
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
        }
        
        .photo-btn {
            padding: 0.4rem 0.75rem;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .btn-zoom {
            background: #f5f5f9;
            color: #666;
        }
        
        .btn-zoom:hover {
            background: #e8e8eb;
        }
        
        .btn-download {
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            color: white;
        }
        
        .btn-download:hover {
            background: #5a3d8a;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .spin {
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body>
    
    <nav class="top-nav">
        <div class="nav-left">
            <a href="../../index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <span class="logo-text">Agrohub</span>
            </a>
            <a href="../../index.html" class="nav-link">
                <i class="fas fa-arrow-left"></i> უკან
            </a>
        </div>
        
        <div class="nav-right">
            <span class="branch-tag"><?php echo htmlspecialchars($branchName); ?></span>
            <div class="user-badge">
                <div class="user-avatar">
                    <?php echo mb_strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                </div>
                <span><?php echo htmlspecialchars($user['name']); ?></span>
            </div>
        </div>
    </nav>

    <div class="container">
        
        <div class="page-header">
            <h1 class="page-title">დარღვევების დამუშავება</h1>
            <p class="page-desc">მენეჯერის პანელი - <?php echo htmlspecialchars($branchName); ?></p>
        </div>

        <div class="info-alert">
            <i class="fas fa-info-circle"></i>
            <strong>შენიშვნა:</strong> რუხი ველები VM-ის მიერ შევსებული (read-only). 
            შეავსეთ 3 სავალდებულო ველი: <strong>პასუხისმგებლობა, სახელი, ჯარიმა</strong>.
        </div>

        <div class="filter-nav">
            <button class="filter-btn active" onclick="filterData('pending')" id="tab-pending">
                <i class="fas fa-clock"></i> განსახილველი
            </button>
            <button class="filter-btn" onclick="filterData('completed')" id="tab-completed">
                <i class="fas fa-check"></i> დასრულებული
            </button>
            <button class="filter-btn" onclick="filterData('all')" id="tab-all">
                <i class="fas fa-list"></i> ყველა
            </button>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-label">სულ</div>
                <div class="stat-value" id="stat-total">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">განსახილველი</div>
                <div class="stat-value" style="color: #e67e22;" id="stat-pending">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">დასრულებული</div>
                <div class="stat-value" style="color: #27ae60;" id="stat-completed">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">ჯარიმები (₾)</div>
                <div class="stat-value" style="color: #e67e22;" id="stat-fines">0.00</div>
            </div>
        </div>

        <div id="loading" class="empty">
            <i class="fas fa-spinner spin"></i>
            <p>იტვირთება...</p>
        </div>
        
        <div class="table-box" id="table-box" style="display: none;">
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th style="width: 180px;">თარიღები</th>
                            <th style="width: 140px;">ფილიალი</th>
                            <th style="width: 100px;">DVR</th>
                            <th style="width: 100px;">კამერა</th>
                            <th style="width: 140px;">ადგილი</th>
                            <th style="width: 140px;">კატეგორია</th>
                            <th style="width: 180px;">ფაქტის ID</th>
                            <th style="width: 120px;">ფოტოები</th>
                            <th style="width: 110px;">სტატუსი</th>
                            <th style="width: 200px;">პასუხისმგებლობა *</th>
                            <th style="width: 180px;">სახელი *</th>
                            <th style="width: 120px;">ჯარიმა (₾) *</th>
                            <th style="width: 240px;">კომენტარი</th>
                            <th style="width: 140px;">მოქმედება</th>
                        </tr>
                    </thead>
                    <tbody id="tbody"></tbody>
                </table>
            </div>
        </div>
        
        <div id="empty" class="empty" style="display: none;">
            <i class="fas fa-inbox"></i>
            <h3>დარღვევები არ მოიძებნა</h3>
            <p>შეცვალეთ ფილტრი</p>
        </div>

    </div>

    <div class="modal" id="modal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-images"></i>
                    დარღვევის ფოტოები #<span id="modal-id"></span>
                </h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="photo-grid" id="gallery"></div>
        </div>
    </div>

    <script>
        const token = localStorage.getItem('auth_token');
        const branches = <?php echo json_encode($branchIds); ?>;
        
        let data = [];
        let zoomed = {};
        let filter = 'pending';
        
        if (!token) {
            window.location.href = '../../index.html';
        }
        
        document.addEventListener('DOMContentLoaded', load);
        
        async function load() {
            try {
                let url = 'api/violations.php?action=list&limit=200';
                if (branches.length > 0) {
                    url += '&branch_ids=' + branches.join(',');
                }
                
                const r = await fetch(url, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                
                const d = await r.json();
                
                if (d.success && d.data?.violations) {
                    data = d.data.violations;
                    updateStats();
                    render();
                } else {
                    showEmpty();
                }
            } catch (e) {
                console.error(e);
                showEmpty();
            }
        }
        
        function filterData(f) {
            filter = f;
            
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + f).classList.add('active');
            
            render();
        }
        
        function updateStats() {
            const total = data.length;
            const pending = data.filter(v => v.progress === 'pending' || !v.progress).length;
            const completed = data.filter(v => v.progress === 'completed').length;
            const fines = data.reduce((s, v) => s + (parseFloat(v.fine_amount) || 0), 0);
            
            document.getElementById('stat-total').textContent = total;
            document.getElementById('stat-pending').textContent = pending;
            document.getElementById('stat-completed').textContent = completed;
            document.getElementById('stat-fines').textContent = fines.toFixed(2);
        }
        
        function render() {
            const tbody = document.getElementById('tbody');
            const box = document.getElementById('table-box');
            const loading = document.getElementById('loading');
            const empty = document.getElementById('empty');
            
            let items = data;
            
            if (filter === 'pending') {
                items = data.filter(v => v.progress === 'pending' || !v.progress);
            } else if (filter === 'completed') {
                items = data.filter(v => v.progress === 'completed');
            }
            
            if (items.length === 0) {
                loading.style.display = 'none';
                box.style.display = 'none';
                empty.style.display = 'block';
                return;
            }
            
            tbody.innerHTML = items.map(v => {
                const done = v.progress === 'completed';
                const hasResp = v.responsibility && v.responsibility.trim();
                const hasName = v.fullname && v.fullname.trim();
                const hasFine = v.fine_amount !== null && v.fine_amount !== undefined && parseFloat(v.fine_amount) >= 0;
                
                const canSave = hasResp && hasName && hasFine;
                
                const status = done ? 'done' : (hasResp || hasName || hasFine ? 'progress' : 'pending');
                const statusText = status === 'done' ? 'დასრულებული' : (status === 'progress' ? 'მიმდინარე' : 'განსახილველი');
                
                return `
                <tr class="${done ? 'completed' : ''}">
                    <td class="readonly"><strong>#${v.id}</strong></td>
                    <td class="readonly date-info">
                        <div><strong>შევსება:</strong> ${v.processing_date || '-'}</div>
                        <div><strong>დარღვევა:</strong> ${v.processed_date || '-'}</div>
                    </td>
                    <td class="readonly">${esc(v.branch_name || '-')}</td>
                    <td class="readonly">${esc(v.dvr || '-')}</td>
                    <td class="readonly">${esc(v.camera || '-')}</td>
                    <td class="readonly">${esc(v.incident_location || '-')}</td>
                    <td class="readonly">${esc(v.category || '-')}</td>
                    <td class="readonly">${esc(v.fact_identifier || '-')}</td>
                    <td>
                        <button class="btn-view" onclick="viewPhotos(${v.id})" id="btn-${v.id}">
                            <i class="fas fa-image"></i>
                            <span id="cnt-${v.id}">...</span>
                        </button>
                    </td>
                    <td>
                        <span class="badge badge-${status}">${statusText}</span>
                    </td>
                    <td>
                        <select class="form-select" 
                                id="resp-${v.id}" 
                                ${done ? 'disabled' : ''}
                                onchange="check(${v.id})">
                            <option value="">აირჩიეთ *</option>
                            <option value="საპატიო" ${v.responsibility === 'საპატიო' ? 'selected' : ''}>საპატიო</option>
                            <option value="დაშვებულია" ${v.responsibility === 'დაშვებულია' ? 'selected' : ''}>დაშვებულია</option>
                            <option value="სიტყვიერი გაფრთხილება" ${v.responsibility === 'სიტყვიერი გაფრთხილება' ? 'selected' : ''}>სიტყვიერი გაფრთხილება</option>
                            <option value="წერილობითი შენიშვნა" ${v.responsibility === 'წერილობითი შენიშვნა' ? 'selected' : ''}>წერილობითი შენიშვნა</option>
                            <option value="საყვედური" ${v.responsibility === 'საყვედური' ? 'selected' : ''}>საყვედური</option>
                            <option value="სასტიკი საყვედური" ${v.responsibility === 'სასტიკი საყვედური' ? 'selected' : ''}>სასტიკი საყვედური</option>
                            <option value="საყვედური და ჯარიმა" ${v.responsibility === 'საყვედური და ჯარიმა' ? 'selected' : ''}>საყვედური და ჯარიმა</option>
                            <option value="გათავისუფლება" ${v.responsibility === 'გათავისუფლება' ? 'selected' : ''}>გათავისუფლება</option>
                        </select>
                    </td>
                    <td>
                        <input type="text" 
                               class="form-input" 
                               id="name-${v.id}" 
                               value="${esc(v.fullname || '')}" 
                               placeholder="სახელი *"
                               ${done ? 'disabled' : ''}
                               onchange="check(${v.id})">
                    </td>
                    <td>
                        <input type="number" 
                               class="form-input" 
                               id="fine-${v.id}" 
                               value="${v.fine_amount || 0}" 
                               step="0.01" 
                               min="0" 
                               placeholder="0.00"
                               ${done ? 'disabled' : ''}
                               onchange="check(${v.id})">
                    </td>
                    <td>
                        <textarea class="form-textarea" 
                                  id="comm-${v.id}" 
                                  placeholder="კომენტარი"
                                  ${done ? 'disabled' : ''}>${esc(v.comment || '')}</textarea>
                    </td>
                    <td>
                        <button class="btn btn-complete" 
                                onclick="save(${v.id})" 
                                id="save-${v.id}"
                                ${!canSave || done ? 'disabled' : ''}>
                            <i class="fas ${done ? 'fa-check-double' : 'fa-check'}"></i>
                            ${done ? 'შესრულდა' : 'დასრულება'}
                        </button>
                    </td>
                </tr>
            `}).join('');
            
            loading.style.display = 'none';
            box.style.display = 'block';
            empty.style.display = 'none';
            
            items.forEach(v => loadCount(v.id));
        }
        
        function check(id) {
            const resp = document.getElementById(`resp-${id}`)?.value.trim();
            const name = document.getElementById(`name-${id}`)?.value.trim();
            const fine = parseFloat(document.getElementById(`fine-${id}`)?.value);
            
            const ok = resp && name && !isNaN(fine) && fine >= 0;
            
            const btn = document.getElementById(`save-${id}`);
            if (btn) btn.disabled = !ok;
        }
        
        function showEmpty() {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('empty').style.display = 'block';
        }
        
        async function loadCount(id) {
            try {
                const r = await fetch(`api/violations.php?action=get_photos&violation_id=${id}`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const d = await r.json();
                
                const cnt = document.getElementById(`cnt-${id}`);
                const btn = document.getElementById(`btn-${id}`);
                
                if (d.success && d.data?.photos) {
                    const c = d.data.photos.length;
                    if (cnt) cnt.textContent = c;
                    
                    if (c === 0 && btn) {
                        btn.disabled = true;
                    }
                } else {
                    if (cnt) cnt.textContent = '0';
                    if (btn) btn.disabled = true;
                }
            } catch (e) {}
        }
        
        async function viewPhotos(id) {
            try {
                const r = await fetch(`api/violations.php?action=get_photos&violation_id=${id}`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const d = await r.json();
                
                if (d.success && d.data?.photos) {
                    const gallery = document.getElementById('gallery');
                    
                    if (d.data.photos.length === 0) {
                        gallery.innerHTML = '<div class="empty"><p>ფოტოები არ არის</p></div>';
                    } else {
                        gallery.innerHTML = d.data.photos.map((p, i) => {
                            let url = p.photo_url;
                            
                            if (!url.startsWith('/') && !url.startsWith('http')) {
                                url = '/' + url;
                            }
                            
                            const pid = `photo-${id}-${i}`;
                            
                            return `
                                <div class="photo-item">
                                    <div class="photo-wrap">
                                        <img src="${url}" 
                                             id="${pid}"
                                             alt="Photo ${i + 1}" 
                                             onclick="zoom('${pid}')"
                                             onerror="this.parentElement.innerHTML='<div style=\\'padding:2rem;text-align:center;color:#95a5a6;\\'>ვერ ჩაიტვირთა</div>';">
                                    </div>
                                    <div class="photo-actions">
                                        <button class="photo-btn btn-zoom" onclick="zoom('${pid}')">
                                            <i class="fas fa-search-plus"></i>
                                            Zoom
                                        </button>
                                        <button class="photo-btn btn-download" onclick="download('${url}', 'photo_${id}_${i + 1}')">
                                            <i class="fas fa-download"></i>
                                            გადმოწერა
                                        </button>
                                    </div>
                                </div>
                            `;
                        }).join('');
                        
                        zoomed = {};
                    }
                    
                    document.getElementById('modal-id').textContent = id;
                    document.getElementById('modal').classList.add('show');
                }
            } catch (e) {
                alert('ფოტოების ჩატვირთვა ვერ მოხერხდა');
            }
        }
        
        function zoom(id) {
            const img = document.getElementById(id);
            if (!img) return;
            
            if (zoomed[id]) {
                img.classList.remove('zoomed');
                zoomed[id] = false;
            } else {
                img.classList.add('zoomed');
                zoomed[id] = true;
            }
        }
        
        async function download(url, name) {
            try {
                const r = await fetch(url);
                const blob = await r.blob();
                const blobUrl = URL.createObjectURL(blob);
                
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = name + (url.match(/\.(jpg|jpeg|png|gif)$/i)?.[0] || '.jpg');
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(blobUrl);
            } catch (e) {
                alert('გადმოწერა ვერ მოხერხდა');
            }
        }
        
        function closeModal() {
            document.getElementById('modal').classList.remove('show');
            zoomed = {};
        }
        
        async function save(id) {
            const resp = document.getElementById(`resp-${id}`).value.trim();
            const name = document.getElementById(`name-${id}`).value.trim();
            const fine = parseFloat(document.getElementById(`fine-${id}`).value);
            const comm = document.getElementById(`comm-${id}`).value.trim();
            
            if (!resp) {
                alert('❌ პასუხისმგებლობა სავალდებულოა!');
                return;
            }
            
            if (!name) {
                alert('❌ სახელი სავალდებულოა!');
                return;
            }
            
            if (isNaN(fine) || fine < 0) {
                alert('❌ ჯარიმა სავალდებულოა!');
                return;
            }
            
            const btn = document.getElementById(`save-${id}`);
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner spin"></i> ინახება...';
            
            try {
                const r = await fetch(`api/violations.php?action=update&id=${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`
                    },
                    body: JSON.stringify({
                        responsibility: resp,
                        fullname: name,
                        fine_amount: fine,
                        comment: comm || null,
                        progress: 'completed'
                    })
                });
                
                const d = await r.json();
                
                if (d.success) {
                    alert('✅ წარმატებით შესრულდა!');
                    load();
                } else {
                    alert('❌ შეცდომა: ' + (d.error || 'უცნობი'));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> დასრულება';
                }
            } catch (e) {
                alert('❌ შენახვა ვერ მოხერხდა');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> დასრულება';
            }
        }
        
        function esc(t) {
            if (!t) return '';
            const d = document.createElement('div');
            d.textContent = t;
            return d.innerHTML;
        }
        
        document.getElementById('modal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>

</body>
</html>