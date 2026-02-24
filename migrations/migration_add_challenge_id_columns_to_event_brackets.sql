-- Migration: add explicit challenge references to event_brackets
SET @db_name := DATABASE();

SET @has_sf1 := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'event_brackets'
      AND COLUMN_NAME = 'sf1_challenge_id'
);

SET @has_sf2 := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'event_brackets'
      AND COLUMN_NAME = 'sf2_challenge_id'
);

SET @has_final := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'event_brackets'
      AND COLUMN_NAME = 'final_challenge_id'
);

SET @has_third := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'event_brackets'
      AND COLUMN_NAME = 'third_challenge_id'
);

SET @sql_sf1 := IF(
    @has_sf1 = 0,
    'ALTER TABLE event_brackets ADD COLUMN sf1_challenge_id INT NULL AFTER sf1_team2_id',
    'SELECT ''event_brackets.sf1_challenge_id already exists'' AS message'
);
PREPARE stmt_sf1 FROM @sql_sf1;
EXECUTE stmt_sf1;
DEALLOCATE PREPARE stmt_sf1;

SET @sql_sf2 := IF(
    @has_sf2 = 0,
    'ALTER TABLE event_brackets ADD COLUMN sf2_challenge_id INT NULL AFTER sf2_team2_id',
    'SELECT ''event_brackets.sf2_challenge_id already exists'' AS message'
);
PREPARE stmt_sf2 FROM @sql_sf2;
EXECUTE stmt_sf2;
DEALLOCATE PREPARE stmt_sf2;

SET @sql_final := IF(
    @has_final = 0,
    'ALTER TABLE event_brackets ADD COLUMN final_challenge_id INT NULL AFTER sf2_score2',
    'SELECT ''event_brackets.final_challenge_id already exists'' AS message'
);
PREPARE stmt_final FROM @sql_final;
EXECUTE stmt_final;
DEALLOCATE PREPARE stmt_final;

SET @sql_third := IF(
    @has_third = 0,
    'ALTER TABLE event_brackets ADD COLUMN third_challenge_id INT NULL AFTER final_challenge_id',
    'SELECT ''event_brackets.third_challenge_id already exists'' AS message'
);
PREPARE stmt_third FROM @sql_third;
EXECUTE stmt_third;
DEALLOCATE PREPARE stmt_third;
