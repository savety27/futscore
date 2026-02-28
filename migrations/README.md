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

## Required migration for challenge-event relation

- File: `migration_add_event_id_to_challenges.sql`
- Apply this migration before deploying challenge pages that store/show selected event via `challenges.event_id`.

## Required migration for lineup uniform choices

- File: `migration_add_uniform_choice_columns_to_challenges.sql`
- Apply this migration before deploying `pelatih/match_lineup.php` changes that save selected kit colors per team.

## Required migration for exact bracket-to-match links

- File: `migration_add_challenge_id_columns_to_event_brackets.sql`
- Apply this migration before deploying bracket pages that must link each slot (`SF1/SF2/Final/3rd`) to an exact `challenges.id`.

## Required migration for official staff match history

- File: `migration_create_match_staff_assignments.sql`
- Apply this migration before deploying `pelatih/match_lineup.php` and `staff.php` changes that record/show staff participation per match and per event.

Example command:

```bash
mysql -u <user> -p <database_name> < migrations/migration_add_half_column_to_lineups.sql
```
