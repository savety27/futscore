INSERT INTO teams (id, name, is_active)
VALUES (1, 'Team Test', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = VALUES(is_active);
