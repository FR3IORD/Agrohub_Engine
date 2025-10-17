-- Create applications table if it doesn't exist
CREATE TABLE IF NOT EXISTS `applications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `technical_name` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `icon` varchar(50) DEFAULT 'fas fa-cube',
  `color` varchar(100) DEFAULT 'from-purple-400 to-purple-600',
  `category` varchar(50) DEFAULT 'Uncategorized',
  `version` varchar(20) DEFAULT '1.0',
  `status` enum('active','inactive','pending','error') DEFAULT 'inactive',
  `installed_at` datetime DEFAULT NULL,
  `installed_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_technical_name` (`technical_name`),
  KEY `idx_app_category` (`category`), -- Added index for faster searches
  KEY `idx_app_status` (`status`) -- Added index for status filtering
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create simple app permissions table (with ON DELETE CASCADE to prevent orphaned records)
CREATE TABLE IF NOT EXISTS `app_user_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `app_id` varchar(50) NOT NULL,
  `can_access` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `created_by` int DEFAULT NULL, -- Added for audit trail
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_app` (`user_id`,`app_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_app_id` (`app_id`),
  CONSTRAINT `fk_app_permissions_app` FOREIGN KEY (`app_id`) 
    REFERENCES `applications` (`technical_name`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create modern permissions table with proper error handling for module deletion
CREATE TABLE IF NOT EXISTS `user_app_access` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `module_id` int NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `granted_by` int DEFAULT NULL,
  `granted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_app` (`user_id`,`module_id`),
  KEY `module_id` (`module_id`),
  KEY `granted_by` (`granted_by`),
  KEY `idx_expires_at` (`expires_at`) -- Added index for expiration queries
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add these if your users/modules tables already exist (otherwise comment them out)
-- ALTER TABLE `user_app_access` ADD CONSTRAINT `user_app_access_user_fk` 
--   FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
-- ALTER TABLE `user_app_access` ADD CONSTRAINT `user_app_access_module_fk` 
--   FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;
-- ALTER TABLE `user_app_access` ADD CONSTRAINT `user_app_access_granted_fk` 
--   FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Create detailed permissions table with search optimizations
CREATE TABLE IF NOT EXISTS `user_app_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `module_id` int NOT NULL,
  `can_view` tinyint(1) DEFAULT 1,
  `can_create` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `can_export` tinyint(1) DEFAULT 0,
  `can_import` tinyint(1) DEFAULT 0,
  `custom_permissions` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_module` (`user_id`,`module_id`),
  KEY `module_id` (`module_id`),
  KEY `idx_combined_perms` (`can_view`,`can_edit`,`can_delete`) -- Compound index for permission checks
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add these if your users/modules tables already exist (otherwise comment them out)
-- ALTER TABLE `user_app_permissions` ADD CONSTRAINT `user_permissions_user_fk` 
--   FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
-- ALTER TABLE `user_app_permissions` ADD CONSTRAINT `user_permissions_module_fk` 
--   FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

-- Create activity log table with better search capabilities
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL, -- Added for better filtering (e.g., 'user', 'module')
  `target_id` int DEFAULT NULL, -- Added to track which record was affected
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `log_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_log_time` (`log_time`),
  KEY `idx_target` (`target_type`,`target_id`) -- Compound index for searching by affected entity
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default applications if not exist
INSERT IGNORE INTO `applications` 
(`technical_name`, `name`, `description`, `icon`, `color`, `category`, `status`) VALUES
('violations', 'Violations Management', 'Track and manage violations across the organization', 'fas fa-exclamation-triangle', 'from-red-400 to-red-600', 'Compliance', 'active'),
('inventory', 'Inventory Management', 'Track inventory across warehouses and locations', 'fas fa-boxes', 'from-blue-400 to-blue-600', 'SupplyChain', 'active'),
('hr', 'Human Resources', 'Manage employees, attendance, and HR processes', 'fas fa-users', 'from-green-400 to-green-600', 'HumanResources', 'active'),
('finance', 'Finance Management', 'Track financial transactions and reports', 'fas fa-dollar-sign', 'from-yellow-400 to-yellow-600', 'Finance', 'active'),
('sales', 'Sales Management', 'Manage sales orders, customers, and opportunities', 'fas fa-shopping-cart', 'from-indigo-400 to-indigo-600', 'Sales', 'active'),
('website', 'Website Builder', 'Create and manage your company website', 'fas fa-globe', 'from-pink-400 to-pink-600', 'Website', 'active');

-- Role-based permissions table with better structure
CREATE TABLE IF NOT EXISTS `violation_role_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role` varchar(50) NOT NULL,
  `permission` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permission` (`role`,`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Safe insert with error handling for admin permissions (won't fail if users table doesn't exist)
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS add_admin_app_permissions()
BEGIN
    DECLARE userTableExists INT;
    
    -- Check if users table exists
    SELECT COUNT(1) INTO userTableExists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'users';
    
    IF userTableExists > 0 THEN
        -- Insert default admin access to all applications
        INSERT IGNORE INTO `app_user_permissions` (`user_id`, `app_id`, `can_access`)
        SELECT 
            u.id, 
            a.technical_name, 
            1
        FROM 
            users u 
        CROSS JOIN 
            applications a
        WHERE 
            u.role = 'admin'
            AND NOT EXISTS (
                SELECT 1 
                FROM app_user_permissions p 
                WHERE p.user_id = u.id 
                AND p.app_id = a.technical_name
            );
    END IF;
END //
DELIMITER ;

-- Execute the procedure to add admin permissions safely
CALL add_admin_app_permissions();
DROP PROCEDURE IF EXISTS add_admin_app_permissions;

-- Procedure for module permissions (will only run if modules table exists)
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS add_admin_module_permissions()
BEGIN
    DECLARE userTableExists INT;
    DECLARE moduleTableExists INT;
    
    -- Check if required tables exist
    SELECT COUNT(1) INTO userTableExists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'users';
    
    SELECT COUNT(1) INTO moduleTableExists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'modules';
    
    IF userTableExists > 0 AND moduleTableExists > 0 THEN
        -- Insert admin module permissions
        INSERT IGNORE INTO `user_app_permissions` 
        (`user_id`, `module_id`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_export`, `can_import`)
        SELECT 
            u.id, m.id, 1, 1, 1, 1, 1, 1
        FROM 
            users u 
        CROSS JOIN 
            modules m
        WHERE 
            u.role = 'admin'
            AND NOT EXISTS (
                SELECT 1 
                FROM user_app_permissions p 
                WHERE p.user_id = u.id 
                AND p.module_id = m.id
            );
            
        -- Also update user_app_access for admins
        INSERT IGNORE INTO `user_app_access` 
        (`user_id`, `module_id`, `is_enabled`, `granted_by`, `granted_at`)
        SELECT 
            u.id, m.id, 1, 
            (SELECT MIN(id) FROM users WHERE role = 'admin'), -- Use first admin as granter
            NOW()
        FROM 
            users u 
        CROSS JOIN 
            modules m
        WHERE 
            u.role = 'admin'
            AND NOT EXISTS (
                SELECT 1 
                FROM user_app_access a 
                WHERE a.user_id = u.id 
                AND a.module_id = m.id
            );
    END IF;
END //
DELIMITER ;

-- Execute the procedure to add admin module permissions safely
CALL add_admin_module_permissions();
DROP PROCEDURE IF EXISTS add_admin_module_permissions;
