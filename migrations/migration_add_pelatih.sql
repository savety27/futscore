-- Migration to add support for 'pelatih' role
-- Run this in phpMyAdmin or your database manager

-- 1. Add team_id column to admin_users table if it doesn't exist
-- Note: We add it after 'role' column for better organization
ALTER TABLE `admin_users`
ADD COLUMN `team_id` int(11) DEFAULT NULL AFTER `role`;

-- 2. Add Foreign Key constraint to link team_id to teams table
ALTER TABLE `admin_users`
ADD CONSTRAINT `fk_admin_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL;

-- 3. Update the role enum to include 'pelatih'
ALTER TABLE `admin_users`
MODIFY COLUMN `role` enum('superadmin','admin','editor','pelatih') DEFAULT 'admin';
