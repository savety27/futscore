-- Migration: add event mapping for operator accounts
-- 1) add nullable event_id column
-- 2) add index
-- 3) add foreign key to events(id)

ALTER TABLE `admin_users`
ADD COLUMN `event_id` INT(11) NULL AFTER `team_id`;

ALTER TABLE `admin_users`
ADD KEY `fk_admin_event` (`event_id`);

ALTER TABLE `admin_users`
ADD CONSTRAINT `fk_admin_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL;

