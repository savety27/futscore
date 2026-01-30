-- Migration: Cleanup BPJS and User ID from Team Staff
-- Description: Drops bpjs_registrations table and removes user_id link from team_staff as part of feature simplification.
-- Date: 2026-01-30

-- Drop bpjs_registrations table
DROP TABLE IF EXISTS `bpjs_registrations`;

-- Modify team_staff table
ALTER TABLE `team_staff` DROP FOREIGN KEY `team_staff_ibfk_2`;
ALTER TABLE `team_staff` DROP COLUMN `user_id`;
