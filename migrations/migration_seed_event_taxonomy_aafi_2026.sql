-- Migration: Seed event taxonomy for LIGA AAFI BATAM 2026
-- Description: Maps legacy event names into one event group and dynamic categories.
-- Date: 2026-02-16

INSERT INTO event_taxonomy
    (event_group_slug, event_group_name, category_name, legacy_event_name, sort_order)
VALUES
    ('liga-aafi-batam-2026', 'LIGA AAFI BATAM 2026', 'U13', 'LIGA AAFI BATAM U-13 PUTRA 2026', 13),
    ('liga-aafi-batam-2026', 'LIGA AAFI BATAM 2026', 'U16', 'LIGA AAFI BATAM U-16 PUTRA 2026', 16),
    ('liga-aafi-batam-2026', 'LIGA AAFI BATAM 2026', 'U16 PUTRI', 'LIGA AAFI BATAM U-16 PUTRI 2026', 17)
ON DUPLICATE KEY UPDATE
    event_group_slug = VALUES(event_group_slug),
    event_group_name = VALUES(event_group_name),
    category_name = VALUES(category_name),
    sort_order = VALUES(sort_order),
    updated_at = CURRENT_TIMESTAMP;
