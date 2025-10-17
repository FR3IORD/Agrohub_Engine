<?php
/**
 * Create Scales schema in agrohub_erp database.
 * Usage: php tools\create_scales_schema.php
 *
 * This is idempotent: it will create tables only if they do not exist.
 * Requires DB constants in api/config.php
 */

require_once __DIR__ . '/../api/config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo "DB connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Create scales_readings table
$createReadings = <<<SQL
CREATE TABLE IF NOT EXISTS `scales_readings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `weight` DECIMAL(10,3) NOT NULL,
  `unit` VARCHAR(10) NOT NULL DEFAULT 'kg',
  `meta` JSON NULL,
  `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_scales_user` (`user_id`),
  INDEX `idx_scales_recorded_at` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

// Optional settings table for the app
$createSettings = <<<SQL
CREATE TABLE IF NOT EXISTS `scales_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_name` VARCHAR(191) NOT NULL,
  `value` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scales_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

try {
    echo "Creating or verifying scales_readings table...\n";
    $pdo->exec($createReadings);
    echo "scales_readings ready.\n";

    echo "Creating or verifying scales_settings table...\n";
    $pdo->exec($createSettings);
    echo "scales_settings ready.\n";

    echo "All Scales schema tasks completed.\n";
} catch (Exception $e) {
    echo "Failed to create Scales schema: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

exit(0);
