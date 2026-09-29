-- ============================================================================
-- SARB_CEA: OBE & ERP Multi-Regulation Decoupling Migration (v2)
-- Safe, Idempotent, and Backward-Compatible
-- Author: Antigravity Agent
-- Date: 2026-09-29
-- ============================================================================

START TRANSACTION;

-- 1. Programs: Add program_level ('UG', 'PG', 'PHD')
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'programs' AND COLUMN_NAME = 'program_level');
SET @sql = IF(@col_exists = 0, 
              "ALTER TABLE `programs` ADD COLUMN `program_level` ENUM('UG', 'PG', 'PHD') NOT NULL DEFAULT 'UG';", 
              "SELECT 1;");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `programs` 
SET `program_level` = 'PG' 
WHERE `prog_shortname` IN ('M.Tech', 'MCA', 'M.Sc', 'MBA') 
   OR `prog_fullname` LIKE '%Master%';

-- 2. Regulations: Add optional start_year and is_active metadata
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regulations' AND COLUMN_NAME = 'start_year');
SET @sql = IF(@col_exists = 0, 
              "ALTER TABLE `regulations` ADD COLUMN `start_year` INT(4) NULL, ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1;", 
              "SELECT 1;");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `regulations` SET `start_year` = 2019 WHERE `regulation` = 'R19' AND `start_year` IS NULL;
UPDATE `regulations` SET `start_year` = 2020 WHERE `regulation` = 'R20' AND `start_year` IS NULL;
UPDATE `regulations` SET `start_year` = 2023 WHERE `regulation` = 'R23' AND `start_year` IS NULL;
UPDATE `regulations` SET `start_year` = 2025 WHERE `regulation` = 'R25' AND `start_year` IS NULL;
UPDATE `regulations` SET `start_year` = 2026 WHERE `regulation` = 'R26' AND `start_year` IS NULL;

-- 3. Student Batches: Create 4-year student cohorts table
CREATE TABLE IF NOT EXISTS `student_batches` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `program_id` INT(5) NOT NULL,
  `regulation_id` INT(11) NOT NULL,
  `batch_name` VARCHAR(50) NOT NULL COMMENT 'e.g., 2023-2027',
  `admission_year` INT(4) NOT NULL,
  `graduation_year` INT(4) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_program_batch` (`program_id`, `batch_name`),
  KEY `idx_batch_reg` (`regulation_id`),
  CONSTRAINT `fk_sb_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sb_regulation` FOREIGN KEY (`regulation_id`) REFERENCES `regulations` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admitted student cohorts adhering to specific regulations';

-- 4. Classes: Add batch_id link
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND COLUMN_NAME = 'batch_id');
SET @sql = IF(@col_exists = 0, 
              "ALTER TABLE `classes` ADD COLUMN `batch_id` INT(11) NULL, ADD KEY `idx_classes_batch` (`batch_id`), ADD CONSTRAINT `fk_classes_batch` FOREIGN KEY (`batch_id`) REFERENCES `student_batches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;", 
              "SELECT 1;");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Subjects: Add curr_sub_id linking running offerings to master curriculum
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects' AND COLUMN_NAME = 'curr_sub_id');
SET @sql = IF(@col_exists = 0, 
              "ALTER TABLE `subjects` ADD COLUMN `curr_sub_id` INT(11) NULL, ADD KEY `idx_subjects_curr_sub` (`curr_sub_id`), ADD CONSTRAINT `fk_subjects_curriculum` FOREIGN KEY (`curr_sub_id`) REFERENCES `curriculum_subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;", 
              "SELECT 1;");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6. Diary: Safely append co_addressed at END of table
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'diary' AND COLUMN_NAME = 'co_addressed');
SET @sql = IF(@col_exists = 0, 
              "ALTER TABLE `diary` ADD COLUMN `co_addressed` INT(11) NULL DEFAULT NULL, ADD KEY `idx_diary_co` (`co_addressed`), ADD CONSTRAINT `fk_diary_co` FOREIGN KEY (`co_addressed`) REFERENCES `course_outcomes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;", 
              "SELECT 1;");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7. PO/PSO: Add reg_id, target_score, and effective_from_year versioning
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'po_pso' AND COLUMN_NAME = 'reg_id');
SET @sql = IF(@col_exists = 0, 
              "ALTER TABLE `po_pso` ADD COLUMN `reg_id` INT(11) NULL, ADD COLUMN `target_score` DECIMAL(3, 2) NOT NULL DEFAULT 2.00, ADD COLUMN `effective_from_year` INT(4) NOT NULL DEFAULT 2020, ADD KEY `idx_popso_reg` (`reg_id`), ADD KEY `idx_popso_effective` (`effective_from_year`);", 
              "SELECT 1;");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `po_pso` p
JOIN `regulations` r ON TRIM(p.regulation) = TRIM(r.regulation)
SET p.reg_id = r.id
WHERE p.reg_id IS NULL;

-- 8. Course Outcomes: Dual-referencing (curr_sub_id + sub_id), Bloom, and Target Threshold
ALTER TABLE `course_outcomes` MODIFY COLUMN `sub_id` INT(11) NULL;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_outcomes' AND COLUMN_NAME = 'curr_sub_id');
SET @sql = IF(@col_exists = 0, 
              "ALTER TABLE `course_outcomes` ADD COLUMN `curr_sub_id` INT(11) NULL, ADD COLUMN `bloom_level` VARCHAR(20) NOT NULL DEFAULT 'L3-Apply', ADD COLUMN `target_threshold_percent` DECIMAL(5, 2) NOT NULL DEFAULT 60.00, ADD KEY `idx_co_curr_sub` (`curr_sub_id`), ADD CONSTRAINT `fk_co_curr_sub` FOREIGN KEY (`curr_sub_id`) REFERENCES `curriculum_subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;", 
              "SELECT 1;");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 9. Lesson Plans: Estimated Diary / Course Delivery Plan Table
CREATE TABLE IF NOT EXISTS `lesson_plans` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sub_id` INT(5) NOT NULL COMMENT 'References subjects(id) for this offering instance',
  `unit_number` INT(2) NOT NULL COMMENT 'Unit 1 to Unit 5',
  `lecture_number` INT(3) NOT NULL COMMENT 'Sequential lecture number (1, 2, ... 50)',
  `planned_topic` VARCHAR(255) NOT NULL COMMENT 'Specific syllabus topic/concept planned',
  `co_id` INT(11) NOT NULL COMMENT 'Target Course Outcome (references course_outcomes.id)',
  `bloom_level` VARCHAR(20) NOT NULL DEFAULT 'L3-Apply' COMMENT 'Target Bloom level',
  `pedagogy` VARCHAR(50) NOT NULL DEFAULT 'Chalk & Talk' COMMENT 'Pedagogy: Chalk & Talk, PPT/LCD, Coding Demo, Video, Flipped',
  `reference_material` VARCHAR(255) NULL COMMENT 'Textbook / Chapter / URL citation',
  `planned_hours` INT(2) NOT NULL DEFAULT 1 COMMENT 'Duration planned in lecture periods',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_subject_lecture` (`sub_id`, `lecture_number`),
  KEY `idx_lp_co` (`co_id`),
  CONSTRAINT `fk_lp_subject` FOREIGN KEY (`sub_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_lp_co` FOREIGN KEY (`co_id`) REFERENCES `course_outcomes` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Course Delivery Schedule / Lesson Plan (Estimated Diary)';

COMMIT;
