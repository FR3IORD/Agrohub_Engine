<?php
/**
 * Violations Module - VM Create Reports
 * Desktop: Horizontal scrollable table | Mobile (under 875px): Vertical cards
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

if (!$permissions['can_create']) {
    http_response_code(403);
    die('Access Denied - VM Only');
}

$db = violationsGetDB();

// Branch-to-Email mapping
$branchEmails = [
    'gelovani' => 't.surguladze@agrohub.ge',
    'გელოვანი' => 't.surguladze@agrohub.ge',
    'avchala' => 'gm.avchala@agrohub.ge',
    'ავჭალა' => 'gm.avchala@agrohub.ge',
    'maghlivi' => 'gm.maghlivi@agrohub.ge',
    'მაღლივი' => 'gm.maghlivi@agrohub.ge',
    'sanapiro' => 'gm.sanapiro@agrohub.ge',
    'სანაპირო' => 'gm.sanapiro@agrohub.ge',
    'sport' => 'gm.sport@agrohub.ge',
    'სპორტი' => 'gm.sport@agrohub.ge',
    'chiladze' => 'gm.chiladze@agrohub.ge',
    'ჭილაძე' => 'gm.chiladze@agrohub.ge',
    'vake' => 'gm.vake@agrohub.ge',
    'ვაკე' => 'gm.vake@agrohub.ge',
    'gldani' => 'gm.gldani@agrohub.ge',
    'გლდანი' => 'gm.gldani@agrohub.ge',
    'qutaisi' => 'gm.qutaisi@agrohub.ge',
    'ქუთაისი' => 'gm.qutaisi@agrohub.ge',
    'kutaisi' => 'gm.qutaisi@agrohub.ge',
    'batumi' => 'gm.batumi@agrohub.ge',
    'ბათუმი' => 'gm.batumi@agrohub.ge'
];

$userBranches = [];
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
    
    if (empty($userBranches)) {
        $stmt = $db->query("SELECT id, name FROM branches ORDER BY name ASC");
        $userBranches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Branches load error: " . $e->getMessage());
    $userBranches = [];
}

foreach ($userBranches as &$branch) {
    $branchNameLower = mb_strtolower($branch['name'], 'UTF-8');
    $branch['email'] = $branchEmails[$branchNameLower] ?? 'gm@agrohub.ge';
}

$branchName = !empty($userBranches) ? $userBranches[0]['name'] : 'Unknown';
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ახალი რეპორტი - Violations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f9;
            color: #2c3e50;
            font-size: 14px;
            line-height: 1.6;
        }
        
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
        
        .user-menu {
            position: relative;
        }
        
        .user-trigger {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
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
        
        .user-dropdown {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: white;
            border: 1px solid #e8e8eb;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            min-width: 200px;
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
            transition: all 0.2s;
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
        
        .info-alert {
            background: #e8f4fd;
            border: 1px solid #b3d9f2;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            color: #0077c2;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        /* DESKTOP TABLE VIEW */
        .table-box {
            background: white;
            border: 1px solid #e8e8eb;
            border-radius: 8px;
            overflow: visible;
            margin-bottom: 1.5rem;
        }
        
        .table-scroll {
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-scroll::-webkit-scrollbar {
            height: 10px;
        }
        
        .table-scroll::-webkit-scrollbar-track {
            background: #f5f5f9;
            border-radius: 5px;
        }
        
        .table-scroll::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            border-radius: 5px;
        }
        
        .table-scroll::-webkit-scrollbar-thumb:hover {
            background: #5a3d8a;
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
            border-right: 1px solid #f5f5f9;
        }
        
        .data-table th:last-child {
            border-right: none;
        }
        
        .data-table td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid #f5f5f9;
            vertical-align: top;
        }
        
        .data-table tbody tr:hover {
            background: #fafafa;
        }
        
        .row-num {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            color: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        /* MOBILE CARD VIEW */
        .cards-container {
            display: none;
        }
        
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
        
        .live-time {
            font-weight: 600;
            color: #27ae60;
            font-size: 0.85rem;
        }
        
        .branch-email {
            font-size: 0.7rem;
            color: #95a5a6;
            margin-top: 0.25rem;
            font-style: italic;
        }
        
        .file-btn {
            padding: 0.4rem 0.75rem;
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s;
        }
        
        .file-btn:hover {
            background: #5a3d8a;
        }
        
        .file-count {
            display: inline-block;
            margin-left: 0.5rem;
            padding: 0.2rem 0.5rem;
            background: #e8e8eb;
            color: #666;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .btn-remove {
            padding: 0.4rem;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-remove:hover {
            background: #c0392b;
        }
        
        .actions-bar {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            border: 1px solid #e8e8eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
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
        
        .btn-add {
            background: #27ae60;
            color: white;
        }
        
        .btn-add:hover {
            background: #229954;
        }
        
        .btn-add:disabled {
            background: #e8e8eb;
            color: #95a5a6;
            cursor: not-allowed;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            color: white;
            padding: 0.65rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        .btn-submit:hover {
            background: #5a3d8a;
        }
        
        .btn-submit:disabled {
            background: #e8e8eb;
            color: #95a5a6;
            cursor: not-allowed;
        }
        
        .counter {
            font-size: 0.9rem;
            color: #666;
            font-weight: 600;
        }
        
        .hidden {
            display: none !important;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .spin {
            animation: spin 1s linear infinite;
        }
        
        /* MOBILE RESPONSIVE (under 875px) */
        @media (max-width: 875px) {
            .top-nav {
                padding: 0.75rem 1rem;
            }
            
            .nav-left {
                gap: 0.75rem;
            }
            
            .nav-link {
                display: none;
            }
            
            .logo-text {
                font-size: 1rem;
            }
            
            .container {
                padding: 1rem;
            }
            
            .page-title {
                font-size: 1.35rem;
            }
            
            .page-desc {
                font-size: 0.8rem;
            }
            
            .info-alert {
                font-size: 0.8rem;
                padding: 0.65rem 0.85rem;
            }
            
            /* Hide desktop table */
            .table-box {
                display: none;
            }
            
            /* Show mobile cards */
            .cards-container {
                display: grid;
                gap: 1rem;
                margin-bottom: 1rem;
            }
            
            .card {
                background: white;
                border: 1px solid #e8e8eb;
                border-radius: 10px;
                padding: 1rem;
                box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            }
            
            .card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1rem;
                padding-bottom: 0.75rem;
                border-bottom: 2px solid linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            }
            
            .card-num {
                width: 36px;
                height: 36px;
                background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
                color: white;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 1rem;
            }
            
            .btn-remove-mobile {
                padding: 0.45rem 0.65rem;
                background: #e74c3c;
                color: white;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 0.85rem;
            }
            
            .form-group {
                margin-bottom: 0.85rem;
            }
            
            .form-label {
                display: block;
                font-size: 0.8rem;
                font-weight: 600;
                color: #666;
                margin-bottom: 0.4rem;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            
            .live-time-display {
                padding: 0.65rem 0.75rem;
                background: #e8f8f5;
                border: 1px solid #c3e6cb;
                border-radius: 6px;
                font-weight: 600;
                color: #27ae60;
                font-size: 0.9rem;
            }
            
            .file-wrap {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
            
            .actions-bar {
                flex-direction: column;
                gap: 0.85rem;
                padding: 1rem;
            }
            
            .btn-submit {
                width: 100%;
                justify-content: center;
            }
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
            <a href="index.php" class="nav-link">
                <i class="fas fa-arrow-left"></i> უკან
            </a>
        </div>
        
        <div class="user-menu">
            <div class="user-trigger" onclick="toggleMenu()">
                <div class="user-avatar">
                    <?php echo mb_strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                </div>
                <span><?php echo htmlspecialchars($user['name']); ?></span>
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
                    
                    <a href="index.php" class="dropdown-item">
                        <i class="fas fa-list"></i>
                        <span>სია</span>
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
        
        <div class="page-header">
            <h1 class="page-title">ახალი დარღვევის რეპორტი</h1>
            <p class="page-desc">შეავსეთ ყველა ველი და დაამატეთ ფოტოები (მაქს. 7 რეპორტი)</p>
        </div>

        <div class="info-alert">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>შენიშვნა:</strong> ყველა ველი სავალდებულოა (*). კატეგორიის კომენტარი ჩნდება კატეგორიის არჩევის შემდეგ. 
                ფილიალი: <strong><?php echo htmlspecialchars($branchName); ?></strong>
            </div>
        </div>

        <form id="form" novalidate>
            
            <!-- DESKTOP TABLE VIEW -->
            <div class="table-box">
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th style="width: 140px;">შევსების თარიღი</th>
                                <th style="width: 160px;">დარღვევის თარიღი *</th>
                                <th style="width: 100px;">DVR *</th>
                                <th style="width: 100px;">კამერა *</th>
                                <th style="width: 160px;">ადგილი *</th>
                                <th style="width: 160px;">კატეგორია *</th>
                                <th style="width: 220px;">კატეგორიის კომენტარი</th>
                                <th style="width: 180px;">ფილიალი *</th>
                                <th style="width: 220px;">დარღვევის აღღწერა *</th>
                                <th style="width: 220px;">ფაქტის ID *</th>
                                <th style="width: 140px;">ფოტო/კადრები *</th>
                                <th style="width: 60px;"></th>
                            </tr>
                        </thead>
                        <tbody id="tbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- MOBILE CARD VIEW -->
            <div class="cards-container" id="cards"></div>

            <div class="actions-bar">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button type="button" class="btn btn-add" onclick="addRow()" id="add-btn">
                        <i class="fas fa-plus"></i>
                        დამატება
                    </button>
                    <span class="counter">
                        <span id="count">0</span> / 7
                    </span>
                </div>
                
                <button type="button" class="btn btn-submit" id="submit-btn" onclick="submitAll()">
                    <i class="fas fa-paper-plane"></i>
                    ყველას გაგზავნა
                </button>
            </div>

        </form>

    </div>

    <script>
        const token = localStorage.getItem('auth_token');
        const userId = <?php echo $user['id']; ?>;
        const branches = <?php echo json_encode($userBranches); ?>;
        
        let count = 0;
        const MAX = 7;
        let files = {};
        
        if (!token) window.location.href = '../../index.html';
        
        document.addEventListener('DOMContentLoaded', () => {
            addRow();
            setInterval(updateTimes, 1000);
        });
        
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
        
        function getTime() {
            const d = new Date();
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            const hours = String(d.getHours()).padStart(2, '0');
            const minutes = String(d.getMinutes()).padStart(2, '0');
            
            return {
                display: `${day}/${month}/${year} ${hours}:${minutes}`,
                input: `${year}-${month}-${day}T${hours}:${minutes}`
            };
        }
        
        function updateTimes() {
            const time = getTime();
            document.querySelectorAll('.live-time, .live-time-display').forEach(el => {
                el.textContent = time.display;
            });
        }
        
        function addRow() {
            if (count >= MAX) {
                alert(`❌ მაქს. ${MAX} რეპორტი!`);
                return;
            }
            
            count++;
            const time = getTime();
            
            // Add to desktop table
            const tbody = document.getElementById('tbody');
            const tr = document.createElement('tr');
            tr.id = `row-${count}`;
            tr.innerHTML = `
                <td>
                    <div class="row-num">${count}</div>
                </td>
                <td>
                    <div class="live-time">${time.display}</div>
                    <input type="hidden" id="created-${count}" value="${time.input}">
                </td>
                <td>
                    <input type="datetime-local" 
                           class="form-input" 
                           id="viol-${count}" 
                           max="${time.input}">
                </td>
                <td>
                    <input type="text" class="form-input" id="dvr-${count}" placeholder="DVR">
                </td>
                <td>
                    <input type="text" class="form-input" id="cam-${count}" placeholder="№">
                </td>
                <td>
                    <input type="text" class="form-input" id="loc-${count}" placeholder="ადგილი">
                </td>
                <td>
                    <select class="form-select" id="cat-${count}" onchange="toggleComment(${count})">
                        <option value="">აირჩიეთ</option>
                        <option value="ტელეფონი">ტელეფონი</option>
                        <option value="უნიფორმა">უნიფორმა</option>
                        <option value="უსაფრთხოება">უსაფრთხოება</option>
                        <option value="ქურდობა">ქურდობა</option>
                        <option value="ჭიშოთობა">ჭიშოთობა</option>
                        <option value="სხვა">სხვა</option>
                    </select>
                </td>
                <td>
                    <textarea class="form-textarea hidden" id="comm-${count}" placeholder="კომენტარი"></textarea>
                </td>
                <td>
                    <select class="form-select" id="branch-${count}" onchange="showEmail(${count})">
                        ${branches.map(b => `<option value="${b.id}" data-email="${b.email}">${esc(b.name)}</option>`).join('')}
                    </select>
                    <div class="branch-email" id="email-${count}">${branches[0]?.email || ''}</div>
                </td>
                <td>
                    <textarea class="form-textarea" id="desc-${count}" placeholder="აღწერა"></textarea>
                </td>
                <td>
                    <input type="text" class="form-input" id="fact-${count}" placeholder="Fact ID">
                </td>
                <td>
                    <input type="file" id="file-${count}" multiple accept="image/*,video/*" style="display:none" onchange="handleFile(${count})">
                    <button type="button" class="file-btn" onclick="document.getElementById('file-${count}').click()">
                        <i class="fas fa-upload"></i>
                        ატვირთვა
                    </button>
                    <span class="file-count" id="cnt-${count}">0</span>
                </td>
                <td>
                    <button type="button" class="btn-remove" onclick="removeRow(${count})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            
            // Add to mobile cards
            const cards = document.getElementById('cards');
            const card = document.createElement('div');
            card.className = 'card';
            card.id = `card-${count}`;
            card.innerHTML = `
                <div class="card-header">
                    <div class="card-num">${count}</div>
                    <button type="button" class="btn-remove-mobile" onclick="removeRow(${count})">
                        <i class="fas fa-trash"></i> წაშლა
                    </button>
                </div>
                
                <div class="form-group">
                    <label class="form-label">შევსების თარიღი</label>
                    <div class="live-time-display">${time.display}</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">დარღვევის თარიღი *</label>
                    <input type="datetime-local" 
                           class="form-input" 
                           id="viol-m-${count}" 
                           max="${time.input}">
                </div>
                
                <div class="form-group">
                    <label class="form-label">DVR *</label>
                    <input type="text" class="form-input" id="dvr-m-${count}" placeholder="DVR">
                </div>
                
                <div class="form-group">
                    <label class="form-label">კამერა *</label>
                    <input type="text" class="form-input" id="cam-m-${count}" placeholder="კამერის ნომერი">
                </div>
                
                <div class="form-group">
                    <label class="form-label">ინციდენტის ადგილი *</label>
                    <input type="text" class="form-input" id="loc-m-${count}" placeholder="ადგილი">
                </div>
                
                <div class="form-group">
                    <label class="form-label">კატეგორია *</label>
                    <select class="form-select" id="cat-m-${count}" onchange="toggleComment(${count})">
                        <option value="">აირჩიეთ კატეგორია</option>
                        <option value="ტელეფონი">ტელეფონი</option>
                        <option value="უნიფორმა">უნიფორმა</option>
                        <option value="უსაფრთხოება">უსაფრთხოება</option>
                        <option value="ქურდობა">ქურდობა</option>
                        <option value="ჭიშოთობა">ჭიშოთობა</option>
                        <option value="სხვა">სხვა</option>
                    </select>
                </div>
                
                <div class="form-group hidden" id="comm-wrap-m-${count}">
                    <label class="form-label">კატეგორიის კომენტარი *</label>
                    <textarea class="form-textarea" id="comm-m-${count}" placeholder="დაწვრილებით აღწერეთ"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ფილიალი *</label>
                    <select class="form-select" id="branch-m-${count}" onchange="showEmail(${count})">
                        ${branches.map(b => `<option value="${b.id}" data-email="${b.email}">${esc(b.name)}</option>`).join('')}
                    </select>
                    <div class="branch-email" id="email-m-${count}">${branches[0]?.email || ''}</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">დარღვევის აღწერა *</label>
                    <textarea class="form-textarea" id="desc-m-${count}" placeholder="დეტალური აღწერა"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ფაქტის იდენტიფიკატორი *</label>
                    <input type="text" class="form-input" id="fact-m-${count}" placeholder="Fact ID">
                </div>
                
                <div class="form-group">
                    <label class="form-label">ფოტო/ვიდეო კადრები *</label>
                    <div class="file-wrap">
                        <button type="button" class="file-btn" onclick="document.getElementById('file-${count}').click()">
                            <i class="fas fa-upload"></i>
                            ატვირთვა
                        </button>
                        <span class="file-count" id="cnt-m-${count}">0 ფაილი</span>
                    </div>
                </div>
            `;
            cards.appendChild(card);
            
            files[count] = [];
            updateCount();
        }
        
        function removeRow(id) {
            if (count <= 1) {
                alert('❌ მინ. 1 რეპორტი!');
                return;
            }
            
            const row = document.getElementById(`row-${id}`);
            const card = document.getElementById(`card-${id}`);
            
            if (row) row.remove();
            if (card) card.remove();
            
            delete files[id];
            count--;
            updateCount();
        }
        
        function toggleComment(id) {
            const cat = document.getElementById(`cat-${id}`);
            const catM = document.getElementById(`cat-m-${id}`);
            const comm = document.getElementById(`comm-${id}`);
            const commM = document.getElementById(`comm-m-${id}`);
            const wrapM = document.getElementById(`comm-wrap-m-${id}`);
            
            const val = (cat?.value || catM?.value);
            
            if (val) {
                if (comm) comm.classList.remove('hidden');
                if (wrapM) wrapM.classList.remove('hidden');
            } else {
                if (comm) {
                    comm.classList.add('hidden');
                    comm.value = '';
                }
                if (wrapM && commM) {
                    wrapM.classList.add('hidden');
                    commM.value = '';
                }
            }
        }
        
        function showEmail(id) {
            const sel = document.getElementById(`branch-${id}`);
            const selM = document.getElementById(`branch-m-${id}`);
            const emailDiv = document.getElementById(`email-${id}`);
            const emailDivM = document.getElementById(`email-m-${id}`);
            
            const s = sel || selM;
            const opt = s.options[s.selectedIndex];
            const email = opt.getAttribute('data-email');
            
            if (emailDiv && email) emailDiv.textContent = email;
            if (emailDivM && email) emailDivM.textContent = email;
        }
        
        function handleFile(id) {
            const input = document.getElementById(`file-${id}`);
            const arr = Array.from(input.files);
            files[id] = arr;
            
            const cnt = document.getElementById(`cnt-${id}`);
            const cntM = document.getElementById(`cnt-m-${id}`);
            
            if (cnt) cnt.textContent = arr.length;
            if (cntM) cntM.textContent = `${arr.length} ფაილი`;
        }
        
        function updateCount() {
            document.getElementById('count').textContent = count;
            const btn = document.getElementById('add-btn');
            if (btn) btn.disabled = count >= MAX;
        }
        
        async function submitAll() {
            const data = [];
            
            for (let i = 1; i <= count; i++) {
                const created = document.getElementById(`created-${i}`)?.value;
                const viol = document.getElementById(`viol-${i}`)?.value || document.getElementById(`viol-m-${i}`)?.value;
                const dvr = (document.getElementById(`dvr-${i}`)?.value || document.getElementById(`dvr-m-${i}`)?.value)?.trim();
                const cam = (document.getElementById(`cam-${i}`)?.value || document.getElementById(`cam-m-${i}`)?.value)?.trim();
                const loc = (document.getElementById(`loc-${i}`)?.value || document.getElementById(`loc-m-${i}`)?.value)?.trim();
                const cat = document.getElementById(`cat-${i}`)?.value || document.getElementById(`cat-m-${i}`)?.value;
                const branch = document.getElementById(`branch-${i}`)?.value || document.getElementById(`branch-m-${i}`)?.value;
                const comm = (document.getElementById(`comm-${i}`)?.value || document.getElementById(`comm-m-${i}`)?.value)?.trim();
                const desc = (document.getElementById(`desc-${i}`)?.value || document.getElementById(`desc-m-${i}`)?.value)?.trim();
                const fact = (document.getElementById(`fact-${i}`)?.value || document.getElementById(`fact-m-${i}`)?.value)?.trim();
                const fls = files[i] || [];
                
                if (!created || !viol || !dvr || !cam || !loc || !cat || !branch || !fact || !desc) {
                    alert(`❌ რეპორტი #${i}: ყველა ველი სავალდებულოა!`);
                    return;
                }
                
                if (cat && !comm) {
                    alert(`❌ რეპორტი #${i}: კატეგორიის კომენტარი სავალდებულოა!`);
                    return;
                }
                
                if (fls.length === 0) {
                    alert(`❌ რეპორტი #${i}: ფოტო სავალდებულოა!`);
                    return;
                }
                
                data.push({
                    branch_id: branch,
                    user_id: userId,
                    processing_date: created.replace('T', ' ') + ':00',
                    processed_date: viol.replace('T', ' ') + ':00',
                    dvr: dvr,
                    camera: cam,
                    incident_location: loc,
                    category: cat,
                    category_comment: comm,
                    fact_identifier: fact,
                    progress: 'pending',
                    rowId: i
                });
            }
            
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner spin"></i> იგზავნება...';
            
            try {
                let success = 0;
                
                for (const v of data) {
                    const r = await fetch('api/violations.php?action=create', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${token}`
                        },
                        body: JSON.stringify(v)
                    });
                    
                    const d = await r.json();
                    
                    if (d.success) {
                        const vid = d.data.violation_id;
                        const fls = files[v.rowId] || [];
                        
                        for (const file of fls) {
                            const fd = new FormData();
                            fd.append('photo', file);
                            fd.append('violation_id', vid);
                            
                            await fetch('api/violations.php?action=upload_photo', {
                                method: 'POST',
                                headers: {
                                    'Authorization': `Bearer ${token}`
                                },
                                body: fd
                            });
                        }
                        
                        success++;
                    } else {
                        alert(`❌ შეცდომა: ${d.error || 'უცნობი'}`);
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-paper-plane"></i> ყველას გაგზავნა';
                        return;
                    }
                }
                
                alert(`✅ ${success} რეპორტი გაიგზავნა!`);
                window.location.href = 'index.php';
                
            } catch (err) {
                console.error('Submit error:', err);
                alert('❌ შეცდომა: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> ყველას გაგზავნა';
            }
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