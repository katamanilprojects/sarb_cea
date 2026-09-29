-- Migration: Course Completion & Plan Reconciliation Audit
-- Table: course_completion_audits
-- Supports NBA Criterion 2.1 & 2.2, NAAC Criterion 2.3

CREATE TABLE IF NOT EXISTS `course_completion_audits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sub_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `total_planned_lectures` int(4) NOT NULL DEFAULT 0,
  `total_actual_conducted` int(4) NOT NULL DEFAULT 0,
  `total_compensatory_classes` int(4) NOT NULL DEFAULT 0,
  `syllabus_completion_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `unit1_completion_date` date DEFAULT NULL,
  `unit2_completion_date` date DEFAULT NULL,
  `unit3_completion_date` date DEFAULT NULL,
  `unit4_completion_date` date DEFAULT NULL,
  `unit5_completion_date` date DEFAULT NULL,
  `deviations_reason` text DEFAULT NULL,
  `compensatory_actions` text DEFAULT NULL,
  `topics_beyond_syllabus` text DEFAULT NULL,
  `faculty_signoff_status` enum('DRAFT','SUBMITTED','APPROVED') NOT NULL DEFAULT 'DRAFT',
  `faculty_signoff_at` datetime DEFAULT NULL,
  `hod_approval_status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `hod_approval_at` datetime DEFAULT NULL,
  `hod_remarks` text DEFAULT NULL,
  `reconciliation_mapping` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cca_sub` (`sub_id`),
  KEY `idx_cca_faculty` (`faculty_id`),
  CONSTRAINT `fk_cca_sub` FOREIGN KEY (`sub_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
