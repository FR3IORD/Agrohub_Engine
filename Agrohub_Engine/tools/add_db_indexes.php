<?php
/**
 * Safe DB index migration script for Agrohub_Engine
 * Usage (from project root):
 *   php tools\add_db_indexes.php
 *
 * This script uses the DB constants from api/config.php and will check
 * for the presence of recommended indexes before creating them.
 * Make a DB backup first (see README_SCALE.md).
 */

require_once __DIR__ . '/../api/config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo "Failed to connect to DB: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

function indexExists(PDO $pdo, $table, $indexName) {
    $stmt = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
    $stmt->execute([$indexName]);
    return ($stmt->rowCount() > 0);
}

function createIndex(PDO $pdo, $table, $indexName, $cols) {
    $colsSql = implode(', ', array_map(function($c){ return "`$c`"; }, $cols));
    $sql = "ALTER TABLE `$table` ADD INDEX `$indexName` ($colsSql)";
    echo "Creating index $indexName on $table($colsSql)...\n";
    $pdo->exec($sql);
    echo "Done.\n";
}

$tasks = [
    ['table' => 'users', 'name' => 'idx_users_email', 'cols' => ['email']],
    ['table' => 'users', 'name' => 'idx_users_username', 'cols' => ['username']],
    ['table' => 'users', 'name' => 'idx_users_phplogin_id', 'cols' => ['phplogin_id']],
];

foreach ($tasks as $t) {
    try {
        if (indexExists($pdo, $t['table'], $t['name'])) {
            echo "Index {$t['name']} already exists on {$t['table']}, skipping.\n";
            continue;
        }
        createIndex($pdo, $t['table'], $t['name'], $t['cols']);
    } catch (Exception $e) {
        echo "Failed to create index {$t['name']} on {$t['table']}: " . $e->getMessage() . PHP_EOL;
    }
}

echo "All tasks completed.\n";
