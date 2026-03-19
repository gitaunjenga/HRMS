-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: localhost    Database: hrms_db
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
INSERT INTO `announcements` VALUES (1,'Welcome to HRMS','We are excited to launch our new Human Resource Management System. Please explore all features and provide feedback.',1,1,'2026-03-15 21:28:07'),(2,'Q1 Performance Reviews','Q1 performance reviews are due by end of March 2026. All managers please complete your reviews in the system.',1,1,'2026-03-15 21:28:07');
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `shift_id` int(10) unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `work_type` enum('Office','WFH','Remote','Field') NOT NULL DEFAULT 'Office',
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `break_minutes` int(11) NOT NULL DEFAULT 0,
  `is_late` tinyint(1) NOT NULL DEFAULT 0,
  `late_minutes` int(11) NOT NULL DEFAULT 0,
  `is_early_departure` tinyint(1) NOT NULL DEFAULT 0,
  `early_departure_minutes` int(11) NOT NULL DEFAULT 0,
  `check_in_method` enum('Manual','QR','Biometric') NOT NULL DEFAULT 'Manual',
  `total_hours` decimal(5,2) DEFAULT NULL,
  `status` enum('Present','Absent','Late','Half Day','On Leave') DEFAULT 'Present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`employee_id`,`date`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (1,1,NULL,'2026-03-15','22:35:06','22:35:13','Office',NULL,NULL,0,0,0,0,0,'Manual',0.00,'Half Day',NULL,'2026-03-15 21:35:06','2026-03-15 21:35:13');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_module` (`module`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,1,'Admin','update','settings','Permission Admin/payroll/edit set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:33:44'),(2,1,'Admin','update','settings','Permission Admin/payroll/edit set to denied','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:33:50'),(3,1,'Admin','update','settings','Permission HR Manager/employees/create set to denied','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:34:07'),(4,1,'Admin','update','settings','Permission Admin/employees/create set to denied','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:34:09'),(5,1,'Admin','update','settings','Permission Admin/employees/view_own set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:34:12'),(6,1,'Admin','update','settings','Permission Admin/attendance/view_own set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:34:19'),(7,1,'Admin','update','settings','Permission Admin/employees/create set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:35:01'),(8,1,'Admin','update','settings','Permission Admin/attendance/view set to denied','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:35:14'),(9,1,'Admin','update','settings','Permission Admin/leaves/view_own set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:35:21'),(10,1,'Admin','update','settings','Permission HR Manager/leaves/create set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:35:36'),(11,1,'Admin','update','settings','Permission Admin/leaves/create set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:35:39'),(12,1,'Admin','update','settings','Permission Admin/leaves/create set to denied','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:35:42'),(13,1,'Admin','update','settings','Permission HR Manager/leaves/view_own set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:35:47'),(14,1,'Admin','update','settings','Permission HR Manager/tickets/create set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:36:24'),(15,1,'Admin','update','settings','Permission Employee/holidays/view set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:36:49'),(16,1,'Admin','update','settings','Permission Admin/performance/view set to denied','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:46:14'),(17,1,'Admin','update','settings','Permission Employee/performance/view set to denied','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:46:38'),(18,1,'Admin','update','settings','Permission Admin/payroll/view_own set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:49:22'),(19,1,'Admin','update','settings','Permission Admin/performance/view_own set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:49:27'),(20,1,'Admin','update','settings','Permission Admin/shifts/view set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-18 02:49:43'),(21,1,'Admin','update','holidays','Updated holiday: Idd-ul-Fitr (2026-03-20)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-19 00:53:53'),(22,1,'Admin','update','holidays','Updated holiday: Mashujaa Day (2026-10-20)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-19 00:54:32'),(23,1,'Admin','delete','holidays','Deleted holiday ID: 3','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-19 00:55:05'),(24,1,'Admin','update','holidays','Updated holiday: New Year&#039;s Day (2026-01-01)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-19 00:55:23'),(25,1,'Admin','update','holidays','Updated holiday: New Year&amp;#039;s Day (2026-01-01)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-19 00:55:45'),(26,1,'Admin','update','settings','Permission Head of Department/holidays/view set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-19 00:58:06'),(27,1,'Admin','update','settings','Permission System Manager/holidays/view set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-19 00:58:20'),(28,1,'Admin','update','settings','Permission Employee/shifts/view_own set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-19 00:58:32'),(29,1,'Admin','update','settings','Permission Head of Department/payroll/view_own set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-19 00:59:13'),(30,1,'Admin','update','holidays','Updated holiday: Idd-ul-Fitr (2026-03-20)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-19 01:00:00'),(31,1,'Admin','update','holidays','Updated holiday: Idd-ul-Fitr (2026-03-20)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-19 01:00:13'),(32,1,'Admin','update','settings','Permission Employee/settings/view set to granted','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-19 01:04:36');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candidates`
--

DROP TABLE IF EXISTS `candidates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candidates`
--

LOCK TABLES `candidates` WRITE;
/*!40000 ALTER TABLE `candidates` DISABLE KEYS */;
/*!40000 ALTER TABLE `candidates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `head_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'Human Resources','HR','Manages recruitment, training, and employee relations',4,'2026-03-15 21:28:07','2026-03-17 12:12:12'),(2,'Information Technology','IT','Handles all technology infrastructure and development',8,'2026-03-15 21:28:07','2026-03-17 12:11:45'),(3,'Finance','FIN','Manages financial planning and accounting',3,'2026-03-15 21:28:07','2026-03-15 21:51:42'),(5,'Operations','OPS','Oversees day-to-day business operations',6,'2026-03-15 21:28:07','2026-03-17 12:11:56'),(6,'Credit','CR',NULL,3,'2026-03-15 23:03:13','2026-03-15 23:03:13');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_shifts`
--

DROP TABLE IF EXISTS `employee_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_shifts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `shift_id` int(10) unsigned NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_shift` (`shift_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_shifts`
--

LOCK TABLES `employee_shifts` WRITE;
/*!40000 ALTER TABLE `employee_shifts` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_shifts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `qr_token` varchar(64) DEFAULT NULL,
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
  UNIQUE KEY `uq_qr_token` (`qr_token`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `fk_employee_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,'EMP001','John','Smith','john.smith@company.com','+1-555-0101',NULL,'Male',NULL,NULL,NULL,NULL,NULL,2,'Senior Developer','Full-Time','Active','2020-01-15',NULL,85000.00,NULL,'d7110e5a7df404a75fa0cc517509ca46',NULL,NULL,NULL,NULL,NULL,'2026-03-15 21:28:07','2026-03-17 12:06:14'),(2,'EMP002','Sarah','Johnson','sarah.johnson@company.com','+1-555-0102',NULL,'Female',NULL,NULL,NULL,NULL,NULL,1,'HR Specialist','Full-Time','Active','2019-06-01',NULL,65000.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-15 21:28:07','2026-03-15 21:28:07'),(3,'EMP003','Michael','Brown','michael.brown@company.com','+1-555-0103',NULL,'Male','','','','','',3,'Financial Analyst','Full-Time','Active','2021-03-10','2026-03-16',72000.00,NULL,NULL,'','','','','','2026-03-15 21:28:07','2026-03-16 10:46:34'),(4,'EMP004','Emily','Davis','emily.davis@company.com','+1-555-0104',NULL,'Female','','','','','',1,'HR','Full-Time','Active','2018-09-20',NULL,78000.00,NULL,NULL,'','','','','','2026-03-15 21:28:07','2026-03-17 12:13:41'),(5,'EMP005','Robert','Wilson','robert.wilson@company.com','+1-555-0105',NULL,'Male',NULL,NULL,NULL,NULL,NULL,5,'Operations Lead','Full-Time','Active','2020-11-01',NULL,70000.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-15 21:28:07','2026-03-15 21:28:07'),(6,'EMP006','Steve','Gitau','johngnjenga99@gmail.com','','2026-03-15','Male','Nairobi','Nairobi','','Kenya','00100',2,'Data Engineer','Full-Time','Active','2026-03-16',NULL,0.00,NULL,NULL,'','','','','','2026-03-15 23:22:26','2026-03-15 23:22:26'),(7,'EMP007','John','Njenga','johngnjenga@gmail.com','',NULL,'Male','Nairobi','Nairobi','','Kenya','00100',6,'Business Intelligence Developer','Full-Time','Active','2026-03-16',NULL,0.00,NULL,NULL,'','','','','','2026-03-15 23:25:50','2026-03-15 23:25:50'),(8,'EMP008','waweru','Njenga','john@gmail.com','','2026-03-09','Male','Nairobi','Nairobi','','Kenya','00100',2,'Data Engineer','Full-Time','Active','2026-03-16',NULL,0.00,'69b93b646a2b96.72538146.jpeg',NULL,'','','','','','2026-03-15 23:38:35','2026-03-17 11:30:44');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_ticket_replies`
--

DROP TABLE IF EXISTS `hr_ticket_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_ticket_replies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `message` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_ticket_replies`
--

LOCK TABLES `hr_ticket_replies` WRITE;
/*!40000 ALTER TABLE `hr_ticket_replies` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_ticket_replies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_tickets`
--

DROP TABLE IF EXISTS `hr_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_tickets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_number` varchar(20) NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `assigned_to` int(10) unsigned DEFAULT NULL COMMENT 'users.id',
  `subject` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'General',
  `priority` enum('Low','Medium','High','Urgent') NOT NULL DEFAULT 'Medium',
  `status` enum('Open','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Open',
  `description` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_tickets`
--

LOCK TABLES `hr_tickets` WRITE;
/*!40000 ALTER TABLE `hr_tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_postings`
--

DROP TABLE IF EXISTS `job_postings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_postings`
--

LOCK TABLES `job_postings` WRITE;
/*!40000 ALTER TABLE `job_postings` DISABLE KEYS */;
INSERT INTO `job_postings` VALUES (1,'Full Stack Developer',2,'We are looking for an experienced Full Stack Developer to join our IT team.','3+ years experience with PHP, JavaScript, MySQL. Knowledge of React/Vue is a plus.','Full-Time','New York, NY',70000.00,95000.00,2,'2026-04-30','Open',1,'2026-03-15 21:28:07','2026-03-15 21:28:07'),(2,'HR Coordinator',1,'Seeking an HR Coordinator to support our growing HR team.','2+ years HR experience. SHRM certification preferred. Strong communication skills.','Full-Time','Remote',50000.00,65000.00,1,'2026-04-15','Open',2,'2026-03-15 21:28:07','2026-03-15 21:28:07'),(3,'Teller',3,'rew','','Full-Time','',NULL,NULL,1,NULL,'Open',2,'2026-03-15 23:04:29','2026-03-15 23:04:29');
/*!40000 ALTER TABLE `job_postings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_balances`
--

DROP TABLE IF EXISTS `leave_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` year(4) NOT NULL,
  `allocated` int(11) NOT NULL DEFAULT 0,
  `used` int(11) NOT NULL DEFAULT 0,
  `remaining` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_balance` (`employee_id`,`leave_type_id`,`year`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  CONSTRAINT `fk_balance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_balance_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_balances`
--

LOCK TABLES `leave_balances` WRITE;
/*!40000 ALTER TABLE `leave_balances` DISABLE KEYS */;
INSERT INTO `leave_balances` VALUES (1,1,1,2026,21,19,2),(2,1,2,2026,10,2,8),(3,2,1,2026,21,3,18),(4,2,2,2026,10,1,9),(5,3,1,2026,21,7,14),(6,3,2,2026,10,0,10),(7,4,1,2026,21,4,17),(8,4,2,2026,10,3,7),(9,5,1,2026,21,6,15),(10,5,2,2026,10,1,9);
/*!40000 ALTER TABLE `leave_balances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL DEFAULT 1,
  `reason` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `dm_action_by` int(11) DEFAULT NULL,
  `dm_action_at` timestamp NULL DEFAULT NULL,
  `dm_comment` text DEFAULT NULL,
  `hr_action_by` int(11) DEFAULT NULL,
  `hr_action_at` timestamp NULL DEFAULT NULL,
  `hr_comment` text DEFAULT NULL,
  `status` enum('Pending Department Approval','Pending HR Approval','Approved','Rejected by Head of Department','Rejected by HR','Cancelled') NOT NULL DEFAULT 'Pending Department Approval',
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
  CONSTRAINT `fk_leave_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_requests`
--

LOCK TABLES `leave_requests` WRITE;
/*!40000 ALTER TABLE `leave_requests` DISABLE KEYS */;
INSERT INTO `leave_requests` VALUES (1,1,4,'2026-03-18','2026-03-24',5,'de',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Approved',2,NULL,'2026-03-15 21:07:45','2026-03-15 23:05:49','2026-03-15 23:07:45'),(2,1,1,'2026-04-23','2026-04-30',6,'sw',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Approved',2,NULL,'2026-03-15 21:07:42','2026-03-15 23:07:11','2026-03-15 23:07:42'),(3,1,1,'2026-05-20','2026-05-31',8,'free',NULL,8,'2026-03-17 09:22:58','needed',2,'2026-03-17 09:26:33','approved','Approved',NULL,NULL,NULL,'2026-03-17 11:12:19','2026-03-17 11:26:33');
/*!40000 ALTER TABLE `leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_types`
--

DROP TABLE IF EXISTS `leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `days_allowed` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_types`
--

LOCK TABLES `leave_types` WRITE;
/*!40000 ALTER TABLE `leave_types` DISABLE KEYS */;
INSERT INTO `leave_types` VALUES (1,'Annual Leave',21,'Yearly paid vacation leave',1),(2,'Sick Leave',10,'Medical illness or injury',1),(3,'Maternity Leave',90,'Leave for new mothers',1),(4,'Paternity Leave',14,'Leave for new fathers',1),(5,'Unpaid Leave',30,'Leave without pay',1),(6,'Emergency Leave',3,'Urgent personal matters',1);
/*!40000 ALTER TABLE `leave_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` enum('general','leave','attendance','payroll','performance','document','recruitment','ticket','overtime','shift') NOT NULL DEFAULT 'general',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,'New Leave Request','John Smith has submitted a Paternity Leave request for 5 day(s) starting Mar 18, 2026.','leave',1,'http://localhost/HRMS/modules/leaves/index.php?status=Pending','2026-03-15 23:05:49'),(2,2,'New Leave Request','John Smith has submitted a Paternity Leave request for 5 day(s) starting Mar 18, 2026.','leave',0,'http://localhost/HRMS/modules/leaves/index.php?status=Pending','2026-03-15 23:05:49'),(3,1,'New Leave Request','John Smith has submitted a Annual Leave request for 6 day(s) starting Apr 23, 2026.','leave',1,'http://localhost/HRMS/modules/leaves/index.php?status=Pending','2026-03-15 23:07:11'),(4,2,'New Leave Request','John Smith has submitted a Annual Leave request for 6 day(s) starting Apr 23, 2026.','leave',1,'http://localhost/HRMS/modules/leaves/index.php?status=Pending','2026-03-15 23:07:11'),(5,3,'Leave Request Approved','Your Annual Leave request for 6 day(s) starting Apr 23, 2026 has been approved by sarah.hr.','leave',1,'http://localhost/HRMS/modules/leaves/index.php','2026-03-15 23:07:42'),(6,3,'Leave Request Approved','Your Paternity Leave request for 5 day(s) starting Mar 18, 2026 has been approved by sarah.hr.','leave',1,'http://localhost/HRMS/modules/leaves/index.php','2026-03-15 23:07:45'),(7,5,'Salary Paid','Your salary for March 2026 has been paid via Bank Transfer. Net Pay: KES 9,020.00','payroll',1,'http://localhost/HRMS/modules/payroll/payslip.php?id=1','2026-03-15 23:07:57'),(8,3,'Performance Review','A performance review for 2323 has been created. Please review and acknowledge.','performance',1,'http://localhost/HRMS/modules/performance/view_review.php?id=1','2026-03-15 23:26:50'),(9,5,'Leave Request Awaiting HR Approval','John Smith\'s Annual Leave request for 8 day(s) starting May 20, 2026 has been approved by the Head of Department and requires your review.','leave',0,'http://localhost/HRMS/modules/leaves/view.php?id=3','2026-03-17 11:22:58'),(10,3,'Leave Request Approved','Your Annual Leave request for 8 day(s) starting May 20, 2026 has been fully approved. Note: approved','leave',1,'http://localhost/HRMS/modules/leaves/view.php?id=3','2026-03-17 11:26:33'),(11,3,'Salary Paid','Your salary for March 2026 has been paid via Bank Transfer. Net Pay: KES 84,050.00','payroll',1,'http://localhost/HRMS/modules/payroll/payslip.php?id=3','2026-03-17 12:08:50');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `overtime_requests`
--

DROP TABLE IF EXISTS `overtime_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `overtime_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `request_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `hours_requested` decimal(4,2) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `approved_by` int(10) unsigned DEFAULT NULL COMMENT 'users.id',
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `overtime_requests`
--

LOCK TABLES `overtime_requests` WRITE;
/*!40000 ALTER TABLE `overtime_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `overtime_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll`
--

DROP TABLE IF EXISTS `payroll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll`
--

LOCK TABLES `payroll` WRITE;
/*!40000 ALTER TABLE `payroll` DISABLE KEYS */;
INSERT INTO `payroll` VALUES (1,4,3,2026,10000.00,3000.00,13020.00,4000.00,9020.00,0,0,0.00,20.00,'Paid','2026-03-16','Bank Transfer','',2,'2026-03-15 22:07:44','2026-03-15 23:07:57'),(2,5,3,2026,70000.00,10800.00,80800.00,11500.00,69300.00,0,0,0.00,0.00,'Paid','2026-03-17','Bank Transfer','',2,'2026-03-15 22:26:29','2026-03-17 12:08:53'),(3,1,3,2026,85000.00,13000.00,98000.00,13950.00,84050.00,0,0,0.00,0.00,'Paid','2026-03-17','Bank Transfer','',2,'2026-03-15 22:53:13','2026-03-17 12:08:50');
/*!40000 ALTER TABLE `payroll` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `performance_reviews`
--

DROP TABLE IF EXISTS `performance_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `performance_reviews`
--

LOCK TABLES `performance_reviews` WRITE;
/*!40000 ALTER TABLE `performance_reviews` DISABLE KEYS */;
INSERT INTO `performance_reviews` VALUES (1,1,2,'2323','2026-03-16',7.00,7.00,7.00,7.00,7.00,7.00,'','','','',NULL,'Draft','2026-03-15 23:26:50','2026-03-15 23:26:50');
/*!40000 ALTER TABLE `performance_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `public_holidays`
--

DROP TABLE IF EXISTS `public_holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `public_holidays` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `holiday_date` date NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `holiday_date` (`holiday_date`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `public_holidays`
--

LOCK TABLES `public_holidays` WRITE;
/*!40000 ALTER TABLE `public_holidays` DISABLE KEYS */;
INSERT INTO `public_holidays` VALUES (1,'New Year&amp;#039;s Day','2026-01-01','New Years Day',1,'2026-03-17 14:40:56'),(2,'Good Friday','2026-04-03','Good Friday',1,'2026-03-17 14:40:56'),(4,'Easter Monday','2026-04-06','Easter Monday',1,'2026-03-17 14:40:56'),(5,'Labour Day','2026-05-01','International Labour Day',1,'2026-03-17 14:40:56'),(6,'Madaraka Day','2026-06-01','Kenya Madaraka Day',1,'2026-03-17 14:40:56'),(7,'Utamaduni Day','2026-10-10','Kenya Utamaduni Day (Moi Day)',1,'2026-03-17 14:40:56'),(8,'Mashujaa Day','2026-10-20','Mashujaa Day',1,'2026-03-17 14:40:56'),(9,'Jamhuri Day','2026-12-12','Kenya Independence Day',1,'2026-03-17 14:40:56'),(10,'Christmas Day','2026-12-25','Christmas Day',1,'2026-03-17 14:40:56'),(11,'Boxing Day','2026-12-26','Boxing Day',1,'2026-03-17 14:40:56'),(12,'Idd-ul-Fitr','2026-03-20','Idd-ul-Fitr',1,'2026-03-17 14:40:56');
/*!40000 ALTER TABLE `public_holidays` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role` varchar(50) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `is_granted` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rma` (`role`,`module`,`action`)
) ENGINE=InnoDB AUTO_INCREMENT=125 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,'Admin','dashboard','view',1),(2,'Admin','employees','view',1),(3,'Admin','employees','create',1),(4,'Admin','employees','edit',1),(5,'Admin','employees','delete',1),(6,'Admin','departments','view',1),(7,'Admin','departments','create',1),(8,'Admin','departments','edit',1),(9,'Admin','departments','delete',1),(10,'Admin','attendance','view',0),(11,'Admin','leaves','view',1),(12,'Admin','recruitment','view',1),(13,'Admin','performance','view',0),(14,'Admin','documents','view',1),(15,'Admin','reports','view',1),(16,'Admin','notifications','view',1),(17,'Admin','settings','view',1),(18,'Admin','settings','manage',1),(19,'Admin','users','view',1),(20,'Admin','users','create',1),(21,'Admin','users','edit',1),(22,'Admin','users','delete',1),(23,'Admin','audit','view',1),(24,'Admin','tickets','view',1),(25,'Admin','tickets','manage',1),(30,'Admin','overtime','view',1),(31,'Admin','overtime','approve',1),(32,'Admin','overtime','reject',1),(33,'Admin','holidays','view',1),(34,'Admin','holidays','create',1),(35,'Admin','holidays','edit',1),(36,'Admin','holidays','delete',1),(37,'HR Manager','dashboard','view',1),(38,'HR Manager','employees','view',1),(39,'HR Manager','employees','create',0),(40,'HR Manager','employees','edit',1),(41,'HR Manager','departments','view',1),(42,'HR Manager','attendance','view',1),(43,'HR Manager','attendance','edit',1),(44,'HR Manager','leaves','view',1),(45,'HR Manager','leaves','approve',1),(46,'HR Manager','leaves','reject',1),(47,'HR Manager','payroll','view',1),(48,'HR Manager','payroll','create',1),(49,'HR Manager','payroll','edit',1),(50,'HR Manager','payroll','process',1),(51,'HR Manager','recruitment','view',1),(52,'HR Manager','recruitment','create',1),(53,'HR Manager','recruitment','edit',1),(54,'HR Manager','performance','view',1),(55,'HR Manager','performance','create',1),(56,'HR Manager','performance','edit',1),(57,'HR Manager','documents','view',1),(58,'HR Manager','documents','upload',1),(59,'HR Manager','documents','manage',1),(60,'HR Manager','reports','view',1),(61,'HR Manager','reports','export',1),(62,'HR Manager','notifications','view',1),(63,'HR Manager','tickets','view',1),(64,'HR Manager','tickets','manage',1),(68,'HR Manager','overtime','view',1),(69,'HR Manager','overtime','approve',1),(70,'HR Manager','overtime','reject',1),(71,'HR Manager','holidays','view',1),(72,'Head of Department','dashboard','view',1),(73,'Head of Department','employees','view',1),(74,'Head of Department','attendance','view',1),(75,'Head of Department','leaves','view',1),(76,'Head of Department','leaves','approve',1),(77,'Head of Department','leaves','reject',1),(78,'Head of Department','performance','view',1),(79,'Head of Department','performance','create',1),(80,'Head of Department','documents','view',1),(81,'Head of Department','reports','view',1),(82,'Head of Department','notifications','view',1),(83,'Head of Department','tickets','view',1),(84,'Head of Department','tickets','create',1),(86,'Head of Department','overtime','view',1),(87,'Head of Department','overtime','approve',1),(88,'Head of Department','overtime','reject',1),(89,'Employee','dashboard','view',1),(90,'Employee','employees','view_own',1),(91,'Employee','attendance','view_own',1),(92,'Employee','leaves','view',1),(93,'Employee','leaves','view_own',1),(94,'Employee','leaves','create',1),(95,'Employee','payroll','view',1),(96,'Employee','payroll','view_own',1),(97,'Employee','performance','view',0),(98,'Employee','performance','view_own',1),(99,'Employee','documents','view',1),(100,'Employee','documents','view_own',1),(101,'Employee','notifications','view',1),(102,'Employee','tickets','view',1),(103,'Employee','tickets','create',1),(106,'Employee','overtime','view_own',1),(107,'Employee','overtime','create',1),(108,'Admin','payroll','edit',0),(109,'Admin','employees','view_own',1),(110,'Admin','attendance','view_own',1),(111,'Admin','leaves','view_own',1),(112,'HR Manager','leaves','create',1),(113,'Admin','leaves','create',0),(114,'HR Manager','leaves','view_own',1),(115,'HR Manager','tickets','create',1),(116,'Employee','holidays','view',1),(117,'Admin','payroll','view_own',1),(118,'Admin','performance','view_own',1),(119,'Admin','shifts','view',1),(120,'Head of Department','holidays','view',1),(121,'System Manager','holidays','view',1),(122,'Employee','shifts','view_own',1),(123,'Head of Department','payroll','view_own',1),(124,'Employee','settings','view',1);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT '',
  `color` varchar(20) DEFAULT 'gray',
  `is_system` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin','Full system access','red',1,'2026-03-18 00:02:29'),(2,'HR Manager','Manages HR operations and employee data','emerald',1,'2026-03-18 00:02:29'),(3,'Head of Department','Manages department staff and approvals','blue',1,'2026-03-18 00:02:29'),(4,'Employee','Standard employee access','slate',1,'2026-03-18 00:02:29'),(5,'System Manager','','pink',0,'2026-03-18 00:07:53');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_structures`
--

DROP TABLE IF EXISTS `salary_structures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_structures`
--

LOCK TABLES `salary_structures` WRITE;
/*!40000 ALTER TABLE `salary_structures` DISABLE KEYS */;
INSERT INTO `salary_structures` VALUES (1,1,85000.00,8500.00,2000.00,1500.00,1000.00,8500.00,4250.00,1200.00,0.00,'2020-01-15','2026-03-15 21:28:07'),(2,2,65000.00,6500.00,1500.00,1000.00,500.00,6500.00,3250.00,900.00,0.00,'2019-06-01','2026-03-15 21:28:07'),(3,3,72000.00,7200.00,1800.00,1200.00,800.00,7200.00,3600.00,1000.00,0.00,'2021-03-10','2026-03-15 21:28:07'),(4,4,78000.00,7800.00,2000.00,1300.00,900.00,7800.00,3900.00,1100.00,0.00,'2018-09-20','2026-03-15 21:28:07'),(5,5,70000.00,7000.00,1800.00,1200.00,800.00,7000.00,3500.00,1000.00,0.00,'2020-11-01','2026-03-15 21:28:07');
/*!40000 ALTER TABLE `salary_structures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shifts`
--

DROP TABLE IF EXISTS `shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shifts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `grace_minutes` int(11) NOT NULL DEFAULT 15 COMMENT 'late arrival tolerance',
  `early_departure_minutes` int(11) NOT NULL DEFAULT 15,
  `is_night_shift` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shifts`
--

LOCK TABLES `shifts` WRITE;
/*!40000 ALTER TABLE `shifts` DISABLE KEYS */;
/*!40000 ALTER TABLE `shifts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `totp_backup_codes`
--

DROP TABLE IF EXISTS `totp_backup_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `totp_backup_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `totp_backup_codes`
--

LOCK TABLES `totp_backup_codes` WRITE;
/*!40000 ALTER TABLE `totp_backup_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `totp_backup_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'Employee',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `totp_secret` varchar(64) DEFAULT NULL,
  `totp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `totp_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `fk_user_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,NULL,'admin','admin@hrms.com','$2y$10$LiqZBDFGq.VPX7OU257WJuW7dwTBhmkg4TLF2lY.CCh3wMBlwKNCe','Admin',1,'2026-03-18 22:03:25',NULL,NULL,'2026-03-15 21:28:07','2026-03-18 22:03:25',0,'FGBJCPPBP7RC76U4ZS572SLVWIXMKSZC',0,0),(2,2,'sarah.hr','sarah.johnson@company.com','$2y$10$LiqZBDFGq.VPX7OU257WJuW7dwTBhmkg4TLF2lY.CCh3wMBlwKNCe','HR Manager',1,'2026-03-17 23:41:08',NULL,NULL,'2026-03-15 21:28:07','2026-03-17 23:41:08',0,'GYVDOJYVJAFIEROECXF7RIIVV2Z3KYV6',0,0),(3,1,'john.emp','john.smith@company.com','$2y$10$LiqZBDFGq.VPX7OU257WJuW7dwTBhmkg4TLF2lY.CCh3wMBlwKNCe','Employee',1,'2026-03-18 22:00:32',NULL,NULL,'2026-03-15 21:28:07','2026-03-18 22:00:32',0,NULL,0,0),(4,3,'michael.dm','michael.brown@company.com','$2y$10$LiqZBDFGq.VPX7OU257WJuW7dwTBhmkg4TLF2lY.CCh3wMBlwKNCe','Head of Department',1,NULL,NULL,NULL,'2026-03-15 21:51:42','2026-03-16 10:59:23',0,NULL,0,0),(5,4,'emily.dm','emily.davis@company.com','$2y$10$LiqZBDFGq.VPX7OU257WJuW7dwTBhmkg4TLF2lY.CCh3wMBlwKNCe','HR Manager',1,'2026-03-17 12:08:27',NULL,NULL,'2026-03-15 21:51:42','2026-03-17 12:08:27',0,NULL,0,0),(6,6,'johngnjenga99','johngnjenga99@gmail.com','$2y$10$fJCGWjvDyTRozY1aRbZp8.tm96Pm0lOH3jYufcpzeby8zOVfmQ49a','Employee',1,NULL,NULL,NULL,'2026-03-15 23:22:26','2026-03-15 23:22:26',0,NULL,0,0),(7,7,'johngnjenga','johngnjenga@gmail.com','$2y$10$pto9w.zNQGRJICN2FiTcMuoOCcVaVhHtvel80vF308NPCrojqu7w2','Employee',1,NULL,NULL,NULL,'2026-03-15 23:25:50','2026-03-15 23:25:50',0,NULL,0,0),(8,8,'john','john@gmail.com','$2y$10$t5Z7CyoYLkxyUaJpDrHN5OiD/.r5dmRPAxYRVxRBRR93tUIpc.Kle','Employee',1,'2026-03-17 12:16:10',NULL,NULL,'2026-03-15 23:38:35','2026-03-17 12:23:10',0,'Z7PFE3OIZK6BOXUD6EI7RFMLIFKHBUSD',0,0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-19 11:01:26
