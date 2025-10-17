<?php
/**
 * Violations Module - Edit Violation
 * Similar to create.php but with pre-filled data
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

$violationId = intval($_GET['id'] ?? 0);

if (!$violationId) {
    header('Location: index.php');
    exit();
}

$violation = violationsGetById($violationId);

if (!$violation) {
    die('Violation not found');
}

$permissions = violationsGetUserPermissions($user['id'], $user['role']);

$canEdit = $permissions['can_edit_all'] ||
           ($permissions['can_edit_own'] && $violation['reported_by'] == $user['id']);

if (!$canEdit) {
    http_response_code(403);
    die('Permission denied');
}

echo "<!-- Edit form will be similar to create.php but with pre-filled values -->";
echo "<!-- Coming soon... -->";
header('Location: report.php?id=' . $violationId);
exit();