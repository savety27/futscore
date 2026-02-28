# Database Migrations

This directory contains SQL migration scripts for the Alvetrix database. These scripts are used to initialize the schema and apply incremental updates as features are developed.

> [!NOTE]
> This `README.md` is specifically structured to be easily parsed and understood by LLM agents assisting with development and database management.


> [!IMPORTANT]
> There is no automated migration tracker (like Phinx or Liquibase) in this project. You must apply these scripts manually using a database client or CLI. Always backup your database before running migrations.

## Getting Started

### Fresh Installation
If you are setting up the project for the first time:
1.  **Base Schema**: Import `futscore_db (8).sql` first. This contains the essential table structures and some initial data.
2.  **Incremental Updates**: Apply all relevant `migration_*.sql` files in chronological order (or follow the categorized list below).

### Existing Updates
If you are updating an existing installation:
1.  Check which features you are missing.
2.  Apply only the specific migration files needed for those features.

---

## Migration Inventory

### Core Schema & Maintenance
- `futscore_db (8).sql`: Base database dump including primary tables and seed data.
- `migration_change_established_year_to_date.sql`: Converts `teams.established_year` from YEAR to DATE format.
- `fix_lineups_goals_fk.sql`: Fixes foreign key constraints for `lineups` and `goals` to point to `challenges`.

### Feature: Lineups, Goals & Match Events
- `migration_add_half_column_to_lineups.sql`: Adds support for recording lineups per half (Babak 1/2).
- `migration_add_half_column_to_goals.sql`: Adds support for recording goals per half.
- `migration_add_uniform_choice_columns_to_challenges.sql`: Adds columns for kit/uniform color selection in matches.
- `migration_add_challenge_id_columns_to_event_brackets.sql`: Links bracket slots to specific challenge IDs for exact tracking.

### Feature: Event Management & Taxonomy
- `migration_create_event_taxonomy.sql`: Implements flexible event grouping and parent-category relations.
- `migration_seed_event_taxonomy_aafi_2026.sql`: Seed data for the AAFI 2026 event structure.
- `migration_add_event_id_to_challenges.sql`: Links challenges directly to events for better filtering.
- `migration_create_event_values_tables.sql`: Adds `event_team_values` (standings) and `player_event_cards` (discipline tracking).

### Feature: Staff & Match Officials (Perangkat)
- `migration_create_perangkat_tables.sql`: Creates tables for match officials (referees, etc.) and their licenses.
- `migration_create_match_staff_assignments.sql`: Enables assigning staff/officials to specific matches.
- `migration_add_half_to_match_staff_assignments.sql`: Support for per-half staff assignment.

### Feature: Authentication & Roles
- `migration_add_pelatih.sql`: Adds support for the 'pelatih' (coach) role and links users to teams.
- `migration_rename_editor_to_operator.sql`: Standardizes the role naming from 'editor' to 'operator'.
- `migration_add_event_id_to_admin_users.sql`: Permits mapping operator accounts to specific events.

### Other Features
- `migration_create_berita_table.sql`: Creates the news/articles system tables.

### Data Integrity & Cleanup
- `migration_add_unique_nik.sql`: Enforces unique NIK for players, officials, and registration.
- `migration_add_unique_player_name_per_team.sql`: Prevents duplicate player names globally.
- `migration_cleanup_team_staff_bpjs.sql`: Removes legacy BPJS and user-link columns from team staff.
- `migration_drop_staff_events_matches.sql`: Drops unused/non-functional staff relation tables.

---

## Usage Example (CLI)

To apply a migration file:

```bash
mysql -u <user> -p <database_name> < migrations/migration_name.sql
```

Example for a specific file:

```bash
mysql -u root -p futscore_db < migrations/migration_add_half_column_to_lineups.sql
```
