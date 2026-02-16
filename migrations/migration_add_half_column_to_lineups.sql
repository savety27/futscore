-- Migration: add half column to lineups
-- Date: 2026-02-16
-- Purpose: support separate lineup entries for Babak 1 and Babak 2

SET @half_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'lineups'
      AND COLUMN_NAME = 'half'
);

SET @sql := IF(
    @half_exists = 0,
    'ALTER TABLE lineups ADD COLUMN half TINYINT(1) NOT NULL DEFAULT 1 AFTER position',
    'SELECT ''lineups.half already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
