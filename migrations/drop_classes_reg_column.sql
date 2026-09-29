-- ============================================================================
-- SARB_CEA - SCHEMA NORMALIZATION MIGRATION
-- Drop redundant `reg` column from `classes` table
-- Regulation code is now dynamically resolved from `regulations.regulation` via `classes.reg_id`.
-- ============================================================================

START TRANSACTION;

-- Drop redundant `reg` column if it exists
SET @dbname = DATABASE();
SET @tablename = "classes";
SET @columnname = "reg";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_NAME = @tablename)
      AND (TABLE_SCHEMA = @dbname)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  "ALTER TABLE `classes` DROP COLUMN `reg`;",
  "SELECT 1;"
));
PREPARE dropColumnIfNotExists FROM @preparedStatement;
EXECUTE dropColumnIfNotExists;
DEALLOCATE PREPARE dropColumnIfNotExists;

COMMIT;
