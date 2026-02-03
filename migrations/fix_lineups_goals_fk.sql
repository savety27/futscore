-- Migration: Fix Lineup and Goals Foreign Key Constraints
-- Description: Redirect foreign keys from matches table to challenges table

-- Update lineups table
ALTER TABLE lineups DROP FOREIGN KEY IF EXISTS lineups_ibfk_1;
ALTER TABLE lineups ADD CONSTRAINT lineups_ibfk_1 FOREIGN KEY (match_id) REFERENCES challenges(id);

-- Update goals table
ALTER TABLE goals DROP FOREIGN KEY IF EXISTS goals_ibfk_1;
ALTER TABLE goals ADD CONSTRAINT goals_ibfk_1 FOREIGN KEY (match_id) REFERENCES challenges(id);
