-- Convert teams.established_year from YEAR/INT to DATE while preserving existing values
-- Step 1: allow string manipulation
ALTER TABLE teams MODIFY established_year VARCHAR(10);

-- Step 2: convert legacy year-only values (YYYY) to YYYY-01-01
UPDATE teams
SET established_year = CONCAT(established_year, '-01-01')
WHERE established_year REGEXP '^[0-9]{4}$';

-- Step 3: convert column to DATE
ALTER TABLE teams MODIFY established_year DATE;
