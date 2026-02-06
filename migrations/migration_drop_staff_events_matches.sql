-- Migration: Drop Dead Features Tables
-- Description: Removes staff_events and staff_matches tables as they are non-functional and no longer referenced in the codebase.
-- Date: 2026-01-30

DROP TABLE IF EXISTS `staff_events`;
DROP TABLE IF EXISTS `staff_matches`;
