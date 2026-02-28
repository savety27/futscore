-- Migration: rename admin_users role value from 'editor' to 'operator'
-- Safe order:
-- 1) convert existing data
-- 2) change enum definition

UPDATE `admin_users`
SET `role` = 'operator'
WHERE `role` = 'editor';

ALTER TABLE `admin_users`
MODIFY COLUMN `role` enum('superadmin','admin','operator','pelatih') DEFAULT 'admin';

