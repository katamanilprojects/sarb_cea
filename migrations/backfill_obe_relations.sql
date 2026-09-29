-- ============================================================================
-- SARB_CEA: OBE & ERP Multi-Regulation Decoupling Backfill (v2)
-- Safely populate curriculum_subjects, student_batches, and link subjects/classes
-- Author: Antigravity Agent
-- Date: 2026-09-29
-- ============================================================================

START TRANSACTION;

-- Step 1: Seed master curriculum_subjects from existing distinct subjects
INSERT IGNORE INTO `curriculum_subjects` 
    (`prog_id`, `reg_id`, `spec_id`, `yearsem`, `subject_sno`, `subcode`, `sub_fullname`, `sub_shortname`, `sub_type`, `lecture_hours`, `tutorial_hours`, `practical_hours`, `credits`, `status`)
SELECT 
    sp.prog_id,
    c.reg_id,
    c.spec_id,
    c.yearsem,
    CAST(COALESCE(NULLIF(s.subject_sno, ''), '1') AS UNSIGNED) AS subject_sno,
    TRIM(s.subcode) AS subcode,
    TRIM(s.sub_fullname) AS sub_fullname,
    TRIM(s.sub_shortname) AS sub_shortname,
    TRIM(s.sub_type) AS sub_type,
    IF(LOWER(s.sub_type) LIKE '%lab%', 0.0, 3.0) AS lecture_hours,
    0.0 AS tutorial_hours,
    IF(LOWER(s.sub_type) LIKE '%lab%', 3.0, 0.0) AS practical_hours,
    IF(LOWER(s.sub_type) LIKE '%lab%', 1.5, 3.0) AS credits,
    1 AS status
FROM `subjects` s
JOIN `classes` c ON s.class_id = c.id
JOIN `specialization` sp ON c.spec_id = sp.id
WHERE c.reg_id IS NOT NULL AND TRIM(s.subcode) != ''
GROUP BY c.reg_id, c.spec_id, c.yearsem, TRIM(s.subcode);

-- Step 2: Backfill subjects.curr_sub_id by matching course catalog code + regulation + specialization
UPDATE `subjects` s
JOIN `classes` c ON s.class_id = c.id
JOIN `curriculum_subjects` cs ON TRIM(s.subcode) = cs.subcode 
                             AND c.reg_id = cs.reg_id 
                             AND c.spec_id = cs.spec_id
                             AND c.yearsem = cs.yearsem
SET s.curr_sub_id = cs.id
WHERE s.curr_sub_id IS NULL;

-- Step 3: Populate student_batches based on degree duration and yearsem
INSERT IGNORE INTO `student_batches` (`program_id`, `regulation_id`, `batch_name`, `admission_year`, `graduation_year`)
SELECT 
    sp.prog_id,
    c.reg_id,
    CONCAT(
        CAST(SUBSTRING(c.acad_year, 1, 4) AS UNSIGNED) - 
        CASE 
            WHEN c.yearsem LIKE '%IV%' THEN 3
            WHEN c.yearsem LIKE '%III%' THEN 2
            WHEN c.yearsem LIKE '%II%' THEN 1
            ELSE 0
        END,
        '-',
        CAST(SUBSTRING(c.acad_year, 1, 4) AS UNSIGNED) - 
        CASE 
            WHEN c.yearsem LIKE '%IV%' THEN 3
            WHEN c.yearsem LIKE '%III%' THEN 2
            WHEN c.yearsem LIKE '%II%' THEN 1
            ELSE 0
        END + 
        IF(p.prog_shortname IN ('M.Tech', 'MBA', 'M.Sc'), 2, 4)
    ) AS batch_name,
    CAST(SUBSTRING(c.acad_year, 1, 4) AS UNSIGNED) - 
    CASE 
        WHEN c.yearsem LIKE '%IV%' THEN 3
        WHEN c.yearsem LIKE '%III%' THEN 2
        WHEN c.yearsem LIKE '%II%' THEN 1
        ELSE 0
    END AS admission_year,
    CAST(SUBSTRING(c.acad_year, 1, 4) AS UNSIGNED) - 
    CASE 
        WHEN c.yearsem LIKE '%IV%' THEN 3
        WHEN c.yearsem LIKE '%III%' THEN 2
        WHEN c.yearsem LIKE '%II%' THEN 1
        ELSE 0
    END + 
    IF(p.prog_shortname IN ('M.Tech', 'MBA', 'M.Sc'), 2, 4) AS graduation_year
FROM `classes` c
JOIN `specialization` sp ON c.spec_id = sp.id
JOIN `programs` p ON sp.prog_id = p.id
WHERE c.reg_id IS NOT NULL AND c.acad_year REGEXP '^[0-9]{4}'
GROUP BY sp.prog_id, c.reg_id, batch_name;

-- Step 4: Link active and historical classes to student_batches
UPDATE `classes` c
JOIN `specialization` sp ON c.spec_id = sp.id
JOIN `programs` p ON sp.prog_id = p.id
JOIN `student_batches` sb ON sb.program_id = sp.prog_id 
                         AND sb.regulation_id = c.reg_id
                         AND sb.admission_year = (
                             CAST(SUBSTRING(c.acad_year, 1, 4) AS UNSIGNED) - 
                             CASE 
                                 WHEN c.yearsem LIKE '%IV%' THEN 3
                                 WHEN c.yearsem LIKE '%III%' THEN 2
                                 WHEN c.yearsem LIKE '%II%' THEN 1
                                 ELSE 0
                             END
                         )
SET c.batch_id = sb.id
WHERE c.batch_id IS NULL AND c.acad_year REGEXP '^[0-9]{4}';

-- Step 5: Backfill effective_from_year on existing po_pso records from their acad_year
UPDATE `po_pso`
SET `effective_from_year` = CAST(SUBSTRING(`acad_year`, 1, 4) AS UNSIGNED)
WHERE `effective_from_year` = 2020 AND `acad_year` REGEXP '^[0-9]{4}';

COMMIT;
