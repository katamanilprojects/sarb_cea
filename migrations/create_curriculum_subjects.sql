-- ============================================================================
-- SARB_CEA - CURRICULUM SUBJECTS SCHEMA MIGRATION
-- Central Master Syllabus Subject Catalog Managed by Academic Section
-- ============================================================================

START TRANSACTION;

-- 1. Create table `curriculum_subjects`
CREATE TABLE IF NOT EXISTS `curriculum_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prog_id` int(11) NOT NULL COMMENT 'Reference to programs.id',
  `reg_id` int(11) NOT NULL COMMENT 'Reference to regulations.id',
  `spec_id` int(11) NOT NULL COMMENT 'Reference to specialization.id',
  `yearsem` varchar(30) NOT NULL COMMENT 'Year and semester (e.g. I Yr - I Sem, IV Yr - I Sem)',
  `subject_sno` int(3) NOT NULL COMMENT 'Ordering serial number (S.No) within syllabus',
  `subcode` varchar(25) NOT NULL COMMENT 'Official course catalog code (e.g. 23A05301T)',
  `sub_fullname` varchar(150) NOT NULL COMMENT 'Full descriptive subject title',
  `sub_shortname` varchar(30) NOT NULL COMMENT 'Course short title / acronym (e.g. DBMS)',
  `sub_type` varchar(30) NOT NULL COMMENT 'Course classification type (Theory, Lab, Mandatory Course, etc.)',
  `lecture_hours` decimal(3,1) NOT NULL DEFAULT 0.0 COMMENT 'Weekly Lecture hours (L)',
  `tutorial_hours` decimal(3,1) NOT NULL DEFAULT 0.0 COMMENT 'Weekly Tutorial hours (T)',
  `practical_hours` decimal(3,1) NOT NULL DEFAULT 0.0 COMMENT 'Weekly Practical / Lab hours (P)',
  `credits` decimal(3,1) NOT NULL DEFAULT 0.0 COMMENT 'Total course credits (C)',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_curriculum_subject` (`reg_id`, `spec_id`, `yearsem`, `subcode`),
  KEY `idx_curr_subcode` (`subcode`),
  KEY `idx_curr_lookup` (`reg_id`, `spec_id`, `yearsem`, `status`),
  KEY `idx_curr_prog` (`prog_id`),
  KEY `fk_curr_spec` (`spec_id`),
  CONSTRAINT `fk_curr_prog` FOREIGN KEY (`prog_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_curr_reg` FOREIGN KEY (`reg_id`) REFERENCES `regulations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_curr_spec` FOREIGN KEY (`spec_id`) REFERENCES `specialization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Central syllabus subject catalog managed by Academic Section';

COMMIT;
