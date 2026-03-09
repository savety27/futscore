-- Migration: add registry-backed cross-table NIK uniqueness and normalized player-name uniqueness
-- Date: 2026-03-10

UPDATE players
SET name = TRIM(name)
WHERE name IS NOT NULL;

UPDATE players
SET nik = TRIM(nik)
WHERE nik IS NOT NULL;

UPDATE perangkat
SET no_ktp = TRIM(no_ktp)
WHERE no_ktp IS NOT NULL;

SET @schema_name = DATABASE();

SET @has_perangkat_gender_column = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @schema_name
      AND table_name = 'perangkat'
      AND column_name = 'gender'
);

SET @add_perangkat_gender_sql = IF(
    @has_perangkat_gender_column = 0,
    'ALTER TABLE perangkat ADD COLUMN gender VARCHAR(20) NULL AFTER age',
    'DO 1'
);

PREPARE add_perangkat_gender_stmt FROM @add_perangkat_gender_sql;
EXECUTE add_perangkat_gender_stmt;
DEALLOCATE PREPARE add_perangkat_gender_stmt;

DELIMITER $$

DROP PROCEDURE IF EXISTS validate_identity_uniqueness_constraints $$
CREATE PROCEDURE validate_identity_uniqueness_constraints()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM (
            SELECT COALESCE(team_id, 0) AS team_scope_id, TRIM(name) AS name_normalized
            FROM players
            GROUP BY COALESCE(team_id, 0), TRIM(name)
            HAVING COUNT(*) > 1
        ) AS duplicate_player_names
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot apply migration: duplicate player names exist within the same team scope after TRIM().';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM (
            SELECT normalized_nik
            FROM (
                SELECT TRIM(nik) AS normalized_nik
                FROM players
                WHERE nik IS NOT NULL AND TRIM(nik) <> ''
                UNION ALL
                SELECT TRIM(no_ktp) AS normalized_nik
                FROM perangkat
                WHERE no_ktp IS NOT NULL AND TRIM(no_ktp) <> ''
            ) AS normalized_identities
            GROUP BY normalized_nik
            HAVING COUNT(*) > 1
        ) AS duplicate_nik_values
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot apply migration: duplicate NIK/KTP values exist across players and perangkat.';
    END IF;
END $$

CALL validate_identity_uniqueness_constraints() $$
DROP PROCEDURE validate_identity_uniqueness_constraints $$

DELIMITER ;

SET @has_players_team_scope_id = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @schema_name
      AND table_name = 'players'
      AND column_name = 'team_scope_id'
);

SET @add_players_team_scope_id_sql = IF(
    @has_players_team_scope_id = 0,
    'ALTER TABLE players ADD COLUMN team_scope_id INT AS (IFNULL(team_id, 0)) STORED AFTER team_id',
    'DO 1'
);

PREPARE add_players_team_scope_id_stmt FROM @add_players_team_scope_id_sql;
EXECUTE add_players_team_scope_id_stmt;
DEALLOCATE PREPARE add_players_team_scope_id_stmt;

SET @has_players_name_normalized = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @schema_name
      AND table_name = 'players'
      AND column_name = 'name_normalized'
);

SET @add_players_name_normalized_sql = IF(
    @has_players_name_normalized = 0,
    'ALTER TABLE players ADD COLUMN name_normalized VARCHAR(100) AS (TRIM(name)) STORED AFTER name',
    'DO 1'
);

PREPARE add_players_name_normalized_stmt FROM @add_players_name_normalized_sql;
EXECUTE add_players_name_normalized_stmt;
DEALLOCATE PREPARE add_players_name_normalized_stmt;

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

SET @legacy_team_name_unique_index = (
    SELECT index_name
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
    LIMIT 1
);

SET @drop_legacy_team_name_unique_sql = IF(
    @legacy_team_name_unique_index IS NULL,
    'DO 1',
    CONCAT('ALTER TABLE players DROP INDEX `', REPLACE(@legacy_team_name_unique_index, '`', '``'), '`')
);

PREPARE drop_legacy_team_name_unique_stmt FROM @drop_legacy_team_name_unique_sql;
EXECUTE drop_legacy_team_name_unique_stmt;
DEALLOCATE PREPARE drop_legacy_team_name_unique_stmt;

SET @has_unique_player_team_scope_name = (
    SELECT COUNT(*)
    FROM (
        SELECT s.index_name
        FROM information_schema.statistics AS s
        WHERE s.table_schema = @schema_name
          AND s.table_name = 'players'
          AND s.non_unique = 0
        GROUP BY s.index_name
        HAVING COUNT(*) = 2
           AND SUM(CASE WHEN s.column_name = 'team_scope_id' THEN 1 ELSE 0 END) = 1
           AND SUM(CASE WHEN s.column_name = 'name_normalized' THEN 1 ELSE 0 END) = 1
    ) AS unique_team_scope_name_indexes
);

SET @create_unique_player_team_scope_name_sql = IF(
    @has_unique_player_team_scope_name = 0,
    'ALTER TABLE players ADD CONSTRAINT uq_players_team_scope_name UNIQUE (team_scope_id, name_normalized)',
    'DO 1'
);

PREPARE create_unique_player_team_scope_name_stmt FROM @create_unique_player_team_scope_name_sql;
EXECUTE create_unique_player_team_scope_name_stmt;
DEALLOCATE PREPARE create_unique_player_team_scope_name_stmt;

CREATE TABLE IF NOT EXISTS nik_registry (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nik CHAR(16) NOT NULL,
    owner_type ENUM('player', 'perangkat') NOT NULL,
    owner_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @has_unique_nik_registry_nik = (
    SELECT COUNT(*)
    FROM (
        SELECT s.index_name
        FROM information_schema.statistics AS s
        WHERE s.table_schema = @schema_name
          AND s.table_name = 'nik_registry'
          AND s.non_unique = 0
        GROUP BY s.index_name
        HAVING COUNT(*) = 1
           AND SUM(CASE WHEN s.column_name = 'nik' THEN 1 ELSE 0 END) = 1
    ) AS unique_nik_indexes
);

SET @create_unique_nik_registry_nik_sql = IF(
    @has_unique_nik_registry_nik = 0,
    'ALTER TABLE nik_registry ADD CONSTRAINT uq_nik_registry_nik UNIQUE (nik)',
    'DO 1'
);

PREPARE create_unique_nik_registry_nik_stmt FROM @create_unique_nik_registry_nik_sql;
EXECUTE create_unique_nik_registry_nik_stmt;
DEALLOCATE PREPARE create_unique_nik_registry_nik_stmt;

SET @has_unique_nik_registry_owner = (
    SELECT COUNT(*)
    FROM (
        SELECT s.index_name
        FROM information_schema.statistics AS s
        WHERE s.table_schema = @schema_name
          AND s.table_name = 'nik_registry'
          AND s.non_unique = 0
        GROUP BY s.index_name
        HAVING COUNT(*) = 2
           AND SUM(CASE WHEN s.column_name = 'owner_type' THEN 1 ELSE 0 END) = 1
           AND SUM(CASE WHEN s.column_name = 'owner_id' THEN 1 ELSE 0 END) = 1
    ) AS unique_owner_indexes
);

SET @create_unique_nik_registry_owner_sql = IF(
    @has_unique_nik_registry_owner = 0,
    'ALTER TABLE nik_registry ADD CONSTRAINT uq_nik_registry_owner UNIQUE (owner_type, owner_id)',
    'DO 1'
);

PREPARE create_unique_nik_registry_owner_stmt FROM @create_unique_nik_registry_owner_sql;
EXECUTE create_unique_nik_registry_owner_stmt;
DEALLOCATE PREPARE create_unique_nik_registry_owner_stmt;

DELETE FROM nik_registry;

INSERT INTO nik_registry (nik, owner_type, owner_id)
SELECT TRIM(nik), 'player', id
FROM players
WHERE nik IS NOT NULL
  AND TRIM(nik) <> '';

INSERT INTO nik_registry (nik, owner_type, owner_id)
SELECT TRIM(no_ktp), 'perangkat', id
FROM perangkat
WHERE no_ktp IS NOT NULL
  AND TRIM(no_ktp) <> '';

DROP TRIGGER IF EXISTS trg_players_nik_registry_ai;
DROP TRIGGER IF EXISTS trg_players_nik_registry_au;
DROP TRIGGER IF EXISTS trg_players_nik_registry_ad;
DROP TRIGGER IF EXISTS trg_perangkat_nik_registry_ai;
DROP TRIGGER IF EXISTS trg_perangkat_nik_registry_au;
DROP TRIGGER IF EXISTS trg_perangkat_nik_registry_ad;

DELIMITER $$

CREATE TRIGGER trg_players_nik_registry_ai
AFTER INSERT ON players
FOR EACH ROW
BEGIN
    IF NEW.nik IS NOT NULL AND TRIM(NEW.nik) <> '' THEN
        INSERT INTO nik_registry (nik, owner_type, owner_id)
        VALUES (TRIM(NEW.nik), 'player', NEW.id);
    END IF;
END $$

CREATE TRIGGER trg_players_nik_registry_au
AFTER UPDATE ON players
FOR EACH ROW
BEGIN
    DECLARE new_nik CHAR(16);
    DECLARE existing_registry_rows INT DEFAULT 0;

    SET new_nik = NULLIF(TRIM(COALESCE(NEW.nik, '')), '');
    SELECT COUNT(*)
    INTO existing_registry_rows
    FROM nik_registry
    WHERE owner_type = 'player'
      AND owner_id = OLD.id;

    IF new_nik IS NULL THEN
        DELETE FROM nik_registry
        WHERE owner_type = 'player'
          AND owner_id = OLD.id;
    ELSEIF existing_registry_rows = 0 THEN
        INSERT INTO nik_registry (nik, owner_type, owner_id)
        VALUES (new_nik, 'player', NEW.id);
    ELSE
        UPDATE nik_registry
        SET nik = new_nik
        WHERE owner_type = 'player'
          AND owner_id = OLD.id;
    END IF;
END $$

CREATE TRIGGER trg_players_nik_registry_ad
AFTER DELETE ON players
FOR EACH ROW
BEGIN
    DELETE FROM nik_registry
    WHERE owner_type = 'player'
      AND owner_id = OLD.id;
END $$

CREATE TRIGGER trg_perangkat_nik_registry_ai
AFTER INSERT ON perangkat
FOR EACH ROW
BEGIN
    IF NEW.no_ktp IS NOT NULL AND TRIM(NEW.no_ktp) <> '' THEN
        INSERT INTO nik_registry (nik, owner_type, owner_id)
        VALUES (TRIM(NEW.no_ktp), 'perangkat', NEW.id);
    END IF;
END $$

CREATE TRIGGER trg_perangkat_nik_registry_au
AFTER UPDATE ON perangkat
FOR EACH ROW
BEGIN
    DECLARE new_nik CHAR(16);
    DECLARE existing_registry_rows INT DEFAULT 0;

    SET new_nik = NULLIF(TRIM(COALESCE(NEW.no_ktp, '')), '');
    SELECT COUNT(*)
    INTO existing_registry_rows
    FROM nik_registry
    WHERE owner_type = 'perangkat'
      AND owner_id = OLD.id;

    IF new_nik IS NULL THEN
        DELETE FROM nik_registry
        WHERE owner_type = 'perangkat'
          AND owner_id = OLD.id;
    ELSEIF existing_registry_rows = 0 THEN
        INSERT INTO nik_registry (nik, owner_type, owner_id)
        VALUES (new_nik, 'perangkat', NEW.id);
    ELSE
        UPDATE nik_registry
        SET nik = new_nik
        WHERE owner_type = 'perangkat'
          AND owner_id = OLD.id;
    END IF;
END $$

CREATE TRIGGER trg_perangkat_nik_registry_ad
AFTER DELETE ON perangkat
FOR EACH ROW
BEGIN
    DELETE FROM nik_registry
    WHERE owner_type = 'perangkat'
      AND owner_id = OLD.id;
END $$

DELIMITER ;
