-- RACIC CHURCH MANAGEMENT SYSTEM
-- Database: racic_church_management
-- Royal Ambassadors of Christ International Church
-- Clean Master Database Script with Single Super Admin Account

CREATE DATABASE IF NOT EXISTS `racic_church_management` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `racic_church_management`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `media`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `budgets`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `income`;
DROP TABLE IF EXISTS `attendance_details`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `activity_reports`;
DROP TABLE IF EXISTS `activity_assignments`;
DROP TABLE IF EXISTS `activities`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `visitor_followups`;
DROP TABLE IF EXISTS `visitors`;
DROP TABLE IF EXISTS `department_members`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `members`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. ROLES
CREATE TABLE `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `display_name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `name`, `display_name`, `description`) VALUES
(1, 'super_admin', 'Super Administrator', 'Full system access and master configuration'),
(2, 'head_pastor', 'Head Pastor', 'Oversees all church activities, departments, members, spiritual & financial health'),
(3, 'church_admin', 'Church Administrator', 'Manages general church records, members, visitors, and logistics'),
(4, 'dept_leader', 'Department Leader', 'Manages specific assigned department, members, activities and reports'),
(5, 'finance_officer', 'Finance Officer', 'Manages income, expenditures, budgets, and financial accounting reports'),
(6, 'church_worker', 'Church Worker', 'Executes assigned duties, views calendar, and submits program updates');

-- 2. USERS
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT NOT NULL,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `department_id` INT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(120) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `avatar` VARCHAR(255) NULL,
  `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. PERMISSIONS
CREATE TABLE `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(80) NOT NULL UNIQUE,
  `name` VARCHAR(120) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`code`, `name`, `category`, `description`) VALUES
('users_manage', 'Manage Users & Permissions', 'Administration', 'Create, edit, deactivate system users'),
('settings_manage', 'Manage System Settings', 'Administration', 'Update church information and system configurations'),
('members_view', 'View Members', 'Members', 'View church membership directory and profiles'),
('members_create', 'Create Members', 'Members', 'Register new church members'),
('members_edit', 'Edit Members', 'Members', 'Modify member personal and spiritual details'),
('members_delete', 'Delete Members', 'Members', 'Remove member records'),
('visitors_manage', 'Manage Visitors & Follow-ups', 'Visitors', 'Record visitors, track visits and convert to members'),
('departments_manage', 'Manage Departments', 'Departments', 'Create and manage church departments and leadership'),
('activities_manage', 'Manage Activities', 'Activities', 'Create, schedule, assign church programs and activities'),
('activities_report', 'Submit Activity Reports', 'Activities', 'File post-event evaluation reports and media'),
('attendance_manage', 'Manage Attendance', 'Attendance', 'Record service and meeting headcounts and roll call'),
('finance_view', 'View Financial Records', 'Finance', 'View income, expenditure and ledger summaries'),
('finance_manage', 'Manage Financial Transactions', 'Finance', 'Post income, disburse expenses and manage budgets'),
('reports_view', 'Generate & Export Reports', 'Reports', 'Access system analytical and exportable PDF/Excel reports'),
('announcements_manage', 'Publish Announcements', 'Announcements', 'Broadcast church-wide and departmental notices'),
('media_manage', 'Manage Media Library', 'Media', 'Upload and organize media, documents and archives'),
('audit_view', 'View Audit Logs', 'Audit', 'Review security and user activity logs');

-- 4. ROLE_PERMISSIONS
CREATE TABLE `role_permissions` (
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Super Admin gets all permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Head Pastor permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `code` IN (
  'members_view', 'visitors_manage', 'departments_manage', 'activities_manage', 
  'activities_report', 'attendance_manage', 'finance_view', 'reports_view', 
  'announcements_manage', 'media_manage', 'audit_view'
);

-- Church Administrator permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions` WHERE `code` IN (
  'members_view', 'members_create', 'members_edit', 'visitors_manage', 
  'departments_manage', 'activities_manage', 'attendance_manage', 
  'reports_view', 'announcements_manage', 'media_manage'
);

-- Department Leader permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, `id` FROM `permissions` WHERE `code` IN (
  'members_view', 'activities_manage', 'activities_report', 'attendance_manage', 
  'announcements_manage', 'media_manage'
);

-- Finance Officer permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, `id` FROM `permissions` WHERE `code` IN (
  'finance_view', 'finance_manage', 'reports_view'
);

-- Church Worker permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, `id` FROM `permissions` WHERE `code` IN (
  'members_view', 'activities_report', 'attendance_manage'
);

-- 5. DEPARTMENTS
CREATE TABLE `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `leader_id` INT NULL,
  `meeting_schedule` VARCHAR(255) NULL,
  `budget_allocation` DECIMAL(12,2) DEFAULT 0.00,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`leader_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. MEMBERS
CREATE TABLE `members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_code` VARCHAR(30) NOT NULL UNIQUE,
  `first_name` VARCHAR(60) NOT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `gender` ENUM('Male', 'Female') NOT NULL,
  `date_of_birth` DATE NULL,
  `marital_status` ENUM('Single', 'Married', 'Divorced', 'Widowed') DEFAULT 'Single',
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(120) NULL,
  `address` TEXT NULL,
  `occupation` VARCHAR(100) NULL,
  `baptism_status` ENUM('Baptized', 'Not Baptized', 'In Training') DEFAULT 'Not Baptized',
  `baptism_date` DATE NULL,
  `membership_date` DATE NOT NULL,
  `membership_status` ENUM('Active', 'Inactive', 'Transferred', 'Deceased') DEFAULT 'Active',
  `department_id` INT NULL,
  `photo` VARCHAR(255) NULL,
  `emergency_contact_name` VARCHAR(100) NULL,
  `emergency_contact_phone` VARCHAR(30) NULL,
  `notes` TEXT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. DEPARTMENT_MEMBERS
CREATE TABLE `department_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_id` INT NOT NULL,
  `member_id` INT NOT NULL,
  `role_in_dept` VARCHAR(60) DEFAULT 'Member',
  `joined_date` DATE NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_dept_member` (`department_id`, `member_id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. VISITORS
CREATE TABLE `visitors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `visitor_code` VARCHAR(30) NOT NULL UNIQUE,
  `first_name` VARCHAR(60) NOT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `gender` ENUM('Male', 'Female') NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(120) NULL,
  `address` TEXT NULL,
  `visit_date` DATE NOT NULL,
  `invited_by_member_id` INT NULL,
  `invited_by_name` VARCHAR(100) NULL,
  `prayer_requests` TEXT NULL,
  `follow_up_status` ENUM('New', 'Contacted', 'Visited', 'Prayed With', 'Converted', 'Not Interested') DEFAULT 'New',
  `assigned_to_user_id` INT NULL,
  `notes` TEXT NULL,
  `converted_to_member_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`invited_by_member_id`) REFERENCES `members`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`converted_to_member_id`) REFERENCES `members`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. VISITOR_FOLLOWUPS
CREATE TABLE `visitor_followups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `visitor_id` INT NOT NULL,
  `follow_up_date` DATE NOT NULL,
  `contact_type` ENUM('Phone Call', 'SMS/WhatsApp', 'Home Visit', 'Personal Meeting', 'Email') NOT NULL,
  `notes` TEXT NOT NULL,
  `next_action_date` DATE NULL,
  `conducted_by_user_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`visitor_id`) REFERENCES `visitors`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`conducted_by_user_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. SERVICES
CREATE TABLE `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `service_name` VARCHAR(120) NOT NULL,
  `service_type` ENUM('Sunday Service', 'Midweek Service', 'Prayer Meeting', 'All-Night Service', 'Evangelism', 'Departmental Meeting', 'Conference', 'Special Program') NOT NULL,
  `service_date` DATE NOT NULL,
  `service_time` TIME NOT NULL,
  `department_id` INT NULL,
  `preacher` VARCHAR(100) NULL,
  `theme` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. ACTIVITIES
CREATE TABLE `activities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `activity_code` VARCHAR(30) NOT NULL UNIQUE,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `department_id` INT NOT NULL,
  `activity_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `venue` VARCHAR(150) NOT NULL,
  `expected_attendance` INT DEFAULT 0,
  `budget` DECIMAL(12,2) DEFAULT 0.00,
  `status` ENUM('Upcoming', 'In Progress', 'Completed', 'Postponed', 'Cancelled') DEFAULT 'Upcoming',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON UPDATE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. ACTIVITY_ASSIGNMENTS
CREATE TABLE `activity_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `activity_id` INT NOT NULL,
  `user_id` INT NULL,
  `member_id` INT NULL,
  `role_description` VARCHAR(100) NOT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. ACTIVITY_REPORTS
CREATE TABLE `activity_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `activity_id` INT NOT NULL UNIQUE,
  `actual_attendance` INT NOT NULL DEFAULT 0,
  `achievements` TEXT NOT NULL,
  `challenges` TEXT NULL,
  `expenditure` DECIMAL(12,2) DEFAULT 0.00,
  `recommendations` TEXT NULL,
  `pictures_json` LONGTEXT NULL,
  `documents_json` LONGTEXT NULL,
  `status` ENUM('Draft', 'Submitted', 'Approved', 'Needs Revision') DEFAULT 'Submitted',
  `submitted_by_user_id` INT NOT NULL,
  `reviewed_by_user_id` INT NULL,
  `review_notes` TEXT NULL,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME NULL,
  FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`submitted_by_user_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE,
  FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. ATTENDANCE
CREATE TABLE `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `service_id` INT NULL,
  `activity_id` INT NULL,
  `department_id` INT NULL,
  `attendance_date` DATE NOT NULL,
  `men_count` INT NOT NULL DEFAULT 0,
  `women_count` INT NOT NULL DEFAULT 0,
  `children_count` INT NOT NULL DEFAULT 0,
  `total_count` INT NOT NULL DEFAULT 0,
  `recorded_by_user_id` INT NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. ATTENDANCE_DETAILS
CREATE TABLE `attendance_details` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `attendance_id` INT NOT NULL,
  `member_id` INT NOT NULL,
  `check_in_time` TIME NULL,
  `status` ENUM('Present', 'Absent', 'Excused') DEFAULT 'Present',
  FOREIGN KEY (`attendance_id`) REFERENCES `attendance`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. INCOME
CREATE TABLE `income` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_code` VARCHAR(30) NOT NULL UNIQUE,
  `category` ENUM('Tithes', 'Offerings', 'Donations', 'Fundraising', 'Special Contributions', 'Other') NOT NULL,
  `member_id` INT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` ENUM('Cash', 'Bank Transfer', 'Mobile Money', 'Cheque', 'Card') NOT NULL,
  `payment_reference` VARCHAR(100) NULL,
  `transaction_date` DATE NOT NULL,
  `receipt_file` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `recorded_by_user_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. EXPENSES
CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_code` VARCHAR(30) NOT NULL UNIQUE,
  `category` ENUM('Church Programs', 'Welfare', 'Utilities', 'Maintenance', 'Departmental Expenses', 'Salaries & Honorariums', 'Other') NOT NULL,
  `department_id` INT NULL,
  `activity_id` INT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` ENUM('Cash', 'Bank Transfer', 'Mobile Money', 'Cheque') NOT NULL,
  `payee` VARCHAR(150) NOT NULL,
  `transaction_date` DATE NOT NULL,
  `receipt_file` VARCHAR(255) NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Approved',
  `approved_by_user_id` INT NULL,
  `notes` TEXT NULL,
  `recorded_by_user_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. BUDGETS
CREATE TABLE `budgets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_id` INT NOT NULL,
  `fiscal_year` INT NOT NULL,
  `title` VARCHAR(120) NOT NULL,
  `allocated_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON UPDATE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. EVENTS
CREATE TABLE `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `event_type` ENUM('Church Service', 'Conference', 'Crusade', 'Evangelism', 'Departmental Activity', 'Special Program', 'Meeting') NOT NULL,
  `department_id` INT NULL,
  `start_datetime` DATETIME NOT NULL,
  `end_datetime` DATETIME NOT NULL,
  `venue` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `color` VARCHAR(20) DEFAULT '#0d6efd',
  `all_day` TINYINT(1) DEFAULT 0,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. ANNOUNCEMENTS
CREATE TABLE `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `content` TEXT NOT NULL,
  `target_audience` ENUM('Entire Church', 'Specific Department', 'Church Workers', 'Department Leaders') NOT NULL,
  `department_id` INT NULL,
  `priority` ENUM('Normal', 'Important', 'Urgent') DEFAULT 'Normal',
  `publish_date` DATE NOT NULL,
  `expiry_date` DATE NULL,
  `status` ENUM('Active', 'Archived') DEFAULT 'Active',
  `created_by_user_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. MEDIA
CREATE TABLE `media` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_type` VARCHAR(50) NOT NULL,
  `file_size` BIGINT NOT NULL,
  `category` ENUM('Picture', 'Video', 'Flyer', 'Church Document', 'Activity Report File', 'Other') NOT NULL,
  `department_id` INT NULL,
  `activity_id` INT NULL,
  `uploaded_by_user_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. NOTIFICATIONS
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. AUDIT_LOGS
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(60) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `record_id` INT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- SINGLE SUPER ADMIN USER
-- Username: RACIC
-- Password: AMBASSADORS
-- Hash: $2y$10$SUA3gXeaVEmNwCLnnPAtsOPYUztx5mFbIPiAIFi2QYxvODnrllkMO
-- No other data exists until keyed in by the Super Admin
-- -------------------------------------------------------------

INSERT INTO `users` (`id`, `role_id`, `username`, `department_id`, `full_name`, `email`, `password`, `phone`, `status`, `last_login`) VALUES
(1, 1, 'RACIC', NULL, 'Super Administrator', 'superadmin@racic.org', '$2y$10$SUA3gXeaVEmNwCLnnPAtsOPYUztx5mFbIPiAIFi2QYxvODnrllkMO', '+233 24 000 0000', 'active', NULL);
