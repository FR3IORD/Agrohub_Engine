<?php
/**
 * Scales Module - simple launcher
 */

define('SCALES_MODULE', true);

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/database.php';
require_once __DIR__ . '/../../api/utils.php';

$utils = new Utils();
$user = $utils->authenticateRequest();

if (!$user) {
    // Redirect guests to main site
    header('Location: ../../index.html');
    exit();
}

// Basic access control: allow all authenticated users for now
// If more granular permissions are required, implement here.

// Forward to the app frontend under apps/scales
$target = '../../apps/scales/index.html';

// Attempt to serve the app within the module context
if (file_exists(__DIR__ . '/../../apps/scales/index.html')) {
    header('Location: ' . $target);
    exit();
}

// Fallback: show a simple page
?><!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Scales Module</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body{font-family:Inter,Arial,Helvetica,sans-serif;background:#f9fafb;color:#111;padding:2rem}</style>
</head>
<body>
    <div style="max-width:800px;margin:0 auto;background:#fff;padding:2rem;border-radius:8px;box-shadow:0 8px 20px rgba(0,0,0,0.05);">
        <h1 style="margin:0 0 1rem 0">Scales</h1>
        <p>This module launches the Scales app. <a href="../../apps/scales/index.html">Open Scales app</a></p>
        <p><a href="../../index.html">← Back to dashboard</a></p>
    </div>
</body>
</html>
