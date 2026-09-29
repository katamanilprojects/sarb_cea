-- ==============================================================================
-- Migration: Centralized Academic Settings Engine & Audit Trail
-- System: sarb_cea (Faculty Academic Record Book - JNTUA CEA Autonomous)
-- Regulations Supported: R19, R20, R23
-- Date: 2026-09-25
-- ==============================================================================

-- 1. Create Academic Settings Table
CREATE TABLE IF NOT EXISTS `academic_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `regulation_code` VARCHAR(10) NOT NULL COMMENT 'Regulation identifier (e.g. R19, R20, R23)',
  `category` ENUM('CIA', 'SEE', 'ATTENDANCE', 'ATTAINMENT', 'GRADING', 'GENERAL') NOT NULL COMMENT 'Regulatory category group',
  `setting_key` VARCHAR(64) NOT NULL COMMENT 'Unique identifier key for the parameter',
  `setting_value` TEXT NOT NULL COMMENT 'Serialized value (scalar or JSON)',
  `data_type` ENUM('STRING', 'INT', 'FLOAT', 'BOOL', 'JSON') NOT NULL DEFAULT 'STRING' COMMENT 'Data type for automatic casting',
  `description` VARCHAR(255) DEFAULT NULL COMMENT 'Rule context and autonomous regulatory clause',
  `is_editable` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = configurable via UI, 0 = system locked',
  `updated_by` INT(11) DEFAULT NULL COMMENT 'User ID of last modifier',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reg_setting` (`regulation_code`, `setting_key`),
  INDEX `idx_regulation` (`regulation_code`),
  INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Centralized Autonomous Academic Settings Engine';

-- 2. Create Audit Trail Table
CREATE TABLE IF NOT EXISTS `academic_settings_audit` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_id` INT(11) NOT NULL COMMENT 'Reference to academic_settings.id',
  `regulation_code` VARCHAR(10) NOT NULL COMMENT 'Regulation of the setting at time of edit',
  `setting_key` VARCHAR(64) NOT NULL COMMENT 'Key of the modified setting',
  `old_value` TEXT DEFAULT NULL COMMENT 'Value prior to modification',
  `new_value` TEXT NOT NULL COMMENT 'Value after modification',
  `changed_by` INT(11) DEFAULT NULL COMMENT 'User ID who performed modification',
  `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp of change',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP Address of requesting user',
  PRIMARY KEY (`id`),
  INDEX `idx_setting_id` (`setting_id`),
  INDEX `idx_audit_reg_key` (`regulation_code`, `setting_key`),
  INDEX `idx_changed_by` (`changed_by`),
  CONSTRAINT `fk_academic_settings_audit_setting` 
    FOREIGN KEY (`setting_id`) REFERENCES `academic_settings` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for academic parameter mutations';

-- 3. Populate Default Seed Data for R23, R20, and R19
-- Helper Procedure or Direct Insert with ON DUPLICATE KEY UPDATE

INSERT INTO `academic_settings` (`regulation_code`, `category`, `setting_key`, `setting_value`, `data_type`, `description`, `is_editable`)
VALUES
-- ============================ R23 REGULATION ============================
-- CIA Evaluation
('R23', 'CIA', 'theory_mid_better_weight', '0.80', 'FLOAT', 'Section 9(a)(v): 80% weightage given to the better mid examination', 1),
('R23', 'CIA', 'theory_mid_lesser_weight', '0.20', 'FLOAT', 'Section 9(a)(v): 20% weightage given to the lower mid examination', 1),
('R23', 'CIA', 'theory_cia_total_marks', '30.00', 'FLOAT', 'Section 9(i): Continuous Internal Assessment carries 30 marks', 1),
('R23', 'CIA', 'theory_mid_subjective_condensed', '15.00', 'FLOAT', 'Section 9(a)(i-ii): Subjective 30 marks condensed to 15 marks', 1),
('R23', 'CIA', 'theory_mid_objective_marks', '10.00', 'FLOAT', 'Section 9(a)(i): Objective test carries 10 marks (20 mins)', 1),
('R23', 'CIA', 'theory_assignment_marks', '5.00', 'FLOAT', 'Section 9(a)(i): Continuous assessment assignment carries 5 marks', 1),
('R23', 'CIA', 'lab_cia_day_to_day_marks', '15.00', 'FLOAT', 'Section 9(c): Practical continuous work evaluated for 15 marks', 1),
('R23', 'CIA', 'lab_cia_internal_test_marks', '15.00', 'FLOAT', 'Section 9(c): Practical internal test evaluated for 15 marks', 1),
('R23', 'CIA', 'drawing_mid_subjective_marks', '15.00', 'FLOAT', 'Section 9(e): Engineering Drawing subjective test for 15 marks', 1),
('R23', 'CIA', 'drawing_day_to_day_marks', '15.00', 'FLOAT', 'Section 9(e): Engineering Drawing classwork reports for 15 marks', 1),

-- SEE Evaluation
('R23', 'SEE', 'theory_see_max_marks', '70.00', 'FLOAT', 'Section 9(i): Semester End Examination carries 70 marks', 1),
('R23', 'SEE', 'theory_compulsory_q1_marks', '20.00', 'FLOAT', 'Section 9(b)(ii): Q1 contains 10 questions x 2 marks = 20 marks', 1),
('R23', 'SEE', 'theory_choice_group_marks', '10.00', 'FLOAT', 'Section 9(b)(iii): Q2 to Q11 carry 10 marks per choice group', 1),
('R23', 'SEE', 'composite_split_max_marks', '35.00', 'FLOAT', 'Section 9(b): Composite split subjects (Part A / Part B) carry 35M each', 1),
('R23', 'SEE', 'composite_q1_marks', '5.00', 'FLOAT', 'Section 9(b)(ii): Split subjects have 5 sub-questions x 1 mark', 1),
('R23', 'SEE', 'drawing_see_max_marks', '70.00', 'FLOAT', 'Section 9(e): 5 either/or questions carrying 14 marks each', 1),

-- Project & Internship
('R23', 'GENERAL', 'project_total_marks', '200.00', 'FLOAT', 'Section 9 / 14: Final semester project total marks', 1),
('R23', 'GENERAL', 'project_internal_marks', '60.00', 'FLOAT', 'Section 14: Internal evaluation (Supervisor 30M + PRC 30M)', 1),
('R23', 'GENERAL', 'project_external_marks', '140.00', 'FLOAT', 'Section 14: External Viva-Voce examination carries 140 marks', 1),
('R23', 'GENERAL', 'summer_internship_marks', '50.00', 'FLOAT', 'Section 9 / 14: Summer Internship evaluated for 50 external marks', 1),
('R23', 'GENERAL', 'full_internship_marks', '100.00', 'FLOAT', '2025 Amendment clause (m): IV-II Internship for 100 marks', 1),

-- Passing Criteria
('R23', 'GRADING', 'see_min_pass_percentage', '35.00', 'FLOAT', 'Section 9: Minimum 35% in End Examination (24.5 / 70)', 1),
('R23', 'GRADING', 'aggregate_min_pass_pct', '40.00', 'FLOAT', 'Section 9: Minimum 40% aggregate (Mid + End examination)', 1),
('R23', 'GRADING', 'mandatory_course_pass_pct', '40.00', 'FLOAT', 'Section 9(f): Zero-credit course passing mark (12 / 30)', 1),

-- Attendance Compliance
('R23', 'ATTENDANCE', 'attendance_min_aggregate_pct', '75.00', 'FLOAT', 'Section 17(i): Minimum aggregate attendance requirement', 1),
('R23', 'ATTENDANCE', 'attendance_min_subject_pct', '40.00', 'FLOAT', 'Section 17(i): Minimum attendance per individual course', 1),
('R23', 'ATTENDANCE', 'attendance_condone_floor_pct', '65.00', 'FLOAT', 'Section 17(ii): Shortage below 65% shall in no case be condoned', 1),

-- Outcome-Based Education (OBE) & Attainment
('R23', 'ATTAINMENT', 'attainment_direct_cia_weight', '0.30', 'FLOAT', 'Direct CO Attainment internal component weightage', 1),
('R23', 'ATTAINMENT', 'attainment_direct_see_weight', '0.70', 'FLOAT', 'Direct CO Attainment external component weightage', 1),
('R23', 'ATTAINMENT', 'co_target_percentage', '60.00', 'FLOAT', 'NBA benchmark: Minimum % a student must score in a CO', 1),
('R23', 'ATTAINMENT', 'attainment_level_3_threshold', '70.00', 'FLOAT', 'Percentage of cohort meeting target for Attainment Level 3', 1),
('R23', 'ATTAINMENT', 'attainment_level_2_threshold', '60.00', 'FLOAT', 'Percentage of cohort meeting target for Attainment Level 2', 1),
('R23', 'ATTAINMENT', 'attainment_level_1_threshold', '50.00', 'FLOAT', 'Percentage of cohort meeting target for Attainment Level 1', 1),
('R23', 'ATTAINMENT', 'overall_direct_weight', '0.80', 'FLOAT', 'PO/PSO Attainment: Direct assessment weightage (80%)', 1),
('R23', 'ATTAINMENT', 'overall_indirect_weight', '0.20', 'FLOAT', 'PO/PSO Attainment: Indirect (surveys) assessment weightage (20%)', 1),

-- Grading Bands & Class Award
('R23', 'GRADING', 'grade_bands_json', '[{"min":90,"max":100,"grade":"A+","points":10},{"min":80,"max":89,"grade":"A","points":9},{"min":70,"max":79,"grade":"B","points":8},{"min":60,"max":69,"grade":"C","points":7},{"min":50,"max":59,"grade":"D","points":6},{"min":40,"max":49,"grade":"E","points":5},{"min":0,"max":39,"grade":"F","points":0}]', 'JSON', 'Section 19: 10-point absolute grading conversion scale', 1),
('R23', 'GRADING', 'class_award_json', '[{"class":"First Class with Distinction","min_cgpa":7.5},{"class":"First Class","min_cgpa":6.5,"max_cgpa":7.49},{"class":"Second Class","min_cgpa":5.5,"max_cgpa":6.49},{"class":"Pass Class","min_cgpa":5.0,"max_cgpa":5.49}]', 'JSON', 'Section 19: CGPA cutoffs for Distinction, First, Second, Pass', 1),

-- System Window
('R23', 'GENERAL', 'marks_entry_grace_days', '15', 'INT', 'Default days after semester end before mark edit forms auto-lock', 1),

-- ============================ R20 REGULATION ============================
-- CIA Evaluation
('R20', 'CIA', 'theory_mid_better_weight', '0.80', 'FLOAT', 'R20: 80% weightage given to the better mid examination', 1),
('R20', 'CIA', 'theory_mid_lesser_weight', '0.20', 'FLOAT', 'R20: 20% weightage given to the lower mid examination', 1),
('R20', 'CIA', 'theory_cia_total_marks', '30.00', 'FLOAT', 'R20: Continuous Internal Assessment carries 30 marks', 1),
('R20', 'CIA', 'theory_mid_subjective_condensed', '15.00', 'FLOAT', 'R20: Subjective 30 marks condensed to 15 marks', 1),
('R20', 'CIA', 'theory_mid_objective_marks', '10.00', 'FLOAT', 'R20: Objective test carries 10 marks', 1),
('R20', 'CIA', 'theory_assignment_marks', '5.00', 'FLOAT', 'R20: Continuous assessment assignment carries 5 marks', 1),
('R20', 'CIA', 'lab_cia_day_to_day_marks', '15.00', 'FLOAT', 'R20: Practical continuous day-to-day evaluation (15 marks)', 1),
('R20', 'CIA', 'lab_cia_internal_test_marks', '15.00', 'FLOAT', 'R20: Practical internal test (15 marks)', 1),
('R20', 'CIA', 'drawing_mid_subjective_marks', '15.00', 'FLOAT', 'R20: Engineering Drawing subjective test for 15 marks', 1),
('R20', 'CIA', 'drawing_day_to_day_marks', '15.00', 'FLOAT', 'R20: Engineering Drawing classwork reports for 15 marks', 1),

-- SEE Evaluation
('R20', 'SEE', 'theory_see_max_marks', '70.00', 'FLOAT', 'R20: Semester End Examination carries 70 marks', 1),
('R20', 'SEE', 'theory_compulsory_q1_marks', '20.00', 'FLOAT', 'R20: Q1 contains 10 questions x 2 marks = 20 marks', 1),
('R20', 'SEE', 'theory_choice_group_marks', '10.00', 'FLOAT', 'R20: Q2 to Q11 carry 10 marks per choice group', 1),
('R20', 'SEE', 'composite_split_max_marks', '35.00', 'FLOAT', 'R20: Composite split subjects carry 35M each', 1),
('R20', 'SEE', 'composite_q1_marks', '5.00', 'FLOAT', 'R20: Split subjects have 5 sub-questions x 1 mark', 1),
('R20', 'SEE', 'drawing_see_max_marks', '70.00', 'FLOAT', 'R20: Engineering Drawing SEE carries 70 marks', 1),

-- Project & Internship
('R20', 'GENERAL', 'project_total_marks', '200.00', 'FLOAT', 'R20: Project total marks', 1),
('R20', 'GENERAL', 'project_internal_marks', '60.00', 'FLOAT', 'R20: Project internal marks', 1),
('R20', 'GENERAL', 'project_external_marks', '140.00', 'FLOAT', 'R20: Project external viva marks', 1),
('R20', 'GENERAL', 'summer_internship_marks', '50.00', 'FLOAT', 'R20: Summer Internship evaluated for 50 marks', 1),
('R20', 'GENERAL', 'full_internship_marks', '100.00', 'FLOAT', 'R20: Full semester internship marks', 1),

-- Passing Criteria
('R20', 'GRADING', 'see_min_pass_percentage', '35.00', 'FLOAT', 'R20: Minimum 35% in End Examination', 1),
('R20', 'GRADING', 'aggregate_min_pass_pct', '40.00', 'FLOAT', 'R20: Minimum 40% aggregate (Mid + End examination)', 1),
('R20', 'GRADING', 'mandatory_course_pass_pct', '40.00', 'FLOAT', 'R20: Zero-credit course passing mark', 1),

-- Attendance Compliance
('R20', 'ATTENDANCE', 'attendance_min_aggregate_pct', '75.00', 'FLOAT', 'R20: Minimum aggregate attendance requirement', 1),
('R20', 'ATTENDANCE', 'attendance_min_subject_pct', '40.00', 'FLOAT', 'R20: Minimum attendance per individual course', 1),
('R20', 'ATTENDANCE', 'attendance_condone_floor_pct', '65.00', 'FLOAT', 'R20: Shortage below 65% shall in no case be condoned', 1),

-- Attainment
('R20', 'ATTAINMENT', 'attainment_direct_cia_weight', '0.30', 'FLOAT', 'R20: Direct CO Attainment internal component weightage', 1),
('R20', 'ATTAINMENT', 'attainment_direct_see_weight', '0.70', 'FLOAT', 'R20: Direct CO Attainment external component weightage', 1),
('R20', 'ATTAINMENT', 'co_target_percentage', '60.00', 'FLOAT', 'R20: Minimum % a student must score in a CO', 1),
('R20', 'ATTAINMENT', 'attainment_level_3_threshold', '70.00', 'FLOAT', 'R20: Cohort % meeting target for Level 3', 1),
('R20', 'ATTAINMENT', 'attainment_level_2_threshold', '60.00', 'FLOAT', 'R20: Cohort % meeting target for Level 2', 1),
('R20', 'ATTAINMENT', 'attainment_level_1_threshold', '50.00', 'FLOAT', 'R20: Cohort % meeting target for Level 1', 1),
('R20', 'ATTAINMENT', 'overall_direct_weight', '0.80', 'FLOAT', 'R20: PO/PSO Direct weightage (80%)', 1),
('R20', 'ATTAINMENT', 'overall_indirect_weight', '0.20', 'FLOAT', 'R20: PO/PSO Indirect weightage (20%)', 1),

-- Grading Bands & Class Award
('R20', 'GRADING', 'grade_bands_json', '[{"min":90,"max":100,"grade":"A+","points":10},{"min":80,"max":89,"grade":"A","points":9},{"min":70,"max":79,"grade":"B","points":8},{"min":60,"max":69,"grade":"C","points":7},{"min":50,"max":59,"grade":"D","points":6},{"min":40,"max":49,"grade":"E","points":5},{"min":0,"max":39,"grade":"F","points":0}]', 'JSON', 'R20: 10-point absolute grading conversion scale', 1),
('R20', 'GRADING', 'class_award_json', '[{"class":"First Class with Distinction","min_cgpa":7.5},{"class":"First Class","min_cgpa":6.5,"max_cgpa":7.49},{"class":"Second Class","min_cgpa":5.5,"max_cgpa":6.49},{"class":"Pass Class","min_cgpa":5.0,"max_cgpa":5.49}]', 'JSON', 'R20: CGPA cutoffs for Distinction, First, Second, Pass', 1),
('R20', 'GENERAL', 'marks_entry_grace_days', '15', 'INT', 'Default days after semester end before mark edit forms auto-lock', 1),

-- ============================ R19 REGULATION ============================
-- CIA Evaluation
('R19', 'CIA', 'theory_mid_better_weight', '0.80', 'FLOAT', 'R19: 80% weightage given to the better mid examination', 1),
('R19', 'CIA', 'theory_mid_lesser_weight', '0.20', 'FLOAT', 'R19: 20% weightage given to the lower mid examination', 1),
('R19', 'CIA', 'theory_cia_total_marks', '30.00', 'FLOAT', 'R19: Continuous Internal Assessment carries 30 marks', 1),
('R19', 'CIA', 'theory_mid_subjective_condensed', '15.00', 'FLOAT', 'R19: Subjective marks condensed to 15 marks', 1),
('R19', 'CIA', 'theory_mid_objective_marks', '10.00', 'FLOAT', 'R19: Objective test carries 10 marks', 1),
('R19', 'CIA', 'theory_assignment_marks', '5.00', 'FLOAT', 'R19: Assignment carries 5 marks', 1),
('R19', 'CIA', 'lab_cia_day_to_day_marks', '15.00', 'FLOAT', 'R19: Practical day-to-day evaluation carries 15 marks', 1),
('R19', 'CIA', 'lab_cia_internal_test_marks', '15.00', 'FLOAT', 'R19: Practical internal test carries 15 marks', 1),
('R19', 'CIA', 'drawing_mid_subjective_marks', '15.00', 'FLOAT', 'R19: Engineering Drawing subjective test carries 15 marks', 1),
('R19', 'CIA', 'drawing_day_to_day_marks', '15.00', 'FLOAT', 'R19: Engineering Drawing classwork carries 15 marks', 1),

-- SEE Evaluation
('R19', 'SEE', 'theory_see_max_marks', '70.00', 'FLOAT', 'R19: Semester End Examination carries 70 marks', 1),
('R19', 'SEE', 'theory_compulsory_q1_marks', '20.00', 'FLOAT', 'R19: Q1 compulsory questions carry 20 marks', 1),
('R19', 'SEE', 'theory_choice_group_marks', '10.00', 'FLOAT', 'R19: Choice groups carry 10 marks each', 1),
('R19', 'SEE', 'composite_split_max_marks', '35.00', 'FLOAT', 'R19: Composite split subjects carry 35M each', 1),
('R19', 'SEE', 'composite_q1_marks', '5.00', 'FLOAT', 'R19: Split subjects have 5 sub-questions x 1 mark', 1),
('R19', 'SEE', 'drawing_see_max_marks', '70.00', 'FLOAT', 'R19: Drawing SEE carries 70 marks', 1),

-- Project & Internship
('R19', 'GENERAL', 'project_total_marks', '200.00', 'FLOAT', 'R19: Project total marks', 1),
('R19', 'GENERAL', 'project_internal_marks', '60.00', 'FLOAT', 'R19: Project internal evaluation marks', 1),
('R19', 'GENERAL', 'project_external_marks', '140.00', 'FLOAT', 'R19: Project external viva examination marks', 1),
('R19', 'GENERAL', 'summer_internship_marks', '50.00', 'FLOAT', 'R19: Summer internship marks', 1),
('R19', 'GENERAL', 'full_internship_marks', '100.00', 'FLOAT', 'R19: Full semester internship marks', 1),

-- Passing Criteria
('R19', 'GRADING', 'see_min_pass_percentage', '35.00', 'FLOAT', 'R19: Minimum 35% in End Examination', 1),
('R19', 'GRADING', 'aggregate_min_pass_pct', '40.00', 'FLOAT', 'R19: Minimum 40% aggregate passing criterion', 1),
('R19', 'GRADING', 'mandatory_course_pass_pct', '40.00', 'FLOAT', 'R19: Mandatory course pass mark', 1),

-- Attendance Compliance
('R19', 'ATTENDANCE', 'attendance_min_aggregate_pct', '75.00', 'FLOAT', 'R19: Minimum aggregate attendance requirement', 1),
('R19', 'ATTENDANCE', 'attendance_min_subject_pct', '40.00', 'FLOAT', 'R19: Minimum attendance per individual course', 1),
('R19', 'ATTENDANCE', 'attendance_condone_floor_pct', '65.00', 'FLOAT', 'R19: Shortage below 65% shall in no case be condoned', 1),

-- Attainment
('R19', 'ATTAINMENT', 'attainment_direct_cia_weight', '0.30', 'FLOAT', 'R19: Direct CO Attainment internal component weightage', 1),
('R19', 'ATTAINMENT', 'attainment_direct_see_weight', '0.70', 'FLOAT', 'R19: Direct CO Attainment external component weightage', 1),
('R19', 'ATTAINMENT', 'co_target_percentage', '60.00', 'FLOAT', 'R19: Minimum % a student must score in a CO', 1),
('R19', 'ATTAINMENT', 'attainment_level_3_threshold', '70.00', 'FLOAT', 'R19: Percentage of cohort meeting target for Level 3', 1),
('R19', 'ATTAINMENT', 'attainment_level_2_threshold', '60.00', 'FLOAT', 'R19: Percentage of cohort meeting target for Level 2', 1),
('R19', 'ATTAINMENT', 'attainment_level_1_threshold', '50.00', 'FLOAT', 'R19: Percentage of cohort meeting target for Level 1', 1),
('R19', 'ATTAINMENT', 'overall_direct_weight', '0.80', 'FLOAT', 'R19: PO/PSO Direct weightage (80%)', 1),
('R19', 'ATTAINMENT', 'overall_indirect_weight', '0.20', 'FLOAT', 'R19: PO/PSO Indirect weightage (20%)', 1),

-- Grading Bands & Class Award
('R19', 'GRADING', 'grade_bands_json', '[{"min":90,"max":100,"grade":"A+","points":10},{"min":80,"max":89,"grade":"A","points":9},{"min":70,"max":79,"grade":"B","points":8},{"min":60,"max":69,"grade":"C","points":7},{"min":50,"max":59,"grade":"D","points":6},{"min":40,"max":49,"grade":"E","points":5},{"min":0,"max":39,"grade":"F","points":0}]', 'JSON', 'R19: 10-point absolute grading conversion scale', 1),
('R19', 'GRADING', 'class_award_json', '[{"class":"First Class with Distinction","min_cgpa":7.5},{"class":"First Class","min_cgpa":6.5,"max_cgpa":7.49},{"class":"Second Class","min_cgpa":5.5,"max_cgpa":6.49},{"class":"Pass Class","min_cgpa":5.0,"max_cgpa":5.49}]', 'JSON', 'R19: CGPA cutoffs for Distinction, First, Second, Pass', 1),
('R19', 'GENERAL', 'marks_entry_grace_days', '15', 'INT', 'Default days after semester end before mark edit forms auto-lock', 1)

ON DUPLICATE KEY UPDATE
  `category` = VALUES(`category`),
  `setting_value` = VALUES(`setting_value`),
  `data_type` = VALUES(`data_type`),
  `description` = VALUES(`description`),
  `is_editable` = VALUES(`is_editable`);
