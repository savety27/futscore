-- Migration: create match_staff_assignments table
-- Purpose : official per-match assignment for team staff
-- Date    : 2026-02-26

CREATE TABLE IF NOT EXISTS `match_staff_assignments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `match_id` INT NOT NULL,
    `staff_id` INT NOT NULL,
    `team_id` INT NOT NULL,
    `role` VARCHAR(100) NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_match_staff_team` (`match_id`, `staff_id`, `team_id`),
    KEY `idx_msa_staff` (`staff_id`),
    KEY `idx_msa_match` (`match_id`),
    KEY `idx_msa_team` (`team_id`),
    CONSTRAINT `fk_msa_match` FOREIGN KEY (`match_id`) REFERENCES `challenges` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_msa_staff` FOREIGN KEY (`staff_id`) REFERENCES `team_staff` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_msa_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
