-- Migration: add gender column to perangkat
-- Date: 2026-03-07

SET @schema_name := DATABASE();

SET @has_perangkat_gender := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'perangkat'
      AND COLUMN_NAME = 'gender'
);

SET @sql_add_perangkat_gender := IF(
    @has_perangkat_gender = 0,
    'ALTER TABLE perangkat ADD COLUMN gender VARCHAR(20) NULL AFTER age',
    'SELECT ''perangkat.gender already exists'' AS message'
);

PREPARE stmt_add_perangkat_gender FROM @sql_add_perangkat_gender;
EXECUTE stmt_add_perangkat_gender;
DEALLOCATE PREPARE stmt_add_perangkat_gender;
