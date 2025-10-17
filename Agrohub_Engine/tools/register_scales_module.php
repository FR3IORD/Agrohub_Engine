<?php
/**
 * Register the Scales module in the modules table if it doesn't exist.
 * Run from project root:
 *   php tools\register_scales_module.php
 *
 * Requires DB credentials defined in api/config.php
 */

require_once __DIR__ . '/../api/config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo "DB connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$technicalName = 'scales';
$stmt = $pdo->prepare("SELECT id FROM modules WHERE technical_name = ? LIMIT 1");
$stmt->execute([$technicalName]);
if ($stmt->fetch()) {
    echo "Module 'scales' already registered.\n";
    exit(0);
}

// Minimal module record
$module = [
    'name' => 'Scales',
    'technical_name' => $technicalName,
    'category' => 'Website',
    'description' => 'Simple scales/weight entry application',
    'icon' => 'fas fa-weight-hanging',
    'color' => 'bg-blue-500',
    'version' => '1.0.0',
    'dependencies' => json_encode([]),
    'features' => json_encode([]),
    'price' => 0
];

$sql = "INSERT INTO modules (name, technical_name, category, description, icon, color, version, dependencies, features, price, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
$stmt = $pdo->prepare($sql);
if ($stmt->execute(array_values($module))) {
    echo "Scales module registered successfully.\n";
} else {
    echo "Failed to register Scales module.\n";
}
