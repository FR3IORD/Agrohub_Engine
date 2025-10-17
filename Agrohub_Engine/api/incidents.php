<?php
/**
 * Incidents API - Cashier receipt incident workflow
 *
 * Endpoints:
 *  - GET /api/incidents.php                 -> list incidents (admins/managers may see all)
 *  - POST /api/incidents.php                -> create new incident (cashier reports wrong receipt)
 *  - PUT /api/incidents.php                 -> update incident status (action-based)
 *
 * Expected JSON (POST):
 *  { "cashier_id": 123, "amount": 5.00, "reason": "Wrong printed receipt", "evidence": {...} }
 *
 * Expected JSON (PUT):
 *  { "incident_id": 1, "action": "notify" | "confirm" | "pay", "note": "optional note" }
 */

require_once 'config.php';
require_once 'database.php';
require_once 'utils.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $db = new Database();
    $utils = new Utils();

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // list incidents - filter optional ?status=reported
        $user = $utils->authenticateRequest();
        $status = $_GET['status'] ?? null;

        // Only admins/managers/monitor/HR can list all; others only their own (cashier)
        $params = [];
        $sql = "SELECT i.*, u.name as reporter_name FROM incidents i LEFT JOIN users u ON i.reported_by = u.id WHERE 1=1";
        if ($status) { $sql .= " AND i.status = ?"; $params[] = $status; }

        if (!$user || !in_array($user['role'], ['admin', 'manager', 'monitor', 'hr'])) {
            // limit to incidents reported by this cashier
            $sql .= " AND i.reported_by = ?";
            $params[] = $user ? $user['id'] : 0;
        }

        $sql .= " ORDER BY i.created_at DESC LIMIT 200";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $utils->sendSuccess(['incidents' => $incidents]);
        exit();
    }

    if ($method === 'POST') {
        // Create incident (cashier reports wrong receipt)
        $input = json_decode(file_get_contents('php://input'), true);
        $reported_by = $input['cashier_id'] ?? null;
        $amount = floatval($input['amount'] ?? 5.00);
        $reason = trim($input['reason'] ?? 'Incorrect receipt detected');
        $evidence = isset($input['evidence']) ? json_encode($input['evidence']) : null;

        if (!$reported_by) {
            $utils->sendError('cashier_id is required', 400);
        }

        // Create record
        $stmt = $db->prepare("INSERT INTO incidents (reported_by, amount, reason, evidence, status, created_at) VALUES (?, ?, ?, ?, 'reported', NOW())");
        $ok = $stmt->execute([$reported_by, $amount, $reason, $evidence]);

        if ($ok) {
            $incident_id = $db->lastInsertId();
            // Log activity
            $log = $db->prepare("INSERT INTO activities (user_id, action, description, module, ip_address, user_agent, created_at) VALUES (?, ?, ?, 'incidents', ?, ?, NOW())");
            $log->execute([$reported_by, 'incident_reported', "Incident #{$incident_id} reported by cashier {$reported_by} for {$amount}₾", $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            $utils->sendSuccess(['incident_id' => $incident_id], 'Incident reported');
        } else {
            $utils->sendError('Failed to create incident', 500);
        }
        exit();
    }

    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $incident_id = intval($input['incident_id'] ?? 0);
        $action = $input['action'] ?? '';
        $note = trim($input['note'] ?? '');

        if (!$incident_id || !$action) {
            $utils->sendError('incident_id and action are required', 400);
        }

        $user = $utils->authenticateRequest();
        if (!$user) {
            $utils->sendError('Authentication required', 401);
        }

        // Load incident
        $stmt = $db->prepare("SELECT * FROM incidents WHERE id = ?");
        $stmt->execute([$incident_id]);
        $incident = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$incident) $utils->sendError('Incident not found', 404);

        // Define allowed transitions and required roles
        $transitions = [
            'notify' => ['allowed_roles' => ['manager','admin'], 'next_status' => 'notified'],
            'confirm' => ['allowed_roles' => ['monitor','manager','admin'], 'next_status' => 'confirmed'],
            'pay' => ['allowed_roles' => ['hr','admin'], 'next_status' => 'paid']
        ];

        if (!isset($transitions[$action])) {
            $utils->sendError('Unknown action', 400);
        }

        // Check role
        $role = $user['role'] ?? 'user';
        if (!in_array($role, $transitions[$action]['allowed_roles'])) {
            $utils->sendError('Insufficient permissions for this action', 403);
        }

        $nextStatus = $transitions[$action]['next_status'];

        // Perform update and record timestamps for traceability
        $fields = ["status = ?"];
        $params = [$nextStatus];

        if ($action === 'notify') {
            $fields[] = "notified_by = ?";
            $params[] = $user['id'];
            $fields[] = "notified_at = NOW()";
        } elseif ($action === 'confirm') {
            $fields[] = "confirmed_by = ?";
            $params[] = $user['id'];
            $fields[] = "confirmed_at = NOW()";
        } elseif ($action === 'pay') {
            $fields[] = "paid_by = ?";
            $params[] = $user['id'];
            $fields[] = "paid_at = NOW()";
            // Record HR payout amount (5₾ bonus)
            $fields[] = "paid_amount = ?";
            $params[] = floatval($incident['amount']);
        }

        $params[] = $incident_id;
        $sql = "UPDATE incidents SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $ok = $stmt->execute($params);

        if ($ok) {
            // Log activity
            $desc = sprintf("Incident #%d %s by user %s. Note: %s", $incident_id, $nextStatus, $user['id'], $note);
            $log = $db->prepare("INSERT INTO activities (user_id, action, description, module, ip_address, user_agent, created_at) VALUES (?, ?, ?, 'incidents', ?, ?, NOW())");
            $log->execute([$user['id'], "incident_{$nextStatus}", $desc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

            // If paid, optionally trigger HR transfer logic (simulated)
            if ($nextStatus === 'paid') {
                // In production integrate with payroll; here we record HR task
                $taskStmt = $db->prepare("INSERT INTO hr_tasks (incident_id, amount, assigned_to, status, created_at) VALUES (?, ?, NULL, 'pending', NOW())");
                try { $taskStmt->execute([$incident_id, floatval($incident['amount'])]); } catch (Exception $e) { /* ignore if HR table missing */ }
            }

            $utils->sendSuccess(['incident_id' => $incident_id, 'status' => $nextStatus], "Incident updated to {$nextStatus}");
        } else {
            $utils->sendError('Failed to update incident', 500);
        }
        exit();
    }

    // unsupported
    $utils->sendError('Method not allowed', 405);
} catch (Exception $e) {
    error_log('Incidents API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
    exit();
}
?>
