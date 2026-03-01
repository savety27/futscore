-- Ensure integration-test schema stays aligned with required constraints.
UPDATE players
SET name = TRIM(name)
WHERE name IS NOT NULL;

SET @schema_name = DATABASE();

SET @global_unique_name_index = (
    SELECT index_name
    FROM (
        SELECT s.index_name
        FROM information_schema.statistics AS s
        WHERE s.table_schema = @schema_name
          AND s.table_name = 'players'
          AND s.non_unique = 0
        GROUP BY s.index_name
        HAVING COUNT(*) = 1
           AND SUM(CASE WHEN s.column_name = 'name' THEN 1 ELSE 0 END) = 1
    ) AS unique_name_indexes
    LIMIT 1
);

SET @drop_global_unique_name_sql = IF(
    @global_unique_name_index IS NULL,
    'DO 1',
    CONCAT('ALTER TABLE players DROP INDEX `', REPLACE(@global_unique_name_index, '`', '``'), '`')
);

PREPARE drop_global_unique_name_stmt FROM @drop_global_unique_name_sql;
EXECUTE drop_global_unique_name_stmt;
DEALLOCATE PREPARE drop_global_unique_name_stmt;

SET @has_unique_player_team_name = (
    SELECT COUNT(*)
    FROM (
        SELECT s.index_name
        FROM information_schema.statistics AS s
        WHERE s.table_schema = @schema_name
          AND s.table_name = 'players'
          AND s.non_unique = 0
        GROUP BY s.index_name
        HAVING COUNT(*) = 2
           AND SUM(CASE WHEN s.column_name = 'team_id' THEN 1 ELSE 0 END) = 1
           AND SUM(CASE WHEN s.column_name = 'name' THEN 1 ELSE 0 END) = 1
    ) AS unique_team_name_indexes
);

SET @create_unique_player_team_name_sql = IF(
    @has_unique_player_team_name = 0,
    'ALTER TABLE players ADD CONSTRAINT uq_players_team_name UNIQUE (team_id, name)',
    'DO 1'
);

PREPARE create_unique_player_team_name_stmt FROM @create_unique_player_team_name_sql;
EXECUTE create_unique_player_team_name_stmt;
DEALLOCATE PREPARE create_unique_player_team_name_stmt;
