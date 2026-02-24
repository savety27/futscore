-- Migration: add uniform choice columns for each team on challenges
SET @db_name := DATABASE();

SET @has_challenger_uniform_choices := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'challenges'
      AND COLUMN_NAME = 'challenger_uniform_choices'
);

SET @has_opponent_uniform_choices := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'challenges'
      AND COLUMN_NAME = 'opponent_uniform_choices'
);

SET @sql_challenger := IF(
    @has_challenger_uniform_choices = 0,
    'ALTER TABLE challenges ADD COLUMN challenger_uniform_choices VARCHAR(255) NULL AFTER match_official',
    'SELECT ''challenges.challenger_uniform_choices already exists'' AS message'
);

PREPARE stmt_challenger FROM @sql_challenger;
EXECUTE stmt_challenger;
DEALLOCATE PREPARE stmt_challenger;

SET @sql_opponent := IF(
    @has_opponent_uniform_choices = 0,
    'ALTER TABLE challenges ADD COLUMN opponent_uniform_choices VARCHAR(255) NULL AFTER challenger_uniform_choices',
    'SELECT ''challenges.opponent_uniform_choices already exists'' AS message'
);

PREPARE stmt_opponent FROM @sql_opponent;
EXECUTE stmt_opponent;
DEALLOCATE PREPARE stmt_opponent;
