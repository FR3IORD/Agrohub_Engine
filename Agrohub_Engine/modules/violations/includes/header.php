<?php
/**
 * Violations Module - Header Template
 */

if (!defined('VIOLATIONS_MODULE')) {
    die('Direct access not permitted');
}

$pageTitle = $pageTitle ?? 'Violations Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Agrohub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/violations.css">
</head>
<body>