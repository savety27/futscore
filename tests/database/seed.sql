INSERT INTO teams (id, name, sport_type, is_active)
VALUES (1, 'Team Test', 'Futsal', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    sport_type = VALUES(sport_type),
    is_active = VALUES(is_active);

INSERT INTO teams (id, name, sport_type, is_active)
VALUES (2, 'Team Test 2', 'Futsal', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    sport_type = VALUES(sport_type),
    is_active = VALUES(is_active);
