<?php
/**
 * Violations Module - Compact Grid Report View
 * Responsive layout with minimal vertical space
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

$violationId = intval($_GET['id'] ?? 0);

if ($violationId <= 0) {
    die('Invalid ID');
}

$db = violationsGetDB();

$stmt = $db->prepare("
    SELECT 
        v.*,
        b.name as branch_name,
        u.name as user_name,
        u.username as user_username,
        u.email as user_email
    FROM violations v
    LEFT JOIN branches b ON v.branch_id = b.id
    LEFT JOIN users u ON v.user_id = u.id
    WHERE v.id = ?
    LIMIT 1
");
$stmt->execute([$violationId]);
$violation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$violation) {
    ?>
    <!DOCTYPE html>
    <html lang="ka">
    <head>
        <meta charset="UTF-8">
        <title>ვერ მოიძებნა</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f5f5f9;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .box {
                background: white;
                padding: 2.5rem;
                border-radius: 12px;
                text-align: center;
                max-width: 450px;
                border: 1px solid #e8e8eb;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            .icon { font-size: 3rem; color: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%); margin-bottom: 1rem; }
            h1 { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.75rem; }
            .btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.6rem 1.25rem;
                background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 500;
                margin-top: 1.25rem;
            }
        </style>
    </head>
    <body>
        <div class="box">
            <i class="fas fa-search icon"></i>
            <h1>დარღვევა ვერ მოიძებნა</h1>
            <p style="color:#95a5a6;margin-bottom:0.5rem;">ID #<?php echo $violationId; ?> არ არსებობს</p>
            <a href="index.php" class="btn">
                <i class="fas fa-arrow-left"></i> დაბრუნება
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$stmt = $db->prepare("
    SELECT id, filename, filepath, photo_url, filesize, uploaded_at
    FROM violation_photos
    WHERE violation_id = ?
    ORDER BY uploaded_at ASC
");
$stmt->execute([$violationId]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>დარღვევა #<?php echo $violationId; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f9;
            color: #2c3e50;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .nav {
            background: white;
            padding: 0.65rem 1.5rem;
            border-bottom: 1px solid #e8e8eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: #2c3e50;
        }
        
        .brand-icon {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }
        
        .brand-text {
            font-size: 1rem;
            font-weight: 600;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.9rem;
            background: #f5f5f9;
            color: #666;
            border: 1px solid #e8e8eb;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
        }
        
        .btn-back:hover {
            background: #e8e8eb;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 1.25rem;
        }
        
        .header {
            background: linear-gradient(135deg, linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%), #8B6EC4);
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
        }
        
        .header-meta {
            display: flex;
            gap: 1.5rem;
            font-size: 0.85rem;
            opacity: 0.95;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        
        .badge {
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgb(29 169 138);
            border: 1px solid rgba(255,255,255,0.3) !important;
            color: white;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        
        .card {
            background: white;
            border: 1px solid #e8e8eb;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        
        .card-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: #95a5a6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        
        .card-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .card.primary .card-value {
            color: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
        }
        
        .card.success .card-value {
            color: #27ae60;
        }
        
        .card.warning .card-value {
            color: #e67e22;
        }
        
        .card.muted .card-value {
            color: #95a5a6;
            font-style: italic;
        }
        
        .section {
            background: white;
            border: 1px solid #e8e8eb;
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        
        .section-header {
            font-size: 1rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }
        
        .photos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
        }
        
        .photo {
            border: 1px solid #e8e8eb;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.2s;
        }
        
        .photo:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(107,78,157,0.15);
        }
        
        .photo img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            cursor: pointer;
        }
        
        .photo-info {
            padding: 0.65rem;
            background: #fafafa;
            font-size: 0.75rem;
            color: #666;
        }
        
        .photo-info div {
            display: flex;
            justify-content: space-between;
            padding: 0.2rem 0;
        }
        
        .empty {
            text-align: center;
            padding: 2.5rem;
            color: #95a5a6;
        }
        
        .empty i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            opacity: 0.4;
        }
        
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.95);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal img {
            max-width: 95%;
            max-height: 90vh;
            border-radius: 8px;
        }
        
        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 40px;
            height: 40px;
            background: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.1rem;
        }
        
        .modal-close:hover {
            background: linear-gradient(135deg, #714b67 0%, #8a5a7a 50%, #a06c8a 100%);
            color: white;
        }
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-meta {
                flex-direction: column;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
    
    <nav class="nav">
        <a href="../../index.html" class="brand">
            <div class="brand-icon">
                <i class="fas fa-shield-halved"></i>
            </div>
            <span class="brand-text">Agrohub</span>
        </a>
        
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> უკან
        </a>
    </nav>

    <div class="container">
        
        <div class="header">
            <div class="header-left">
                <h1>დარღვევა #<?php echo $violationId; ?></h1>
                <div class="header-meta">
                    <div class="meta-item">
                        <i class="fas fa-building"></i>
                        <strong><?php echo htmlspecialchars($violation['branch_name'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($violation['user_name'] ?? 'N/A'); ?>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <?php echo $violation['processed_date'] ?? 'N/A'; ?>
                    </div>
                </div>
            </div>
            
            <div class="badge">
                <?php 
                    if ($violation['progress'] === 'completed') echo 'დასრულებული';
                    elseif ($violation['progress'] === 'in_progress') echo 'მიმდინარე';
                    else echo 'განსახილველი';
                ?>
            </div>
        </div>

        <!-- MAIN INFO GRID -->
        <div class="grid">
            <div class="card primary">
                <div class="card-title">
                    <i class="fas fa-hashtag"></i> ID
                </div>
                <div class="card-value">#<?php echo $violation['id']; ?></div>
            </div>
            
            <div class="card <?php 
                if ($violation['progress'] === 'completed') echo 'success';
                elseif ($violation['progress'] === 'in_progress') echo 'warning';
            ?>">
                <div class="card-title">
                    <i class="fas fa-signal"></i> სტატუსი
                </div>
                <div class="card-value">
                    <?php 
                        if ($violation['progress'] === 'completed') echo 'დასრულებული';
                        elseif ($violation['progress'] === 'in_progress') echo 'მიმდინარე';
                        else echo 'განსახილველი';
                    ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-store"></i> ფილიალი
                </div>
                <div class="card-value"><?php echo htmlspecialchars($violation['branch_name'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-user"></i> შემქმნელი
                </div>
                <div class="card-value"><?php echo htmlspecialchars($violation['user_name'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-calendar"></i> დარღვევის თარიღი
                </div>
                <div class="card-value"><?php echo $violation['processed_date'] ?? 'N/A'; ?></div>
            </div>
            
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-clock"></i> შექმნა
                </div>
                <div class="card-value"><?php echo $violation['created_at'] ?? 'N/A'; ?></div>
            </div>
            
            <div class="card primary">
                <div class="card-title">
                    <i class="fas fa-server"></i> DVR
                </div>
                <div class="card-value"><?php echo htmlspecialchars($violation['dvr'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="card primary">
                <div class="card-title">
                    <i class="fas fa-camera"></i> კამერა
                </div>
                <div class="card-value"><?php echo htmlspecialchars($violation['camera'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-map-marker-alt"></i> ადგილი
                </div>
                <div class="card-value"><?php echo htmlspecialchars($violation['incident_location'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="card primary">
                <div class="card-title">
                    <i class="fas fa-tag"></i> კატეგორია
                </div>
                <div class="card-value"><?php echo htmlspecialchars($violation['category'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-fingerprint"></i> ფაქტის ID
                </div>
                <div class="card-value"><?php echo htmlspecialchars($violation['fact_identifier'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="card <?php echo $violation['fullname'] ? '' : 'muted'; ?>">
                <div class="card-title">
                    <i class="fas fa-user-circle"></i> დამრღვევი
                </div>
                <div class="card-value"><?php echo htmlspecialchars($violation['fullname'] ?? '— არ არის'); ?></div>
            </div>
            
            <div class="card <?php echo $violation['responsibility'] ? 'warning' : 'muted'; ?>">
                <div class="card-title">
                    <i class="fas fa-scale-balanced"></i> პასუხისმგებლობა
                </div>
                <div class="card-value"><?php echo htmlspecialchars($violation['responsibility'] ?? '— არ არის'); ?></div>
            </div>
            
            <div class="card <?php echo $violation['fine_amount'] ? 'success' : 'muted'; ?>">
                <div class="card-title">
                    <i class="fas fa-coins"></i> ჯარიმა
                </div>
                <div class="card-value">
                    <?php 
                        if ($violation['fine_amount']) {
                            echo number_format($violation['fine_amount'], 2) . ' ₾';
                        } else {
                            echo '— არ არის';
                        }
                    ?>
                </div>
            </div>
        </div>

        <!-- COMMENTS SECTION -->
        <?php if ($violation['category_comment'] || $violation['comment']): ?>
        <div class="section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-comment"></i>
                </div>
                <span>კომენტარები</span>
            </div>
            
            <div class="grid">
                <?php if ($violation['category_comment']): ?>
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-message"></i> კატეგორიის კომენტარი
                    </div>
                    <div class="card-value" style="white-space:pre-wrap;line-height:1.6;">
                        <?php echo nl2br(htmlspecialchars($violation['category_comment'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($violation['comment']): ?>
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-comment-dots"></i> GM-ის კომენტარი
                    </div>
                    <div class="card-value" style="white-space:pre-wrap;line-height:1.6;">
                        <?php echo nl2br(htmlspecialchars($violation['comment'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- PHOTOS -->
        <div class="section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-images"></i>
                </div>
                <span>ფოტოები (<?php echo count($photos); ?>)</span>
            </div>
            
            <?php if (empty($photos)): ?>
                <div class="empty">
                    <i class="fas fa-image"></i>
                    <p>ფოტოები არ არის</p>
                </div>
            <?php else: ?>
                <div class="photos">
                    <?php foreach ($photos as $photo): ?>
                    <div class="photo">
                        <img src="<?php echo htmlspecialchars($photo['photo_url'] ?? $photo['filepath']); ?>" 
                             alt="Photo" 
                             onclick="openModal('<?php echo htmlspecialchars($photo['photo_url'] ?? $photo['filepath']); ?>')">
                        <div class="photo-info">
                            <div>
                                <strong>ფაილი:</strong>
                                <span><?php echo htmlspecialchars($photo['filename'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong>ზომა:</strong>
                                <span><?php echo isset($photo['filesize']) ? round($photo['filesize']/1024, 2) . ' KB' : 'N/A'; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="modal" id="modal" onclick="closeModal()">
        <button class="modal-close" onclick="closeModal(); event.stopPropagation();">
            <i class="fas fa-times"></i>
        </button>
        <img id="modal-img" src="" alt="Full">
    </div>

    <script>
        function openModal(src) {
            document.getElementById('modal-img').src = src;
            document.getElementById('modal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('modal').classList.remove('show');
            document.body.style.overflow = '';
        }
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    </script>

</body>
</html>