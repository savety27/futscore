-- Migration: enforce globally unique player name
-- Date: 2026-02-13

-- Optional cleanup: trim accidental leading/trailing spaces
UPDATE players
SET name = TRIM(name)
WHERE name IS NOT NULL;

-- Enforce global unique name across all teams
ALTER TABLE players
ADD CONSTRAINT uq_players_name UNIQUE (name);
