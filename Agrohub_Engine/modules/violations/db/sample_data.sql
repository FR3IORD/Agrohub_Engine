-- =====================================================
-- Violations Module - Sample Data
-- Optional: For testing purposes only
-- =====================================================

-- Sample Violations (using existing users and branches)
INSERT INTO `violations` (
    `title`, `description`, `reported_by`, `branch_id`, 
    `violation_type_id`, `violation_date`, `severity`, `status`, `status_id`
) VALUES
(
    'Late Arrival Without Notice',
    'Employee arrived 45 minutes late without prior notification or valid reason.',
    1, 1, 2, DATE_SUB(NOW(), INTERVAL 5 DAY), 'low', 'pending', 1
),
(
    'Safety Equipment Not Used',
    'Employee was observed not wearing required safety equipment in the warehouse area.',
    1, 2, 1, DATE_SUB(NOW(), INTERVAL 3 DAY), 'high', 'in_progress', 2
),
(
    'Unprofessional Behavior',
    'Multiple complaints about unprofessional conduct and inappropriate language in the workplace.',
    1, 1, 12, DATE_SUB(NOW(), INTERVAL 7 DAY), 'medium', 'resolved', 3
),
(
    'Unauthorized Absence',
    'Employee was absent for 2 days without informing management or providing documentation.',
    1, 3, 2, DATE_SUB(NOW(), INTERVAL 10 DAY), 'medium', 'closed', 4
),
(
    'Data Security Breach',
    'Confidential customer information was shared with unauthorized personnel.',
    1, 1, 7, DATE_SUB(NOW(), INTERVAL 2 DAY), 'critical', 'in_progress', 2
);

SELECT '✅ Sample data inserted successfully!' as Status;
SELECT 'Note: This is sample data for testing purposes only.' as Notice;