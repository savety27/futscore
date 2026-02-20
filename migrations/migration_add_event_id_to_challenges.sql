-- Migration: Add event_id relation from challenges to events
-- Description: Adds nullable challenges.event_id, index, and FK to events(id).
-- Date: 2026-02-20

SET @db_name := DATABASE();

-- 1) Add column `event_id` if it does not exist.
SET @has_event_id_column := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'challenges'
      AND COLUMN_NAME = 'event_id'
);

SET @sql_add_event_id_column := IF(
    @has_event_id_column = 0,
    'ALTER TABLE `challenges` ADD COLUMN `event_id` INT NULL AFTER `expiry_date`',
    'SELECT "challenges.event_id already exists"'
);
PREPARE stmt FROM @sql_add_event_id_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Add index for faster filtering/join.
SET @has_event_id_index := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'challenges'
      AND INDEX_NAME = 'idx_challenges_event_id'
);

SET @sql_add_event_id_index := IF(
    @has_event_id_index = 0,
    'CREATE INDEX `idx_challenges_event_id` ON `challenges` (`event_id`)',
    'SELECT "idx_challenges_event_id already exists"'
);
PREPARE stmt FROM @sql_add_event_id_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Add foreign key constraint if it does not exist.
SET @has_fk_event_id := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @db_name
      AND TABLE_NAME = 'challenges'
      AND CONSTRAINT_NAME = 'fk_challenges_event_id'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql_add_fk_event_id := IF(
    @has_fk_event_id = 0,
    'ALTER TABLE `challenges` ADD CONSTRAINT `fk_challenges_event_id` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT "fk_challenges_event_id already exists"'
);
PREPARE stmt FROM @sql_add_fk_event_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optional backfill example (uncomment only if event names are guaranteed unique):
-- UPDATE challenges c
-- JOIN events e ON e.name = c.sport_type
-- SET c.event_id = e.id
-- WHERE c.event_id IS NULL
--   AND c.sport_type IS NOT NULL
--   AND c.sport_type <> '';
