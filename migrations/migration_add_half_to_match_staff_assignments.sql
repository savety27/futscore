-- Migration: add half column to match_staff_assignments
-- Purpose  : support per-half staff assignment (Babak 1 / Babak 2)
-- Date     : 2026-02-27

SET @has_half_column := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'match_staff_assignments'
      AND COLUMN_NAME = 'half'
);

SET @sql_add_half_column := IF(
    @has_half_column = 0,
    'ALTER TABLE match_staff_assignments ADD COLUMN half TINYINT(1) NOT NULL DEFAULT 1 AFTER team_id',
    'SELECT "match_staff_assignments.half already exists"'
);
PREPARE stmt FROM @sql_add_half_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure only valid half values.
UPDATE match_staff_assignments
SET half = 1
WHERE half IS NULL OR half NOT IN (1, 2);

-- Replace old unique key (match_id, staff_id, team_id) with per-half unique key.
SET @has_old_unique := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'match_staff_assignments'
      AND INDEX_NAME = 'uniq_match_staff_team'
);

SET @sql_drop_old_unique := IF(
    @has_old_unique > 0,
    'ALTER TABLE match_staff_assignments DROP INDEX uniq_match_staff_team',
    'SELECT "uniq_match_staff_team not found"'
);
PREPARE stmt FROM @sql_drop_old_unique;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_new_unique := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'match_staff_assignments'
      AND INDEX_NAME = 'uniq_match_staff_team_half'
);

SET @sql_add_new_unique := IF(
    @has_new_unique = 0,
    'ALTER TABLE match_staff_assignments ADD UNIQUE KEY uniq_match_staff_team_half (match_id, staff_id, team_id, half)',
    'SELECT "uniq_match_staff_team_half already exists"'
);
PREPARE stmt FROM @sql_add_new_unique;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
