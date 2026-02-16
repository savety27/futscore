-- Migration: Create event taxonomy mapping table
-- Description: Adds parent-event and category mapping for legacy event names.
-- Date: 2026-02-16

CREATE TABLE IF NOT EXISTS `event_taxonomy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_group_slug` varchar(140) COLLATE utf8mb4_general_ci NOT NULL,
  `event_group_name` varchar(140) COLLATE utf8mb4_general_ci NOT NULL,
  `category_name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `legacy_event_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_taxonomy_legacy_event` (`legacy_event_name`),
  KEY `idx_event_taxonomy_group` (`event_group_slug`,`sort_order`,`category_name`),
  KEY `idx_event_taxonomy_group_name` (`event_group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Example mapping (adjust names to your real event labels):
-- INSERT INTO event_taxonomy
-- (event_group_slug, event_group_name, category_name, legacy_event_name, sort_order)
-- VALUES
-- ('liga-aafi-batam-2026', 'LIGA AAFI BATAM 2026', 'U13', 'LIGA AAFI BATAM U-13 PUTRA 2026', 13),
-- ('liga-aafi-batam-2026', 'LIGA AAFI BATAM 2026', 'U16', 'LIGA AAFI BATAM U-16 PUTRA 2026', 16);
