-- Migration: Add lesson_plan_id to diary table for end-of-course reconciliation
-- Allows daily attendance to remain purely custom text with zero friction,
-- while enabling end-of-course mapping to the lesson plan and its CO/Unit metadata.

ALTER TABLE `diary` 
  ADD COLUMN `lesson_plan_id` int(11) NULL DEFAULT NULL,
  ADD KEY `idx_diary_lp` (`lesson_plan_id`);
