-- ============================================================================
-- SARB_CEA - EXTERNAL (SEE) ASSESSMENT SCHEMA MIGRATION
-- Compatible with JNTUA CEA R23, R20, and R19 Regulations
-- ============================================================================

START TRANSACTION;

-- 1. Extend the component_type ENUM in assessment_components to include SEE types
ALTER TABLE `assessment_components` 
MODIFY `component_type` ENUM(
  'Subjective',
  'Objective',
  'Assignment',
  'Day-to-Day',
  'Internal Exam',
  'Subjective-1',
  'Objective-1',
  'Subjective-2',
  'Objective-2',
  'Activity',
  'SEE-Theory',
  'SEE-Split-A',
  'SEE-Split-B',
  'SEE-Lab'
) NOT NULL;

-- 2. Create the dedicated External Assessment Marks table for finalized SEE results
CREATE TABLE IF NOT EXISTS `external_assessment_marks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `entry_mode` enum('DETAILED','DIRECT') NOT NULL DEFAULT 'DETAILED' COMMENT 'DETAILED = Mode A (Consolidated QP), DIRECT = Mode B (Ledger)',
  `external_marks` decimal(5,2) NOT NULL COMMENT 'Final marks scored out of max_marks',
  `max_marks` decimal(5,2) NOT NULL DEFAULT 70.00 COMMENT 'Maximum marks threshold (typically 70 or 35)',
  `q1_marks` decimal(5,2) DEFAULT NULL COMMENT 'Marks scored in compulsory Question 1 (Mode A only)',
  `choice_marks` decimal(5,2) DEFAULT NULL COMMENT 'Sum of resolved Either/Or choices (Mode A only)',
  `part_a_marks` decimal(5,2) DEFAULT NULL COMMENT 'Composite split course Part A marks',
  `part_b_marks` decimal(5,2) DEFAULT NULL COMMENT 'Composite split course Part B marks',
  `submitted_by` int(11) DEFAULT NULL COMMENT 'Faculty user ID who submitted the marks',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_subject_see` (`student_id`, `subject_id`),
  KEY `idx_see_subject` (`subject_id`),
  KEY `idx_see_student` (`student_id`),
  CONSTRAINT `fk_see_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_see_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Final Consolidated and Direct Semester End Examination Marks';

-- 3. Verify that regulatory academic_settings entries exist; insert fallbacks if absent
INSERT INTO `academic_settings` (`regulation_code`, `category`, `setting_key`, `setting_value`, `data_type`, `description`, `is_editable`)
SELECT 'R23', 'SEE', 'theory_see_max_marks', '70.00', 'FLOAT', 'Section 9(i): Semester End Examination carries 70 marks', 1
WHERE NOT EXISTS (SELECT 1 FROM `academic_settings` WHERE `regulation_code` = 'R23' AND `setting_key` = 'theory_see_max_marks');

INSERT INTO `academic_settings` (`regulation_code`, `category`, `setting_key`, `setting_value`, `data_type`, `description`, `is_editable`)
SELECT 'R23', 'SEE', 'theory_compulsory_q1_marks', '20.00', 'FLOAT', 'Section 9(b)(ii): Q1 contains 10 questions x 2 marks = 20 marks', 1
WHERE NOT EXISTS (SELECT 1 FROM `academic_settings` WHERE `regulation_code` = 'R23' AND `setting_key` = 'theory_compulsory_q1_marks');

INSERT INTO `academic_settings` (`regulation_code`, `category`, `setting_key`, `setting_value`, `data_type`, `description`, `is_editable`)
SELECT 'R23', 'SEE', 'theory_choice_group_marks', '10.00', 'FLOAT', 'Section 9(b)(iii): Q2 to Q11 carry 10 marks per choice group', 1
WHERE NOT EXISTS (SELECT 1 FROM `academic_settings` WHERE `regulation_code` = 'R23' AND `setting_key` = 'theory_choice_group_marks');

INSERT INTO `academic_settings` (`regulation_code`, `category`, `setting_key`, `setting_value`, `data_type`, `description`, `is_editable`)
SELECT 'R23', 'SEE', 'composite_split_max_marks', '35.00', 'FLOAT', 'Section 9(b): Composite split subjects carry 35M each', 1
WHERE NOT EXISTS (SELECT 1 FROM `academic_settings` WHERE `regulation_code` = 'R23' AND `setting_key` = 'composite_split_max_marks');

INSERT INTO `academic_settings` (`regulation_code`, `category`, `setting_key`, `setting_value`, `data_type`, `description`, `is_editable`)
SELECT 'R23', 'SEE', 'composite_q1_marks', '5.00', 'FLOAT', 'Section 9(b)(ii): Split subjects have 5 sub-questions x 1 mark', 1
WHERE NOT EXISTS (SELECT 1 FROM `academic_settings` WHERE `regulation_code` = 'R23' AND `setting_key` = 'composite_q1_marks');

COMMIT;
