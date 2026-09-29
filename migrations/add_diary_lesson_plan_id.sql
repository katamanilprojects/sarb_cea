-- Migration: Add lesson_plan_id to diary table for end-of-course reconciliation
-- Allows daily attendance to remain purely custom text with zero friction,
-- while enabling end-of-course mapping to the lesson plan and its CO/Unit metadata.
-- Safe, idempotent script for production deployment.

START TRANSACTION;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'diary' AND COLUMN_NAME = 'lesson_plan_id');
SET @sql = IF(@col_exists = 0, 
              "ALTER TABLE `diary` ADD COLUMN `lesson_plan_id` INT(11) NULL DEFAULT NULL, ADD KEY `idx_diary_lp` (`lesson_plan_id`);", 
              "SELECT 1;");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;
