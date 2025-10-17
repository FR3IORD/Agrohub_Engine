-- =====================================================
-- Violations Module - Database Schema
-- =====================================================

-- Violation Types Table
CREATE TABLE IF NOT EXISTS `violation_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Violation Statuses Table
CREATE TABLE IF NOT EXISTS `violation_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `color` varchar(20) DEFAULT '#6b7280',
  `icon` varchar(50) DEFAULT 'fa-circle',
  `sort_order` int DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Main Violations Table
CREATE TABLE IF NOT EXISTS `violations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `reported_by` int NOT NULL,
  `branch_id` int NOT NULL,
  `violation_type_id` int NOT NULL,
  `violation_date` date NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` varchar(50) DEFAULT 'pending',
  `status_id` int DEFAULT 1,
  `assigned_to` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `reported_by` (`reported_by`),
  KEY `branch_id` (`branch_id`),
  KEY `violation_type_id` (`violation_type_id`),
  KEY `status_id` (`status_id`),
  KEY `violation_date` (`violation_date`),
  CONSTRAINT `violations_user_fk` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `violations_branch_fk` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `violations_type_fk` FOREIGN KEY (`violation_type_id`) REFERENCES `violation_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `violations_status_fk` FOREIGN KEY (`status_id`) REFERENCES `violation_statuses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Violation Photos Table
CREATE TABLE IF NOT EXISTS `violation_photos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `violation_id` int NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(500) NOT NULL,
  `filesize` bigint DEFAULT NULL,
  `uploaded_by` int NOT NULL,
  `uploaded_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `violation_id` (`violation_id`),
  CONSTRAINT `photos_violation_fk` FOREIGN KEY (`violation_id`) REFERENCES `violations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Violation Sanctions Table
CREATE TABLE IF NOT EXISTS `violation_sanctions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `violation_id` int NOT NULL,
  `sanction_type` varchar(100) NOT NULL,
  `description` text,
  `applied_by` int NOT NULL,
  `applied_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `violation_id` (`violation_id`),
  CONSTRAINT `sanctions_violation_fk` FOREIGN KEY (`violation_id`) REFERENCES `violations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- User Permissions Table
CREATE TABLE IF NOT EXISTS `violation_user_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `can_view_all` tinyint(1) DEFAULT 0,
  `can_view_own` tinyint(1) DEFAULT 1,
  `can_view_branch` tinyint(1) DEFAULT 0,
  `can_create` tinyint(1) DEFAULT 0,
  `can_edit_own` tinyint(1) DEFAULT 0,
  `can_edit_all` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `can_apply_sanctions` tinyint(1) DEFAULT 0,
  `can_reject` tinyint(1) DEFAULT 0,
  `can_view_sanctions` tinyint(1) DEFAULT 0,
  `can_view_photos` tinyint(1) DEFAULT 1,
  `can_export` tinyint(1) DEFAULT 0,
  `can_view_analytics` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `violation_user_perms_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs Table
CREATE TABLE IF NOT EXISTS `violation_activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `violation_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `violation_id` (`violation_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Branch Settings Table
CREATE TABLE IF NOT EXISTS `violation_branch_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `auto_assign` tinyint(1) DEFAULT 0,
  `assigned_to` int DEFAULT NULL,
  `notification_email` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_id` (`branch_id`),
  CONSTRAINT `branch_settings_fk` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert Default Statuses
INSERT INTO `violation_statuses` (`id`, `name`, `color`, `icon`, `sort_order`) VALUES
(1, 'Pending', '#ef4444', 'fa-clock', 1),
(2, 'In Progress', '#f59e0b', 'fa-spinner', 2),
(3, 'Resolved', '#10b981', 'fa-check-circle', 3),
(4, 'Closed', '#6b7280', 'fa-times-circle', 4),
(5, 'Rejected', '#ef4444', 'fa-ban', 5)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Insert Sample Violation Types
INSERT INTO `violation_types` (`name`, `description`, `severity`, `is_active`) VALUES
('Safety Violation', 'Workplace safety rules violation', 'high', 1),
('Attendance Issue', 'Late arrival or absence without notice', 'low', 1),
('Dress Code', 'Violation of company dress code policy', 'low', 1),
('Harassment', 'Any form of harassment or discrimination', 'critical', 1),
('Policy Breach', 'Violation of company policies', 'medium', 1),
('Equipment Misuse', 'Improper use of company equipment', 'medium', 1),
('Data Security', 'Security or confidentiality breach', 'critical', 1),
('Insubordination', 'Refusal to follow instructions', 'high', 1),
('Theft', 'Theft or unauthorized removal of property', 'critical', 1),
('Violence', 'Physical violence or threats', 'critical', 1),
('Substance Abuse', 'Alcohol or drug use at workplace', 'critical', 1),
('Misconduct', 'General workplace misconduct', 'medium', 1),
('Negligence', 'Careless or negligent behavior', 'medium', 1),
('Conflict of Interest', 'Personal interest conflicts with company', 'high', 1),
('Fraud', 'Fraudulent activity or deception', 'critical', 1),
('Other', 'Other violations not listed', 'medium', 1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Grant admin full permissions
INSERT INTO `violation_user_permissions` (
    `user_id`, `can_view_all`, `can_view_own`, `can_view_branch`, 
    `can_create`, `can_edit_own`, `can_edit_all`, `can_delete`, 
    `can_apply_sanctions`, `can_reject`, `can_view_sanctions`, 
    `can_view_photos`, `can_export`, `can_view_analytics`
)
SELECT 
    id, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1
FROM users 
WHERE role = 'admin'
ON DUPLICATE KEY UPDATE 
    can_view_all = 1,
    can_edit_all = 1,
    can_delete = 1,
    can_apply_sanctions = 1,
    can_view_analytics = 1;

-- Grant manager permissions
INSERT INTO `violation_user_permissions` (
    `user_id`, `can_view_all`, `can_view_own`, `can_view_branch`, 
    `can_create`, `can_edit_own`, `can_edit_all`, `can_delete`, 
    `can_apply_sanctions`, `can_reject`, `can_view_sanctions`, 
    `can_view_photos`, `can_export`, `can_view_analytics`
)
SELECT 
    id, 0, 1, 1, 1, 1, 0, 0, 1, 1, 1, 1, 1, 0
FROM users 
WHERE role = 'manager'
ON DUPLICATE KEY UPDATE 
    can_view_branch = 1,
    can_create = 1,
    can_apply_sanctions = 1,
    can_export = 1;

SELECT '✅ Violations module database schema created successfully!' as Status;