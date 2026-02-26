-- Migration: add half column to goals
-- Date     : 2026-02-26
-- Purpose  : support separating goals for Babak 1 and Babak 2

SET @half_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'goals'
      AND COLUMN_NAME = 'half'
);

SET @sql := IF(
    @half_exists = 0,
    'ALTER TABLE goals ADD COLUMN half TINYINT(1) NOT NULL DEFAULT 1 AFTER minute',
    'SELECT ''goals.half already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
