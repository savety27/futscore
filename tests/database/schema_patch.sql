-- Ensure integration-test schema stays aligned with required constraints.
UPDATE players
SET name = TRIM(name)
WHERE name IS NOT NULL;

SET @schema_name = DATABASE();

SET @has_unique_player_name = (
    SELECT COUNT(*)
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
);

SET @create_unique_player_name_sql = IF(
    @has_unique_player_name = 0,
    'ALTER TABLE players ADD CONSTRAINT uq_players_name UNIQUE (name)',
    'SELECT 1'
);

PREPARE create_unique_player_name_stmt FROM @create_unique_player_name_sql;
EXECUTE create_unique_player_name_stmt;
DEALLOCATE PREPARE create_unique_player_name_stmt;
