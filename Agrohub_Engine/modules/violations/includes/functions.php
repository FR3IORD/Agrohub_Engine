<?php
/**
 * Violations Module - Helper Functions
 */

if (!defined('VIOLATIONS_MODULE')) {
    die('Direct access not permitted');
}

/**
 * Get all violation types
 */
function violationsGetTypes() {
    try {
        $db = violationsGetDB();
        $stmt = $db->query("
            SELECT id, name, description, severity, is_active
            FROM violation_types
            WHERE is_active = 1
            ORDER BY name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Get Types Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all statuses
 */
function violationsGetStatuses() {
    try {
        $db = violationsGetDB();
        $stmt = $db->query("
            SELECT id, name, color, icon
            FROM violation_statuses
            ORDER BY id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Get Statuses Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get violation by ID
 */
function violationsGetById($id) {
    try {
        $db = violationsGetDB();
        $stmt = $db->prepare("
            SELECT 
                v.*,
                vt.name as type_name,
                vt.severity as type_severity,
                vs.name as status_name,
                b.name as branch_name,
                u.name as reported_by_name
            FROM violations v
            LEFT JOIN violation_types vt ON v.violation_type_id = vt.id
            LEFT JOIN violation_statuses vs ON v.status_id = vs.id
            LEFT JOIN branches b ON v.branch_id = b.id
            LEFT JOIN users u ON v.reported_by = u.id
            WHERE v.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Get By ID Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get violation photos
 */
function violationsGetPhotos($violationId) {
    try {
        $db = violationsGetDB();
        $stmt = $db->prepare("
            SELECT id, violation_id, filename, filepath, filesize, uploaded_by, uploaded_at
            FROM violation_photos
            WHERE violation_id = ?
            ORDER BY uploaded_at ASC
        ");
        $stmt->execute([$violationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Get Photos Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get statistics
 */
function violationsGetStats($filters = []) {
    try {
        $db = violationsGetDB();
        
        $where = ["1=1"];
        $params = [];
        
        if (isset($filters['period'])) {
            $where[] = "violation_date >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $params[] = $filters['period'];
        }
        
        if (isset($filters['branch_id'])) {
            $where[] = "branch_id = ?";
            $params[] = $filters['branch_id'];
        }
        
        if (isset($filters['user_id'])) {
            $where[] = "reported_by = ?";
            $params[] = $filters['user_id'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as investigating,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
            FROM violations
            WHERE $whereClause
        ");
        
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Get Stats Error: " . $e->getMessage());
        return ['total' => 0, 'open' => 0, 'investigating' => 0, 'resolved' => 0, 'closed' => 0];
    }
}

/**
 * Search violations
 */
function violationsSearch($params) {
    try {
        $db = violationsGetDB();
        
        $where = ["1=1"];
        $values = [];
        
        if (!empty($params['search'])) {
            $where[] = "(v.title LIKE ? OR v.description LIKE ?)";
            $searchTerm = "%{$params['search']}%";
            $values[] = $searchTerm;
            $values[] = $searchTerm;
        }
        
        if (!empty($params['status'])) {
            $where[] = "v.status = ?";
            $values[] = $params['status'];
        }
        
        if (!empty($params['type'])) {
            $where[] = "v.violation_type_id = ?";
            $values[] = $params['type'];
        }
        
        if (!empty($params['branch_id'])) {
            $where[] = "v.branch_id = ?";
            $values[] = $params['branch_id'];
        }
        
        if (!empty($params['user_id'])) {
            $where[] = "v.reported_by = ?";
            $values[] = $params['user_id'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $limit = intval($params['limit'] ?? 50);
        $offset = intval($params['offset'] ?? 0);
        
        $values[] = $limit;
        $values[] = $offset;
        
        $stmt = $db->prepare("
            SELECT 
                v.*,
                vt.name as type_name,
                vt.severity,
                vs.name as status_name,
                b.name as branch_name
            FROM violations v
            LEFT JOIN violation_types vt ON v.violation_type_id = vt.id
            LEFT JOIN violation_statuses vs ON v.status_id = vs.id
            LEFT JOIN branches b ON v.branch_id = b.id
            WHERE $whereClause
            ORDER BY v.violation_date DESC, v.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute($values);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Search Error: " . $e->getMessage());
        return [];
    }
}

return true;