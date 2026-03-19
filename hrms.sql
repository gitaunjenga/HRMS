-- ============================================================
-- HRMS - Human Resource Management System
-- Database Schema
-- Compatible with MySQL 5.7+ / phpMyAdmin
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `hrms_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hrms_db`;

-- ============================================================
-- Table: departments
-- ============================================================
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `head_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: employees
-- ============================================================
CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `employment_type` enum('Full-Time','Part-Time','Contract','Internship') DEFAULT 'Full-Time',
  `employment_status` enum('Active','Inactive','Terminated','On Leave') DEFAULT 'Active',
  `hire_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT 0.00,
  `photo` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  UNIQUE KEY `email` (`email`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `fk_employee_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: users (Authentication)
-- ============================================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','HR Manager','Employee') NOT NULL DEFAULT 'Employee',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `fk_user_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: attendance
-- ============================================================
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `total_hours` decimal(5,2) DEFAULT NULL,
  `status` enum('Present','Absent','Late','Half Day','On Leave') DEFAULT 'Present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`employee_id`,`date`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: leave_types
-- ============================================================
CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `days_allowed` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: leave_requests
-- ============================================================
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL DEFAULT 1,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') DEFAULT 'Pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `fk_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`),
  CONSTRAINT `fk_leave_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: leave_balances
-- ============================================================
CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` year NOT NULL,
  `allocated` int(11) NOT NULL DEFAULT 0,
  `used` int(11) NOT NULL DEFAULT 0,
  `remaining` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_balance` (`employee_id`,`leave_type_id`,`year`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  CONSTRAINT `fk_balance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_balance_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: salary_structures
-- ============================================================
CREATE TABLE `salary_structures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `house_allowance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `transport_allowance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `medical_allowance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `other_allowances` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_deduction` decimal(10,2) NOT NULL DEFAULT 0.00,
  `provident_fund` decimal(10,2) NOT NULL DEFAULT 0.00,
  `insurance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `other_deductions` decimal(10,2) NOT NULL DEFAULT 0.00,
  `effective_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `fk_salary_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: payroll
-- ============================================================
CREATE TABLE `payroll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_allowances` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gross_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_deductions` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `days_worked` int(11) DEFAULT NULL,
  `days_absent` int(11) DEFAULT NULL,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `overtime_pay` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('Pending','Paid','Cancelled') DEFAULT 'Pending',
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_payroll` (`employee_id`,`month`,`year`),
  KEY `employee_id` (`employee_id`),
  KEY `processed_by` (`processed_by`),
  CONSTRAINT `fk_payroll_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_user` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: job_postings
-- ============================================================
CREATE TABLE `job_postings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `employment_type` enum('Full-Time','Part-Time','Contract','Internship') DEFAULT 'Full-Time',
  `location` varchar(100) DEFAULT NULL,
  `salary_range_min` decimal(10,2) DEFAULT NULL,
  `salary_range_max` decimal(10,2) DEFAULT NULL,
  `vacancies` int(11) DEFAULT 1,
  `deadline` date DEFAULT NULL,
  `status` enum('Open','Closed','On Hold') DEFAULT 'Open',
  `posted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `posted_by` (`posted_by`),
  CONSTRAINT `fk_job_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_job_poster` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: candidates
-- ============================================================
CREATE TABLE `candidates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `cover_letter` text DEFAULT NULL,
  `status` enum('Applied','Screening','Interview','Offer','Hired','Rejected') DEFAULT 'Applied',
  `interview_date` datetime DEFAULT NULL,
  `interview_notes` text DEFAULT NULL,
  `rating` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `job_id` (`job_id`),
  CONSTRAINT `fk_candidate_job` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: performance_reviews
-- ============================================================
CREATE TABLE `performance_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `review_period` varchar(50) NOT NULL,
  `review_date` date NOT NULL,
  `kpi_score` decimal(5,2) DEFAULT NULL,
  `communication_score` decimal(5,2) DEFAULT NULL,
  `teamwork_score` decimal(5,2) DEFAULT NULL,
  `leadership_score` decimal(5,2) DEFAULT NULL,
  `productivity_score` decimal(5,2) DEFAULT NULL,
  `overall_score` decimal(5,2) DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `improvements` text DEFAULT NULL,
  `goals` text DEFAULT NULL,
  `manager_comments` text DEFAULT NULL,
  `employee_comments` text DEFAULT NULL,
  `status` enum('Draft','Submitted','Acknowledged') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `reviewer_id` (`reviewer_id`),
  CONSTRAINT `fk_review_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: documents
-- ============================================================
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `category` enum('HR Policy','Contract','Certificate','ID','Other') DEFAULT 'Other',
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_doc_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_doc_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: notifications
-- ============================================================
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` enum('leave','payroll','announcement','performance','general') DEFAULT 'general',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: announcements
-- ============================================================
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `content` text NOT NULL,
  `author_id` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `author_id` (`author_id`),
  CONSTRAINT `fk_announce_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Departments
INSERT INTO `departments` (`id`, `name`, `code`, `description`) VALUES
(1, 'Human Resources', 'HR', 'Manages recruitment, training, and employee relations'),
(2, 'Information Technology', 'IT', 'Handles all technology infrastructure and development'),
(3, 'Finance', 'FIN', 'Manages financial planning and accounting'),
(4, 'Marketing', 'MKT', 'Handles brand promotion and marketing campaigns'),
(5, 'Operations', 'OPS', 'Oversees day-to-day business operations');

-- Leave Types
INSERT INTO `leave_types` (`id`, `name`, `days_allowed`, `description`) VALUES
(1, 'Annual Leave', 21, 'Yearly paid vacation leave'),
(2, 'Sick Leave', 10, 'Medical illness or injury'),
(3, 'Maternity Leave', 90, 'Leave for new mothers'),
(4, 'Paternity Leave', 14, 'Leave for new fathers'),
(5, 'Unpaid Leave', 30, 'Leave without pay'),
(6, 'Emergency Leave', 3, 'Urgent personal matters');

-- Default Admin User (password: Admin@1234)
INSERT INTO `users` (`id`, `employee_id`, `username`, `email`, `password`, `role`, `is_active`) VALUES
(1, NULL, 'admin', 'admin@hrms.com', '$2y$10$LiqZBDFGq.VPX7OU257WJuW7dwTBhmkg4TLF2lY.CCh3wMBlwKNCe', 'Admin', 1);

-- Demo Employees
INSERT INTO `employees` (`id`, `employee_id`, `first_name`, `last_name`, `email`, `phone`, `gender`, `department_id`, `position`, `employment_type`, `employment_status`, `hire_date`, `salary`) VALUES
(1, 'EMP001', 'John', 'Smith', 'john.smith@company.com', '+1-555-0101', 'Male', 2, 'Senior Developer', 'Full-Time', 'Active', '2020-01-15', 85000.00),
(2, 'EMP002', 'Sarah', 'Johnson', 'sarah.johnson@company.com', '+1-555-0102', 'Female', 1, 'HR Specialist', 'Full-Time', 'Active', '2019-06-01', 65000.00),
(3, 'EMP003', 'Michael', 'Brown', 'michael.brown@company.com', '+1-555-0103', 'Male', 3, 'Financial Analyst', 'Full-Time', 'Active', '2021-03-10', 72000.00),
(4, 'EMP004', 'Emily', 'Davis', 'emily.davis@company.com', '+1-555-0104', 'Female', 4, 'Marketing Manager', 'Full-Time', 'Active', '2018-09-20', 78000.00),
(5, 'EMP005', 'Robert', 'Wilson', 'robert.wilson@company.com', '+1-555-0105', 'Male', 5, 'Operations Lead', 'Full-Time', 'Active', '2020-11-01', 70000.00);

-- HR Manager User (password: Admin@1234)
INSERT INTO `users` (`id`, `employee_id`, `username`, `email`, `password`, `role`, `is_active`) VALUES
(2, 2, 'sarah.hr', 'sarah.johnson@company.com', '$2y$10$LiqZBDFGq.VPX7OU257WJuW7dwTBhmkg4TLF2lY.CCh3wMBlwKNCe', 'HR Manager', 1),
(3, 1, 'john.emp', 'john.smith@company.com', '$2y$10$LiqZBDFGq.VPX7OU257WJuW7dwTBhmkg4TLF2lY.CCh3wMBlwKNCe', 'Employee', 1);

-- Salary Structures
INSERT INTO `salary_structures` (`employee_id`, `basic_salary`, `house_allowance`, `transport_allowance`, `medical_allowance`, `other_allowances`, `tax_deduction`, `provident_fund`, `insurance`, `effective_date`) VALUES
(1, 85000.00, 8500.00, 2000.00, 1500.00, 1000.00, 8500.00, 4250.00, 1200.00, '2020-01-15'),
(2, 65000.00, 6500.00, 1500.00, 1000.00, 500.00, 6500.00, 3250.00, 900.00, '2019-06-01'),
(3, 72000.00, 7200.00, 1800.00, 1200.00, 800.00, 7200.00, 3600.00, 1000.00, '2021-03-10'),
(4, 78000.00, 7800.00, 2000.00, 1300.00, 900.00, 7800.00, 3900.00, 1100.00, '2018-09-20'),
(5, 70000.00, 7000.00, 1800.00, 1200.00, 800.00, 7000.00, 3500.00, 1000.00, '2020-11-01');

-- Leave Balances (current year)
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated`, `used`, `remaining`) VALUES
(1, 1, 2026, 21, 5, 16), (1, 2, 2026, 10, 2, 8),
(2, 1, 2026, 21, 3, 18), (2, 2, 2026, 10, 1, 9),
(3, 1, 2026, 21, 7, 14), (3, 2, 2026, 10, 0, 10),
(4, 1, 2026, 21, 4, 17), (4, 2, 2026, 10, 3, 7),
(5, 1, 2026, 21, 6, 15), (5, 2, 2026, 10, 1, 9);

-- Sample Announcements
INSERT INTO `announcements` (`title`, `content`, `author_id`) VALUES
('Welcome to HRMS', 'We are excited to launch our new Human Resource Management System. Please explore all features and provide feedback.', 1),
('Q1 Performance Reviews', 'Q1 performance reviews are due by end of March 2026. All managers please complete your reviews in the system.', 1);

-- Job Postings
INSERT INTO `job_postings` (`title`, `department_id`, `description`, `requirements`, `employment_type`, `location`, `salary_range_min`, `salary_range_max`, `vacancies`, `deadline`, `status`, `posted_by`) VALUES
('Full Stack Developer', 2, 'We are looking for an experienced Full Stack Developer to join our IT team.', '3+ years experience with PHP, JavaScript, MySQL. Knowledge of React/Vue is a plus.', 'Full-Time', 'New York, NY', 70000.00, 95000.00, 2, '2026-04-30', 'Open', 1),
('HR Coordinator', 1, 'Seeking an HR Coordinator to support our growing HR team.', '2+ years HR experience. SHRM certification preferred. Strong communication skills.', 'Full-Time', 'Remote', 50000.00, 65000.00, 1, '2026-04-15', 'Open', 2);

COMMIT;
