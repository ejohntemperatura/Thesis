-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 02, 2025 at 08:42 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `elms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cto_earnings`
--

CREATE TABLE `cto_earnings` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `earned_date` date NOT NULL,
  `hours_worked` decimal(5,2) NOT NULL,
  `cto_earned` decimal(5,2) NOT NULL,
  `work_type` enum('overtime','holiday','weekend','special_assignment') NOT NULL,
  `rate_applied` decimal(3,2) NOT NULL DEFAULT 1.00,
  `description` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cto_expiration`
--

CREATE TABLE `cto_expiration` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `cto_earnings_id` int(11) NOT NULL,
  `hours_expired` decimal(5,2) NOT NULL,
  `expiration_date` date NOT NULL,
  `processed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cto_usage`
--

CREATE TABLE `cto_usage` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_request_id` int(11) DEFAULT NULL,
  `hours_used` decimal(5,2) NOT NULL,
  `used_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dtr`
--

CREATE TABLE `dtr` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `morning_time_in` datetime DEFAULT NULL,
  `morning_time_out` datetime DEFAULT NULL,
  `afternoon_time_in` datetime DEFAULT NULL,
  `afternoon_time_out` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `email_queue_id` int(11) DEFAULT NULL,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `status` enum('sent','failed','queued','cancelled') NOT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `email_queue_id`, `to_email`, `subject`, `status`, `sent_at`, `error_message`, `created_at`) VALUES
(1, 1, 'elmsproject1@gmail.com', 'ELMS Test Email - 2025-09-29 03:16:35', 'queued', NULL, NULL, '2025-09-29 01:16:35'),
(2, 1, 'elmsproject1@gmail.com', 'ELMS Test Email - 2025-09-29 03:16:35', 'sent', '2025-09-28 19:17:40', NULL, '2025-09-29 01:17:40'),
(3, 2, 'elmsproject1@gmail.com', 'ELMS System Test - 2025-09-29 03:18:22', 'queued', NULL, NULL, '2025-09-29 01:18:22'),
(4, 2, 'elmsproject1@gmail.com', 'ELMS System Test - 2025-09-29 03:18:22', 'sent', '2025-09-28 19:18:24', NULL, '2025-09-29 01:18:24'),
(5, 3, 'elmsproject1@gmail.com', 'ELMS Auto Processing Test - 2025-09-29 03:22:50', 'queued', NULL, NULL, '2025-09-29 01:22:50'),
(6, 3, 'elmsproject1@gmail.com', 'ELMS Auto Processing Test - 2025-09-29 03:22:50', 'sent', '2025-09-28 19:24:03', NULL, '2025-09-29 01:24:03');

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `to_name` varchar(255) DEFAULT NULL,
  `subject` varchar(500) NOT NULL,
  `body` longtext NOT NULL,
  `is_html` tinyint(1) DEFAULT 1,
  `status` enum('pending','sending','sent','failed','cancelled') DEFAULT 'pending',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 3,
  `last_attempt_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `error_message` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_queue`
--

INSERT INTO `email_queue` (`id`, `to_email`, `to_name`, `subject`, `body`, `is_html`, `status`, `priority`, `attempts`, `max_attempts`, `last_attempt_at`, `sent_at`, `created_at`, `updated_at`, `error_message`, `metadata`) VALUES
(1, 'elmsproject1@gmail.com', 'ELMS Test', 'ELMS Test Email - 2025-09-29 03:16:35', '<h2>ELMS Email Test</h2><p>This is a test email sent at 2025-09-29 03:16:35</p><p>If you receive this, the email system is working correctly!</p>', 1, 'sent', 'normal', 0, 3, '2025-09-29 01:17:40', '2025-09-29 01:17:40', '2025-09-29 01:16:35', '2025-09-29 01:17:40', NULL, NULL),
(2, 'elmsproject1@gmail.com', 'ELMS System', 'ELMS System Test - 2025-09-29 03:18:22', '<h2>ELMS Email System Test</h2>\r\n            <p>This is a test email sent at 2025-09-29 03:18:22</p>\r\n            <p>If you receive this, the email system is working correctly!</p>\r\n            <p><strong>System Status:</strong></p>\r\n            <ul>\r\n                <li>✅ Gmail SMTP connection working</li>\r\n                <li>✅ Email queueing system active</li>\r\n                <li>✅ Automatic processing ready</li>\r\n            </ul>', 1, 'sent', 'normal', 0, 3, '2025-09-29 01:18:24', '2025-09-29 01:18:24', '2025-09-29 01:18:22', '2025-09-29 01:18:24', NULL, NULL),
(3, 'elmsproject1@gmail.com', 'ELMS System', 'ELMS Auto Processing Test - 2025-09-29 03:22:50', '<h2>ELMS Automatic Processing Test</h2>\r\n            <p>This is a test email to verify automatic processing.</p>\r\n            <p>Time: 2025-09-29 03:22:50</p>\r\n            <p>If you receive this, automatic processing is working!</p>', 1, 'sent', 'normal', 0, 3, '2025-09-29 01:24:03', '2025-09-29 01:24:03', '2025-09-29 01:22:50', '2025-09-29 01:24:03', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `html_body` text NOT NULL,
  `plain_text_body` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_verification_logs`
--

CREATE TABLE `email_verification_logs` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `verification_token` varchar(255) NOT NULL,
  `sent_at` datetime DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `status` enum('sent','verified','expired','failed') DEFAULT 'sent',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_verification_logs`
--

INSERT INTO `email_verification_logs` (`id`, `employee_id`, `email`, `verification_token`, `sent_at`, `verified_at`, `expires_at`, `status`, `ip_address`, `user_agent`) VALUES
(26, 61, 'jeromedichos898@gmail.com', 'dde45fb7b8666be27f9b22b4762be4013f2aa72531e698ed71ab6b2ef0fcc558', '2025-10-22 14:43:59', '2025-10-22 14:44:54', '2025-10-23 08:43:59', 'verified', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36'),
(28, 63, 'erictorion94@gmail.com', '99c174a92806f779d78f2272823ea79993e0b9f673dde827af2d4ed1f8171c29', '2025-10-31 21:42:33', '2025-10-31 21:43:30', '2025-11-01 21:42:33', 'verified', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `account_status` enum('pending','active','suspended') DEFAULT 'pending',
  `contact` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `role` enum('employee','manager','admin','director') DEFAULT 'employee',
  `sick_leave_balance` decimal(5,2) DEFAULT 1.25,
  `emergency_leave_balance` int(11) DEFAULT 0,
  `maternity_leave_balance` int(11) DEFAULT 0,
  `paternity_leave_balance` int(11) DEFAULT 0,
  `study_leave_balance` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `vacation_leave_balance` decimal(5,2) DEFAULT 1.25,
  `special_leave_privilege_balance` int(11) DEFAULT 3,
  `solo_parent_leave_balance` int(11) DEFAULT 7,
  `vawc_leave_balance` int(11) DEFAULT 10,
  `rehabilitation_privilege_balance` int(11) DEFAULT 0,
  `special_women_leave_balance` int(11) DEFAULT 0,
  `special_emergency_leave_balance` int(11) DEFAULT 0,
  `adoption_leave_balance` int(11) DEFAULT 0,
  `mandatory_leave_balance` int(11) DEFAULT 0,
  `service_start_date` date DEFAULT NULL,
  `last_leave_credit_update` date DEFAULT NULL,
  `special_privilege_leave_balance` decimal(5,2) DEFAULT 3.00,
  `rehabilitation_leave_balance` decimal(5,2) DEFAULT 0.00,
  `terminal_leave_balance` int(11) DEFAULT 0,
  `gender` enum('male','female') DEFAULT 'male',
  `is_solo_parent` tinyint(1) DEFAULT 0,
  `remember_token` varchar(255) DEFAULT NULL,
  `remember_token_expires` datetime DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `cto_balance` decimal(5,2) DEFAULT 0.00,
  `bereavement_leave_balance` int(11) DEFAULT 3,
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `email`, `email_verified`, `verification_token`, `verification_expires`, `account_status`, `contact`, `department`, `position`, `password`, `name`, `role`, `sick_leave_balance`, `emergency_leave_balance`, `maternity_leave_balance`, `paternity_leave_balance`, `study_leave_balance`, `created_at`, `vacation_leave_balance`, `special_leave_privilege_balance`, `solo_parent_leave_balance`, `vawc_leave_balance`, `rehabilitation_privilege_balance`, `special_women_leave_balance`, `special_emergency_leave_balance`, `adoption_leave_balance`, `mandatory_leave_balance`, `service_start_date`, `last_leave_credit_update`, `special_privilege_leave_balance`, `rehabilitation_leave_balance`, `terminal_leave_balance`, `gender`, `is_solo_parent`, `remember_token`, `remember_token_expires`, `password_reset_token`, `password_reset_expires`, `cto_balance`, `bereavement_leave_balance`, `profile_picture`) VALUES
(6, 'admin@gmail.com', 0, NULL, NULL, 'pending', '09300507279', 'Executive', 'Employee', '$2y$10$6W1ejtqssxCWEgeALgx30eUHqkye42x4JFNJKahru0CwpSk3iJ4zK', 'Human Resource', 'admin', 5, 0, 105, 7, 0, '2025-06-12 02:48:46', 5, 1, 0, 10, 6, 2, 3, 60, 5, '2024-01-01', '2025-10-11', 3.00, 6.00, 30, 'male', 0, NULL, NULL, NULL, NULL, 0.00, 3, NULL),
(24, 'departmenthead999@gmail.com', 1, NULL, NULL, 'active', 'N/A', 'College of Technology', 'Department Head', '$2y$10$CfTNa2K3D7fy1z1Lg4T9BucMWSdhEixLPL4pN.MhdpJIh3gB3rxIa', 'Department Head - Technology', 'manager', 15, 0, 105, 7, 0, '2025-08-25 13:41:36', 15, 3, 7, 10, 0, 2, 3, 60, 5, '2025-08-25', '2025-10-11', 3.00, 0.00, 0, 'male', 0, NULL, NULL, '5f91ee92c25d464a8842eeefde16962add9dda44db7891e4cf416628024ffcf7', '2025-10-11 16:51:05', 0.00, 3, NULL),
(25, 'directorhead999@gmail.com', 1, NULL, NULL, 'active', 'N/A', 'Operations', 'Director Head', '$2y$10$CfTNa2K3D7fy1z1Lg4T9BucMWSdhEixLPL4pN.MhdpJIh3gB3rxIa', 'Director Head', 'director', 15, 0, 105, 7, 0, '2025-08-25 13:53:55', 15, 3, 7, 10, 0, 2, 3, 60, 5, '2025-08-25', '2025-10-11', 3.00, 0.00, 0, 'male', 0, NULL, NULL, NULL, NULL, 0.00, 3, NULL),
(61, 'jeromedichos898@gmail.com', 1, NULL, NULL, 'active', '09300507279', 'College of Agriculture', 'Department Head', '$2y$10$q3AB.im3TjIvQOofRCLNV.fYV36iql0JAxzAxFP1/.OVvNHy03yZ2', 'Department Head - Agriculture', 'manager', 10, 0, 0, 0, 0, '2025-10-22 06:43:59', 15, 3, 7, 10, 0, 0, 0, 0, 0, NULL, NULL, 3.00, 0.00, 0, 'male', 0, NULL, NULL, NULL, NULL, 0.00, 3, NULL),
(63, 'erictorion94@gmail.com', 1, NULL, NULL, 'active', '09300507279', 'College of Agriculture', 'Instructor', '$2y$10$f63paQl1T3WwbB9cF4dtrugo6rVcJNmNAOCH875Bi5Ynsqr6tk922', 'eric john', 'employee', 10, 0, 0, 0, 0, '2025-10-31 13:42:33', 15, 3, 7, 5, 0, 0, 0, 0, 0, NULL, NULL, 3.00, 0.00, 0, 'male', 0, NULL, NULL, NULL, NULL, 0.00, 3, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `leave_alerts`
--

CREATE TABLE `leave_alerts` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `alert_type` varchar(50) DEFAULT NULL,
  `priority` enum('low','moderate','critical','urgent') DEFAULT 'moderate',
  `alert_category` enum('utilization','year_end','csc_compliance','wellness','custom') DEFAULT 'utilization',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `message` text DEFAULT NULL,
  `sent_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_alerts`
--

INSERT INTO `leave_alerts` (`id`, `employee_id`, `alert_type`, `priority`, `alert_category`, `metadata`, `message`, `sent_by`, `created_at`, `is_read`, `read_at`) VALUES
(34, 6, 'test_alert', 'moderate', 'utilization', NULL, 'This is a test alert to verify the notification system is working properly.', 6, '2025-09-16 18:23:38', 1, NULL),
(95, 6, 'civil_service_compliance', 'moderate', 'utilization', NULL, 'Dear Employee, IMPORTANT NOTICE: Your leave utilization is below the required Civil Service Commission (CSC) standards. Please ensure you are taking adequate time off to maintain work-life balance and comply with CSC regulations.', NULL, '2025-10-11 16:08:32', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `leave_credit_earnings`
--

CREATE TABLE `leave_credit_earnings` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `vacation_earned` decimal(5,2) DEFAULT 1.25,
  `sick_earned` decimal(5,2) DEFAULT 1.25,
  `special_privilege_earned` decimal(5,2) DEFAULT 0.25,
  `service_days` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_credit_history`
--

CREATE TABLE `leave_credit_history` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `credit_type` enum('vacation','sick','special_privilege','maternity','paternity','solo_parent','vawc','rehabilitation','special_women','special_emergency','adoption','mandatory') NOT NULL,
  `credit_amount` decimal(5,2) NOT NULL,
  `accrual_date` date NOT NULL,
  `service_days` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type` enum('vacation','sick','special_privilege','maternity','paternity','solo_parent','vawc','rehabilitation','study','terminal','cto','without_pay') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `department_approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `director_approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `department_approved_at` datetime DEFAULT NULL,
  `dept_head_approval` enum('pending','approved','rejected') DEFAULT 'pending',
  `dept_head_approved_by` int(11) DEFAULT NULL,
  `dept_head_approved_at` datetime DEFAULT NULL,
  `director_approval` enum('pending','approved','rejected') DEFAULT 'pending',
  `director_approved_by` int(11) DEFAULT NULL,
  `director_approved_at` datetime DEFAULT NULL,
  `department_comment` text DEFAULT NULL,
  `director_comment` text DEFAULT NULL,
  `admin_approval` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_approved_by` int(11) DEFAULT NULL,
  `admin_approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `medical_certificate_path` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded medical certificate file (for sick leave)',
  `medical_condition` enum('in_hospital','out_patient') DEFAULT NULL,
  `illness_specify` varchar(255) DEFAULT NULL,
  `special_women_condition` text DEFAULT NULL,
  `location_type` enum('within_philippines','outside_philippines') DEFAULT NULL,
  `location_specify` varchar(255) DEFAULT NULL,
  `study_type` enum('masters_degree','bar_board') DEFAULT NULL,
  `monetization_details` text DEFAULT NULL,
  `terminal_leave_details` text DEFAULT NULL,
  `is_late` tinyint(1) DEFAULT 0,
  `late_justification` text DEFAULT NULL,
  `days_requested` int(11) DEFAULT 0,
  `approved_days` int(11) DEFAULT NULL,
  `pay_status` enum('with_pay','without_pay') DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `dept_head_rejection_reason` text DEFAULT NULL,
  `director_rejection_reason` text DEFAULT NULL,
  `final_approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_days_with_pay` int(11) DEFAULT NULL,
  `approved_days_without_pay` int(11) DEFAULT NULL,
  `director_approval_notes` text DEFAULT NULL,
  `admin_approval_notes` text DEFAULT NULL,
  `printed_at` datetime DEFAULT NULL,
  `printed_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `original_leave_type` varchar(50) DEFAULT NULL COMMENT 'Stores the original leave type when converted to without_pay due to insufficient credits'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `employee_id`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `department_approval_status`, `director_approval_status`, `department_approved_at`, `dept_head_approval`, `dept_head_approved_by`, `dept_head_approved_at`, `director_approval`, `director_approved_by`, `director_approved_at`, `department_comment`, `director_comment`, `admin_approval`, `admin_approved_by`, `admin_approved_at`, `created_at`, `medical_certificate_path`, `medical_condition`, `illness_specify`, `special_women_condition`, `location_type`, `location_specify`, `study_type`, `monetization_details`, `terminal_leave_details`, `is_late`, `late_justification`, `days_requested`, `approved_days`, `pay_status`, `approved_by`, `approved_at`, `rejection_reason`, `dept_head_rejection_reason`, `director_rejection_reason`, `final_approval_status`, `approved_days_with_pay`, `approved_days_without_pay`, `director_approval_notes`, `admin_approval_notes`, `printed_at`, `printed_by`, `rejected_at`, `rejected_by`, `original_leave_type`) VALUES
(179, 63, 'without_pay', '2025-10-31', '2025-11-05', 'dasdasd', 'approved', 'pending', 'pending', NULL, 'approved', 61, '2025-10-31 22:22:16', 'approved', 25, '2025-10-31 22:50:35', NULL, NULL, 'pending', NULL, NULL, '2025-10-31 14:17:09', NULL, '', '', '', '', '', '', NULL, NULL, 0, NULL, 4, 2, 'without_pay', NULL, NULL, NULL, NULL, NULL, 'pending', 0, 2, NULL, NULL, NULL, NULL, NULL, NULL, 'maternity'),
(180, 63, 'vawc', '2025-10-30', '2025-11-05', 'dadasdda', 'approved', 'pending', 'pending', NULL, 'approved', 61, '2025-10-31 23:32:25', 'approved', 25, '2025-10-31 23:33:20', NULL, NULL, 'pending', NULL, NULL, '2025-10-31 15:28:43', NULL, '', '', '', '', '', '', NULL, NULL, 1, 'dasdad', 5, 5, 'with_pay', NULL, NULL, NULL, NULL, NULL, 'pending', 5, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `medical_certificates`
--

CREATE TABLE `medical_certificates` (
  `id` int(11) NOT NULL,
  `leave_request_id` int(11) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offline_email_settings`
--

CREATE TABLE `offline_email_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offline_email_settings`
--

INSERT INTO `offline_email_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'offline_mode', '1', 'Enable offline email mode (1=on, 0=off)', '2025-09-29 00:30:30'),
(2, 'queue_processing', '1', 'Enable automatic queue processing (1=on, 0=off)', '2025-09-29 00:30:30'),
(3, 'max_attempts', '3', 'Maximum retry attempts for failed emails', '2025-09-29 00:30:30'),
(4, 'retry_delay_minutes', '30', 'Delay between retry attempts in minutes', '2025-09-29 00:30:30'),
(5, 'batch_size', '10', 'Number of emails to process in each batch', '2025-09-29 00:30:30'),
(6, 'cleanup_days', '30', 'Days to keep sent emails in queue before cleanup', '2025-09-29 00:30:30'),
(7, 'last_processed', '1759109043', 'Timestamp of last queue processing', '2025-09-29 01:24:03'),
(8, 'smtp_available', '1', 'SMTP server availability status (1=available, 0=unavailable)', '2025-09-29 01:16:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cto_earnings`
--
ALTER TABLE `cto_earnings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_cto_earnings_employee_date` (`employee_id`,`earned_date`),
  ADD KEY `idx_cto_earnings_status` (`status`);

--
-- Indexes for table `cto_expiration`
--
ALTER TABLE `cto_expiration`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `cto_earnings_id` (`cto_earnings_id`),
  ADD KEY `idx_cto_expiration_date` (`expiration_date`);

--
-- Indexes for table `cto_usage`
--
ALTER TABLE `cto_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leave_request_id` (`leave_request_id`),
  ADD KEY `idx_cto_usage_employee_date` (`employee_id`,`used_date`);

--
-- Indexes for table `dtr`
--
ALTER TABLE `dtr`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`date`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email_queue_id` (`email_queue_id`),
  ADD KEY `idx_to_email` (`to_email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_to_email` (`to_email`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_name` (`template_name`);

--
-- Indexes for table `email_verification_logs`
--
ALTER TABLE `email_verification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_service_start_date` (`service_start_date`);

--
-- Indexes for table `leave_alerts`
--
ALTER TABLE `leave_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sent_by` (`sent_by`),
  ADD KEY `idx_leave_alerts_employee_read` (`employee_id`,`is_read`);

--
-- Indexes for table `leave_credit_earnings`
--
ALTER TABLE `leave_credit_earnings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_month` (`employee_id`,`year`,`month`),
  ADD KEY `idx_leave_credit_earnings_employee` (`employee_id`,`year`,`month`);

--
-- Indexes for table `leave_credit_history`
--
ALTER TABLE `leave_credit_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_leave_credit_history_employee` (`employee_id`,`accrual_date`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dept_head_approved_by` (`dept_head_approved_by`),
  ADD KEY `fk_director_approved_by` (`director_approved_by`),
  ADD KEY `fk_admin_approved_by` (`admin_approved_by`),
  ADD KEY `idx_medical_certificate_path` (`medical_certificate_path`),
  ADD KEY `idx_leave_requests_employee` (`employee_id`,`start_date`),
  ADD KEY `idx_leave_requests_status` (`status`),
  ADD KEY `idx_director_approval` (`director_approval`),
  ADD KEY `idx_admin_approval` (`admin_approval`),
  ADD KEY `idx_final_approval_status` (`final_approval_status`),
  ADD KEY `fk_approved_by` (`approved_by`),
  ADD KEY `fk_rejected_by` (`rejected_by`),
  ADD KEY `idx_leave_type` (`leave_type`),
  ADD KEY `idx_leave_status` (`status`),
  ADD KEY `idx_leave_dates` (`start_date`,`end_date`);

--
-- Indexes for table `medical_certificates`
--
ALTER TABLE `medical_certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leave_request_id` (`leave_request_id`);

--
-- Indexes for table `offline_email_settings`
--
ALTER TABLE `offline_email_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cto_earnings`
--
ALTER TABLE `cto_earnings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cto_expiration`
--
ALTER TABLE `cto_expiration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cto_usage`
--
ALTER TABLE `cto_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dtr`
--
ALTER TABLE `dtr`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_verification_logs`
--
ALTER TABLE `email_verification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `leave_alerts`
--
ALTER TABLE `leave_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `leave_credit_earnings`
--
ALTER TABLE `leave_credit_earnings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_credit_history`
--
ALTER TABLE `leave_credit_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=181;

--
-- AUTO_INCREMENT for table `medical_certificates`
--
ALTER TABLE `medical_certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offline_email_settings`
--
ALTER TABLE `offline_email_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cto_earnings`
--
ALTER TABLE `cto_earnings`
  ADD CONSTRAINT `cto_earnings_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cto_earnings_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cto_expiration`
--
ALTER TABLE `cto_expiration`
  ADD CONSTRAINT `cto_expiration_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cto_expiration_ibfk_2` FOREIGN KEY (`cto_earnings_id`) REFERENCES `cto_earnings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cto_usage`
--
ALTER TABLE `cto_usage`
  ADD CONSTRAINT `cto_usage_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cto_usage_ibfk_2` FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dtr`
--
ALTER TABLE `dtr`
  ADD CONSTRAINT `dtr_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`email_queue_id`) REFERENCES `email_queue` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `email_verification_logs`
--
ALTER TABLE `email_verification_logs`
  ADD CONSTRAINT `email_verification_logs_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_alerts`
--
ALTER TABLE `leave_alerts`
  ADD CONSTRAINT `leave_alerts_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `leave_alerts_ibfk_2` FOREIGN KEY (`sent_by`) REFERENCES `employees` (`id`);

--
-- Constraints for table `leave_credit_earnings`
--
ALTER TABLE `leave_credit_earnings`
  ADD CONSTRAINT `leave_credit_earnings_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_credit_history`
--
ALTER TABLE `leave_credit_history`
  ADD CONSTRAINT `leave_credit_history_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `fk_admin_approved_by` FOREIGN KEY (`admin_approved_by`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `fk_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `fk_dept_head_approved_by` FOREIGN KEY (`dept_head_approved_by`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `fk_director_approved_by` FOREIGN KEY (`director_approved_by`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `fk_leave_requests_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `medical_certificates`
--
ALTER TABLE `medical_certificates`
  ADD CONSTRAINT `medical_certificates_ibfk_1` FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
