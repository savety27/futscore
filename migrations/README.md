# Migrations

Run database migrations from this directory using CLI during deployment.
Do not expose migration or schema-debug scripts in public web routes.

## Required migration for two-half lineups

- File: `migration_add_half_column_to_lineups.sql`
- Apply this migration before deploying code that writes `lineups.half` (for example `pelatih/match_lineup.php`).

## Required migration for dynamic event categories

- File: `migration_create_event_taxonomy.sql`
- Apply this migration before deploying `event.php` and `event_peserta.php` changes that group legacy event names into parent events and categories.

## Optional seed migration for current AAFI data

- File: `migration_seed_event_taxonomy_aafi_2026.sql`
- Apply this seed to map current legacy labels (`U-13` and `U-16`) into one event group (`LIGA AAFI BATAM 2026`).

Example command:

```bash
mysql -u <user> -p <database_name> < migrations/migration_add_half_column_to_lineups.sql
```
